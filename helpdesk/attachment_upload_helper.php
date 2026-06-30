<?php
require_once __DIR__ . '/entity_upload_helper.php';
/*
|--------------------------------------------------------------------------
| Helpdesk Attachment Upload Helper
|--------------------------------------------------------------------------
| Central upload validation for ticket attachments/replies.
| Supports normal files plus WhatsApp-style voice recordings from iPhone,
| Android, Safari/PWA and Chrome. Validation uses extension + MIME/signature.
*/

if(!function_exists('hd_upload_normalize_mime'))
{
    function hd_upload_normalize_mime($mime)
    {
        $mime = strtolower(trim((string)$mime));
        if($mime === '') return '';
        $mime = explode(';', $mime)[0];
        return trim($mime);
    }
}

if(!function_exists('hd_upload_detect_mime'))
{
    function hd_upload_detect_mime($tmpPath, $browserMime = '')
    {
        $browserMime = hd_upload_normalize_mime($browserMime);
        $finfoMime = '';

        // Accept both temporary upload paths and saved relative paths like uploads/tickets/xxx/video.mp4.
        if(!is_file($tmpPath) && $tmpPath !== '')
        {
            $candidate = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($tmpPath, '/\\'));
            if(is_file($candidate)) $tmpPath = $candidate;
        }

        if(is_file($tmpPath) && function_exists('finfo_open'))
        {
            try
            {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if($finfo)
                {
                    $finfoMime = hd_upload_normalize_mime(finfo_file($finfo, $tmpPath));
                    finfo_close($finfo);
                }
            }
            catch(Exception $e) {}
        }

        return $finfoMime ?: $browserMime;
    }
}

if(!function_exists('hd_upload_ext_from_mime'))
{
    function hd_upload_ext_from_mime($mime, $tmpPath = '')
    {
        $mime = hd_upload_normalize_mime($mime);

        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/x-m4a' => 'm4a',
            'audio/aac' => 'aac',
            'audio/aacp' => 'aac',
            'audio/webm' => 'webm',
            'video/webm' => 'webm',
            'audio/ogg' => 'ogg',
            'application/ogg' => 'ogg',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/wave' => 'wav',
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'video/x-m4v' => 'm4v',
        ];

        if(isset($map[$mime])) return $map[$mime];

        if(is_file($tmpPath))
        {
            $head = @file_get_contents($tmpPath, false, null, 0, 32);
            if($head !== false)
            {
                if(strpos($head, 'ftyp') !== false) return 'm4a';
                if(substr($head, 0, 4) === 'OggS') return 'ogg';
                if(substr($head, 0, 4) === 'RIFF') return 'wav';
                if(substr($head, 0, 3) === 'ID3') return 'mp3';
                if(isset($head[0]) && ord($head[0]) === 0x1A && substr($head, 1, 3) === 'E\xDF\xA3') return 'webm';
            }
        }

        if(strpos($mime, 'audio/') === 0) return 'm4a';
        if(strpos($mime, 'video/') === 0) return ($mime === 'video/quicktime' ? 'mov' : 'mp4');

        return '';
    }
}

if(!function_exists('hd_upload_is_allowed_attachment'))
{
    function hd_upload_is_allowed_attachment($ext, $mime, $tmpPath = '')
    {
        $ext = strtolower(trim((string)$ext));
        $mime = hd_upload_normalize_mime($mime);

        $allowedExt = [
            'jpg','jpeg','png','gif','webp',
            'pdf','doc','docx','xls','xlsx',
            'mp3','m4a','wav','aac','ogg','webm','mp4','mov','m4v'
        ];

        $allowedMime = [
            'image/jpeg','image/png','image/gif','image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'audio/mpeg','audio/mp3','audio/mp4','audio/x-m4a','audio/aac','audio/aacp',
            'audio/webm','video/webm','audio/ogg','application/ogg',
            'audio/wav','audio/x-wav','audio/wave',
            'video/mp4','video/quicktime','video/x-m4v'
        ];

        if($ext !== '' && in_array($ext, $allowedExt, true)) return true;
        if($mime !== '' && in_array($mime, $allowedMime, true)) return true;
        if(strpos($mime, 'audio/') === 0 || strpos($mime, 'video/') === 0) return true;

        // Some iPhone/PWA recordings arrive as application/octet-stream.
        // Only accept them if the binary signature looks like a known audio/video file.
        if($mime === 'application/octet-stream' && is_file($tmpPath))
        {
            $detectedExt = hd_upload_ext_from_mime($mime, $tmpPath);
            if($detectedExt !== '' && in_array($detectedExt, ['mp3','m4a','wav','aac','ogg','webm','mp4','mov','m4v'], true)) return true;
        }

        return false;
    }
}



/*
 * Video compatibility converter
 * ------------------------------------------------------------
 * iPhone / Android camera videos may be MOV, rotated by metadata, or
 * encoded in a way that mobile Safari can play but Windows Chrome/Edge
 * cannot display reliably. When FFmpeg is available, uploaded videos are
 * converted to a browser-safe MP4: H.264 + AAC + yuv420p + faststart.
 *
 * Configure FFmpeg by either:
 * - Installing it in C:\ffmpeg\bin\ffmpeg.exe, or
 * - Adding ffmpeg to the system PATH, or
 * - Defining HD_FFMPEG_PATH in config.php before upload helpers are used.
 */
if(!function_exists('hd_upload_ffmpeg_binary'))
{
    function hd_upload_ffmpeg_binary()
    {
        /*
         * Cross-platform FFmpeg auto-detection.
         * This lets the same Helpdesk code run on:
         * - Windows / Laragon: C:\ffmpeg\bin\ffmpeg.exe or PATH
         * - Linux VPS / Oracle Cloud / Ubuntu / Debian: /usr/bin/ffmpeg or PATH
         * - Docker / custom server: define HD_FFMPEG_PATH or set env HD_FFMPEG_PATH
         */
        $candidates = [];

        if(defined('HD_FFMPEG_PATH') && HD_FFMPEG_PATH)
        {
            $candidates[] = HD_FFMPEG_PATH;
        }

        $envPath = getenv('HD_FFMPEG_PATH');
        if($envPath)
        {
            $candidates[] = $envPath;
        }

        $isWindows = (stripos(PHP_OS, 'WIN') === 0);

        // First try the operating system PATH.
        $cmd = $isWindows ? 'where ffmpeg 2>NUL' : 'command -v ffmpeg 2>/dev/null';
        $out = [];
        $ret = 1;
        @exec($cmd, $out, $ret);
        if($ret === 0 && !empty($out))
        {
            foreach($out as $found)
            {
                $found = trim((string)$found);
                if($found !== '') $candidates[] = $found;
            }
        }

        // Project-local binary.
        $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . ($isWindows ? 'ffmpeg.exe' : 'ffmpeg');

        // Common Windows locations.
        $candidates[] = 'C:\\ffmpeg\\bin\\ffmpeg.exe';
        $candidates[] = 'D:\\ffmpeg\\bin\\ffmpeg.exe';
        $candidates[] = 'C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe';
        $candidates[] = 'C:\\Program Files (x86)\\ffmpeg\\bin\\ffmpeg.exe';

        // Common Linux / VPS locations.
        $candidates[] = '/usr/bin/ffmpeg';
        $candidates[] = '/usr/local/bin/ffmpeg';
        $candidates[] = '/snap/bin/ffmpeg';
        $candidates[] = '/opt/ffmpeg/bin/ffmpeg';

        $checked = [];
        foreach($candidates as $bin)
        {
            $bin = trim((string)$bin);
            if($bin === '') continue;
            if(isset($checked[$bin])) continue;
            $checked[$bin] = true;

            if(is_file($bin) || (!$isWindows && is_executable($bin)))
            {
                return $bin;
            }
        }

        // Final fallback. Some hosting environments allow command execution
        // even when command -v / where is restricted.
        $test = [];
        $testRet = 1;
        @exec('ffmpeg -version 2>&1', $test, $testRet);
        if($testRet === 0)
        {
            return 'ffmpeg';
        }

        return '';
    }
}

if(!function_exists('hd_upload_is_video_file'))
{
    function hd_upload_is_video_file($path, $mime = '')
    {
        $ext = strtolower(pathinfo((string)$path, PATHINFO_EXTENSION));
        $mime = hd_upload_normalize_mime($mime);
        return (strpos($mime, 'video/') === 0) || in_array($ext, ['mp4','mov','m4v','webm'], true);
    }
}

if(!function_exists('hd_upload_convert_video_to_mp4'))
{
    function hd_upload_convert_video_to_mp4($sourcePath, $mime = '')
    {
        $sourcePath = (string)$sourcePath;
        if(!is_file($sourcePath)) return $sourcePath;
        if(!hd_upload_is_video_file($sourcePath, $mime)) return $sourcePath;

        $ffmpeg = hd_upload_ffmpeg_binary();
        if($ffmpeg === '')
        {
            // FFmpeg is not installed; keep the original file instead of failing upload.
            return $sourcePath;
        }

        $dir = dirname($sourcePath);
        $base = pathinfo($sourcePath, PATHINFO_FILENAME);
        $targetPath = $dir . DIRECTORY_SEPARATOR . $base . '_web.mp4';

        // Avoid overwriting an existing file from a previous retry.
        if(is_file($targetPath))
        {
            $targetPath = $dir . DIRECTORY_SEPARATOR . $base . '_web_' . bin2hex(random_bytes(3)) . '.mp4';
        }

        // Make phone videos browser-safe:
        // - Auto-applies iPhone rotation metadata before filtering.
        // - Removes metadata rotation from the output so Windows/Android browsers do not show black/rotated video.
        // - Forces H.264 + AAC + yuv420p + MP4 faststart for Chrome/Edge/Safari/Android/iPhone.
        $videoFilter = 'scale=trunc(iw/2)*2:trunc(ih/2)*2,setsar=1';
        $cmd = escapeshellarg($ffmpeg)
            . ' -y -hide_banner -loglevel error -nostdin'
            . ' -i ' . escapeshellarg($sourcePath)
            . ' -map 0:v:0 -map 0:a? -sn -dn -ignore_unknown'
            . ' -vf ' . escapeshellarg($videoFilter)
            . ' -c:v libx264 -preset veryfast -crf 23 -profile:v baseline -level 3.1 -pix_fmt yuv420p'
            . ' -c:a aac -b:a 128k -ac 2'
            . ' -map_metadata -1 -metadata:s:v:0 rotate=0'
            . ' -movflags +faststart -max_muxing_queue_size 1024'
            . ' ' . escapeshellarg($targetPath)
            . ' 2>&1';

        $out = [];
        $ret = 1;
        @exec($cmd, $out, $ret);

        if($ret === 0 && is_file($targetPath) && filesize($targetPath) > 0)
        {
            // Keep original only when conversion output is suspiciously tiny.
            if(filesize($targetPath) > 1024)
            {
                @unlink($sourcePath);
                return $targetPath;
            }
        }

        if(!empty($out))
        {
            @file_put_contents(__DIR__ . '/uploads/ffmpeg_error.log', '[' . date('Y-m-d H:i:s') . '] ' . basename($sourcePath) . ' => ' . implode("\n", $out) . "\n\n", FILE_APPEND);
        }
        @unlink($targetPath);
        return $sourcePath;
    }
}

if(!function_exists('hd_handle_attachment_upload'))
{
    function hd_handle_attachment_upload(array $file, string $uploadDir, int $maxSize = 10485760)
    {
        if(!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) return null;

        if((int)($file['size'] ?? 0) <= 0) return null;
        if((int)$file['size'] > $maxSize) die('Attachment size must be below '.round($maxSize/1024/1024).'MB.');

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if($tmpPath === '' || !is_uploaded_file($tmpPath)) die('Invalid uploaded file.');

        $originalName = basename((string)($file['name'] ?? ''));
        $originalName = trim($originalName) !== '' ? $originalName : ('voice_'.date('Ymd_His'));

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = hd_upload_detect_mime($tmpPath, $file['type'] ?? '');

        if(!hd_upload_is_allowed_attachment($ext, $mime, $tmpPath))
        {
            die('Invalid attachment type. Allowed: jpg, jpeg, png, gif, webp, pdf, doc, docx, xls, xlsx, mp3, m4a, wav, aac, ogg, webm, mp4, mov, m4v');
        }

        if($ext === '')
        {
            $ext = hd_upload_ext_from_mime($mime, $tmpPath);
            if($ext === '') $ext = 'bin';
            $originalName .= '.'.$ext;
        }

        if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $base = preg_replace('/[^a-zA-Z0-9._-]/', '_', $base);
        $base = trim($base, '._-');
        if($base === '') $base = 'attachment';

        $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $base . '.' . $ext;
        $targetPath = rtrim($uploadDir, '/\\') . '/' . $safeName;

        if(!move_uploaded_file($tmpPath, $targetPath)) die('Failed to upload attachment.');

        // Convert camera/video uploads to web-compatible MP4 when FFmpeg is available.
        $targetPath = hd_upload_convert_video_to_mp4($targetPath, $mime);

        $root = str_replace('\\','/', __DIR__) . '/';
        $norm = str_replace('\\','/', $targetPath);
        if(strpos($norm, $root) === 0) return substr($norm, strlen($root));
        return $targetPath;
    }
}
