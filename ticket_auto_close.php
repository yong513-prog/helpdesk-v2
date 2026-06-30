<?php
/*
|--------------------------------------------------------------------------
| Auto Close Solved Tickets
|--------------------------------------------------------------------------
| Rule:
| - Solved ticket stays active first.
| - After 5 days without update, system changes it to Closed automatically.
| - Solved must NOT be marked Closed? in Ticket Status Management.
| - Closed must be marked Closed? in Ticket Status Management.
*/

if(!function_exists('ticket_auto_close_ensure_last_update_columns'))
{
    function ticket_auto_close_ensure_last_update_columns(PDO $pdo)
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
}

if(!function_exists('ticket_auto_close_table_exists'))
{
    function ticket_auto_close_table_exists(PDO $pdo, string $table): bool
    {
        try
        {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        }
        catch(Exception $e)
        {
            return false;
        }
    }
}

if(!function_exists('ticket_auto_close_solved_tickets'))
{
    function ticket_auto_close_solved_tickets(PDO $pdo, int $days = 5)
    {
        $days = max(1, $days);

        try
        {
            if(function_exists('ensure_ticket_status_master'))
            {
                ensure_ticket_status_master($pdo);
            }

            if(function_exists('ticket_status_ensure_ticket_column'))
            {
                ticket_status_ensure_ticket_column($pdo);
            }

            ticket_auto_close_ensure_last_update_columns($pdo);

            // Force the required workflow:
            // Solved stays in active list first, Closed goes to Closed Tickets.
            $stmt = $pdo->prepare("
                UPDATE ticket_status_master
                SET is_closed = 0,
                    is_active = 1,
                    updated_at = NOW()
                WHERE status_name = 'Solved'
            ");
            $stmt->execute();

            $stmt = $pdo->prepare("
                UPDATE ticket_status_master
                SET is_closed = 1,
                    is_active = 1,
                    updated_at = NOW()
                WHERE status_name = 'Closed'
            ");
            $stmt->execute();

            $sql = "
                SELECT id, ticket_no
                FROM tickets
                WHERE status = 'Solved'
                AND COALESCE(last_update, updated_at, created_at) <= DATE_SUB(NOW(), INTERVAL ".$days." DAY)
            ";

            $tickets = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            if(!$tickets)
            {
                return;
            }

            $ids = array_map(function($row){ return (int)$row['id']; }, $tickets);
            $ids = array_values(array_filter($ids));

            if(!$ids)
            {
                return;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $stmt = $pdo->prepare("
                UPDATE tickets
                SET status = 'Closed',
                    closed_at = IF(closed_at IS NULL, NOW(), closed_at),
                    updated_at = NOW(),
                    last_update = NOW(),
                    last_updated_by = 'System Auto Close'
                WHERE id IN ($placeholders)
            ");
            $stmt->execute($ids);

            if(ticket_auto_close_table_exists($pdo, 'ticket_history'))
            {
                $stmtHistory = $pdo->prepare("
                    INSERT INTO ticket_history
                    (ticket_id, action, created_by)
                    VALUES
                    (?, ?, NULL)
                ");

                foreach($tickets as $ticket)
                {
                    $stmtHistory->execute([
                        (int)$ticket['id'],
                        'Auto Closed: Solved for more than '.$days.' days'
                    ]);
                }
            }
        }
        catch(Exception $e)
        {
            // Keep user pages usable even if auto close fails.
        }
    }
}
