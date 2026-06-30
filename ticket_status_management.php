<?php
require 'header.php';
require 'db.php';
require_once 'module_permissions.php';
require_once 'audit_log.php';
require_once 'ticket_status_options.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

require_module_permission('ticket_status_management');
ensure_ticket_status_master($pdo);
ticket_status_ensure_ticket_column($pdo);

function esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function status_usage_count(PDO $pdo, string $statusName): int
{
    try
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE status = ?");
        $stmt->execute([$statusName]);
        return (int)$stmt->fetchColumn();
    }
    catch(Exception $e)
    {
        return 0;
    }
}

function next_status_sort_order(PDO $pdo): int
{
    try
    {
        $max = (int)$pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM ticket_status_master")->fetchColumn();
        return $max + 10;
    }
    catch(Exception $e)
    {
        return 10;
    }
}

$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $status_name = trim($_POST['status_name'] ?? '');
    $status_color = trim($_POST['status_color'] ?? 'secondary');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $is_closed = isset($_POST['is_closed']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if($action === 'add')
    {
        if($status_name === '')
        {
            $error = 'Status name cannot be empty.';
        }
        else
        {
            try
            {
                if($sort_order <= 0)
                {
                    $sort_order = next_status_sort_order($pdo);
                }

                $stmt = $pdo->prepare("
                    INSERT INTO ticket_status_master
                    (status_name, status_color, sort_order, is_closed, is_active)
                    VALUES (?, ?, ?, ?, 1)
                ");
                $stmt->execute([$status_name, $status_color, $sort_order, $is_closed]);
                $message = 'Ticket status added successfully.';
            }
            catch(Exception $e)
            {
                $error = 'Add failed. Status may already exist.';
            }
        }
    }

    if($action === 'edit')
    {
        if($id <= 0 || $status_name === '')
        {
            $error = 'Invalid data.';
        }
        else
        {
            try
            {
                $pdo->beginTransaction();

                $stmtOld = $pdo->prepare("SELECT status_name FROM ticket_status_master WHERE id = ? LIMIT 1");
                $stmtOld->execute([$id]);
                $oldName = (string)$stmtOld->fetchColumn();

                if($oldName === '')
                {
                    throw new Exception('Status not found');
                }

                $stmt = $pdo->prepare("
                    UPDATE ticket_status_master
                    SET status_name = ?,
                        status_color = ?,
                        sort_order = ?,
                        is_closed = ?,
                        is_active = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$status_name, $status_color, $sort_order, $is_closed, $is_active, $id]);

                if($oldName !== $status_name)
                {
                    $stmtTickets = $pdo->prepare("
                        UPDATE tickets
                        SET status = ?, updated_at = NOW()
                        WHERE status = ?
                    ");
                    $stmtTickets->execute([$status_name, $oldName]);

                    $stmtHistory = $pdo->prepare("
                        INSERT INTO ticket_history (ticket_id, action, created_by, created_at)
                        SELECT id, ?, ?, NOW()
                        FROM tickets
                        WHERE status = ?
                    ");
                    $stmtHistory->execute([
                        'Status name renamed: '.$oldName.' → '.$status_name,
                        $_SESSION['user_id'] ?? null,
                        $status_name
                    ]);
                }

                if($is_closed)
                {
                    $stmtClosed = $pdo->prepare("
                        UPDATE tickets
                        SET closed_at = IF(closed_at IS NULL, NOW(), closed_at),
                            updated_at = NOW()
                        WHERE status = ?
                    ");
                    $stmtClosed->execute([$status_name]);
                }
                else
                {
                    $stmtOpen = $pdo->prepare("
                        UPDATE tickets
                        SET closed_at = NULL,
                            updated_at = NOW()
                        WHERE status = ?
                    ");
                    $stmtOpen->execute([$status_name]);
                }

                $pdo->commit();
                $message = 'Ticket status updated successfully. Existing tickets were synced if status name changed.';
            }
            catch(Exception $e)
            {
                if($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Update failed. Status name may already exist.';
            }
        }
    }

    if($action === 'toggle')
    {
        if($id > 0)
        {
            $stmt = $pdo->prepare("
                UPDATE ticket_status_master
                SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $message = 'Status active flag updated.';
        }
    }

    if($action === 'delete')
    {
        if($id <= 0)
        {
            $error = 'Invalid delete request.';
        }
        else
        {
            try
            {
                $stmtOld = $pdo->prepare("SELECT status_name FROM ticket_status_master WHERE id = ? LIMIT 1");
                $stmtOld->execute([$id]);
                $oldName = (string)$stmtOld->fetchColumn();

                if($oldName === '')
                {
                    $error = 'Status not found.';
                }
                else
                {
                    $usage = status_usage_count($pdo, $oldName);

                    if($usage > 0)
                    {
                        $error = 'This status is already used by '.$usage.' ticket(s). Delete is blocked. Use rename or Disable instead.';
                    }
                    else
                    {
                        $stmt = $pdo->prepare("DELETE FROM ticket_status_master WHERE id = ?");
                        $stmt->execute([$id]);
                        $message = 'Ticket status deleted successfully.';
                        $status_name = $oldName;
                    }
                }
            }
            catch(Exception $e)
            {
                $error = 'Delete failed.';
            }
        }
    }

    if(function_exists('audit_log'))
    {
        audit_log($pdo, 'Ticket Status Management', $action.' ticket status '.$status_name);
    }
}

$items = ticket_status_fetch_all($pdo, false);
?>

<style>
.master-page-header{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:18px;}
.master-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 10px 24px rgba(15,23,42,.06);overflow:hidden;margin-bottom:18px;}
.master-card-header{padding:18px 20px;border-bottom:1px solid #eef2f7;display:flex;justify-content:space-between;align-items:center;}
.master-card-body{padding:20px;}
.master-table td,.master-table th{vertical-align:middle;}
.color-help{font-size:12px;color:#64748b;}
.usage-badge{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:26px;border-radius:999px;font-weight:800;font-size:12px;}
.usage-zero{background:#dcfce7;color:#166534;}
.usage-used{background:#e0f2fe;color:#075985;}
.lock-note{font-size:12px;color:#64748b;margin-top:4px;}
.status-action-group{display:flex;gap:6px;flex-wrap:wrap;align-items:center;}
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
  table.hd-mobile-card-table td:nth-child(2)::before{content:"状态名称";}
  table.hd-mobile-card-table td:nth-child(3)::before{content:"颜色";}
  table.hd-mobile-card-table td:nth-child(4)::before{content:"排序";}
  table.hd-mobile-card-table td:nth-child(5)::before{content:"已关闭?";}
  table.hd-mobile-card-table td:nth-child(6)::before{content:"启用?";}
  table.hd-mobile-card-table td:nth-child(7)::before{content:"使用量";}
  table.hd-mobile-card-table td:nth-child(8)::before{content:"预览";}
  table.hd-mobile-card-table td:nth-child(9)::before{content:"操作";}
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
        <h2 class="mb-1">Ticket Status Management</h2>
        <div class="text-muted">Fully dynamic: rename, color, sort, active and closed/archive behaviour.</div>
    </div>
    <a href="administration.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<?php if($message !== ''): ?><div class="alert alert-success"><?= esc($message); ?></div><?php endif; ?>
<?php if($error !== ''): ?><div class="alert alert-danger"><?= esc($error); ?></div><?php endif; ?>

<div class="master-card">
    <div class="master-card-header">
        <div>
            <strong>Add Ticket Status</strong>
            <div class="text-muted small">Example: Waiting Vendor, Waiting Branch, On Hold, Cancelled.</div>
        </div>
    </div>
    <div class="master-card-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="action" value="add">
            <div class="col-md-4">
                <label class="form-label">Status Name</label>
                <input type="text" name="status_name" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Badge Color</label>
                <select name="status_color" class="form-select">
                    <option value="danger">Red / Danger</option>
                    <option value="warning text-dark">Yellow / Warning</option>
                    <option value="info text-dark">Blue / Info</option>
                    <option value="success">Green / Success</option>
                    <option value="secondary">Grey / Secondary</option>
                    <option value="dark">Dark</option>
                    <option value="primary">Primary</option>
                </select>
                <div class="color-help">Bootstrap badge color class.</div>
            </div>
            <div class="col-md-2">
                <label class="form-label">Sort</label>
                <input type="number" name="sort_order" class="form-control" value="<?= (int)next_status_sort_order($pdo); ?>">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="is_closed" id="addClosed">
                    <label class="form-check-label" for="addClosed">Closed</label>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100"><i class="bi bi-plus-circle"></i> Add</button>
            </div>
        </form>
    </div>
</div>

<div class="master-card">
    <div class="master-card-header">
        <div>
            <strong>Status List</strong>
            <div class="text-muted small">Rename will sync existing tickets. Delete is only allowed when Usage = 0.</div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover master-table mb-0 hd-mobile-card-table">
            <thead class="table-dark">
                <tr>
                    <th>No.</th>
                    <th>Status Name</th>
                    <th>Color</th>
                    <th>Sort</th>
                    <th>Closed?</th>
                    <th>Active?</th>
                    <th>Usage</th>
                    <th>Preview</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $no=1; foreach($items as $item): ?>
                <?php
                    $rowId = (int)$item['id'];
                    $formId = 'statusForm'.$rowId;
                    $currentName = (string)($item['status_name'] ?? '');
                    $usage = status_usage_count($pdo, $currentName);
                    $canDelete = ($usage === 0);
                ?>
                <tr>
                    <td class="fw-bold text-center"><?= $no++; ?></td>
                    <td>
                        <input form="<?= $formId; ?>" type="text" name="status_name" class="form-control form-control-sm" value="<?= esc($item['status_name']); ?>" required>
                        <?php if($usage > 0): ?><div class="lock-note">Rename allowed; existing tickets will sync.</div><?php endif; ?>
                    </td>
                    <td>
                        <select form="<?= $formId; ?>" name="status_color" class="form-select form-select-sm">
                            <?php
                            $colors = ['danger'=>'Red / Danger','warning text-dark'=>'Yellow / Warning','info text-dark'=>'Blue / Info','success'=>'Green / Success','secondary'=>'Grey / Secondary','dark'=>'Dark','primary'=>'Primary'];
                            foreach($colors as $value=>$label):
                            ?>
                            <option value="<?= esc($value); ?>" <?= ($item['status_color'] ?? '') === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input form="<?= $formId; ?>" type="number" name="sort_order" class="form-control form-control-sm" value="<?= (int)$item['sort_order']; ?>"></td>
                    <td class="text-center"><input form="<?= $formId; ?>" type="checkbox" name="is_closed" value="1" <?= ((int)$item['is_closed']===1) ? 'checked' : ''; ?>></td>
                    <td class="text-center"><input form="<?= $formId; ?>" type="checkbox" name="is_active" value="1" <?= ((int)$item['is_active']===1) ? 'checked' : ''; ?>></td>
                    <td><span class="usage-badge <?= $usage > 0 ? 'usage-used' : 'usage-zero'; ?>"><?= (int)$usage; ?></span></td>
                    <td><span class="badge bg-<?= esc($item['status_color'] ?: 'secondary'); ?>"><?= esc($item['status_name']); ?></span></td>
                    <td>
                        <div class="status-action-group">
                            <form id="<?= $formId; ?>" method="post" class="d-inline">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="id" value="<?= $rowId; ?>">
                                <button type="submit" class="btn btn-sm btn-primary">Save</button>
                            </form>

                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $rowId; ?>">
                                <button type="submit" class="btn btn-sm btn-warning"><?= ((int)$item['is_active']===1) ? 'Disable' : 'Enable'; ?></button>
                            </form>

                            <?php if($canDelete): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this ticket status?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $rowId; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-secondary" disabled>In Use</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if(!$items): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No status found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


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
<style>@media(max-width:768px){table.hd-mobile-card-table td:nth-child(1)::before{content:"序号";}table.hd-mobile-card-table td:nth-child(2)::before{content:"状态名称";}table.hd-mobile-card-table td:nth-child(3)::before{content:"颜色";}table.hd-mobile-card-table td:nth-child(4)::before{content:"排序";}table.hd-mobile-card-table td:nth-child(5)::before{content:"已关闭?";}table.hd-mobile-card-table td:nth-child(6)::before{content:"启用?";}table.hd-mobile-card-table td:nth-child(7)::before{content:"使用量";}table.hd-mobile-card-table td:nth-child(8)::before{content:"预览";}table.hd-mobile-card-table td:nth-child(9)::before{content:"操作";}}</style>
<?php require 'footer.php'; ?>
