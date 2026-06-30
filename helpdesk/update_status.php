<?php

session_start();

require 'db.php';
require_once 'audit_log.php';
require_once 'access_control.php';
require_once 'ticket_history.php';
require_once 'send_mail.php';
require_once 'ticket_status_options.php';
require_once 'notification_helper.php';
ticket_status_ensure_ticket_column($pdo);


function ensure_ticket_status_column_is_varchar(PDO $pdo)
{
    try
    {
        $stmt = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'status'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);

        $type = strtolower((string)($col['Type'] ?? ''));

        if(strpos($type, 'enum(') === 0 || strpos($type, 'varchar') !== 0)
        {
            $pdo->exec("ALTER TABLE tickets MODIFY COLUMN status VARCHAR(100) NOT NULL DEFAULT 'Open'");
        }
    }
    catch(Exception $e)
    {
        // Do not stop the page here. If ALTER fails, the UPDATE below will show the real DB error.
    }
}

ensure_ticket_status_column_is_varchar($pdo);


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


$ticket_id = (int)($_POST['ticket_id'] ?? 0);
$status = trim($_POST['status'] ?? '');

$allowedStatus = ticket_status_name_list($pdo, true);

if($ticket_id <= 0 || !in_array($status, $allowedStatus, true))
{
    die("Invalid request. Status is not active in Ticket Status Management.");
}

$stmt = $pdo->prepare("
SELECT *
FROM tickets
WHERE id = ?
");

$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch();

if(!$ticket)
{
    die("Ticket not found");
}

if(!can_access_ticket($ticket))
{
    die("Access denied");
}

require_once 'module_permissions.php';
require_action_permission('change_status');

$oldStatus = $ticket['status'] ?? '';
$actorName = $_SESSION['username'] ?? ('User ID '.$_SESSION['user_id']);

if(ticket_status_is_closed($pdo, $status))
{
    $stmt = $pdo->prepare("
        UPDATE tickets
        SET
            status = ?,
            closed_at = IF(closed_at IS NULL, NOW(), closed_at),
            updated_at = NOW(),
            last_update = NOW(),
            last_updated_by = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $status,
        $actorName,
        $ticket_id
    ]);
}
else
{
    $stmt = $pdo->prepare("
        UPDATE tickets
        SET
            status = ?,
            closed_at = NULL,
            updated_at = NOW(),
            last_update = NOW(),
            last_updated_by = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $status,
        $actorName,
        $ticket_id
    ]);
}

audit_log(
    $pdo,
    'Update Status',
    'Ticket '.$ticket['ticket_no'].' changed to '.$status
);

ticket_history(
    $pdo,
    $ticket_id,
    'Status Changed: '.$oldStatus.' → '.$status,
    $_SESSION['user_id']
);

notify_ticket_status_changed($pdo, $ticket, $oldStatus, $status);
notify_ticket_status_internal($pdo, $ticket, $oldStatus, $status);

redirect_to_ticket_list_after_action();
