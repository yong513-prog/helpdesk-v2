<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require 'db.php';
require_once __DIR__ . '/lang.php';
require_once 'notification_helper.php';

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['unread'=>0,'latest'=>[]]);
    exit;
}

notification_ensure_schema($pdo);
$userId = (int)$_SESSION['user_id'];

function notif_icon($type) {
    $type = strtolower((string)$type);
    if(strpos($type,'ticket') !== false) return 'bi-ticket-perforated';
    if(strpos($type,'announcement') !== false) return 'bi-megaphone';
    if(strpos($type,'kb') !== false || strpos($type,'knowledge') !== false) return 'bi-book';
    if(strpos($type,'asset') !== false) return 'bi-pc-display';
    return 'bi-bell';
}

try {
    if(function_exists('notification_visible_rows')) {
        $rows = notification_visible_rows($pdo, $userId, 8, false);
        $unread = count(notification_visible_rows($pdo, $userId, 500, true));
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
        $stmt->execute([$userId]);
        $unread = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT id, type, title, message, url, is_read, created_at
            FROM notifications
            WHERE user_id=?
            ORDER BY is_read ASC, created_at DESC, id DESC
            LIMIT 8
        ");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach($rows as &$r) {
        $r['icon'] = notif_icon($r['type'] ?? '');
        $r['title'] = function_exists('notification_i18n_text') ? notification_i18n_text((string)($r['title'] ?? '')) : (string)($r['title'] ?? '');
        $r['message'] = mb_substr(function_exists('notification_i18n_text') ? notification_i18n_text((string)($r['message'] ?? '')) : (string)($r['message'] ?? ''), 0, 160);
        $r['url'] = (string)($r['url'] ?? 'notifications.php');
        if(!empty($r['created_at'])) {
            $r['created_at'] = date('d/m/Y h:i A', strtotime($r['created_at']));
        }
    }

    echo json_encode(['unread'=>$unread,'latest'=>$rows], JSON_UNESCAPED_UNICODE);
} catch(Exception $e) {
    echo json_encode(['unread'=>0,'latest'=>[]]);
}
