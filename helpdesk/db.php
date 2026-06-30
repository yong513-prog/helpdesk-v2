<?php

date_default_timezone_set('Asia/Kuala_Lumpur');

$host = "localhost";
$dbname = "helpdesk_db";

$host = "localhost";
$dbname = "helpdesk_db";
$user = "root";
$pass = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if(file_exists(__DIR__ . '/remember_me.php')) {
        require_once __DIR__ . '/remember_me.php';
        if(function_exists('hd_restore_remembered_login')) {
            hd_restore_remembered_login($pdo);
        }
    }


} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
