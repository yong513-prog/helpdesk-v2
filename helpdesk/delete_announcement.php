<?php
session_start();
require 'db.php';
require_once 'audit_log.php';
require_once 'module_permissions.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

require_action_permission('manage_announcement');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if($id <= 0)
{
    die('Invalid announcement ID');
}

$stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$row)
{
    die('Announcement not found');
}

try
{
    $pdo->beginTransaction();
    try { $pdo->prepare("DELETE FROM announcement_reads WHERE announcement_id = ?")->execute([$id]); } catch(Exception $ignore) {}
    $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
    $pdo->commit();

    if(!empty($row['attachment_path']))
    {
        $path = ltrim(str_replace('\\','/',$row['attachment_path']), '/');
        if(strpos($path, 'uploads/') === 0)
        {
            $full = __DIR__ . '/' . $path;
            if(is_file($full)) @unlink($full);
        }
    }

    if(function_exists('audit_log'))
    {
        audit_log($pdo, 'Delete Announcement', 'Deleted announcement: '.$row['title']);
    }
}
catch(Exception $e)
{
    if($pdo->inTransaction()) $pdo->rollBack();
    die('Delete failed: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

header("Location: announcements.php");
exit;
?>
