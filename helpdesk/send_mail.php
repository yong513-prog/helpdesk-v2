<?php

/*
|--------------------------------------------------------------------------
| Email Helper for Helpdesk System
|--------------------------------------------------------------------------
| Requirement:
| composer require phpmailer/phpmailer
*/

function helpdesk_mail_config()
{
    static $config = null;

    if($config === null)
    {
        $config = require __DIR__ . '/mail_config.php';
    }

    return $config;
}

function helpdesk_mail_available()
{
    return file_exists(__DIR__ . '/vendor/autoload.php');
}

function send_helpdesk_mail($to, $subject, $body, $altBody = '')
{
    $config = helpdesk_mail_config();

    if(empty($config['enabled']))
    {
        return false;
    }

    if(!helpdesk_mail_available())
    {
        error_log('Helpdesk email not sent: vendor/autoload.php not found. Please install PHPMailer.');
        return false;
    }

    require_once __DIR__ . '/vendor/autoload.php';

    if(!class_exists('PHPMailer\\PHPMailer\\PHPMailer'))
    {
        error_log('Helpdesk email not sent: PHPMailer class not found.');
        return false;
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try
    {
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_secure'];
        $mail->Port = (int)$config['smtp_port'];
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($config['from_email'], $config['from_name']);

        if(is_array($to))
        {
            foreach($to as $email)
            {
                $email = trim((string)$email);
                if(filter_var($email, FILTER_VALIDATE_EMAIL))
                {
                    $mail->addAddress($email);
                }
            }
        }
        else
        {
            $email = trim((string)$to);
            if(filter_var($email, FILTER_VALIDATE_EMAIL))
            {
                $mail->addAddress($email);
            }
        }

        if(count($mail->getToAddresses()) == 0)
        {
            return false;
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $altBody != '' ? $altBody : strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        return $mail->send();
    }
    catch(Exception $e)
    {
        error_log('Helpdesk email error: '.$mail->ErrorInfo);
        return false;
    }
}

function get_user_email_by_id(PDO $pdo, $userId)
{
    try
    {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([(int)$userId]);
        $email = $stmt->fetchColumn();
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
    catch(Exception $e)
    {
        return null;
    }
}

function get_ticket_notification_emails(PDO $pdo, array $ticket, $excludeUserId = null)
{
    $emails = [];
    $config = helpdesk_mail_config();

    try
    {
        $params = [];
        $conditions = [];

        // Notify admin users
        $conditions[] = "role = 'admin'";

        // Notify users under the same PIC / department
        if(!empty($ticket['department']))
        {
            $conditions[] = "department = ?";
            $params[] = $ticket['department'];
        }

        // Notify ticket creator
        if(!empty($ticket['created_by']))
        {
            $conditions[] = "id = ?";
            $params[] = (int)$ticket['created_by'];
        }

        $sql = "
            SELECT id, email
            FROM users
            WHERE status = 'active'
            AND email IS NOT NULL
            AND email <> ''
            AND (".implode(' OR ', $conditions).")
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        while($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            if($excludeUserId !== null && (int)$row['id'] === (int)$excludeUserId)
            {
                continue;
            }

            if(filter_var($row['email'], FILTER_VALIDATE_EMAIL))
            {
                $emails[] = $row['email'];
            }
        }
    }
    catch(Exception $e)
    {
        // If email column not installed yet, fallback to default email.
    }

    if(empty($emails) && !empty($config['default_notify_email']))
    {
        $emails[] = $config['default_notify_email'];
    }

    return array_values(array_unique($emails));
}

function ticket_url($ticketId)
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');

    return $scheme.'://'.$host.$base.'/view_ticket.php?id='.(int)$ticketId;
}

function notify_ticket_created(PDO $pdo, array $ticket)
{
    $to = get_ticket_notification_emails($pdo, $ticket, $_SESSION['user_id'] ?? null);

    $subject = '[Helpdesk] New Ticket '.$ticket['ticket_no'];

    $body = '
        <h3>New 工单已创建</h3>
        <p><strong>Ticket No:</strong> '.htmlspecialchars($ticket['ticket_no']).'</p>
        <p><strong>Title:</strong> '.htmlspecialchars($ticket['title']).'</p>
        <p><strong>Branch:</strong> '.htmlspecialchars($ticket['branch'] ?? '-').'</p>
        <p><strong>Person In Charge:</strong> '.htmlspecialchars($ticket['department'] ?? '-').'</p>
        <p><strong>Category:</strong> '.htmlspecialchars($ticket['category'] ?? '-').'</p>
        <p><strong>Priority:</strong> '.htmlspecialchars($ticket['priority'] ?? '-').'</p>
        <p><strong>Due Date:</strong> '.htmlspecialchars($ticket['due_date'] ?? '-').'</p>
        <p><a href="'.htmlspecialchars(ticket_url($ticket['id'])).'">View Ticket</a></p>
    ';

    return send_helpdesk_mail($to, $subject, $body);
}

function notify_ticket_replied(PDO $pdo, array $ticket, $message)
{
    $to = get_ticket_notification_emails($pdo, $ticket, $_SESSION['user_id'] ?? null);

    $subject = '[Helpdesk] Ticket Replied '.$ticket['ticket_no'];

    $body = '
        <h3>Ticket Reply Added</h3>
        <p><strong>Ticket No:</strong> '.htmlspecialchars($ticket['ticket_no']).'</p>
        <p><strong>Title:</strong> '.htmlspecialchars($ticket['title']).'</p>
        <p><strong>Status:</strong> '.htmlspecialchars($ticket['status'] ?? '-').'</p>
        <p><strong>Message:</strong></p>
        <div style="padding:10px;border:1px solid #ddd;background:#f8f9fa;">'.nl2br(htmlspecialchars($message)).'</div>
        <p><a href="'.htmlspecialchars(ticket_url($ticket['id'])).'">View Ticket</a></p>
    ';

    return send_helpdesk_mail($to, $subject, $body);
}

function notify_ticket_status_changed(PDO $pdo, array $ticket, $oldStatus, $newStatus)
{
    $to = get_ticket_notification_emails($pdo, $ticket, $_SESSION['user_id'] ?? null);

    $subject = '[Helpdesk] Status Changed '.$ticket['ticket_no'].' - '.$newStatus;

    $body = '
        <h3>Ticket Status Changed</h3>
        <p><strong>Ticket No:</strong> '.htmlspecialchars($ticket['ticket_no']).'</p>
        <p><strong>Title:</strong> '.htmlspecialchars($ticket['title']).'</p>
        <p><strong>Old Status:</strong> '.htmlspecialchars($oldStatus).'</p>
        <p><strong>New Status:</strong> '.htmlspecialchars($newStatus).'</p>
        <p><a href="'.htmlspecialchars(ticket_url($ticket['id'])).'">View Ticket</a></p>
    ';

    return send_helpdesk_mail($to, $subject, $body);
}
