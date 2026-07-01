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
        $stmt = $pdo->prepare("UPDATE asset_type_master SET status = 0, updated_at = NOW() WHERE id = ?");
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
            $stmt = $pdo->prepare("INSERT INTO asset_type_master (type_name, status, sort_order) VALUES (?, 1, ?)");
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
            $stmt = $pdo->prepare("UPDATE asset_type_master SET type_name = ?, status = ?, sort_order = ?, updated_at = NOW() WHERE id = ?");
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

        $stmt = $pdo->prepare("UPDATE asset_type_master SET status = CASE WHEN status = 1 THEN 0 ELSE 1 END, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        redirect_with_message('Status updated successfully.');
    }
}

$stmt = $pdo->query("SELECT * FROM asset_type_master ORDER BY sort_order ASC, type_name ASC");
$types = $stmt->fetchAll(PDO::FETCH_ASSOC);

$usageMap = [];
try
{
    $usageStmt = $pdo->query("SELECT UPPER(TRIM(asset_type)) AS asset_type_key, COUNT(*) AS total FROM assets GROUP BY UPPER(TRIM(asset_type))");
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

<link rel="stylesheet" href="assets/css/admin-ui-v2.css?v=20260701-asset-kb-unified1">

<div class="admin-v2-page">
    <div class="admin-v2-header">
        <div>
            <h2 class="mb-1">Asset Type Management</h2>
            <div class="text-muted">Maintain Asset Type list used by Add Asset, Edit Asset and Asset filtering.</div>
        </div>
        <a href="administration.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Administration
        </a>
    </div>

    <?php if($message !== ''): ?><div class="alert alert-success"><?= esc($message); ?></div><?php endif; ?>
    <?php if($error !== ''): ?><div class="alert alert-danger"><?= esc($error); ?></div><?php endif; ?>

    <div class="admin-v2-card">
        <div class="admin-v2-card-header">
            <div>
                <strong>Add Asset Type</strong>
                <div class="text-muted small">Add a new asset type option for assets.</div>
            </div>
        </div>
        <div class="admin-v2-card-body">
            <form method="post" action="asset_type_management.php" class="admin-v2-add-form">
                <input type="hidden" name="action" value="add">

                <div class="mb-3">
                    <label class="form-label">Asset Type Name</label>
                    <input type="text" name="type_name" class="form-control" placeholder="Example: POS, Printer, Router" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-plus-circle"></i> Add Asset Type
                </button>
            </form>
        </div>
    </div>

    <div class="admin-v2-card">
        <div class="admin-v2-card-header">
            <div>
                <strong>Asset Type List</strong>
                <div class="text-muted small">Active asset types appear in Add Asset / Edit Asset dropdowns.</div>
            </div>
            <div class="input-group admin-v2-search">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="masterSearch" class="form-control" placeholder="Search...">
            </div>
        </div>
        <div class="admin-v2-card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle admin-v2-table hd-mobile-card-table hd-table-asset" id="masterTable">
                    <thead class="table-light">
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

                    <?php $no = 1; foreach($types as $row): ?>
                        <?php
                            $rowId = (int)$row['id'];
                            $formId = 'assetTypeForm' . $rowId;
                            $usedCount = $usageMap[strtoupper(trim((string)$row['type_name']))] ?? 0;
                        ?>
                        <tr>
                            <td class="fw-bold text-center"><?= $no++; ?></td>
                            <td>
                                <form id="<?= esc($formId); ?>" method="post" action="asset_type_management.php"></form>
                                <input form="<?= esc($formId); ?>" type="hidden" name="action" value="edit">
                                <input form="<?= esc($formId); ?>" type="hidden" name="id" value="<?= $rowId; ?>">
                                <input form="<?= esc($formId); ?>" type="text" name="type_name" class="form-control form-control-sm" value="<?= esc($row['type_name']); ?>" required>
                            </td>
                            <td>
                                <input form="<?= esc($formId); ?>" type="number" name="sort_order" class="form-control form-control-sm" value="<?= (int)$row['sort_order']; ?>">
                            </td>
                            <td>
                                <select form="<?= esc($formId); ?>" name="status" class="form-select form-select-sm">
                                    <option value="1" <?= ((int)$row['status'] === 1) ? 'selected' : ''; ?>>Active</option>
                                    <option value="0" <?= ((int)$row['status'] === 0) ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </td>
                            <td>
                                <span class="badge bg-<?= $usedCount > 0 ? 'primary' : 'secondary'; ?>"><?= $usedCount; ?></span>
                            </td>
                            <td>
                                <div class="admin-v2-actions">
                                    <button form="<?= esc($formId); ?>" type="submit" class="btn btn-sm btn-primary">Save</button>

                                    <form method="post" action="asset_type_management.php" class="d-inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $rowId; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning"><?= ((int)$row['status'] === 1) ? 'Disable' : 'Enable'; ?></button>
                                    </form>

                                    <?php if($usedCount <= 0): ?>
                                        <a href="asset_type_management.php?delete_id=<?= $rowId; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this unused asset type?');">Delete</a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-in-use" disabled title="Already used by assets. Disable instead.">In Use</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-info admin-v2-info">
        <strong>Linked pages:</strong> Add Asset and Edit Asset will automatically use active Asset Types from this list.
        Asset Types already used by assets cannot be deleted; use Disable instead.
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const search = document.getElementById('masterSearch');
    const table = document.getElementById('masterTable');
    if(search && table)
    {
        search.addEventListener('input', function(){
            const keyword = this.value.toLowerCase().trim();
            table.querySelectorAll('tbody tr').forEach(function(row){
                row.style.display = row.innerText.toLowerCase().includes(keyword) ? '' : 'none';
            });
        });
    }
});
</script>

<?php require 'footer.php'; ?>
