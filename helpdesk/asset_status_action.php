<?php
require 'db.php';
require_once 'audit_log.php';
require_once 'module_permissions.php';

if(session_status() === PHP_SESSION_NONE)
{
    session_start();
}

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

require_action_permission('manage_asset');

function redirect_asset($message = '', $error = '')
{
    if($message !== '') $_SESSION['asset_message'] = $message;
    if($error !== '') $_SESSION['asset_error'] = $error;

    header("Location: asset_list.php");
    exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    redirect_asset();
}

$id = (int)($_POST['id'] ?? 0);
$action = trim($_POST['action'] ?? '');

if($id <= 0)
{
    redirect_asset('', 'Invalid asset selected.');
}

$stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$asset = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$asset)
{
    redirect_asset('', 'Asset not found.');
}

$assetLabel = trim(($asset['asset_code'] ?? '').' - '.($asset['asset_name'] ?? ''));

try
{
    if($action === 'disable')
    {
        $stmt = $pdo->prepare("UPDATE assets SET status = 'Inactive' WHERE id = ?");
        $stmt->execute([$id]);

        audit_log($pdo, 'Disable Asset', 'Disabled Asset '.$assetLabel);
        redirect_asset('Asset disabled successfully. It will no longer appear in Create/Edit Ticket asset dropdown.');
    }

    if($action === 'enable')
    {
        $stmt = $pdo->prepare("UPDATE assets SET status = 'Active' WHERE id = ?");
        $stmt->execute([$id]);

        audit_log($pdo, 'Enable Asset', 'Enabled Asset '.$assetLabel);
        redirect_asset('Asset enabled successfully. It is now available in Create/Edit Ticket asset dropdown.');
    }

    if($action === 'delete')
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE asset_id = ?");
        $stmt->execute([$id]);
        $linkedTickets = (int)$stmt->fetchColumn();

        if($linkedTickets > 0)
        {
            redirect_asset('', 'Cannot delete this asset because it is linked to '.$linkedTickets.' ticket(s). Disable it instead to keep ticket history safe.');
        }

        $photo = $asset['asset_photo'] ?? '';

        $stmt = $pdo->prepare("DELETE FROM assets WHERE id = ?");
        $stmt->execute([$id]);

        if($photo !== '' && strpos($photo, 'uploads/assets/') === 0)
        {
            $photoPath = __DIR__ . '/' . $photo;
            if(is_file($photoPath)) @unlink($photoPath);
        }

        audit_log($pdo, 'Delete Asset', 'Deleted Asset '.$assetLabel);
        redirect_asset('Asset deleted successfully.');
    }

    redirect_asset('', 'Invalid asset action.');
}
catch(Exception $e)
{
    redirect_asset('', 'Asset action failed: '.$e->getMessage());
}
