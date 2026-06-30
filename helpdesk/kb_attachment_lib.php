<?php
require_once __DIR__ . '/entity_upload_helper.php';
if (!function_exists('kb_h')) {
    function kb_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('kb_col_exists')) {
    function kb_col_exists($pdo, $table, $col) {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$col]);
            return (bool)$stmt->fetch();
        } catch (Exception $e) { return false; }
    }
}

if (!function_exists('kb_table_exists')) {
    function kb_table_exists($pdo, $table) {
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return (bool)$stmt->fetch();
        } catch (Exception $e) { return false; }
    }
}

if (!function_exists('kb_ensure_attachment_table')) {
    function kb_ensure_attachment_table($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS knowledge_base_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            article_id INT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_size INT DEFAULT 0,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_kb_article_id (article_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('kb_upload_dir')) {
    function kb_upload_dir($articleId = null) {
        $folder = ($articleId !== null && (int)$articleId > 0) ? ('KB-' . (int)$articleId) : 'pending';
        $dir = hd_entity_upload_dir('knowledge_base', $folder);
        $ht = $dir . '/.htaccess';
        if (!file_exists($ht)) {
            @file_put_contents($ht, "Options -Indexes\nphp_flag engine off\nRemoveHandler .php .phtml .php3 .php4 .php5 .phar\n");
        }
        return $dir;
    }
}

if (!function_exists('kb_upload_multiple_attachments')) {
    function kb_upload_multiple_attachments($field = 'attachments', $articleId = null) {
        if (empty($_FILES[$field]) || empty($_FILES[$field]['name'])) return [];
        $files = $_FILES[$field];
        $allowed = ['pdf','jpg','jpeg','png','gif','webp','doc','docx','xls','xlsx','ppt','pptx','txt','csv','zip'];
        $max = 10 * 1024 * 1024;
        $out = [];
        $count = is_array($files['name']) ? count($files['name']) : 0;
        $dir = kb_upload_dir($articleId);

        for ($i=0; $i<$count; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            if ($files['error'][$i] !== UPLOAD_ERR_OK) throw new Exception('One attachment upload failed.');
            if ($files['size'][$i] > $max) throw new Exception('Each attachment cannot exceed 10MB.');

            $original = basename($files['name'][$i]);
            $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                throw new Exception('Allowed files: PDF, image, Word, Excel, PowerPoint, TXT, CSV, ZIP.');
            }
            if (preg_match('/\.(php|phtml|phar|html|htm|js)$/i', $original)) {
                throw new Exception('Unsafe attachment type is not allowed.');
            }

            $safe = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
            $target = $dir . '/' . $safe;
            if (!move_uploaded_file($files['tmp_name'][$i], $target)) {
                throw new Exception('Cannot save attachment.');
            }
            $out[] = [
                'path' => hd_entity_upload_relative('knowledge_base', (($articleId !== null && (int)$articleId > 0) ? ('KB-' . (int)$articleId) : 'pending'), $safe),
                'name' => $original,
                'size' => (int)$files['size'][$i]
            ];
        }
        return $out;
    }
}

if (!function_exists('kb_save_attachments')) {
    function kb_save_attachments($pdo, $articleId, $attachments) {
        if (!$attachments) return;
        $stmt = $pdo->prepare("INSERT INTO knowledge_base_attachments (article_id, file_path, original_name, file_size, uploaded_at) VALUES (?,?,?,?,NOW())");
        foreach ($attachments as $a) {
            $stmt->execute([$articleId, $a['path'], $a['name'], $a['size']]);
        }
        // Keep legacy columns updated with first attachment for old pages.
        if (kb_col_exists($pdo, 'knowledge_base', 'attachment')) {
            $first = $attachments[0];
            if (kb_col_exists($pdo, 'knowledge_base', 'attachment_name')) {
                $pdo->prepare("UPDATE knowledge_base SET attachment=?, attachment_name=? WHERE id=? AND (attachment IS NULL OR attachment='')")
                    ->execute([$first['path'], $first['name'], $articleId]);
            } else {
                $pdo->prepare("UPDATE knowledge_base SET attachment=? WHERE id=? AND (attachment IS NULL OR attachment='')")
                    ->execute([$first['path'], $articleId]);
            }
        }
    }
}

if (!function_exists('kb_get_attachments')) {
    function kb_get_attachments($pdo, $articleId, $article = null) {
        kb_ensure_attachment_table($pdo);
        $stmt = $pdo->prepare("SELECT * FROM knowledge_base_attachments WHERE article_id=? ORDER BY id ASC");
        $stmt->execute([$articleId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows && $article && !empty($article['attachment'])) {
            $rows[] = [
                'id' => 0,
                'article_id' => $articleId,
                'file_path' => $article['attachment'],
                'original_name' => !empty($article['attachment_name']) ? $article['attachment_name'] : basename($article['attachment']),
                'file_size' => 0,
                'uploaded_at' => $article['created_at'] ?? null
            ];
        }
        return $rows;
    }
}

if (!function_exists('kb_delete_attachment')) {
    function kb_delete_attachment($pdo, $articleId, $attachmentId) {
        $stmt = $pdo->prepare("SELECT * FROM knowledge_base_attachments WHERE id=? AND article_id=?");
        $stmt->execute([$attachmentId, $articleId]);
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$a) return false;
        $full = __DIR__ . '/' . ltrim($a['file_path'], '/');
        if (is_file($full)) @unlink($full);
        $pdo->prepare("DELETE FROM knowledge_base_attachments WHERE id=? AND article_id=?")->execute([$attachmentId, $articleId]);

        // Refresh legacy single attachment columns to first remaining attachment.
        if (kb_col_exists($pdo, 'knowledge_base', 'attachment')) {
            $next = kb_get_attachments($pdo, $articleId);
            if ($next) {
                $first = $next[0];
                if (kb_col_exists($pdo, 'knowledge_base', 'attachment_name')) {
                    $pdo->prepare("UPDATE knowledge_base SET attachment=?, attachment_name=? WHERE id=?")
                        ->execute([$first['file_path'], $first['original_name'], $articleId]);
                } else {
                    $pdo->prepare("UPDATE knowledge_base SET attachment=? WHERE id=?")->execute([$first['file_path'], $articleId]);
                }
            } else {
                if (kb_col_exists($pdo, 'knowledge_base', 'attachment_name')) {
                    $pdo->prepare("UPDATE knowledge_base SET attachment=NULL, attachment_name=NULL WHERE id=?")->execute([$articleId]);
                } else {
                    $pdo->prepare("UPDATE knowledge_base SET attachment=NULL WHERE id=?")->execute([$articleId]);
                }
            }
        }
        return true;
    }
}

if (!function_exists('kb_format_size')) {
    function kb_format_size($bytes) {
        $bytes = (int)$bytes;
        if ($bytes <= 0) return '-';
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes/1024,1) . ' KB';
        return round($bytes/1048576,1) . ' MB';
    }
}
?>
