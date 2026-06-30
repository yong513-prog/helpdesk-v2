<?php
/*
|--------------------------------------------------------------------------
| Helpdesk Internal Notification Center
|--------------------------------------------------------------------------
| No phone number, no WhatsApp API, no external service.
| Notifications are stored inside your own MySQL database.
*/

if(file_exists(__DIR__ . '/access_control.php')){ require_once __DIR__ . '/access_control.php'; }

if(!function_exists('notification_ensure_schema'))
{
    function notification_ensure_schema(PDO $pdo)
    {
        try
        {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS notifications (
                    id INT NOT NULL AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    ticket_id INT NULL,
                    type VARCHAR(50) NULL,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NULL,
                    url VARCHAR(255) NULL,
                    is_read TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    read_at DATETIME NULL,
                    PRIMARY KEY (id),
                    KEY idx_notifications_user_read (user_id, is_read),
                    KEY idx_notifications_ticket (ticket_id),
                    KEY idx_notifications_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        catch(Exception $e) {}
    }
}

if(!function_exists('notification_user_ids_by_names'))
{
    function notification_user_ids_by_names(PDO $pdo, array $names): array
    {
        notification_ensure_schema($pdo);

        $clean = [];
        foreach($names as $name)
        {
            $name = trim((string)$name);
            if($name !== '' && $name !== '-' && strtolower($name) !== 'unassigned')
            {
                $clean[] = $name;
            }
        }

        $clean = array_values(array_unique($clean));
        if(!$clean)
        {
            return [];
        }

        $ids = [];

        foreach($clean as $name)
        {
            try
            {
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE username = ?
                       OR full_name = ?
                       OR department = ?
                       OR ticket_pic_access LIKE ?
                       OR ticket_assign_access LIKE ?
                    ORDER BY id ASC
                ");
                $like = '%'.$name.'%';
                $stmt->execute([$name, $name, $name, $like, $like]);
                $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach($rows as $id)
                {
                    $ids[] = (int)$id;
                }
            }
            catch(Exception $e) {}
        }

        return array_values(array_unique(array_filter($ids)));
    }
}

if(!function_exists('notification_create'))
{
    function notification_create(PDO $pdo, int $userId, ?int $ticketId, string $type, string $title, string $message = '', string $url = '')
    {
        notification_ensure_schema($pdo);

        if($userId <= 0 || trim($title) === '')
        {
            return;
        }

        if($url === '' && $ticketId)
        {
            $url = 'view_ticket.php?id='.(int)$ticketId;
        }

        try
        {
            $stmt = $pdo->prepare("
                INSERT INTO notifications
                (user_id, ticket_id, type, title, message, url)
                VALUES
                (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $ticketId ?: null,
                $type,
                $title,
                $message,
                $url
            ]);
        }
        catch(Exception $e) {}
    }
}

if(!function_exists('notification_create_many'))
{
    function notification_create_many(PDO $pdo, array $userIds, ?int $ticketId, string $type, string $title, string $message = '', string $url = '', ?int $excludeUserId = null)
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        foreach($userIds as $userId)
        {
            if($userId <= 0)
            {
                continue;
            }

            if($excludeUserId !== null && $userId === (int)$excludeUserId)
            {
                continue;
            }

            notification_create($pdo, $userId, $ticketId, $type, $title, $message, $url);
        }
    }
}

if(!function_exists('notification_ticket_recipients'))
{
    function notification_ticket_recipients(PDO $pdo, array $ticket, bool $includeCreator = true): array
    {
        $ids = [];

        if($includeCreator && !empty($ticket['created_by']))
        {
            $ids[] = (int)$ticket['created_by'];
        }

        $names = [];
        if(!empty($ticket['assigned_to']))
        {
            $names[] = $ticket['assigned_to'];
        }

        if(!empty($ticket['department']))
        {
            $names[] = $ticket['department'];
        }

        $ids = array_merge($ids, notification_user_ids_by_names($pdo, $names));

        return array_values(array_unique(array_filter($ids)));
    }
}


if(!function_exists('notification_i18n_text'))
{
    function notification_i18n_text($text)
    {
        $text = (string)($text ?? '');
        if($text === '') return $text;
        if(!function_exists('__')) return $text;

        // Translate common notification prefixes while preserving ticket numbers / user-entered values.
        $patterns = [
            '/^New Ticket Assigned:\s*(.+)$/u' => __('New Ticket Assigned').': $1',
            '/^Ticket Assigned To You:\s*(.+)$/u' => __('Ticket Assigned To You').': $1',
            '/^Assigned:\s*(.+)$/u' => __('Assigned').': $1',
            '/^Assigned by:\s*(.+)$/u' => __('Assigned by').': $1',
            '/^Title:\s*(.+)$/u' => __('Title:').' $1',
            '/^Branch:\s*(.+)$/u' => __('Branch:').' $1',
            '/^Priority:\s*(.+)$/u' => __('Priority:').' $1',
            '/^New Ticket Created:\s*(.+)$/u' => __('New Ticket').' '.__('Created').': $1',
            '/^Ticket Replied:\s*(.+)$/u' => __('Reply Ticket').': $1',
            '/^Ticket Updated:\s*(.+)$/u' => __('Updated').': $1',
        ];

        $lines = preg_split('/\R/u', $text);
        foreach($lines as &$line){
            $line = (string)$line;
            $done = false;
            foreach($patterns as $regex=>$replacement){
                if(preg_match($regex, $line)){
                    $line = preg_replace($regex, $replacement, $line);
                    $done = true;
                    break;
                }
            }
            if(!$done){
                $line = __($line);
            }
        }
        unset($line);
        return implode("
", $lines);
    }
}

if(!function_exists('notification_unread_count'))
{
    function notification_unread_count(PDO $pdo, int $userId): int
    {
        notification_ensure_schema($pdo);
        if(function_exists('notification_visible_rows')){
            return count(notification_visible_rows($pdo, $userId, 500, true));
        }

        try
        {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        }
        catch(Exception $e)
        {
            return 0;
        }
    }
}

if(!function_exists('notify_ticket_created_internal'))
{
    function notify_ticket_created_internal(PDO $pdo, array $ticket)
    {
        $ticketId = (int)($ticket['id'] ?? 0);
        $ticketNo = (string)($ticket['ticket_no'] ?? '');
        $title = (string)($ticket['title'] ?? '');
        $branch = (string)($ticket['branch'] ?? '');
        $priority = (string)($ticket['priority'] ?? '');
        $assignedTo = (string)($ticket['assigned_to'] ?? '');
        $department = (string)($ticket['department'] ?? '');

        $recipients = notification_user_ids_by_names($pdo, [$assignedTo, $department]);
        if($ticketId > 0 && function_exists('notification_filter_ticket_recipients_by_permission')) $recipients = notification_filter_ticket_recipients_by_permission($pdo, $recipients, $ticket);

        notification_create_many(
            $pdo,
            $recipients,
            $ticketId,
            'ticket_created',
            'New Ticket Assigned: '.$ticketNo,
            "Title: ".$title."\nBranch: ".$branch."\nPriority: ".$priority,
            'view_ticket.php?id='.$ticketId,
            (int)($_SESSION['user_id'] ?? 0)
        );
    }
}

if(!function_exists('notify_ticket_assigned_internal'))
{
    function notify_ticket_assigned_internal(PDO $pdo, array $ticket, string $assignedTo)
    {
        $ticketId = (int)($ticket['id'] ?? 0);
        $ticketNo = (string)($ticket['ticket_no'] ?? '');
        $recipients = notification_user_ids_by_names($pdo, [$assignedTo]);
        if($ticketId > 0 && function_exists('notification_filter_ticket_recipients_by_permission')) $recipients = notification_filter_ticket_recipients_by_permission($pdo, $recipients, $ticket);

        notification_create_many(
            $pdo,
            $recipients,
            $ticketId,
            'ticket_assigned',
            'Ticket Assigned To You: '.$ticketNo,
            'Assigned by: '.($_SESSION['username'] ?? 'System'),
            'view_ticket.php?id='.$ticketId,
            (int)($_SESSION['user_id'] ?? 0)
        );
    }
}

if(!function_exists('notify_ticket_replied_internal'))
{
    function notify_ticket_replied_internal(PDO $pdo, array $ticket, string $replyMessage = '')
    {
        $ticketId = (int)($ticket['id'] ?? 0);
        $ticketNo = (string)($ticket['ticket_no'] ?? '');
        $recipients = notification_ticket_recipients($pdo, $ticket, true);
        if($ticketId > 0 && function_exists('notification_filter_ticket_recipients_by_permission')) $recipients = notification_filter_ticket_recipients_by_permission($pdo, $recipients, $ticket);

        notification_create_many(
            $pdo,
            $recipients,
            $ticketId,
            'ticket_replied',
            'Ticket Replied: '.$ticketNo,
            mb_substr(strip_tags($replyMessage), 0, 180),
            'view_ticket.php?id='.$ticketId,
            (int)($_SESSION['user_id'] ?? 0)
        );
    }
}

if(!function_exists('notify_ticket_status_internal'))
{
    function notify_ticket_status_internal(PDO $pdo, array $ticket, string $oldStatus, string $newStatus)
    {
        $ticketId = (int)($ticket['id'] ?? 0);
        $ticketNo = (string)($ticket['ticket_no'] ?? '');
        $recipients = notification_ticket_recipients($pdo, $ticket, true);
        if($ticketId > 0 && function_exists('notification_filter_ticket_recipients_by_permission')) $recipients = notification_filter_ticket_recipients_by_permission($pdo, $recipients, $ticket);

        notification_create_many(
            $pdo,
            $recipients,
            $ticketId,
            'ticket_status',
            'Ticket Status Updated: '.$ticketNo,
            $oldStatus.' → '.$newStatus,
            'view_ticket.php?id='.$ticketId,
            (int)($_SESSION['user_id'] ?? 0)
        );
    }
}

/* Final permission-linked notification helpers */
if(!function_exists('notification_user_identity_names_for_record')){
    function notification_user_identity_names_for_record(array $user): array{
        $names = [];
        foreach([$user['username'] ?? '', $user['full_name'] ?? '', $user['department'] ?? ''] as $n){
            $n = trim((string)$n);
            if($n !== '') $names[] = $n;
        }
        foreach(explode(',', (string)($user['ticket_assign_access'] ?? '')) as $n){
            $n = trim($n);
            if($n !== '') $names[] = $n;
        }
        return array_values(array_unique($names));
    }
}

if(!function_exists('notification_csv_array')){
    function notification_csv_array($value): array{
        $out = [];
        foreach(explode(',', (string)$value) as $v){
            $v = trim($v);
            if($v !== '') $out[] = $v;
        }
        return array_values(array_unique($out));
    }
}

if(!function_exists('notification_user_can_access_ticket')){
    function notification_user_can_access_ticket(PDO $pdo, int $userId, array $ticket): bool{
        if($userId <= 0) return false;
        try{
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!$user) return false;
        }catch(Exception $e){ return false; }

        $role = strtolower(trim((string)($user['role'] ?? 'staff')));
        if(in_array($role, ['administrator','admin'], true)) return true;
        if($role !== 'head') $role = 'staff';

        $branch = trim((string)($ticket['branch'] ?? ''));
        $pic = trim((string)($ticket['department'] ?? ''));
        $assignedTo = trim((string)($ticket['assigned_to'] ?? ''));
        $createdBy = (int)($ticket['created_by'] ?? 0);

        $identityNames = notification_user_identity_names_for_record($user);

        if($role === 'head'){
            $branches = array_merge(
                notification_csv_array($user['branch_access'] ?? ''),
                notification_csv_array($user['ticket_branch_access'] ?? '')
            );
            $primary = trim((string)($user['branch'] ?? ''));
            if($primary !== '') $branches[] = $primary;
            $branches = array_values(array_unique($branches));

            $branchOk = count($branches) === 0 || ($branch !== '' && in_array($branch, $branches, true));
            if(!$branchOk) return false;

            $pics = notification_csv_array($user['ticket_pic_access'] ?? '');
            return ($pic !== '' && in_array($pic, $pics, true))
                || ($assignedTo !== '' && in_array($assignedTo, $identityNames, true))
                || ($createdBy === $userId);
        }

        $primaryBranch = trim((string)($user['branch'] ?? ''));
        return ($branch !== '' && $primaryBranch !== '' && $branch === $primaryBranch)
            || ($assignedTo !== '' && in_array($assignedTo, $identityNames, true))
            || ($createdBy === $userId);
    }
}

if(!function_exists('notification_filter_ticket_recipients_by_permission')){
    function notification_filter_ticket_recipients_by_permission(PDO $pdo, array $userIds, array $ticket): array{
        $out = [];
        foreach(array_values(array_unique(array_map('intval', $userIds))) as $uid){
            if(notification_user_can_access_ticket($pdo, $uid, $ticket)) $out[] = $uid;
        }
        return array_values(array_unique($out));
    }
}

if(!function_exists('notification_fetch_ticket')){
    function notification_fetch_ticket(PDO $pdo, int $ticketId): ?array{
        if($ticketId <= 0) return null;
        try{
            $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id=? LIMIT 1");
            $stmt->execute([$ticketId]);
            $t = $stmt->fetch(PDO::FETCH_ASSOC);
            return $t ?: null;
        }catch(Exception $e){ return null; }
    }
}

if(!function_exists('notification_visible_rows')){
    function notification_visible_rows(PDO $pdo, int $userId, int $limit = 100, bool $onlyUnread = false): array{
        notification_ensure_schema($pdo);
        $limit = max(1, min(500, $limit));
        $sql = "SELECT id, user_id, ticket_id, type, title, message, url, is_read, created_at FROM notifications WHERE user_id=?";
        if($onlyUnread) $sql .= " AND is_read=0";
        $sql .= " ORDER BY is_read ASC, created_at DESC, id DESC LIMIT ".($limit * 4);
        try{
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }catch(Exception $e){ return []; }

        $out = [];
        foreach($rows as $r){
            $ticketId = (int)($r['ticket_id'] ?? 0);
            if($ticketId > 0){
                $ticket = notification_fetch_ticket($pdo, $ticketId);
                if(!$ticket || !notification_user_can_access_ticket($pdo, $userId, $ticket)) continue;
            }
            $out[] = $r;
            if(count($out) >= $limit) break;
        }
        return $out;
    }
}
?>
