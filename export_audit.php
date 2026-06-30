<?php

session_start();

require 'db.php';
require_once 'module_permissions.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

require_action_permission('export_audit');



$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$keyword = trim($_GET['keyword'] ?? '');
$action = $_GET['action'] ?? '';
$username = trim($_GET['username'] ?? '');

$sql = "
SELECT *
FROM audit_logs
WHERE 1=1
";

$params = [];

// Export Audit permission allows full audit export.

if($start_date != '')
{
    $sql .= " AND DATE(created_at) >= ? ";
    $params[] = $start_date;
}

if($end_date != '')
{
    $sql .= " AND DATE(created_at) <= ? ";
    $params[] = $end_date;
}

if($keyword != '')
{
    $sql .= " AND (username LIKE ? OR action LIKE ? OR details LIKE ?) ";
    $params[] = "%".$keyword."%";
    $params[] = "%".$keyword."%";
    $params[] = "%".$keyword."%";
}

if($username != '')
{
    $sql .= " AND username LIKE ? ";
    $params[] = "%".$username."%";
}

if($action != '')
{
    $sql .= " AND action = ? ";
    $params[] = $action;
}

$sql .= "
ORDER BY id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$filename = "audit_logs_" . date("Ymd_His") . ".csv";

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=".$filename);
header("Pragma: no-cache");
header("Expires: 0");

$output = fopen("php://output", "w");

// Excel UTF-8 BOM
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, [
    "Date",
    "User",
    "Action",
    "Details"
]);

foreach($logs as $log)
{
    fputcsv($output, [
        $log['created_at'] ?? '',
        $log['username'] ?? '',
        $log['action'] ?? '',
        $log['details'] ?? ''
    ]);
}

fclose($output);
exit;

?>
