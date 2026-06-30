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
    die('You cannot disable your own account.');
}

$stmt = $pdo->prepare("SELECT username, role, status FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$user)
{
    die('User not found');
}

$newStatus = (($user['status'] ?? 'active') === 'active') ? 'inactive' : 'active';

// Prevent disabling the last active admin account.
if($newStatus !== 'active' && normalize_role($user['role'] ?? '') === 'admin')
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'");
    $activeAdminCount = (int)$stmt->fetchColumn();

    if($activeAdminCount <= 1)
    {
        die('You cannot disable the last active admin account.');
    }
}

$stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
$stmt->execute([$newStatus, $id]);

audit_log($pdo, 'Toggle User Status', 'Changed user '.$user['username'].' to '.$newStatus);

header('Location: users.php?focus_user_id='.(int)$id);
exit;

?>
