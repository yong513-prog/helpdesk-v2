<?php

session_start();

require 'db.php';
require_once 'audit_log.php';
require_once 'access_control.php';
require_once 'module_permissions.php';

require_action_permission('manage_user');

$id = (int)($_GET['id'] ?? 0);

if($id <= 0)
{
    die('Invalid user ID');
}

if($id === (int)($_SESSION['user_id'] ?? 0))
{
    die('You cannot delete your own account.');
}

$stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$user)
{
    die('User not found');
}

// Prevent deleting the last admin account.
if(normalize_role($user['role'] ?? '') === 'admin')
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'");
    $activeAdminCount = (int)$stmt->fetchColumn();

    if($activeAdminCount <= 1)
    {
        die('You cannot delete the last active admin account.');
    }
}

$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$id]);

audit_log($pdo, 'Delete User', 'Deleted user '.$user['username']);

header('Location: users.php?return_scroll=1');
exit;

?>
