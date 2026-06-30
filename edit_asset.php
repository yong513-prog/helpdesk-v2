<?php
require 'header.php';
require 'db.php';
require_once 'audit_log.php';
require_once 'module_permissions.php';
require_once 'ticket_master_options.php';
require_once 'entity_upload_helper.php';
if(!isset($_SESSION['user_id'])){ header("Location: login.php"); exit; }
require_action_permission('manage_asset');

function ensure_asset_photo_column($pdo)
{
    try { $pdo->query("SELECT asset_photo FROM assets LIMIT 1"); }
    catch(Exception $e) { try { $pdo->exec("ALTER TABLE assets ADD COLUMN asset_photo VARCHAR(255) NULL AFTER remark"); } catch(Exception $ignore) {} }
}
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

function selected($a,$b){ return ($a == $b) ? 'selected' : ''; }
function upload_asset_photo($fieldName, $oldPhoto = '', $assetCode = '')
{
    if(!isset($_FILES[$fieldName]) || ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) == UPLOAD_ERR_NO_FILE) return $oldPhoto;
    if($_FILES[$fieldName]['error'] != UPLOAD_ERR_OK) die("Photo upload failed.");
    if($_FILES[$fieldName]['size'] > 5 * 1024 * 1024) die("Photo is too large. Maximum 5MB allowed.");
    $folderName = $assetCode !== '' ? $assetCode : 'uncategorized';
    $uploadDir = hd_entity_upload_dir('assets', $folderName);
    $ext = strtolower(pathinfo($_FILES[$fieldName]['name'] ?? '', PATHINFO_EXTENSION));
    if(!in_array($ext, ['jpg','jpeg','png','gif','webp'])) die("Invalid photo type. Only JPG, PNG, GIF and WEBP are allowed.");
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if(!in_array($finfo->file($_FILES[$fieldName]['tmp_name']), ['image/jpeg','image/png','image/gif','image/webp'])) die("Invalid photo file.");
    $fileName = 'asset_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if(!move_uploaded_file($_FILES[$fieldName]['tmp_name'], rtrim($uploadDir, '/\\') . '/' . $fileName)) die("Unable to save uploaded photo.");
    if($oldPhoto != '' && strpos($oldPhoto, 'uploads/assets/') === 0) { $oldPath=__DIR__.'/'.$oldPhoto; if(is_file($oldPath)) @unlink($oldPath); }
    return hd_entity_upload_relative('assets', $folderName, $fileName);
}
$branches = master_fetch_active_branches($pdo);
$types = master_fetch_active_asset_types($pdo);
$statuses = ['Active','Repair','Inactive','Disposed'];

ensure_asset_photo_column($pdo);
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
$stmt->execute([$id]);
$asset = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$asset){ die("Asset not found"); }
$currentAssetType = trim((string)($asset['asset_type'] ?? ''));
if($currentAssetType !== '' && !in_array($currentAssetType, $types, true)) { $types[] = $currentAssetType; }
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $asset_code=trim($_POST['asset_code'] ?? ''); $asset_name=trim($_POST['asset_name'] ?? ''); $asset_type=trim($_POST['asset_type'] ?? '');
    $brand=trim($_POST['brand'] ?? ''); $model=trim($_POST['model'] ?? ''); $serial_no=trim($_POST['serial_no'] ?? '');
    $branch=trim($_POST['branch'] ?? ''); $location=trim($_POST['location'] ?? ''); $status=$_POST['status'] ?? 'Active';
    $purchase_date=$_POST['purchase_date'] ?? null; $remark=trim($_POST['remark'] ?? '');
    if($asset_code=='' || $asset_name=='') die("Asset Code and Asset Name are required.");
    if(!in_array($status,$statuses)) die("Invalid status.");
    if($purchase_date=='') $purchase_date=null;
    $oldPhoto = $asset['asset_photo'] ?? '';
    if(isset($_POST['remove_photo']) && $_POST['remove_photo']=='1') {
        if($oldPhoto!='' && strpos($oldPhoto,'uploads/assets/')===0) { $oldPath=__DIR__.'/'.$oldPhoto; if(is_file($oldPath)) @unlink($oldPath); }
        $oldPhoto='';
    }
    $asset_photo = upload_asset_photo('asset_photo', $oldPhoto, $asset_code);
    $stmt=$pdo->prepare("UPDATE assets SET asset_code=?, asset_name=?, asset_type=?, brand=?, model=?, serial_no=?, branch=?, location=?, status=?, purchase_date=?, remark=?, asset_photo=? WHERE id=?");
    try{ $stmt->execute([$asset_code,$asset_name,$asset_type,$brand,$model,$serial_no,$branch,$location,$status,$purchase_date,$remark,$asset_photo,$id]); }
    catch(Exception $e){ die("Unable to update asset. Asset code may already exist."); }
    audit_log($pdo, 'Edit Asset', 'Edited Asset '.$asset_code);
    header("Location: asset_list.php"); exit;
}
?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1"><?= h(__('Edit Asset')) ?></h2><div class="text-muted"><?= h(__('Update asset details and equipment photo.')) ?></div></div><a href="asset_list.php" class="btn btn-outline-secondary"><?= h(__('Back to Asset List')) ?></a></div>
<div class="card shadow-sm border-0"><div class="card-body p-4"><form method="post" enctype="multipart/form-data"><div class="row"><div class="col-md-8">
<div class="row"><div class="col-md-6 mb-3"><label class="form-label fw-semibold"><?= h(__('Asset Code')) ?> *</label><input type="text" name="asset_code" class="form-control" value="<?= h($asset['asset_code']); ?>" required></div><div class="col-md-6 mb-3"><label class="form-label fw-semibold"><?= h(__('Asset Name')) ?> *</label><input type="text" name="asset_name" class="form-control" value="<?= h($asset['asset_name']); ?>" required></div></div>
<div class="row"><div class="col-md-4 mb-3"><label class="form-label fw-semibold"><?= h(__('Asset Type')) ?></label><select name="asset_type" class="form-select"><?php foreach($types as $type): ?><option value="<?= h($type); ?>" <?= selected($asset['asset_type'],$type); ?>><?= h(hd_asset_type_display_label($type)); ?></option><?php endforeach; ?></select><div class="form-text"><?= h(__('Controlled by Asset Type Management.')) ?></div></div><div class="col-md-4 mb-3"><label class="form-label fw-semibold"><?= h(__('Brand')) ?></label><input type="text" name="brand" class="form-control" value="<?= h($asset['brand']); ?>"></div><div class="col-md-4 mb-3"><label class="form-label fw-semibold"><?= h(__('Model')) ?></label><input type="text" name="model" class="form-control" value="<?= h($asset['model']); ?>"></div></div>
<div class="row"><div class="col-md-4 mb-3"><label class="form-label fw-semibold"><?= h(__('Serial No')) ?></label><input type="text" name="serial_no" class="form-control" value="<?= h($asset['serial_no']); ?>"></div><div class="col-md-4 mb-3"><label class="form-label fw-semibold"><?= h(__('Branch')) ?></label><select name="branch" class="form-select"><?php foreach($branches as $branch): ?><option class="hd-no-translate" value="<?= h($branch['branch_code']); ?>" <?= selected($asset['branch'],$branch['branch_code']); ?>><?= h($branch['branch_code'].' - '.$branch['branch_name']); ?></option><?php endforeach; ?></select></div><div class="col-md-4 mb-3"><label class="form-label fw-semibold"><?= h(__('Location')) ?></label><input type="text" name="location" class="form-control" value="<?= h($asset['location']); ?>"></div></div>
<div class="row"><div class="col-md-4 mb-3"><label class="form-label fw-semibold"><?= h(__('Status')) ?></label><select name="status" class="form-select"><?php foreach($statuses as $s): ?><option value="<?= h($s); ?>" <?= selected($asset['status'],$s); ?>><?= h($s); ?></option><?php endforeach; ?></select></div><div class="col-md-4 mb-3"><label class="form-label fw-semibold"><?= h(__('Purchase Date')) ?></label><input type="date" name="purchase_date" class="form-control" value="<?= h($asset['purchase_date']); ?>"></div></div>
<div class="mb-3"><label class="form-label fw-semibold"><?= h(__('Remark')) ?></label><textarea name="remark" rows="4" class="form-control"><?= h($asset['remark']); ?></textarea></div>
</div><div class="col-md-4"><div class="border rounded-3 p-3 bg-light h-100"><label class="form-label fw-semibold"><?= h(__('Asset Photo')) ?></label><div class="text-muted small mb-2"><?= h(__('Upload new photo to replace current photo. Maximum 5MB.')) ?></div><input type="file" name="asset_photo" id="assetPhoto" class="form-control" accept="image/*" capture="environment"><div class="mt-3 text-center"><?php if(!empty($asset['asset_photo'])): ?><img id="photoPreview" src="<?= h($asset['asset_photo']); ?>" style="max-width:100%; max-height:260px; object-fit:cover; border-radius:12px; border:1px solid #dee2e6;" alt="<?= h(__('Asset Photo')) ?>"><div class="form-check mt-2 text-start"><input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="removePhoto"><label class="form-check-label" for="removePhoto"><?= h(__('Remove current photo')) ?></label></div><?php else: ?><img id="photoPreview" src="" style="display:none; max-width:100%; max-height:260px; object-fit:cover; border-radius:12px; border:1px solid #dee2e6;" alt="<?= h(__('Photo Preview')) ?>"><div id="photoPlaceholder" class="border rounded-3 d-flex align-items-center justify-content-center text-muted" style="height:220px; background:#fff;"><?= h(__('No photo uploaded')) ?></div><?php endif; ?></div></div></div></div><hr><button type="submit" class="btn btn-primary"><?= h(__('Update Asset')) ?></button> <a href="asset_list.php" class="btn btn-outline-secondary"><?= h(__('Cancel')) ?></a> <a href="asset_history.php?id=<?= (int)$asset['id']; ?>" class="btn btn-outline-info"><?= h(__('History')) ?></a></form></div></div>
<script>document.getElementById('assetPhoto').addEventListener('change',function(e){const file=e.target.files[0]; let preview=document.getElementById('photoPreview'); const ph=document.getElementById('photoPlaceholder'); if(file){preview.src=URL.createObjectURL(file); preview.style.display='inline-block'; if(ph) ph.style.display='none';}});</script>
<?php require 'footer.php'; ?>
