<?php

require 'header.php';
require 'db.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'] ?? '';
$isAdmin = ($role === 'admin');

$keyword = trim($_GET['keyword'] ?? '');
$show = $_GET['show'] ?? 'active';

$sql = "
SELECT
    a.*,
    COALESCE(u.username, '-') AS created_by_name
FROM announcements a
LEFT JOIN users u ON u.id = a.created_by
WHERE 1=1
";

$params = [];

if($keyword !== '')
{
    $sql .= " AND (a.title LIKE ? OR a.content LIKE ?) ";
    $params[] = "%".$keyword."%";
    $params[] = "%".$keyword."%";
}

if($show === 'active')
{
    $sql .= "
    AND (a.start_date IS NULL OR a.start_date <= CURDATE())
    AND (a.end_date IS NULL OR a.end_date >= CURDATE())
    ";
}

$sql .= " ORDER BY a.id DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
.announcement-box{
    border:1px solid #e5e7eb;
    border-radius:18px;
    background:#fff;
    box-shadow:0 10px 24px rgba(15,23,42,.06);
    margin-bottom:16px;
}
.announcement-box-header{
    padding:16px 18px;
    border-bottom:1px solid #eef2f7;
    display:flex;
    justify-content:space-between;
    gap:12px;
    align-items:flex-start;
}
.announcement-box-body{
    padding:18px;
    white-space:pre-line;
}
.announcement-meta{
    color:#64748b;
    font-size:13px;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">📢 Announcements</h2>
        <div class="text-muted">Company notices and internal updates</div>
    </div>

    <?php if($isAdmin): ?>
    <a href="add_announcement.php" class="btn btn-primary">Add Announcement</a>
    <?php endif; ?>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-6">
        <input type="text" name="keyword" value="<?= htmlspecialchars($keyword); ?>" class="form-control" placeholder="Search announcement...">
    </div>
    <div class="col-md-3">
        <select name="show" class="form-select">
            <option value="active" <?= $show === 'active' ? 'selected' : ''; ?>>Active Only</option>
            <option value="all" <?= $show === 'all' ? 'selected' : ''; ?>>All</option>
        </select>
    </div>
    <div class="col-md-3">
        <button type="submit" class="btn btn-dark w-100">Search</button>
    </div>
</form>

<?php foreach($announcements as $row): ?>
<div class="announcement-box">
    <div class="announcement-box-header">
        <div>
            <h5 class="mb-1"><?= htmlspecialchars($row['title']); ?></h5>
            <div class="announcement-meta">
                Active:
                <?= !empty($row['start_date']) ? htmlspecialchars($row['start_date']) : '-'; ?>
                to
                <?= !empty($row['end_date']) ? htmlspecialchars($row['end_date']) : '-'; ?>
                · Posted by <?= htmlspecialchars($row['created_by_name']); ?>
                · <?= htmlspecialchars($row['created_at']); ?>
            </div>
        </div>

        <?php if($isAdmin): ?>
        <a href="delete_announcement.php?id=<?= (int)$row['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this announcement?');">Delete</a>
        <?php endif; ?>
    </div>

    <div class="announcement-box-body">
        <?= nl2br(htmlspecialchars($row['content'])); ?>
    </div>
</div>
<?php endforeach; ?>

<?php if(count($announcements) == 0): ?>
<div class="alert alert-info">No announcements found.</div>
<?php endif; ?>

<?php require 'footer.php'; ?>
