<?php

require 'header.php';
require 'db.php';
require_once 'audit_log.php';
require_once 'access_control.php';
require_once 'module_permissions.php';
require_once 'pic_options.php';
require_once 'ticket_master_options.php';
require_once 'ticket_status_options.php';
require_once 'attachment_upload_helper.php';


function ensure_ticket_last_update_columns(PDO $pdo)
{
    try
    {
        $stmt = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'last_update'");
        if(!$stmt->fetch(PDO::FETCH_ASSOC))
        {
            $pdo->exec("ALTER TABLE tickets ADD COLUMN last_update DATETIME NULL DEFAULT NULL AFTER updated_at");
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'last_updated_by'");
        if(!$stmt->fetch(PDO::FETCH_ASSOC))
        {
            $pdo->exec("ALTER TABLE tickets ADD COLUMN last_updated_by VARCHAR(100) NULL DEFAULT NULL AFTER last_update");
        }
    }
    catch(Exception $e) {}
}


function redirect_to_ticket_list_after_action()
{
    $status = trim($_POST['return_status'] ?? $_GET['return_status'] ?? '');
    $branch = trim($_POST['return_branch'] ?? $_GET['return_branch'] ?? '');
    $priority = trim($_POST['return_priority'] ?? $_GET['return_priority'] ?? '');

    $params = [];

    if($status !== '')
    {
        $params['status'] = $status;
    }

    if($branch !== '')
    {
        $params['branch'] = $branch;
    }

    if($priority !== '')
    {
        $params['priority'] = $priority;
    }

    $url = 'ticket_list.php';
    if(count($params) > 0)
    {
        $url .= '?' . http_build_query($params);
    }

    header("Location: ".$url);
    exit;
}

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

ensure_ticket_last_update_columns($pdo);


require_action_permission('edit_ticket');

$branchMasterList = master_fetch_active_branches($pdo);
$categoryMasterList = master_fetch_active_categories($pdo);
$slaMasterList = master_fetch_active_sla($pdo);
$ticketStatusList = ticket_status_fetch_all($pdo, true);
$statusClass = ticket_status_color_map($pdo);

$currentRoleForEdit = normalize_role($_SESSION['role'] ?? 'staff');
$canChangeStatusInEdit = has_action_permission('change_status');
$canAssignInEdit = has_action_permission('assign_ticket');
$allowedEditBranches = ($currentRoleForEdit === 'admin') ? array_column($branchMasterList, 'branch_code') : get_user_ticket_branches();

$id = (int)($_GET['id'] ?? 0);

if($id <= 0)
{
    die("Invalid ticket ID");
}

$stmt = $pdo->prepare("
SELECT *
FROM tickets
WHERE id = ?
");

$stmt->execute([$id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$ticket)
{
    die("Ticket not found");
}

if(function_exists('can_access_ticket') && !can_access_ticket($ticket))
{
    die("Access Denied");
}

$canSelectAssetInTicket = has_action_permission('asset_list')
    || has_action_permission('select_asset_in_ticket');

$stmtAssets = $pdo->prepare("
    SELECT id, asset_code, asset_name, branch, location
    FROM assets
    WHERE status IN ('Active','Repair')
    ORDER BY branch, asset_code
");
$stmtAssets->execute();
$assets = $stmtAssets->fetchAll(PDO::FETCH_ASSOC);


$actorName = $_SESSION['username'] ?? ('User ID '.$_SESSION['user_id']);

if($_SERVER['REQUEST_METHOD'] == 'POST')
{
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $branch = trim($_POST['branch'] ?? '');

    if($currentRoleForEdit === 'staff')
    {
        // Staff / normal user cannot move a ticket out of their own branch.
        $branch = trim((string)($_SESSION['branch'] ?? ''));
    }
    elseif($currentRoleForEdit === 'head' && !in_array($branch, $allowedEditBranches, true))
    {
        $branch = $ticket['branch'] ?? '';
    }

    $department = trim($_POST['department'] ?? '');
    $category = trim($_POST['category'] ?? '');

    $defaultPriority = function_exists('master_category_default_priority')
        ? master_category_default_priority($categoryMasterList, $category)
        : '';

    $priority = $defaultPriority ?: trim($_POST['priority'] ?? '');
    $status = $canChangeStatusInEdit ? trim($_POST['status'] ?? '') : ($ticket['status'] ?? 'Open');
    $assigned_to = $canAssignInEdit ? trim($_POST['assigned_to'] ?? '') : ($ticket['assigned_to'] ?? '');
    $asset_id = (int)($_POST['asset_id'] ?? 0);

    $sla_hours = master_priority_hours($slaMasterList, $priority);

    if($sla_hours !== null && !empty($ticket['created_at']))
    {
        $due_date = date(
            'Y-m-d H:i:s',
            strtotime($ticket['created_at']) + ($sla_hours * 3600)
        );
    }
    else
    {
        $due_date = trim($_POST['due_date'] ?? '');
    }

    if($title == '' || $branch == '' || $department == '' || $category == '' || $priority == '' || $status == '')
    {
        $error = "Please fill in all required fields.";
    }
    elseif($currentRoleForEdit !== 'admin' && !in_array($branch, $allowedEditBranches, true))
    {
        $error = "Invalid branch selected. Please enable branch access for this role/user first.";
    }
    elseif(!in_array($department, get_user_ticket_pics(), true) && $currentRoleForEdit !== 'admin')
    {
        $error = "Invalid PIC selected. Please enable PIC access for this user first.";
    }
    elseif(!master_category_exists($categoryMasterList, $category))
    {
        $error = "Invalid category selected. Please enable it in Category Management first.";
    }
    elseif($sla_hours === null)
    {
        $error = "Invalid priority selected. Please enable it in SLA Management first.";
    }
    else
    {
        $attachment = $ticket['attachment'] ?? null;

        if(isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0)
        {
            $ticketNoForFolder = !empty($ticket['ticket_no']) ? $ticket['ticket_no'] : ('TICKET-' . (int)$ticket['id']);
            $attachment = hd_handle_attachment_upload(
                $_FILES['attachment'],
                hd_entity_upload_dir('tickets', $ticketNoForFolder)
            );
        }

        if(!isset($error))
        {
            $stmt = $pdo->prepare("
            UPDATE tickets
            SET
                title = ?,
                description = ?,
                branch = ?,
                department = ?,
                category = ?,
                priority = ?,
                sla_hours = ?,
                status = ?,
                assigned_to = ?,
                due_date = ?,
                attachment = ?,
                asset_id = ?,
                updated_at = NOW(),
                last_update = NOW(),
                last_updated_by = ?
            WHERE id = ?
            ");

            $stmt->execute([
                $title,
                $description,
                $branch,
                $department,
                $category,
                $priority,
                $sla_hours,
                $status,
                $assigned_to,
                $due_date,
                $attachment,
                $asset_id,
                $actorName,
                $id
            ]);

            audit_log(
                $pdo,
                'Edit Ticket',
                'Updated Ticket '.$ticket['ticket_no']
            );

            redirect_to_ticket_list_after_action();
        }
    }
}


$picList = get_active_pic_options($pdo, [$ticket['department'] ?? '']);
if($currentRoleForEdit !== 'admin') {
    $allowedPicsForEdit = get_user_ticket_pics();
    $picList = array_values(array_unique(array_filter($picList, function($p) use ($allowedPicsForEdit){ return in_array($p, $allowedPicsForEdit, true); })));
    if(!in_array($ticket['department'] ?? '', $picList, true) && !empty($ticket['department'])) $picList[] = $ticket['department'];
}
$editBranchList = $branchMasterList;
if($currentRoleForEdit !== 'admin') {
    $editBranchList = array_values(array_filter($branchMasterList, function($b) use ($allowedEditBranches){ return in_array($b['branch_code'] ?? '', $allowedEditBranches, true); }));
}

?>

<h2>Edit Ticket</h2>

<?php if(isset($error)): ?>
<div class="alert alert-danger">
<?= htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">

<div class="row">

<div class="col-md-6 mb-3">
<label class="form-label">Ticket No</label>
<input type="text" class="form-control" value="<?= htmlspecialchars($ticket['ticket_no'] ?? ''); ?>" readonly>
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Status</label>
<select name="status" class="form-select" required <?= !$canChangeStatusInEdit ? 'disabled' : ''; ?>>
<?php foreach($ticketStatusList as $statusOption): ?>
<option value="<?= htmlspecialchars($statusOption['status_name']); ?>" <?= ($ticket['status'] ?? '') == $statusOption['status_name'] ? 'selected' : ''; ?>>
<?= htmlspecialchars($statusOption['status_name']); ?>
</option>
<?php endforeach; ?>
</select>
<?php if(!$canChangeStatusInEdit): ?><input type="hidden" name="status" value="<?= htmlspecialchars($ticket['status'] ?? 'Open'); ?>" data-name="status_hidden_role_matrix"><?php endif; ?>
</div>

</div>

<div class="mb-3">
<label class="form-label">Title</label>
<input type="text" name="title" class="form-control" value="<?= htmlspecialchars($ticket['title'] ?? ''); ?>" required>
</div>

<div class="mb-3">
<label class="form-label">Description</label>
<textarea name="description" class="form-control" rows="5"><?= htmlspecialchars($ticket['description'] ?? ''); ?></textarea>
</div>

<div class="row">

<div class="col-md-6 mb-3">
<label class="form-label">Branch</label>
<?php if(normalize_role($_SESSION['role'] ?? 'staff') == 'staff'): ?>
<input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['branch'] ?? ''); ?>" readonly>
<input type="hidden" name="branch" value="<?= htmlspecialchars($_SESSION['branch'] ?? ''); ?>">
<?php else: ?>
<select name="branch" class="form-select" required>
<option value="">Select Branch</option>
<?php foreach($editBranchList as $b): ?>
<option class="hd-no-translate" value="<?= htmlspecialchars($b['branch_code']); ?>" <?= ($ticket['branch'] ?? '') == $b['branch_code'] ? 'selected' : ''; ?>><?= htmlspecialchars($b['branch_code'].' - '.$b['branch_name']); ?></option>
<?php endforeach; ?>
</select>
<?php endif; ?>
</div>

<div class="col-md-6 mb-3">
<label class="form-label">PIC</label>
<select name="department" class="form-select" required>
<option value=""><?= htmlspecialchars(__('Select Person In Charge')); ?></option>
<?php foreach($picList as $pic): if(function_exists('normalize_pic_display_value')) { $pic = normalize_pic_display_value($pic); } ?>
<option value="<?= htmlspecialchars($pic); ?>" <?= ($ticket['department'] ?? '') == $pic ? 'selected' : ''; ?>><?= htmlspecialchars($pic); ?></option>
<?php endforeach; ?>
</select>
</div>

</div>


<div class="mb-3">
<label class="form-label">Asset / Equipment</label>
<select name="asset_id" class="form-select" <?= !$canSelectAssetInTicket ? 'disabled' : ''; ?>>
<option value="0">No Asset Selected</option>
<?php foreach($assets as $asset): ?>
<option value="<?= (int)$asset['id']; ?>" <?= ((int)($ticket['asset_id'] ?? 0)==(int)$asset['id']) ? 'selected' : ''; ?>>
<?= htmlspecialchars($asset['asset_code'].' - '.$asset['asset_name'].' ['.($asset['branch'] ?? '-').']'.(!empty($asset['location']) ? ' - '.$asset['location'] : '')); ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="row">

<div class="col-md-4 mb-3">
<label class="form-label">Category</label>
<select id="category" name="category" class="form-select" required>
<option value="">Select Category</option>
<?php foreach($categoryMasterList as $cat): ?>
<option value="<?= htmlspecialchars($cat['category_name']); ?>" <?= ($ticket['category'] ?? '') == $cat['category_name'] ? 'selected' : ''; ?>><?= htmlspecialchars($cat['category_name']); ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">Priority</label>
<select id="priority" name="priority" class="form-select" required>
<option value="">Select Priority</option>
<?php foreach($slaMasterList as $sla): ?>
<option value="<?= htmlspecialchars($sla['priority_name']); ?>" <?= ($ticket['priority'] ?? '') == $sla['priority_name'] ? 'selected' : ''; ?>><?= htmlspecialchars($sla['priority_name'].' ('.(int)$sla['sla_hours'].' hours)'); ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">Assigned To</label>
<input type="text" name="assigned_to" class="form-control" value="<?= htmlspecialchars($ticket['assigned_to'] ?? ''); ?>" placeholder="Unassigned" <?= !$canAssignInEdit ? 'readonly' : ''; ?>>
<?php if(!$canAssignInEdit): ?><div class="form-text">Controlled by Role Permission Matrix: 指派工单 is not enabled.</div><?php endif; ?>
</div>

</div>

<div class="row">

<div class="col-md-6 mb-3">
<label class="form-label">Due Date</label>
<input type="datetime-local" name="due_date" class="form-control" value="<?= !empty($ticket['due_date']) ? date('Y-m-d\TH:i', strtotime($ticket['due_date'])) : ''; ?>">
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Replace Attachment</label>
<input type="file" name="attachment" class="form-control" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.mp3,.m4a,.wav,.aac,.ogg,.webm,.mp4">
<?php if(!empty($ticket['attachment'])): ?>
<div class="form-text">
Current:
<a href="<?= htmlspecialchars($ticket['attachment']); ?>" target="_blank">View Attachment</a>
</div>
<?php endif; ?>
</div>

</div>

<button type="submit" class="btn btn-primary">
Save Changes
</button>

<a href="view_ticket.php?id=<?= $ticket['id']; ?>" class="btn btn-secondary">
Back
</a>

</form>


<script>
document.getElementById('category')?.addEventListener('change', function(){
    const map={
        'Network':'Critical',
        'POS':'High',
        'Printer':'Medium',
        'Asset':'Low'
    };
    if(map[this.value]){
        document.getElementById('priority').value=map[this.value];
    }
});
</script>

<?php require 'footer.php'; ?>
