<?php
/**
 * Helpdesk Auto Translator V1
 * Place this file in Helpdesk root folder and run:
 *   php auto_translate_hardcoded.php --dry-run
 *   php auto_translate_hardcoded.php --apply
 *
 * It replaces simple hardcoded UI text with <?= __('Text') ?> safely for common HTML patterns.
 * It creates backups in _translation_backup_YYYYmmdd_HHMMSS before applying changes.
 */

$root = __DIR__;
$mode = in_array('--apply', $argv, true) ? 'apply' : 'dry-run';
$timestamp = date('Ymd_His');
$backupDir = $root . DIRECTORY_SEPARATOR . '_translation_backup_' . $timestamp;
$reportHtml = $root . DIRECTORY_SEPARATOR . 'auto_translate_report.html';
$reportCsv = $root . DIRECTORY_SEPARATOR . 'auto_translate_report.csv';

$skipDirs = ['vendor','node_modules','.git','storage','uploads','backup','backups'];
$skipFiles = [basename(__FILE__), 'scan_translation.php', 'translation_report.html', 'auto_translate_report.html', 'lang.php'];

$targetFiles = [];
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file){
    if(!$file->isFile()) continue;
    $path = $file->getPathname();
    $rel = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
    $parts = preg_split('/[\\\\\/]+/', $rel);
    if(array_intersect($parts, $skipDirs)) continue;
    if(in_array(basename($path), $skipFiles, true)) continue;
    if(strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') continue;
    $targetFiles[] = $path;
}

function at_e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function at_clean($text){
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function at_is_ui_text($text){
    $t = at_clean($text);
    if($t === '' || strlen($t) < 2) return false;
    if(strpos($t, '<?') !== false || strpos($t, '?>') !== false) return false;
    if(preg_match('/^[\d\s\-:.,%\/]+$/', $t)) return false;
    if(preg_match('/^[A-Z0-9_\-]{1,12}$/', $t)) return false; // branch / code
    if(preg_match('/^(PC|HQ|KB|KC|KJ|KK|KL|KR|KS|ML|PJ|PKL|PM|SE|TM|TPC|TPN|TPT|WC|WK|BS|LAS)$/i', $t)) return false;
    if(preg_match('/^(class|href|src|id|name|type|value|required|selected|checked)$/i', $t)) return false;
    if(preg_match('/[{};=]/', $t)) return false;
    if(strlen($t) > 160) return false;
    return preg_match('/[A-Za-z]/', $t) === 1;
}

function at_php_quote($text){
    return str_replace(["\\", "'"], ["\\\\", "\\'"], $text);
}

function at_already_translated_near($chunk){
    return preg_match('/__\s*\(|hd_t\s*\(|\bt\s*\(/', $chunk) || strpos($chunk, 'notranslate') !== false || strpos($chunk, 'translate="no"') !== false || strpos($chunk, "translate='no'") !== false;
}

function at_replace_text_nodes($content, &$changes, $rel){
    return preg_replace_callback('/>([^<>]*[A-Za-z][^<>]*)</u', function($m) use (&$changes, $rel){
        $raw = $m[1];
        $text = at_clean($raw);
        if(!at_is_ui_text($text)) return $m[0];
        $full = $m[0];
        if(at_already_translated_near($full)) return $m[0];
        if(preg_match('/^\s*$/', $raw)) return $m[0];
        $leading = preg_match('/^\s+/', $raw, $lm) ? $lm[0] : '';
        $trailing = preg_match('/\s+$/', $raw, $tm) ? $tm[0] : '';
        $replacement = '>' . $leading . "<?= __('" . at_php_quote($text) . "') ?>" . $trailing . '<';
        $changes[] = ['file'=>$rel, 'type'=>'HTML text', 'from'=>$text, 'to'=>$replacement];
        return $replacement;
    }, $content);
}

function at_replace_attributes($content, &$changes, $rel){
    $attrs = ['placeholder','title','alt','aria-label'];
    foreach($attrs as $attr){
        $pattern = '/\b'.preg_quote($attr,'/').'\s*=\s*(["\'])([^"\']*[A-Za-z][^"\']*)\1/iu';
        $content = preg_replace_callback($pattern, function($m) use (&$changes, $rel, $attr){
            $text = at_clean($m[2]);
            if(!at_is_ui_text($text)) return $m[0];
            if(strpos($m[0], '<?=') !== false || strpos($m[0], '<?php') !== false) return $m[0];
            $replacement = $attr . '="<?= __(\'' . at_php_quote($text) . '\') ?>"';
            $changes[] = ['file'=>$rel, 'type'=>$attr, 'from'=>$text, 'to'=>$replacement];
            return $replacement;
        }, $content);
    }
    return $content;
}

function at_replace_js_dialogs($content, &$changes, $rel){
    $pattern = '/\b(alert|confirm|prompt)\s*\(\s*(["\'])([^"\']*[A-Za-z][^"\']*)\2\s*\)/iu';
    return preg_replace_callback($pattern, function($m) use (&$changes, $rel){
        $fn = $m[1];
        $text = at_clean($m[3]);
        if(!at_is_ui_text($text)) return $m[0];
        $replacement = $fn . "(<?= json_encode(__('" . at_php_quote($text) . "')) ?>)";
        $changes[] = ['file'=>$rel, 'type'=>'JS dialog', 'from'=>$text, 'to'=>$replacement];
        return $replacement;
    }, $content);
}

function at_replace_die_exit($content, &$changes, $rel){
    $pattern = '/\b(die|exit)\s*\(\s*(["\'])([^"\']*[A-Za-z][^"\']*)\2\s*\)\s*;/iu';
    return preg_replace_callback($pattern, function($m) use (&$changes, $rel){
        $fn = $m[1];
        $text = at_clean($m[3]);
        if(!at_is_ui_text($text)) return $m[0];
        $replacement = $fn . "(__('" . at_php_quote($text) . "'));";
        $changes[] = ['file'=>$rel, 'type'=>'PHP die/exit', 'from'=>$text, 'to'=>$replacement];
        return $replacement;
    }, $content);
}

function at_replace_echo_print($content, &$changes, $rel){
    $pattern = '/\b(echo|print)\s+(["\'])([^"\']*[A-Za-z][^"\']*)\2\s*;/iu';
    return preg_replace_callback($pattern, function($m) use (&$changes, $rel){
        $cmd = $m[1];
        $text = at_clean($m[3]);
        if(!at_is_ui_text($text)) return $m[0];
        $replacement = $cmd . " __('" . at_php_quote($text) . "');";
        $changes[] = ['file'=>$rel, 'type'=>'PHP '.$cmd, 'from'=>$text, 'to'=>$replacement];
        return $replacement;
    }, $content);
}

$allChanges = [];
$filesChanged = 0;
foreach($targetFiles as $path){
    $rel = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
    $content = file_get_contents($path);
    $original = $content;
    $changes = [];

    $content = at_replace_text_nodes($content, $changes, $rel);
    $content = at_replace_attributes($content, $changes, $rel);
    $content = at_replace_js_dialogs($content, $changes, $rel);
    $content = at_replace_die_exit($content, $changes, $rel);
    $content = at_replace_echo_print($content, $changes, $rel);

    if($content !== $original && $changes){
        $filesChanged++;
        foreach($changes as $c) $allChanges[] = $c;
        if($mode === 'apply'){
            $backupPath = $backupDir . DIRECTORY_SEPARATOR . $rel;
            if(!is_dir(dirname($backupPath))) mkdir(dirname($backupPath), 0777, true);
            copy($path, $backupPath);
            file_put_contents($path, $content);
        }
    }
}

$csv = fopen($reportCsv, 'w');
fputcsv($csv, ['file','type','from','suggested']);
foreach($allChanges as $c){ fputcsv($csv, [$c['file'],$c['type'],$c['from'],$c['to']]); }
fclose($csv);

$grouped = [];
foreach($allChanges as $c) $grouped[$c['file']][] = $c;
$html = '<!doctype html><html><head><meta charset="utf-8"><title>Helpdesk Auto Translator Report</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#f6f8fb;color:#0f172a;padding:24px}.wrap{max-width:1300px;margin:auto}.hero,.card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:18px;margin:14px 0;box-shadow:0 10px 24px rgba(15,23,42,.06)}.hero{background:linear-gradient(135deg,#0f172a,#2563eb);color:#fff}.item{border-top:1px solid #eef2f7;padding:12px 0}.badge{display:inline-block;border-radius:999px;background:#dbeafe;color:#1d4ed8;padding:4px 9px;font-size:12px;font-weight:800}.code{background:#0f172a;color:#e2e8f0;border-radius:10px;padding:10px;overflow:auto;font-family:Consolas,monospace;font-size:12px}.ok{color:#16a34a;font-weight:900}.warn{color:#f59e0b;font-weight:900}</style></head><body><div class="wrap">';
$html .= '<div class="hero"><h1>Helpdesk Auto Translator V1</h1><p>Mode: <b>'.at_e($mode).'</b> | Scanned files: '.count($targetFiles).' | Files with changes: '.$filesChanged.' | Changes: '.count($allChanges).' | Generated: '.date('Y-m-d H:i:s').'</p>';
$html .= $mode === 'apply' ? '<p class="ok">Applied. Backup folder created: '.at_e(basename($backupDir)).'</p>' : '<p class="warn">Dry-run only. No files changed. Run <b>php auto_translate_hardcoded.php --apply</b> to apply.</p>';
$html .= '<p>Report CSV: auto_translate_report.csv</p></div>';
if(!$grouped){
    $html .= '<div class="card"><h2>No safe auto-translation candidates found.</h2></div>';
}else{
    foreach($grouped as $file=>$items){
        $html .= '<div class="card"><h2>'.at_e($file).' <span class="badge">'.count($items).' changes</span></h2>';
        foreach($items as $it){
            $html .= '<div class="item"><div><span class="badge">'.at_e($it['type']).'</span></div><h3>'.at_e($it['from']).'</h3><div class="code">'.at_e($it['to']).'</div></div>';
        }
        $html .= '</div>';
    }
}
$html .= '</div></body></html>';
file_put_contents($reportHtml, $html);

echo "Helpdesk Auto Translator V1\n";
echo "Mode: {$mode}\n";
echo "Scanned files: ".count($targetFiles)."\n";
echo "Files with changes: {$filesChanged}\n";
echo "Changes: ".count($allChanges)."\n";
if($mode === 'apply') echo "Backup: {$backupDir}\n";
echo "Report: {$reportHtml}\n";
echo "CSV: {$reportCsv}\n";
