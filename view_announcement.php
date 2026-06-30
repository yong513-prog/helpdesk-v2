<?php
require 'header.php';
require 'db.php';
require_once 'attachment_preview.php';
require_once 'announcement_content_translate.php';
require_once 'module_permissions.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

hd_ensure_announcement_translation_columns($pdo);
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? '';
$isAdmin = (($role === 'admin') || (function_exists('has_action_permission') && has_action_permission('manage_announcement')));
$announcementId = (int)($_GET['id'] ?? 0);

try {
    $pdo->query("SELECT 1 FROM announcement_reads LIMIT 1");
} catch(Exception $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `announcement_reads` (
        `id` int NOT NULL AUTO_INCREMENT,
        `announcement_id` int NOT NULL,
        `user_id` int NOT NULL,
        `read_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_announcement_user` (`announcement_id`,`user_id`),
        KEY `idx_announcement_reads_user` (`user_id`),
        KEY `idx_announcement_reads_announcement` (`announcement_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

if($announcementId <= 0) {
    echo '<div class="alert alert-danger">' . __('Invalid announcement.') . '</div>';
    require 'footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT a.*, COALESCE(u.username, '-') AS created_by_name
                       FROM announcements a
                       LEFT JOIN users u ON u.id = a.created_by
                       WHERE a.id = ?");
$stmt->execute([$announcementId]);
$announcement = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$announcement) {
    echo '<div class="alert alert-warning">' . __('Announcement not found.') . '</div>';
    require 'footer.php';
    exit;
}

// Auto mark as read when user opens this announcement.
$readStmt = $pdo->prepare("INSERT INTO announcement_reads (announcement_id, user_id, read_at)
                           VALUES (?, ?, NOW())
                           ON DUPLICATE KEY UPDATE read_at = read_at");
$readStmt->execute([$announcementId, $currentUserId]);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM announcement_reads WHERE announcement_id = ?");
$countStmt->execute([$announcementId]);
$totalRead = (int)$countStmt->fetchColumn();
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='active' AND role <> 'admin'")->fetchColumn();
$percent = $totalUsers > 0 ? round(($totalRead / $totalUsers) * 100, 1) : 0;

$isActive = ((empty($announcement['start_date']) || $announcement['start_date'] <= date('Y-m-d')) && (empty($announcement['end_date']) || $announcement['end_date'] >= date('Y-m-d')));
?>
<style>
.view-wrap{max-width:1050px;margin:0 auto;}
.view-head{display:flex;justify-content:space-between;gap:15px;align-items:flex-start;margin-bottom:22px;}
.view-title{font-size:30px;font-weight:850;color:#0f172a;margin:0;}
.view-sub{color:#64748b;margin-top:6px;}
.view-card{background:#fff;border:1px solid #e8eef8;border-radius:20px;box-shadow:0 14px 32px rgba(15,23,42,.06);overflow:hidden;}
.view-banner{padding:26px 30px;background:linear-gradient(135deg,#f8faff,#ffffff);border-bottom:1px solid #eef2f7;}
.view-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:800;}
.view-badge.read{background:#dcfce7;color:#166534;}.view-badge.active{background:#dcfce7;color:#15803d;}.view-badge.inactive{background:#fee2e2;color:#991b1b;}
.view-content{padding:30px;font-size:16px;line-height:1.8;color:#1e293b;white-space:pre-wrap;}
.view-info{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;}
.info-box{background:#fff;border:1px solid #e8eef8;border-radius:16px;padding:17px;box-shadow:0 10px 24px rgba(15,23,42,.04);}
.info-label{font-size:12px;color:#64748b;font-weight:750;text-transform:uppercase;}.info-val{font-weight:850;color:#0f172a;margin-top:5px;}
@media(max-width:900px){.view-info{grid-template-columns:1fr 1fr}.view-head{flex-direction:column}}
</style>

<div class="view-wrap">
    <div class="view-head">
        <div>
            <h2 class="view-title"><i class="bi bi-megaphone-fill text-primary me-2"></i><span class="hd-no-translate"><?= htmlspecialchars(hd_announcement_title($pdo, $announcement)); ?></span></h2>
            <div class="view-sub"><?= __('Opened announcement will be automatically marked as read') ?>.</div>
        </div>
       <div class="d-flex gap-2 flex-wrap">

    <a href="announcements.php"
       class="btn btn-outline-secondary px-4">
        <i class="bi bi-arrow-left me-2"></i><?= __('Back') ?>
    </a>

    <?php if($isAdmin): ?>

        <a href="edit_announcement.php?id=<?= $announcementId; ?>"
           class="btn btn-warning px-4">
            <i class="bi bi-pencil-square me-2"></i>
            <?= __('Edit Announcement') ?>
        </a>

        <a href="announcement_read_report.php?announcement_id=<?= $announcementId; ?>"
           class="btn btn-outline-primary px-4">
            <i class="bi bi-people me-2"></i>
            <?= __('Read Status') ?>
        </a>

    <?php endif; ?>

</div>
    </div>

    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i>
        <div><?= __('This announcement has been marked as read automatically') ?>.</div>
    </div>

    <div class="view-info">
        <div class="info-box"><div class="info-label"><?= __('Status') ?></div><div class="info-val"><span class="view-badge <?= $isActive?'active':'inactive'; ?>"><?= __($isActive?'Active':'Inactive'); ?></span></div></div>
        <div class="info-box"><div class="info-label"><?= __('Posted By') ?></div><div class="info-val"><?= htmlspecialchars($announcement['created_by_name']); ?></div></div>
        <div class="info-box"><div class="info-label"><?= __('Date') ?></div><div class="info-val"><?= !empty($announcement['start_date']) ? htmlspecialchars($announcement['start_date']) : '-'; ?> to <?= !empty($announcement['end_date']) ? htmlspecialchars($announcement['end_date']) : '-'; ?></div></div>
        <div class="info-box"><div class="info-label"><?= __('Read Rate') ?></div><div class="info-val"><?= $totalRead; ?> / <?= $totalUsers; ?> (<?= $percent; ?>%)</div></div>
    </div>

    <div class="view-card">
        <div class="view-banner">
            <span class="view-badge read"><i class="bi bi-check2-circle"></i><?= __('Read') ?></span>
            <span class="view-badge <?= $isActive?'active':'inactive'; ?> ms-2"><?= __($isActive?'Active':'Inactive'); ?></span>
            <div class="text-muted mt-3"><?= __('Created at') ?>: <?= htmlspecialchars($announcement['created_at'] ?? '-'); ?></div>
        </div>
        <div class="view-content hd-no-translate"><?= nl2br(htmlspecialchars(hd_announcement_content($pdo, $announcement))); ?></div>

        <?php if(!empty($announcement['attachment_path'])): ?>
        <div class="px-4 pb-4">
            <?= hd_ap_render($announcement['attachment_path'], $announcement['attachment_name'] ?? basename($announcement['attachment_path']), __('Announcement Attachment')); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?= hd_ap_assets(); ?>
<?php require 'footer.php'; ?>
