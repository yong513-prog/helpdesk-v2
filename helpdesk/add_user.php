<?php
require 'header.php';
require 'db.php';
require_once 'access_control.php';
require_once 'module_permissions.php';
require_once 'pic_options.php';

if(!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_action_permission('manage_user');

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }


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

$branchNames = load_active_branch_options($pdo, array_merge([$_POST['branch'] ?? ''], $_POST['ticket_branch_access'] ?? []));
$branchList = array_keys($branchNames);
$picList = get_active_pic_options($pdo);

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = normalize_role($_POST['role'] ?? 'staff');
    $branch = trim($_POST['branch'] ?? '');
    $department = implode(',', normalize_selected_pics($_POST['department'] ?? [], $picList));
    $ticket_pic_access = implode(',', normalize_selected_pics($_POST['ticket_pic_access'] ?? [], $picList));
    $branch_access = '';
    $ticket_branch_access = '';
    $ticket_scope = default_ticket_scope_for_role($role);

    if($role === 'admin'){
        $ticket_scope = 'ALL';
        $branch_access = implode(',', $branchList);
        $ticket_branch_access = implode(',', $branchList);
    } elseif($role === 'head'){
        $selectedBranches = array_values(array_intersect($branchList, array_map('trim', $_POST['ticket_branch_access'] ?? [])));
        $branch_access = implode(',', $selectedBranches);
        $ticket_branch_access = implode(',', $selectedBranches);
        $ticket_scope = 'BRANCH';
    } else {
        $role = 'staff';
        $ticket_scope = 'OWN';
        $branch_access = '';
        $ticket_branch_access = $branch;
    }

    if($username === '' || $password === '') $error = 'Username and Password are required.';
    elseif($role !== 'admin' && $branch === '') $error = 'Primary Branch is required for Head / Staff.';
    elseif($role === 'head' && $ticket_branch_access === '') $error = 'Please tick at least one Branch for Head.';
    elseif($role !== 'admin' && $ticket_pic_access === '') $error = 'Please tick at least one User Own PIC / PIC Access.';
    else {
        $check = $pdo->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
        $check->execute([$username]);
        if($check->fetch()) $error = 'Username already exists.';
        else {
            $stmt = $pdo->prepare('INSERT INTO users (full_name, username, password, role, branch, branch_access, department, ticket_scope, ticket_branch_access, ticket_pic_access, status) VALUES (?,?,?,?,?,?,?,?,?,?,\'active\')');
            $stmt->execute([$full_name, $username, password_hash($password, PASSWORD_DEFAULT), $role, $branch, $branch_access, $department, $ticket_scope, $ticket_branch_access, $ticket_pic_access]);
            $newUserId = (int)$pdo->lastInsertId();
            header('Location: users.php?focus_user_id='.$newUserId); exit;
        }
    }
}

$selectedRole = normalize_role($_POST['role'] ?? 'staff');
$selectedBranch = $_POST['branch'] ?? '';
$selectedDepartmentPics = normalize_selected_pics($_POST['department'] ?? [], $picList);
$selectedTicketBranches = array_values(array_intersect($branchList, array_map('trim', $_POST['ticket_branch_access'] ?? [])));
$selectedTicketPics = normalize_selected_pics($_POST['ticket_pic_access'] ?? [], $picList);
?>

<style>
.user-form-shell{max-width:1180px;padding-bottom:80px}.user-page-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}.user-page-title h2{margin:0;font-weight:900}.user-section{background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 10px 24px rgba(15,23,42,.05);margin-bottom:16px;overflow:hidden}.user-section-header{width:100%;border:0;background:#fff;padding:18px 20px;display:flex;justify-content:space-between;align-items:center;font-weight:900;color:#0f172a;cursor:pointer;text-align:left}.user-section-header small{display:block;font-weight:500;color:#64748b;margin-top:4px}.user-section-body{padding:20px;border-top:1px solid #eef2f7}.choice-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}.choice-card{display:block;cursor:pointer}.choice-card input{display:none}.choice-card span{display:block;border:1px solid #dbe3f0;border-radius:14px;padding:12px 14px;background:#fff;transition:.18s ease;min-height:68px}.choice-card strong{display:block}.choice-card small{color:#64748b}.choice-card input:checked+span{background:#0d6efd;color:#fff;border-color:#0d6efd;box-shadow:0 8px 20px rgba(13,110,253,.22)}.choice-card input:checked+span small{color:rgba(255,255,255,.88)}.summary-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:14px;color:#475569;font-size:14px}.sticky-actions{position:fixed;right:28px;bottom:24px;background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:12px;box-shadow:0 20px 50px rgba(15,23,42,.18);display:flex;gap:10px;z-index:30}.quick-actions{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}.role-hint{font-size:13px;color:#64748b}
</style>

<?php if(!empty($error)): ?><div class="alert alert-danger"><?= h($error); ?></div><?php endif; ?>
<div class="user-form-shell">
  <div class="user-page-header"><div class="user-page-title"><h2>Add User</h2><div class="text-muted">Create Admin / Head / Staff user with checkbox ticket visibility.</div></div><a href="users.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a></div>
  <form method="post">
    <div class="user-section"><button class="user-section-header" type="button" data-bs-toggle="collapse" data-bs-target="#basic"><span>1. Basic User Info<small>Login information.</small></span><i class="bi bi-chevron-down"></i></button><div id="basic" class="collapse show"><div class="user-section-body"><div class="row g-3">
      <div class="col-md-4"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" value="<?= h($_POST['full_name'] ?? ''); ?>"></div>
      <div class="col-md-4"><label class="form-label">Username</label><input type="text" name="username" class="form-control" value="<?= h($_POST['username'] ?? ''); ?>" required></div>
      <div class="col-md-4"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
    </div></div></div></div>

    <div class="user-section"><button class="user-section-header" type="button" data-bs-toggle="collapse" data-bs-target="#role"><span>2. Role & Primary Branch<small>Only Admin, Head and Staff are supported.</small></span><i class="bi bi-chevron-down"></i></button><div id="role" class="collapse show"><div class="user-section-body"><div class="row g-3">
      <div class="col-md-4"><label class="form-label">Role</label><select name="role" id="roleSelect" class="form-select" onchange="applyRoleHint()"><option value="admin" <?= $selectedRole==='admin'?'selected':''; ?>>Admin</option><option value="head" <?= $selectedRole==='head'?'selected':''; ?>>Head</option><option value="staff" <?= $selectedRole==='staff'?'selected':''; ?>>Staff</option></select><div class="role-hint mt-2" id="roleHint"></div></div>
      <div class="col-md-4"><label class="form-label">Primary Branch</label><select name="branch" class="form-select"><option value="">-- Select Branch --</option><?php foreach($branchList as $b): ?><option class="hd-no-translate notranslate" translate="no" value="<?= h($b); ?>" <?= $selectedBranch===$b?'selected':''; ?>><?= h((function_exists('hd_branch_code_raw') ? hd_branch_code_raw($b) : $b).' - '.($branchNames[$b] ?? $b)); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label">Profile PIC / Department</label><select name="department[]" class="form-select" multiple size="5"><?php foreach($picList as $pic): ?><option value="<?= h($pic); ?>" <?= in_array($pic,$selectedDepartmentPics,true)?'selected':''; ?>><?= h($pic); ?></option><?php endforeach; ?></select></div>
    </div></div></div></div>

    <div class="user-section"><button class="user-section-header" type="button" data-bs-toggle="collapse" data-bs-target="#ticketAccess"><span>3. Ticket Visibility Permission<small>Checkbox access. Staff = Own Branch + User Own PIC.</small></span><i class="bi bi-chevron-down"></i></button><div id="ticketAccess" class="collapse show"><div class="user-section-body">
      <div class="summary-box mb-3"><strong>Final rule:</strong> Admin sees all. Head sees checked Branch + checked PIC. Staff sees own Primary Branch + checked User Own PIC only.</div>
      <h6 class="fw-bold">Branch Access for Head</h6><div class="quick-actions"><button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleBoxes('ticket_branch_access[]',true)">Select All Branch</button><button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleBoxes('ticket_branch_access[]',false)">Clear Branch</button></div>
      <div class="choice-grid mb-4"><?php foreach($branchList as $b): ?><label class="choice-card"><input type="checkbox" name="ticket_branch_access[]" value="<?= h($b); ?>" <?= in_array($b,$selectedTicketBranches,true)?'checked':''; ?>><span><strong class="hd-no-translate notranslate" translate="no"><?= h(function_exists('hd_branch_code_raw') ? hd_branch_code_raw($b) : $b); ?></strong><small><?= h($branchNames[$b] ?? $b); ?></small></span></label><?php endforeach; ?></div>
      <h6 class="fw-bold">PIC Access / User Own PIC</h6><div class="quick-actions"><button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleBoxes('ticket_pic_access[]',true)">Select All PIC</button><button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleBoxes('ticket_pic_access[]',false)">Clear PIC</button></div>
      <div class="choice-grid"><?php foreach($picList as $pic): ?><label class="choice-card"><input type="checkbox" name="ticket_pic_access[]" value="<?= h($pic); ?>" <?= in_array($pic,$selectedTicketPics,true)?'checked':''; ?>><span><strong><?= h($pic); ?></strong><small>Person In Charge</small></span></label><?php endforeach; ?></div>
    </div></div></div>

    <div class="sticky-actions"><a href="users.php" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Create User</button></div>
  </form>
</div>
<script>
function toggleBoxes(name,state){document.querySelectorAll('input[name="'+name+'"]').forEach(function(b){b.checked=state;});}
function applyRoleHint(){var r=document.getElementById('roleSelect').value;var h=document.getElementById('roleHint');if(r==='admin')h.textContent='Admin: all tickets and all admin functions.';else if(r==='head')h.textContent='Head: only checked Branch + checked PIC tickets.';else h.textContent='Staff: only own Primary Branch + checked User Own PIC tickets.';}
applyRoleHint();
</script>
<?php require 'footer.php'; ?>
