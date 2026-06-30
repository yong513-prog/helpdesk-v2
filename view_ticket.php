<?php

require 'header.php';
require 'db.php';
require_once 'access_control.php';
require_once 'module_permissions.php';
require_once 'ticket_status_options.php';
require_once 'attachment_preview.php';
require_once 'ticket_attachment_helper.php';


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


ticket_status_ensure_ticket_column($pdo);


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


$returnStatus = trim($_GET['return_status'] ?? '');
$returnBranch = trim($_GET['return_branch'] ?? '');
$returnPriority = trim($_GET['return_priority'] ?? '');

function render_return_hidden_fields($returnStatus, $returnBranch, $returnPriority)
{
    echo '<input type="hidden" name="return_status" value="'.htmlspecialchars($returnStatus, ENT_QUOTES, 'UTF-8').'">';
    echo '<input type="hidden" name="return_branch" value="'.htmlspecialchars($returnBranch, ENT_QUOTES, 'UTF-8').'">';
    echo '<input type="hidden" name="return_priority" value="'.htmlspecialchars($returnPriority, ENT_QUOTES, 'UTF-8').'">';
}

function h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function hd_view_current_lang()
{
    $lang = $_GET['lang'] ?? ($_SESSION['lang'] ?? ($_SESSION['language'] ?? 'en'));
    $lang = strtolower((string)$lang);
    if(in_array($lang, ['zh','cn','zh-cn','chinese'])) return 'zh';
    if(in_array($lang, ['ms','bm','my','malay','bahasa'])) return 'ms';
    return 'en';
}


function hd_view_pic_label()
{
    $lang = hd_view_current_lang();
    if($lang === 'zh') return '负责人';
    if($lang === 'ms') return 'Pegawai Bertanggungjawab';
    return 'Person In Charge';
}

function hd_view_timeline_text($action)
{
    $action = (string)($action ?? '');
    $suffix = '';
    if(preg_match('/\s*(\(\d+\))\s*$/', $action, $m))
    {
        $suffix = ' '.$m[1];
        $action = preg_replace('/\s*\(\d+\)\s*$/', '', $action);
    }

    $lang = hd_view_current_lang();
    $statusChanged = [
        'zh' => '状态更新',
        'ms' => 'Status Dikemaskini',
        'en' => 'Status Changed'
    ][$lang] ?? 'Status Changed';

    $changedOnly = [
        'zh' => '更新',
        'ms' => 'Dikemaskini',
        'en' => 'Changed'
    ][$lang] ?? 'Changed';

    if(stripos($action, 'Status Changed') !== false)
    {
        $action = preg_replace('/Status\s+Changed/i', $statusChanged, $action);
    }
    elseif(preg_match('/\bChanged\b/i', $action))
    {
        $action = preg_replace('/\bChanged\b/i', $changedOnly, $action);
    }
    else
    {
        $action = __($action);
    }

    return $action.$suffix;
}

function ticket_badge_class($status)
{
    global $statusClass;

    $bootstrapClass = $statusClass[$status] ?? 'bg-secondary';

    if(strpos($bootstrapClass, 'bg-danger') !== false) return 'td-badge-danger';
    if(strpos($bootstrapClass, 'bg-warning') !== false) return 'td-badge-warning';
    if(strpos($bootstrapClass, 'bg-info') !== false || strpos($bootstrapClass, 'bg-primary') !== false) return 'td-badge-info';
    if(strpos($bootstrapClass, 'bg-success') !== false) return 'td-badge-success';

    return 'td-badge-secondary';
}

function priority_badge_class($priority)
{
    $map = [
        'Low' => 'td-badge-secondary',
        'Medium' => 'td-badge-info',
        'High' => 'td-badge-warning',
        'Urgent' => 'td-badge-danger'
    ];
    return $map[$priority] ?? 'td-badge-info';
}

function file_icon_class($path)
{
    $ext = strtolower(pathinfo((string)$path, PATHINFO_EXTENSION));
    if(in_array($ext, ['jpg','jpeg','png','gif','webp'])) return 'bi-file-image';
    if(in_array($ext, ['mp3','m4a','wav','aac','ogg','webm','mp4'])) return 'bi-mic-fill';
    if($ext == 'pdf') return 'bi-file-earmark-pdf';
    if(in_array($ext, ['doc','docx'])) return 'bi-file-earmark-word';
    if(in_array($ext, ['xls','xlsx'])) return 'bi-file-earmark-excel';
    return 'bi-paperclip';
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
$stmt->execute([$id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$ticket)
{
    die("Ticket not found");
}

if(!can_access_ticket($ticket))
{
    die("Access denied");
}

$asset = null;
if(!empty($ticket['asset_id']))
{
    $stmtAsset = $pdo->prepare("SELECT * FROM assets WHERE id = ? LIMIT 1");
    $stmtAsset->execute([$ticket['asset_id']]);
    $asset = $stmtAsset->fetch(PDO::FETCH_ASSOC);
}

$creator = '-';
if(!empty($ticket['created_by']))
{
    $stmtCreator = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ? LIMIT 1");
    $stmtCreator->execute([(int)$ticket['created_by']]);
    $creatorRow = $stmtCreator->fetch(PDO::FETCH_ASSOC);
    if($creatorRow)
    {
        $creator = !empty($creatorRow['full_name']) ? $creatorRow['full_name'] : $creatorRow['username'];
    }
}

$role = $_SESSION['role'] ?? 'staff';
$canEditTicket = has_action_permission('edit_ticket');
$canChangeStatus = has_action_permission('change_status');
$canAssignTicket = has_action_permission('assign_ticket');
$canReplyTicket = has_action_permission('reply_ticket');
$canUpdateTicket = ($canChangeStatus || $canAssignTicket);

$ticketStatusList = ticket_status_fetch_all($pdo, true);
$statusClass = ticket_status_color_map($pdo);

$assignStmt = $pdo->query("SELECT assign_name FROM assign_to_master WHERE status = 1 ORDER BY assign_name");
$assignList = $assignStmt->fetchAll(PDO::FETCH_ASSOC);

$isOpenTicket = !ticket_status_is_closed($pdo, (string)($ticket['status'] ?? ''));
$isOverdue = !empty($ticket['due_date']) && $isOpenTicket && strtotime($ticket['due_date']) < time();
$slaText = '-';
$slaClass = 'td-badge-secondary';
if($isOverdue)
{
    $slaText = 'Overdue';
    $slaClass = 'td-badge-danger';
}
elseif(!empty($ticket['due_date']) && $isOpenTicket)
{
    $slaText = 'Within SLA';
    $slaClass = 'td-badge-success';
}
elseif(!$isOpenTicket)
{
    $slaText = 'Completed';
    $slaClass = 'td-badge-secondary';
}

$stmt = $pdo->prepare("\nSELECT th.*, COALESCE(u.full_name, u.username, '-') AS username\nFROM ticket_history th\nLEFT JOIN users u ON u.id = th.created_by\nWHERE th.ticket_id = ?\nORDER BY th.id DESC\n");
$stmt->execute([$ticket['id']]);
$timelineLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("\nSELECT tr.*, COALESCE(u.full_name, u.username, '-') AS username, COALESCE(u.role, '-') AS role_name\nFROM ticket_replies tr\nLEFT JOIN users u ON u.id = tr.user_id\nWHERE tr.ticket_id = ?\nORDER BY tr.id DESC\n");
$stmt->execute([$ticket['id']]);
$replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

hd_ta_ensure_table($pdo);
$ticketAttachments = hd_ta_fetch_ticket($pdo, (int)$ticket['id']);
$replyAttachmentsByReply = hd_ta_fetch_by_reply($pdo, (int)$ticket['id']);

?>

<style>
.ticket-page{padding-bottom:24px;}
.ticket-hero{background:linear-gradient(135deg,#ffffff,#f8fbff);border:1px solid #e7edf7;border-radius:22px;padding:22px;box-shadow:0 10px 30px rgba(15,23,42,.05);margin-bottom:18px;}
.ticket-hero-title{display:flex;gap:16px;align-items:flex-start;}
.ticket-icon{width:56px;height:56px;border-radius:18px;background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;display:flex;align-items:center;justify-content:center;font-size:27px;box-shadow:0 12px 25px rgba(37,99,235,.22);flex:0 0 auto;}
.ticket-title{font-size:28px;font-weight:800;margin:0 0 6px;color:#0f172a;}
.ticket-subtitle{color:#64748b;margin:0;font-size:14px;}
.ticket-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;}
.ticket-card{background:#fff;border:1px solid #e7edf7;border-radius:18px;box-shadow:0 8px 24px rgba(15,23,42,.045);margin-bottom:18px;overflow:hidden;}
.ticket-card-header{padding:16px 18px;border-bottom:1px solid #eef2f7;background:#fbfdff;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:8px;}
.ticket-card-body{padding:18px;}
.ticket-stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px;}
.ticket-stat{background:#fff;border:1px solid #e7edf7;border-radius:18px;padding:16px;box-shadow:0 8px 24px rgba(15,23,42,.04);display:flex;gap:12px;align-items:center;}
.ticket-stat .stat-icon{width:42px;height:42px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;background:#2563eb;}
.ticket-stat .stat-label{font-size:12px;color:#64748b;margin-bottom:2px;}
.ticket-stat .stat-value{font-size:15px;font-weight:800;color:#0f172a;word-break:break-word;}
.info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;}
.info-item{border:1px solid #edf2f7;border-radius:14px;padding:13px;background:#fff;}
.info-label{font-size:12px;color:#64748b;margin-bottom:5px;}
.info-value{font-size:14px;font-weight:700;color:#0f172a;word-break:break-word;}
.td-badge{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:800;line-height:1;}
.td-badge-danger{background:#fee2e2;color:#991b1b;}
.td-badge-warning{background:#fef3c7;color:#92400e;}
.td-badge-info{background:#dbeafe;color:#1e40af;}
.td-badge-success{background:#dcfce7;color:#166534;}
.td-badge-secondary{background:#e5e7eb;color:#374151;}
.description-box{min-height:110px;background:#f8fafc;border:1px solid #eef2f7;border-radius:16px;padding:16px;white-space:normal;color:#1f2937;line-height:1.65;}
.action-panel{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.action-box{border:1px solid #e7edf7;border-radius:16px;padding:16px;background:#fff;}
.form-control,.form-select{border-radius:12px;border-color:#dbe3ef;}
.btn{border-radius:12px;font-weight:700;}
.attachment-preview{border:1px solid #e7edf7;background:#f8fafc;border-radius:16px;padding:14px;display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;}
.attachment-img{max-width:100%;max-height:280px;border-radius:14px;border:1px solid #e7edf7;margin-top:12px;}
.timeline{position:relative;padding-left:10px;}
.timeline-item{display:flex;gap:12px;padding:12px 0;border-bottom:1px dashed #e5e7eb;}
.timeline-dot{width:34px;height:34px;border-radius:50%;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center;flex:0 0 auto;}
.timeline-title{font-weight:800;color:#0f172a;}
.timeline-meta{font-size:12px;color:#64748b;margin-top:2px;}
.reply-card{border:1px solid #e7edf7;border-radius:16px;padding:16px;margin-bottom:12px;background:#fff;}
.reply-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:10px;}
.reply-user{font-weight:800;color:#0f172a;}
.reply-message{background:#f8fafc;border-radius:14px;padding:12px;line-height:1.6;}
.empty-state{padding:20px;text-align:center;color:#64748b;background:#f8fafc;border-radius:16px;border:1px dashed #cbd5e1;}
@media(max-width:1100px){.ticket-stat-grid{grid-template-columns:repeat(2,minmax(0,1fr));}.action-panel{grid-template-columns:1fr;}}
@media(max-width:768px){.ticket-hero{padding:16px;}.ticket-actions{justify-content:flex-start;margin-top:14px;}.ticket-stat-grid,.info-grid{grid-template-columns:1fr;}.ticket-title{font-size:23px;}}

@media(max-width:768px){
.ticket-page{padding-bottom:86px}.ticket-hero{border-radius:14px}.ticket-actions{display:grid!important;grid-template-columns:1fr 1fr;gap:8px;width:100%}.ticket-actions .btn{width:100%}
.action-panel{gap:12px}.action-card{border-radius:14px;padding:14px}.reply-card,.timeline-card,.asset-card{border-radius:14px}
.action-panel select,.action-panel textarea,.action-panel input{font-size:16px}
.ticket-stat-grid .stat-card{min-height:auto}.info-grid{gap:8px}
}


/* View Ticket Mobile Collapse Optimization */
.collapse-card-toggle{
    cursor:pointer;
    user-select:none;
}
.collapse-card-toggle .ms-auto{
    transition:transform .18s ease;
}
.ticket-card.is-collapsed .collapse-card-toggle .ms-auto{
    transform:rotate(-90deg);
}
.ticket-card.is-collapsed .ticket-card-body{
    display:none;
}
.mobile-detail-toggle-row{
    display:none;
}
@media(max-width:768px){
    .ticket-page{
        padding-bottom:88px!important;
    }

    .ticket-hero{
        border-radius:18px!important;
        padding:16px!important;
        margin-bottom:14px!important;
    }

    .ticket-icon{
        width:48px!important;
        height:48px!important;
        border-radius:16px!important;
        font-size:23px!important;
    }

    .ticket-title{
        font-size:26px!important;
        line-height:1.2!important;
    }

    .ticket-subtitle{
        font-size:14px!important;
        line-height:1.45!important;
    }

    .ticket-actions{
        display:grid!important;
        grid-template-columns:1fr 1fr!important;
        gap:10px!important;
        margin-top:14px!important;
    }

    .ticket-actions .btn{
        min-height:46px!important;
        border-radius:14px!important;
        font-size:16px!important;
        width:100%!important;
    }

    .ticket-actions .btn-warning{
        grid-column:1 / -1;
    }

    .mobile-detail-toggle-row{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:10px;
        margin:0 0 14px;
    }

    .mobile-detail-toggle-row button{
        min-height:48px;
        border-radius:14px;
        font-weight:900;
    }

    .ticket-stat-grid{
        display:none;
        grid-template-columns:1fr!important;
        gap:10px!important;
        margin-bottom:14px!important;
    }

    .ticket-stat-grid.mobile-show{
        display:grid!important;
    }

    .ticket-stat{
        border-radius:16px!important;
        padding:14px!important;
    }

    .ticket-card{
        border-radius:18px!important;
        margin-bottom:14px!important;
    }

    .ticket-card-header{
        padding:15px 16px!important;
        font-size:17px!important;
        justify-content:space-between!important;
    }

    .ticket-card-header .collapse-hint{
        display:inline-flex;
        align-items:center;
        gap:5px;
        color:#64748b;
        font-size:12px;
        font-weight:800;
    }

    .ticket-card-body{
        padding:15px!important;
    }

    .info-grid{
        grid-template-columns:1fr!important;
        gap:10px!important;
    }

    .info-item{
        border-radius:15px!important;
        padding:13px!important;
    }

    .description-box{
        min-height:auto!important;
        border-radius:15px!important;
        padding:14px!important;
    }

    .action-panel{
        grid-template-columns:1fr!important;
    }

    .action-box{
        border-radius:15px!important;
        padding:14px!important;
    }

    .form-control,.form-select{
        min-height:50px!important;
        font-size:16px!important;
        border-radius:13px!important;
    }

    .btn{
        min-height:44px;
    }

    /* Default hidden information sections on mobile */
    .ticket-card.mobile-collapse-default .ticket-card-body{
        display:none;
    }

    .ticket-card.mobile-collapse-default.mobile-open .ticket-card-body{
        display:block;
    }

    .ticket-card.mobile-collapse-default.mobile-open .collapse-card-toggle .ms-auto{
        transform:rotate(0deg);
    }

    .ticket-card.mobile-collapse-default .collapse-card-toggle .ms-auto{
        transform:rotate(-90deg);
    }
}


/* Reply Upload Mobile Fix */
.reply-upload-box{
    border:1.5px dashed #cbd5e1;
    border-radius:16px;
    padding:14px;
    background:#fbfdff;
}
.reply-file-input-wrap{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}
.reply-file-picker-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    min-height:46px;
    padding:10px 14px;
    border-radius:14px;
    border:1px solid #dbe3ef;
    background:#fff;
    color:#0f172a;
    font-weight:900;
}
.reply-file-name{
    color:#64748b;
    font-size:13px;
    word-break:break-word;
}

.reply-file-name .hd-selected-files{margin-top:8px;border:1px solid #e5e7eb;background:#fff;border-radius:14px;padding:10px;display:grid;gap:8px;width:100%}
.reply-file-name .hd-selected-files-head{display:flex;align-items:center;justify-content:space-between;gap:8px;color:#334155;font-size:13px;margin-bottom:2px}
.reply-file-name .hd-selected-file-row{display:grid;grid-template-columns:auto minmax(0,1fr) auto auto;align-items:center;gap:8px;border:1px solid #eef2f7;background:#f8fafc;border-radius:12px;padding:8px 10px}
.reply-file-name .hd-selected-file-icon{font-size:18px;line-height:1}.reply-file-name .hd-selected-file-name{font-weight:800;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.reply-file-name .hd-selected-file-size{font-size:12px;color:#64748b;white-space:nowrap}.reply-file-name .hd-selected-file-remove{border:1px solid #fecaca;background:#fff5f5;color:#dc2626;border-radius:10px;padding:6px 10px;font-weight:900}
@media(max-width:768px){.reply-file-name .hd-selected-file-row{grid-template-columns:auto minmax(0,1fr) auto}.reply-file-name .hd-selected-file-size{display:none}.reply-file-name .hd-selected-file-remove{padding:7px 10px}.reply-file-name .hd-selected-files-head{font-size:13px}}
@media(max-width:768px){
    .reply-upload-box{
        padding:13px!important;
        border-radius:16px!important;
    }
    .reply-file-input-wrap{
        display:grid!important;
        grid-template-columns:1fr!important;
        gap:9px!important;
    }
    .reply-file-picker-btn{
        width:100%!important;
        min-height:52px!important;
        font-size:16px!important;
    }
    .reply-file-name{
        font-size:13px!important;
    }
    input[type="file"].reply-hidden-file{
        position:absolute!important;
        width:1px!important;
        height:1px!important;
        opacity:0!important;
        overflow:hidden!important;
        pointer-events:none!important;
    }
}


/* One Click Quick Action */
.quick-confirm-box{
    width:100%;
}
.quick-confirm-box .action-panel{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
}
.quick-confirm-btn{
    min-height:46px;
    font-weight:900;
}
@media(max-width:768px){
    .quick-confirm-box .action-panel{
        grid-template-columns:1fr!important;
        gap:12px!important;
    }
    .quick-confirm-btn{
        min-height:52px!important;
        border-radius:14px!important;
        font-size:17px!important;
    }
}

.circle-final-mobile-fix{}
@media(max-width:768px){.ticket-mobile-tools .btn{white-space:nowrap}.empty-state{font-size:16px}}

/* Old Replies Collapse Fix */
.old-replies-toggle{
    min-height:42px;
    border-radius:12px;
    font-weight:800;
}
#oldReplyHistory{
    display:none;
}
#oldReplyHistory.show{
    display:block;
}

</style>

<div class="ticket-page">

    <div class="ticket-hero">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <div class="ticket-hero-title">
                    <div class="ticket-icon"><i class="bi bi-ticket-detailed"></i></div>
                    <div>
                        <div class="d-flex gap-2 flex-wrap mb-2">
                            <span class="td-badge <?= ticket_badge_class($ticket['status'] ?? ''); ?>"><i class="bi bi-circle-fill" style="font-size:7px;"></i><?= h(__($ticket['status'] ?? '-')); ?></span>
                            <span class="td-badge <?= priority_badge_class($ticket['priority'] ?? 'Medium'); ?>"><i class="bi bi-flag-fill"></i><?= h(__($ticket['priority'] ?? 'Medium')); ?></span>
                            <span class="td-badge <?= $slaClass; ?>"><i class="bi bi-clock-history"></i><?= h(__($slaText)); ?></span>
                        </div>
                        <h2 class="ticket-title"><?= h($ticket['title']); ?></h2>
                        <p class="ticket-subtitle"><?= h(__('Ticket No')) ?>: <strong><?= h($ticket['ticket_no']); ?></strong> · <?= h(__('Created At')) ?> <?= h($ticket['created_at'] ?? '-'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="ticket-actions">
                    <a href="ticket_list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> <?= h(__('Back')) ?></a>
                    <button type="button" onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> <?= h(__('Print')) ?></button>
                    <?php if($canEditTicket): ?>
                        <a href="edit_ticket.php?id=<?= (int)$ticket['id']; ?>" class="btn btn-warning"><i class="bi bi-pencil-square"></i> <?= h(__('Edit Ticket')) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


<div class="mobile-detail-toggle-row">
    <button type="button" class="btn btn-outline-primary" id="showTicketStats">
        <i class="bi bi-grid-3x3-gap me-1"></i> <?= h(__('Show Summary')) ?>
    </button>
    <button type="button" class="btn btn-outline-secondary" id="openAllMobileCards">
        <i class="bi bi-chevron-down me-1"></i> <?= h(__('Open All')) ?>
    </button>
</div>

    <div class="ticket-stat-grid" id="ticketStatGrid">
        <div class="ticket-stat"><div class="stat-icon"><i class="bi bi-shop"></i></div><div><div class="stat-label"><?= h(__('Branch')) ?></div><div class="stat-value"><?= h($ticket['branch'] ?: '-'); ?></div></div></div>
        <div class="ticket-stat"><div class="stat-icon" style="background:#7c3aed;"><i class="bi bi-person-badge"></i></div><div><div class="stat-label"><?= h(hd_view_pic_label()) ?></div><div class="stat-value"><?= h($ticket['department'] ?: '-'); ?></div></div></div>
        <div class="ticket-stat"><div class="stat-icon" style="background:#0891b2;"><i class="bi bi-person-check"></i></div><div><div class="stat-label"><?= h(__('Assigned To')) ?></div><div class="stat-value"><?= h(!empty($ticket['assigned_to']) ? $ticket['assigned_to'] : __('Unassigned')); ?></div></div></div>
        <div class="ticket-stat"><div class="stat-icon" style="background:<?= $isOverdue ? '#dc2626' : '#16a34a'; ?>;"><i class="bi bi-calendar-event"></i></div><div><div class="stat-label"><?= h(__('Due Date')) ?></div><div class="stat-value"><?= h(!empty($ticket['due_date']) ? $ticket['due_date'] : '-'); ?></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="ticket-card mobile-collapse-default">
                <div class="ticket-card-header collapse-card-toggle"><span><i class="bi bi-info-circle"></i> <?= h(__('Ticket Information')) ?></span><i class="bi bi-chevron-down ms-auto"></i></div>
                <div class="ticket-card-body">
                    <div class="info-grid">
                        <div class="info-item"><div class="info-label"><?= h(__('Category')) ?></div><div class="info-value"><span class="td-badge td-badge-info"><?= h($ticket['category'] ?? 'Other'); ?></span></div></div>
                        <div class="info-item"><div class="info-label"><?= h(__('Created By')) ?></div><div class="info-value"><?= h($creator); ?></div></div>
                        <div class="info-item"><div class="info-label"><?= h(__('Created At')) ?></div><div class="info-value"><?= h($ticket['created_at'] ?? '-'); ?></div></div>
                        <div class="info-item"><div class="info-label"><?= h(__('Last Updated')) ?></div><div class="info-value"><?= h(!empty($ticket['last_update']) ? $ticket['last_update'] : ($ticket['updated_at'] ?? '-')); ?></div></div>
                        <div class="info-item"><div class="info-label"><?= h(__('Last Updated By')) ?></div><div class="info-value"><?= h($ticket['last_updated_by'] ?? '-'); ?></div></div>
                        <div class="info-item"><div class="info-label"><?= h(__('Closed At')) ?></div><div class="info-value"><?= h($ticket['closed_at'] ?? '-'); ?></div></div>
                        <div class="info-item"><div class="info-label"><?= h(__('SLA Hours')) ?></div><div class="info-value"><?= h($ticket['sla_hours'] ?? '-'); ?></div></div>
                    </div>
                </div>
            </div>

            <div class="ticket-card">
                <div class="ticket-card-header"><i class="bi bi-card-text"></i> <?= h(__('Description')) ?></div>
                <div class="ticket-card-body">
                    <div class="description-box"><?= nl2br(h($ticket['description'])); ?></div>

                    <?php
                    $legacyTicketAttachmentShown = false;
                    if(!empty($ticket['attachment'])){
                        echo hd_ap_render($ticket['attachment'], basename($ticket['attachment']), __('Ticket Attachment'));
                        $legacyTicketAttachmentShown = true;
                    }
                    foreach(($ticketAttachments ?? []) as $idx => $ta){
                        if($legacyTicketAttachmentShown && !empty($ticket['attachment']) && $ta['file_path'] === $ticket['attachment']) continue;
                        echo hd_ap_render($ta['file_path'], $ta['original_name'] ?? basename($ta['file_path']), __('Ticket Attachment').' '.($idx+1));
                    }
                    ?>
                </div>
            </div>

<?php if($canReplyTicket): ?>
            <div class="ticket-card">
                <div class="ticket-card-header"><i class="bi bi-reply-all"></i> <?= h(__('Reply Ticket')) ?></div>
                <div class="ticket-card-body">
                    <form action="reply_ticket.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label fw-bold"><?= h(__('Message')) ?></label>
                            <textarea name="message" rows="5" class="form-control" placeholder="<?= h(__('Type update, troubleshooting result, or instruction for branch...')) ?>"></textarea>
                        </div>
                        <div class="mb-3 reply-upload-box">
                            <label class="form-label fw-bold"><?= h(__('Upload Attachment')) ?></label>
                            <div class="reply-file-input-wrap">
                                <button type="button" class="reply-file-picker-btn" data-file-target="replyAttachment">
                                    <i class="bi bi-folder2-open"></i> <?= h(__('Choose File / Photo')) ?>
                                </button>
                                <button type="button" class="reply-file-picker-btn hd-wa-record-btn hd-wa-reply-btn" data-target-input="replyAttachment" data-preview="replyFileName">
                                    <i class="bi bi-mic-fill"></i> <?= h(__('Hold Voice')) ?>
                                </button>
                                <div class="reply-file-name" id="replyFileName"><?= h(__('No file selected')) ?></div>
                            </div>
                            <input type="file" name="attachments[]" id="replyAttachment" class="form-control reply-hidden-file" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.mp3,.m4a,.wav,.aac,.ogg,.webm,.mp4,.mov" multiple>
                            <small class="text-muted d-block mt-2"><?= h(__('Allowed: multiple photos, videos, voice/audio, PDF, Word and Excel. Max 50MB each.')) ?></small>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> <?= h(__('Submit Reply')) ?></button>
                    </form>
                </div>
            </div>
<?php endif; ?>

            <div class="ticket-card">
                <div class="ticket-card-header"><i class="bi bi-chat-left-text"></i> <?= h(__('Reply History')) ?></div>
                <div class="ticket-card-body">
                    <?php if(count($replies) == 0): ?>
                        <div class="empty-state"><i class="bi bi-inbox"></i><br><?= h(__('No replies yet.')) ?></div>
                    <?php else: ?>
                        <?php
                        $latestReply = $replies[0];
                        $oldReplies = array_slice($replies, 1);
                        ?>

                        <div class="reply-card latest-reply-card">
                            <div class="reply-head">
                                <div>
                                    <div class="reply-user"><i class="bi bi-person-circle me-1"></i><?= h($latestReply['username']); ?></div>
                                    <small class="text-muted"><?= h(ucfirst($latestReply['role_name'] ?? '-')); ?></small>
                                </div>
                                <small class="text-muted"><i class="bi bi-clock"></i> <?= h($latestReply['created_at']); ?></small>
                            </div>

                            <?php if(trim((string)$latestReply['message']) !== ''): ?>
                                <div class="reply-message"><?= nl2br(h($latestReply['message'])); ?></div>
                            <?php endif; ?>

                            <?php
                            $legacyReplyAttachmentShown = false;
                            if(!empty($latestReply['attachment'])){
                                echo hd_ap_render($latestReply['attachment'], basename($latestReply['attachment']), __('Reply Attachment'), true);
                                $legacyReplyAttachmentShown = true;
                            }
                            foreach(($replyAttachmentsByReply[(int)$latestReply['id']] ?? []) as $idx => $ra){
                                if($legacyReplyAttachmentShown && !empty($latestReply['attachment']) && $ra['file_path'] === $latestReply['attachment']) continue;
                                echo hd_ap_render($ra['file_path'], $ra['original_name'] ?? basename($ra['file_path']), __('Reply Attachment').' '.($idx+1), true);
                            }
                            ?>
                        </div>

                        <?php if(count($oldReplies) > 0): ?>
                            <div class="text-center my-3">
                                <button class="btn btn-outline-secondary old-replies-toggle"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#oldReplyHistory"
                                        aria-expanded="false"
                                        aria-controls="oldReplyHistory">
                                    <i class="bi bi-clock-history"></i>
                                    <?= h(__('Show Old Replies')) ?> (<?= count($oldReplies); ?>)
                                </button>
                            </div>

                            <div class="collapse" id="oldReplyHistory">
                                <?php foreach($oldReplies as $reply): ?>
                                    <div class="reply-card old-reply-card">
                                        <div class="reply-head">
                                            <div>
                                                <div class="reply-user"><i class="bi bi-person-circle me-1"></i><?= h($reply['username']); ?></div>
                                                <small class="text-muted"><?= h(ucfirst($reply['role_name'] ?? '-')); ?></small>
                                            </div>
                                            <small class="text-muted"><i class="bi bi-clock"></i> <?= h($reply['created_at']); ?></small>
                                        </div>

                                        <?php if(trim((string)$reply['message']) !== ''): ?>
                                            <div class="reply-message"><?= nl2br(h($reply['message'])); ?></div>
                                        <?php endif; ?>

                                        <?php
                                        $legacyReplyAttachmentShown = false;
                                        if(!empty($reply['attachment'])){
                                            echo hd_ap_render($reply['attachment'], basename($reply['attachment']), __('Reply Attachment'), true);
                                            $legacyReplyAttachmentShown = true;
                                        }
                                        foreach(($replyAttachmentsByReply[(int)$reply['id']] ?? []) as $idx => $ra){
                                            if($legacyReplyAttachmentShown && !empty($reply['attachment']) && $ra['file_path'] === $reply['attachment']) continue;
                                            echo hd_ap_render($ra['file_path'], $ra['original_name'] ?? basename($ra['file_path']), __('Reply Attachment').' '.($idx+1), true);
                                        }
                                        ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <?php if($canUpdateTicket): ?>
                <div class="ticket-card mobile-collapse-default mobile-open">
                    <div class="ticket-card-header collapse-card-toggle"><span><i class="bi bi-sliders"></i> <?= h(__('Quick Actions')) ?></span><i class="bi bi-chevron-down ms-auto"></i></div>
                    <div class="ticket-card-body">
                        <form action="quick_action_update.php" method="post" class="action-box quick-confirm-box">
                            <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id']; ?>">

                            <div class="action-panel">
                                <?php if($canChangeStatus): ?>
                                <div>
                                    <label class="form-label fw-bold"><?= h(__('Update Status')) ?></label>
                                    <select name="status" class="form-select">
                                        <?php foreach($ticketStatusList as $statusOption): ?>
                                        <option value="<?= htmlspecialchars($statusOption['status_name']); ?>" <?= ($ticket['status']==$statusOption['status_name']) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($statusOption['status_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php else: ?>
                                    <input type="hidden" name="status" value="<?= h($ticket['status'] ?? ''); ?>">
                                <?php endif; ?>

                                <?php if($canAssignTicket): ?>
                                <div>
                                    <label class="form-label fw-bold"><?= h(__('Assign To')) ?></label>
                                    <select name="assigned_to" class="form-select">
                                        <option value=""><?= h(__('Unassigned')) ?></option>
                                        <?php foreach($assignList as $assign): ?>
                                            <option value="<?= h($assign['assign_name']); ?>" <?= (($ticket['assigned_to'] ?? '') == $assign['assign_name']) ? 'selected' : ''; ?>><?= h($assign['assign_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php else: ?>
                                    <input type="hidden" name="assigned_to" value="<?= h($ticket['assigned_to'] ?? ''); ?>">
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn btn-success w-100 mt-3 quick-confirm-btn">
                                <i class="bi bi-check2-circle"></i> <?= h(__('One Click Confirm')) ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <div class="ticket-card mobile-collapse-default">
                <div class="ticket-card-header collapse-card-toggle"><span><i class="bi bi-pc-display"></i> <?= h(__('Asset / Equipment')) ?></span><i class="bi bi-chevron-down ms-auto"></i></div>
                <div class="ticket-card-body">
                    <?php if($asset): ?>
                        <div class="info-item mb-2"><div class="info-label">Asset</div><div class="info-value"><?= h($asset['asset_code'].' - '.$asset['asset_name']); ?></div></div>
                        <div class="info-grid" style="grid-template-columns:1fr;">
                            <div class="info-item"><div class="info-label">Type</div><div class="info-value"><?= h($asset['asset_type'] ?? '-'); ?></div></div>
                            <div class="info-item"><div class="info-label">Branch</div><div class="info-value"><?= h($asset['branch'] ?? '-'); ?></div></div>
                            <div class="info-item"><div class="info-label">Location</div><div class="info-value"><?= h($asset['location'] ?? '-'); ?></div></div>
                            <div class="info-item"><div class="info-label">Status</div><div class="info-value"><?= h($asset['status'] ?? '-'); ?></div></div>
                        </div>
                        <a href="asset_history.php?id=<?= (int)$asset['id']; ?>" class="btn btn-outline-secondary w-100 mt-3"><i class="bi bi-clock-history"></i> <?= h(__('View Asset History')) ?></a>
                    <?php else: ?>
                        <div class="empty-state"><i class="bi bi-pc-display-horizontal"></i><br><?= h(__('No asset linked.')) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ticket-card mobile-collapse-default">
                <div class="ticket-card-header collapse-card-toggle"><span><i class="bi bi-activity"></i> <?= h(__('Ticket Timeline')) ?></span><i class="bi bi-chevron-down ms-auto"></i></div>
                <div class="ticket-card-body">
                    <?php if(count($timelineLogs) == 0): ?>
                        <div class="empty-state"><?= h(__('No timeline records found.')) ?></div>
                    <?php endif; ?>
                    <div class="timeline">
                        <?php foreach($timelineLogs as $log): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot"><i class="bi bi-check2"></i></div>
                                <div>
                                    <div class="timeline-title"><?= h(hd_view_timeline_text($log['action'] ?? '')); ?></div>
                                    <div class="timeline-meta"><?= h($log['created_at']); ?> · <?= h($log['username']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>


<script>
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.collapse-card-toggle').forEach(function(header){
        header.addEventListener('click', function(){
            var card = header.closest('.ticket-card');
            if(card){
                card.classList.toggle('mobile-open');
            }
        });
    });

    var statsBtn = document.getElementById('showTicketStats');
    var statGrid = document.getElementById('ticketStatGrid');
    if(statsBtn && statGrid){
        statsBtn.addEventListener('click', function(){
            statGrid.classList.toggle('mobile-show');
            statsBtn.innerHTML = statGrid.classList.contains('mobile-show')
                ? '<i class="bi bi-eye-slash me-1"></i> <?= h(__('Hide Summary')) ?>'
                : '<i class="bi bi-grid-3x3-gap me-1"></i> <?= h(__('Show Summary')) ?>';
        });
    }

    var openAllBtn = document.getElementById('openAllMobileCards');
    if(openAllBtn){
        openAllBtn.addEventListener('click', function(){
            var cards = document.querySelectorAll('.ticket-card.mobile-collapse-default');
            var shouldOpen = false;
            cards.forEach(function(card){
                if(!card.classList.contains('mobile-open')){ shouldOpen = true; }
            });
            cards.forEach(function(card){
                card.classList.toggle('mobile-open', shouldOpen);
            });
            openAllBtn.innerHTML = shouldOpen
                ? '<i class="bi bi-chevron-up me-1"></i> <?= h(__('Close All')) ?>'
                : '<i class="bi bi-chevron-down me-1"></i> <?= h(__('Open All')) ?>';
        });
    }
});
</script>


<script>
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('[data-file-target]').forEach(function(btn){
        btn.addEventListener('click', function(){
            var input = document.getElementById(btn.getAttribute('data-file-target'));
            if(input){ input.click(); }
        });
    });

    var replyInput = document.getElementById('replyAttachment');
    var replyName = document.getElementById('replyFileName');
    var replySelectedFiles = [];
    var replySyncingFiles = false;

    function replyEscapeHtml(s){
        return String(s == null ? '' : s).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];});
    }
    function replyFileKey(f){
        return [f.name || '', f.size || 0, f.lastModified || 0, f.type || ''].join('|');
    }
    function replyFileIcon(f){
        var type=(f.type||'').toLowerCase();
        var name=(f.name||'').toLowerCase();
        if(type.indexOf('image/')===0 || /\.(jpg|jpeg|png|gif|webp)$/.test(name)) return '🖼️';
        if(type.indexOf('video/')===0 || /\.(mp4|mov|m4v|webm)$/.test(name)) return '🎥';
        if(type.indexOf('audio/')===0 || /\.(mp3|m4a|wav|aac|ogg|webm)$/.test(name)) return '🎤';
        if(/\.pdf$/.test(name)) return '📄';
        if(/\.(doc|docx)$/.test(name)) return '📝';
        if(/\.(xls|xlsx)$/.test(name)) return '📊';
        return '📎';
    }
    function replyFormatFileSize(bytes){
        bytes = Number(bytes || 0);
        if(bytes >= 1024*1024) return (bytes/1024/1024).toFixed(2) + ' MB';
        if(bytes >= 1024) return (bytes/1024).toFixed(1) + ' KB';
        return bytes + ' B';
    }
    function replySyncSelectedFilesToInput(){
        if(!replyInput || typeof DataTransfer === 'undefined') return;
        var dt = new DataTransfer();
        replySelectedFiles.forEach(function(f){ dt.items.add(f); });
        replySyncingFiles = true;
        replyInput.files = dt.files;
        replySyncingFiles = false;
    }
    function replyRenderSelectedFiles(){
        if(!replyName) return;
        if(replySelectedFiles.length === 0){
            replyName.textContent = 'No file selected';
            return;
        }
        var total = replySelectedFiles.reduce(function(s,f){ return s + (f.size || 0); }, 0);
        replyName.innerHTML = '<div class="hd-selected-files">'
            + '<div class="hd-selected-files-head"><strong>Selected: ' + replySelectedFiles.length + ' file(s)</strong><span>' + replyEscapeHtml(replyFormatFileSize(total)) + '</span></div>'
            + replySelectedFiles.map(function(f,idx){
                return '<div class="hd-selected-file-row" data-file-index="' + idx + '">'
                    + '<span class="hd-selected-file-icon">' + replyFileIcon(f) + '</span>'
                    + '<span class="hd-selected-file-name" title="' + replyEscapeHtml(f.name || 'attachment') + '">' + replyEscapeHtml(f.name || 'attachment') + '</span>'
                    + '<span class="hd-selected-file-size">' + replyEscapeHtml(replyFormatFileSize(f.size || 0)) + '</span>'
                    + '<button type="button" class="hd-selected-file-remove" data-remove-file="' + idx + '">Delete</button>'
                    + '</div>';
            }).join('')
            + '</div>';
    }
    function replyAddFilesFromInput(input){
        if(replySyncingFiles) return;
        var files = Array.from((input && input.files) ? input.files : []);
        if(input && input.dataset && input.dataset.hdForceReplace === '1'){
            replySelectedFiles = files.slice();
            replySyncSelectedFilesToInput();
            replyRenderSelectedFiles();
            return;
        }
        if(files.length === 0){ replyRenderSelectedFiles(); return; }
        var existing = new Set(replySelectedFiles.map(replyFileKey));
        files.forEach(function(f){
            var key = replyFileKey(f);
            if(!existing.has(key)){
                replySelectedFiles.push(f);
                existing.add(key);
            }
        });
        replySyncSelectedFilesToInput();
        replyRenderSelectedFiles();
    }
    function replyRemoveSelectedFile(index){
        index = Number(index);
        if(!Number.isInteger(index) || index < 0 || index >= replySelectedFiles.length) return;
        replySelectedFiles.splice(index, 1);
        replySyncSelectedFilesToInput();
        replyRenderSelectedFiles();
    }
    if(replyInput && replyName){
        replyInput.addEventListener('change', function(){ replyAddFilesFromInput(replyInput); });
        replyName.addEventListener('click', function(e){
            var btn = e.target.closest('[data-remove-file]');
            if(!btn) return;
            e.preventDefault();
            replyRemoveSelectedFile(btn.getAttribute('data-remove-file'));
        });
    }
});
</script>


<script>
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.old-replies-toggle').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.preventDefault();

            var selector = btn.getAttribute('data-bs-target') || btn.getAttribute('data-target');
            if(!selector){ return; }

            var target = document.querySelector(selector);
            if(!target){ return; }

            var isOpen = target.classList.toggle('show');
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

            var countMatch = (btn.textContent || '').match(/\((\d+)\)/);
            var countText = countMatch ? ' (' + countMatch[1] + ')' : '';

            if(isOpen){
                btn.innerHTML = '<i class="bi bi-chevron-up"></i> <?= h(__('Hide Old Replies')) ?>' + countText;
            }else{
                btn.innerHTML = '<i class="bi bi-clock-history"></i> <?= h(__('Show Old Replies')) ?>' + countText;
            }
        });
    });
});
</script>

<?= hd_ap_assets(); ?>
<?php require 'footer.php'; ?>
