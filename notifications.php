<?php
require 'header.php';
require 'db.php';
require_once 'notification_helper.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

notification_ensure_schema($pdo);
$userId = (int)$_SESSION['user_id'];

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function notif_icon_class($type){
    $type = strtolower((string)$type);
    if(strpos($type,'ticket') !== false) return 'bi-ticket-perforated';
    if(strpos($type,'announcement') !== false) return 'bi-megaphone-fill';
    if(strpos($type,'kb') !== false || strpos($type,'knowledge') !== false) return 'bi-book-half';
    if(strpos($type,'asset') !== false) return 'bi-pc-display';
    return 'bi-bell-fill';
}

function notif_kb_tab_label(){
    $lang = function_exists('hd_lang') ? hd_lang() : ($_GET['lang'] ?? 'en');
    if(in_array($lang, ['zh','cn','zh-cn'], true)) return '知识库';
    if(in_array($lang, ['ms','bm','my'], true)) return 'Pangkalan Pengetahuan';
    return 'Knowledge Base';
}

function notif_type_group($type){
    $type = strtolower((string)$type);
    if(strpos($type,'ticket') !== false) return 'ticket';
    if(strpos($type,'announcement') !== false) return 'announcement';
    if(strpos($type,'kb') !== false || strpos($type,'knowledge') !== false) return 'kb';
    return 'other';
}

if(isset($_GET['mark_all_read']))
{
    $stmt = $pdo->prepare("UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND is_read=0");
    $stmt->execute([$userId]);
    header("Location: notifications.php");
    exit;
}

if(isset($_GET['read']))
{
    $id = (int)$_GET['read'];
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id=? AND user_id=? LIMIT 1");
    $stmt->execute([$id, $userId]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);
    if($notification)
    {
        if(!empty($notification['ticket_id']) && function_exists('notification_fetch_ticket') && function_exists('notification_user_can_access_ticket')){
            $ticket = notification_fetch_ticket($pdo, (int)$notification['ticket_id']);
            if(!$ticket || !notification_user_can_access_ticket($pdo, $userId, $ticket)){
                header('Location: notifications.php');
                exit;
            }
        }
        $stmt = $pdo->prepare("UPDATE notifications SET is_read=1, read_at=NOW() WHERE id=? AND user_id=?");
        $stmt->execute([$id, $userId]);
        $url = trim((string)($notification['url'] ?? '')) ?: 'notifications.php';
        header("Location: ".$url);
        exit;
    }
}

$filter = strtolower(trim($_GET['filter'] ?? 'all'));
$allowed = ['all','unread','ticket','announcement','kb'];
if(!in_array($filter, $allowed, true)) $filter = 'all';

$allVisible = function_exists('notification_visible_rows')
    ? notification_visible_rows($pdo, $userId, 150, false)
    : [];

if(!function_exists('notification_visible_rows')){
    $stmt = $pdo->prepare("SELECT n.*, t.ticket_no FROM notifications n LEFT JOIN tickets t ON t.id=n.ticket_id WHERE n.user_id=? ORDER BY n.is_read ASC, n.created_at DESC, n.id DESC LIMIT 150");
    $stmt->execute([$userId]);
    $allVisible = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

foreach($allVisible as &$n){
    $n['ticket_no'] = '';
    if(!empty($n['ticket_id'])){
        try{
            $stmtTicketNo = $pdo->prepare('SELECT ticket_no FROM tickets WHERE id=? LIMIT 1');
            $stmtTicketNo->execute([(int)$n['ticket_id']]);
            $n['ticket_no'] = (string)$stmtTicketNo->fetchColumn();
        }catch(Exception $e){}
    }
}
unset($n);

$cntAll = count($allVisible);
$cntUnread = count(array_filter($allVisible, fn($n) => (int)($n['is_read'] ?? 0) === 0));
$cntTicket = count(array_filter($allVisible, fn($n) => notif_type_group($n['type'] ?? '') === 'ticket'));
$cntAnnouncement = count(array_filter($allVisible, fn($n) => notif_type_group($n['type'] ?? '') === 'announcement'));
$cntKb = count(array_filter($allVisible, fn($n) => notif_type_group($n['type'] ?? '') === 'kb'));

$notifications = array_values(array_filter($allVisible, function($n) use ($filter){
    if($filter === 'all') return true;
    if($filter === 'unread') return (int)($n['is_read'] ?? 0) === 0;
    return notif_type_group($n['type'] ?? '') === $filter;
}));
?>
<style>
.notif2-wrap{max-width:1180px;margin:0 auto}.notif2-head{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:16px}.notif2-title{display:flex;align-items:center;gap:12px}.notif2-title-icon{width:48px;height:48px;border-radius:16px;background:#eaf2ff;color:#1266f1;display:flex;align-items:center;justify-content:center;font-size:24px}.notif2-title h2{font-size:28px;font-weight:950;margin:0;color:#07142f}.notif2-sub{color:#64748b}.notif2-card{background:#fff;border:1px solid #e5edf7;border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.055);overflow:hidden}.notif2-tabs{display:flex;gap:4px;padding:14px 16px 0;border-bottom:1px solid #edf2f7;overflow:auto}.notif2-tabs a{padding:11px 16px 12px;text-decoration:none;color:#334155;font-weight:850;border-bottom:3px solid transparent;white-space:nowrap}.notif2-tabs a.active{color:#1266f1;border-color:#1266f1}.notif2-actions{display:flex;gap:10px;flex-wrap:wrap}.notif2-list{display:flex;flex-direction:column}.notif2-row{position:relative;display:flex;gap:14px;padding:17px 18px;border-bottom:1px solid #edf2f7;text-decoration:none;color:#0f172a}.notif2-row:hover{background:#f8fbff;color:#0f172a}.notif2-row.unread{background:#eff6ff}.notif2-icon{width:52px;height:52px;border-radius:16px;background:#dbeafe;color:#1266f1;display:flex;align-items:center;justify-content:center;font-size:23px;flex:0 0 auto}.notif2-row[data-group="ticket"] .notif2-icon{background:#dbeafe;color:#1266f1}.notif2-row[data-group="announcement"] .notif2-icon{background:#f3e8ff;color:#7c3aed}.notif2-row[data-group="kb"] .notif2-icon{background:#cffafe;color:#0891b2}.notif2-name{font-weight:950;font-size:16px;line-height:1.25}.notif2-msg{font-size:14px;color:#475569;white-space:pre-line;margin-top:5px;line-height:1.35}.notif2-meta{font-size:12px;color:#64748b;margin-top:7px}.notif2-pill{font-size:11px;border-radius:999px;padding:4px 8px;font-weight:900}.notif2-pill.unread{background:#fee2e2;color:#dc2626}.notif2-pill.read{background:#e5e7eb;color:#475569}.notif2-dot{position:absolute;right:18px;top:22px;width:10px;height:10px;border-radius:999px;background:#ef4444}.empty-state{text-align:center;color:#64748b;padding:50px}@media(max-width:768px){.notif2-wrap{padding-bottom:76px}.notif2-title h2{font-size:23px}.notif2-title-icon{width:42px;height:42px}.notif2-actions{display:grid;grid-template-columns:1fr 1fr;width:100%}.notif2-actions .btn{width:100%}.notif2-tabs{padding:10px 10px 0;gap:2px}.notif2-tabs a{font-size:13px;padding:10px 10px;flex:0 0 auto}.notif2-row{padding:14px 12px}.notif2-icon{width:42px;height:42px;border-radius:14px;font-size:20px}.notif2-name{font-size:14px;padding-right:18px}.notif2-msg{font-size:12px}.notif2-meta{font-size:11px}.notif2-pill{font-size:10px}.notif2-dot{right:12px;top:19px}}
</style>
<div class="notif2-wrap">
    <div class="notif2-head">
        <div class="notif2-title"><div class="notif2-title-icon"><i class="bi bi-bell-fill"></i></div><div><h2><?= h(__('Notifications')) ?></h2><div class="notif2-sub"><?= h(__('System notifications linked with tickets, announcements, knowledge base and status changes.')) ?></div></div></div>
        <div class="notif2-actions">
            <?php if($cntUnread>0): ?><a href="notifications.php?mark_all_read=1<?= hd_lang() ? '&lang='.h(hd_lang()) : '' ?>" class="btn btn-outline-primary"><i class="bi bi-check2-all me-1"></i><?= h(__('Mark All Read')) ?></a><?php endif; ?>
            <a href="ticket_list.php?lang=<?= h(hd_lang()) ?>" class="btn btn-primary"><i class="bi bi-list-task me-1"></i><?= h(__('Ticket List')) ?></a>
        </div>
    </div>
    <div class="notif2-card">
        <div class="notif2-tabs">
            <a class="<?= $filter==='all'?'active':'' ?>" href="notifications.php?lang=<?= h(hd_lang()) ?>"><?= h(__('All')) ?> (<?= $cntAll ?>)</a>
            <a class="<?= $filter==='unread'?'active':'' ?>" href="notifications.php?filter=unread&lang=<?= h(hd_lang()) ?>"><?= h(__('Unread')) ?> (<?= $cntUnread ?>)</a>
            <a class="<?= $filter==='ticket'?'active':'' ?>" href="notifications.php?filter=ticket&lang=<?= h(hd_lang()) ?>"><?= h(__('Ticket')) ?> (<?= $cntTicket ?>)</a>
            <a class="<?= $filter==='announcement'?'active':'' ?>" href="notifications.php?filter=announcement&lang=<?= h(hd_lang()) ?>"><?= h(__('Announcement')) ?> (<?= $cntAnnouncement ?>)</a>
            <a class="<?= $filter==='kb'?'active':'' ?>" href="notifications.php?filter=kb&lang=<?= h(hd_lang()) ?>"><?= h(notif_kb_tab_label()) ?> (<?= $cntKb ?>)</a>
        </div>
        <div class="notif2-list">
            <?php foreach($notifications as $n): $grp=notif_type_group($n['type']??''); ?>
            <a class="notif2-row <?= ((int)$n['is_read']===0)?'unread':'' ?>" data-group="<?= h($grp) ?>" href="notifications.php?read=<?= (int)$n['id'] ?>&lang=<?= h(hd_lang()) ?>">
                <div class="notif2-icon"><i class="bi <?= h(notif_icon_class($n['type']??'')) ?>"></i></div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between gap-2 align-items-start">
                        <div class="notif2-name"><?= h(function_exists('notification_i18n_text') ? notification_i18n_text($n['title']) : $n['title']) ?></div>
                        <span class="notif2-pill <?= ((int)$n['is_read']===0)?'unread':'read' ?>"><?= h(__(((int)$n['is_read']===0)?'UNREAD':'READ')) ?></span>
                    </div>
                    <?php if(!empty($n['message'])): ?><div class="notif2-msg"><?= h(function_exists('notification_i18n_text') ? notification_i18n_text($n['message']) : $n['message']) ?></div><?php endif; ?>
                    <div class="notif2-meta"><?= h($n['created_at']) ?><?php if(!empty($n['ticket_no'])): ?> · <?= h($n['ticket_no']) ?><?php endif; ?></div>
                </div>
                <?php if((int)$n['is_read']===0): ?><span class="notif2-dot"></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
            <?php if(!$notifications): ?><div class="empty-state"><i class="bi bi-bell-slash fs-1 d-block mb-2"></i><?= h(__('No notifications found.')) ?></div><?php endif; ?>
        </div>
    </div>
</div>
<?php require 'footer.php'; ?>
