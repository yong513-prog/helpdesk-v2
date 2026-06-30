<?php
if(!function_exists('h')){ function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); } }
require 'header.php';
require 'db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'module_permissions.php';
require_action_permission('manage_announcement');

try {
    $pdo->query("SELECT branch FROM announcement_reads LIMIT 1");
} catch(Exception $e) {
    try { $pdo->exec("ALTER TABLE announcement_reads ADD COLUMN branch VARCHAR(100) NULL AFTER user_id"); } catch(Exception $ignore) {}
}


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

$announcement_id = (int)($_GET['announcement_id'] ?? 0);
$announcementList = $pdo->query("SELECT id, title, created_at FROM announcements ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
if($announcement_id <= 0 && count($announcementList) > 0) { $announcement_id = (int)$announcementList[0]['id']; }

$selectedAnn = null; $branchRows = []; $userRows = [];
$totalUsers = 0; $totalRead = 0; $totalUnread = 0; $readPercent = 0;

if($announcement_id > 0) {
    $stmtAnn = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmtAnn->execute([$announcement_id]);
    $selectedAnn = $stmtAnn->fetch(PDO::FETCH_ASSOC);

    $stmtBranch = $pdo->prepare("SELECT COALESCE(NULLIF(u.branch,''), '-') AS branch,
            COUNT(u.id) AS total_users,
            SUM(CASE WHEN ar.id IS NULL THEN 0 ELSE 1 END) AS read_users,
            COUNT(u.id) - SUM(CASE WHEN ar.id IS NULL THEN 0 ELSE 1 END) AS unread_users
        FROM users u
        LEFT JOIN announcement_reads ar ON ar.user_id = u.id AND ar.announcement_id = ?
        WHERE u.status = 'active' AND u.role <> 'admin'
        GROUP BY COALESCE(NULLIF(u.branch,''), '-')
        ORDER BY branch ASC");
    $stmtBranch->execute([$announcement_id]);
    $branchRows = $stmtBranch->fetchAll(PDO::FETCH_ASSOC);

    $stmtUsers = $pdo->prepare("SELECT u.id, u.username, u.full_name, u.role, COALESCE(NULLIF(u.branch,''), '-') AS branch, ar.read_at
        FROM users u
        LEFT JOIN announcement_reads ar ON ar.user_id = u.id AND ar.announcement_id = ?
        WHERE u.status = 'active' AND u.role <> 'admin'
        ORDER BY branch ASC, u.username ASC");
    $stmtUsers->execute([$announcement_id]);
    $userRows = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    foreach($branchRows as $r) { $totalUsers += (int)$r['total_users']; $totalRead += (int)$r['read_users']; }
    $totalUnread = $totalUsers - $totalRead;
    $readPercent = $totalUsers > 0 ? round(($totalRead / $totalUsers) * 100, 1) : 0;
}
?>
<style>
.rpt-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:24px;}.rpt-title{font-size:30px;font-weight:850;margin:0;color:#0f172a}.rpt-sub{color:#64748b;margin-top:5px}.rpt-card{background:#fff;border:1px solid #e8eef8;border-radius:18px;padding:22px;box-shadow:0 12px 28px rgba(15,23,42,.06);}.rpt-stat{display:flex;align-items:center;gap:16px;height:100%;}.rpt-ico{width:54px;height:54px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:25px}.rpt-ico.blue{background:linear-gradient(135deg,#3b82f6,#2563eb)}.rpt-ico.green{background:linear-gradient(135deg,#22c55e,#16a34a)}.rpt-ico.orange{background:linear-gradient(135deg,#fb923c,#f97316)}.rpt-ico.red{background:linear-gradient(135deg,#ef4444,#dc2626)}.rpt-num{font-size:27px;font-weight:850;color:#0f172a}.rpt-label{font-weight:750;color:#334155}.badge-read{background:#dcfce7;color:#166534;padding:5px 11px;border-radius:999px;font-size:12px;font-weight:800}.badge-unread{background:#fef3c7;color:#92400e;padding:5px 11px;border-radius:999px;font-size:12px;font-weight:800}.table thead th{background:#f8fafc;color:#334155;font-weight:800}.progress-thin{height:7px;background:#e5e7eb;border-radius:99px;overflow:hidden}.progress-thin span{display:block;height:100%;background:linear-gradient(90deg,#22c55e,#16a34a)}
</style>

<div class="rpt-head">
    <div><h2 class="rpt-title"><i class="bi bi-people-fill text-primary me-2"></i>Announcement Read Status</h2><div class="rpt-sub"><?= h(__('Check which branch / user has read company announcements.')) ?></div></div>
    <a href="announcements.php" class="btn btn-outline-secondary px-4"><i class="bi bi-arrow-left me-2"></i>Back</a>
</div>

<form method="get" class="rpt-card mb-4">
    <label class="form-label fw-bold"><?= h(__('Select Announcement')) ?></label>
    <select name="announcement_id" class="form-select form-select-lg" onchange="this.form.submit()">
        <?php foreach($announcementList as $ann): ?>
            <option value="<?= (int)$ann['id']; ?>" <?= ((int)$ann['id'] === $announcement_id) ? 'selected' : ''; ?>>#<?= (int)$ann['id']; ?> - <?= htmlspecialchars($ann['title']); ?> - <?= htmlspecialchars($ann['created_at']); ?></option>
        <?php endforeach; ?>
    </select>
</form>

<?php if(!$selectedAnn): ?>
<div class="alert alert-info"><?= h(__('No announcement found.')) ?></div>
<?php else: ?>
<div class="rpt-card mb-4"><h5 class="mb-1"><?= htmlspecialchars($selectedAnn['title']); ?></h5><div class="text-muted"><?= h(__('Created at')) ?>: <?= htmlspecialchars($selectedAnn['created_at']); ?></div><div class="mt-3"><?= nl2br(htmlspecialchars($selectedAnn['content'])); ?></div></div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3"><div class="rpt-card rpt-stat"><div class="rpt-ico blue"><i class="bi bi-people"></i></div><div><div class="rpt-num"><?= $totalUsers; ?></div><div class="rpt-label"><?= h(__('Total Users')) ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="rpt-card rpt-stat"><div class="rpt-ico green"><i class="bi bi-check-lg"></i></div><div><div class="rpt-num"><?= $totalRead; ?></div><div class="rpt-label"><?= h(__('Read')) ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="rpt-card rpt-stat"><div class="rpt-ico red"><i class="bi bi-exclamation-lg"></i></div><div><div class="rpt-num"><?= $totalUnread; ?></div><div class="rpt-label"><?= h(__('Unread')) ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="rpt-card rpt-stat"><div class="rpt-ico orange"><i class="bi bi-graph-up"></i></div><div><div class="rpt-num"><?= $readPercent; ?>%</div><div class="rpt-label"><?= h(__('Read Rate')) ?></div></div></div></div>
</div>

<div class="rpt-card mb-4">
    <h5 class="mb-3">Branch Summary</h5>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Branch</th><th><?= h(__('Total Users')) ?></th><th><?= h(__('Read')) ?></th><th><?= h(__('Unread')) ?></th><th style="width:220px">Read %</th></tr></thead><tbody>
    <?php foreach($branchRows as $r): $total=(int)$r['total_users']; $read=(int)$r['read_users']; $percent=$total>0?round(($read/$total)*100,1):0; ?>
        <tr><td class="fw-bold hd-no-translate notranslate" translate="no"><?= htmlspecialchars(function_exists('hd_branch_code_raw') ? hd_branch_code_raw($r['branch']) : $r['branch']); ?></td><td><?= $total; ?></td><td><span class="badge-read"><?= $read; ?></span></td><td><span class="badge-unread"><?= (int)$r['unread_users']; ?></span></td><td><div class="d-flex align-items-center gap-2"><div class="progress-thin flex-grow-1"><span style="width:<?= min(100,$percent); ?>%"></span></div><b><?= $percent; ?>%</b></div></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<div class="rpt-card">
    <h5 class="mb-3">User Details</h5>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Branch</th><th>Username</th><th>Full Name</th><th>Role</th><th>Status</th><th>Read At</th></tr></thead><tbody>
    <?php foreach($userRows as $u): ?>
        <tr><td class="fw-bold hd-no-translate notranslate" translate="no"><?= htmlspecialchars(function_exists('hd_branch_code_raw') ? hd_branch_code_raw($u['branch']) : $u['branch']); ?></td><td><?= htmlspecialchars($u['username']); ?></td><td><?= htmlspecialchars($u['full_name'] ?? '-'); ?></td><td><?= htmlspecialchars($u['role']); ?></td><td><?= !empty($u['read_at']) ? '<span class="badge-read">'.h(__('Read')).'</span>' : '<span class="badge-unread">'.h(__('Unread')).'</span>'; ?></td><td><?= !empty($u['read_at']) ? htmlspecialchars($u['read_at']) : '-'; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>
<?php require 'footer.php'; ?>
