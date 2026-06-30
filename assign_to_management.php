<?php

require 'header.php';
require 'db.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

require_once 'module_permissions.php';
require_module_permission('assign_to_management');

function esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $action = $_POST['action'] ?? '';

    if($action === 'add')
    {
        $assign_name = trim($_POST['assign_name'] ?? '');
        $assign_email = trim($_POST['assign_email'] ?? '');

        if($assign_name === '')
        {
            $error = 'Assign To name is required.';
        }
        else
        {
            try
            {
                $stmt = $pdo->prepare("
                    INSERT INTO assign_to_master
                    (assign_name, assign_email, status)
                    VALUES (?, ?, 1)
                ");
                $stmt->execute([
                    $assign_name,
                    $assign_email !== '' ? $assign_email : null
                ]);

                $message = 'Assign To added successfully.';
            }
            catch(Exception $e)
            {
                $error = 'Add failed. Name may already exist.';
            }
        }
    }

    if($action === 'edit')
    {
        $id = (int)($_POST['id'] ?? 0);
        $assign_name = trim($_POST['assign_name'] ?? '');
        $assign_email = trim($_POST['assign_email'] ?? '');
        $status = (int)($_POST['status'] ?? 1);

        if($id <= 0 || $assign_name === '')
        {
            $error = 'Invalid data.';
        }
        else
        {
            try
            {
                $stmt = $pdo->prepare("
                    UPDATE assign_to_master
                    SET assign_name = ?,
                        assign_email = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $assign_name,
                    $assign_email !== '' ? $assign_email : null,
                    $status == 1 ? 1 : 0,
                    $id
                ]);

                $message = 'Assign To updated successfully.';
            }
            catch(Exception $e)
            {
                $error = 'Update failed. Name may already exist.';
            }
        }
    }

    if($action === 'delete')
    {
        $id = (int)($_POST['id'] ?? 0);

        if($id > 0)
        {
            $stmt = $pdo->prepare("SELECT assign_name FROM assign_to_master WHERE id = ?");
            $stmt->execute([$id]);
            $deleteName = trim((string)$stmt->fetchColumn());

            if($deleteName === '')
            {
                $error = 'Assign To name not found.';
            }
            else
            {
                $used = 0;
                try { $s = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to = ?"); $s->execute([$deleteName]); $used += (int)$s->fetchColumn(); } catch(Exception $e) {}
                try { $s = $pdo->prepare("SELECT COUNT(*) FROM users WHERE ticket_assign_access LIKE ?"); $s->execute(['%'.$deleteName.'%']); $used += (int)$s->fetchColumn(); } catch(Exception $e) {}

                if($used > 0)
                {
                    $stmt = $pdo->prepare("UPDATE assign_to_master SET status = 0, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Assign To is already used by Tickets / User visibility, so it has been disabled instead of deleted.';
                }
                else
                {
                    $stmt = $pdo->prepare("DELETE FROM assign_to_master WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Unused Assign To deleted successfully.';
                }
            }
        }
    }

    if($action === 'toggle')
    {
        $id = (int)($_POST['id'] ?? 0);

        if($id > 0)
        {
            $stmt = $pdo->prepare("
                UPDATE assign_to_master
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
    FROM assign_to_master
    ORDER BY status DESC, assign_name ASC
");

$assignList = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
.assign-page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    margin-bottom:18px;
}
.assign-card{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:18px;
    box-shadow:0 10px 24px rgba(15,23,42,.06);
    overflow:hidden;
    margin-bottom:18px;
}
.assign-card-header{
    padding:18px 20px;
    border-bottom:1px solid #eef2f7;
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.assign-card-body{
    padding:20px;
}
.assign-table td,
.assign-table th{
    vertical-align:middle;
}
.action-buttons{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
}
.search-input{
    max-width:360px;
}
@media(max-width:768px){
    .assign-page-header{
        flex-direction:column;
        align-items:flex-start;
    }
}


/* Mobile Card UI - only affects phone screens. Desktop table layout remains unchanged. */
@media(max-width:768px){
    .master-page-header,.assign-page-header{
        gap:10px!important;
        margin-bottom:14px!important;
    }
    .master-page-header h2,.assign-page-header h2,.kbc-hero h2{
        font-size:24px!important;
        line-height:1.2!important;
    }
    .master-page-header .btn,.assign-page-header .btn{
        width:100%!important;
        min-height:46px!important;
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        border-radius:14px!important;
    }
    .master-card,.assign-card,.kbc-card{
        border-radius:18px!important;
        margin-bottom:14px!important;
        overflow:hidden!important;
    }
    .master-card-header,.assign-card-header,.kbc-card-header{
        display:block!important;
        padding:15px 16px!important;
    }
    .master-card-body,.assign-card-body,.kbc-card-body{
        padding:15px!important;
    }
    .search-input{
        max-width:none!important;
        width:100%!important;
        margin-top:12px!important;
    }
    .table-responsive{
        overflow:visible!important;
    }
    .master-table,.assign-table,.kbc-card .table{
        border-collapse:separate!important;
        border-spacing:0 12px!important;
        margin-bottom:0!important;
    }
    .master-table thead,.assign-table thead,.kbc-card .table thead{
        display:none!important;
    }
    .master-table tbody,.assign-table tbody,.kbc-card .table tbody,
    .master-table tr,.assign-table tr,.kbc-card .table tr,
    .master-table td,.assign-table td,.kbc-card .table td{
        display:block!important;
        width:100%!important;
    }
    .master-table tbody tr,.assign-table tbody tr,.kbc-card .table tbody tr{
        background:#fff!important;
        border:1px solid #e5e7eb!important;
        border-radius:18px!important;
        box-shadow:0 8px 22px rgba(15,23,42,.06)!important;
        padding:14px!important;
        margin-bottom:12px!important;
    }
    .master-table td,.assign-table td,.kbc-card .table td{
        border:0!important;
        padding:8px 0!important;
        min-height:auto!important;
    }
    .master-table td::before,.assign-table td::before,.kbc-card .table td::before{
        display:block!important;
        font-size:11px!important;
        font-weight:900!important;
        color:#64748b!important;
        text-transform:uppercase!important;
        letter-spacing:.04em!important;
        margin-bottom:5px!important;
    }
    .master-table td input,.master-table td select,
    .assign-table td input,.assign-table td select,
    .kbc-card .table td input,.kbc-card .table td select{
        width:100%!important;
        min-height:46px!important;
        font-size:16px!important;
        border-radius:13px!important;
    }
    .action-buttons,.status-action-group{
        display:grid!important;
        grid-template-columns:repeat(3,minmax(0,1fr))!important;
        gap:8px!important;
        width:100%!important;
    }
    .action-buttons form,.status-action-group form,
    .action-buttons .d-inline,.status-action-group .d-inline{
        display:block!important;
        width:100%!important;
        margin:0!important;
    }
    .action-buttons .btn,.status-action-group .btn,
    .kbc-card .table td .btn{
        width:100%!important;
        min-height:42px!important;
        border-radius:12px!important;
        font-size:14px!important;
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        gap:5px!important;
        margin:0!important;
    }
    .badge,.usage-badge{
        min-height:28px!important;
        display:inline-flex!important;
        align-items:center!important;
        justify-content:center!important;
        border-radius:999px!important;
        padding:6px 10px!important;
        font-weight:900!important;
    }
    .form-check.form-switch{
        min-height:38px!important;
        display:flex!important;
        align-items:center!important;
        gap:10px!important;
    }
    .form-check-input{
        width:3em!important;
        height:1.55em!important;
    }
}

@media(max-width:768px){
    .assign-table td:nth-child(1)::before{content:'No.';}
    .assign-table td:nth-child(2)::before{content:'Name / Email / Status';}
    .assign-table td:nth-child(3)::before{content:'Email';}
    .assign-table td:nth-child(4)::before{content:'Status';}
    .assign-table td:nth-child(5)::before{content:'Action';}
}
</style>
<link rel="stylesheet" href="assets/css/admin-ui-v2.css?v=20260630true1">


<div class="assign-page-header">
    <div>
        <h2 class="mb-1"><?= esc(__('Assign To Management')); ?></h2>
        <div class="text-muted"><?= esc(__('Add, edit, disable or delete Assign To options used in tickets.')); ?></div>
    </div>

    <a href="users.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<?php if($message !== ''): ?>
<div class="alert alert-success"><?= esc($message); ?></div>
<?php endif; ?>

<?php if($error !== ''): ?>
<div class="alert alert-danger"><?= esc($error); ?></div>
<?php endif; ?>

<div class="assign-card">
    <div class="assign-card-header">
        <div>
            <strong><?= esc(__('Add Assign To')); ?></strong>
            <div class="text-muted small">Example: IT Team, KIAT, Vendor, POS Support.</div>
        </div>
    </div>

    <div class="assign-card-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="action" value="add">

            <div class="col-md-5">
                <label class="form-label"><?= esc(__('Assign To Name')); ?></label>
                <input type="text" name="assign_name" class="form-control" required>
            </div>

            <div class="col-md-5">
                <label class="form-label">Email Optional</label>
                <input type="email" name="assign_email" class="form-control" placeholder="optional@email.com">
            </div>

            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100">
                    <i class="bi bi-plus-circle"></i> Add
                </button>
            </div>
        </form>
    </div>
</div>

<div class="assign-card">
    <div class="assign-card-header">
        <div>
            <strong><?= esc(__('Assign To List')); ?></strong>
            <div class="text-muted small">Inactive items will not appear in Create Ticket / 指派工单 dropdown.</div>
        </div>

        <div class="input-group search-input">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="assignSearch" class="form-control" placeholder="Search...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover assign-table mb-0" id="assignTable">
            <thead class="table-light">
                <tr>
                    <th style="width:70px;">No.</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th style="width:110px;">Status</th>
                    <th style="width:320px;">Action</th>
                </tr>
            </thead>

            <tbody>
                <?php $seqNo = 1; foreach($assignList as $item): ?>
                <tr>
                    <td class="fw-bold text-center"><?= $seqNo++; ?></td>

                    <td>
                        <form method="post" class="row g-2 align-items-center">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?= (int)$item['id']; ?>">

                            <div class="col-md-5">
                                <input type="text" name="assign_name" class="form-control form-control-sm" value="<?= esc($item['assign_name']); ?>" required>
                            </div>

                            <div class="col-md-5">
                                <input type="email" name="assign_email" class="form-control form-control-sm" value="<?= esc($item['assign_email'] ?? ''); ?>" placeholder="Email optional">
                            </div>

                            <div class="col-md-2">
                                <select name="status" class="form-select form-select-sm">
                                    <option value="1" <?= ((int)$item['status'] == 1) ? 'selected' : ''; ?>>Active</option>
                                    <option value="0" <?= ((int)$item['status'] == 0) ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                    </td>

                    <td><?= esc($item['assign_email'] ?? '-'); ?></td>

                    <td>
                        <?php if((int)$item['status'] == 1): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div class="action-buttons">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    Save
                                </button>
                        </form>

                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int)$item['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-warning">
                                    Toggle
                                </button>
                            </form>

                            <form method="post" style="display:inline;" onsubmit="return confirm('<?= esc(__('Delete this Assign To option?')); ?>');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$item['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if(count($assignList) == 0): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        <?= esc(__('No Assign To records found.')); ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const search = document.getElementById('assignSearch');
    const table = document.getElementById('assignTable');

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
