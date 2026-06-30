<?php
require 'header.php';
require 'db.php';
require_once 'access_control.php';
require_once 'module_permissions.php';
require_once 'ticket_master_options.php';
require_once 'ticket_status_options.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

ticket_status_ensure_ticket_column($pdo);

if(!function_exists('ensure_ticket_last_update_columns'))
{
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
}
ensure_ticket_last_update_columns($pdo);

$search = trim($_GET['search'] ?? '');
$branch = trim($_GET['branch'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$status = trim($_GET['status'] ?? '');
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
$slaMasterList = master_fetch_active_sla($pdo);
$statusClass = ticket_status_color_map($pdo);
$closedStatusRows = array_values(array_filter(ticket_status_fetch_all($pdo, false), function($row){
    return (int)($row['is_closed'] ?? 0) === 1;
}));
$closedStatusNames = ticket_status_closed_names($pdo);
$closedPlaceholders = ticket_status_sql_in_placeholders($closedStatusNames);
$closedStatusQuoted = count($closedStatusNames) ? implode(',', array_map([$pdo, 'quote'], $closedStatusNames)) : "''";


if(!function_exists('hd_ticket_label')){ function hd_ticket_label($text){ return function_exists('__') ? __($text) : $text; } }
if(!function_exists('hd_ticket_value')){ function hd_ticket_value($text){ return function_exists('__') ? __((string)$text) : (string)$text; } }

if(!function_exists('closed_lang_code'))
{
    function closed_lang_code()
    {
        $lang = $_SESSION['helpdesk_lang'] ?? $_COOKIE['helpdesk_lang'] ?? $_SESSION['lang'] ?? $_SESSION['language'] ?? $_GET['lang'] ?? $_COOKIE['lang'] ?? 'en';
        $lang = strtolower((string)$lang);
        if($lang === 'bm') $lang = 'ms';
        if(!in_array($lang, ['en','ms','zh'], true)) $lang = 'en';
        return $lang;
    }
}

if(!function_exists('closed_t'))
{
    function closed_t($key)
    {
        static $dict = [
            'en' => [
                'total_tickets' => 'Total %d Tickets',
                'sort_created_at' => 'Created Time',
                'sort_ticket_no' => 'Ticket No',
                'sort_title' => 'Title',
                'sort_branch' => 'Branch',
                'sort_department' => 'PIC',
                'sort_category' => 'Category',
                'sort_priority' => 'Priority',
                'sort_status' => 'Status',
                'sort_assigned_to' => 'Assigned To',
                'sort_last_update' => 'Last Update',
                'sort_last_updated_by' => 'Last Updated By',
                'no_closed_tickets_found' => 'No closed tickets found.'
            ],
            'ms' => [
                'total_tickets' => 'Jumlah %d Tiket',
                'sort_created_at' => 'Masa Dicipta',
                'sort_ticket_no' => 'No. Tiket',
                'sort_title' => 'Tajuk',
                'sort_branch' => 'Cawangan',
                'sort_department' => 'PIC',
                'sort_category' => 'Kategori',
                'sort_priority' => 'Keutamaan',
                'sort_status' => 'Status',
                'sort_assigned_to' => 'Ditugaskan Kepada',
                'sort_last_update' => 'Kemaskini Terakhir',
                'sort_last_updated_by' => 'Dikemaskini Oleh',
                'no_closed_tickets_found' => 'Tiada tiket ditutup.'
            ],
            'zh' => [
                'total_tickets' => '共 %d 个工单',
                'sort_created_at' => '创建时间',
                'sort_ticket_no' => '工单号',
                'sort_title' => '标题',
                'sort_branch' => '分行',
                'sort_department' => '负责人',
                'sort_category' => '分类',
                'sort_priority' => '优先级',
                'sort_status' => '状态',
                'sort_assigned_to' => '指派给',
                'sort_last_update' => '最后更新',
                'sort_last_updated_by' => '最后更新者',
                'no_closed_tickets_found' => '没有已关闭工单。'
            ]
        ];
        $lang = closed_lang_code();
        return $dict[$lang][$key] ?? $dict['en'][$key] ?? $key;
    }
}

if(!function_exists('closed_sort_label'))
{
    function closed_sort_label($column)
    {
        return closed_t('sort_'.(string)$column);
    }
}

function h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function closed_count_by_condition(PDO $pdo, string $condition = ''): int
{
    global $closedStatusQuoted;

    $sql = "SELECT COUNT(*) FROM tickets WHERE 1=1";
    $params = [];

    apply_ticket_access_filter($sql, $params);

    $sql .= " AND status IN (".$closedStatusQuoted.")";

    if($condition !== '')
    {
        $sql .= " AND ".$condition;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
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

    return '<a class="sortable-head" href="closed_tickets.php?'.h(http_build_query($params)).'">'.h($label).' <span>'.$icon.'</span></a>';
}

function last_update_value(array $ticket): string
{
    return !empty($ticket['last_update']) ? (string)$ticket['last_update'] : (string)($ticket['updated_at'] ?? $ticket['created_at'] ?? '');
}

function last_updated_by_value(array $ticket): string
{
    return !empty($ticket['last_updated_by']) ? (string)$ticket['last_updated_by'] : '-';
}

$closedTabs = [
    [
        'label' => 'ALL CLOSED',
        'url' => 'closed_tickets.php',
        'count' => closed_count_by_condition($pdo, ''),
        'badge_class' => 'bg-dark',
        'active' => ($status === '')
    ]
];

foreach($closedStatusRows as $statusRow)
{
    $statusName = (string)$statusRow['status_name'];
    $closedTabs[] = [
        'label' => strtoupper($statusName),
        'url' => 'closed_tickets.php?status='.urlencode($statusName),
        'count' => closed_count_by_condition($pdo, "status = ".$pdo->quote($statusName)),
        'badge_class' => $statusClass[$statusName] ?? 'bg-secondary',
        'active' => ($status === $statusName)
    ];
}

$sql = "SELECT * FROM tickets WHERE 1=1";
$params = [];

apply_ticket_access_filter($sql, $params);

if($status !== '')
{
    if(!in_array($status, $closedStatusNames, true))
    {
        $status = '';
        $sql .= " AND status IN ($closedPlaceholders)";
        $params = array_merge($params, $closedStatusNames);
    }
    else
    {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
}
else
{
    $sql .= " AND status IN ($closedPlaceholders)";
    $params = array_merge($params, $closedStatusNames);
}

if($search !== '')
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
    for($i=0;$i<7;$i++) $params[] = $searchValue;
}

if($branch !== '')
{
    $sql .= " AND branch = ?";
    $params[] = $branch;
}

if($priority !== '')
{
    $sql .= " AND priority = ?";
    $params[] = $priority;
}

$sql .= " ORDER BY ".$allowedSortColumns[$sort]." ".strtoupper($order).", id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$priorityClass = [
    'Low' => 'bg-secondary',
    'Medium' => 'bg-primary',
    'High' => 'bg-warning text-dark',
    'Urgent' => 'bg-danger',
    'Critical' => 'bg-danger'
];

?>

<style>
.closed-page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:18px}.closed-title{font-weight:850;color:#0f172a}.closed-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 10px 24px rgba(15,23,42,.06);overflow:hidden}.closed-toolbar{padding:18px;border-bottom:1px solid #eef2f7;background:#fbfdff}.closed-table th{font-size:13px;color:#334155;background:#f8fafc!important;white-space:nowrap}.closed-table td{vertical-align:middle;white-space:nowrap}.btn{border-radius:10px;font-weight:650}.badge{border-radius:10px;padding:.45em .7em}.empty-state{text-align:center;color:#64748b;padding:36px}.closed-info{background:#ecfdf5;border:1px solid #bbf7d0;color:#166534;border-radius:14px;padding:12px 14px;font-size:14px;margin-bottom:14px}.closed-status-tabs{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px}.closed-status-tab{display:flex;align-items:center;gap:8px;text-decoration:none;border:1px solid transparent;border-radius:14px;padding:12px 16px;font-weight:850;color:#fff;box-shadow:0 8px 20px rgba(15,23,42,.05);transition:.15s ease}.closed-status-tab:hover{transform:translateY(-2px);box-shadow:0 12px 24px rgba(15,23,42,.08);color:#fff}.closed-status-tab.active{outline:3px solid rgba(37,99,235,.25)}.closed-status-tab .count{background:rgba(255,255,255,.25)!important;color:#fff!important;border-radius:999px;padding:.25rem .55rem;font-size:12px}.sortable-head{color:inherit;text-decoration:none;font-weight:900}.sortable-head span{font-size:16px;margin-left:4px}

/* Closed Tickets Mobile + Collapsible Panels */
.closed-action-row{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin:14px 0 16px;
}
.closed-toggle-btn{
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
}
.closed-toggle-btn.export{
    background:#ecfdf5;
    border-color:#bbf7d0;
    color:#047857;
}
.closed-toggle-btn.filter{
    background:#eff6ff;
    border-color:#bfdbfe;
    color:#1d4ed8;
}
.closed-collapse-panel{
    display:none;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:18px;
    padding:18px;
    box-shadow:0 10px 24px rgba(15,23,42,.05);
    margin-bottom:18px;
}
.closed-collapse-panel.show{display:block;}
.closed-collapse-title{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:14px;
    font-weight:950;
    color:#0f172a;
}
.closed-collapse-title small{
    color:#64748b;
    font-weight:700;
}
.closed-card .closed-toolbar{
    margin:0!important;
    padding:0!important;
    border:0!important;
    background:transparent!important;
}
.closed-mobile-cards{display:none;}
@media(max-width:768px){
    .closed-page-head{
        display:block!important;
        padding:0 2px;
    }
    .closed-page-head h2{
        font-size:28px!important;
        line-height:1.18!important;
        margin-bottom:8px!important;
    }
    .closed-page-head .text-muted{
        font-size:15px!important;
        line-height:1.45!important;
    }
    .closed-page-head .d-flex{
        display:grid!important;
        grid-template-columns:1fr;
        gap:10px!important;
        margin-top:12px;
    }
    .closed-page-head .btn{
        width:100%!important;
        min-height:48px!important;
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        border-radius:14px!important;
        font-size:16px!important;
    }
    .closed-status-tabs{
        display:flex!important;
        flex-wrap:nowrap!important;
        overflow-x:auto!important;
        gap:10px!important;
        padding:4px 2px 12px!important;
        margin:14px -2px!important;
        -webkit-overflow-scrolling:touch;
    }
    .closed-status-tabs::-webkit-scrollbar{display:none;}
    .closed-status-tab{
        flex:0 0 auto!important;
        min-width:132px!important;
        height:58px!important;
        border-radius:16px!important;
        justify-content:center!important;
        font-size:16px!important;
    }
    .closed-info{
        border-radius:18px!important;
        padding:14px!important;
        font-size:15px!important;
        line-height:1.45!important;
    }
    .closed-action-row{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:10px;
        margin:12px 0 14px;
    }
    .closed-toggle-btn{
        justify-content:center;
        min-height:48px;
        padding:10px 12px;
        font-size:15px;
    }
    .closed-collapse-panel{
        padding:14px;
        border-radius:18px;
        margin-bottom:14px;
    }
    .closed-collapse-title{
        font-size:18px;
        margin-bottom:12px;
    }
    .closed-collapse-panel .row>[class*="col-"]{
        margin-bottom:10px;
    }
    .closed-collapse-panel .form-control,
    .closed-collapse-panel .form-select{
        min-height:50px;
        font-size:16px;
        border-radius:13px;
    }
    .closed-collapse-panel .btn{
        min-height:48px;
        border-radius:13px;
        font-size:16px;
        width:100%;
    }
    .closed-card{display:none!important;}
    .closed-mobile-cards{display:block!important;padding-bottom:88px;}
    .closed-mobile-summary{
        display:flex;
        justify-content:space-between;
        align-items:center;
        color:#64748b;
        font-size:15px;
        margin:16px 2px 10px;
    }
    .closed-mobile-card{
        background:#fff;
        border:1px solid #e5e7eb;
        border-left:5px solid #22c55e;
        border-radius:18px;
        padding:15px;
        margin-bottom:12px;
        box-shadow:0 10px 24px rgba(15,23,42,.06);
    }
    .closed-mobile-top{
        display:flex;
        justify-content:space-between;
        gap:10px;
        align-items:flex-start;
        margin-bottom:8px;
    }
    .closed-mobile-no{
        font-weight:950;
        color:#0f172a;
        font-size:16px;
    }
    .closed-mobile-title{
        font-weight:800;
        color:#1e293b;
        font-size:16px;
        line-height:1.35;
        margin-top:5px;
    }
    .closed-mobile-badges{
        display:flex;
        gap:6px;
        flex-wrap:wrap;
        margin:10px 0;
    }
    .closed-mobile-meta{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:10px;
        font-size:13px;
        color:#64748b;
        margin:12px 0;
    }
    .closed-mobile-meta strong{
        display:block;
        color:#334155;
        font-size:12px;
        text-transform:uppercase;
        letter-spacing:.04em;
        margin-bottom:2px;
    }
    .closed-mobile-actions{
        display:flex;
        gap:8px;
        margin-top:12px;
    }
    .closed-mobile-actions .btn{
        flex:1;
        min-height:42px;
        border-radius:12px;
        font-weight:800;
    }
    .closed-mobile-empty{
        background:#fff;
        border:1px dashed #cbd5e1;
        border-radius:18px;
        padding:26px;
        text-align:center;
        color:#64748b;
    }
}

</style>

<div class="closed-page-head">
    <div>
        <h2 class="closed-title mb-1"><i class="bi bi-archive me-2 text-success"></i><?= __('Closed Tickets') ?></h2>
        <div class="text-muted"><?= __('Tickets marked as closed in Ticket Status Management are separated here and will not appear in Ticket List or Overdue.') ?></div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="ticket_list.php" class="btn btn-outline-primary"><i class="bi bi-list-task me-1"></i> <?= __('Active Ticket List') ?></a>
        <a href="create_ticket.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> <?= __('Create Ticket') ?></a>
    </div>
</div>

<div class="closed-status-tabs">
    <?php foreach($closedTabs as $tab): ?>
    <a href="<?= h($tab['url']); ?>" class="closed-status-tab <?= h($tab['badge_class']); ?> <?= $tab['active'] ? 'active' : ''; ?>">
        <span><?= h($tab['label']); ?></span>
        <span class="count"><?= (int)$tab['count']; ?></span>
    </a>
    <?php endforeach; ?>
</div>

<div class="closed-info">
    <i class="bi bi-info-circle-fill me-1"></i>
    <?= __('Closed-status tickets are archived in this page only. They are excluded from normal Ticket List and Overdue counts.') ?>
</div>


<div class="closed-action-row">
    <button type="button" class="closed-toggle-btn export" data-toggle-panel="closedExportPanel">
        <i class="bi bi-filetype-csv"></i>
        Export
    </button>

    <button type="button" class="closed-toggle-btn filter" data-toggle-panel="closedFilterPanel">
        <i class="bi bi-funnel"></i>
        Search / Filter
    </button>
</div>

<div class="closed-collapse-panel" id="closedExportPanel">
    <div class="closed-collapse-title">
        <span><i class="bi bi-download me-1 text-success"></i> Export</span>
        <small>Open only when needed</small>
    </div>

    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <a href="export_tickets.php?closed=1" class="btn btn-success w-100">
                <i class="bi bi-filetype-csv me-1"></i> Export Closed CSV
            </a>
        </div>
    </div>
</div>

<div class="closed-collapse-panel <?= ($search !== '' || $branch !== '' || $priority !== '') ? 'show' : ''; ?>" id="closedFilterPanel">
    <div class="closed-collapse-title">
        <span><i class="bi bi-search me-1 text-primary"></i> Search / Filter</span>
        <small><?= ($search !== '' || $branch !== '' || $priority !== '') ? 'Active filter' : 'Open only when needed'; ?></small>
    </div>

    <form method="get" class="closed-toolbar">
        <?php if($status !== ''): ?><input type="hidden" name="status" value="<?= h($status); ?>"><?php endif; ?>
        <div class="row g-3 align-items-end">
            <div class="col-lg-5">
                <label class="form-label fw-semibold">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search ticket no, title, description, PIC, assignee..." value="<?= h($search); ?>">
            </div>
            <div class="col-lg-2">
                <label class="form-label fw-semibold">Branch</label>
                <select name="branch" class="form-select">
                    <option value="">All Branches</option>
                    <?php foreach($branchMasterList as $b): ?>
                    <option class="hd-no-translate" value="<?= h($b['branch_code']); ?>" <?= $branch===$b['branch_code']?'selected':''; ?>><?= h($b['branch_code']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label fw-semibold">Priority</label>
                <select name="priority" class="form-select">
                    <option value="">All Priorities</option>
                    <?php foreach($slaMasterList as $sla): ?>
                    <option value="<?= h($sla['priority_name']); ?>" <?= $priority===$sla['priority_name']?'selected':''; ?>><?= h($sla['priority_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i> Search</button>
                <a href="closed_tickets.php" class="btn btn-outline-secondary">Reset</a>
            </div>
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

<div class="closed-card">
    <div class="table-responsive">
        <table class="table table-hover closed-table mb-0">
            <thead>
                <tr>
                    <th style="width:60px;">No.</th>
                    <th><?= sort_link('ticket_no', 'Ticket No'); ?></th>
                    <th><?= sort_link('title', 'Title'); ?></th>
                    <th><?= sort_link('branch', 'Branch'); ?></th>
                    <th><?= sort_link('department', 'PIC'); ?></th>
                    <th><?= sort_link('category', 'Category'); ?></th>
                    <th><?= sort_link('priority', 'Priority'); ?></th>
                    <th><?= sort_link('status', 'Status'); ?></th>
                    <th><?= sort_link('assigned_to', 'Assigned To'); ?></th>
                    <th><?= sort_link('created_at', 'Created At'); ?></th>
                    <th><?= sort_link('last_update', 'Last Update'); ?></th>
                    <th><?= sort_link('last_updated_by', 'Last Updated By'); ?></th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $no=1; foreach($tickets as $ticket): ?>
                <tr>
                    <td class="fw-bold text-center"><?= $no++; ?></td>
                    <td><strong class="hd-no-translate notranslate" translate="no"><?= h(function_exists('hd_ticket_no_raw') ? hd_ticket_no_raw($ticket['ticket_no']) : $ticket['ticket_no']); ?></strong></td>
                    <td><?= h($ticket['title']); ?></td>
                    <td><span class="badge bg-dark hd-no-translate notranslate" translate="no"><?= h(function_exists('hd_branch_code_raw') ? hd_branch_code_raw($ticket['branch'] ?: '-') : ($ticket['branch'] ?: '-')); ?></span></td>
                    <td><?= h($ticket['department'] ?: '-'); ?></td>
                    <td><span class="badge bg-info text-dark"><?= h($ticket['category'] ?? 'Other'); ?></span></td>
                    <td><span class="badge <?= $priorityClass[$ticket['priority']] ?? 'bg-secondary'; ?>"><?= h($ticket['priority'] ?? 'Medium'); ?></span></td>
                    <td><span class="badge <?= $statusClass[$ticket['status']] ?? 'bg-secondary'; ?>"><?= h($ticket['status'] ?? 'Closed'); ?></span></td>
                    <td><?= h($ticket['assigned_to'] ?: 'Unassigned'); ?></td>
                    <td><?= h($ticket['created_at']); ?></td>
                    <td><?= h(last_update_value($ticket)); ?></td>
                    <td><?= h(last_updated_by_value($ticket)); ?></td>
                    <td>
                        <a href="view_ticket.php?id=<?= (int)$ticket['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                        <?php if(has_action_permission('delete_ticket')): ?>
                        <a href="delete_ticket.php?id=<?= (int)$ticket['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this closed ticket? This action cannot be undone.');"><?= h(__('Delete')) ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($tickets) == 0): ?>
                <tr><td colspan="13"><div class="empty-state"><i class="bi bi-archive fs-1 d-block mb-2"></i><?= h(closed_t('no_closed_tickets_found')) ?></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<div class="closed-mobile-cards">
    <div class="closed-mobile-summary">
        <span><?= h(sprintf(closed_t('total_tickets'), count($tickets))); ?></span>
        <span><?= h(__('Sort')) ?>: <?= h(closed_sort_label($sort)); ?> <?= $order === 'asc' ? '↑' : '↓'; ?></span>
    </div>

    <?php foreach($tickets as $ticket): ?>
    <div class="closed-mobile-card">
        <div class="closed-mobile-top">
            <div>
                <div class="closed-mobile-no hd-no-translate notranslate" translate="no"><?= h(function_exists('hd_ticket_no_raw') ? hd_ticket_no_raw($ticket['ticket_no']) : $ticket['ticket_no']); ?></div>
                <div class="closed-mobile-title"><?= h($ticket['title']); ?></div>
            </div>
            <div class="d-flex gap-2 align-items-start flex-wrap justify-content-end">
                <span class="badge <?= $statusClass[$ticket['status']] ?? 'bg-secondary'; ?>"><?= h($ticket['status'] ?? 'Closed'); ?></span>
                <span class="badge bg-dark hd-no-translate notranslate" translate="no"><?= h(function_exists('hd_branch_code_raw') ? hd_branch_code_raw($ticket['branch'] ?: '-') : ($ticket['branch'] ?: '-')); ?></span>
            </div>
        </div>

        <div class="closed-mobile-badges">
            <span class="badge bg-info text-dark"><?= h($ticket['category'] ?? 'Other'); ?></span>
            <span class="badge <?= $priorityClass[$ticket['priority']] ?? 'bg-secondary'; ?>"><?= h($ticket['priority'] ?? 'Medium'); ?></span>
            <span class="badge bg-light text-dark"><?= h($ticket['assigned_to'] ?: 'Unassigned'); ?></span>
        </div>

        <div class="closed-mobile-meta">
            <div><strong><?= h(__('Person In Charge')) ?></strong><?= h($ticket['department'] ?: '-'); ?></div>
            <div><strong><?= h(__('Assigned')) ?></strong><?= h(!empty($ticket['assigned_to']) ? $ticket['assigned_to'] : __('Unassigned')); ?></div>
            <div><strong><?= h(__('Created')) ?></strong><?= h($ticket['created_at'] ?? '-'); ?></div>
            <div><strong><?= h(__('Last Update')) ?></strong><?= h(last_update_value($ticket)); ?></div>
        </div>

        <div class="closed-mobile-actions">
            <a href="view_ticket.php?id=<?= (int)$ticket['id']; ?>" class="btn btn-primary"><?= h(__('View')) ?></a>
            <?php if(has_action_permission('delete_ticket')): ?>
            <a href="delete_ticket.php?id=<?= (int)$ticket['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Delete this closed ticket? This action cannot be undone.');"><?= h(__('Delete')) ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if(count($tickets) == 0): ?>
        <div class="closed-mobile-empty">
            <i class="bi bi-archive fs-1 d-block mb-2"></i>
            <?= h(closed_t('no_closed_tickets_found')) ?>
        </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
