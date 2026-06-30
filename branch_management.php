<?php

require 'header.php';
require 'db.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

require_once 'module_permissions.php';
require_module_permission('branch_management');

function esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $action = $_POST['action'] ?? '';
    $branch_code = trim($_POST['branch_code'] ?? '');
    $branch_name = trim($_POST['branch_name'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $status = (int)($_POST['status'] ?? 1);

    if($action === 'add')
    {
        if($branch_code === '' || $branch_name === '')
        {
            $error = 'Required field cannot be empty.';
        }
        else
        {
            try
            {
                $stmt = $pdo->prepare("
                    INSERT INTO branch_master
                    (branch_code, branch_name, status)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                $branch_code,
                $branch_name,
                1
                ]);
                $message = 'Branch Management added successfully.';
            }
            catch(Exception $e)
            {
                $error = 'Add failed. Data may already exist.';
            }
        }
    }

    if($action === 'edit')
    {
        if($id <= 0 || $branch_code === '' || $branch_name === '')
        {
            $error = 'Invalid data.';
        }
        else
        {
            try
            {
                $stmt = $pdo->prepare("
                    UPDATE branch_master
                    SET branch_code = ?,
                        branch_name = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $branch_code,
                    $branch_name,
                    $status,
                    $id
                ]);
                $message = 'Branch Management updated successfully.';
            }
            catch(Exception $e)
            {
                $error = 'Update failed. Data may already exist.';
            }
        }
    }

    if($action === 'delete')
    {
        if($id > 0)
        {
            $stmt = $pdo->prepare("SELECT branch_code FROM branch_master WHERE id = ?");
            $stmt->execute([$id]);
            $deleteCode = trim((string)$stmt->fetchColumn());

            if($deleteCode === '')
            {
                $error = 'Branch not found.';
            }
            else
            {
                $used = 0;
                try { $s = $pdo->prepare("SELECT COUNT(*) FROM users WHERE branch = ? OR branch_access LIKE ? OR ticket_branch_access LIKE ?"); $s->execute([$deleteCode, '%'.$deleteCode.'%', '%'.$deleteCode.'%']); $used += (int)$s->fetchColumn(); } catch(Exception $e) {}
                try { $s = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE branch = ?"); $s->execute([$deleteCode]); $used += (int)$s->fetchColumn(); } catch(Exception $e) {}
                try { $s = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE branch = ?"); $s->execute([$deleteCode]); $used += (int)$s->fetchColumn(); } catch(Exception $e) {}

                if($used > 0)
                {
                    $stmt = $pdo->prepare("UPDATE branch_master SET status = 0, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Branch is already used by Users / Tickets / Assets, so it has been disabled instead of deleted.';
                }
                else
                {
                    $stmt = $pdo->prepare("DELETE FROM branch_master WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Unused Branch deleted successfully.';
                }
            }
        }
    }

    if($action === 'toggle')
    {
        if($id > 0)
        {
            $stmt = $pdo->prepare("
                UPDATE branch_master
                SET status = CASE WHEN status = 1 THEN 0 ELSE 1 END,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $message = 'Status updated successfully.';
        }
    }
}

$stmt = $pdo->query("
    SELECT *
    FROM branch_master
    ORDER BY status DESC, branch_code ASC, branch_name ASC
");

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
.master-page-header{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:18px;}
.master-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 10px 24px rgba(15,23,42,.06);overflow:hidden;margin-bottom:18px;}
.master-card-header{padding:18px 20px;border-bottom:1px solid #eef2f7;display:flex;justify-content:space-between;align-items:center;}
.master-card-body{padding:20px;}
.master-table td,.master-table th{vertical-align:middle;}
.action-buttons{display:flex;flex-wrap:wrap;gap:6px;}
.search-input{max-width:360px;}
@media(max-width:768px){.master-page-header{flex-direction:column;align-items:flex-start;}}


/* Mobile-only admin table optimization: desktop remains unchanged */
@media (max-width: 768px){
  .table-responsive{overflow:visible!important;}
  table.hd-mobile-card-table{border-collapse:separate!important;border-spacing:0 14px!important;width:100%!important;}
  table.hd-mobile-card-table thead{display:none!important;}
  table.hd-mobile-card-table tbody, table.hd-mobile-card-table tr, table.hd-mobile-card-table td{display:block!important;width:100%!important;}
  table.hd-mobile-card-table tr{background:#fff!important;border:1px solid #e5e7eb!important;border-radius:18px!important;margin:0 0 14px!important;padding:14px!important;box-shadow:0 10px 24px rgba(15,23,42,.06)!important;overflow:hidden!important;}
  table.hd-mobile-card-table td{border:0!important;border-bottom:1px dashed #e5e7eb!important;padding:10px 0!important;min-height:0!important;}
  table.hd-mobile-card-table td:last-child{border-bottom:0!important;padding-bottom:0!important;}
  table.hd-mobile-card-table td::before{display:block!important;font-size:12px!important;font-weight:900!important;color:#64748b!important;text-transform:uppercase!important;letter-spacing:.02em!important;margin-bottom:6px!important;}
  table.hd-mobile-card-table td:nth-child(1)::before{content:"序号";}
  table.hd-mobile-card-table td:nth-child(2)::before{content:"分行代码";}
  table.hd-mobile-card-table td:nth-child(3)::before{content:"分行名称";}
  table.hd-mobile-card-table td:nth-child(4)::before{content:"状态";}
  table.hd-mobile-card-table td:nth-child(5)::before{content:"操作";}
  table.hd-mobile-card-table td:nth-child(6)::before{content:"";}
  table.hd-mobile-card-table td:nth-child(7)::before{content:"";}
  table.hd-mobile-card-table td:nth-child(8)::before{content:"";}
  table.hd-mobile-card-table td:nth-child(9)::before{content:"";}
  table.hd-mobile-card-table td:first-child{font-size:18px!important;font-weight:900!important;color:#0f172a!important;}
  table.hd-mobile-card-table input.form-control,
  table.hd-mobile-card-table select.form-select{width:100%!important;min-height:44px!important;font-size:16px!important;border-radius:12px!important;}
  table.hd-mobile-card-table .btn{min-height:42px!important;border-radius:12px!important;font-weight:900!important;padding:.55rem .8rem!important;}
  table.hd-mobile-card-table td:last-child,
  table.hd-mobile-card-table .action-buttons,
  table.hd-mobile-card-table .status-action-group{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:8px!important;align-items:stretch!important;}
  table.hd-mobile-card-table td:last-child .btn,
  table.hd-mobile-card-table .action-buttons .btn,
  table.hd-mobile-card-table .status-action-group .btn{width:100%!important;margin:0!important;white-space:normal!important;}
  table.hd-mobile-card-table .badge,
  table.hd-mobile-card-table .usage-badge{font-size:13px!important;min-height:28px!important;padding:.35rem .65rem!important;}
  .master-card-header,
  .assign-card-header,
  .kbc-card-header{flex-direction:column!important;align-items:stretch!important;gap:12px!important;}
  .search-input{max-width:100%!important;width:100%!important;}
}
</style>


<div class="master-page-header">
    <div>
        <h2 class="mb-1">Branch Management</h2>
        <div class="text-muted">Add, edit, disable or delete branch records.</div>
    </div>
    <a href="users.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<?php if($message !== ''): ?><div class="alert alert-success"><?= esc($message); ?></div><?php endif; ?>
<?php if($error !== ''): ?><div class="alert alert-danger"><?= esc($error); ?></div><?php endif; ?>

<div class="master-card">
    <div class="master-card-header">
        <div>
            <strong>Add Branch</strong>
            <div class="text-muted small">Add, edit, disable or delete branch records.</div>
        </div>
    </div>
    <div class="master-card-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="action" value="add">
            
            <div class="col-md">
                <label class="form-label">Branch Code</label>
                <input type="text" name="branch_code" class="form-control" value="" required>
            </div>
            <div class="col-md">
                <label class="form-label">Branch Name</label>
                <input type="text" name="branch_name" class="form-control" value="" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100">
                    <i class="bi bi-plus-circle"></i> Add
                </button>
            </div>
        </form>
    </div>
</div>

<div class="master-card">
    <div class="master-card-header">
        <div>
            <strong>Branch List</strong>
            <div class="text-muted small">Inactive items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.</div>
        </div>
        <div class="input-group search-input">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="masterSearch" class="form-control" placeholder="Search...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover master-table mb-0 hd-mobile-card-table" id="masterTable">
            <thead class="table-light"><tr><th>No.</th>
<th>Branch Code</th>
<th>Branch Name</th>
<th>Status</th>
<th>Action</th></tr></thead>
            <tbody>
                <?php $seqNo = 1; foreach($items as $item): ?>
                <tr>
                    <form method="post">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?= (int)$item['id']; ?>">
                    <td class="fw-bold text-center"><?= $seqNo++; ?></td>
                    
                    <td>
                        <input type="text" name="branch_code" class="form-control form-control-sm" value="<?= esc($item['branch_code'] ?? ''); ?>" required>
                    </td>
                    <td>
                        <input type="text" name="branch_name" class="form-control form-control-sm" value="<?= esc($item['branch_name'] ?? ''); ?>" required>
                    </td>
                    <td>
                        <select name="status" class="form-select form-select-sm">
                            <option value="1" <?= ((int)$item['status'] == 1) ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?= ((int)$item['status'] == 0) ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                    </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int)$item['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-warning">Toggle</button>
                            </form>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this item?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$item['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if(count($items) == 0): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
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
/* AH color button normalization */
.btn-primary{font-weight:800;}
.btn-warning{font-weight:800;color:#111827;}
.btn-danger{font-weight:800;}
.alert-info{background:#cff4fc;border-color:#9eeaf9;color:#055160;border-radius:12px;}
.master-card-header{background:#fff;}
</style>
<style>@media(max-width:768px){table.hd-mobile-card-table td:nth-child(1)::before{content:"序号";}table.hd-mobile-card-table td:nth-child(2)::before{content:"分行代码";}table.hd-mobile-card-table td:nth-child(3)::before{content:"分行名称";}table.hd-mobile-card-table td:nth-child(4)::before{content:"状态";}table.hd-mobile-card-table td:nth-child(5)::before{content:"操作";}}</style>
<link rel="stylesheet" href="assets/css/admin-ui-v2.css?v=20260630true1">

<?php require 'footer.php'; ?>
