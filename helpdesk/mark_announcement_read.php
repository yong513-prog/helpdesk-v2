<?php
require 'db.php';

if(session_status() === PHP_SESSION_NONE)
{
    session_start();
}

if(!isset($_SESSION['user_id']))
{
    header('Location: login.php');
    exit;
}

try
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `announcement_reads` (
        `id` int NOT NULL AUTO_INCREMENT,
        `announcement_id` int NOT NULL,
        `user_id` int NOT NULL,
        `branch` varchar(100) DEFAULT NULL,
        `read_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_announcement_user` (`announcement_id`,`user_id`),
        KEY `idx_announcement_reads_user` (`user_id`),
        KEY `idx_announcement_reads_announcement` (`announcement_id`),
        KEY `idx_announcement_reads_branch` (`branch`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try { $pdo->query("SELECT branch FROM announcement_reads LIMIT 1"); }
    catch(Exception $e) { $pdo->exec("ALTER TABLE announcement_reads ADD COLUMN branch VARCHAR(100) NULL AFTER user_id"); }
}
catch(Exception $e)
{
    // Do not expose DB details to normal users.
}

$announcementId = (int)($_POST['announcement_id'] ?? $_GET['announcement_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];
$branch = $_SESSION['branch'] ?? null;

if($announcementId > 0)
{
    $stmt = $pdo->prepare("
        INSERT INTO announcement_reads (announcement_id, user_id, branch, read_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            branch = VALUES(branch),
            read_at = VALUES(read_at)
    ");
    $stmt->execute([$announcementId, $userId, $branch]);
}

$return = $_SERVER['HTTP_REFERER'] ?? 'announcements.php';

if(stripos($return, "\r") !== false || stripos($return, "\n") !== false)
{
    $return = 'announcements.php';
}

header('Location: ' . $return);
exit;
?>
