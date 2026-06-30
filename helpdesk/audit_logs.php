<?php
require 'header.php';
require 'db.php';
require_once 'module_permissions.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'] ?? '';
$isAdmin = current_user_has_permission('audit_logs');
$canExportAudit = has_action_permission('export_audit');

$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';
$keyword    = trim($_GET['keyword'] ?? '');
$action     = $_GET['action'] ?? '';
$username   = trim($_GET['username'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = (int)($_GET['per_page'] ?? 25);
if(!in_array($per_page, [10,25,50,100])) { $per_page = 25; }
$offset = ($page - 1) * $per_page;

$where = " WHERE 1=1 ";
$params = [];

if(!$isAdmin)
{
    $where .= " AND username = ? ";
    $params[] = $_SESSION['username'] ?? '';
}

if($start_date !== '')
{
    $where .= " AND DATE(created_at) >= ? ";
    $params[] = $start_date;
}

if($end_date !== '')
{
    $where .= " AND DATE(created_at) <= ? ";
    $params[] = $end_date;
}

if($keyword !== '')
{
    $where .= " AND (username LIKE ? OR action LIKE ? OR details LIKE ?) ";
    $params[] = "%".$keyword."%";
    $params[] = "%".$keyword."%";
    $params[] = "%".$keyword."%";
}

if($username !== '')
{
    $where .= " AND username LIKE ? ";
    $params[] = "%".$username."%";
}

if($action !== '')
{
    $where .= " AND action = ? ";
    $params[] = $action;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs " . $where);
$countStmt->execute($params);
$total_rows = (int)$countStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
if($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $per_page; }

$sql = "SELECT * FROM audit_logs " . $where . " ORDER BY id DESC LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs WHERE action IS NOT NULL AND action != '' ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

if($isAdmin)
{
    $users = $pdo->query("SELECT DISTINCT username FROM audit_logs WHERE username IS NOT NULL AND username != '' ORDER BY username ASC")->fetchAll(PDO::FETCH_COLUMN);
}
else
{
    $users = [$_SESSION['username'] ?? ''];
}

$statsWhere = $isAdmin ? "" : " WHERE username = " . $pdo->quote($_SESSION['username'] ?? '');
$totalAll = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs" . $statsWhere)->fetchColumn();
$todaySql = "SELECT COUNT(*) FROM audit_logs " . ($statsWhere ? $statsWhere . " AND " : " WHERE ") . " DATE(created_at) = CURDATE()";
$todayAll = (int)$pdo->query($todaySql)->fetchColumn();
$usersSql = "SELECT COUNT(DISTINCT username) FROM audit_logs" . $statsWhere;
$userCount = (int)$pdo->query($usersSql)->fetchColumn();
$lastSql = "SELECT created_at FROM audit_logs" . $statsWhere . " ORDER BY id DESC LIMIT 1";
$lastLog = $pdo->query($lastSql)->fetchColumn();

function audit_badge_class($action)
{
    $a = strtolower((string)$action);
    if(strpos($a, 'login') !== false) return 'bg-primary';
    if(strpos($a, 'logout') !== false) return 'bg-secondary';
    if(strpos($a, 'delete') !== false) return 'bg-danger';
    if(strpos($a, 'create') !== false || strpos($a, 'add') !== false) return 'bg-success';
    if(strpos($a, 'edit') !== false || strpos($a, 'update') !== false) return 'bg-warning text-dark';
    if(strpos($a, 'export') !== false) return 'bg-info text-dark';
    return 'bg-dark';
}


function audit_display_raw_code($value)
{
    $value = trim((string)$value);
    if($value === '') return $value;

    // Branch codes / user names are database values. Do NOT translate them.
    // Normalize old translated branch names back to their original branch code.
    $map = [
        '电脑'=>'PC','電腦'=>'PC','Computer'=>'PC','computer'=>'PC','Komputer'=>'PC','komputer'=>'PC',
        'HQ'=>'HQ','hq'=>'HQ','KB'=>'KB','kb'=>'KB','KC'=>'KC','kc'=>'KC','KJ'=>'KJ','kj'=>'KJ','KK'=>'KK','kk'=>'KK','KL'=>'KL','kl'=>'KL','KR'=>'KR','kr'=>'KR','KS'=>'KS','ks'=>'KS','ML'=>'ML','ml'=>'ML',
        'PC'=>'PC','pc'=>'PC','PJ'=>'PJ','pj'=>'PJ','PKL'=>'PKL','pkl'=>'PKL','PM'=>'PM','pm'=>'PM','SE'=>'SE','se'=>'SE','TM'=>'TM','tm'=>'TM','TPC'=>'TPC','tpc'=>'TPC','TPN'=>'TPN','tpn'=>'TPN','TPT'=>'TPT','tpt'=>'TPT','WC'=>'WC','wc'=>'WC','WK'=>'WK','wk'=>'WK',
        'BS'=>'BS','bs'=>'BS','LAS'=>'LAS','las'=>'LAS','YAP'=>'YAP','yap'=>'YAP'
    ];
    if(isset($map[$value])) return $map[$value];

    // If a ticket/branch code was stored together with a number, fix only the prefix.
    if(preg_match('/^(电脑|電腦|Computer|computer|Komputer|komputer)-?(\d{6}-\d+)$/u', $value, $m)) return 'PC-' . $m[2];
    if(preg_match('/^pc(-?\d{6}-\d+)$/i', $value, $m)) return 'PC' . (strpos($m[1], '-') === 0 ? $m[1] : '-' . $m[1]);

    return $value;
}

function audit_branch_code_no_translate($value)
{
    return audit_display_raw_code($value);
}

function audit_normalize_action($action)
{
    $action = trim((string)$action);
    $normalized = preg_replace('/\s+/', ' ', $action);
    $replace = [
        '删除 Asset'=>'Delete Asset', '删除资产'=>'Delete Asset', 'Deleted Asset'=>'Delete Asset',
        '停用 Asset'=>'Disable Asset', '停用资产'=>'Disable Asset',
        '启用 Asset'=>'Enable Asset', '启用资产'=>'Enable Asset',
        '编辑 Asset'=>'Edit Asset', '编辑资产'=>'Edit Asset',
        '添加 Asset'=>'Add Asset', '添加资产'=>'Add Asset',
        'Assign 工单'=>'Assign Ticket', '指派工单'=>'Assign Ticket',
        'Create 公告'=>'Create Announcement', '创建公告'=>'Create Announcement',
        'Create 工单'=>'Create Ticket', '创建工单'=>'Create Ticket',
        'Delete 工单'=>'Delete Ticket', 'Deleted Ticket'=>'Delete Ticket', '删除工单'=>'Delete Ticket',
        'Reply 工单'=>'Reply Ticket', '回复工单'=>'Reply Ticket',
        '更新 Permission'=>'Update Role Permission', 'Update Permission'=>'Update Role Permission', '更新角色 Permission'=>'Update Role Permission',
        'Permission'=>'Update Role Permission', 'Update Role'=>'Update Role Permission', 'Update Role Permission'=>'Update Role Permission'
    ];
    return $replace[$normalized] ?? $normalized;
}

function audit_action_display($action)
{
    $key = audit_normalize_action($action);
    $lang = function_exists('hd_lang') ? hd_lang() : 'en';
    $map = [
        'en' => [
            'Delete Asset'=>'Delete Asset','Enable Asset'=>'Enable Asset','Disable Asset'=>'Disable Asset','Edit Asset'=>'Edit Asset','Add Asset'=>'Add Asset',
            'Assign Ticket'=>'Assign Ticket','Create Announcement'=>'Create Announcement','Create Ticket'=>'Create Ticket','Delete Ticket'=>'Delete Ticket','Reply Ticket'=>'Reply Ticket',
            'Update Role Permission'=>'Update Role Permission','Login'=>'Login','Logout'=>'Logout','Export'=>'Export'
        ],
        'ms' => [
            'Delete Asset'=>'Padam Aset','Enable Asset'=>'Aktifkan Aset','Disable Asset'=>'Nyahaktifkan Aset','Edit Asset'=>'Edit Aset','Add Asset'=>'Tambah Aset',
            'Assign Ticket'=>'Tugaskan Tiket','Create Announcement'=>'Cipta Pengumuman','Create Ticket'=>'Cipta Tiket','Delete Ticket'=>'Padam Tiket','Reply Ticket'=>'Balas Tiket',
            'Update Role Permission'=>'Kemas Kini Kebenaran Peranan','Login'=>'Log Masuk','Logout'=>'Log Keluar','Export'=>'Eksport'
        ],
        'zh' => [
            'Delete Asset'=>'删除资产','Enable Asset'=>'启用资产','Disable Asset'=>'停用资产','Edit Asset'=>'编辑资产','Add Asset'=>'添加资产',
            'Assign Ticket'=>'指派工单','Create Announcement'=>'创建公告','Create Ticket'=>'创建工单','Delete Ticket'=>'删除工单','Reply Ticket'=>'回复工单',
            'Update Role Permission'=>'更新角色权限','Login'=>'登录','Logout'=>'退出登录','Export'=>'导出'
        ]
    ];
    return $map[$lang][$key] ?? (function_exists('__') ? __($key) : $key);
}

function audit_details_display($details)
{
    $d = (string)$details;
    // Fix old translated branch code in existing audit records.
    $d = preg_replace('/\b(电脑|Computer|Komputer)-?(\d{6}-\d+)\b/u', 'PC-$2', $d);
    $d = preg_replace('/\bPC(?=\d{6}-\d+)\b/u', 'PC-', $d);

    $lang = function_exists('hd_lang') ? hd_lang() : 'en';
    if($lang === 'zh'){
        $repl = [
            'Deleted Asset '=>'删除资产 ', 'Delete Asset '=>'删除资产 ', 'Deleted asset '=>'删除资产 ',
            'Enabled Asset '=>'启用资产 ', 'Enable Asset '=>'启用资产 ',
            'Disabled Asset '=>'停用资产 ', 'Disable Asset '=>'停用资产 ',
            'Edited Asset '=>'编辑资产 ', 'Edit Asset '=>'编辑资产 ',
            'Added Asset '=>'添加资产 ', 'Add Asset '=>'添加资产 ',
            'Deleted announcement:'=>'删除公告：', 'Deleted Announcement:'=>'删除公告：',
            'Deleted article '=>'删除文章 ', 'Deleted Article '=>'删除文章 ',
            'Created ticket '=>'创建工单 ', 'Created Ticket '=>'创建工单 ',
            'Deleted ticket '=>'删除工单 ', 'Deleted Ticket '=>'删除工单 ', 'Deleted 工单 '=>'删除工单 ', 'Deleted 工单'=>'删除工单',
            'Ticket '=>'工单 ', ' replied'=>' 已回复',
            'Updated role permission'=>'更新角色权限', 'Updated Role Permission'=>'更新角色权限',
            'Deleted user '=>'删除用户 ', 'Created user '=>'创建用户 ', 'Edited user '=>'编辑用户 '
        ];
        return strtr($d, $repl);
    }
    if($lang === 'ms'){
        $repl = [
            'Deleted Asset '=>'Padam Aset ', 'Delete Asset '=>'Padam Aset ', 'Deleted asset '=>'Padam Aset ',
            'Enabled Asset '=>'Aktifkan Aset ', 'Enable Asset '=>'Aktifkan Aset ',
            'Disabled Asset '=>'Nyahaktifkan Aset ', 'Disable Asset '=>'Nyahaktifkan Aset ',
            'Edited Asset '=>'Edit Aset ', 'Edit Asset '=>'Edit Aset ',
            'Added Asset '=>'Tambah Aset ', 'Add Asset '=>'Tambah Aset ',
            'Deleted announcement:'=>'Padam Pengumuman:', 'Deleted Announcement:'=>'Padam Pengumuman:',
            'Deleted article '=>'Padam Artikel ', 'Deleted Article '=>'Padam Artikel ',
            'Created ticket '=>'Cipta Tiket ', 'Created Ticket '=>'Cipta Tiket ',
            'Deleted ticket '=>'Padam Tiket ', 'Deleted Ticket '=>'Padam Tiket ', 'Deleted 工单 '=>'Padam Tiket ', 'Deleted 工单'=>'Padam Tiket',
            'Ticket '=>'Tiket ', ' replied'=>' dibalas',
            'Updated role permission'=>'Kemas Kini Kebenaran Peranan', 'Updated Role Permission'=>'Kemas Kini Kebenaran Peranan',
            'Deleted user '=>'Padam Pengguna ', 'Created user '=>'Cipta Pengguna ', 'Edited user '=>'Edit Pengguna '
        ];
        return strtr($d, $repl);
    }
    // English: normalize any mixed Chinese label back to English.
    $repl = [
        '删除资产 '=>'Deleted Asset ', '删除工单 '=>'Deleted Ticket ', 'Deleted 工单 '=>'Deleted Ticket ', '创建时间 工单 '=>'Created ticket ',
        '创建工单 '=>'Created ticket ', '回复工单 '=>'Ticket ', '已回复'=>' replied'
    ];
    return strtr($d, $repl);
}

function build_query_keep($extra = [])
{
    $q = array_merge($_GET, $extra);
    foreach($q as $k => $v)
    {
        if($v === '' || $v === null) unset($q[$k]);
    }
    return http_build_query($q);
}
?>

<style>
.audit-page .page-title-card{border:0;border-radius:18px;background:linear-gradient(135deg,#ffffff,#f8fbff);box-shadow:0 8px 24px rgba(15,23,42,.06)}
.audit-page .stat-card{border:0;border-radius:16px;box-shadow:0 8px 24px rgba(15,23,42,.06);height:100%}
.audit-page .stat-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff}
.audit-page .filter-card,.audit-page .table-card{border:0;border-radius:18px;box-shadow:0 8px 24px rgba(15,23,42,.06)}
.audit-page .table thead th{background:#f8fafc;color:#334155;border-bottom:1px solid #e5e7eb;font-size:13px;text-transform:uppercase;letter-spacing:.03em}
.audit-page .table td{vertical-align:middle}
.audit-page .details-cell{max-width:520px;white-space:normal;word-break:break-word;color:#475569}
.audit-page .date-main{font-weight:600;color:#0f172a}.audit-page .date-sub{font-size:12px;color:#64748b}
.audit-page .empty-state{padding:45px;text-align:center;color:#64748b}
.audit-page .btn-soft{border:1px solid #dbeafe;background:#eff6ff;color:#1d4ed8}
@media print{.sidebar,.topbar,.filter-card,.no-print,.btn{display:none!important}.content,.main-content{margin:0!important}.audit-page .card{box-shadow:none!important}}
</style>

<div class="audit-page">

<div class="card page-title-card mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:linear-gradient(135deg,#2563eb,#4f46e5);">📋</div>
                <div>
                    <h2 class="mb-1"><?= htmlspecialchars(__('Audit Logs')); ?></h2>
                    <div class="text-muted">Track login, logout, create, edit, delete and export activities.</div>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 no-print">
            <button onclick="window.print()" class="btn btn-outline-secondary">🖨 Print</button>
            <?php if($canExportAudit): ?><a href="export_audit.php?<?= htmlspecialchars(build_query_keep(['page'=>null])); ?>" class="btn btn-success">⬇ Export CSV</a><?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card"><div class="card-body d-flex gap-3 align-items-center">
            <div class="stat-icon" style="background:linear-gradient(135deg,#6366f1,#4338ca);">🧾</div>
            <div><h4 class="mb-0"><?= number_format($totalAll); ?></h4><div class="text-muted small"><?= htmlspecialchars(__('Total Logs')); ?></div></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card"><div class="card-body d-flex gap-3 align-items-center">
            <div class="stat-icon" style="background:linear-gradient(135deg,#16a34a,#22c55e);">📅</div>
            <div><h4 class="mb-0"><?= number_format($todayAll); ?></h4><div class="text-muted small"><?= htmlspecialchars(__('Today Activity')); ?></div></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card"><div class="card-body d-flex gap-3 align-items-center">
            <div class="stat-icon" style="background:linear-gradient(135deg,#0ea5e9,#2563eb);">👤</div>
            <div><h4 class="mb-0"><?= number_format($userCount); ?></h4><div class="text-muted small"><?= htmlspecialchars(__('Active Users')); ?></div></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card"><div class="card-body d-flex gap-3 align-items-center">
            <div class="stat-icon" style="background:linear-gradient(135deg,#f97316,#f59e0b);">⏱</div>
            <div><h6 class="mb-0"><?= $lastLog ? htmlspecialchars(date('d/m/Y H:i', strtotime($lastLog))) : '-'; ?></h6><div class="text-muted small"><?= htmlspecialchars(__('Last Activity')); ?></div></div>
        </div></div>
    </div>
</div>

<div class="card filter-card mb-4 no-print">
    <div class="card-body">
        <form method="get" action="audit_logs.php">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label"><?= htmlspecialchars(__('Start Date')); ?></label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= htmlspecialchars(__('End Date')); ?></label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= htmlspecialchars(__('User')); ?></label>
                    <select name="username" class="form-select hd-no-translate notranslate" translate="no">
                        <option value=""><?= htmlspecialchars(__('All Users')); ?></option>
                        <?php foreach($users as $u): ?>
                            <option translate="no" class="hd-no-translate notranslate" value="<?= htmlspecialchars($u); ?>" <?= $username == $u ? 'selected' : ''; ?>><?= htmlspecialchars(audit_display_raw_code($u)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= htmlspecialchars(__('Action')); ?></label>
                    <select name="action" class="form-select">
                        <option value=""><?= htmlspecialchars(__('All Actions')); ?></option>
                        <?php foreach($actions as $a): ?>
                            <option value="<?= htmlspecialchars($a); ?>" <?= $action == $a ? 'selected' : ''; ?>><?= htmlspecialchars(audit_action_display($a)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= htmlspecialchars(__('Per Page')); ?></label>
                    <select name="per_page" class="form-select">
                        <?php foreach([10,25,50,100] as $n): ?>
                            <option value="<?= $n; ?>" <?= $per_page == $n ? 'selected' : ''; ?>><?= $n; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">🔍 <?= htmlspecialchars(__('Search')); ?></button>
                </div>
                <div class="col-md-8">
                    <label class="form-label"><?= htmlspecialchars(__('Keyword')); ?></label>
                    <input type="text" name="keyword" class="form-control" placeholder="Search user, action or details..." value="<?= htmlspecialchars($keyword); ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <a href="audit_logs.php" class="btn btn-outline-secondary flex-fill"><?= htmlspecialchars(__('Reset')); ?></a>
                    <?php if($canExportAudit): ?><a href="export_audit.php?<?= htmlspecialchars(build_query_keep(['page'=>null])); ?>" class="btn btn-success flex-fill">Export CSV</a><?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card table-card">
    <div class="card-body p-0">
        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
            <div><strong><?= htmlspecialchars(__('Activity List')); ?></strong><div class="text-muted small">Showing <?= number_format(count($logs)); ?> of <?= number_format($total_rows); ?> filtered records</div></div>
            <div class="text-muted small no-print">Page <?= $page; ?> / <?= $total_pages; ?></div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width:170px;"><?= htmlspecialchars(__('Date')); ?></th>
                        <th style="width:160px;"><?= htmlspecialchars(__('User')); ?></th>
                        <th style="width:170px;"><?= htmlspecialchars(__('Action')); ?></th>
                        <th><?= htmlspecialchars(__('Details')); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($logs as $log): ?>
                    <?php $time = !empty($log['created_at']) ? strtotime($log['created_at']) : null; ?>
                    <tr>
                        <td>
                            <div class="date-main"><?= $time ? htmlspecialchars(date('d/m/Y', $time)) : '-'; ?></div>
                            <div class="date-sub"><?= $time ? htmlspecialchars(date('h:i:s A', $time)) : ''; ?></div>
                        </td>
                        <td>
                            <div class="fw-semibold hd-no-translate notranslate" translate="no"><?= htmlspecialchars(audit_display_raw_code($log['username'] ?? '-')); ?></div>
                            <?php if(!empty($log['user_id'])): ?><div class="text-muted small">ID: <?= htmlspecialchars($log['user_id']); ?></div><?php endif; ?>
                        </td>
                        <td><span class="badge <?= audit_badge_class($log['action'] ?? ''); ?> px-3 py-2"><?= htmlspecialchars(audit_action_display($log['action'] ?? '-')); ?></span></td>
                        <td class="details-cell"><?= nl2br(htmlspecialchars(audit_details_display($log['details'] ?? '-'))); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if(count($logs) == 0): ?>
                    <tr><td colspan="4"><div class="empty-state"><?= htmlspecialchars(__('No audit logs found.')); ?></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center p-3 border-top no-print">
            <div class="text-muted small">Showing <?= $total_rows ? number_format($offset + 1) : 0; ?> to <?= number_format(min($offset + $per_page, $total_rows)); ?> of <?= number_format($total_rows); ?> records</div>
            <div class="btn-group">
                <a class="btn btn-outline-secondary <?= $page <= 1 ? 'disabled' : ''; ?>" href="audit_logs.php?<?= htmlspecialchars(build_query_keep(['page'=>$page-1])); ?>">‹ Previous</a>
                <a class="btn btn-soft disabled" href="#"><?= $page; ?></a>
                <a class="btn btn-outline-secondary <?= $page >= $total_pages ? 'disabled' : ''; ?>" href="audit_logs.php?<?= htmlspecialchars(build_query_keep(['page'=>$page+1])); ?>">Next ›</a>
            </div>
        </div>
    </div>
</div>

</div>

<?php require 'footer.php'; ?>
