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

require_action_permission('export_ticket');
ticket_status_ensure_last_update_columns($pdo);

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

if(empty($start_date) || empty($end_date))
{
    die("Please select date range.");
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=ticket_report.csv');

$output = fopen('php://output', 'w');

// Excel UTF-8 BOM
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

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
WHERE DATE(created_at)
BETWEEN ? AND ?
";

$params = [
    $start_date,
    $end_date
];

apply_ticket_access_filter($sql, $params);

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
?>
