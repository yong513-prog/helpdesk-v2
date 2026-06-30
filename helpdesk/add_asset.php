<?php

require 'header.php';
require 'db.php';
require_once 'audit_log.php';
require_once 'module_permissions.php';
require_once 'ticket_master_options.php';
require_once 'entity_upload_helper.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

require_action_permission('manage_asset');

function h($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

if(!function_exists('hd_asset_type_display_label')){
    function hd_asset_type_display_label($name){
        $name = (string)$name;
        $lang = function_exists('hd_lang') ? hd_lang() : ($_GET['lang'] ?? 'en');
        $map = [
            'zh' => [
                'Printer'=>'打印机',
                'Barcode Printer'=>'条码打印机',
                'Scanner'=>'扫描器',
                'Cash Drawer'=>'钱箱',
                'Barcode Scanner'=>'条码扫描器',
                'Weighing Scale'=>'电子秤',
                'PC'=>'电脑',
            ],
            'ms' => [
                'Printer'=>'Pencetak',
                'Barcode Printer'=>'Pencetak Barcode',
                'Scanner'=>'Pengimbas',
                'Cash Drawer'=>'Laci Tunai',
                'Barcode Scanner'=>'Pengimbas Barcode',
                'Weighing Scale'=>'Penimbang',
            ],
        ];
        return $map[$lang][$name] ?? (function_exists('__') ? __($name) : $name);
    }
}


function ensure_asset_photo_column($pdo)
{
    try {
        $pdo->query("SELECT asset_photo FROM assets LIMIT 1");
    } catch(Exception $e) {
        try { $pdo->exec("ALTER TABLE assets ADD COLUMN asset_photo VARCHAR(255) NULL AFTER remark"); } catch(Exception $ignore) {}
    }
}

function upload_asset_photo($fieldName, $oldPhoto = '', $assetCode = '')
{
    if(!isset($_FILES[$fieldName]) || ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) == UPLOAD_ERR_NO_FILE) {
        return $oldPhoto;
    }

    if($_FILES[$fieldName]['error'] != UPLOAD_ERR_OK) {
        die("Photo upload failed.");
    }

    if($_FILES[$fieldName]['size'] > 5 * 1024 * 1024) {
        die("Photo is too large. Maximum 5MB allowed.");
    }

    $folderName = $assetCode !== '' ? $assetCode : 'uncategorized';
    $uploadDir = hd_entity_upload_dir('assets', $folderName);
    if(!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $originalName = $_FILES[$fieldName]['name'] ?? '';
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];

    if(!in_array($ext, $allowed)) {
        die("Invalid photo type. Only JPG, PNG, GIF and WEBP are allowed.");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($_FILES[$fieldName]['tmp_name']);
    $allowedMime = ['image/jpeg','image/png','image/gif','image/webp'];
    if(!in_array($mime, $allowedMime)) {
        die("Invalid photo file.");
    }

    $fileName = 'asset_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = rtrim($uploadDir, '/\\') . '/' . $fileName;

    if(!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $target)) {
        die("Unable to save uploaded photo.");
    }

    if($oldPhoto != '' && strpos($oldPhoto, 'uploads/assets/') === 0) {
        $oldPath = __DIR__ . '/' . $oldPhoto;
        if(is_file($oldPath)) { @unlink($oldPath); }
    }

    return hd_entity_upload_relative('assets', $folderName, $fileName);
}

ensure_asset_photo_column($pdo);
$assetTypes = master_fetch_active_asset_types($pdo);
$branches = master_fetch_active_branches($pdo);

if($_SERVER['REQUEST_METHOD'] == 'POST')
{
    $asset_code = trim($_POST['asset_code'] ?? '');
    $asset_name = trim($_POST['asset_name'] ?? '');
    $asset_type = trim($_POST['asset_type'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $serial_no = trim($_POST['serial_no'] ?? '');
    $branch = trim($_POST['branch'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $status = $_POST['status'] ?? 'Active';
    $purchase_date = $_POST['purchase_date'] ?? null;
    $remark = trim($_POST['remark'] ?? '');
    $allowedStatus = ['Active','Repair','Inactive','Disposed'];

    if($asset_code == '' || $asset_name == '')
    {
        die("Asset Code and Asset Name are required.");
    }

    if(!in_array($status, $allowedStatus))
    {
        die("Invalid status.");
    }

    if($purchase_date == '')
    {
        $purchase_date = null;
    }

    $asset_photo = upload_asset_photo('asset_photo', '', $asset_code);

    $stmt = $pdo->prepare("
        INSERT INTO assets
        (asset_code, asset_name, asset_type, brand, model, serial_no, branch, location, status, purchase_date, remark, asset_photo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    try
    {
        $stmt->execute([$asset_code, $asset_name, $asset_type, $brand, $model, $serial_no, $branch, $location, $status, $purchase_date, $remark, $asset_photo]);
    }
    catch(Exception $e)
    {
        die("Unable to save asset. Asset code may already exist.");
    }

    audit_log($pdo, 'Add Asset', 'Added Asset '.$asset_code);

    header("Location: asset_list.php");
    exit;
}

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">Add Asset</h2>
        <div class="text-muted"><?= __('Register equipment with photo, branch, serial number and purchase information.') ?></div>
    </div>
    <a href="asset_list.php" class="btn btn-outline-secondary"><?= __('Back to Asset List') ?></a>
</div>

<div class="card shadow-sm border-0">
<div class="card-body p-4">
<form method="post" enctype="multipart/form-data">

<div class="row">
<div class="col-md-8">

<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">Asset Code <span class="text-danger">*</span></label>
<input type="text" name="asset_code" class="form-control" placeholder="POS-KB-001" required>
</div>

<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">Asset Name <span class="text-danger">*</span></label>
<input type="text" name="asset_name" class="form-control" placeholder="POS Counter 1" required>
</div>
</div>

<div class="row">
<div class="col-md-4 mb-3">
<label class="form-label fw-semibold"><?= h(__('Asset Type')) ?></label>
<select name="asset_type" class="form-select">
<option value="">-- <?= h(__('Select Asset Type')) ?> --</option>
<?php foreach($assetTypes as $type): ?>
<option value="<?= h($type); ?>"><?= h(hd_asset_type_display_label($type)); ?></option>
<?php endforeach; ?>
</select>
<div class="form-text"><?= __('Controlled by Asset Type Management.') ?></div>
</div>

<div class="col-md-4 mb-3">
<label class="form-label fw-semibold"><?= h(__('Brand')) ?></label>
<input type="text" name="brand" class="form-control" placeholder="<?= h(__('Brand')) ?>">
</div>

<div class="col-md-4 mb-3">
<label class="form-label fw-semibold"><?= h(__('Model')) ?></label>
<input type="text" name="model" class="form-control" placeholder="<?= h(__('Model')) ?>">
</div>
</div>

<div class="row">
<div class="col-md-4 mb-3">
<label class="form-label fw-semibold">Serial No</label>
<input type="text" name="serial_no" class="form-control" placeholder="Serial number">
</div>

<div class="col-md-4 mb-3">
<label class="form-label fw-semibold">Branch</label>
<select name="branch" class="form-select">
<option value="">-- Select Branch --</option>
<?php foreach($branches as $b): ?>
<option class="hd-no-translate" value="<?= h($b['branch_code']); ?>"><?= h($b['branch_code'].' - '.$b['branch_name']); ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-4 mb-3">
<label class="form-label fw-semibold">Location</label>
<input type="text" name="location" class="form-control" placeholder="Counter 1 / Office / Server Rack">
</div>
</div>

<div class="row">
<div class="col-md-4 mb-3">
<label class="form-label fw-semibold">Status</label>
<select name="status" class="form-select">
<option value="Active"><?= h(__('Active')) ?></option>
<option value="Repair"><?= h(__('Repair')) ?></option>
<option value="Inactive"><?= h(__('Inactive')) ?></option>
<option value="Disposed"><?= h(__('Disposed')) ?></option>
</select>
</div>

<div class="col-md-4 mb-3">
<label class="form-label fw-semibold">Purchase Date</label>
<input type="date" name="purchase_date" class="form-control">
</div>
</div>

<div class="mb-3">
<label class="form-label fw-semibold"><?= __('Remark') ?></label>
<textarea name="remark" rows="4" class="form-control" placeholder="<?= __('Warranty, supplier, maintenance note...') ?>"></textarea>
</div>

</div>

<div class="col-md-4">
    <div class="border rounded-3 p-3 bg-light h-100">
        <label class="form-label fw-semibold"><?= __('Asset Photo') ?></label>
        <div class="text-muted small mb-2"><?= __('Upload equipment photo. JPG, PNG, GIF or WEBP. Maximum 5MB.') ?></div>
        <input type="file" name="asset_photo" id="assetPhoto" class="form-control" accept="image/*" capture="environment">
        <div class="mt-3 text-center">
            <img id="photoPreview" src="" style="display:none; max-width:100%; max-height:260px; object-fit:cover; border-radius:12px; border:1px solid #dee2e6;" alt="Photo Preview">
            <div id="photoPlaceholder" class="border rounded-3 d-flex align-items-center justify-content-center text-muted" style="height:220px; background:#fff;">
                <?= __('No photo selected') ?>
            </div>
        </div>
    </div>
</div>
</div>

<hr>

<button type="submit" class="btn btn-primary"><?= __('Save Asset') ?></button>
<a href="asset_list.php" class="btn btn-outline-secondary">Cancel</a>

</form>
</div>
</div>

<script>
document.getElementById('assetPhoto').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('photoPreview');
    const placeholder = document.getElementById('photoPlaceholder');
    if(file) {
        preview.src = URL.createObjectURL(file);
        preview.style.display = 'inline-block';
        placeholder.style.display = 'none';
    } else {
        preview.src = '';
        preview.style.display = 'none';
        placeholder.style.display = 'flex';
    }
});
</script>

<?php require 'footer.php'; ?>
