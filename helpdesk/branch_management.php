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
        <table class="table table-hover master-table mb-0" id="masterTable">
            <thead class="table-dark"><tr><th>No.</th>
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

<?php require 'footer.php'; ?>
