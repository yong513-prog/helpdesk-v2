<?php

session_start();

require 'db.php';
require_once 'audit_log.php';
require_once 'access_control.php';
require_once 'ticket_history.php';
require_once 'send_mail.php';
require_once 'module_permissions.php';
require_once 'notification_helper.php';
require_once 'attachment_upload_helper.php';
require_once 'ticket_attachment_helper.php';


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
$message = trim($_POST['message'] ?? '');
$attachment = null;
$replyAttachments = [];

if($ticket_id <= 0)
{
    die("Invalid ticket");
}

if($message == '' && !hd_ta_has_uploads($_FILES, ['attachment','attachments','attachmentCamera','attachmentGallery','attachmentVoice','voiceAttachment']))
{
    die("Please type a reply or upload an attachment.");
}

$stmt = $pdo->prepare("
SELECT *
FROM tickets
WHERE id = ?
");

$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$ticket)
{
    die("Ticket not found");
}

if(!can_access_ticket($ticket))
{
    die("Access denied");
}

require_action_permission('reply_ticket');

$ticketNoForFolder = !empty($ticket['ticket_no']) ? $ticket['ticket_no'] : ('TICKET-'.$ticket_id);
$replyAttachments = hd_ta_upload_many(
    $_FILES,
    hd_entity_upload_dir('tickets', $ticketNoForFolder),
    ['attachment','attachments','attachmentCamera','attachmentGallery','attachmentVoice','voiceAttachment']
);
if(!empty($replyAttachments)) {
    $attachment = $replyAttachments[0]['path'];
}

$stmt = $pdo->prepare("
INSERT INTO ticket_replies
(
    ticket_id,
    user_id,
    message,
    attachment
)
VALUES
(
    ?,
    ?,
    ?,
    ?
)
");

$stmt->execute([
    $ticket_id,
    $_SESSION['user_id'],
    $message,
    $attachment
]);

$reply_id = (int)$pdo->lastInsertId();
if(!empty($replyAttachments)) {
    hd_ta_insert_many($pdo, $ticket_id, $reply_id, $replyAttachments, (int)$_SESSION['user_id']);
}


$actorName = $_SESSION['username'] ?? ('User ID '.$_SESSION['user_id']);
$stmtTicketUpdate = $pdo->prepare("
    UPDATE tickets
    SET updated_at = NOW(),
        last_update = NOW(),
        last_updated_by = ?
    WHERE id = ?
");
$stmtTicketUpdate->execute([
    $actorName,
    $ticket_id
]);

audit_log(
    $pdo,
    'Reply Ticket',
    'Ticket '.$ticket['ticket_no'].' replied'
);

ticket_history(
    $pdo,
    $ticket_id,
    'Reply Added',
    $_SESSION['user_id']
);

if(!empty($attachment))
{
    ticket_history(
        $pdo,
        $ticket_id,
        'Reply Attachment Uploaded',
        $_SESSION['user_id']
    );
}

notify_ticket_replied($pdo, $ticket, $message);
notify_ticket_replied_internal($pdo, $ticket, $message);

redirect_to_ticket_list_after_action();

?>
