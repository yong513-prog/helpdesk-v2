<?php
require 'db.php';
require_once 'entity_upload_helper.php';

if(session_status() === PHP_SESSION_NONE) session_start();
if(empty($_SESSION['user_id'])) { die('Login required'); }
if(strtolower((string)($_SESSION['role'] ?? '')) !== 'admin') { die('Admin only'); }

function move_file_to_entity_folder($oldPath, $module, $entityName)
{
    $oldPath = ltrim(str_replace('\\','/', (string)$oldPath), '/');
    if($oldPath === '' || strpos($oldPath, 'uploads/') !== 0) return $oldPath;
    if(strpos($oldPath, 'uploads/'.$module.'/'.hd_safe_folder_name($entityName).'/') === 0) return $oldPath;

    $fullOld = __DIR__ . '/' . $oldPath;
    if(!is_file($fullOld)) return $oldPath;

    $dir = hd_entity_upload_dir($module, $entityName);
    $base = basename($oldPath);
    $target = rtrim($dir, '/\\') . '/' . $base;
    if(is_file($target)) {
        $pi = pathinfo($base);
        $name = $pi['filename'] ?? 'file';
        $ext = isset($pi['extension']) ? '.'.$pi['extension'] : '';
        $target = rtrim($dir, '/\\') . '/' . $name . '_' . bin2hex(random_bytes(3)) . $ext;
        $base = basename($target);
    }
    if(@rename($fullOld, $target)) {
        return hd_entity_upload_relative($module, $entityName, $base);
    }
    if(@copy($fullOld, $target)) {
        @unlink($fullOld);
        return hd_entity_upload_relative($module, $entityName, $base);
    }
    return $oldPath;
}

$moved = 0;

// Tickets main attachments
try {
    $rows = $pdo->query("SELECT id,ticket_no,attachment FROM tickets WHERE attachment IS NOT NULL AND attachment<>''")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("UPDATE tickets SET attachment=? WHERE id=?");
    foreach($rows as $r){
        $new = move_file_to_entity_folder($r['attachment'], 'tickets', $r['ticket_no'] ?: ('TICKET-'.$r['id']));
        if($new !== $r['attachment']) { $stmt->execute([$new, $r['id']]); $moved++; }
    }
} catch(Exception $e) {}

// Ticket reply attachments
try {
    $rows = $pdo->query("SELECT tr.id,tr.attachment,t.ticket_no,tr.ticket_id FROM ticket_replies tr LEFT JOIN tickets t ON t.id=tr.ticket_id WHERE tr.attachment IS NOT NULL AND tr.attachment<>''")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("UPDATE ticket_replies SET attachment=? WHERE id=?");
    foreach($rows as $r){
        $new = move_file_to_entity_folder($r['attachment'], 'tickets', $r['ticket_no'] ?: ('TICKET-'.$r['ticket_id']));
        if($new !== $r['attachment']) { $stmt->execute([$new, $r['id']]); $moved++; }
    }
} catch(Exception $e) {}

// Asset photos
try {
    $rows = $pdo->query("SELECT id,asset_code,asset_photo FROM assets WHERE asset_photo IS NOT NULL AND asset_photo<>''")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("UPDATE assets SET asset_photo=? WHERE id=?");
    foreach($rows as $r){
        $new = move_file_to_entity_folder($r['asset_photo'], 'assets', $r['asset_code'] ?: ('ASSET-'.$r['id']));
        if($new !== $r['asset_photo']) { $stmt->execute([$new, $r['id']]); $moved++; }
    }
} catch(Exception $e) {}

// Knowledge base attachments
try {
    $rows = $pdo->query("SELECT id,article_id,file_path FROM knowledge_base_attachments WHERE file_path IS NOT NULL AND file_path<>''")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("UPDATE knowledge_base_attachments SET file_path=? WHERE id=?");
    foreach($rows as $r){
        $folder = 'KB-' . (int)$r['article_id'];
        $new = move_file_to_entity_folder($r['file_path'], 'knowledge_base', $folder);
        if($new !== $r['file_path']) { $stmt->execute([$new, $r['id']]); $moved++; }
    }
} catch(Exception $e) {}

// Announcements
try {
    $rows = $pdo->query("SELECT id,title,attachment_path FROM announcements WHERE attachment_path IS NOT NULL AND attachment_path<>''")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("UPDATE announcements SET attachment_path=? WHERE id=?");
    foreach($rows as $r){
        $folder = 'ANN-' . (int)$r['id'];
        $new = move_file_to_entity_folder($r['attachment_path'], 'announcements', $folder);
        if($new !== $r['attachment_path']) { $stmt->execute([$new, $r['id']]); $moved++; }
    }
} catch(Exception $e) {}

echo '<h3>Entity upload folder migration completed.</h3>';
echo '<p>Moved files: '.(int)$moved.'</p>';
echo '<p>You can delete this file after migration.</p>';
