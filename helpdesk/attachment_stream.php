<?php
/*
|--------------------------------------------------------------------------
| Helpdesk Attachment Stream
|--------------------------------------------------------------------------
| Streams images/audio/video/PDF safely with correct MIME and HTTP Range
| support. This prevents iPhone/Android from opening MP4 in external preview
| and allows inline playback/seek inside the Helpdesk page.
*/

if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

function hs_fail($code, $msg) {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

if(empty($_SESSION['user_id'])) {
    hs_fail(403, 'Access denied');
}

$fileParam = (string)($_GET['file'] ?? '');
$fileParam = str_replace(["\0", '\\'], ['', '/'], $fileParam);
$fileParam = preg_replace('#^https?://[^/]+/#i', '', $fileParam);
$fileParam = preg_replace('#^/+?#', '', $fileParam);
$fileParam = preg_replace('#^helpdesk/#i', '', $fileParam);

if($fileParam === '' || strpos($fileParam, '..') !== false) {
    hs_fail(400, 'Invalid file');
}

$appRoot = realpath(__DIR__);
$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname($appRoot);
$candidates = [
    $appRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fileParam),
    $docRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fileParam),
];

$path = false;
foreach($candidates as $candidate) {
    $real = realpath($candidate);
    if($real && is_file($real)) {
        $realNorm = str_replace('\\', '/', $real);
        $appNorm = str_replace('\\', '/', $appRoot);
        $docNorm = str_replace('\\', '/', $docRoot);
        if(strpos($realNorm, $appNorm) === 0 || strpos($realNorm, $docNorm) === 0) {
            $path = $real;
            break;
        }
    }
}

if(!$path) {
    hs_fail(404, 'File not found');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mimeMap = [
    'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp',
    'pdf'=>'application/pdf',
    'mp3'=>'audio/mpeg','m4a'=>'audio/mp4','aac'=>'audio/aac','wav'=>'audio/wav','ogg'=>'audio/ogg','webm'=>'video/webm',
    'mp4'=>'video/mp4','m4v'=>'video/mp4','mov'=>'video/quicktime',
    'doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

$mime = $mimeMap[$ext] ?? 'application/octet-stream';

if(function_exists('finfo_open')) {
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    if($finfo) {
        $detected = @finfo_file($finfo, $path);
        @finfo_close($finfo);
        if(is_string($detected) && $detected !== '') {
            if(strpos($detected, 'video/') === 0 || strpos($detected, 'audio/') === 0 || strpos($detected, 'image/') === 0 || $detected === 'application/pdf') {
                $mime = $detected;
            }
        }
    }
}

$download = isset($_GET['download']);
$filename = basename($path);
$size = filesize($path);
if($size === false) {
    hs_fail(500, 'Unable to read file');
}

@ini_set('zlib.output_compression', 'Off');
while(ob_get_level()) { @ob_end_clean(); }

header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . str_replace('"', '', $filename) . '"');

$start = 0;
$end = $size - 1;

if(isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    if($m[1] !== '') $start = (int)$m[1];
    if($m[2] !== '') $end = (int)$m[2];

    if($m[1] === '' && $m[2] !== '') {
        $suffix = (int)$m[2];
        $start = max(0, $size - $suffix);
        $end = $size - 1;
    }

    if($start > $end || $start < 0 || $end >= $size) {
        header('Content-Range: bytes */' . $size);
        hs_fail(416, 'Requested Range Not Satisfiable');
    }

    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    header('Content-Length: ' . (($end - $start) + 1));
}

$fp = fopen($path, 'rb');
if(!$fp) {
    hs_fail(500, 'Unable to open file');
}

fseek($fp, $start);
$remaining = ($end - $start) + 1;
$chunk = 8192;

while(!feof($fp) && $remaining > 0) {
    $read = ($remaining > $chunk) ? $chunk : $remaining;
    $data = fread($fp, $read);
    if($data === false) break;
    echo $data;
    flush();
    $remaining -= strlen($data);
}

fclose($fp);
exit;
