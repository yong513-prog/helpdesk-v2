<?php
require 'header.php';
require 'db.php';
require_once 'audit_log.php';
require_once 'access_control.php';
require_once 'module_permissions.php';
require_once 'pic_options.php';

// Ensure new ticket assign visibility column exists. Safe to run repeatedly.
try { $pdo->exec("ALTER TABLE users ADD COLUMN ticket_assign_access TEXT NULL AFTER ticket_pic_access"); } catch(Exception $e) {}

if(!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_action_permission('manage_user');

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function arr_csv($v){ return function_exists('csv_to_array') ? csv_to_array($v) : array_filter(array_map('trim', explode(',', (string)$v))); }


function load_active_branch_options(PDO $pdo, array $extraCodes = []){
    $fallback = ['HQ'=>'Head Quarter','KB'=>'Kota Bharu','KC'=>'Kampung Chempaka','KJ'=>'Kota Jembal','KK'=>'Kubang Kerian','KL'=>'Kok Lanas','KR'=>'Ketereh','KS'=>'Kampung Serendah','ML'=>'Melor','PC'=>'Pengkalan Chepa','PJ'=>'Panji','PKL'=>'Pasaraya Kok Lanas','PM'=>'Pasir Mas','SE'=>'Sering','TM'=>'Tanah Merah','TPC'=>'Tumpat Cabang Empat','TPN'=>'Tumpat New Town','TPT'=>'Tumpat','WC'=>'Wakaf Che Yeh','WK'=>'Wakaf Kebakat'];
    $out = [];
    try {
        $stmt = $pdo->query("SELECT branch_code, branch_name FROM branch_master WHERE status = 1 ORDER BY branch_code ASC");
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $code = trim((string)($row['branch_code'] ?? ''));
            if($code === '') continue;
            $out[$code] = trim((string)($row['branch_name'] ?? '')) ?: $code;
        }
    } catch(Exception $e) {
        $out = $fallback;
    }
    foreach($extraCodes as $code){
        $code = trim((string)$code);
        if($code !== '' && !isset($out[$code])) $out[$code] = $fallback[$code] ?? $code;
    }
    return $out ?: $fallback;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$user) die('User not found');

$branchNames = load_active_branch_options($pdo, array_merge([$_POST['branch'] ?? ($user['branch'] ?? '')], $_POST['ticket_branch_access'] ?? arr_csv($user['ticket_branch_access'] ?? '')));
$branchList = array_keys($branchNames);

$picList = get_active_pic_options($pdo, [$user['department'] ?? '', $user['ticket_pic_access'] ?? '']);

$assignList = [];
try {
    $assignList = $pdo->query("SELECT assign_name FROM assign_to_master WHERE status = 1 ORDER BY assign_name ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch(Exception $e) {
    $assignList = [];
}
$existingAssignAccess = arr_csv($user['ticket_assign_access'] ?? '');
$assignList = array_values(array_unique(array_filter(array_merge($assignList, $existingAssignAccess))));
sort($assignList, SORT_NATURAL | SORT_FLAG_CASE);

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $full_name = trim($_POST['full_name'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = normalize_role($_POST['role'] ?? 'staff');
    $branch = trim($_POST['branch'] ?? '');
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $department = implode(',', normalize_selected_pics($_POST['department'] ?? [], $picList));
    $ticket_pic_access = implode(',', normalize_selected_pics($_POST['ticket_pic_access'] ?? [], $picList));
    $ticket_assign_access = implode(',', array_values(array_intersect($assignList, array_map('trim', $_POST['ticket_assign_access'] ?? []))));
    $branch_access = '';
    $ticket_branch_access = '';
    $ticket_scope = default_ticket_scope_for_role($role);

    if($role === 'admin'){
        $ticket_scope = 'ALL';
        $branch_access = implode(',', $branchList);
        $ticket_branch_access = implode(',', $branchList);
        $ticket_assign_access = implode(',', $assignList);
    } else {
        $selectedBranches = array_values(array_intersect($branchList, array_map('trim', $_POST['ticket_branch_access'] ?? [])));
        $branch_access = ($role === 'head') ? implode(',', $selectedBranches) : '';
        $ticket_branch_access = implode(',', $selectedBranches);
        $ticket_scope = 'BRANCH';
        if($role !== 'head') $role = 'staff';
    }

    if($role !== 'admin' && $branch === '') $error = 'Primary Branch is required for Head / Staff.';
    elseif($role === 'head' && $ticket_pic_access === '' && $ticket_assign_access === '') $error = 'Head must tick at least one Checked PIC or View Assigned To permission.';
    elseif($role === 'staff' && $branch === '' && $ticket_assign_access === '') $error = 'Staff must have Primary Branch or View Assigned To permission.';
    else {
        if($password !== ''){
            $stmt = $pdo->prepare('UPDATE users SET full_name=?, password=?, role=?, branch=?, branch_access=?, department=?, ticket_scope=?, ticket_branch_access=?, ticket_pic_access=?, ticket_assign_access=?, status=? WHERE id=?');
            $stmt->execute([$full_name, password_hash($password, PASSWORD_DEFAULT), $role, $branch, $branch_access, $department, $ticket_scope, $ticket_branch_access, $ticket_pic_access, $ticket_assign_access, $status, $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET full_name=?, role=?, branch=?, branch_access=?, department=?, ticket_scope=?, ticket_branch_access=?, ticket_pic_access=?, ticket_assign_access=?, status=? WHERE id=?');
            $stmt->execute([$full_name, $role, $branch, $branch_access, $department, $ticket_scope, $ticket_branch_access, $ticket_pic_access, $ticket_assign_access, $status, $id]);
        }
        if(function_exists('audit_log')) audit_log($pdo, 'Edit User', 'Updated user ID '.$id.' role '.$role);
        if(ob_get_length()) ob_clean();
        header('Location: users.php?focus_user_id='.(int)$id); exit;
    }
}

$selectedRole = normalize_role($_POST['role'] ?? ($user['role'] ?? 'staff'));
$selectedBranch = $_POST['branch'] ?? ($user['branch'] ?? '');
$selectedDepartmentPics = normalize_selected_pics($_POST['department'] ?? ($user['department'] ?? ''), $picList);
$selectedTicketBranches = array_values(array_intersect($branchList, array_map('trim', $_POST['ticket_branch_access'] ?? arr_csv($user['ticket_branch_access'] ?? ''))));
$selectedTicketPics = normalize_selected_pics($_POST['ticket_pic_access'] ?? ($user['ticket_pic_access'] ?? ''), $picList);
$selectedTicketAssign = array_values(array_intersect($assignList, array_map('trim', $_POST['ticket_assign_access'] ?? arr_csv($user['ticket_assign_access'] ?? ''))));
?>

<style>
.user-form-shell{max-width:1180px;padding-bottom:80px}.user-page-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}.user-page-title h2{margin:0;font-weight:900}.user-section{background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 10px 24px rgba(15,23,42,.05);margin-bottom:16px;overflow:hidden}.user-section-header{width:100%;border:0;background:#fff;padding:18px 20px;display:flex;justify-content:space-between;align-items:center;font-weight:900;color:#0f172a;cursor:pointer;text-align:left}.user-section-header small{display:block;font-weight:500;color:#64748b;margin-top:4px}.user-section-body{padding:20px;border-top:1px solid #eef2f7}.choice-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}.choice-card{display:block;cursor:pointer}.choice-card input{display:none}.choice-card span{display:block;border:1px solid #dbe3f0;border-radius:14px;padding:12px 14px;background:#fff;transition:.18s ease;min-height:68px}.choice-card strong{display:block}.choice-card small{color:#64748b}.choice-card input:checked+span{background:#0d6efd;color:#fff;border-color:#0d6efd;box-shadow:0 8px 20px rgba(13,110,253,.22)}.choice-card input:checked+span small{color:rgba(255,255,255,.88)}.summary-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:14px;color:#475569;font-size:14px}.sticky-actions{position:fixed;right:28px;bottom:24px;background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:12px;box-shadow:0 20px 50px rgba(15,23,42,.18);display:flex;gap:10px;z-index:30}.quick-actions{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}.role-hint{font-size:13px;color:#64748b}
</style>

<?php if(!empty($error)): ?><div class="alert alert-danger"><?= h($error); ?></div><?php endif; ?>
<div class="user-form-shell">
  <div class="user-page-header"><div class="user-page-title"><h2>Edit User</h2><div class="text-muted">Admin / Head / Staff permission setup using existing database fields.</div></div><a href="users.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a></div>
  <form method="post">
    <div class="user-section"><button class="user-section-header" type="button" data-bs-toggle="collapse" data-bs-target="#basic"><span>1. Basic User Info<small>Login information and status.</small></span><i class="bi bi-chevron-down"></i></button><div id="basic" class="collapse show"><div class="user-section-body"><div class="row g-3">
      <div class="col-md-4"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" value="<?= h($_POST['full_name'] ?? $user['full_name']); ?>"></div>
      <div class="col-md-4"><label class="form-label">Username</label><input type="text" class="form-control" value="<?= h($user['username']); ?>" disabled></div>
      <div class="col-md-4"><label class="form-label">New Password</label><input type="password" name="password" class="form-control" placeholder="Leave blank to keep current"></div>
      <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active" <?= ($user['status'] ?? '')==='active'?'selected':''; ?>>Active</option><option value="inactive" <?= ($user['status'] ?? '')==='inactive'?'selected':''; ?>>Inactive</option></select></div>
    </div></div></div></div>

    <div class="user-section"><button class="user-section-header" type="button" data-bs-toggle="collapse" data-bs-target="#role"><span>2. Role & Primary Branch<small>Only Admin, Head and Staff are supported.</small></span><i class="bi bi-chevron-down"></i></button><div id="role" class="collapse show"><div class="user-section-body"><div class="row g-3">
      <div class="col-md-4"><label class="form-label">Role</label><select name="role" id="roleSelect" class="form-select" onchange="applyRoleHint()"><option value="admin" <?= $selectedRole==='admin'?'selected':''; ?>>Admin</option><option value="head" <?= $selectedRole==='head'?'selected':''; ?>>Head</option><option value="staff" <?= $selectedRole==='staff'?'selected':''; ?>>Staff</option></select><div class="role-hint mt-2" id="roleHint"></div></div>
      <div class="col-md-4"><label class="form-label">Primary Branch</label><select name="branch" class="form-select"><option value="">-- Select Branch --</option><?php foreach($branchList as $b): ?><option value="<?= h($b); ?>" <?= $selectedBranch===$b?'selected':''; ?>><?= h($b.' - '.($branchNames[$b] ?? $b)); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label">Profile PIC / Department</label><select name="department[]" class="form-select" multiple size="5"><?php foreach($picList as $pic): ?><option value="<?= h($pic); ?>" <?= in_array($pic,$selectedDepartmentPics,true)?'selected':''; ?>><?= h($pic); ?></option><?php endforeach; ?></select></div>
    </div></div></div></div>

    <div class="user-section"><button class="user-section-header" type="button" data-bs-toggle="collapse" data-bs-target="#ticketAccess"><span>3. Ticket Visibility Permission<small>Head = Checked PIC or Assigned To. Staff = Own Branch or Assigned To.</small></span><i class="bi bi-chevron-down"></i></button><div id="ticketAccess" class="collapse show"><div class="user-section-body">
      <div class="summary-box mb-3"><strong>Final rule:</strong> Admin sees all. Head sees Checked PIC / own in-charge tickets OR checked Assigned To tickets. Staff sees own Primary Branch tickets OR checked Assigned To tickets.</div>
      <h6 class="fw-bold">Branch Access for Head / Reference Only</h6><div class="text-muted mb-2" style="font-size:13px">Ticket visibility now uses: Head = Checked PIC + Assigned To; Staff = own Primary Branch + Assigned To. This branch checkbox is kept for Head branch access/reference.</div><div class="quick-actions"><button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleBoxes('ticket_branch_access[]',true)">Select All Branch</button><button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleBoxes('ticket_branch_access[]',false)">Clear Branch</button></div>
      <div class="choice-grid mb-4"><?php foreach($branchList as $b): ?><label class="choice-card"><input type="checkbox" name="ticket_branch_access[]" value="<?= h($b); ?>" <?= in_array($b,$selectedTicketBranches,true)?'checked':''; ?>><span><strong><?= h($b); ?></strong><small><?= h($branchNames[$b] ?? $b); ?></small></span></label><?php endforeach; ?></div>
      <h6 class="fw-bold">View Assigned To</h6><div class="quick-actions"><button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleBoxes('ticket_assign_access[]',true)">Select All Assign To</button><button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleBoxes('ticket_assign_access[]',false)">Clear Assign To</button></div>
      <div class="choice-grid mb-4"><?php foreach($assignList as $assignName): ?><label class="choice-card"><input type="checkbox" name="ticket_assign_access[]" value="<?= h($assignName); ?>" <?= in_array($assignName,$selectedTicketAssign,true)?'checked':''; ?>><span><strong><?= h($assignName); ?></strong><small>Assigned To</small></span></label><?php endforeach; ?></div>
      <h6 class="fw-bold">Checked PIC / Profile PIC Access</h6><div class="quick-actions"><button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleBoxes('ticket_pic_access[]',true)">Select All PIC</button><button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleBoxes('ticket_pic_access[]',false)">Clear PIC</button></div>
      <div class="choice-grid"><?php foreach($picList as $pic): ?><label class="choice-card"><input type="checkbox" name="ticket_pic_access[]" value="<?= h($pic); ?>" <?= in_array($pic,$selectedTicketPics,true)?'checked':''; ?>><span><strong><?= h($pic); ?></strong><small>Person In Charge</small></span></label><?php endforeach; ?></div>
    </div></div></div>

    <div class="sticky-actions"><a href="users.php" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save User</button></div>
  </form>
</div>
<script>
function toggleBoxes(name,state){document.querySelectorAll('input[name="'+name+'"]').forEach(function(b){b.checked=state;});}
function applyRoleHint(){var r=document.getElementById('roleSelect').value;var h=document.getElementById('roleHint');if(r==='admin')h.textContent='Admin: all tickets and all admin functions.';else if(r==='head')h.textContent='Head: Checked PIC / own in-charge tickets OR checked Assigned To tickets.';else h.textContent='Staff: own Primary Branch tickets OR checked Assigned To tickets.';}
applyRoleHint();
</script>
<?php require 'footer.php'; ?>
