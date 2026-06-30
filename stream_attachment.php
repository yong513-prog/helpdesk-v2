<?php
if(session_status() === PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])) { http_response_code(403); exit('Forbidden'); }

function hd_stream_b64url_decode($s){
    $s=strtr((string)$s,'-_','+/');
    $pad=strlen($s)%4; if($pad) $s.=str_repeat('=',4-$pad);
    return base64_decode($s,true);
}
function hd_stream_mime($path){
    $ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
    $map=[
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp',
        'pdf'=>'application/pdf','mp3'=>'audio/mpeg','m4a'=>'audio/mp4','aac'=>'audio/aac','wav'=>'audio/wav','ogg'=>'audio/ogg','webm'=>'video/webm','mp4'=>'video/mp4','m4v'=>'video/mp4','mov'=>'video/quicktime',
        'doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    return $map[$ext] ?? 'application/octet-stream';
}
$rel=hd_stream_b64url_decode($_GET['f'] ?? '');
if($rel===false || $rel===''){ http_response_code(400); exit('Bad request'); }
$rel=str_replace('\\','/',$rel);
$rel=preg_replace('#/+#','/',$rel);
$rel=ltrim($rel,'/');
if(strpos($rel,'..')!==false || strpos($rel,'uploads/')!==0){ http_response_code(403); exit('Forbidden'); }
$base=realpath(__DIR__.'/uploads');
$file=realpath(__DIR__.'/'.$rel);
if(!$base || !$file || strpos($file,$base)!==0 || !is_file($file)){ http_response_code(404); exit('Not found'); }
$size=filesize($file);
$mime=hd_stream_mime($file);
$download=isset($_GET['download']);
header('Content-Type: '.$mime);
header('Content-Disposition: '.($download?'attachment':'inline').'; filename="'.basename($file).'"');
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
if($download){ header('Content-Length: '.$size); readfile($file); exit; }
$start=0; $end=$size-1;
if(isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)){
    if($m[1] !== '') $start=(int)$m[1];
    if($m[2] !== '') $end=(int)$m[2];
    if($start>$end || $start>=$size){ header('HTTP/1.1 416 Range Not Satisfiable'); header('Content-Range: bytes */'.$size); exit; }
    if($end>=$size) $end=$size-1;
    header('HTTP/1.1 206 Partial Content');
    header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
}
$length=$end-$start+1;
header('Content-Length: '.$length);
$fp=fopen($file,'rb');
fseek($fp,$start);
$sent=0;
while(!feof($fp) && $sent<$length){
    $chunkSize=min(8192,$length-$sent);
    echo fread($fp,$chunkSize);
    $sent+=$chunkSize;
    if(connection_aborted()) break;
}
fclose($fp);
exit;
?>
