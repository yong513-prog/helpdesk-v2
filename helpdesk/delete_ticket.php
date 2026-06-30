<?php

session_start();

require 'db.php';
require_once 'audit_log.php';
require_once 'access_control.php';
require_once 'module_permissions.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

require_action_permission('delete_ticket');

$id = (int)($_GET['id'] ?? 0);

if($id <= 0)
{
    die("Invalid ticket ID");
}

$stmt = $pdo->prepare("
    SELECT
        *
    FROM tickets
    WHERE id = ?
");

$stmt->execute([$id]);
$ticket = $stmt->fetch();

if(!$ticket)
{
    die("Ticket not found");
}

if(function_exists('can_access_ticket') && !can_access_ticket($ticket))
{
    die('Access Denied');
}

/*
|--------------------------------------------------------------------------
| Delete main ticket attachment
|--------------------------------------------------------------------------
*/
if(!empty($ticket['attachment']) && file_exists($ticket['attachment']))
{
    @unlink($ticket['attachment']);
}

/*
|--------------------------------------------------------------------------
| Delete reply attachments
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT attachment
    FROM ticket_replies
    WHERE ticket_id = ?
");

$stmt->execute([$id]);

while($reply = $stmt->fetch())
{
    if(!empty($reply['attachment']) && file_exists($reply['attachment']))
    {
        @unlink($reply['attachment']);
    }
}

/*
|--------------------------------------------------------------------------
| Delete replies
|--------------------------------------------------------------------------
*/
$pdo->prepare("
    DELETE FROM ticket_replies
    WHERE ticket_id = ?
")->execute([$id]);

/*
|--------------------------------------------------------------------------
| Delete ticket
|--------------------------------------------------------------------------
*/
$pdo->prepare("
    DELETE FROM tickets
    WHERE id = ?
")->execute([$id]);

audit_log(
    $pdo,
    'Delete Ticket',
    'Deleted Ticket '.$ticket['ticket_no']
);

header("Location: ticket_list.php");
exit;

?>