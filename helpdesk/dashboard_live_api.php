<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require 'db.php';
require_once 'access_control.php';
require_once 'notification_helper.php';
require_once 'ticket_status_options.php';

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['counts'=>[]]);
    exit;
}

try { ticket_status_ensure_ticket_column($pdo); } catch(Exception $e) {}
notification_ensure_schema($pdo);

$whereSql = " WHERE 1=1 ";
$whereParams = [];
if(function_exists('apply_ticket_access_filter')) {
    apply_ticket_access_filter($whereSql, $whereParams);
}

function live_count(PDO $pdo, string $whereSql, array $whereParams, string $extra = ''): int {
    $sql = "SELECT COUNT(*) FROM tickets ".$whereSql;
    if($extra !== '') $sql .= " AND ".$extra;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($whereParams);
    return (int)$stmt->fetchColumn();
}

try {
    $counts = [
        'total' => live_count($pdo, $whereSql, $whereParams),
        'open' => live_count($pdo, $whereSql, $whereParams, "status='Open'"),
        'progress' => live_count($pdo, $whereSql, $whereParams, "status='In Progress'"),
        'pending' => live_count($pdo, $whereSql, $whereParams, "status='Pending'"),
        'solved' => live_count($pdo, $whereSql, $whereParams, "status='Solved'"),
        'closed' => live_count($pdo, $whereSql, $whereParams, "status='Closed'"),
        'overdue' => live_count($pdo, $whereSql, $whereParams, "due_date IS NOT NULL AND due_date < NOW() AND status NOT IN ('Solved','Closed')"),
        'notifications' => notification_unread_count($pdo, (int)$_SESSION['user_id']),
    ];
    echo json_encode(['counts'=>$counts], JSON_UNESCAPED_UNICODE);
} catch(Exception $e) {
    echo json_encode(['counts'=>[]]);
}
