<?php

session_start();

require 'db.php';
require_once 'audit_log.php';
require_once 'access_control.php';
require_once 'module_permissions.php';
require_once 'ticket_history.php';
require_once 'ticket_status_options.php';
require_once 'notification_helper.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

function ensure_quick_action_columns(PDO $pdo)
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

        $stmt = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'status'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        $type = strtolower((string)($col['Type'] ?? ''));

        if(strpos($type, 'enum(') === 0 || strpos($type, 'varchar') !== 0)
        {
            $pdo->exec("ALTER TABLE tickets MODIFY COLUMN status VARCHAR(100) NOT NULL DEFAULT 'Open'");
        }
    }
    catch(Exception $e) {}
}

ensure_quick_action_columns($pdo);
ticket_status_ensure_ticket_column($pdo);
notification_ensure_schema($pdo);

$ticket_id = (int)($_POST['ticket_id'] ?? 0);
$status = trim((string)($_POST['status'] ?? ''));
$assigned_to = trim((string)($_POST['assigned_to'] ?? ''));
$actorId = (int)($_SESSION['user_id'] ?? 0);
$actorName = $_SESSION['username'] ?? ('User ID '.$actorId);

if($ticket_id <= 0)
{
    die("Invalid ticket.");
}

$stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ? LIMIT 1");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$ticket)
{
    die("Ticket not found.");
}

if(function_exists('can_access_ticket') && !can_access_ticket($ticket))
{
    die("Access denied.");
}

$canChangeStatus = has_action_permission('change_status', false);
$canAssignTicket = has_action_permission('assign_ticket', false);

if(!$canChangeStatus && !$canAssignTicket)
{
    die("Access denied.");
}

$oldStatus = (string)($ticket['status'] ?? '');
$oldAssignedTo = (string)($ticket['assigned_to'] ?? '');

if(!$canChangeStatus)
{
    $status = $oldStatus;
}

if(!$canAssignTicket)
{
    $assigned_to = $oldAssignedTo;
}

if($status === '')
{
    $status = $oldStatus;
}

$statusRows = ticket_status_fetch_all($pdo, true);
$validStatuses = [];
foreach($statusRows as $row)
{
    $validStatuses[] = (string)$row['status_name'];
}

if(!in_array($status, $validStatuses, true))
{
    die("Invalid status selected.");
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

    if(!$stmtAssign->fetch(PDO::FETCH_ASSOC))
    {
        die("Invalid assigned user selected.");
    }
}

try
{
    $closedAtSql = ticket_status_is_closed($pdo, $status)
        ? "closed_at = IF(closed_at IS NULL, NOW(), closed_at),"
        : "closed_at = NULL,";

    $stmtUpdate = $pdo->prepare("
        UPDATE tickets
        SET
            status = ?,
            assigned_to = ?,
            $closedAtSql
            updated_at = NOW(),
            last_update = NOW(),
            last_updated_by = ?
        WHERE id = ?
    ");

    $stmtUpdate->execute([
        $status,
        $assigned_to,
        $actorName,
        $ticket_id
    ]);

    if($status !== $oldStatus)
    {
        ticket_history(
            $pdo,
            $ticket_id,
            'Status Changed: '.$oldStatus.' → '.$status,
            $actorId
        );
    }

    if($assigned_to !== $oldAssignedTo)
    {
        ticket_history(
            $pdo,
            $ticket_id,
            $assigned_to !== '' ? 'Assigned To: '.$assigned_to : 'Assigned To: Unassigned',
            $actorId
        );
    }

    if($status === $oldStatus && $assigned_to === $oldAssignedTo)
    {
        ticket_history(
            $pdo,
            $ticket_id,
            'Quick Action Confirmed',
            $actorId
        );
    }

    if(function_exists('audit_log'))
    {
        audit_log(
            $pdo,
            'Quick Action Confirm',
            'Ticket '.($ticket['ticket_no'] ?? $ticket_id).' status: '.$oldStatus.' → '.$status.', assigned: '.($oldAssignedTo ?: 'Unassigned').' → '.($assigned_to ?: 'Unassigned')
        );
    }
}
catch(Exception $e)
{
    die("Quick action failed: ".$e->getMessage());
}

$updatedTicket = $ticket;
$updatedTicket['status'] = $status;
$updatedTicket['assigned_to'] = $assigned_to;

if($status !== $oldStatus)
{
    if(function_exists('notify_ticket_status_changed'))
    {
        notify_ticket_status_changed($pdo, $ticket, $oldStatus, $status);
    }

    if(function_exists('notify_ticket_status_internal'))
    {
        notify_ticket_status_internal($pdo, $ticket, $oldStatus, $status);
    }
}

if($assigned_to !== $oldAssignedTo && $assigned_to !== '' && function_exists('notify_ticket_assigned_internal'))
{
    notify_ticket_assigned_internal($pdo, $updatedTicket, $assigned_to);
}

$_SESSION['success_message'] = "Quick action confirmed successfully.";

header("Location: view_ticket.php?id=".$ticket_id);
exit;
