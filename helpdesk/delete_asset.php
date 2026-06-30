<?php
require 'db.php';
require_once 'audit_log.php';
require_once 'module_permissions.php';

if(session_status() === PHP_SESSION_NONE)
{
    session_start();
}

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

require_action_permission('manage_asset');

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    header("Location: asset_list.php");
    exit;
}

$_POST['action'] = $_POST['action'] ?? 'disable';
require __DIR__ . '/asset_status_action.php';
