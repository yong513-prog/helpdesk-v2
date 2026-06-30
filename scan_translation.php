<?php
/**
 * Helpdesk Translation Scanner V2
 * Put this file in Helpdesk root folder and run:
 *   php scan_translation.php
 * Output:
 *   translation_report.html
 *   translation_missing.csv
 *   lang_missing_keys.php
 */

$root = __DIR__;
$outHtml = $root . DIRECTORY_SEPARATOR . 'translation_report.html';
$outCsv = $root . DIRECTORY_SEPARATOR . 'translation_missing.csv';
$outKeys = $root . DIRECTORY_SEPARATOR . 'lang_missing_keys.php';
$startTime = microtime(true);

$allowedExt = ['php'];
$skipDirs = ['vendor','node_modules','.git','storage','uploads','upload','backup','backups','cache','tmp','logs','assets','dist','build'];
$skipFiles = ['scan_translation.php','translation_report.html','translation_missing.csv','lang_missing_keys.php'];

$uiFilesPriority = [
    'dashboard.php','ticket_list.php','closed_tickets.php','overdue.php','view_ticket.php','create_ticket.php','edit_ticket.php',
    'report_kpi.php','audit_logs.php','announcements.php','add_announcement.php','edit_announcement.php','announcement_read_report.php',
    'knowledge_base.php','add_article.php','edit_article.php','asset_list.php','add_asset.php','edit_asset.php',
    'users.php','add_user.php','edit_user.php','administration.php'
];

$patterns = [
    ['regex'=>'/>([^<]*[A-Za-z][^<]*)</u', 'type'=>'HTML text', 'group'=>1],
    ['regex'=>'/\bplaceholder\s*=\s*(["\'])([^"\']*[A-Za-z][^"\']*)\1/iu', 'type'=>'placeholder', 'group'=>2],
    ['regex'=>'/\btitle\s*=\s*(["\'])([^"\']*[A-Za-z][^"\']*)\1/iu', 'type'=>'title attribute', 'group'=>2],
    ['regex'=>'/\balt\s*=\s*(["\'])([^"\']*[A-Za-z][^"\']*)\1/iu', 'type'=>'alt attribute', 'group'=>2],
    ['regex'=>'/\baria-label\s*=\s*(["\'])([^"\']*[A-Za-z][^"\']*)\1/iu', 'type'=>'aria-label', 'group'=>2],
    ['regex'=>'/\bdata-bs-title\s*=\s*(["\'])([^"\']*[A-Za-z][^"\']*)\1/iu', 'type'=>'tooltip', 'group'=>2],
    ['regex'=>'/\becho\s+(["\'])([^"\']*[A-Za-z][^"\']*)\1\s*;/iu', 'type'=>'PHP echo', 'group'=>2],
    ['regex'=>'/\bprint\s+(["\'])([^"\']*[A-Za-z][^"\']*)\1\s*;/iu', 'type'=>'PHP print', 'group'=>2],
    ['regex'=>'/\b(?:die|exit)\s*\(\s*(["\'])([^"\']*[A-Za-z][^"\']*)\1\s*\)/iu', 'type'=>'PHP die/exit', 'group'=>2],
    ['regex'=>'/new\s+Exception\s*\(\s*(["\'])([^"\']*[A-Za-z][^"\']*)\1/iu', 'type'=>'Exception', 'group'=>2],
    ['regex'=>'/\b(?:alert|confirm|prompt)\s*\(\s*(["\'`])([^"\'`]*[A-Za-z][^"\'`]*)\1\s*\)/iu', 'type'=>'JS dialog', 'group'=>2],
    ['regex'=>'/Swal\.fire\s*\(\s*(["\'`])([^"\'`]*[A-Za-z][^"\'`]*)\1/iu', 'type'=>'SweetAlert', 'group'=>2],
    ['regex'=>'/toastr\.(?:success|error|warning|info)\s*\(\s*(["\'`])([^"\'`]*[A-Za-z][^"\'`]*)\1/iu', 'type'=>'Toast', 'group'=>2],
    ['regex'=>'/(?:text|title|message|label|buttonText|confirmButtonText|cancelButtonText)\s*:\s*(["\'`])([^"\'`]*[A-Za-z][^"\'`]*)\1/iu', 'type'=>'JS object', 'group'=>2],
    ['regex'=>'/["\'](?:message|error|success|title|label|text)["\']\s*=>\s*(["\'])([^"\']*[A-Za-z][^"\']*)\1/iu', 'type'=>'PHP array text', 'group'=>2],
];

$ignoreExact = array_flip(array_map('strtolower', [
    'UTF-8','GET','POST','SESSION','COOKIE','REQUEST','SERVER','FILES','TRUE','FALSE','NULL','HTML','HEAD','BODY','SCRIPT','STYLE',
    'BI','BTN','FORM','TABLE','DIV','SPAN','CLASS','HREF','SRC','ID','NAME','TYPE','SUBMIT','RESET','BUTTON','CHECKBOX','RADIO','TEXT',
    'PHP','JS','CSS','PNG','JPG','JPEG','GIF','SVG','PDF','XLSX','DOCX','CSV','ZIP','RAR','JSON','XML','MYSQL','PDO','SQL',
    'LOCALHOST','HTTP','HTTPS','CLOUDFLARE','BOOTSTRAP','CHART.JS','JQUERY','SELECT2','DATATABLES','FLATPICKR',
    'ADMIN','HEAD','STAFF','PIC','SLA','KPI','POS','KB','HQ','BS','PC','KC','KJ','KK','KL','KR','KS','ML','PJ','PKL','PM','SE','TM','TPC','TPN','TPT','WC','WK','LAS','YAP'
]));

$dbFieldPattern = '/^(id|user_id|ticket_id|ticket_no|asset_id|branch_id|status_id|created_at|updated_at|deleted_at|closed_at|last_update|due_date|serial_no|asset_code|asset_name|full_name|username|password|email|role|department|branch|status|priority|category|description|title|content|action|module|permission|is_active|is_closed|read_at)$/i';
$codeFragmentPattern = '/(\$|=>|->|::|\$_|\?>|<\?php|function\s|foreach\s|if\s*\(|while\s*\(|return\s|array\s*\(|\bSELECT\b|\bFROM\b|\bWHERE\b|\bJOIN\b)/i';
$branchOrCodePattern = '/^([A-Z]{1,5}|[A-Z]{1,5}-\d{4,}|[A-Z]{1,5}-\d{6}-\d+|[A-Z0-9_\-\.\/]{2,14})$/';

function should_skip_dir_v2(string $rel, array $skipDirs): bool {
    $parts = preg_split('/[\\\/]+/', $rel);
    foreach($parts as $p){ if(in_array($p, $skipDirs, true)) return true; }
    return false;
}

function clean_text_v2(string $text): string {
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/<\?php.*?\?>/s', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function line_is_translated_v2(string $line, string $match): bool {
    $pos = strpos($line, $match);
    if($pos === false) $pos = 0;
    $near = substr($line, max(0, $pos - 140), 280 + strlen($match));
    if(preg_match('/(__|hd_t|t|trans|translate)\s*\(/', $near)) return true;
    if(stripos($near, 'no-i18n') !== false || stripos($near, 'notranslate') !== false || stripos($near, 'translate="no"') !== false) return true;
    return false;
}

function looks_ignorable_v2(string $text, string $line, array $ignoreExact, string $dbFieldPattern, string $codeFragmentPattern, string $branchOrCodePattern): bool {
    $t = trim($text);
    if($t === '' || mb_strlen($t) < 2) return true;
    if(mb_strlen($t) > 180) return true;
    if(isset($ignoreExact[strtolower($t)])) return true;
    if(preg_match($dbFieldPattern, $t)) return true;
    if(preg_match($branchOrCodePattern, $t)) return true;
    if(preg_match('/^[\d\s\-:\/\.%,]+$/', $t)) return true;
    if(preg_match('/^[a-z0-9_\-\.]+$/', $t) && strlen($t) <= 28) return true;
    if(preg_match('/^#[0-9a-f]{3,8}$/i', $t)) return true;
    if(strpos($t, '{{') !== false || strpos($t, '}}') !== false) return true;
    if(strpos($t, '<?=') !== false || strpos($t, '<?php') !== false) return true;
    if(preg_match('/(__|hd_t|t|trans|translate)\s*\(/', $t)) return true;
    if(preg_match($codeFragmentPattern, $t)) return true;
    if(preg_match('/^(btn|col|row|container|card|modal|table|form|text|bg|border|rounded|shadow|d-flex|align|justify|gap|mt|mb|ms|me|px|py)-/i', $t)) return true;
    if(preg_match('/\.(php|js|css|png|jpg|jpeg|gif|svg|pdf|xlsx|docx|zip|rar)$/i', $t)) return true;
    if(preg_match('/^[A-Z][a-z]+\/[A-Z][a-z]+$/', $t)) return true;
    if(stripos($line, 'password_hash') !== false || stripos($line, 'password_verify') !== false) return true;
    return false;
}

function severity_v2(string $type, string $text, string $file): string {
    $criticalWords = ['Due Soon','Overdue','Previous','Next','records','Edited','announcement','Changed','Deleted','Created','Save','Cancel','Search','Filter','Submit','Error','Success','Warning'];
    foreach($criticalWords as $w){ if(stripos($text, $w) !== false) return 'High'; }
    if(in_array($type, ['JS dialog','SweetAlert','Toast','PHP die/exit','Exception'], true)) return 'High';
    if(preg_match('/(ticket|dashboard|audit|report|announcement|asset|user|knowledge)/i', $file)) return 'Medium';
    return 'Low';
}

function suggestion_v2(string $type, string $text): string {
    $safe = str_replace("'", "\\'", $text);
    if(in_array($type, ['HTML text','placeholder','title attribute','alt attribute','aria-label','tooltip'], true)) return "<?= __('{$safe}') ?>";
    if(in_array($type, ['JS dialog','SweetAlert','Toast','JS object'], true)) return "<?= json_encode(__('{$safe}')) ?>";
    return "__('{$safe}')";
}

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$findings = [];
$fileCount = 0;
$typeCount = [];
$fileStats = [];

foreach($rii as $file){
    if(!$file->isFile()) continue;
    $path = $file->getPathname();
    $rel = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
    if(should_skip_dir_v2($rel, $skipDirs)) continue;
    if(in_array(basename($path), $skipFiles, true)) continue;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if(!in_array($ext, $allowedExt, true)) continue;

    $fileCount++;
    $lines = @file($path);
    if(!$lines) continue;

    foreach($lines as $lineNo => $line){
        if(stripos($line, 'no-i18n') !== false || stripos($line, 'notranslate') !== false || stripos($line, 'translate="no"') !== false) continue;
        // Skip pure comments
        if(preg_match('/^\s*(\/\/|#|\*)/', $line)) continue;
        foreach($patterns as $p){
            if(preg_match_all($p['regex'], $line, $matches, PREG_SET_ORDER)){
                foreach($matches as $m){
                    $raw = $m[$p['group']] ?? '';
                    $txt = clean_text_v2($raw);
                    if(looks_ignorable_v2($txt, $line, $ignoreExact, $dbFieldPattern, $codeFragmentPattern, $branchOrCodePattern)) continue;
                    if(line_is_translated_v2($line, $raw) || line_is_translated_v2($line, $txt)) continue;
                    $sev = severity_v2($p['type'], $txt, $rel);
                    $findings[] = [
                        'file'=>$rel,
                        'line'=>$lineNo+1,
                        'type'=>$p['type'],
                        'severity'=>$sev,
                        'text'=>$txt,
                        'suggestion'=>suggestion_v2($p['type'], $txt),
                        'code'=>trim($line),
                    ];
                    $typeCount[$p['type']] = ($typeCount[$p['type']] ?? 0) + 1;
                    $fileStats[$rel] = ($fileStats[$rel] ?? 0) + 1;
                }
            }
        }
    }
}

usort($findings, function($a,$b) use ($uiFilesPriority){
    $ai = array_search(basename($a['file']), $uiFilesPriority, true); if($ai === false) $ai = 999;
    $bi = array_search(basename($b['file']), $uiFilesPriority, true); if($bi === false) $bi = 999;
    return [$ai,$a['file'],$a['line']] <=> [$bi,$b['file'],$b['line']];
});
arsort($fileStats);
arsort($typeCount);

$grouped = [];
foreach($findings as $f){ $grouped[$f['file']][] = $f; }
$elapsed = microtime(true) - $startTime;

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function badgeClass($sev){ return $sev === 'High' ? 'high' : ($sev === 'Medium' ? 'med' : 'low'); }

// CSV export
$csv = fopen($outCsv, 'w');
fputcsv($csv, ['file','line','type','severity','text','suggestion','code']);
foreach($findings as $f){ fputcsv($csv, [$f['file'],$f['line'],$f['type'],$f['severity'],$f['text'],$f['suggestion'],$f['code']]); }
fclose($csv);

// lang missing key template
$uniqueTexts = [];
foreach($findings as $f){ $uniqueTexts[$f['text']] = true; }
$keyPhp = "<?php\n// Auto generated by Translation Scanner V2\n// Copy these into lang.php and translate manually.\n\n";
$keyPhp .= "// English\n";
foreach(array_keys($uniqueTexts) as $txt){ $keyPhp .= "'".str_replace("'", "\\'", $txt)."' => '".str_replace("'", "\\'", $txt)."',\n"; }
$keyPhp .= "\n// Bahasa Melayu / 中文: translate the same keys manually.\n";
file_put_contents($outKeys, $keyPhp);

$totalFindings = count($findings);
$coverage = $fileCount > 0 ? max(0, round(100 - (($totalFindings / max(1, $fileCount * 25)) * 100), 1)) : 0;
$topRows = array_slice($fileStats, 0, 10, true);

$html = '<!doctype html><html><head><meta charset="utf-8"><title>Helpdesk Translation Scanner V2 Report</title>';
$html .= '<style>
:root{--bg:#f5f7fb;--card:#fff;--line:#e5e7eb;--text:#0f172a;--muted:#64748b;--blue:#2563eb;--green:#16a34a;--red:#dc2626;--orange:#f59e0b;--purple:#7c3aed}
*{box-sizing:border-box}body{font-family:Arial,Helvetica,sans-serif;background:var(--bg);color:var(--text);margin:0;padding:24px}.wrap{max-width:1480px;margin:auto}.hero{background:linear-gradient(135deg,#0b2a5b,#0f459d);color:#fff;border-radius:22px;padding:24px;margin-bottom:18px;box-shadow:0 18px 38px rgba(15,23,42,.18)}h1{margin:0 0 8px;font-size:30px}.meta{opacity:.9}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin:18px 0}.kpi{background:#fff;border:1px solid var(--line);border-radius:18px;padding:18px;box-shadow:0 10px 24px rgba(15,23,42,.06)}.kpi span{display:block;color:var(--muted);font-weight:800;font-size:13px}.kpi strong{font-size:32px;display:block;margin-top:8px}.layout{display:grid;grid-template-columns:1fr 1fr;gap:16px}.card{background:#fff;border:1px solid var(--line);border-radius:18px;margin:14px 0;overflow:hidden;box-shadow:0 10px 24px rgba(15,23,42,.05)}.head{padding:14px 16px;background:#f8fafc;border-bottom:1px solid var(--line);font-weight:900;display:flex;justify-content:space-between;gap:10px}.item{padding:12px 16px;border-bottom:1px solid #eef2f7}.item:last-child{border-bottom:0}.line{font-weight:900;color:var(--blue)}.pill{display:inline-block;border-radius:999px;padding:4px 9px;font-size:12px;font-weight:900;margin-left:8px}.type{background:#dbeafe;color:#1d4ed8}.high{background:#fee2e2;color:#991b1b}.med{background:#fef3c7;color:#92400e}.low{background:#dcfce7;color:#166534}.text{font-weight:900;margin:7px 0}.code,.suggest{background:#0f172a;color:#e2e8f0;padding:10px;border-radius:10px;overflow:auto;font-family:Consolas,monospace;font-size:12px}.suggest{background:#ecfdf5;color:#14532d}.tips{background:#ecfdf5;border:1px solid #bbf7d0;color:#166534;border-radius:14px;padding:14px;margin-top:12px}.warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:14px;padding:14px;margin-top:12px}.searchbar{display:flex;gap:10px;margin:14px 0}.searchbar input,.searchbar select{padding:11px;border:1px solid var(--line);border-radius:12px;min-width:180px}.btn{display:inline-block;text-decoration:none;background:var(--blue);color:#fff;border-radius:12px;padding:10px 14px;font-weight:900}.btn.green{background:var(--green)}table{width:100%;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #eef2f7;text-align:left;font-size:13px}th{background:#f8fafc;color:#334155}.bar{height:9px;background:#e5e7eb;border-radius:999px;overflow:hidden}.bar span{display:block;height:100%;background:linear-gradient(90deg,#2563eb,#06b6d4)}@media(max-width:1000px){.grid{grid-template-columns:1fr 1fr}.layout{grid-template-columns:1fr}}@media(max-width:640px){.grid{grid-template-columns:1fr}.searchbar{flex-direction:column}}
</style></head><body><div class="wrap">';
$html .= '<div class="hero"><h1>Helpdesk Translation Scanner V2</h1><div class="meta">Generated: '.e(date('Y-m-d H:i:s')).' | Time: '.e(number_format($elapsed,2)).'s</div><div class="tips">V2 已过滤变量、数据库字段、分行代号、工单号、已使用 __()/hd_t()/t() 的文字。报告中的项目仍需人工确认：确认是界面文字后，改成 <b>&lt;?= __(\'Text\') ?&gt;</b>，再到 lang.php 加三语言。</div><div class="warn">分行代号、资产编号、工单号等系统数据，不要翻译。可在对应行加 <b>// no-i18n</b> 或 HTML 加 <b>translate="no"</b>。</div></div>';
$html .= '<div class="grid"><div class="kpi"><span>Scanned PHP Files</span><strong>'.e($fileCount).'</strong></div><div class="kpi"><span>Possible Untranslated</span><strong>'.e($totalFindings).'</strong></div><div class="kpi"><span>Affected Files</span><strong>'.e(count($fileStats)).'</strong></div><div class="kpi"><span>Estimated Coverage</span><strong>'.e($coverage).'%</strong></div><div class="kpi"><span>Unique Keys</span><strong>'.e(count($uniqueTexts)).'</strong></div></div>';
$html .= '<div class="searchbar"><input id="q" placeholder="Search text / file..." onkeyup="filterItems()"><select id="sev" onchange="filterItems()"><option value="">All severity</option><option>High</option><option>Medium</option><option>Low</option></select><a class="btn green" href="translation_missing.csv">Download CSV</a><a class="btn" href="lang_missing_keys.php">Lang Key Template</a></div>';
$html .= '<div class="layout"><div class="card"><div class="head">Top 10 Files</div><table><tr><th>File</th><th>Items</th><th>Bar</th></tr>';
$maxTop = max(1, reset($topRows) ?: 1);
foreach($topRows as $file=>$cnt){ $w = round($cnt/$maxTop*100); $html .= '<tr><td>'.e($file).'</td><td><b>'.e($cnt).'</b></td><td><div class="bar"><span style="width:'.$w.'%"></span></div></td></tr>'; }
$html .= '</table></div><div class="card"><div class="head">Type Summary</div><table><tr><th>Type</th><th>Items</th></tr>';
foreach($typeCount as $type=>$cnt){ $html .= '<tr><td>'.e($type).'</td><td><b>'.e($cnt).'</b></td></tr>'; }
$html .= '</table></div></div>';

if(!$findings){
    $html .= '<div class="card"><div class="head">No obvious untranslated English text found.</div></div>';
}else{
    foreach($grouped as $file => $items){
        $html .= '<div class="card file-card" data-file="'.e(strtolower($file)).'"><div class="head"><span>'.e($file).' <span class="pill type">'.count($items).' item(s)</span></span></div>';
        foreach($items as $it){
            $html .= '<div class="item finding" data-text="'.e(strtolower($it['text'].' '.$it['file'].' '.$it['type'].' '.$it['severity'])).'" data-sev="'.e($it['severity']).'">';
            $html .= '<div><span class="line">Line '.e($it['line']).'</span><span class="pill type">'.e($it['type']).'</span><span class="pill '.badgeClass($it['severity']).'">'.e($it['severity']).'</span></div>';
            $html .= '<div class="text">'.e($it['text']).'</div><div class="code">'.e($it['code']).'</div><div class="suggest">Suggested: '.e($it['suggestion']).'</div></div>';
        }
        $html .= '</div>';
    }
}
$html .= '<script>
function filterItems(){
 var q=(document.getElementById("q").value||"").toLowerCase();
 var s=document.getElementById("sev").value;
 document.querySelectorAll(".file-card").forEach(function(card){
   var any=false;
   card.querySelectorAll(".finding").forEach(function(it){
     var ok=(!q || it.dataset.text.indexOf(q)>=0) && (!s || it.dataset.sev===s);
     it.style.display=ok?"block":"none"; if(ok) any=true;
   });
   card.style.display=any?"block":"none";
 });
}
</script>';
$html .= '</div></body></html>';
file_put_contents($outHtml, $html);

echo "Done. Translation Scanner V2 scanned {$fileCount} PHP files. Found {$totalFindings} possible untranslated items.\n";
echo "Open: {$outHtml}\n";
echo "CSV: {$outCsv}\n";
echo "Lang keys: {$outKeys}\n";
