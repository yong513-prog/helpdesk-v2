<?php
require 'header.php';
require 'db.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

require_once 'module_permissions.php';
require_module_permission('asset_type_management');

function esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ensure_asset_type_master(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS asset_type_master (
            id INT NOT NULL AUTO_INCREMENT,
            type_name VARCHAR(100) NOT NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_asset_type_name (type_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /*
     * IMPORTANT:
     * Do not re-seed default Asset Types every time this page loads.
     * Previously, deleted default types were inserted back automatically by INSERT IGNORE.
     * Now defaults are only inserted when the table is empty.
     */
    $count = (int)$pdo->query("SELECT COUNT(*) FROM asset_type_master")->fetchColumn();
    if($count > 0)
    {
        return;
    }

    $defaults = [
        'POS','Printer','Barcode Printer','Scanner','PC','Laptop','Server',
        'Network Switch','Router','Firewall','CCTV','DVR','NVR','UPS',
        'Cash Drawer','Barcode Scanner','Weighing Scale','Touch Screen',
        'Customer Display','Tablet','Mobile Device','Other'
    ];

    $stmt = $pdo->prepare("INSERT INTO asset_type_master (type_name, status, sort_order) VALUES (?, 1, ?)");
    foreach($defaults as $i => $name)
    {
        $stmt->execute([$name, ($i + 1) * 10]);
    }
}

function get_asset_type_name(PDO $pdo, int $id): string
{
    $stmt = $pdo->prepare("SELECT type_name FROM asset_type_master WHERE id = ?");
    $stmt->execute([$id]);
    return (string)$stmt->fetchColumn();
}

function asset_type_usage_count(PDO $pdo, string $typeName): int
{
    try
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE UPPER(TRIM(asset_type)) = UPPER(TRIM(?))");
        $stmt->execute([$typeName]);
        return (int)$stmt->fetchColumn();
    }
    catch(Exception $e)
    {
        return 0;
    }
}

function redirect_with_message(string $message = '', string $error = '')
{
    header("Location: asset_type_management.php?msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit;
}

ensure_asset_type_master($pdo);

$message = trim((string)($_GET['msg'] ?? ''));
$error = trim((string)($_GET['err'] ?? ''));

/*
 * Direct GET delete is used intentionally here because some browser/table layouts
 * break multiple small POST forms inside table rows. This makes Delete reliable.
 */
if(isset($_GET['delete_id']))
{
    $id = (int)$_GET['delete_id'];

    if($id <= 0)
    {
        redirect_with_message('', 'Invalid Asset Type.');
    }

    $deleteTypeName = get_asset_type_name($pdo, $id);

    if($deleteTypeName === '')
    {
        redirect_with_message('', 'Asset Type not found.');
    }

    $used = asset_type_usage_count($pdo, $deleteTypeName);

    if($used > 0)
    {
        $stmt = $pdo->prepare("
            UPDATE asset_type_master
            SET status = 0,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        redirect_with_message('This Asset Type is already used by assets, so it was disabled instead of deleted.');
    }

    $stmt = $pdo->prepare("DELETE FROM asset_type_master WHERE id = ?");
    $stmt->execute([$id]);

    redirect_with_message('Unused Asset Type deleted successfully.');
}

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $action = strtolower(trim($_POST['action'] ?? ''));
    $type_name = trim($_POST['type_name'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $status = (int)($_POST['status'] ?? 1);
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if($action === 'add')
    {
        if($type_name === '')
        {
            redirect_with_message('', 'Asset Type cannot be empty.');
        }

        try
        {
            $stmt = $pdo->prepare("
                INSERT INTO asset_type_master
                (type_name, status, sort_order)
                VALUES (?, 1, ?)
            ");
            $stmt->execute([$type_name, $sort_order]);
            redirect_with_message('Asset Type added successfully.');
        }
        catch(Exception $e)
        {
            redirect_with_message('', 'Add failed. Asset Type may already exist.');
        }
    }

    if($action === 'edit')
    {
        if($id <= 0 || $type_name === '')
        {
            redirect_with_message('', 'Invalid data.');
        }

        try
        {
            $stmt = $pdo->prepare("
                UPDATE asset_type_master
                SET type_name = ?,
                    status = ?,
                    sort_order = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$type_name, $status, $sort_order, $id]);
            redirect_with_message('Asset Type updated successfully.');
        }
        catch(Exception $e)
        {
            redirect_with_message('', 'Update failed. Asset Type may already exist.');
        }
    }

    if($action === 'toggle')
    {
        if($id <= 0)
        {
            redirect_with_message('', 'Invalid Asset Type.');
        }

        $stmt = $pdo->prepare("
            UPDATE asset_type_master
            SET status = CASE WHEN status = 1 THEN 0 ELSE 1 END,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        redirect_with_message('Status updated successfully.');
    }
}

$stmt = $pdo->query("
    SELECT *
    FROM asset_type_master
    ORDER BY sort_order ASC, type_name ASC
");
$types = $stmt->fetchAll(PDO::FETCH_ASSOC);

$usageMap = [];
try
{
    $usageStmt = $pdo->query("
        SELECT UPPER(TRIM(asset_type)) AS asset_type_key, COUNT(*) AS total
        FROM assets
        GROUP BY UPPER(TRIM(asset_type))
    ");
    foreach($usageStmt->fetchAll(PDO::FETCH_ASSOC) as $u)
    {
        $usageMap[(string)$u['asset_type_key']] = (int)$u['total'];
    }
}
catch(Exception $e)
{
    $usageMap = [];
}
?>


<style>
/* AH style / color alignment */
.master-page-header,.ah-page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    margin-bottom:18px;
}
.master-page-header h2,.ah-page-header h2{
    font-size:30px;
    font-weight:850;
    color:#0f172a;
}
.master-card,.ah-card{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:18px;
    box-shadow:0 10px 24px rgba(15,23,42,.06);
    overflow:hidden;
    margin-bottom:18px;
}
.master-card-header,.ah-card-header{
    padding:18px 20px;
    border-bottom:1px solid #eef2f7;
    display:flex;
    justify-content:space-between;
    align-items:center;
    background:#fff;
}
.master-card-body,.ah-card-body{
    padding:20px;
}
.master-table td,.master-table th,
.ah-table td,.ah-table th{
    vertical-align:middle;
}
.ah-actions,.action-buttons,.status-action-group{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
}
.ah-search,.search-input{max-width:360px;}
.ah-btn-add{background:#198754;border-color:#198754;color:#fff;font-weight:800;}
.ah-btn-save{background:#0d6efd;border-color:#0d6efd;color:#fff;font-weight:800;}
.ah-btn-toggle{background:#ffc107;border-color:#ffc107;color:#111827;font-weight:800;}
.ah-btn-delete{background:#dc3545;border-color:#dc3545;color:#fff;font-weight:800;}
.ah-info{
    background:#cff4fc;
    border:1px solid #9eeaf9;
    color:#055160;
    border-radius:12px;
}
@media(max-width:768px){
    .master-page-header,.ah-page-header{
        flex-direction:column;
        align-items:flex-start;
    }
    .master-page-header .btn,.ah-page-header .btn{
        width:100%;
        min-height:44px;
        border-radius:13px;
        display:flex;
        justify-content:center;
        align-items:center;
    }
}
</style>

<style>
/* Mobile-only card format; desktop table remains unchanged */
@media (max-width: 768px){
  .table-responsive{overflow:visible!important;}
  table.hd-mobile-card-table{border-collapse:separate!important;border-spacing:0 14px!important;width:100%!important;}
  table.hd-mobile-card-table thead{display:none!important;}
  table.hd-mobile-card-table tbody, table.hd-mobile-card-table tr, table.hd-mobile-card-table td{display:block!important;width:100%!important;}
  table.hd-mobile-card-table tr{background:#fff!important;border:1px solid #e5e7eb!important;border-radius:18px!important;margin:0 0 14px!important;padding:14px!important;box-shadow:0 10px 24px rgba(15,23,42,.06)!important;overflow:hidden!important;}
  table.hd-mobile-card-table td{border:0!important;border-bottom:1px dashed #e5e7eb!important;padding:10px 0!important;min-height:0!important;}
  table.hd-mobile-card-table td:last-child{border-bottom:0!important;padding-bottom:0!important;}
  table.hd-mobile-card-table td::before{display:block!important;font-size:12px!important;font-weight:900!important;color:#64748b!important;text-transform:uppercase!important;letter-spacing:.02em!important;margin-bottom:6px!important;}
  table.hd-mobile-card-table td:first-child{font-size:18px!important;font-weight:900!important;color:#0f172a!important;}
  table.hd-mobile-card-table input.form-control,
  table.hd-mobile-card-table select.form-select{width:100%!important;min-height:44px!important;font-size:16px!important;border-radius:12px!important;}
  table.hd-mobile-card-table .btn{min-height:42px!important;border-radius:12px!important;font-weight:900!important;padding:.55rem .8rem!important;}
  table.hd-mobile-card-table td:last-child,
  table.hd-mobile-card-table .action-buttons,
  table.hd-mobile-card-table .status-action-group,
  table.hd-mobile-card-table .ah-actions{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:8px!important;align-items:stretch!important;}
  table.hd-mobile-card-table td:last-child .btn,
  table.hd-mobile-card-table .action-buttons .btn,
  table.hd-mobile-card-table .status-action-group .btn,
  table.hd-mobile-card-table .ah-actions .btn{width:100%!important;margin:0!important;white-space:normal!important;}
  table.hd-mobile-card-table .badge,
  table.hd-mobile-card-table .usage-badge{font-size:13px!important;min-height:28px!important;padding:.35rem .65rem!important;border-radius:999px!important;}
  .master-card-header,.ah-card-header{flex-direction:column!important;align-items:stretch!important;gap:12px!important;}
  .search-input,.ah-search{max-width:100%!important;width:100%!important;}
}
</style>

<style>
@media(max-width:768px){
  table.hd-mobile-card-table td:nth-child(1)::before{content:"序号";}
  table.hd-mobile-card-table td:nth-child(2)::before{content:"资产类型";}
  table.hd-mobile-card-table td:nth-child(3)::before{content:"排序";}
  table.hd-mobile-card-table td:nth-child(4)::before{content:"状态";}
  table.hd-mobile-card-table td:nth-child(5)::before{content:"已使用";}
  table.hd-mobile-card-table td:nth-child(6)::before{content:"操作";}
}
</style>
<div class="master-page-header">
    <div>
        <h2 class="mb-1">Asset Type Management</h2>
        <div class="text-muted">Maintain Asset Type list used by Add Asset, Edit Asset and Asset filtering.</div>
    </div>
    <a href="administration.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Administration</a>
</div>

<?php if($message !== ''): ?><div class="alert alert-success"><?= esc($message); ?></div><?php endif; ?>
<?php if($error !== ''): ?><div class="alert alert-danger"><?= esc($error); ?></div><?php endif; ?>

<div>
    <div class="master-card">
        <div class="master-card-header"><div><strong>Add Asset Type</strong><div class="text-muted small">Maintain asset type records.</div></div></div>
        <div class="master-card-body">
                <form method="post" action="asset_type_management.php">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Asset Type Name</label>
                        <input type="text" name="type_name" class="form-control" placeholder="Example: POS, Printer, Router" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>
                    <button type="submit" class="btn ah-btn-add w-100"><i class="bi bi-plus-circle"></i> Add Asset Type</button>
                </form>
            </div>
        </div>
    </div>

    <div class="master-card">
        <div class="master-card-header"><div><strong>Asset Type List</strong><div class="text-muted small">Active asset types appear in Add Asset / Edit Asset dropdowns.</div></div></div>
        <div class="master-card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle master-table hd-mobile-card-table">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:70px;">No.</th>
                                <th>Asset Type</th>
                                <th style="width:120px;">Sort</th>
                                <th style="width:130px;">Status</th>
                                <th style="width:110px;">Used</th>
                                <th style="width:270px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if(!$types): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No asset type found.</td></tr>
                        <?php endif; ?>

                        <?php $no = 1; ?>
                        <?php foreach($types as $row): ?>
                            <?php
                                $rowId = (int)$row['id'];
                                $formId = 'assetTypeForm' . $rowId;
                                $usedCount = $usageMap[strtoupper(trim((string)$row['type_name']))] ?? 0;
                            ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td>
                                    <form id="<?= esc($formId); ?>" method="post" action="asset_type_management.php"></form>
                                    <input form="<?= esc($formId); ?>" type="hidden" name="action" value="edit">
                                    <input form="<?= esc($formId); ?>" type="hidden" name="id" value="<?= $rowId; ?>">
                                    <input form="<?= esc($formId); ?>" type="text" name="type_name" class="form-control" value="<?= esc($row['type_name']); ?>" required>
                                </td>
                                <td>
                                    <input form="<?= esc($formId); ?>" type="number" name="sort_order" class="form-control" value="<?= (int)$row['sort_order']; ?>">
                                </td>
                                <td>
                                    <select form="<?= esc($formId); ?>" name="status" class="form-select">
                                        <option value="1" <?= ((int)$row['status'] === 1) ? 'selected' : ''; ?>>Active</option>
                                        <option value="0" <?= ((int)$row['status'] === 0) ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $usedCount > 0 ? 'primary' : 'secondary'; ?>">
                                        <?= $usedCount; ?>
                                    </span>
                                </td>
                                <td class="text-nowrap">
                                    <button form="<?= esc($formId); ?>" type="submit" class="btn btn-sm ah-btn-save">Save</button>

                                    <form method="post" action="asset_type_management.php" class="d-inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $rowId; ?>">
                                        <button type="submit" class="btn btn-sm ah-btn-toggle"><?= ((int)$row['status'] === 1) ? 'Disable' : 'Enable'; ?></button>
                                    </form>

                                    <?php if($usedCount <= 0): ?>
                                        <a href="asset_type_management.php?delete_id=<?= $rowId; ?>"
                                           class="btn btn-sm ah-btn-delete"
                                           onclick="return confirm('Delete this unused asset type?');">Delete</a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Already used by assets. Disable instead.">In Use</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="alert ah-info mt-3 mb-0">
                    <strong>Linked pages:</strong> Add Asset and Edit Asset will automatically use active Asset Types from this list.
                    Asset Types already used by assets cannot be deleted; use Disable instead. Deleted unused default types will no longer come back automatically.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
