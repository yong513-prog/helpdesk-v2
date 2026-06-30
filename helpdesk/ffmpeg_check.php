<?php
session_start();
if(!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once __DIR__ . '/attachment_upload_helper.php';

$ffmpeg = function_exists('hd_upload_ffmpeg_binary') ? hd_upload_ffmpeg_binary() : '';
$ok = false;
$output = [];
$ret = 1;
if($ffmpeg !== '') {
    @exec(escapeshellarg($ffmpeg) . ' -version 2>&1', $output, $ret);
    $ok = ($ret === 0);
}

$isWindows = (stripos(PHP_OS, 'WIN') === 0);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>FFmpeg Check</title>
<style>
body{font-family:Arial,Helvetica,sans-serif;background:#f4f7fb;margin:0;padding:30px;color:#0f172a}.card{max-width:920px;margin:0 auto;background:#fff;border:1px solid #e5edf7;border-radius:18px;padding:24px;box-shadow:0 12px 35px rgba(15,23,42,.08)}.ok{color:#15803d;font-weight:800}.bad{color:#b91c1c;font-weight:800}.muted{color:#64748b}code{background:#eef2ff;border-radius:8px;padding:2px 6px}pre{background:#0f172a;color:#e5e7eb;border-radius:14px;padding:16px;overflow:auto}.btn{display:inline-block;margin-top:14px;background:#2563eb;color:#fff;text-decoration:none;border-radius:12px;padding:10px 16px;font-weight:800}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.box{border:1px solid #e5edf7;border-radius:14px;padding:14px;background:#fbfdff}@media(max-width:760px){body{padding:14px}.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="card">
<h2>FFmpeg Check</h2>
<p class="muted">This check is cross-platform. The same Helpdesk code can run on Windows/Laragon and Linux VPS as long as FFmpeg is installed.</p>

<?php if($ok): ?>
<p class="ok">✅ FFmpeg detected and working.</p>
<p><strong>Detected binary:</strong> <code><?= htmlspecialchars($ffmpeg, ENT_QUOTES, 'UTF-8') ?></code></p>
<pre><?= htmlspecialchars(implode("\n", array_slice($output,0,12)), ENT_QUOTES, 'UTF-8') ?></pre>
<p>Video upload auto-convert is enabled. iPhone MOV/MP4 will be converted to browser-compatible MP4 after upload.</p>
<?php else: ?>
<p class="bad">❌ FFmpeg not detected or cannot run.</p>
<div class="grid">
    <div class="box">
        <strong>Windows / Laragon</strong>
        <p>Install FFmpeg here:</p>
        <p><code>C:\ffmpeg\bin\ffmpeg.exe</code></p>
        <p>Or add <code>C:\ffmpeg\bin</code> to Windows PATH.</p>
    </div>
    <div class="box">
        <strong>Linux VPS / Oracle Cloud / Ubuntu</strong>
        <p>Install with:</p>
        <p><code>sudo apt update && sudo apt install ffmpeg -y</code></p>
        <p>The system will auto-detect <code>/usr/bin/ffmpeg</code>.</p>
    </div>
</div>
<?php if($ffmpeg): ?><pre><?= htmlspecialchars(implode("\n", $output), ENT_QUOTES, 'UTF-8') ?></pre><?php endif; ?>
<?php endif; ?>

<a class="btn" href="dashboard.php">Back to Dashboard</a>
</div>
</body>
</html>
