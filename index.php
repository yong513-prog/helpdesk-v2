<?php
// WLS Helpdesk direct entry.
// No public landing screen: open Helpdesk -> Login page, or Dashboard when already remembered/logged in.
if(session_status() === PHP_SESSION_NONE)
{
    session_start();
}

require_once __DIR__ . '/db.php';

if(!empty($_SESSION['user_id']))
{
    header('Location: dashboard.php');
    exit;
}

header('Location: login.php');
exit;
