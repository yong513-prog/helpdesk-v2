<?php
require 'header.php';
require 'db.php';
require_once 'announcement_content_translate.php';
require_once 'module_permissions.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'] ?? '';
$canManageAnnouncement = has_action_permission('manage_announcement');

try {
    $pdo->query("SELECT branch FROM announcement_reads LIMIT 1");
} catch(Exception $e) {
    try { $pdo->exec("ALTER TABLE announcement_reads ADD COLUMN branch VARCHAR(100) NULL AFTER user_id"); } catch(Exception $ignore) {}
}

$isAdmin = $canManageAnnouncement;
hd_ensure_announcement_translation_columns($pdo);
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

$keyword = trim($_GET['keyword'] ?? '');
$show = $_GET['show'] ?? 'active';
$branchFilter = trim($_GET['branch'] ?? '');
if(!in_array($show, ['active','expired','all'], true)) { $show = 'active'; }
$activeAnnouncementSql = "(a.start_date IS NULL OR a.start_date <= CURDATE()) AND (a.end_date IS NULL OR a.end_date >= CURDATE())";
$expiredAnnouncementSql = "a.end_date IS NOT NULL AND a.end_date < CURDATE()";

// Avoid error if SQL not installed yet.
try {
    $pdo->query("SELECT 1 FROM announcement_reads LIMIT 1");
} catch(Exception $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `announcement_reads` (
        `id` int NOT NULL AUTO_INCREMENT,
        `announcement_id` int NOT NULL,
        `user_id` int NOT NULL,
        `branch` varchar(100) DEFAULT NULL,
        `read_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_announcement_user` (`announcement_id`,`user_id`),
        KEY `idx_announcement_reads_user` (`user_id`),
        KEY `idx_announcement_reads_announcement` (`announcement_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$where = " WHERE 1=1 ";
$params = [];

if($keyword !== '') {
    $where .= " AND (a.title LIKE ? OR a.content LIKE ?) ";
    $params[] = "%".$keyword."%";
    $params[] = "%".$keyword."%";
}

if($show === 'active') {
    $where .= " AND $activeAnnouncementSql ";
}
elseif($show === 'expired') {
    $where .= " AND $expiredAnnouncementSql ";
}

$sql = "
SELECT
    a.*,
    COALESCE(u.username, '-') AS created_by_name,
    ar.read_at AS my_read_at,
    IF(ar.id IS NULL, 0, 1) AS is_read,
    (SELECT COUNT(*) FROM users uu WHERE uu.status = 'active' AND uu.role <> 'admin') AS total_target_users,
    (SELECT COUNT(*) FROM announcement_reads rr WHERE rr.announcement_id = a.id) AS total_read_users
FROM announcements a
LEFT JOIN users u ON u.id = a.created_by
LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.user_id = ?
" . $where . "
ORDER BY a.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$currentUserId], $params));
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAnnouncements = count($announcements);
$myUnread = 0;
foreach($announcements as $a) {
    if((int)$a['is_read'] === 0) { $myUnread++; }
}

$totalActive = (int)$pdo->query("SELECT COUNT(*) FROM announcements a WHERE $activeAnnouncementSql")->fetchColumn();
$totalExpired = (int)$pdo->query("SELECT COUNT(*) FROM announcements a WHERE $expiredAnnouncementSql")->fetchColumn();
$totalBranches = (int)$pdo->query("SELECT COUNT(DISTINCT branch) FROM users WHERE status='active' AND role <> 'admin' AND branch IS NOT NULL AND branch <> ''")->fetchColumn();
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='active' AND role <> 'admin'")->fetchColumn();
$totalReadRows = 0;
if($totalActive > 0) {
    $totalReadRows = (int)$pdo->query("SELECT COUNT(*) FROM announcement_reads ar INNER JOIN announcements a ON a.id = ar.announcement_id WHERE $activeAnnouncementSql")->fetchColumn();
}
$totalPossibleReads = $totalActive * max($totalUsers, 0);
$readRate = $totalPossibleReads > 0 ? round(($totalReadRows / $totalPossibleReads) * 100, 1) : 0;
?>

<style>
.ann-page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:26px;padding-top:4px;}
.ann-title-wrap{display:flex;gap:14px;align-items:center;}
.ann-icon{width:54px;height:54px;border-radius:18px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#eef2ff,#ffffff);color:#4f46e5;font-size:28px;box-shadow:0 12px 28px rgba(79,70,229,.12);}
.ann-title{font-size:30px;font-weight:800;margin:0;color:#0f172a;}
.ann-subtitle{color:#64748b;margin-top:4px;}
.ann-toolbar{background:#fff;border:1px solid #e8eef8;border-radius:16px;padding:14px;box-shadow:0 10px 28px rgba(15,23,42,.04);margin-bottom:22px;}
.ann-stat{background:#fff;border:1px solid #e8eef8;border-radius:18px;padding:22px;box-shadow:0 12px 28px rgba(15,23,42,.06);height:100%;display:flex;align-items:center;gap:17px;}
.ann-stat-icon{width:54px;height:54px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:25px;color:#fff;flex:0 0 54px;}
.ann-stat-icon.purple{background:linear-gradient(135deg,#6366f1,#4f46e5);} .ann-stat-icon.green{background:linear-gradient(135deg,#22c55e,#16a34a);} .ann-stat-icon.blue{background:linear-gradient(135deg,#3b82f6,#2563eb);} .ann-stat-icon.orange{background:linear-gradient(135deg,#fb923c,#f97316);}
.ann-stat-number{font-size:26px;line-height:1;font-weight:850;color:#0f172a;}
.ann-stat-label{font-weight:700;margin-top:7px;color:#1e293b;}.ann-stat-note{font-size:13px;color:#64748b;margin-top:3px;}
.ann-table-card{background:#fff;border:1px solid #e8eef8;border-radius:18px;box-shadow:0 14px 32px rgba(15,23,42,.06);overflow:hidden;}
.ann-row{display:grid;grid-template-columns:minmax(420px,1fr) 150px 120px 150px 150px;gap:18px;align-items:center;padding:26px 26px;border-top:1px solid #eef2f7;}
.ann-row.header{padding:17px 26px;border-top:0;background:#fbfdff;font-weight:800;color:#334155;}
.ann-dot{width:13px;height:13px;border-radius:50%;display:inline-block;margin-right:14px;background:#a3aab8;}.ann-dot.unread{background:#4f46e5;box-shadow:0 0 0 5px rgba(79,70,229,.10);}
.ann-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:999px;font-size:12px;font-weight:800;}.ann-badge.read{background:#e2e8f0;color:#475569;}.ann-badge.unread{background:#ede9fe;color:#4f46e5;}.ann-badge.active{background:#dcfce7;color:#15803d;}.ann-badge.inactive{background:#fee2e2;color:#991b1b;}
.ann-item-title{font-size:18px;font-weight:850;margin:7px 0 6px;color:#0f172a;}.ann-item-title a{color:#0f172a;text-decoration:none}.ann-item-title a:hover{color:#2563eb;text-decoration:underline}.ann-content{max-height:48px;overflow:hidden;}.ann-meta{color:#64748b;font-size:13px;}.ann-content{color:#334155;margin:15px 0 13px;line-height:1.6;}.ann-time{display:flex;gap:22px;flex-wrap:wrap;color:#334155;font-weight:650;font-size:14px;}.ann-read-num{font-size:18px;font-weight:850;color:#2563eb;}.ann-read-muted{font-size:13px;color:#64748b;}.ann-actions{display:flex;flex-direction:column;gap:10px;}.progress-thin{height:5px;background:#e5e7eb;border-radius:99px;overflow:hidden;margin-top:10px;}.progress-thin span{height:100%;display:block;background:linear-gradient(90deg,#f59e0b,#f97316);}
@media(max-width:1100px){.ann-row{grid-template-columns:1fr;}.ann-row.header{display:none;}.ann-actions{flex-direction:row;}.ann-page-head{flex-direction:column;}.ann-page-head .d-flex{width:100%;}}
</style>

<div class="ann-page-head">
    <div class="ann-title-wrap">
        <div class="ann-icon"><i class="bi bi-megaphone-fill"></i></div>
        <div>
            <h2 class="ann-title"><?= __('Announcements') ?></h2>
            <div class="ann-subtitle"><?= __('Company notices and internal updates') ?></div>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if($isAdmin): ?>
            <a href="announcement_read_report.php" class="btn btn-outline-primary px-4"><i class="bi bi-people me-2"></i><?= __('Read Status') ?></a>
            <a href="add_announcement.php" class="btn btn-primary px-4"><i class="bi bi-plus-circle me-2"></i><?= __('Add Announcement') ?></a>
        <?php endif; ?>
    </div>
</div>

<form method="get" class="ann-toolbar">
    <div class="row g-3 align-items-center">
        <div class="col-lg-6"><input type="text" name="keyword" value="<?= htmlspecialchars($keyword); ?>" class="form-control form-control-lg" placeholder="<?= __('Search announcement...') ?>"></div>
        <div class="col-lg-2"><select name="show" class="form-select form-select-lg"><option value="active" <?= $show==='active'?'selected':''; ?>><?= __('Active Only') ?></option><option value="expired" <?= $show==='expired'?'selected':''; ?>><?= __('Expired Only') ?></option><option value="all" <?= $show==='all'?'selected':''; ?>><?= __('All') ?></option></select></div>
        <div class="col-lg-2"><select name="branch" class="form-select form-select-lg"><option value=""><?= __('All Branches') ?></option></select></div>
        <div class="col-lg-2"><button type="submit" class="btn btn-dark btn-lg w-100"><i class="bi bi-search me-2"></i><?= __('Search') ?></button></div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3"><div class="ann-stat"><div class="ann-stat-icon purple"><i class="bi bi-megaphone-fill"></i></div><div><div class="ann-stat-number"><?= $totalActive; ?></div><div class="ann-stat-label"><?= __('Total Announcements') ?></div><div class="ann-stat-note"><?= __('Active announcements') ?> · <?= $totalExpired; ?> <?= __('Expired') ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="ann-stat"><div class="ann-stat-icon green"><i class="bi bi-check-lg"></i></div><div><div class="ann-stat-number"><?= $myUnread; ?></div><div class="ann-stat-label"><?= __('Unread (You)') ?></div><div class="ann-stat-note"><?= __('Announcement') ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="ann-stat"><div class="ann-stat-icon blue"><i class="bi bi-people-fill"></i></div><div><div class="ann-stat-number"><?= $totalBranches; ?></div><div class="ann-stat-label"><?= __('Branches') ?></div><div class="ann-stat-note"><?= __('Total branches') ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="ann-stat"><div class="ann-stat-icon orange"><i class="bi bi-people-fill"></i></div><div><div class="ann-stat-number"><?= $totalReadRows; ?> / <?= $totalPossibleReads; ?></div><div class="ann-stat-label"><?= __('Read / Total') ?></div><div class="ann-stat-note"><?= $readRate; ?>% <?= __('read rate') ?></div><div class="progress-thin"><span style="width:<?= min(100,$readRate); ?>%"></span></div></div></div></div>
</div>

<div class="ann-table-card">
    <div class="ann-row header"><div><?= __('Announcement') ?></div><div><?= __('Target') ?></div><div><?= __('Status') ?></div><div><?= __('Read Info') ?></div><div><?= __('Action') ?></div></div>
    <?php foreach($announcements as $row):
        $isRead = ((int)$row['is_read'] === 1);
        $isExpired = (!empty($row['end_date']) && $row['end_date'] < date('Y-m-d'));
        $isActive = (!$isExpired && (empty($row['start_date']) || $row['start_date'] <= date('Y-m-d')) && (empty($row['end_date']) || $row['end_date'] >= date('Y-m-d')));
        $readTotal = (int)$row['total_read_users'];
        $targetTotal = (int)$row['total_target_users'];
        $percent = $targetTotal > 0 ? round(($readTotal / $targetTotal) * 100, 1) : 0;
    ?>
    <div class="ann-row">
        <div>
            <div><span class="ann-dot <?= !$isRead?'unread':''; ?>"></span><span class="ann-badge <?= $isRead?'read':'unread'; ?>"><?= __($isRead?'Read':'Unread'); ?></span></div>
            <div class="ann-item-title hd-no-translate"><a href="view_announcement.php?id=<?= (int)$row['id']; ?>"><?= htmlspecialchars(hd_announcement_title($pdo, $row)); ?></a></div>
            <div class="ann-meta"><span class="ann-badge <?= $isActive?'active':'inactive'; ?>"><?= __($isExpired ? 'Expired' : ($isActive?'Active':'Inactive')); ?></span> &nbsp; <?= __('Posted by') ?> <b><?= htmlspecialchars($row['created_by_name']); ?></b> &nbsp;·&nbsp; <?= htmlspecialchars($row['created_at']); ?></div>
            <div class="ann-content"><span class="hd-no-translate"><?= nl2br(htmlspecialchars(mb_substr(hd_announcement_content($pdo, $row), 0, 120))); ?><?= mb_strlen(hd_announcement_content($pdo, $row)) > 120 ? '...' : ''; ?></span></div>
            <?php if(!empty($row['attachment_path'])): ?><div class="ann-meta"><i class="bi bi-paperclip me-1"></i><?= __('Attachment') ?>: <?= htmlspecialchars($row['attachment_name'] ?? basename($row['attachment_path'])); ?></div><?php endif; ?>
            <div class="ann-time">
                <span><i class="bi bi-calendar-event me-2"></i><?= !empty($row['start_date']) ? htmlspecialchars($row['start_date']) : '-'; ?> to <?= !empty($row['end_date']) ? htmlspecialchars($row['end_date']) : '-'; ?></span>
                <?php if(!empty($row['my_read_at'])): ?><span><i class="bi bi-check2-circle me-2"></i><?= __('Read on') ?> <?= htmlspecialchars($row['my_read_at']); ?></span><?php endif; ?>
            </div>
        </div>
        <div><i class="bi bi-building me-2"></i><?= __('All Branches') ?></div>
        <div><span class="ann-badge <?= $isActive?'active':'inactive'; ?>"><?= __($isExpired ? 'Expired' : ($isActive?'Active':'Inactive')); ?></span></div>
        <div><div class="ann-read-num"><?= $readTotal; ?> / <?= $targetTotal; ?></div><div class="ann-read-muted"><?= $percent; ?>%</div><?php if($isAdmin): ?><a href="announcement_read_report.php?announcement_id=<?= (int)$row['id']; ?>" class="small text-decoration-none"><?= __('View Details') ?></a><?php endif; ?></div>
        <div class="ann-actions">
            <a href="view_announcement.php?id=<?= (int)$row['id']; ?>" class="btn btn-sm <?= !$isRead ? 'btn-primary' : 'btn-outline-primary'; ?> w-100"><i class="bi bi-eye me-1"></i><?= __('View') ?></a>
            <?php if($isAdmin): ?><a href="delete_announcement.php?id=<?= (int)$row['id']; ?>" class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('<?= __('Delete this announcement?') ?>');"><i class="bi bi-trash me-1"></i><?= __('Delete') ?></a><?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if(count($announcements) == 0): ?><div class="p-4"><div class="alert alert-info mb-0"><?= __('No announcements found.') ?></div></div><?php endif; ?>
</div>

<?php require 'footer.php'; ?>
