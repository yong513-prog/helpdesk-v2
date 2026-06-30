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

require_action_permission('manage_kb');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if($id <= 0)
{
    die('Invalid article ID');
}

$stmt = $pdo->prepare("SELECT * FROM knowledge_base WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$article)
{
    die("Article not found");
}

$filesToDelete = [];

if(!empty($article['attachment']))
{
    $filesToDelete[] = $article['attachment'];
}

try
{
    $stmt = $pdo->prepare("SELECT file_path FROM knowledge_base_attachments WHERE article_id = ?");
    $stmt->execute([$id]);
    foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $path)
    {
        if($path) $filesToDelete[] = $path;
    }
}
catch(Exception $ignore) {}

try
{
    $pdo->beginTransaction();

    try
    {
        $pdo->prepare("DELETE FROM knowledge_base_attachments WHERE article_id = ?")->execute([$id]);
    }
    catch(Exception $ignore) {}

    $pdo->prepare("DELETE FROM knowledge_base WHERE id = ?")->execute([$id]);

    $pdo->commit();

    foreach(array_unique($filesToDelete) as $path)
    {
        $path = ltrim(str_replace('\\','/',$path), '/');
        if(strpos($path, 'uploads/') === 0)
        {
            $full = __DIR__ . '/' . $path;
            if(is_file($full)) @unlink($full);
        }
    }

    audit_log($pdo, 'Delete Article', 'Deleted article '.$article['title']);
}
catch(Exception $e)
{
    if($pdo->inTransaction()) $pdo->rollBack();
    die('Delete failed: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

header("Location: knowledge_base.php");
exit;
?>
