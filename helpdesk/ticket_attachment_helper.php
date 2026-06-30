<?php
require_once __DIR__ . '/attachment_upload_helper.php';

if(!function_exists('hd_ta_ensure_table')){
function hd_ta_ensure_table(PDO $pdo){
    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        reply_id INT NULL DEFAULT NULL,
        file_path VARCHAR(500) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        mime VARCHAR(120) NULL DEFAULT NULL,
        file_size INT DEFAULT 0,
        created_by INT NULL DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ta_ticket (ticket_id),
        INDEX idx_ta_reply (reply_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
}

if(!function_exists('hd_ta_collect_uploaded_files')){
function hd_ta_collect_uploaded_files(array $filesSuperglobal, array $fieldNames=['attachment','attachments']){
    $out=[];
    foreach($fieldNames as $field){
        if(empty($filesSuperglobal[$field])) continue;
        $f=$filesSuperglobal[$field];
        if(is_array($f['name'] ?? null)){
            $count=count($f['name']);
            for($i=0;$i<$count;$i++){
                $err=(int)($f['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                if($err===UPLOAD_ERR_NO_FILE) continue;
                // Some mobile browsers submit an empty placeholder file when using camera/voice controls.
                // Do not pass zero-byte placeholders into the upload handler, otherwise the page dies with
                // "Attachment is empty." and the ticket/reply cannot continue.
                if($err===UPLOAD_ERR_OK && (int)($f['size'][$i] ?? 0) <= 0) continue;
                $out[]=[
                    'name'=>$f['name'][$i] ?? '',
                    'type'=>$f['type'][$i] ?? '',
                    'tmp_name'=>$f['tmp_name'][$i] ?? '',
                    'error'=>$err,
                    'size'=>$f['size'][$i] ?? 0,
                ];
            }
        }else{
            $err=(int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
            if($err!==UPLOAD_ERR_NO_FILE){
                if($err===UPLOAD_ERR_OK && (int)($f['size'] ?? 0) <= 0) continue;
                $out[]=$f;
            }
        }
    }
    return $out;
}
}

if(!function_exists('hd_ta_has_uploads')){
function hd_ta_has_uploads(array $filesSuperglobal, array $fieldNames=['attachment','attachments']){
    return count(hd_ta_collect_uploaded_files($filesSuperglobal,$fieldNames))>0;
}
}

if(!function_exists('hd_ta_upload_many')){
function hd_ta_upload_many(array $filesSuperglobal, string $uploadDir, array $fieldNames=['attachment','attachments'], int $maxSize=52428800){
    $items=hd_ta_collect_uploaded_files($filesSuperglobal,$fieldNames);
    $saved=[];
    foreach($items as $file){
        if((int)($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){
            die('One attachment upload failed.');
        }
        if((int)($file['size'] ?? 0) <= 0) continue;
        $path=hd_handle_attachment_upload($file,$uploadDir,$maxSize);
        if($path){
            $mime=hd_upload_detect_mime($path, $file['type'] ?? '');
            $savedSize = (int)($file['size'] ?? 0);
            $savedPathForSize = $path;
            if(!is_file($savedPathForSize)) {
                $candidate = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
                if(is_file($candidate)) $savedPathForSize = $candidate;
            }
            if(is_file($savedPathForSize)) $savedSize = (int)filesize($savedPathForSize);

            $saved[]=[
                'path'=>$path,
                'original_name'=>basename((string)($file['name'] ?? basename($path))),
                'mime'=>$mime,
                'size'=>$savedSize,
            ];
        }
    }
    return $saved;
}
}

if(!function_exists('hd_ta_insert_many')){
function hd_ta_insert_many(PDO $pdo, int $ticketId, $replyId, array $attachments, $createdBy=null){
    hd_ta_ensure_table($pdo);
    if(!$attachments) return;
    $stmt=$pdo->prepare("INSERT INTO ticket_attachments (ticket_id,reply_id,file_path,original_name,mime,file_size,created_by,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
    foreach($attachments as $a){
        $stmt->execute([$ticketId, $replyId ?: null, $a['path'], $a['original_name'] ?: basename($a['path']), $a['mime'] ?? null, (int)($a['size'] ?? 0), $createdBy]);
    }
}
}

if(!function_exists('hd_ta_fetch_ticket')){
function hd_ta_fetch_ticket(PDO $pdo, int $ticketId){
    hd_ta_ensure_table($pdo);
    $stmt=$pdo->prepare("SELECT * FROM ticket_attachments WHERE ticket_id=? AND reply_id IS NULL ORDER BY id ASC");
    $stmt->execute([$ticketId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}

if(!function_exists('hd_ta_fetch_by_reply')){
function hd_ta_fetch_by_reply(PDO $pdo, int $ticketId){
    hd_ta_ensure_table($pdo);
    $stmt=$pdo->prepare("SELECT * FROM ticket_attachments WHERE ticket_id=? AND reply_id IS NOT NULL ORDER BY id ASC");
    $stmt->execute([$ticketId]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $out=[];
    foreach($rows as $r){ $out[(int)$r['reply_id']][]=$r; }
    return $out;
}
}
?>
