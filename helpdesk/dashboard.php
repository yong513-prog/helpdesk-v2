<?php

require 'header.php';
require 'db.php';
require_once 'announcement_content_translate.php';
require_once 'kb_content_translate.php';
try { hd_ensure_kb_translation_columns($pdo); } catch (Exception $e) {}
if (isset($pdo)) { hd_ensure_announcement_translation_columns($pdo); }
require_once 'access_control.php';
require_once 'module_permissions.php';
require_once 'ticket_master_options.php';
require_once 'ticket_status_options.php';
require_once 'notification_helper.php';


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

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

require_module_permission('dashboard');
ensure_ticket_last_update_columns($pdo);
ticket_status_ensure_ticket_column($pdo);
notification_ensure_schema($pdo);
$myUnreadNotifications = notification_unread_count($pdo, (int)$_SESSION['user_id']);

function ensure_ticket_status_column_is_varchar(PDO $pdo)
{
    try
    {
        $stmt = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'status'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        $type = strtolower((string)($col['Type'] ?? ''));

        if(strpos($type, 'enum(') === 0 || strpos($type, 'varchar') !== 0)
        {
            $pdo->exec("ALTER TABLE tickets MODIFY COLUMN status VARCHAR(100) NOT NULL DEFAULT 'Open'");
        }
    }
    catch(Exception $e) {}
}

ensure_ticket_status_column_is_varchar($pdo);


$whereSql = " WHERE 1=1 ";
$whereParams = [];
apply_ticket_access_filter($whereSql, $whereParams);

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

function fetch_count($pdo, $whereSql, $whereParams, $extraCondition = "")
{
    $sql = "SELECT COUNT(*) FROM tickets ".$whereSql;
    if($extraCondition != "") $sql .= " AND " . $extraCondition;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($whereParams);
    return (int)$stmt->fetchColumn();
}

function pct($value, $total)
{
    if($total <= 0) return 0;
    return round(($value / $total) * 100);
}

function safe_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch(Exception $e) { return false; }
}

function safe_fetch_count(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch(Exception $e) { return 0; }
}

function make_map(array $rows, string $keyField, string $labelField): array
{
    $map = [];
    foreach($rows as $r) {
        $key = (string)($r[$keyField] ?? '');
        if($key !== '') $map[$key] = (string)($r[$labelField] ?? $key);
    }
    return $map;
}

function ticket_group_counts(PDO $pdo, string $whereSql, array $whereParams, string $field): array
{
    $allowed = ['branch','department','category','priority','status'];
    if(!in_array($field, $allowed, true)) return [];

    $stmt = $pdo->prepare("SELECT {$field} AS item_key, COUNT(*) total FROM tickets ".$whereSql." GROUP BY {$field} ORDER BY total DESC");
    $stmt->execute($whereParams);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach($rows as $r) {
        $key = trim((string)($r['item_key'] ?? ''));
        if($key === '') $key = '-';
        $out[$key] = (int)$r['total'];
    }
    return $out;
}

function top_from_master_counts(array $masterRows, string $keyField, string $labelField, array $counts, int $limit = 5): array
{
    $rows = [];
    $seen = [];

    foreach($masterRows as $m) {
        $key = (string)($m[$keyField] ?? '');
        if($key === '') continue;
        $seen[$key] = true;
        $rows[] = [
            'key' => $key,
            'label' => (string)($m[$labelField] ?? $key),
            'total' => (int)($counts[$key] ?? 0)
        ];
    }

    foreach($counts as $key => $total) {
        if(!isset($seen[$key])) {
            $rows[] = ['key'=>$key, 'label'=>$key, 'total'=>(int)$total];
        }
    }

    usort($rows, function($a, $b){
        if($a['total'] === $b['total']) return strcmp($a['label'], $b['label']);
        return $b['total'] <=> $a['total'];
    });

    return array_slice($rows, 0, $limit);
}

function knowledge_where_for_current_user(array &$params): string
{
    $role = function_exists('normalize_role') ? normalize_role($_SESSION['role'] ?? 'staff') : strtolower((string)($_SESSION['role'] ?? 'staff'));
    if($role === 'admin') return " WHERE status='Published' ";

    $branch = trim((string)($_SESSION['branch'] ?? ''));
    if($branch === '') return " WHERE status='Published' AND (branch_scope IS NULL OR branch_scope='' OR UPPER(branch_scope)='ALL') ";

    $params[] = $branch;
    $params[] = "%".$branch."%";
    return " WHERE status='Published'
        AND (
            branch_scope IS NULL
            OR branch_scope=''
            OR UPPER(branch_scope)='ALL'
            OR branch_scope = ?
            OR branch_scope LIKE ?
        ) ";
}

$statusClass = ticket_status_color_map($pdo);
$closedStatusNames = ticket_status_closed_names($pdo);
$closedStatusQuoted = count($closedStatusNames) ? implode(',', array_map([$pdo, 'quote'], $closedStatusNames)) : "''";
$ticketStatusList = ticket_status_fetch_all($pdo, true);
$statusCounts = [];
foreach($ticketStatusList as $statusRow){ $sn = (string)$statusRow['status_name']; $statusCounts[$sn] = fetch_count($pdo, $whereSql, $whereParams, "status=".$pdo->quote($sn)); }

$total = fetch_count($pdo, $whereSql, $whereParams);
$open = array_sum(array_map(function($r) use ($statusCounts){ return ((int)($r['is_closed'] ?? 0) === 0) ? (int)($statusCounts[(string)$r['status_name']] ?? 0) : 0; }, $ticketStatusList));
$progress = 0;
$pending = 0;
$solved = 0;
$closed = fetch_count($pdo, $whereSql, $whereParams, "status IN (".$closedStatusQuoted.")");
$overdue = fetch_count($pdo, $whereSql, $whereParams, "due_date IS NOT NULL AND due_date < NOW() AND status NOT IN (".$closedStatusQuoted.")");

$stmt = $pdo->prepare("SELECT * FROM tickets ".$whereSql." ORDER BY id DESC LIMIT 5");
$stmt->execute($whereParams);
$latestTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM tickets ".$whereSql." AND priority IN ('Urgent','Critical') AND status NOT IN (".$closedStatusQuoted.") ORDER BY id DESC LIMIT 5");
$stmt->execute($whereParams);
$urgentTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$branchMasterList = master_fetch_active_branches($pdo);
$categoryMasterList = master_fetch_active_categories($pdo);
$slaMasterList = master_fetch_active_sla($pdo);

$branchCounts = ticket_group_counts($pdo, $whereSql, $whereParams, 'branch');
$picCounts = ticket_group_counts($pdo, $whereSql, $whereParams, 'department');
$categoryCounts = ticket_group_counts($pdo, $whereSql, $whereParams, 'category');

$topBranches = top_from_master_counts($branchMasterList, 'branch_code', 'branch_name', $branchCounts, 5);
$topDepartments = [];
foreach($picCounts as $key => $count) $topDepartments[] = ['key'=>$key, 'label'=>$key, 'total'=>$count];
usort($topDepartments, function($a,$b){ return $b['total'] <=> $a['total']; });
$topDepartments = array_slice($topDepartments, 0, 5);
$topCategories = top_from_master_counts($categoryMasterList, 'category_name', 'category_name', $categoryCounts, 5);

$assetTotal = $assetActive = $assetRepair = $assetInactive = 0;
if(current_user_has_permission('asset_list') && safe_table_exists($pdo, 'assets')) {
    $assetTotal = safe_fetch_count($pdo, "SELECT COUNT(*) FROM assets");
    $assetActive = safe_fetch_count($pdo, "SELECT COUNT(*) FROM assets WHERE status='Active'");
    $assetRepair = safe_fetch_count($pdo, "SELECT COUNT(*) FROM assets WHERE status='Repair'");
    $assetInactive = safe_fetch_count($pdo, "SELECT COUNT(*) FROM assets WHERE status IN ('Inactive','Disposed')");
}

$kbTotal = $kbGuide = $kbTrouble = 0;
$topKnowledge = [];
if(current_user_has_permission('knowledge_base') && safe_table_exists($pdo, 'knowledge_base')) {
    $kbParams = [];
    $kbWhere = knowledge_where_for_current_user($kbParams);
    $kbTotal = safe_fetch_count($pdo, "SELECT COUNT(*) FROM knowledge_base ".$kbWhere, $kbParams);

    $tmpParams = $kbParams;
    $kbGuide = safe_fetch_count($pdo, "SELECT COUNT(*) FROM knowledge_base ".$kbWhere." AND knowledge_type='Guide'", $tmpParams);

    $tmpParams = $kbParams;
    $kbTrouble = safe_fetch_count($pdo, "SELECT COUNT(*) FROM knowledge_base ".$kbWhere." AND knowledge_type IN ('Troubleshooting','FAQ','SOP')", $tmpParams);

    try {
        $stmt = $pdo->prepare("SELECT * FROM knowledge_base ".$kbWhere." ORDER BY views DESC, id DESC LIMIT 5");
        $stmt->execute($kbParams);
        $topKnowledge = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) { $topKnowledge = []; }
}

$logs = [];
if(current_user_has_permission('audit_logs')) {
    try{
        $logs = $pdo->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
    }catch(Exception $e){ $logs = []; }
}

try{
    $stmt = $pdo->prepare("SELECT a.*, COALESCE(u.username,'-') AS created_by_name FROM announcements a LEFT JOIN users u ON u.id=a.created_by WHERE (a.start_date IS NULL OR a.start_date <= CURDATE()) AND (a.end_date IS NULL OR a.end_date >= CURDATE()) ORDER BY a.id DESC LIMIT 3");
    $stmt->execute();
    $latestAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);
}catch(Exception $e){ $latestAnnouncements = []; }


$priorityClass = [
    'Low' => 'bg-success-subtle text-success',
    'Medium' => 'bg-warning-subtle text-warning',
    'High' => 'bg-danger-subtle text-danger',
    'Urgent' => 'bg-danger text-white',
    'Critical' => 'bg-danger text-white'
];

?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body{background:#f6f9fc}.dash-wrap{max-width:100%;overflow-x:hidden}.top-actions{display:grid;grid-template-columns:1fr 240px 240px 170px;gap:14px;margin-bottom:16px}.welcome-card,.mini-card,.announcement-strip,.stat-card,.dash-card{background:#fff;border:1px solid #e8eef7;border-radius:16px;box-shadow:0 8px 22px rgba(15,23,42,.045)}.welcome-card{padding:18px 20px;display:flex;align-items:center;gap:14px}.welcome-icon,.mini-icon{width:42px;height:42px;border-radius:13px;display:flex;align-items:center;justify-content:center;background:#eef4ff;color:#2563eb;font-size:20px}.welcome-title{font-weight:900;color:#0f172a;margin:0}.mini-card{padding:14px 16px;display:flex;align-items:center;gap:12px}.mini-card strong{font-size:14px}.create-btn{height:100%;display:flex;align-items:center;justify-content:center;border-radius:14px;font-weight:800}.ann-title{font-weight:900;margin:0 0 10px;color:#0f172a;display:flex;align-items:center;gap:8px}.ann-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.ann-item{padding:15px;border:1px solid #edf2f7;border-radius:14px;background:#fff;min-height:105px;display:flex;gap:12px;align-items:flex-start}.ann-icon{width:46px;height:46px;border-radius:14px;background:#eef4ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:22px;flex:0 0 auto}.ann-item:nth-child(2) .ann-icon{background:#f5f3ff;color:#7c3aed}.ann-item:nth-child(3) .ann-icon{background:#ecfdf5;color:#16a34a}.ann-name{font-weight:900;color:#0f172a}.ann-content{font-size:12px;color:#64748b;margin-top:4px;line-height:1.35}.ann-meta{font-size:12px;color:#475569;margin-top:4px}.tabs{background:#fff;border:1px solid #e8eef7;border-radius:16px;margin:14px 0 14px;display:flex;overflow:hidden}.tabs a{flex:1;text-align:center;padding:13px 8px;text-decoration:none;color:#0f172a;font-weight:850;border-right:1px solid #edf2f7}.tabs a:last-child{border-right:0}.tabs a.active{color:#2563eb;box-shadow:inset 0 -3px 0 #2563eb}.tabs .badge{margin-left:6px}.stat-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:14px;margin-bottom:16px}.stat-card{padding:18px;min-height:130px;text-decoration:none;color:#0f172a;display:block}.stat-card:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(15,23,42,.08)}.stat-top{display:flex;align-items:center;gap:14px}.stat-icon{width:52px;height:52px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff}.stat-no{font-size:31px;font-weight:950;line-height:1}.stat-label{font-weight:850;margin-top:8px}.stat-sub{font-size:12px;color:#64748b;margin-top:4px}.bg-blue{background:linear-gradient(135deg,#60a5fa,#2563eb)}.bg-red{background:linear-gradient(135deg,#fb7185,#ef4444)}.bg-orange{background:linear-gradient(135deg,#fbbf24,#f97316)}.bg-cyan{background:linear-gradient(135deg,#22d3ee,#0891b2)}.bg-green{background:linear-gradient(135deg,#4ade80,#16a34a)}.bg-purple{background:linear-gradient(135deg,#a78bfa,#7c3aed)}.bg-darkx{background:linear-gradient(135deg,#475569,#0f172a)}.dash-grid-1{display:grid;grid-template-columns:1.05fr 1fr 1fr;gap:16px;margin-bottom:16px}.dash-grid-2{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px}.dash-grid-3{display:grid;grid-template-columns:1fr;gap:16px}.dash-card{overflow:hidden}.dash-head{padding:14px 16px;border-bottom:1px solid #edf2f7;display:flex;justify-content:space-between;align-items:center;font-weight:900}.dash-body{padding:16px}.table td,.table th{font-size:12px;vertical-align:middle}.table thead th{background:#fbfdff!important;color:#334155}.rank-row{display:flex;align-items:center;gap:10px;margin-bottom:12px}.rank-no{width:26px;height:26px;border-radius:8px;background:#eef4ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:12px}.rank-bar{height:7px;background:#e5e7eb;border-radius:99px;overflow:hidden}.rank-fill{height:100%;background:#2563eb;border-radius:99px}.rank-fill.green{background:#16a34a}.rank-fill.cyan{background:#0891b2}.quick-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.quick-link{text-decoration:none;color:#0f172a;text-align:center;border-radius:14px;padding:13px 8px;background:#f8fafc;border:1px solid #edf2f7;font-weight:800;font-size:12px}.quick-link i{display:block;font-size:24px;margin-bottom:7px}.empty-state{text-align:center;color:#64748b;padding:24px}.footer-note{font-size:12px;color:#64748b;padding:18px 2px 0}.integration-note{font-size:12px;color:#64748b}.mini-stat{display:flex;align-items:center;justify-content:space-between;border:1px solid #edf2f7;background:#f8fafc;border-radius:14px;padding:12px 14px;margin-bottom:10px}.mini-stat strong{font-size:22px;color:#0f172a}.mini-stat span{font-weight:850;color:#334155}.chart-wrap{height:220px;display:flex;align-items:center;justify-content:center}.chart-wrap canvas{max-height:210px!important}.small-badge-new{font-size:10px;background:#dcfce7;color:#15803d;border-radius:99px;padding:3px 8px;margin-left:8px;font-weight:900}@media(max-width:1500px){.stat-grid{grid-template-columns:repeat(4,1fr)}.dash-grid-1,.dash-grid-2,.dash-grid-3{grid-template-columns:1fr 1fr}.top-actions{grid-template-columns:1fr 220px 220px}}@media(max-width:992px){.top-actions,.ann-grid,.stat-grid,.dash-grid-1,.dash-grid-2,.dash-grid-3{grid-template-columns:1fr}.tabs{overflow-x:auto}.tabs a{min-width:135px}.quick-grid{grid-template-columns:repeat(2,1fr)}}

@media(max-width:768px){
.dash-wrap{padding-bottom:86px}.top-actions,.ann-grid,.stat-grid,.dash-grid-1,.dash-grid-2,.dash-grid-3{grid-template-columns:1fr!important;gap:12px}
.welcome-card,.mini-card,.announcement-strip,.stat-card,.dash-card{border-radius:14px}.stat-card{min-height:auto;padding:14px}.stat-icon{width:44px;height:44px}
.stat-no{font-size:26px}.tabs{overflow-x:auto;white-space:nowrap;border-radius:14px}.tabs a{min-width:135px;flex:0 0 auto}
.chart-wrap{height:230px}.table-responsive{font-size:12px}
}


/* Dashboard Mobile Optimization */
.dash-mobile-toggle-row{display:none;}
@media(max-width:768px){
    .dash-wrap{
        padding-bottom:86px!important;
        overflow-x:hidden!important;
    }

    .top-actions{
        display:grid!important;
        grid-template-columns:1fr!important;
        gap:10px!important;
        margin-bottom:12px!important;
    }

    .welcome-card{
        padding:16px!important;
        border-radius:18px!important;
        align-items:flex-start!important;
    }

    .welcome-icon{
        width:46px!important;
        height:46px!important;
        border-radius:15px!important;
        font-size:22px!important;
        flex:0 0 46px!important;
    }

    .welcome-title{
        font-size:20px!important;
        line-height:1.25!important;
    }

    .welcome-card .text-muted{
        font-size:13px!important;
        line-height:1.35!important;
    }

    /* Show date and time cards on mobile, compact style */
    .top-actions .mini-card{
        display:flex!important;
    }

    .top-actions .mini-card{
        padding:12px 14px!important;
        border-radius:18px!important;
        min-height:72px!important;
    }

    .top-actions .mini-card .mini-icon{
        width:42px!important;
        height:42px!important;
        border-radius:14px!important;
        flex:0 0 42px!important;
        font-size:20px!important;
    }

    .top-actions .mini-card strong{
        font-size:16px!important;
        line-height:1.2!important;
    }

    .top-actions .mini-card small{
        font-size:13px!important;
    }

    .create-btn{
        min-height:52px!important;
        border-radius:16px!important;
        font-size:17px!important;
    }

    .announcement-strip{
        padding:15px!important;
        border-radius:18px!important;
        margin-bottom:12px!important;
    }

    .announcement-strip .d-flex{
        align-items:center!important;
    }

    .ann-title{
        font-size:20px!important;
        margin:0!important;
    }

    .announcement-strip .btn{
        min-height:40px!important;
        border-radius:13px!important;
        font-weight:900!important;
        white-space:nowrap!important;
    }

    .ann-grid{
        display:grid!important;
        grid-template-columns:1fr!important;
        gap:10px!important;
    }

    /* Show only newest announcement by default on mobile */
    .ann-grid .ann-item:nth-child(n+2){
        display:none!important;
    }

    .ann-item{
        min-height:auto!important;
        padding:14px!important;
        border-radius:17px!important;
    }

    .ann-icon{
        width:44px!important;
        height:44px!important;
        border-radius:15px!important;
        font-size:21px!important;
    }

    .ann-name{
        font-size:17px!important;
        line-height:1.35!important;
    }

    .ann-content{
        font-size:13px!important;
        line-height:1.45!important;
        display:-webkit-box!important;
        -webkit-line-clamp:3!important;
        -webkit-box-orient:vertical!important;
        overflow:hidden!important;
    }

    .tabs{
        display:flex!important;
        overflow-x:auto!important;
        border-radius:16px!important;
        margin:12px 0!important;
        -webkit-overflow-scrolling:touch!important;
    }

    .tabs::-webkit-scrollbar{display:none;}

    .tabs a{
        flex:0 0 auto!important;
        min-width:150px!important;
        padding:14px 14px!important;
        font-size:16px!important;
        white-space:nowrap!important;
    }

    .stat-grid{
        display:grid!important;
        grid-template-columns:1fr 1fr!important;
        gap:10px!important;
        margin-bottom:12px!important;
    }

    .stat-card{
        min-height:auto!important;
        padding:14px!important;
        border-radius:18px!important;
    }

    .stat-top{
        align-items:flex-start!important;
        gap:10px!important;
    }

    .stat-icon{
        width:42px!important;
        height:42px!important;
        border-radius:14px!important;
        font-size:21px!important;
        flex:0 0 42px!important;
    }

    .stat-no{
        font-size:26px!important;
    }

    .stat-label{
        font-size:14px!important;
        line-height:1.25!important;
        margin-top:5px!important;
    }

    .stat-sub{
        font-size:11px!important;
        line-height:1.3!important;
    }

    .dash-mobile-toggle-row{
        display:grid!important;
        grid-template-columns:1fr 1fr!important;
        gap:10px!important;
        margin:12px 0!important;
    }

    .dash-mobile-toggle-row button,
    .dash-mobile-toggle-row a{
        min-height:46px!important;
        border-radius:14px!important;
        font-weight:900!important;
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
    }

    .dash-grid-1,.dash-grid-2,.dash-grid-3{
        display:grid!important;
        grid-template-columns:1fr!important;
        gap:12px!important;
        margin-bottom:12px!important;
    }

    /* Hide heavy dashboard detail cards first on mobile */
    .dash-detail-area{
        display:none!important;
    }

    .dash-detail-area.mobile-show{
        display:grid!important;
    }

    .dash-card{
        border-radius:18px!important;
    }

    .dash-head{
        padding:14px 15px!important;
        font-size:16px!important;
    }

    .dash-body{
        padding:14px!important;
    }

    .chart-wrap{
        height:220px!important;
    }

    .table-responsive{
        border-radius:14px!important;
        overflow-x:auto!important;
    }

    .table td,.table th{
        font-size:12px!important;
        white-space:nowrap!important;
    }

    .quick-grid{
        grid-template-columns:1fr 1fr!important;
    }

    .quick-link{
        min-height:78px!important;
        border-radius:16px!important;
    }
}


/* Mobile Date / Time Compact Display */
@media(max-width:768px){
    .top-actions{
        grid-template-columns:1fr 1fr!important;
    }

    .top-actions .welcome-card{
        grid-column:1 / -1!important;
    }

    .top-actions .mini-card{
        grid-column:auto!important;
    }

    .top-actions .create-btn{
        grid-column:1 / -1!important;
    }

    .top-actions .mini-card:nth-of-type(2),
    .top-actions .mini-card:nth-of-type(3){
        min-width:0!important;
    }

    .top-actions .mini-card:nth-of-type(2) strong,
    .top-actions .mini-card:nth-of-type(3) strong{
        white-space:nowrap!important;
        overflow:hidden!important;
        text-overflow:ellipsis!important;
        display:block!important;
        max-width:100%!important;
    }
}

</style>

<div class="dash-wrap">
    <div class="top-actions">
        <div class="welcome-card">
            <div class="welcome-icon"><i class="bi bi-house-heart"></i></div>
            <div><h5 class="welcome-title">Welcome back, <?= h($_SESSION['username'] ?? 'User'); ?>! 👋</h5><div class="text-muted small">Here is what is happening with your helpdesk today.</div></div>
        </div>
        <div class="mini-card"><div class="mini-icon"><i class="bi bi-calendar3"></i></div><div><strong id="liveDate"></strong><br><small id="liveDay" class="text-muted"></small></div></div>
        <div class="mini-card"><div class="mini-icon"><i class="bi bi-clock"></i></div><div><strong id="liveTime"></strong><br><small class="text-muted">Malaysia Time</small></div></div>
        <?php if(current_user_has_permission('create_ticket')): ?><a href="create_ticket.php" class="btn btn-primary create-btn"><i class="bi bi-plus-lg me-2"></i>Create Ticket</a><?php else: ?><div class="mini-card"><div class="mini-icon"><i class="bi bi-shield-lock"></i></div><div><strong>Read Only</strong><br><small class="text-muted">No create permission</small></div></div><?php endif; ?>
    </div>

    <div class="announcement-strip p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2"><h6 class="ann-title"><i class="bi bi-megaphone text-primary"></i> <?= __('Announcements') ?></h6><a href="announcements.php" class="btn btn-sm btn-outline-primary"><?= __('View All') ?></a></div>
        <div class="ann-grid">
            <?php if(count($latestAnnouncements)>0): foreach($latestAnnouncements as $idx=>$a): ?>
            <a href="announcements.php" class="ann-item text-decoration-none">
                <div class="ann-icon"><i class="bi <?= $idx==0?'bi-tools':($idx==1?'bi-stars':'bi-shield-check'); ?>"></i></div>
                <div><div class="ann-name"><?= h(hd_announcement_title($pdo, $a)); ?><span class="small-badge-new"><?= __('New') ?></span></div><div class="ann-meta"><?= h($a['created_at'] ?? ''); ?></div><div class="ann-content"><?= h(mb_substr(strip_tags(hd_announcement_content($pdo, $a)),0,110)); ?><?= mb_strlen(strip_tags(hd_announcement_content($pdo, $a)))>110?'...':''; ?></div></div>
            </a>
            <?php endforeach; else: ?>
            <div class="ann-item"><div class="ann-icon"><i class="bi bi-megaphone"></i></div><div><div class="ann-name"><?= __('No active announcement') ?></div><div class="ann-content"><?= __('There are no company notices at the moment.') ?></div></div></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="tabs">
        <a class="active" href="ticket_list.php">ALL <span class="badge bg-dark" data-live-count="total"><?= $total; ?></span></a>
        <?php foreach($ticketStatusList as $sr): ?>
            <?php if((int)$sr['is_closed'] === 1) continue; ?>
            <?php $sn = (string)$sr['status_name']; ?>
            <a href="ticket_list.php?status=<?= urlencode($sn); ?>">
                <?= h(strtoupper($sn)); ?> <span class="badge <?= $statusClass[$sn] ?? 'bg-secondary'; ?>"><?= (int)($statusCounts[$sn] ?? 0); ?></span>
            </a>
        <?php endforeach; ?>
        <a href="closed_tickets.php">CLOSED <span class="badge bg-secondary" data-live-count="closed"><?= $closed; ?></span></a>
    </div>

    <div class="stat-grid">
        <a class="stat-card" href="ticket_list.php"><div class="stat-top"><div class="stat-icon bg-blue"><i class="bi bi-ticket-perforated"></i></div><div><div class="stat-no" data-live-count="total"><?= $total; ?></div><div class="stat-label">Total Tickets</div><div class="stat-sub">All active + closed</div></div></div></a>
        <?php foreach($ticketStatusList as $sr): ?>
            <?php
                $sn = (string)$sr['status_name'];
                $isClosedStatus = ((int)$sr['is_closed'] === 1);
                $cardUrl = $isClosedStatus ? 'closed_tickets.php' : 'ticket_list.php?status='.urlencode($sn);
                $iconClass = $isClosedStatus ? 'bi-archive' : 'bi-record-circle';
                $bgClass = $isClosedStatus ? 'bg-purple' : 'bg-cyan';
            ?>
            <a class="stat-card" href="<?= h($cardUrl); ?>"><div class="stat-top"><div class="stat-icon <?= $bgClass; ?>"><i class="bi <?= $iconClass; ?>"></i></div><div><div class="stat-no"><?= (int)($statusCounts[$sn] ?? 0); ?></div><div class="stat-label"><?= h($sn); ?></div><div class="stat-sub"><?= $isClosedStatus ? 'Closed / archived' : 'Active status'; ?></div></div></div></a>
        <?php endforeach; ?>
        <a class="stat-card" href="ticket_list.php?status=overdue"><div class="stat-top"><div class="stat-icon bg-darkx"><i class="bi bi-alarm"></i></div><div><div class="stat-no" data-live-count="overdue"><?= $overdue; ?></div><div class="stat-label">Overdue</div><div class="stat-sub">Past due date</div></div></div></a>
    </div>


<div class="dash-mobile-toggle-row">
    <button type="button" class="btn btn-outline-primary" id="showDashboardDetails">
        <i class="bi bi-bar-chart me-1"></i> Show Details
    </button>
    <a href="ticket_list.php" class="btn btn-outline-secondary">
        <i class="bi bi-list-task me-1"></i> Ticket List
    </a>
</div>

    <div class="dash-grid-1 dash-detail-area" id="dashboardDetailArea">
        <div class="dash-card"><div class="dash-head"><span>Ticket Status Overview</span></div><div class="dash-body chart-wrap"><canvas id="ticketChart"></canvas></div></div>
        <div class="dash-card"><div class="dash-head"><span>Latest 5 Tickets</span><a href="ticket_list.php" class="btn btn-sm btn-outline-primary">View All</a></div><div class="dash-body p-0"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Ticket No</th><th>Title</th><th>Branch</th><th>Priority</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($latestTickets as $t): ?><tr><td><a href="view_ticket.php?id=<?= (int)$t['id']; ?>" class="fw-bold text-decoration-none"><?= h(function_exists('hd_ticket_no_raw') ? hd_ticket_no_raw($t['ticket_no']) : $t['ticket_no']); ?></a></td><td><?= h($t['title']); ?></td><td class="hd-no-translate notranslate" translate="no"><?= h(function_exists('hd_branch_code_raw') ? hd_branch_code_raw($t['branch'] ?? '-') : ($t['branch'] ?? '-')); ?></td><td><span class="badge <?= $priorityClass[$t['priority']] ?? 'bg-secondary-subtle text-secondary'; ?>"><?= h($t['priority'] ?? 'Medium'); ?></span></td><td><span class="badge <?= $statusClass[$t['status']] ?? 'bg-secondary-subtle text-secondary'; ?>"><?= h($t['status']); ?></span></td><td><a class="btn btn-sm btn-outline-primary" href="view_ticket.php?id=<?= (int)$t['id']; ?>"><i class="bi bi-three-dots"></i></a></td></tr><?php endforeach; if(count($latestTickets)==0): ?><tr><td colspan="6" class="text-center text-muted">No tickets found</td></tr><?php endif; ?></tbody></table></div></div></div>
        <div class="dash-card"><div class="dash-head"><span>Urgent Tickets</span><a href="ticket_list.php?priority=Urgent" class="btn btn-sm btn-outline-primary">View All</a></div><div class="dash-body p-0"><div class="table-responsive"><table class="table table-hover mb-0"><tbody><?php foreach($urgentTickets as $t): ?><tr><td><a href="view_ticket.php?id=<?= (int)$t['id']; ?>" class="fw-bold text-decoration-none"><?= h(function_exists('hd_ticket_no_raw') ? hd_ticket_no_raw($t['ticket_no']) : $t['ticket_no']); ?></a></td><td><?= h($t['title']); ?></td><td class="hd-no-translate notranslate" translate="no"><?= h(function_exists('hd_branch_code_raw') ? hd_branch_code_raw($t['branch']) : $t['branch']); ?></td><td><?= h($t['created_at']); ?></td><td><span class="badge bg-danger-subtle text-danger">Urgent</span></td></tr><?php endforeach; if(count($urgentTickets)==0): ?><tr><td><div class="empty-state"><i class="bi bi-check-circle-fill text-success fs-2"></i><div>No urgent tickets</div><small>Great! All clear for now.</small></div></td></tr><?php endif; ?></tbody></table></div></div></div>
    </div>

    <div class="dash-grid-2 dash-detail-area">
        <div class="dash-card"><div class="dash-head"><span><?= h(__('Top Branch')) ?></span></div><div class="dash-body"><?php foreach($topBranches as $i=>$r): $p=pct((int)$r['total'],$total); ?><div class="rank-row"><div class="rank-no"><?= $i+1; ?></div><div class="flex-grow-1"><div class="d-flex justify-content-between"><strong><span class="hd-no-translate notranslate" translate="no"><?= h(function_exists('hd_branch_code_raw') ? hd_branch_code_raw($r['key'] ?? '-') : ($r['key'] ?? '-')); ?></span><?= (($r['label'] ?? '') && ($r['label'] ?? '') !== ($r['key'] ?? '') ? ' - ' . h($r['label'] ?? '') : ''); ?></strong><span><?= (int)$r['total']; ?></span></div><div class="rank-bar"><div class="rank-fill" style="width:<?= $p; ?>%"></div></div></div></div><?php endforeach; ?></div></div>
        <div class="dash-card"><div class="dash-head"><span><?= h(__('Top Person In Charge')) ?></span></div><div class="dash-body"><?php foreach($topDepartments as $i=>$r): $p=pct((int)$r['total'],$total); ?><div class="rank-row"><div class="rank-no"><?= $i+1; ?></div><div class="flex-grow-1"><div class="d-flex justify-content-between"><strong><?= h($r['label'] ?? $r['key'] ?? '-'); ?></strong><span><?= (int)$r['total']; ?></span></div><div class="rank-bar"><div class="rank-fill green" style="width:<?= $p; ?>%"></div></div></div></div><?php endforeach; ?></div></div>
        <div class="dash-card"><div class="dash-head"><span><?= h(__('Top Categories')) ?></span></div><div class="dash-body"><?php foreach($topCategories as $i=>$r): $p=pct((int)$r['total'],$total); ?><div class="rank-row"><div class="rank-no"><?= $i+1; ?></div><div class="flex-grow-1"><div class="d-flex justify-content-between"><strong><?= h($r['label'] ?? $r['key'] ?? '-'); ?></strong><span><?= (int)$r['total']; ?></span></div><div class="rank-bar"><div class="rank-fill cyan" style="width:<?= $p; ?>%"></div></div></div></div><?php endforeach; ?></div></div>
        
    </div>

    <div class="dash-grid-2 dash-detail-area">
        <?php if(current_user_has_permission('asset_list')): ?>
        <div class="dash-card">
            <div class="dash-head"><span>Asset Overview</span><a href="asset_list.php" class="btn btn-sm btn-outline-primary">Asset List</a></div>
            <div class="dash-body">
                <div class="mini-stat"><span>Total Assets</span><strong><?= $assetTotal; ?></strong></div>
                <div class="mini-stat"><span>Active</span><strong><?= $assetActive; ?></strong></div>
                <div class="mini-stat"><span>Repair</span><strong><?= $assetRepair; ?></strong></div>
                <div class="mini-stat"><span>Inactive / Disposed</span><strong><?= $assetInactive; ?></strong></div>
                <div class="integration-note">Linked with Asset Management and Asset Type Management.</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if(current_user_has_permission('knowledge_base')): ?>
        <div class="dash-card">
            <div class="dash-head"><span>Knowledge Base</span><a href="knowledge_base.php" class="btn btn-sm btn-outline-primary">Open KB</a></div>
            <div class="dash-body">
                <div class="mini-stat"><span>Published Articles</span><strong><?= $kbTotal; ?></strong></div>
                <div class="mini-stat"><span>Guide</span><strong><?= $kbGuide; ?></strong></div>
                <div class="mini-stat"><span>Troubleshooting / FAQ / SOP</span><strong><?= $kbTrouble; ?></strong></div>
                <div class="integration-note">Linked with Category Management and Branch Scope.</div>
            </div>
        </div>
        <div class="dash-card">
            <div class="dash-head"><span><?= h(__('Top Knowledge Articles')) ?></span></div>
            <div class="dash-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Title</th><th>Category</th><th>Type</th><th>Views</th></tr></thead>
                        <tbody>
                        <?php foreach($topKnowledge as $k): ?>
                            <tr>
                                <td><a href="view_article.php?id=<?= (int)$k['id']; ?>" class="fw-bold text-decoration-none"><?= h(hd_kb_title($pdo, $k)); ?></a></td>
                                <td><?= h($k['category'] ?? '-'); ?></td>
                                <td><?= h($k['knowledge_type'] ?? '-'); ?></td>
                                <td><?= (int)($k['views'] ?? 0); ?></td>
                            </tr>
                        <?php endforeach; if(count($topKnowledge)==0): ?>
                            <tr><td colspan="4" class="text-center text-muted">No knowledge articles found</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if(current_user_has_permission('audit_logs')): ?>
    <div class="dash-grid-3 dash-detail-area">
        <div class="dash-card"><div class="dash-head"><span>Recent Audit Logs</span><a href="audit_logs.php" class="btn btn-sm btn-outline-primary">View All</a></div><div class="dash-body p-0"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Date</th><th>User</th><th>Action</th><th>Details</th></tr></thead><tbody><?php foreach($logs as $log): ?><tr><td><?= h($log['created_at']); ?></td><td><?= h($log['username']); ?></td><td><?= h($log['action']); ?></td><td><?= h($log['details'] ?? ''); ?></td></tr><?php endforeach; if(count($logs)==0): ?><tr><td colspan="4" class="text-center text-muted">No audit logs found</td></tr><?php endif; ?></tbody></table></div></div></div>
    </div>
    <?php endif; ?>
</div>

<script>
function updateDateTime(){const now=new Date();document.getElementById('liveDate').textContent=now.toLocaleDateString('en-GB',{day:'2-digit',month:'long',year:'numeric'});document.getElementById('liveDay').textContent=now.toLocaleDateString('en-GB',{weekday:'long'});document.getElementById('liveTime').textContent=now.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});} updateDateTime(); setInterval(updateDateTime,1000);
new Chart(document.getElementById('ticketChart'),{type:'doughnut',data:{labels:<?= json_encode(array_map('hd_t', array_merge(array_keys($statusCounts), ['Overdue']))); ?>,datasets:[{data:<?= json_encode(array_merge(array_values($statusCounts), [$overdue])); ?>,backgroundColor:['#ef4444','#f59e0b','#06b6d4','#22c55e','#6366f1'],borderWidth:0}]},options:{cutout:'68%',responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right'}}}});
</script>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('showDashboardDetails');
    if(!btn){ return; }

    btn.addEventListener('click', function(){
        var areas = document.querySelectorAll('.dash-detail-area');
        var shouldShow = false;

        areas.forEach(function(area){
            if(!area.classList.contains('mobile-show')){
                shouldShow = true;
            }
        });

        areas.forEach(function(area){
            area.classList.toggle('mobile-show', shouldShow);
        });

        btn.innerHTML = shouldShow
            ? '<i class="bi bi-eye-slash me-1"></i> Hide Details'
            : '<i class="bi bi-bar-chart me-1"></i> Show Details';

        if(shouldShow && areas.length){
            setTimeout(function(){
                areas[0].scrollIntoView({behavior:'smooth', block:'start'});
            }, 80);
        }
    });
});
</script>

<?php require 'footer.php'; ?>
