<?php

require 'header.php';
require 'db.php';
require_once 'access_control.php';
require_once 'module_permissions.php';
require_once 'ticket_master_options.php';
require_once 'ticket_status_options.php';


if(!function_exists('h')){
    function h($value){
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if(!function_exists('hd_ticket_label')){
    function hd_ticket_label($text){ return function_exists('__') ? __($text) : $text; }
}

if(!function_exists('hd_ticket_mobile_pic_label')){
    function hd_ticket_mobile_pic_label(){
        $lang = function_exists('hd_lang') ? hd_lang() : 'en';
        if($lang === 'zh') return '负责人';
        if($lang === 'ms') return 'Pegawai Bertanggungjawab';
        return 'Person In Charge';
    }
}

if(!function_exists('hd_ticket_value')){
    function hd_ticket_value($text){
        $text = (string)$text;
        $map = [
            'Unassigned' => 'Unassigned',
            '未指派' => 'Unassigned',
            'Overdue' => 'Overdue',
            'New Ticket' => 'New Ticket',
            'In Progress' => 'In Progress',
            'Waiting Reply' => 'Waiting Reply',
            'Solved' => 'Solved',
            'Closed' => 'Closed',
            'Cancelled' => 'Cancelled',
            'Low' => 'Low',
            'Medium' => 'Medium',
            'High' => 'High',
            'Urgent' => 'Urgent',
        ];
        $key = $map[$text] ?? $text;
        return function_exists('__') ? __($key) : $text;
    }
}



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

ensure_ticket_last_update_columns($pdo);



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

ticket_status_ensure_ticket_column($pdo);

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$branch = $_GET['branch'] ?? '';
$priority = $_GET['priority'] ?? '';

$sort = $_GET['sort'] ?? 'created_at';
$order = strtolower($_GET['order'] ?? 'desc');

$allowedSortColumns = [
    'ticket_no' => 'ticket_no',
    'title' => 'title',
    'branch' => 'branch',
    'department' => 'department',
    'category' => 'category',
    'priority' => 'priority',
    'status' => 'status',
    'assigned_to' => 'assigned_to',
    'due_date' => 'due_date',
    'created_at' => 'created_at',
    'last_update' => 'last_update',
    'last_updated_by' => 'last_updated_by'
];

if(!isset($allowedSortColumns[$sort]))
{
    $sort = 'created_at';
}

$order = ($order === 'asc') ? 'asc' : 'desc';


$branchMasterList = master_fetch_active_branches($pdo);
$branchFilterList = $branchMasterList;
$currentRoleForBranchFilter = normalize_role($_SESSION['role'] ?? 'staff');
if($currentRoleForBranchFilter === 'staff') {
    // Staff visibility is own Primary Branch OR checked Assigned To.
    // Keep branch filter to own branch only.
    $allowedFilterBranches = [$_SESSION['branch'] ?? ''];
    $branchFilterList = array_values(array_filter($branchMasterList, function($b) use ($allowedFilterBranches){ return in_array($b['branch_code'] ?? '', $allowedFilterBranches, true); }));
}
// Head visibility is Checked PIC OR Assigned To, so branch filter can show all branches;
// access_control.php still protects the actual ticket list query.
$slaMasterList = master_fetch_active_sla($pdo);
$ticketStatusList = ticket_status_fetch_all($pdo, true);
$statusClass = ticket_status_color_map($pdo);
$closedStatusNames = ticket_status_closed_names($pdo);
$closedPlaceholders = ticket_status_sql_in_placeholders($closedStatusNames);
$closedStatusQuoted = count($closedStatusNames) ? implode(',', array_map([$pdo, 'quote'], $closedStatusNames)) : "''";

$sql = "SELECT * FROM tickets WHERE 1=1";
$params = [];

apply_ticket_access_filter($sql, $params);

// Closed tickets are separated into closed_tickets.php.
// Ticket List only shows active/non-closed tickets.
$sql .= " AND status NOT IN ($closedPlaceholders)";
$params = array_merge($params, $closedStatusNames);

if($search != '')
{
    $sql .= " AND (
        ticket_no LIKE ?
        OR title LIKE ?
        OR description LIKE ?
        OR category LIKE ?
        OR department LIKE ?
        OR assigned_to LIKE ?
        OR branch LIKE ?
    )";

    $searchValue = "%".$search."%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if($status == 'overdue')
{
    $sql .= "
        AND due_date IS NOT NULL
        AND due_date < NOW()
        AND status NOT IN ($closedPlaceholders)
    ";
    $params = array_merge($params, $closedStatusNames);
}
elseif($status != '')
{
    $sql .= " AND status = ?";
    $params[] = $status;
}

if($branch != '')
{
    $sql .= " AND branch = ?";
    $params[] = $branch;
}

if($priority != '')
{
    $sql .= " AND priority = ?";
    $params[] = $priority;
}

$sql .= " ORDER BY ".$allowedSortColumns[$sort]." ".strtoupper($order).", id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();


$priorityClass = [
    'Low' => 'bg-secondary',
    'Medium' => 'bg-primary',
    'High' => 'bg-warning text-dark',
    'Urgent' => 'bg-danger',
    'Critical' => 'bg-danger'
];

function ticket_count_by_condition($pdo, $condition)
{
    $countSql = "SELECT COUNT(*) FROM tickets WHERE 1=1";
    $countParams = [];
    apply_ticket_access_filter($countSql, $countParams);
    if($condition !== '')
    {
        $countSql .= " AND " . $condition;
    }
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($countParams);
    return (int)$stmt->fetchColumn();
}

$ticketTabs = [
    'all' => [
        'label' => 'ALL',
        'url' => 'ticket_list.php',
        'count' => ticket_count_by_condition($pdo, "status NOT IN (".$closedStatusQuoted.")"),
        'class' => 'tab-all',
        'active' => ($status === ''),
        'badge_class' => 'bg-dark'
    ],
];

foreach($ticketStatusList as $statusRow)
{
    $statusName = (string)$statusRow['status_name'];
    $isClosedStatus = ((int)($statusRow['is_closed'] ?? 0) === 1);

    if($isClosedStatus)
    {
        continue;
    }

    $ticketTabs['status_'.ticket_status_slug($statusName)] = [
        'label' => strtoupper($statusName),
        'url' => 'ticket_list.php?status='.urlencode($statusName),
        'count' => ticket_count_by_condition($pdo, "status = ".$pdo->quote($statusName)),
        'class' => 'tab-'.ticket_status_slug($statusName),
        'active' => ($status === $statusName),
        'badge_class' => $statusClass[$statusName] ?? 'bg-secondary'
    ];
}

$ticketTabs['closed'] = [
    'label' => 'CLOSED',
    'url' => 'closed_tickets.php',
    'count' => ticket_count_by_condition($pdo, "status IN (".$closedStatusQuoted.")"),
    'class' => 'tab-closed',
    'active' => false,
    'badge_class' => 'bg-secondary'
];


function view_ticket_link($ticketId)
{
    $params = ['id' => (int)$ticketId];

    if(isset($_GET['status']) && trim($_GET['status']) !== '')
    {
        $params['return_status'] = trim($_GET['status']);
    }

    if(isset($_GET['branch']) && trim($_GET['branch']) !== '')
    {
        $params['return_branch'] = trim($_GET['branch']);
    }

    if(isset($_GET['priority']) && trim($_GET['priority']) !== '')
    {
        $params['return_priority'] = trim($_GET['priority']);
    }

    return 'view_ticket.php?' . http_build_query($params);
}

function sort_link($column, $label)
{
    global $sort, $order;

    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = ($sort === $column && $order === 'asc') ? 'desc' : 'asc';

    $icon = '↕';
    if($sort === $column)
    {
        $icon = ($order === 'asc') ? '↑' : '↓';
    }

    $displayLabel = function_exists('hd_ticket_label') ? hd_ticket_label($label) : $label;
    return '<a class="sortable-head" href="ticket_list.php?'.htmlspecialchars(http_build_query($params)).'">'.htmlspecialchars($displayLabel).' <span>'.$icon.'</span></a>';
}

function last_update_value(array $ticket): string
{
    return !empty($ticket['last_update']) ? (string)$ticket['last_update'] : (string)($ticket['updated_at'] ?? $ticket['created_at'] ?? '');
}

function last_updated_by_value(array $ticket): string
{
    return !empty($ticket['last_updated_by']) ? (string)$ticket['last_updated_by'] : '-';
}

function hd_branch_code_no_translate($value): string
{
    $value = trim((string)$value);
    $map = ['电脑'=>'PC','Computer'=>'PC','Komputer'=>'PC'];
    return $map[$value] ?? $value;
}

function hd_ticket_no_display(array $ticket): string
{
    $ticketNo = trim((string)($ticket['ticket_no'] ?? ''));
    $branchCode = hd_branch_code_no_translate($ticket['branch'] ?? '');

    if($ticketNo === '') return '-';

    // Ticket number and branch code are system data, not UI language.
    // Keep branch codes like PC/BS/KB unchanged in every language.
    $ticketNo = preg_replace('/^(电脑|Computer|Komputer)/u', 'PC', $ticketNo);
    $ticketNo = preg_replace('/^(PC)(?=\d{6}-\d+)/u', 'PC-', $ticketNo);

    if($branchCode !== '' && strpos($ticketNo, $branchCode . '-') === 0)
    {
        return $ticketNo;
    }

    if($branchCode !== '' && preg_match('/(\d{6}-\d+)$/u', $ticketNo, $m))
    {
        return $branchCode . '-' . $m[1];
    }

    return $ticketNo;
}



?>


<style>
.ticket-page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:18px}.ticket-title{font-weight:850;color:#0f172a}.ticket-status-tabs{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px}.sortable-head{color:inherit;text-decoration:none;font-weight:900}.sortable-head span{font-size:16px;margin-left:4px}.ticket-status-tab.status-color-tab{color:#fff;border-color:transparent}.ticket-status-tab.status-color-tab .count{background:rgba(255,255,255,.25)!important;color:#fff!important}.ticket-status-tab{display:flex;align-items:center;gap:8px;text-decoration:none;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:12px 16px;font-weight:850;color:#334155;box-shadow:0 8px 20px rgba(15,23,42,.05);transition:.15s ease}.ticket-status-tab:hover{transform:translateY(-2px);box-shadow:0 12px 24px rgba(15,23,42,.08)}.ticket-status-tab.active{background:#0f172a;color:#fff;border-color:#0f172a}.ticket-status-tab .count{border-radius:999px;padding:.25rem .55rem;font-size:12px}.ticket-status-tab.active .count{background:rgba(255,255,255,.20)!important;color:#fff!important}.tab-all .count{background:#e5e7eb;color:#111827}.tab-open .count{background:#fee2e2;color:#b91c1c}.tab-progress .count{background:#fef3c7;color:#92400e}.tab-pending .count{background:#cffafe;color:#155e75}.tab-solved .count{background:#dcfce7;color:#166534}.tab-closed .count{background:#e5e7eb;color:#374151}.ticket-filter-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:18px;box-shadow:0 10px 24px rgba(15,23,42,.05);margin-bottom:18px}.ticket-table-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;box-shadow:0 10px 24px rgba(15,23,42,.05)}.btn{border-radius:10px;font-weight:650}.badge{border-radius:10px;padding:.45em .7em}

@media(max-width:768px){
.ticket-page-head{align-items:stretch;gap:10px}.ticket-page-head .btn{width:100%}
.ticket-status-tabs{overflow-x:auto;flex-wrap:nowrap;padding-bottom:4px}.ticket-status-tab{min-width:145px;justify-content:center}
.ticket-filter-card{padding:12px;border-radius:14px}.ticket-filter-card .row>[class*="col-"]{margin-bottom:8px}
.ticket-table-card{display:none}
.mobile-ticket-cards{display:block!important}
.mobile-ticket-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:14px;margin-bottom:12px;box-shadow:0 8px 20px rgba(15,23,42,.05)}
.mobile-ticket-top{display:flex;justify-content:space-between;gap:8px;align-items:flex-start;margin-bottom:8px}
.mobile-ticket-no{font-weight:950;color:#0f172a}.mobile-ticket-title{font-weight:850;margin:4px 0;color:#334155}
.mobile-ticket-meta{display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;color:#64748b;margin:10px 0}
.mobile-ticket-actions{display:flex;gap:8px}.mobile-ticket-actions .btn{flex:1}
}
@media(min-width:769px){.mobile-ticket-cards{display:none!important}}


/* Mobile Ticket List Fix */
@media(max-width:768px){
    .ticket-page-head{
        display:block!important;
        padding:0 2px;
    }
    .ticket-page-head h2{
        font-size:28px!important;
        line-height:1.18!important;
        margin-bottom:8px!important;
    }
    .ticket-page-head .text-muted{
        font-size:15px!important;
        line-height:1.45!important;
    }
    .ticket-page-head > a.btn{
        margin-top:12px!important;
        min-height:48px!important;
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        border-radius:14px!important;
        font-size:17px!important;
        width:100%!important;
    }
    .ticket-status-tabs{
        display:flex!important;
        flex-wrap:nowrap!important;
        overflow-x:auto!important;
        gap:10px!important;
        padding:4px 2px 12px!important;
        margin:14px -2px!important;
        scroll-snap-type:x proximity;
        -webkit-overflow-scrolling:touch;
    }
    .ticket-status-tabs::-webkit-scrollbar{display:none;}
    .ticket-status-tab{
        flex:0 0 auto!important;
        min-width:132px!important;
        height:58px!important;
        border-radius:16px!important;
        scroll-snap-align:start;
        font-size:16px!important;
    }
    .ticket-filter-card{
        border-radius:18px!important;
        padding:14px!important;
        margin-bottom:14px!important;
    }
    .ticket-filter-card .form-control,
    .ticket-filter-card .form-select{
        min-height:50px!important;
        font-size:16px!important;
        border-radius:13px!important;
    }
    .ticket-filter-card .btn{
        min-height:48px!important;
        border-radius:13px!important;
        font-size:16px!important;
    }
    .ticket-table-card{display:none!important;}
    .mobile-ticket-cards{display:block!important;padding-bottom:88px;}
    .mobile-ticket-card{
        background:#fff;
        border:1px solid #e5e7eb;
        border-radius:18px;
        padding:15px;
        margin-bottom:12px;
        box-shadow:0 10px 24px rgba(15,23,42,.06);
    }
    .mobile-ticket-card.is-overdue{border-color:#fecaca;background:#fff7f7;}
    .mobile-ticket-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;margin-bottom:8px;}
    .mobile-ticket-no{font-weight:950;color:#0f172a;font-size:14px;}
    .mobile-ticket-title{font-weight:900;color:#1e293b;font-size:17px;line-height:1.35;margin-top:4px;}
    .mobile-ticket-badges{display:flex;gap:6px;flex-wrap:wrap;margin:10px 0;}
    .mobile-ticket-meta{display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:13px;color:#64748b;margin:12px 0;}
    .mobile-ticket-meta strong{display:block;color:#334155;font-size:12px;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px;}
    .mobile-ticket-actions{display:flex;gap:8px;margin-top:12px;}
    .mobile-ticket-actions .btn{flex:1;min-height:42px;border-radius:12px;font-weight:800;}
    .mobile-empty-card{background:#fff;border:1px dashed #cbd5e1;border-radius:18px;padding:26px;text-align:center;color:#64748b;}
}
@media(min-width:769px){
    .mobile-ticket-cards{display:none!important;}
}


/* Collapsible Export / Filter Panels */
.ticket-action-row{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin:14px 0 16px;
}
.ticket-toggle-btn{
    border:1px solid #dbe4f0;
    background:#fff;
    color:#0f172a;
    border-radius:14px;
    padding:11px 16px;
    font-weight:900;
    display:inline-flex;
    align-items:center;
    gap:8px;
    box-shadow:0 8px 18px rgba(15,23,42,.05);
    text-decoration:none;
}
.ticket-toggle-btn:hover{
    background:#f8fafc;
    color:#0f172a;
}
.ticket-toggle-btn.export{
    background:#ecfdf5;
    border-color:#bbf7d0;
    color:#047857;
}
.ticket-toggle-btn.filter{
    background:#eff6ff;
    border-color:#bfdbfe;
    color:#1d4ed8;
}
.ticket-collapse-panel{
    display:none;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:18px;
    padding:18px;
    box-shadow:0 10px 24px rgba(15,23,42,.05);
    margin-bottom:18px;
}
.ticket-collapse-panel.show{
    display:block;
}
.ticket-collapse-title{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:14px;
    font-weight:950;
    color:#0f172a;
}
.ticket-collapse-title small{
    color:#64748b;
    font-weight:700;
}
.ticket-filter-card{
    margin-bottom:0!important;
    box-shadow:none!important;
    border:0!important;
    padding:0!important;
    background:transparent!important;
}
@media(max-width:768px){
    .ticket-action-row{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:10px;
        margin:12px 0 14px;
    }
    .ticket-toggle-btn{
        justify-content:center;
        min-height:48px;
        padding:10px 12px;
        font-size:15px;
    }
    .ticket-collapse-panel{
        padding:14px;
        border-radius:18px;
        margin-bottom:14px;
    }
    .ticket-collapse-title{
        font-size:18px;
        margin-bottom:12px;
    }
    .ticket-collapse-panel .row>[class*="col-"]{
        margin-bottom:10px;
    }
    .ticket-collapse-panel .form-control,
    .ticket-collapse-panel .form-select{
        min-height:50px;
        font-size:16px;
        border-radius:13px;
    }
    .ticket-collapse-panel .btn{
        min-height:48px;
        border-radius:13px;
        font-size:16px;
        width:100%;
    }
    .ticket-collapse-panel .d-mobile-buttons{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:10px;
    }
}

.hd-no-translate,.notranslate{unicode-bidi:isolate;}
</style>

<style>
#mobilePullRefresh{display:none}
@media(max-width:768px){
#mobilePullRefresh{
position:fixed;left:50%;top:10px;transform:translateX(-50%) translateY(-80px);
z-index:99999;background:#fff;padding:10px 16px;border-radius:999px;
box-shadow:0 10px 25px rgba(0,0,0,.15);font-weight:700;opacity:0;
transition:.2s;
}
#mobilePullRefresh.show{display:block;opacity:1}
#mobilePullRefresh.ready{color:#16a34a}
#mobilePullRefresh.refreshing{color:#2563eb}
}
</style>


<div class="ticket-page-head">
    <div>
        <h2 class="ticket-title mb-1"><i class="bi bi-list-task me-2 text-primary"></i><?= __('Ticket List') ?></h2>
        <div class="text-muted"><?= __('Active tickets are shown here. Closed tickets are separated into Closed Tickets.') ?></div>
        <?php if(!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show mt-3 mb-0" role="alert">
            <?= htmlspecialchars($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
    </div>
    <?php if(current_user_has_permission('create_ticket')): ?>
    <a href="create_ticket.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> <?= h(__('Create Ticket')) ?></a>
    <?php endif; ?>
</div>

<div class="ticket-status-tabs">
    <?php foreach($ticketTabs as $tab): ?>
    <?php $tabColor = $tab['badge_class'] ?? ''; ?>
    <a href="<?= htmlspecialchars($tab['url']); ?>" class="ticket-status-tab <?= htmlspecialchars($tab['class']); ?> <?= $tabColor ? htmlspecialchars($tabColor).' status-color-tab' : ''; ?> <?= $tab['active'] ? 'active' : ''; ?>">
        <span><?= htmlspecialchars($tab['label']); ?></span>
        <span class="count"><?= (int)$tab['count']; ?></span>
    </a>
    <?php endforeach; ?>
</div>



<div class="ticket-action-row">
    <?php if(has_action_permission('export_ticket')): ?>
    <button type="button" class="ticket-toggle-btn export" data-toggle-panel="exportPanel">
        <i class="bi bi-filetype-csv"></i>
        <?= h(__('Export')) ?>
    </button>
    <?php endif; ?>

    <button type="button" class="ticket-toggle-btn filter" data-toggle-panel="filterPanel">
        <i class="bi bi-funnel"></i>
        <?= h(__('Search / Filter')) ?>
    </button>
</div>

<?php if(has_action_permission('export_ticket')): ?>
<div class="ticket-collapse-panel" id="exportPanel">
    <div class="ticket-collapse-title">
        <span><i class="bi bi-download me-1 text-success"></i> <?= h(__('Export')) ?></span>
        <small><?= h(__('Open filter only when needed')) ?></small>
    </div>

    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <a href="export_tickets.php" class="btn btn-success w-100">
                <i class="bi bi-filetype-csv me-1"></i> <?= h(__('Export CSV')) ?>
            </a>
        </div>
    </div>

    <hr>

    <form method="get" action="export_report.php" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label"><?= h(__('Start Date')) ?></label>
            <input type="date" name="start_date" class="form-control" required>
        </div>

        <div class="col-md-3">
            <label class="form-label"><?= h(__('End Date')) ?></label>
            <input type="date" name="end_date" class="form-control" required>
        </div>

        <div class="col-md-3">
            <label class="form-label d-none d-md-block">&nbsp;</label>
            <button type="submit" class="btn btn-success w-100">
                <i class="bi bi-file-earmark-arrow-down me-1"></i> <?= h(__('Export Report')) ?>
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="ticket-collapse-panel <?= ($search !== '' || $status !== '' || $branch !== '' || $priority !== '') ? 'show' : ''; ?>" id="filterPanel">
    <div class="ticket-collapse-title">
        <span><i class="bi bi-search me-1 text-primary"></i> <?= h(__('Search / Filter')) ?></span>
        <small><?= ($search !== '' || $status !== '' || $branch !== '' || $priority !== '') ? h(__('Active filter')) : h(__('Open filter only when needed')); ?></small>
    </div>

    <form method="get" class="row g-3 align-items-end ticket-filter-card">
        <div class="col-md-3">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="<?= h(__('Search ticket no, title, description, department, assignee')) ?>"
                value="<?= htmlspecialchars($search); ?>">
        </div>

        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value=""><?= h(__('All Status')) ?></option>
                <option value="overdue" <?= $status=='overdue' ? 'selected' : ''; ?>><?= h(__('Overdue')) ?></option>
                <?php foreach($ticketStatusList as $statusOption): ?>
                <option value="<?= htmlspecialchars($statusOption['status_name']); ?>" <?= $status==$statusOption['status_name'] ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($statusOption['status_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="branch" class="form-select">
                <option value=""><?= h(__('All Branches')) ?></option>
                <?php foreach($branchFilterList as $b): ?>
                <option class="hd-no-translate" value="<?= htmlspecialchars($b['branch_code']); ?>" <?= $branch==$b['branch_code'] ? 'selected' : ''; ?>><?= htmlspecialchars($b['branch_code']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="priority" class="form-select">
                <option value=""><?= h(__('All Priorities')) ?></option>
                <?php foreach($slaMasterList as $sla): ?>
                <option value="<?= htmlspecialchars($sla['priority_name']); ?>" <?= $priority==$sla['priority_name'] ? 'selected' : ''; ?>><?= htmlspecialchars($sla['priority_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3 d-mobile-buttons">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search me-1"></i> <?= h(__('Search / Filter')) ?>
            </button>

            <a href="ticket_list.php" class="btn btn-secondary">
                <?= h(__('Reset')) ?>
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('[data-toggle-panel]').forEach(function(btn){
        btn.addEventListener('click', function(){
            var id = btn.getAttribute('data-toggle-panel');
            var panel = document.getElementById(id);
            if(panel){
                panel.classList.toggle('show');
                if(panel.classList.contains('show')){
                    setTimeout(function(){
                        panel.scrollIntoView({behavior:'smooth', block:'nearest'});
                    }, 50);
                }
            }
        });
    });
});
</script>

<div class="ticket-table-card"><div class="table-responsive">
<table class="table table-hover mb-0">

<thead class="table-dark">
<tr>
    <th><?= sort_link('ticket_no', 'Ticket No'); ?></th>
    <th><?= sort_link('title', 'Title'); ?></th>
    <th><?= sort_link('branch', 'Branch'); ?></th>
    <th><?= sort_link('department', hd_ticket_mobile_pic_label()); ?></th>
    <th><?= sort_link('category', 'Category'); ?></th>
    <th><?= sort_link('priority', 'Priority'); ?></th>
    <th><?= sort_link('status', 'Status'); ?></th>
    <th><?= sort_link('assigned_to', 'Assigned To'); ?></th>
    <th><?= sort_link('due_date', 'SLA'); ?></th>
    <th><?= sort_link('created_at', 'Created At'); ?></th>
    <th><?= sort_link('last_update', 'Last Update'); ?></th>
    <th><?= sort_link('last_updated_by', 'Last Updated By'); ?></th>
    <th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach($tickets as $ticket): ?>

<?php

$rowClass = '';

if(
    !empty($ticket['due_date'])
    &&
    !ticket_status_is_closed($pdo, (string)$ticket['status'])
)
{
    $timeLeft = strtotime($ticket['due_date']) - time();

    if($timeLeft < 0)
    {
        $rowClass = 'table-danger';
    }
    elseif($timeLeft <= 4 * 60 * 60)
    {
        $rowClass = 'table-warning';
    }
}

?>

<tr class="<?= $rowClass; ?>">
<td><span class="hd-no-translate notranslate" translate="no"><?= htmlspecialchars(hd_ticket_no_display($ticket)); ?></span></td>
<td><?= htmlspecialchars($ticket['title']); ?></td>

<td>
<span class="badge bg-dark hd-no-translate notranslate" translate="no">
<?= !empty($ticket['branch']) ? htmlspecialchars(hd_branch_code_no_translate($ticket['branch'])) : '-'; ?>
</span>
</td>

<td><?= !empty($ticket['department']) ? htmlspecialchars($ticket['department']) : '-'; ?></td>

<td>
<span class="badge bg-info text-dark">
<?= htmlspecialchars($ticket['category'] ?? 'Other'); ?>
</span>
</td>

<td>
<span class="badge <?= $priorityClass[$ticket['priority']] ?? 'bg-secondary'; ?>">
<?= htmlspecialchars(hd_ticket_value($ticket['priority'] ?? 'Medium')); ?>
</span>
</td>

<td>
<span class="badge <?= $statusClass[$ticket['status']] ?? 'bg-dark'; ?>">
<?= htmlspecialchars($ticket['status']); ?>
</span>
</td>

<td>
<?= !empty($ticket['assigned_to']) ? htmlspecialchars($ticket['assigned_to']) : 'Unassigned'; ?>
</td>

<td>
<?php

if(
    !empty($ticket['due_date'])
    &&
    !ticket_status_is_closed($pdo, (string)$ticket['status'])
    &&
    strtotime($ticket['due_date']) < time()
)
{
    $overdueHours = floor((time() - strtotime($ticket['due_date'])) / 3600);

    if($overdueHours >= 24)
    {
        $overdueDays = floor($overdueHours / 24);
        echo '<span class="badge bg-danger">'.htmlspecialchars(__('Overdue')).' '.$overdueDays.'d</span>';
    }
    else
    {
        echo '<span class="badge bg-danger">'.htmlspecialchars(__('Overdue')).' '.$overdueHours.'h</span>';
    }
}
elseif(!empty($ticket['due_date']) && !ticket_status_is_closed($pdo, (string)$ticket['status']))
{
    $timeLeft = strtotime($ticket['due_date']) - time();

    if($timeLeft <= 4 * 60 * 60)
    {
        echo '<span class="badge bg-warning text-dark">'.htmlspecialchars(__('Due Soon')).'</span>';
    }
    else
    {
        echo '<span class="badge bg-success">'.htmlspecialchars(__('Within SLA')).'</span>';
    }
}
elseif(ticket_status_is_closed($pdo, (string)$ticket['status']))
{
    echo '<span class="badge bg-secondary">'.htmlspecialchars(__('Completed')).'</span>';
}
else
{
    echo '-';
}

?>
</td>

<td><?= htmlspecialchars($ticket['created_at']); ?></td>
<td><?= htmlspecialchars(last_update_value($ticket)); ?></td>
<td><?= htmlspecialchars(last_updated_by_value($ticket)); ?></td>

<td>

<a href="<?= htmlspecialchars(view_ticket_link($ticket['id'])); ?>"
class="btn btn-sm btn-primary">
View
</a>

<?php if(has_action_permission('delete_ticket')): ?>

<a
href="delete_ticket.php?id=<?= $ticket['id']; ?>"
class="btn btn-sm btn-danger"
onclick="return confirm('Delete this ticket? This action cannot be undone.');">

Delete

</a>

<?php endif; ?>

</td>
</tr>

<?php endforeach; ?>

<?php if(count($tickets) == 0): ?>

<tr>
<td colspan="13" class="text-center text-muted">
No tickets found
</td>
</tr>

<?php endif; ?>

</tbody>

</table>
</div></div>


<div class="mobile-ticket-cards">
    <?php foreach($tickets as $ticket): ?>
    <?php
        $mobileIsClosed = ticket_status_is_closed($pdo, (string)($ticket['status'] ?? ''));
        $mobileIsOverdue = (
            !$mobileIsClosed
            && !empty($ticket['due_date'])
            && strtotime($ticket['due_date']) < time()
        );
    ?>
    <div class="mobile-ticket-card <?= $mobileIsOverdue ? 'is-overdue' : ''; ?>">
        <div class="mobile-ticket-top">
            <div>
                <div class="mobile-ticket-no hd-no-translate notranslate" translate="no"><?= htmlspecialchars(hd_ticket_no_display($ticket)); ?></div>
                <div class="mobile-ticket-title"><?= htmlspecialchars($ticket['title'] ?? '-'); ?></div>
            </div>
            <span class="badge <?= $statusClass[$ticket['status']] ?? 'bg-secondary'; ?>">
                <?= htmlspecialchars(hd_ticket_value($ticket['status'] ?? '-')); ?>
            </span>
        </div>

        <div class="mobile-ticket-badges">
            <span class="badge bg-dark hd-no-translate notranslate" translate="no"><?= htmlspecialchars($ticket['branch'] ? hd_branch_code_no_translate($ticket['branch']) : '-'); ?></span>
            <span class="badge bg-info text-dark"><?= htmlspecialchars($ticket['category'] ?? 'Other'); ?></span>
            <span class="badge <?= $priorityClass[$ticket['priority']] ?? 'bg-secondary'; ?>">
                <?= htmlspecialchars(hd_ticket_value($ticket['priority'] ?? 'Medium')); ?>
            </span>
            <?php if($mobileIsOverdue): ?>
                <span class="badge bg-danger"><?= htmlspecialchars(hd_ticket_value('Overdue')); ?></span>
            <?php endif; ?>
        </div>

        <div class="mobile-ticket-meta">
            <div><strong><?= htmlspecialchars(hd_ticket_mobile_pic_label()); ?></strong><span><?= htmlspecialchars($ticket['department'] ?: '-'); ?></span></div>
            <div><strong><?= htmlspecialchars(hd_ticket_label('Assigned To')); ?></strong><span><?= htmlspecialchars(($ticket['assigned_to'] ?? '') !== '' ? $ticket['assigned_to'] : hd_ticket_value('Unassigned')); ?></span></div>
            <div><strong><?= htmlspecialchars(hd_ticket_label('Created At')); ?></strong><span><?= htmlspecialchars($ticket['created_at'] ?? '-'); ?></span></div>
            <div><strong><?= htmlspecialchars(hd_ticket_label('Last Update')); ?></strong><span><?= htmlspecialchars(last_update_value($ticket)); ?></span></div>
        </div>

        <div class="mobile-ticket-actions">
            <a href="<?= htmlspecialchars(view_ticket_link($ticket['id'])); ?>" class="btn btn-primary"><?= htmlspecialchars(hd_ticket_label('View')); ?></a>
            <?php if(has_action_permission('delete_ticket')): ?>
                <a href="delete_ticket.php?id=<?= (int)$ticket['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Delete this ticket?');"><?= htmlspecialchars(hd_ticket_label('Delete')); ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if(count($tickets) == 0): ?>
        <div class="mobile-empty-card">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            <?= htmlspecialchars(hd_ticket_label('No ticket found for this filter.')); ?>
        </div>
    <?php endif; ?>
</div>


<div id="mobilePullRefresh">下拉刷新</div>
<script>
(function(){
if(window.innerWidth>768)return;
let startY=0,pull=0,active=false,refreshing=false;
const box=document.getElementById('mobilePullRefresh');
document.addEventListener('touchstart',e=>{
if(window.scrollY===0&&!refreshing){
startY=e.touches[0].clientY;active=true;
}
},{passive:true});
document.addEventListener('touchmove',e=>{
if(!active)return;
pull=e.touches[0].clientY-startY;
if(pull>0){
box.className='show'+(pull>80?' ready':'');
box.style.transform='translateX(-50%) translateY('+Math.min(pull*0.4,40)+'px)';
box.innerHTML=pull>80?'<?= htmlspecialchars(hd_ticket_label('Release to refresh')); ?>':'<?= htmlspecialchars(hd_ticket_label('Pull down to refresh')); ?>';
}
},{passive:true});
document.addEventListener('touchend',()=>{
if(!active)return;
active=false;
if(pull>80){
refreshing=true;
box.className='show refreshing';
box.innerHTML='<?= htmlspecialchars(hd_ticket_label('Refreshing...')); ?>';
setTimeout(()=>location.reload(),200);
}else{
box.className='';
box.style.transform='translateX(-50%) translateY(-80px)';
}
pull=0;
},{passive:true});
})();
</script>

<?php require 'footer.php'; ?>
