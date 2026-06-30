<?php

require 'header.php';
require 'db.php';
require_once 'module_permissions.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

require_action_permission('manage_user');

$users = $pdo->query("
SELECT u.*
FROM users u
ORDER BY
    CASE u.role WHEN 'admin' THEN 1 WHEN 'head' THEN 2 ELSE 3 END,
    u.username ASC
")->fetchAll(PDO::FETCH_ASSOC);

ensure_role_permissions_table($pdo);
$moduleList = module_permission_list();
$actionList = action_permission_list();
$rolePermissionCache = [];

function role_permission_keys_for_display($role, $type)
{
    global $pdo, $rolePermissionCache;
    $role = normalize_permission_role($role ?? 'staff');
    $type = ($type === 'action') ? 'action' : 'module';
    $cacheKey = $role . ':' . $type;

    if(!isset($rolePermissionCache[$cacheKey]))
    {
        $rolePermissionCache[$cacheKey] = get_role_permissions($pdo, $role, $type);
    }

    return $rolePermissionCache[$cacheKey];
}

function h($v)
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function split_csv_list($value)
{
    $items = [];

    foreach(explode(',', (string)$value) as $item)
    {
        $item = trim($item);

        if($item !== '')
        {
            $items[] = $item;
        }
    }

    return array_values(array_unique($items));
}

function render_full_badge_list($items, $badgeClass = 'bg-light text-dark border')
{
    if(!is_array($items))
    {
        $items = split_csv_list($items);
    }

    if(count($items) == 0)
    {
        echo '<span class="text-muted small">-</span>';
        return;
    }

    echo '<div class="compact-badge-wrap">';

    foreach($items as $item)
    {
        echo '<span class="badge '.$badgeClass.' hd-no-translate notranslate" translate="no">'.h(function_exists('hd_branch_code_raw') ? hd_branch_code_raw($item) : $item).'</span>';
    }

    echo '</div>';
}

function action_labels($permissionCsv, $actionList)
{
    $labels = [];

    foreach(split_csv_list($permissionCsv) as $perm)
    {
        $labels[] = $actionList[$perm]['label'] ?? $perm;
    }

    return $labels;
}

function module_labels($permissionCsv, $moduleList)
{
    $labels = [];

    foreach(split_csv_list($permissionCsv) as $perm)
    {
        $labels[] = $moduleList[$perm]['label'] ?? $perm;
    }

    return $labels;
}

$totalUsers = count($users);
$activeUsers = 0;
$inactiveUsers = 0;
$adminUsers = 0;
$headUsers = 0;
$staffUsers = 0;

foreach($users as $u)
{
    if(($u['status'] ?? 'active') == 'active') $activeUsers++; else $inactiveUsers++;

    if(($u['role'] ?? '') == 'admin') $adminUsers++;
    elseif(($u['role'] ?? '') == 'head') $headUsers++;
    else $staffUsers++;
}

$roleClass = [
    'admin' => 'bg-primary',
    'head' => 'bg-info text-dark',
    'staff' => 'bg-secondary'
];

$scopeLabel = [
    'ALL' => ['All Tickets', 'bg-primary'],
    'BRANCH' => ['Branch / PIC Tickets', 'bg-info text-dark'],
    'OWN' => ['Own / Related', 'bg-secondary']
];

?>

<style>
.user-page-wrap{max-width:100%;overflow-x:hidden}.user-page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:14px}.user-page-title{font-weight:900;color:#0f172a;margin:0}.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:14px}.summary-card{background:#fff;border:1px solid #e8eef7;border-radius:16px;padding:16px;box-shadow:0 8px 20px rgba(15,23,42,.045)}.summary-no{font-size:26px;font-weight:950;line-height:1}.summary-label{font-weight:750;color:#64748b;font-size:13px;margin-top:5px}.user-toolbar{background:#fff;border:1px solid #e8eef7;border-radius:16px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;box-shadow:0 8px 20px rgba(15,23,42,.04)}.search-box{max-width:430px}.hint{font-size:12px;color:#64748b}.user-list{display:flex;flex-direction:column;gap:12px}.user-card{background:#fff;border:1px solid #e8eef7;border-radius:18px;box-shadow:0 8px 24px rgba(15,23,42,.05);overflow:hidden}.user-main-row{display:grid;grid-template-columns:60px 1.15fr 140px 110px 145px 1fr 210px;gap:12px;align-items:center;padding:14px 16px;border-bottom:1px solid #edf2f7;background:#fbfdff}.seq-box{width:34px;height:34px;border-radius:10px;background:#eef4ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-weight:900}.user-name{font-weight:900;color:#0f172a}.user-login{font-size:12px;color:#64748b}.role-badge{text-transform:capitalize}.visibility-badge{white-space:normal;text-align:center}.user-actions{display:flex;flex-wrap:wrap;gap:6px;justify-content:flex-end}.user-actions .btn{font-size:12px;padding:5px 9px;border-radius:9px}.user-detail-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;padding:14px 16px}.permission-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:0 16px 16px}.detail-box{border:1px solid #edf2f7;border-radius:14px;background:#fff;padding:12px;min-height:74px}.detail-label{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;font-weight:900;margin-bottom:7px}.compact-badge-wrap{display:flex;flex-wrap:wrap;gap:5px}.compact-badge-wrap .badge{font-size:10.5px;font-weight:750;line-height:1.2;white-space:normal;text-align:left;padding:.42em .58em}.status-dot{display:inline-flex;align-items:center;gap:6px}.status-dot:before{content:'';width:8px;height:8px;border-radius:50%;background:#22c55e}.status-dot.inactive:before{background:#ef4444}.empty-state{background:#fff;border:1px solid #e8eef7;border-radius:18px;text-align:center;color:#64748b;padding:32px}.toggle-details{font-size:12px}.user-return-highlight{animation:userReturnFlash 2.8s ease-in-out 1;box-shadow:0 0 0 3px rgba(13,110,253,.22),0 8px 24px rgba(15,23,42,.05)!important}@keyframes userReturnFlash{0%{background:#fff7d6}45%{background:#fff7d6}100%{background:#fff}}.collapse-user .permission-grid,.collapse-user .user-detail-grid{display:none}.view-mode-buttons .btn{font-size:12px;border-radius:9px}@media(max-width:1500px){.user-main-row{grid-template-columns:50px 1fr 105px 90px 120px 1fr 190px;font-size:12px}.user-detail-grid{grid-template-columns:1fr 1fr}.permission-grid{grid-template-columns:1fr}.summary-grid{grid-template-columns:repeat(4,1fr)}}@media(max-width:992px){.summary-grid,.user-main-row,.user-detail-grid,.permission-grid{grid-template-columns:1fr}.user-actions{justify-content:flex-start}.search-box{max-width:100%;width:100%}}
</style>

<div class="user-page-wrap">
    <div class="user-page-head">
        <div>
            <h2 class="user-page-title">User Management</h2>
            <div class="text-muted">Manage login, branch access and PIC visibility. Module / Action permissions are inherited from Role Permission Matrix.</div>
        </div>

        <a href="add_user.php" class="btn btn-success">
            <i class="bi bi-person-plus me-1"></i> Add User
        </a>
    </div>

    <div class="summary-grid">
        <div class="summary-card"><div class="summary-no"><?= (int)$totalUsers; ?></div><div class="summary-label">Total Users</div></div>
        <div class="summary-card"><div class="summary-no text-success"><?= (int)$activeUsers; ?></div><div class="summary-label">Active</div></div>
        <div class="summary-card"><div class="summary-no text-primary"><?= (int)$adminUsers; ?></div><div class="summary-label">Admin</div></div>
        <div class="summary-card"><div class="summary-no text-info"><?= (int)$headUsers + (int)$staffUsers; ?></div><div class="summary-label">Head / Staff</div></div>
    </div>

    <div class="user-toolbar">
        <div class="input-group search-box">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="userSearch" class="form-control" placeholder="Search user, branch, PIC, permission...">
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="hint">100% view optimized. All permission info is shown without +more.</span>
            <div class="view-mode-buttons">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="collapseAllUsers">Compact</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="expandAllUsers">Expand All</button>
            </div>
        </div>
    </div>

    <div class="user-list" id="usersList">
        <?php $seqNo = 1; foreach($users as $user): ?>
        <?php
            $scope = $user['ticket_scope'] ?? 'OWN';
            $sl = $scopeLabel[$scope] ?? $scopeLabel['OWN'];
            $branchAccess = $user['branch_access'] ?? '';
            $ticketBranchAccess = $user['ticket_branch_access'] ?? '';
            $ticketPicAccess = $user['ticket_pic_access'] ?? '';

            $userRole = normalize_permission_role($user['role'] ?? 'staff');

            if($userRole === 'admin')
            {
                $moduleLabels = ['All Modules'];
                $actionLabels = ['All Actions'];
            }
            else
            {
                $moduleLabels = module_labels(implode(',', role_permission_keys_for_display($userRole, 'module')), $moduleList);
                $actionLabels = action_labels(implode(',', role_permission_keys_for_display($userRole, 'action')), $actionList);
            }
        ?>

        <div class="user-card" id="user-<?= (int)$user['id']; ?>" data-user-id="<?= (int)$user['id']; ?>" data-search="<?= h(strtolower(($user['username'] ?? '').' '.($user['full_name'] ?? '').' '.($user['role'] ?? '').' '.($user['branch'] ?? '').' '.($user['department'] ?? '').' '.$branchAccess.' '.$ticketBranchAccess.' '.$ticketPicAccess.' '.implode(' ', $moduleLabels).' '.implode(' ', $actionLabels))); ?>">
            <div class="user-main-row">
                <div><div class="seq-box"><?= $seqNo++; ?></div></div>

                <div>
                    <div class="user-name"><?= h($user['full_name'] ?: $user['username']); ?></div>
                    <div class="user-login"><?= h($user['username']); ?> · ID: <?= (int)$user['id']; ?></div>
                </div>

                <div>
                    <span class="badge role-badge <?= $roleClass[$user['role'] ?? 'staff'] ?? 'bg-secondary'; ?>"><?= h($user['role'] ?? 'staff'); ?></span>
                </div>

                <div>
                    <?php if(($user['status'] ?? 'active') == 'active'): ?>
                        <span class="badge bg-success status-dot">Active</span>
                    <?php else: ?>
                        <span class="badge bg-danger status-dot inactive">Inactive</span>
                    <?php endif; ?>
                </div>

                <div>
                    <span class="badge bg-dark hd-no-translate notranslate" translate="no"><?= h(function_exists('hd_branch_code_raw') ? hd_branch_code_raw($user['branch'] ?: '-') : ($user['branch'] ?: '-')); ?></span>
                </div>

                <div>
                    <span class="badge <?= $sl[1]; ?> visibility-badge"><?= h($sl[0]); ?></span>
                    <div class="small text-muted mt-1">PIC:</div><?php render_full_badge_list($user['department'] ?? '', 'bg-success-subtle text-success border'); ?>
                </div>

                <div class="user-actions">
                    <a href="edit_user.php?id=<?= (int)$user['id']; ?>" class="btn btn-primary btn-sm" onclick="rememberUserPosition(<?= (int)$user['id']; ?>)">Edit</a>
                    <a href="add_user.php?copy_user_id=<?= (int)$user['id']; ?>" class="btn btn-info btn-sm text-white" onclick="rememberUserPosition(<?= (int)$user['id']; ?>)">Copy</a>
                    <a href="toggle_user_status.php?id=<?= (int)$user['id']; ?>" class="btn btn-warning btn-sm" onclick="rememberUserPosition(<?= (int)$user['id']; ?>); return confirm('Are you sure?');"><?= ($user['status'] ?? 'active') == 'active' ? 'Disable' : 'Enable'; ?></a>
                    <?php if((int)$user['id'] != (int)($_SESSION['user_id'] ?? 0)): ?>
                    <a href="delete_user.php?id=<?= (int)$user['id']; ?>" class="btn btn-danger btn-sm" onclick="rememberUserPosition(<?= (int)$user['id']; ?>); return confirm('Delete this user? This action cannot be undone.');">Delete</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="user-detail-grid">
                <div class="detail-box">
                    <div class="detail-label">Branch Access</div>
                    <?php render_full_badge_list($branchAccess, 'bg-light text-dark border'); ?>
                </div>
                <div class="detail-box">
                    <div class="detail-label">Ticket Branch Access</div>
                    <?php render_full_badge_list($ticketBranchAccess, 'bg-primary-subtle text-primary border'); ?>
                </div>
                <div class="detail-box">
                    <div class="detail-label">Ticket PIC Access</div>
                    <?php render_full_badge_list($ticketPicAccess, 'bg-success-subtle text-success border'); ?>
                </div>
            </div>

            <div class="permission-grid">
                <div class="detail-box">
                    <div class="detail-label">Role Module Permission</div>
                    <?php render_full_badge_list($moduleLabels, (($user['role'] ?? '') == 'admin') ? 'bg-primary' : 'bg-light text-dark border'); ?>
                </div>
                <div class="detail-box">
                    <div class="detail-label">Role Action Permission</div>
                    <?php render_full_badge_list($actionLabels, (($user['role'] ?? '') == 'admin') ? 'bg-primary' : 'bg-warning-subtle text-warning border'); ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if(count($users) == 0): ?>
            <div class="empty-state">No users found</div>
        <?php endif; ?>
    </div>
</div>

<script>
function rememberUserPosition(userId){
    localStorage.setItem('usersReturnUserId', String(userId || ''));
    localStorage.setItem('usersReturnScrollY', String(window.scrollY || 0));
}

document.addEventListener('DOMContentLoaded', function(){
    const input = document.getElementById('userSearch');
    const cards = document.querySelectorAll('.user-card');
    const collapseBtn = document.getElementById('collapseAllUsers');
    const expandBtn = document.getElementById('expandAllUsers');

    if(input)
    {
        input.addEventListener('input', function(){
            const keyword = input.value.toLowerCase().trim();

            cards.forEach(function(card){
                const text = card.getAttribute('data-search') || card.innerText.toLowerCase();
                card.style.display = text.includes(keyword) ? '' : 'none';
            });
        });
    }

    if(collapseBtn)
    {
        collapseBtn.addEventListener('click', function(){
            cards.forEach(function(card){ card.classList.add('collapse-user'); });
            localStorage.setItem('usersCompactMode','yes');
        });
    }

    if(expandBtn)
    {
        expandBtn.addEventListener('click', function(){
            cards.forEach(function(card){ card.classList.remove('collapse-user'); });
            localStorage.setItem('usersCompactMode','no');
        });
    }

    if(localStorage.getItem('usersCompactMode') === 'yes')
    {
        cards.forEach(function(card){ card.classList.add('collapse-user'); });
    }

    const params = new URLSearchParams(window.location.search);
    const focusUserId = params.get('focus_user_id') || localStorage.getItem('usersReturnUserId');
    const fallbackScrollY = parseInt(localStorage.getItem('usersReturnScrollY') || '0', 10);

    setTimeout(function(){
        let restored = false;

        if(focusUserId)
        {
            const target = document.getElementById('user-' + focusUserId);

            if(target)
            {
                target.scrollIntoView({behavior:'auto', block:'center'});
                target.classList.add('user-return-highlight');
                setTimeout(function(){ target.classList.remove('user-return-highlight'); }, 3200);
                restored = true;
            }
        }

        if(!restored && fallbackScrollY > 0)
        {
            window.scrollTo(0, fallbackScrollY);
        }

        localStorage.removeItem('usersReturnUserId');
        localStorage.removeItem('usersReturnScrollY');
    }, 120);

});
</script>

<?php require 'footer.php'; ?>
