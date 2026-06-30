<?php
// Knowledge Base content auto-translation helper.
// Stores one copy per language so English / Bahasa Melayu / 中文 pages can show translated KB content.

function hd_kb_lang_code() {
    if (function_exists('hd_lang')) {
        $l = hd_lang();
        if ($l === 'bm') return 'ms';
        if (in_array($l, ['en','ms','zh'], true)) return $l;
    }
    $l = $_SESSION['lang'] ?? ($_COOKIE['helpdesk_lang'] ?? 'en');
    if ($l === 'bm') return 'ms';
    return in_array($l, ['en','ms','zh'], true) ? $l : 'en';
}

function hd_kb_target_google($lang) {
    if ($lang === 'zh') return 'zh-CN';
    if ($lang === 'ms' || $lang === 'bm') return 'ms';
    return 'en';
}

function hd_ensure_kb_translation_columns(PDO $pdo) {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $cols = [];
        $stmt = $pdo->query("SHOW COLUMNS FROM knowledge_base");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) $cols[$c['Field']] = true;
        $alters = [];
        foreach (['en','ms','zh'] as $lng) {
            if (empty($cols['title_'.$lng])) $alters[] = "ADD COLUMN title_{$lng} TEXT NULL";
            if (empty($cols['content_'.$lng])) $alters[] = "ADD COLUMN content_{$lng} LONGTEXT NULL";
        }
        if ($alters) $pdo->exec("ALTER TABLE knowledge_base ".implode(', ', $alters));
    } catch (Exception $e) {
        // Keep system usable even if database user has no ALTER permission.
    }
}

function hd_kb_google_translate_text($text, $targetLang) {
    $text = trim((string)$text);
    if ($text === '') return '';
    $target = hd_kb_target_google($targetLang);
    $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=' . rawurlencode($target) . '&dt=t&q=' . rawurlencode($text);
    $json = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 HelpdeskTranslate/1.0'
        ]);
        $json = curl_exec($ch);
        curl_close($ch);
    }

    if ($json === false || $json === '') {
        $ctx = stream_context_create(['http'=>['timeout'=>12,'header'=>"User-Agent: Mozilla/5.0 HelpdeskTranslate/1.0\r\n"]]);
        $json = @file_get_contents($url, false, $ctx);
    }

    if (!$json) return $text;
    $data = json_decode($json, true);
    if (!is_array($data) || empty($data[0]) || !is_array($data[0])) return $text;

    $out = '';
    foreach ($data[0] as $piece) {
        if (isset($piece[0])) $out .= $piece[0];
    }
    return trim($out) !== '' ? $out : $text;
}

function hd_build_kb_translations($title, $content) {
    $result = [];
    foreach (['en','ms','zh'] as $lng) {
        $result['title_'.$lng] = hd_kb_google_translate_text($title, $lng);
        $result['content_'.$lng] = hd_kb_google_translate_text($content, $lng);
    }
    return $result;
}

function hd_pick_kb_field(PDO $pdo, array $row, $base) {
    hd_ensure_kb_translation_columns($pdo);
    $lang = hd_kb_lang_code();
    $field = $base . '_' . $lang;
    if (!empty($row[$field])) return $row[$field];

    $original = (string)($row[$base] ?? '');
    if ($original === '') return '';

    // Auto-translate old KB articles on first view, then save for future use.
    $translated = hd_kb_google_translate_text($original, $lang);
    if (!empty($row['id']) && $translated !== '') {
        try {
            $stmt = $pdo->prepare("UPDATE knowledge_base SET {$field} = ? WHERE id = ?");
            $stmt->execute([$translated, (int)$row['id']]);
        } catch (Exception $e) {}
    }
    return $translated !== '' ? $translated : $original;
}

function hd_kb_title(PDO $pdo, array $row) {
    return hd_pick_kb_field($pdo, $row, 'title');
}

function hd_kb_content(PDO $pdo, array $row) {
    return hd_pick_kb_field($pdo, $row, 'content');
}

function hd_kb_display_text($text) {
    $text = (string)($text ?? '');
    if ($text === '' || !function_exists('hd_lang')) return $text;
    $lang = hd_lang();
    if ($lang === 'en') return $text;
    // Display-only translation for short KB data such as tags. It does not change saved database values.
    if (function_exists('hd_kb_google_translate_text')) {
        $out = hd_kb_google_translate_text($text, $lang);
        return $out !== '' ? $out : $text;
    }
    return $text;
}
?>
