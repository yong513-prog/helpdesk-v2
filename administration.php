<?php
require 'header.php';
require 'db.php';
require_once 'module_permissions.php';
require_once 'audit_log.php';

if(!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if(function_exists('normalize_role') && normalize_role($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); die('Access Denied'); }
ensure_role_permissions_table($pdo);
function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

$roles = ['head'=>'Head', 'staff'=>'Staff'];
$success = '';
if($_SERVER['REQUEST_METHOD']==='POST') {
    foreach($roles as $role=>$label) {
        save_role_permissions($pdo, $role, 'module', $_POST['module'][$role] ?? []);
        save_role_permissions($pdo, $role, 'action', $_POST['action'][$role] ?? []);
    }
    if(function_exists('audit_log')) audit_log($pdo, 'Update Role Permission', 'Updated Head/Staff role permission checkbox matrix.');
    $success = 'Role permissions updated. This is effective for all users immediately.';
}

$modules = module_permission_list();
$actions = action_permission_list();
$selected = [];
foreach($roles as $role=>$label) {
    $selected[$role]['module'] = get_role_permissions($pdo, $role, 'module');
    $selected[$role]['action'] = get_role_permissions($pdo, $role, 'action');
}

$cards = [
    ['title'=>'User Management','desc'=>'Only choose Admin / Head / Staff, then assign branch and PIC access.','icon'=>'bi-people','url'=>'users.php'],
    ['title'=>'Assign To Management','desc'=>'Controls ticket Assign To dropdown. Staff visibility follows assigned_to.','icon'=>'bi-person-check','url'=>'assign_to_management.php'],
    ['title'=>'PIC Management','desc'=>'Controls Person In Charge choices for ticket and Head visibility.','icon'=>'bi-person-badge','url'=>'pic_management.php'],
    ['title'=>'Branch Management','desc'=>'Branch master data for tickets and Head/Staff access.','icon'=>'bi-shop','url'=>'branch_management.php'],
    ['title'=>'Asset Type Management','desc'=>'Asset type master list used by Add Asset and Edit Asset.','icon'=>'bi-hdd-stack','url'=>'asset_type_management.php'],
    ['title'=>'Category Management','desc'=>'Ticket category setup linked to Create Ticket and reports.','icon'=>'bi-tags','url'=>'category_management.php'],
    ['title'=>'SLA Management','desc'=>'SLA rules linked to due date and monitoring.','icon'=>'bi-stopwatch','url'=>'sla_management.php'],
    ['title'=>'Ticket Status Management','desc'=>'Controls ticket status dropdown, badge color and closed/archive logic.','icon'=>'bi-kanban','url'=>'ticket_status_management.php'],
    ['title'=>'Audit Logs','desc'=>'Review administration changes.','icon'=>'bi-clock-history','url'=>'audit_logs.php'],
];
$stats=['users'=>0,'head'=>0,'staff'=>0,'branches'=>0,'pics'=>0];
try{$stats['users']=(int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();}catch(Exception $e){}
try{$stats['head']=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='head'")->fetchColumn();}catch(Exception $e){}
try{$stats['staff']=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='staff' OR role NOT IN ('admin','head')")->fetchColumn();}catch(Exception $e){}
try{$stats['branches']=(int)$pdo->query("SELECT COUNT(*) FROM branches WHERE status='active'")->fetchColumn();}catch(Exception $e){}
try{$stats['pics']=(int)$pdo->query("SELECT COUNT(*) FROM person_in_charge WHERE status='active'")->fetchColumn();}catch(Exception $e){}
?>
<style>
.admin-hero{background:linear-gradient(135deg,#0f172a,#1d4ed8);border-radius:22px;color:#fff;padding:24px;margin-bottom:18px;box-shadow:0 14px 34px rgba(15,23,42,.18)}.admin-hero h2{font-weight:950;margin:0 0 6px}.admin-hero p{margin:0;color:#dbeafe}.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-top:18px}.stat{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.16);border-radius:16px;padding:14px}.stat strong{display:block;font-size:24px}.admin-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin-bottom:18px}.admin-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:18px;box-shadow:0 10px 24px rgba(15,23,42,.05);display:flex;gap:14px;align-items:flex-start;text-decoration:none;color:#0f172a}.admin-icon{width:46px;height:46px;border-radius:15px;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:22px}.matrix-card{background:#fff;border:1px solid #e5e7eb;border-radius:20px;padding:18px;box-shadow:0 10px 24px rgba(15,23,42,.05)}.perm-table th{background:#f8fafc}.role-col{width:120px;text-align:center}.group-row td{background:#eef4ff;font-weight:900;color:#1e3a8a}.small-muted{font-size:12px;color:#64748b}.sticky-actions{position:sticky;bottom:0;background:rgba(255,255,255,.94);border:1px solid #e5e7eb;border-radius:16px;padding:12px;margin-top:14px;box-shadow:0 -8px 24px rgba(15,23,42,.06)}
</style>
<div class="admin-hero"><h2><i class="bi bi-sliders"></i> Administration Control Panel</h2><p>3-role system only: Admin, Head, Staff. Tick the functions here once and every user under that role follows it.</p><div class="stat-grid"><div class="stat"><span>Total Users</span><strong><?= $stats['users']; ?></strong></div><div class="stat"><span>Head</span><strong><?= $stats['head']; ?></strong></div><div class="stat"><span>Staff</span><strong><?= $stats['staff']; ?></strong></div><div class="stat"><span>Branches</span><strong><?= $stats['branches']; ?></strong></div><div class="stat"><span>Active PIC</span><strong><?= $stats['pics']; ?></strong></div></div></div>
<?php if($success): ?><div class="alert alert-success"><?= h($success); ?></div><?php endif; ?>
<div class="admin-grid"><?php foreach($cards as $c): ?><a class="admin-card" href="<?= h($c['url']); ?>"><div class="admin-icon"><i class="bi <?= h($c['icon']); ?>"></i></div><div><h5 class="fw-bold mb-1"><?= h($c['title']); ?></h5><p class="small-muted mb-0"><?= h($c['desc']); ?></p></div></a><?php endforeach; ?></div>
<form method="post" class="matrix-card">
<h4 class="fw-bold mb-1">Role Permission Checkbox Matrix</h4><div class="small-muted mb-3">Admin always has all permissions and is not shown. Head / Staff are controlled here globally.</div>
<div class="d-flex gap-2 mb-3 flex-wrap"><button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleRole('head',true)">Tick all Head</button><button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleRole('head',false)">Clear Head</button><button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleRole('staff',true)">Tick all Staff</button><button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleRole('staff',false)">Clear Staff</button></div>
<div class="table-responsive"><table class="table table-bordered align-middle perm-table"><thead><tr><th>Function</th><th class="role-col">Head<br><input type="checkbox" onclick="toggleRole('head',this.checked)"></th><th class="role-col">Staff<br><input type="checkbox" onclick="toggleRole('staff',this.checked)"></th><th>Description</th></tr></thead><tbody>
<tr class="group-row"><td colspan="4">Menu / Page Access</td></tr>
<?php foreach($modules as $key=>$info): ?><tr><td><strong><?= h($info['label']); ?></strong><div class="small-muted"><?= h($key); ?></div></td><?php foreach($roles as $role=>$label): ?><td class="text-center"><input class="role-<?= h($role); ?>" type="checkbox" name="module[<?= h($role); ?>][]" value="<?= h($key); ?>" <?= in_array($key,$selected[$role]['module'],true)?'checked':''; ?>></td><?php endforeach; ?><td class="small-muted"><?= h($info['description']); ?></td></tr><?php endforeach; ?>
<tr class="group-row"><td colspan="4">Action Permission</td></tr>
<?php foreach($actions as $key=>$info): ?><tr><td><strong><?= h($info['label']); ?></strong><div class="small-muted"><?= h($key); ?></div></td><?php foreach($roles as $role=>$label): ?><td class="text-center"><input class="role-<?= h($role); ?>" type="checkbox" name="action[<?= h($role); ?>][]" value="<?= h($key); ?>" <?= in_array($key,$selected[$role]['action'],true)?'checked':''; ?>></td><?php endforeach; ?><td class="small-muted"><?= h($info['description']); ?></td></tr><?php endforeach; ?>
</tbody></table></div><div class="sticky-actions"><button class="btn btn-primary"><i class="bi bi-save"></i> Save Role Permissions</button></div></form>
<script>function toggleRole(role,checked){document.querySelectorAll('.role-'+role).forEach(b=>b.checked=checked);}</script>
<?php require 'footer.php'; ?>
