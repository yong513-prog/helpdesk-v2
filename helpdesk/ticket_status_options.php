<?php
/*
|--------------------------------------------------------------------------
| Ticket Status Master Helper - Fully Dynamic Edition
|--------------------------------------------------------------------------
| Status names are configurable from Ticket Status Management.
| Important:
| - Default statuses are inserted ONLY when the table is empty.
| - After you rename Pending -> Waiting Reply, the system will NOT recreate Pending.
*/

if(!function_exists('ticket_status_ensure_ticket_column'))
{
    function ticket_status_ensure_ticket_column(PDO $pdo)
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
        catch(Exception $e) {}
    }
}

if(!function_exists('ensure_ticket_status_master'))
{
    function ensure_ticket_status_master(PDO $pdo)
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ticket_status_master (
                id INT NOT NULL AUTO_INCREMENT,
                status_name VARCHAR(100) NOT NULL,
                status_color VARCHAR(50) NOT NULL DEFAULT 'secondary',
                sort_order INT NOT NULL DEFAULT 0,
                is_closed TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_ticket_status_name (status_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        ticket_status_ensure_ticket_column($pdo);

        $count = (int)$pdo->query("SELECT COUNT(*) FROM ticket_status_master")->fetchColumn();

        // Only seed default statuses when the table is completely empty.
        // Do NOT reinsert missing default names after user renames them.
        if($count === 0)
        {
            $defaults = [
                ['Open', 'danger', 10, 0, 1],
                ['In Progress', 'warning text-dark', 20, 0, 1],
                ['Pending', 'info text-dark', 30, 0, 1],
                ['Solved', 'success', 40, 1, 1],
                ['Closed', 'secondary', 50, 1, 1],
            ];

            $stmt = $pdo->prepare("
                INSERT INTO ticket_status_master
                (status_name, status_color, sort_order, is_closed, is_active)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach($defaults as $d)
            {
                $stmt->execute($d);
            }
        }
    }
}

if(!function_exists('ticket_status_fetch_all'))
{
    function ticket_status_fetch_all(PDO $pdo, bool $activeOnly = true): array
    {
        ensure_ticket_status_master($pdo);

        $sql = "SELECT * FROM ticket_status_master";
        if($activeOnly)
        {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order ASC, status_name ASC";

        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}

if(!function_exists('ticket_status_name_list'))
{
    function ticket_status_name_list(PDO $pdo, bool $activeOnly = true): array
    {
        $rows = ticket_status_fetch_all($pdo, $activeOnly);
        return array_values(array_map(function($r){ return (string)$r['status_name']; }, $rows));
    }
}

if(!function_exists('ticket_status_color_map'))
{
    function ticket_status_color_map(PDO $pdo): array
    {
        $rows = ticket_status_fetch_all($pdo, false);
        $map = [];

        foreach($rows as $r)
        {
            $map[(string)$r['status_name']] = 'bg-' . trim((string)($r['status_color'] ?: 'secondary'));
        }

        return $map;
    }
}

if(!function_exists('ticket_status_closed_names'))
{
    function ticket_status_closed_names(PDO $pdo): array
    {
        ensure_ticket_status_master($pdo);

        $stmt = $pdo->query("
            SELECT status_name
            FROM ticket_status_master
            WHERE is_closed = 1
            ORDER BY sort_order ASC, status_name ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_filter(array_map('strval', $rows)));
    }
}

if(!function_exists('ticket_status_open_names'))
{
    function ticket_status_open_names(PDO $pdo): array
    {
        ensure_ticket_status_master($pdo);

        $stmt = $pdo->query("
            SELECT status_name
            FROM ticket_status_master
            WHERE is_closed = 0
            ORDER BY sort_order ASC, status_name ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_filter(array_map('strval', $rows)));
    }
}

if(!function_exists('ticket_status_is_closed'))
{
    function ticket_status_is_closed(PDO $pdo, string $status): bool
    {
        ensure_ticket_status_master($pdo);

        $stmt = $pdo->prepare("
            SELECT is_closed
            FROM ticket_status_master
            WHERE status_name = ?
            LIMIT 1
        ");
        $stmt->execute([$status]);
        $v = $stmt->fetchColumn();

        if($v === false)
        {
            return in_array($status, ['Solved','Closed'], true);
        }

        return ((int)$v) === 1;
    }
}

if(!function_exists('ticket_status_sql_in_placeholders'))
{
    function ticket_status_sql_in_placeholders(array $items): string
    {
        if(count($items) === 0)
        {
            return "''";
        }

        return implode(',', array_fill(0, count($items), '?'));
    }
}

if(!function_exists('ticket_status_slug'))
{
    function ticket_status_slug(string $status): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($status)));
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'status';
    }
}

if(!function_exists('ticket_status_default_open_name'))
{
    function ticket_status_default_open_name(PDO $pdo): string
    {
        ensure_ticket_status_master($pdo);

        $stmt = $pdo->query("
            SELECT status_name
            FROM ticket_status_master
            WHERE is_active = 1 AND is_closed = 0
            ORDER BY sort_order ASC, id ASC
            LIMIT 1
        ");
        $name = $stmt->fetchColumn();

        return $name ? (string)$name : 'Open';
    }
}

if(!function_exists('ticket_status_ensure_last_update_columns'))
{
    function ticket_status_ensure_last_update_columns(PDO $pdo)
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

