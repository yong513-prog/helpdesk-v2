<?php

session_start();

require 'db.php';
require_once 'access_control.php';
require_once 'ticket_history.php';
require_once 'notification_helper.php';


function ensure_ticket_last_update_columns(PDO $pdo)
{
    try
    {
        $stmt = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'last_update'");
        if(!$stmt->fetch(PDO::FETCH_ASSOC))
        {
            $pdo->exec("ALTER TABLE tickets ADD COLUMN last_update DATETIME NULL DEFAULT NULL AFTER updated_at");
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'last_updated_by'");
        if(!$stmt->fetch(PDO::FETCH_ASSOC))
        {
            $pdo->exec("ALTER TABLE tickets ADD COLUMN last_updated_by VARCHAR(100) NULL DEFAULT NULL AFTER last_update");
        }
    }
    catch(Exception $e) {}
}


function redirect_to_ticket_list_after_action()
{
    $status = trim($_POST['return_status'] ?? $_GET['return_status'] ?? '');
    $branch = trim($_POST['return_branch'] ?? $_GET['return_branch'] ?? '');
    $priority = trim($_POST['return_priority'] ?? $_GET['return_priority'] ?? '');

    $params = [];

    if($status !== '')
    {
        $params['status'] = $status;
    }

    if($branch !== '')
    {
        $params['branch'] = $branch;
    }

    if($priority !== '')
    {
        $params['priority'] = $priority;
    }

    $url = 'ticket_list.php';
    if(count($params) > 0)
    {
        $url .= '?' . http_build_query($params);
    }

    header("Location: ".$url);
    exit;
}

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

ensure_ticket_last_update_columns($pdo);


require_once 'module_permissions.php';
require_action_permission('assign_ticket');

$ticket_id = (int)($_POST['ticket_id'] ?? 0);
$assigned_to = trim($_POST['assigned_to'] ?? '');
$actorName = $_SESSION['username'] ?? ('User ID '.$_SESSION['user_id']);

if($ticket_id <= 0)
{
    die("Invalid ticket.");
}

$stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$ticket)
{
    die("Ticket not found.");
}

if(function_exists('can_access_ticket') && !can_access_ticket($ticket))
{
    die("Access Denied");
}

if($assigned_to !== '')
{
    $stmtAssign = $pdo->prepare("
        SELECT assign_name
        FROM assign_to_master
        WHERE assign_name = ?
        AND status = 1
        LIMIT 1
    ");
    $stmtAssign->execute([$assigned_to]);

    if(!$stmtAssign->fetch())
    {
        die("Invalid assigned user selected.");
    }
}

$stmtUpdate = $pdo->prepare("
    UPDATE tickets
    SET assigned_to = ?,
        updated_at = NOW(),
        last_update = NOW(),
        last_updated_by = ?
    WHERE id = ?
");

$stmtUpdate->execute([
    $assigned_to,
    $actorName,
    $ticket_id
]);

if(function_exists('ticket_history'))
{
    ticket_history(
        $pdo,
        $ticket_id,
        $assigned_to !== '' ? 'Assigned To: '.$assigned_to : 'Assigned To: Unassigned',
        $_SESSION['user_id']
    );
}

if($assigned_to !== '')
{
    notify_ticket_assigned_internal($pdo, $ticket, $assigned_to);
}

redirect_to_ticket_list_after_action();
