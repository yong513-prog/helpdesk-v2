<?php
/*
|--------------------------------------------------------------------------
| Helpdesk Unified Attachment Preview - WhatsApp Style
|--------------------------------------------------------------------------
| One preview component for tickets, replies and other modules.
| Video: direct inline player (no Play/Download card first)
| Audio: WhatsApp-style voice bubble with play/pause, progress and time
| Image/PDF: safe preview modal
| Office/other: safe download button
*/

if (!function_exists('hd_ap_h')) {
    function hd_ap_h($v) {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('hd_ap_normalize_path')) {
    function hd_ap_normalize_path($path) {
        $path = str_replace("\\", "/", (string)$path);
        $path = trim($path);
        if ($path === '') return '';

        $appRoot = str_replace("\\", "/", realpath(__DIR__) ?: __DIR__);
        $docRoot = str_replace("\\", "/", realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');

        $real = realpath($path);
        if (!$real) {
            $candidate = __DIR__ . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $path);
            $real = realpath($candidate);
        }

        if ($real) {
            $real = str_replace("\\", "/", $real);
            if ($appRoot !== '' && strpos($real, $appRoot) === 0) {
                $path = ltrim(substr($real, strlen($appRoot)), '/');
            } elseif ($docRoot !== '' && strpos($real, $docRoot) === 0) {
                $path = ltrim(substr($real, strlen($docRoot)), '/');
            }
        }

        $path = preg_replace('#^https?://[^/]+/#i', '', $path);
        $path = preg_replace('#^/+?#', '', $path);
        $path = preg_replace('#^helpdesk/#i', '', $path);
        return $path;
    }
}

if (!function_exists('hd_ap_url')) {
    function hd_ap_url($path) {
        $path = hd_ap_normalize_path($path);
        if ($path === '') return '#';
        $parts = array_map('rawurlencode', explode('/', $path));
        return implode('/', $parts);
    }
}

if (!function_exists('hd_ap_stream_url')) {
    function hd_ap_stream_url($path, $download = false) {
        $path = hd_ap_normalize_path($path);
        if ($path === '') return '#';
        $q = ['file' => $path];
        if ($download) $q['download'] = 1;
        return 'attachment_stream.php?' . http_build_query($q);
    }
}

if (!function_exists('hd_ap_ext')) {
    function hd_ap_ext($path) {
        return strtolower(pathinfo((string)$path, PATHINFO_EXTENSION));
    }
}

if (!function_exists('hd_ap_detect_mime')) {
    function hd_ap_detect_mime($path) {
        $real = realpath($path);
        if (!$real) {
            $norm = hd_ap_normalize_path($path);
            $candidate = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $norm);
            $real = realpath($candidate);
        }
        if ($real && is_file($real) && function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = @finfo_file($finfo, $real);
                @finfo_close($finfo);
                return strtolower((string)$mime);
            }
        }
        return '';
    }
}

if (!function_exists('hd_ap_type')) {
    function hd_ap_type($path) {
        $ext = hd_ap_ext($path);
        $mime = hd_ap_detect_mime($path);
        $nameHint = strtolower((string)$path);

        /*
         * Phone voice recordings are sometimes saved as .webm / .mp4 and can be
         * detected as video/webm or application/octet-stream. When the name/path
         * clearly comes from voice recording, render it as a WhatsApp audio bar.
         */
        $looksLikeVoice = (bool)preg_match('/(voice|audio|record|recording|mic|microphone|语音|語音|录音|錄音)/u', $nameHint);

        if (strpos($mime, 'image/') === 0 || in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) return 'image';

        if ($looksLikeVoice && in_array($ext, ['mp3','m4a','aac','wav','webm','ogg','mp4','m4v'], true)) return 'audio';

        if (strpos($mime, 'audio/') === 0 || in_array($ext, ['mp3','m4a','aac','wav','ogg'], true)) return 'audio';

        if (strpos($mime, 'video/') === 0 || in_array($ext, ['mp4','m4v','mov','webm'], true)) return 'video';

        if ($mime === 'application/pdf' || $ext === 'pdf') return 'pdf';
        if (in_array($ext, ['doc','docx'], true)) return 'word';
        if (in_array($ext, ['xls','xlsx'], true)) return 'excel';
        return 'file';
    }
}

if (!function_exists('hd_ap_mime_for_source')) {
    function hd_ap_mime_for_source($path, $type) {
        $ext = hd_ap_ext($path);
        if ($type === 'video') {
            if ($ext === 'webm') return 'video/webm';
            if ($ext === 'mov') return 'video/quicktime';
            return 'video/mp4';
        }
        if ($type === 'audio') {
            if ($ext === 'webm') return 'audio/webm';
            if ($ext === 'ogg') return 'audio/ogg';
            if ($ext === 'wav') return 'audio/wav';
            if ($ext === 'mp3') return 'audio/mpeg';
            return 'audio/mp4';
        }
        return '';
    }
}

if (!function_exists('hd_ap_icon')) {
    function hd_ap_icon($type) {
        return [
            'image'=>'bi-file-image',
            'audio'=>'bi-mic-fill',
            'video'=>'bi-camera-video-fill',
            'pdf'=>'bi-file-earmark-pdf',
            'word'=>'bi-file-earmark-word',
            'excel'=>'bi-file-earmark-excel',
            'file'=>'bi-paperclip'
        ][$type] ?? 'bi-paperclip';
    }
}

if (!function_exists('hd_ap_render')) {
    function hd_ap_render($path, $name = '', $label = 'Attachment', $compact = false) {
        $path = (string)$path;
        if (trim($path) === '') return '';

        $type = hd_ap_type($path);
        $streamUrl = hd_ap_stream_url($path);
        $downloadUrl = hd_ap_stream_url($path, true);
        $directUrl = hd_ap_url($path);
        $name = $name !== '' ? $name : basename(str_replace('\\', '/', $path));
        $safeName = hd_ap_h($name);
        $safeLabel = hd_ap_h($label);
        $id = 'ap_' . substr(md5($path . $name . uniqid('', true)), 0, 10);
        $mime = hd_ap_mime_for_source($path, $type);
        $icon = hd_ap_icon($type);

        ob_start();

        if ($type === 'video'): ?>
            <div class="hd-wa-video-card <?= $compact ? 'hd-ap-compact' : ''; ?>">
                <video class="hd-wa-video"
                       controls
                       playsinline
                       webkit-playsinline
                       preload="auto"
                       controlsList="nodownload"
                       data-hd-video-inline>
                    <source src="<?= hd_ap_h($directUrl); ?>" type="<?= hd_ap_h($mime); ?>">
                    Your browser cannot play this video.
                </video>
            </div>
        <?php elseif ($type === 'audio'): ?>
            <div class="hd-wa-audio-card <?= $compact ? 'hd-ap-compact' : ''; ?>" data-hd-audio-card>
                <button type="button" class="hd-wa-audio-play" data-hd-audio-play aria-label="Play voice">
                    <i class="bi bi-play-fill"></i>
                </button>
                <div class="hd-wa-audio-body">
                    <div class="hd-wa-audio-top">
                        <span class="hd-wa-audio-title"><i class="bi bi-mic-fill"></i> Voice</span>
                        <span class="hd-wa-audio-time" data-hd-audio-time>00:00</span>
                    </div>
                    <input type="range" min="0" max="1000" value="0" class="hd-wa-audio-range" data-hd-audio-range>
                    <audio preload="metadata" playsinline webkit-playsinline controlsList="nodownload noplaybackrate" data-hd-audio>
                        <source src="<?= hd_ap_h($directUrl); ?>" type="<?= hd_ap_h($mime); ?>">
                    </audio>
                </div>
            </div>
        <?php elseif ($type === 'image'): ?>
            <div class="hd-wa-image-card <?= $compact ? 'hd-ap-compact' : ''; ?>">
                <button type="button" class="hd-wa-image-thumb hd-ap-open" data-ap-target="<?= hd_ap_h($id); ?>">
                    <img src="<?= hd_ap_h($directUrl); ?>" alt="<?= $safeName; ?>">
                </button>
                <div class="hd-wa-media-caption">
                    <span><i class="bi bi-image"></i> <?= $safeName; ?></span>
                    <a href="<?= hd_ap_h($downloadUrl); ?>" class="hd-wa-media-download" title="Download"><i class="bi bi-download"></i></a>
                </div>
            </div>
            <div class="hd-ap-modal" id="<?= hd_ap_h($id); ?>" aria-hidden="true">
                <div class="hd-ap-modal-inner">
                    <button type="button" class="hd-ap-close" aria-label="Close">×</button>
                    <img src="<?= hd_ap_h($directUrl); ?>" alt="<?= $safeName; ?>">
                </div>
            </div>
        <?php elseif ($type === 'pdf'): ?>
            <div class="hd-ap-box <?= $compact ? 'hd-ap-compact' : ''; ?>">
                <div class="hd-ap-info">
                    <div class="hd-ap-icon"><i class="bi bi-file-earmark-pdf"></i></div>
                    <div class="hd-ap-text">
                        <div class="hd-ap-label"><?= $safeLabel; ?></div>
                        <div class="hd-ap-name" title="<?= $safeName; ?>"><?= $safeName; ?></div>
                    </div>
                </div>
                <div class="hd-ap-actions">
                    <button type="button" class="btn btn-primary hd-ap-open" data-ap-target="<?= hd_ap_h($id); ?>"><i class="bi bi-eye"></i> Preview</button>
                    <a class="btn btn-outline-secondary" href="<?= hd_ap_h($downloadUrl); ?>"><i class="bi bi-download"></i> Download</a>
                </div>
            </div>
            <div class="hd-ap-modal" id="<?= hd_ap_h($id); ?>" aria-hidden="true">
                <div class="hd-ap-modal-inner hd-ap-pdf-inner">
                    <button type="button" class="hd-ap-close" aria-label="Close">×</button>
                    <iframe src="<?= hd_ap_h($directUrl); ?>"></iframe>
                </div>
            </div>
        <?php else: ?>
            <div class="hd-ap-box <?= $compact ? 'hd-ap-compact' : ''; ?>">
                <div class="hd-ap-info">
                    <div class="hd-ap-icon"><i class="bi <?= hd_ap_h($icon); ?>"></i></div>
                    <div class="hd-ap-text">
                        <div class="hd-ap-label"><?= $safeLabel; ?></div>
                        <div class="hd-ap-name" title="<?= $safeName; ?>"><?= $safeName; ?></div>
                    </div>
                </div>
                <div class="hd-ap-actions">
                    <a class="btn btn-outline-secondary" href="<?= hd_ap_h($downloadUrl); ?>"><i class="bi bi-download"></i> Download</a>
                </div>
            </div>
        <?php endif;

        return ob_get_clean();
    }
}

if (!function_exists('hd_ap_assets')) {
    function hd_ap_assets() {
        static $done = false;
        if ($done) return '';
        $done = true;
        ob_start(); ?>
        <style>
        .hd-wa-video-card,.hd-wa-image-card{margin-top:14px;border-radius:18px;overflow:hidden;background:#0f172a;border:1px solid #dbeafe;box-shadow:0 8px 22px rgba(15,23,42,.08);max-width:100%}
        .hd-wa-video-card{background:#000}
        .hd-wa-video{display:block;width:100%;max-height:560px;background:#000;object-fit:contain;outline:0}
        .reply-card .hd-wa-video{max-height:420px}
        .hd-wa-media-caption{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 12px;background:#fff;color:#334155;font-size:13px;font-weight:800;word-break:break-word}
        .hd-wa-media-caption span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .hd-wa-media-download,.hd-wa-audio-download{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;background:#eff6ff;color:#2563eb;text-decoration:none;flex:0 0 auto}
        .hd-wa-image-thumb{display:block;width:100%;border:0;padding:0;background:#0f172a;cursor:pointer}
        .hd-wa-image-thumb img{display:block;max-width:100%;max-height:420px;margin:0 auto;object-fit:contain}

        .hd-wa-audio-card{margin-top:14px;display:flex;align-items:center;gap:10px;max-width:520px;background:#dcf8c6;border:1px solid #b7ef9b;border-radius:18px 18px 18px 6px;padding:10px 12px;box-shadow:0 8px 22px rgba(15,23,42,.06)}
        .reply-card .hd-wa-audio-card{background:#fff;border-color:#dbeafe;border-radius:18px 18px 6px 18px}
        .hd-wa-audio-card.hd-ap-compact{max-width:460px}
        .hd-wa-audio-play{width:44px;height:44px;border-radius:50%;border:0;background:#128c7e;color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;flex:0 0 auto}
        .hd-wa-audio-body{min-width:0;flex:1}
        .hd-wa-audio-top{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:5px}
        .hd-wa-audio-title{font-size:13px;font-weight:900;color:#075e54;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .hd-wa-audio-time{font-size:12px;font-weight:900;color:#334155;white-space:nowrap}
        .hd-wa-audio-range{width:100%;accent-color:#128c7e}
        .hd-wa-audio-card audio{display:none!important}

        .hd-ap-box{margin-top:14px;border:1px solid #e7edf7;background:#fbfdff;border-radius:18px;padding:14px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
        .hd-ap-info{display:flex;align-items:center;gap:12px;min-width:0}
        .hd-ap-icon{width:54px;height:54px;border-radius:16px;background:#eaf2ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:24px;flex:0 0 auto}
        .hd-ap-text{min-width:0}.hd-ap-label{font-size:13px;color:#64748b;font-weight:800}.hd-ap-name{font-size:14px;color:#334155;max-width:320px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .hd-ap-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .hd-ap-modal{display:none;position:fixed;inset:0;background:rgba(15,23,42,.88);z-index:5000;padding:18px;align-items:center;justify-content:center}
        .hd-ap-modal.show{display:flex}
        .hd-ap-modal-inner{position:relative;background:#0b1220;border-radius:18px;max-width:min(1024px,96vw);max-height:92vh;overflow:hidden;box-shadow:0 24px 70px rgba(0,0,0,.35)}
        .hd-ap-modal-inner img{display:block;max-width:96vw;max-height:88vh;object-fit:contain}
        .hd-ap-pdf-inner{width:min(1000px,96vw);height:88vh;background:#fff}.hd-ap-pdf-inner iframe{width:100%;height:100%;border:0}
        .hd-ap-close{position:absolute;right:10px;top:10px;width:46px;height:46px;border:0;border-radius:50%;background:rgba(255,255,255,.95);color:#0f172a;font-size:30px;line-height:1;z-index:2;font-weight:900;box-shadow:0 8px 24px rgba(0,0,0,.25)}

        @media(max-width:768px){
            .hd-wa-video-card,.hd-wa-image-card,.hd-wa-audio-card{border-radius:16px;margin-top:12px}
            .hd-wa-video{width:100%;max-height:56vh}
            .hd-wa-media-caption span{max-width:72vw}
            .hd-wa-audio-card{max-width:100%;padding:10px}
            .hd-wa-audio-play{width:42px;height:42px;font-size:23px}
            .hd-wa-audio-download{display:none}
            .hd-ap-box{display:block;padding:14px}.hd-ap-info{margin-bottom:12px}.hd-ap-name{max-width:72vw}
            .hd-ap-actions{display:grid;grid-template-columns:1fr 1fr}.hd-ap-actions .btn{width:100%;min-height:46px}
            .hd-ap-modal{padding:10px}.hd-ap-close{width:48px;height:48px;font-size:32px}
        }
        </style>
        <script>
        (function(){
            function fmt(sec){
                sec = Math.max(0, Math.floor(sec || 0));
                return String(Math.floor(sec/60)).padStart(2,'0') + ':' + String(sec%60).padStart(2,'0');
            }
            function stopOtherAudios(current){
                document.querySelectorAll('[data-hd-audio]').forEach(function(a){
                    if(a !== current){ try{ a.pause(); }catch(e){} }
                });
                document.querySelectorAll('[data-hd-audio-play] i').forEach(function(i){ i.className='bi bi-play-fill'; });
            }
            function initAudioCard(card){
                if(card.dataset.hdAudioReady === '1') return;
                card.dataset.hdAudioReady = '1';
                var audio = card.querySelector('[data-hd-audio]');
                var btn = card.querySelector('[data-hd-audio-play]');
                var icon = btn ? btn.querySelector('i') : null;
                var range = card.querySelector('[data-hd-audio-range]');
                var time = card.querySelector('[data-hd-audio-time]');
                if(!audio || !btn || !range) return;
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    if(audio.paused){
                        stopOtherAudios(audio);
                        audio.play().catch(function(){});
                    }else{
                        audio.pause();
                    }
                });
                audio.addEventListener('play', function(){ if(icon) icon.className='bi bi-pause-fill'; });
                audio.addEventListener('pause', function(){ if(icon) icon.className='bi bi-play-fill'; });
                audio.addEventListener('loadedmetadata', function(){
                    if(time && isFinite(audio.duration)) time.textContent = fmt(audio.duration);
                });
                audio.addEventListener('timeupdate', function(){
                    if(isFinite(audio.duration) && audio.duration > 0){
                        range.value = Math.round((audio.currentTime / audio.duration) * 1000);
                        if(time) time.textContent = fmt(audio.currentTime) + ' / ' + fmt(audio.duration);
                    }
                });
                audio.addEventListener('ended', function(){ range.value = 0; if(icon) icon.className='bi bi-play-fill'; });
                range.addEventListener('input', function(){
                    if(isFinite(audio.duration) && audio.duration > 0){
                        audio.currentTime = (Number(range.value) / 1000) * audio.duration;
                    }
                });
            }
            function initInlineVideo(root){
                (root || document).querySelectorAll('[data-hd-video-inline]').forEach(function(video){
                    if(video.dataset.hdVideoReady === '1') return;
                    video.dataset.hdVideoReady = '1';
                    try{ video.load(); }catch(e){}
                    video.addEventListener('loadedmetadata', function(){
                        if(video.duration && video.duration > 0 && video.currentTime === 0){
                            try{ video.currentTime = Math.min(0.1, video.duration / 10); }catch(e){}
                        }
                    }, {once:true});
                    video.addEventListener('play', function(){
                        document.querySelectorAll('audio,video').forEach(function(m){
                            if(m !== video){ try{ m.pause(); }catch(e){} }
                        });
                    });
                });
            }

            function initAllAudio(root){
                (root || document).querySelectorAll('[data-hd-audio-card]').forEach(initAudioCard);
                initInlineVideo(root || document);
            }
            document.addEventListener('click',function(e){
                var open=e.target.closest('.hd-ap-open');
                if(open){
                    e.preventDefault();
                    var m=document.getElementById(open.getAttribute('data-ap-target'));
                    if(m){
                        m.classList.add('show');
                        document.body.style.overflow='hidden';
                    }
                    return;
                }
                var close=e.target.closest('.hd-ap-close');
                if(close){
                    var m=close.closest('.hd-ap-modal');
                    if(m){
                        m.querySelectorAll('video,audio').forEach(function(v){try{v.pause();v.currentTime=0;}catch(e){}});
                        m.classList.remove('show');
                        document.body.style.overflow='';
                    }
                    return;
                }
                if(e.target.classList && e.target.classList.contains('hd-ap-modal')){
                    e.target.querySelectorAll('video,audio').forEach(function(v){try{v.pause();v.currentTime=0;}catch(e){}});
                    e.target.classList.remove('show');
                    document.body.style.overflow='';
                }
            });
            document.addEventListener('keydown',function(e){
                if(e.key==='Escape'){
                    document.querySelectorAll('.hd-ap-modal.show').forEach(function(m){
                        m.querySelectorAll('video,audio').forEach(function(v){try{v.pause();v.currentTime=0;}catch(e){}});
                        m.classList.remove('show');
                    });
                    document.body.style.overflow='';
                }
            });
            document.addEventListener('DOMContentLoaded', function(){ initAllAudio(document); });
            var mo = new MutationObserver(function(ms){ms.forEach(function(m){m.addedNodes.forEach(function(n){if(n.nodeType===1) initAllAudio(n);});});});
            document.addEventListener('DOMContentLoaded', function(){ if(document.body) mo.observe(document.body,{childList:true,subtree:true}); });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
?>