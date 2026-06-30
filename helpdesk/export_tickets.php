<?php

session_start();

require 'db.php';
require_once 'access_control.php';
require_once 'ticket_status_options.php';
require_once 'module_permissions.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}
require_once 'module_permissions.php';
require_action_permission('export_ticket');
ticket_status_ensure_last_update_columns($pdo);
$closedExport = isset($_GET['closed']) && $_GET['closed'] == '1';
$exportStatus = trim($_GET['status'] ?? '');
$closedStatusNames = ticket_status_closed_names($pdo);
$closedPlaceholders = ticket_status_sql_in_placeholders($closedStatusNames);


if(!has_action_permission('export_ticket'))
{
    die("Access Denied");
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . ($closedExport ? 'closed_tickets.csv' : 'tickets.csv'));

$output = fopen('php://output', 'w');

fputcsv($output, [
    'Ticket No',
    'Title',
    'Branch',
    'Person In Charge',
    'Category',
    'Priority',
    'Status',
    'Assigned To',
    'SLA Due Date',
    'Created At',
    'Last Update',
    'Last Updated By'
]);

$sql = "
    SELECT *
    FROM tickets
    WHERE 1=1
";

$params = [];

apply_ticket_access_filter($sql, $params);

if($closedExport)
{
    $sql .= " AND status IN ($closedPlaceholders)";
    $params = array_merge($params, $closedStatusNames);
}
elseif($exportStatus !== '')
{
    $sql .= " AND status = ?";
    $params[] = $exportStatus;
}

$sql .= " ORDER BY id DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

while($row = $stmt->fetch(PDO::FETCH_ASSOC))
{
    fputcsv($output, [
        $row['ticket_no'] ?? '',
        $row['title'] ?? '',
        $row['branch'] ?? '',
        $row['department'] ?? '',
        $row['category'] ?? '',
        $row['priority'] ?? '',
        $row['status'] ?? '',
        $row['assigned_to'] ?? '',
        $row['due_date'] ?? '',
        $row['created_at'] ?? '',
        $row['last_update'] ?? ($row['updated_at'] ?? ''),
        $row['last_updated_by'] ?? ''
    ]);
}

fclose($output);
exit;
