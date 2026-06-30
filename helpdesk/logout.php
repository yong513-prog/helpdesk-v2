<?php

session_start();

require 'db.php';
require_once 'remember_me.php';
require_once 'audit_log.php';

if(isset($_SESSION['user_id']))
{
    audit_log(
        $pdo,
        'Logout',
        'User logged out'
    );
}

if(function_exists('hd_clear_current_remember_token'))
{
    hd_clear_current_remember_token($pdo);
}

session_unset();

session_destroy();

header("Location: login.php");

exit;
