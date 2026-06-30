<?php if(isset($_SESSION['user_id'])): ?>

    </main>

</div>

<?php else: ?>

</div>

<?php endif; ?>

</div>


<?php if(function_exists('hd_lang') && hd_lang() !== 'en'): ?>
<script>
(function(){
  const HD_UI_DICT = <?= json_encode(hd_translation_dict()[hd_lang()] ?? [], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
  function tr(s){
    if(!s) return s;
    if(Object.prototype.hasOwnProperty.call(HD_UI_DICT, s)) return HD_UI_DICT[s];
    return s;
  }
  function preserveReplace(node, translated){
    const old=node.nodeValue;
    const lead=(old.match(/^\s*/)||[''])[0];
    const tail=(old.match(/\s*$/)||[''])[0];
    node.nodeValue=lead+translated+tail;
  }
  function walk(root){
    if(!root) return;
    const walker=document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode(n){
        const p=n.parentElement;
        if(!p) return NodeFilter.FILTER_REJECT;
        if(['SCRIPT','STYLE','TEXTAREA','INPUT'].includes(p.tagName)) return NodeFilter.FILTER_REJECT;
        if(p.closest && (p.closest('.hd-no-translate') || p.closest('.notranslate') || p.closest('[translate="no"]'))) return NodeFilter.FILTER_REJECT;
        const t=n.nodeValue.trim();
        if(!t) return NodeFilter.FILTER_REJECT;
        return Object.prototype.hasOwnProperty.call(HD_UI_DICT, t) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
      }
    });
    const nodes=[];
    while(walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach(n=>preserveReplace(n, HD_UI_DICT[n.nodeValue.trim()]));
    root.querySelectorAll && root.querySelectorAll('[placeholder],[title],[alt],[aria-label],input[type=button],input[type=submit],input[type=reset]').forEach(el=>{
      ['placeholder','title','alt','aria-label'].forEach(a=>{
        if(el.hasAttribute(a)) el.setAttribute(a, tr(el.getAttribute(a)));
      });
      if(el.tagName==='INPUT' && ['button','submit','reset'].includes((el.type||'').toLowerCase())){
        el.value=tr(el.value);
      }
    });
  }
  document.addEventListener('DOMContentLoaded', function(){
    walk(document.body);
    const mo=new MutationObserver(ms=>ms.forEach(m=>m.addedNodes.forEach(n=>{ if(n.nodeType===1) walk(n); })));
    mo.observe(document.body,{childList:true,subtree:true});
  });
})();
</script>
<?php endif; ?>


<?php if(function_exists('hd_lang')): ?>
<style>
.hd-file-native{position:absolute!important;left:-9999px!important;width:1px!important;height:1px!important;opacity:0!important;overflow:hidden!important}
.hd-file-ui{display:inline-flex;align-items:center;gap:10px;border:1px solid #d1d5db;border-radius:8px;background:#fff;min-height:38px;padding:0 12px;cursor:pointer;max-width:100%}
.hd-file-ui .hd-file-btn{font-weight:600;color:#111827}
.hd-file-ui .hd-file-name{color:#6b7280}

/* iOS-safe inline audio playback */
.hd-voice-player{position:relative!important;display:flex!important;align-items:center!important;gap:10px!important;border:1px solid #dbeafe!important;background:#eff6ff!important;border-radius:14px!important;padding:10px!important;max-width:100%!important;overflow:hidden!important}
.hd-voice-player audio{width:100%!important;max-width:420px!important;display:block!important}
.hd-voice-download{white-space:nowrap}
@media(max-width:768px){.hd-voice-player{align-items:flex-start!important;flex-direction:column!important}.hd-voice-player audio{max-width:100%!important}.hd-voice-download{width:100%;text-align:center}}

</style>
<script>
(function(){
  const lang = <?= json_encode(function_exists('hd_lang') ? hd_lang() : 'en'); ?>;
  const refreshLabels = {en:{pull:'Pull to refresh',release:'Release to refresh',refreshing:'Refreshing...'},zh:{pull:'下拉刷新',release:'松开刷新',refreshing:'正在刷新...'},ms:{pull:'Tarik untuk segar semula',release:'Lepas untuk segar semula',refreshing:'Sedang menyegar semula...'}}[lang] || {pull:'Pull to refresh',release:'Release to refresh',refreshing:'Refreshing...'};
  const labels = {
    en:{choose:'Choose File',chooseMulti:'Choose Files',none:'No file chosen'},
    zh:{choose:'选择文件',chooseMulti:'选择文件',none:'未选择文件'},
    ms:{choose:'Pilih Fail',chooseMulti:'Pilih Fail',none:'Tiada fail dipilih'}
  }[lang] || {choose:'Choose File',chooseMulti:'Choose Files',none:'No file chosen'};
  function applyFileUi(root){
    (root||document).querySelectorAll('input[type="file"]:not([data-hd-file-ui])').forEach(function(input){
      input.dataset.hdFileUi='1';
      if(!input.id) input.id='hd_file_'+Math.random().toString(36).slice(2);
      const label=document.createElement('label');
      label.className='hd-file-ui';
      label.setAttribute('for', input.id);
      const btn=document.createElement('span');
      btn.className='hd-file-btn';
      btn.textContent=input.multiple ? labels.chooseMulti : labels.choose;
      const name=document.createElement('span');
      name.className='hd-file-name';
      name.textContent=labels.none;
      label.appendChild(btn); label.appendChild(name);
      input.classList.add('hd-file-native');
      input.parentNode.insertBefore(label, input);
      input.addEventListener('change', function(){
        if(input.files && input.files.length){
          name.textContent = input.files.length===1 ? input.files[0].name : (input.files.length + ' ' + (lang==='zh'?'个文件':(lang==='ms'?'fail':'files')));
        }else{
          name.textContent = labels.none;
        }
      });
    });
  }
  document.addEventListener('DOMContentLoaded', function(){ applyFileUi(document); });
  const mo=new MutationObserver(function(ms){ms.forEach(function(m){m.addedNodes.forEach(function(n){if(n.nodeType===1) applyFileUi(n);});});});
  document.addEventListener('DOMContentLoaded', function(){ if(document.body) mo.observe(document.body,{childList:true,subtree:true}); });
})();
</script>
<?php endif; ?>





<style>
/* WhatsApp-style voice recording + global pull refresh */
.hd-wa-record-btn{touch-action:none;user-select:none;-webkit-user-select:none;position:relative}
.hd-wa-record-btn.is-recording{background:#fee2e2!important;border-color:#ef4444!important;color:#b91c1c!important;box-shadow:0 0 0 4px rgba(239,68,68,.12)!important}
.hd-wa-voice-panel{margin-top:10px;border:1px solid #e5e7eb;background:#fff;border-radius:14px;padding:10px 12px;display:none;gap:10px;align-items:center;flex-wrap:wrap;box-shadow:0 8px 20px rgba(15,23,42,.06)}
.hd-wa-voice-panel.show{display:flex}
.hd-wa-voice-status{font-weight:900;color:#dc2626;display:flex;gap:6px;align-items:center}
.hd-wa-voice-dot{width:9px;height:9px;border-radius:50%;background:#ef4444;display:inline-block;animation:hdPulse 1s infinite}
@keyframes hdPulse{0%,100%{opacity:.35}50%{opacity:1}}
.hd-wa-voice-time{font-weight:900;color:#0f172a;min-width:52px}
.hd-wa-voice-panel audio{height:34px;max-width:260px}
.hd-wa-delete-voice{border:1px solid #fecaca;background:#fff5f5;color:#dc2626;border-radius:10px;padding:7px 10px;font-weight:800}
.hd-voice-player{display:flex;align-items:center;gap:10px;border:1px solid #dbeafe;background:#eff6ff;border-radius:14px;padding:10px;max-width:100%}
.hd-voice-player i{color:#2563eb;font-size:20px}.hd-voice-player audio{width:min(100%,360px)}
.hd-pull-refresh-indicator{position:fixed;left:50%;top:12px;transform:translate(-50%,-90px);z-index:5000;background:#fff;border:1px solid #dbe3ee;border-radius:999px;padding:9px 14px;box-shadow:0 12px 28px rgba(15,23,42,.18);font-weight:800;color:#2563eb;transition:transform .18s ease,opacity .18s ease;opacity:0;pointer-events:none}
.hd-pull-refresh-indicator.show{transform:translate(-50%,0);opacity:1}.hd-pull-refresh-indicator.ready{color:#16a34a}
@media(max-width:768px){.hd-wa-voice-panel audio{width:100%;max-width:100%}.hd-wa-record-btn{min-height:44px}}
</style>
<script>
(function(){
  const lang = (document.documentElement.lang || 'en').toLowerCase();
  window.refreshLabels = window.refreshLabels || ({
    en:{pull:'Pull to refresh',release:'Release to refresh',refreshing:'Refreshing...'},
    zh:{pull:'下拉刷新',release:'松开刷新',refreshing:'正在刷新...'},
    ms:{pull:'Tarik untuk segar semula',release:'Lepas untuk segar semula',refreshing:'Sedang menyegar semula...'}
  }[lang] || {pull:'Pull to refresh',release:'Release to refresh',refreshing:'Refreshing...'});

  const dict = {
    hold:{en:'Hold Voice',zh:'按住说话',ms:'Tekan Suara'},
    recording:{en:'Recording...',zh:'录音中...',ms:'Merakam...'},
    release:{en:'Release to stop',zh:'松开停止',ms:'Lepas untuk berhenti'},
    ready:{en:'Voice ready',zh:'语音已准备',ms:'Suara sedia'},
    deleted:{en:'Voice deleted',zh:'语音已删除',ms:'Suara dipadam'},
    micDenied:{en:'Microphone permission denied',zh:'麦克风权限被拒绝',ms:'Kebenaran mikrofon ditolak'},
    unsupported:{en:'This browser does not support recording',zh:'此浏览器不支持录音',ms:'Pelayar ini tidak menyokong rakaman'},
    deleteVoice:{en:'Delete',zh:'删除',ms:'Padam'}
  };
  function t(k){return (dict[k] && (dict[k][lang] || dict[k].en)) || k;}
  function fmt(sec){sec=Math.max(0, Math.floor(sec||0)); return String(Math.floor(sec/60)).padStart(2,'0')+':'+String(sec%60).padStart(2,'0');}
  function chooseMime(){
    if(!window.MediaRecorder) return '';
    const ua = navigator.userAgent || '';
    const isiOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const types = isiOS
      ? ['audio/mp4','audio/aac','audio/webm;codecs=opus','audio/webm','audio/ogg;codecs=opus']
      : ['audio/webm;codecs=opus','audio/webm','audio/mp4','audio/aac','audio/ogg;codecs=opus'];
    for(const mt of types){try{if(MediaRecorder.isTypeSupported(mt)) return mt;}catch(e){}}
    return '';
  }
  function extFromMime(m){m=(m||'').toLowerCase(); if(m.includes('mp4')) return 'm4a'; if(m.includes('aac')) return 'aac'; if(m.includes('ogg')) return 'ogg'; if(m.includes('wav')) return 'wav'; return 'webm';}
  function setFileToInput(input, file){
    if(!input || !file) return false;
    try{
      const dt = new DataTransfer();
      if(input.multiple && input.files && input.files.length){
        Array.from(input.files).forEach(function(existing){ dt.items.add(existing); });
      }
      dt.items.add(file);
      input.files = dt.files;
      input.dispatchEvent(new Event('change', {bubbles:true}));
      return true;
    }catch(e){return false;}
  }
  function removeNamedFileFromInput(input, fileName){
    if(!input || !fileName || !input.files || !input.files.length) return;
    try{
      const dt = new DataTransfer();
      Array.from(input.files).forEach(function(existing){
        if(existing.name !== fileName) dt.items.add(existing);
      });
      input.files = dt.files;
      // Tell enhanced file-list UIs that this change is a removal, not a new append.
      input.dataset.hdForceReplace = '1';
      input.dispatchEvent(new Event('change', {bubbles:true}));
      setTimeout(function(){ input.dataset.hdForceReplace = ''; }, 0);
    }catch(e){}
  }
  function getPanel(btn){
    let p=btn.nextElementSibling;
    if(p && p.classList && p.classList.contains('hd-wa-voice-panel')) return p;
    p=document.createElement('div');
    p.className='hd-wa-voice-panel';
    p.innerHTML='<span class="hd-wa-voice-status"><span class="hd-wa-voice-dot"></span><span class="hd-wa-status-text">'+t('recording')+'</span></span><span class="hd-wa-voice-time">00:00</span><audio controls playsinline preload="metadata" controlsList="nodownload noplaybackrate" style="display:none"></audio><button type="button" class="hd-wa-delete-voice">'+t('deleteVoice')+'</button>';
    btn.insertAdjacentElement('afterend', p);
    return p;
  }
  function setPreviewText(btn, text){
    const id=btn.getAttribute('data-preview');
    const el=id ? document.getElementById(id) : null;
    if(!el) return;
    // If the page has upgraded the preview area into a selected-file list,
    // do not overwrite it with plain voice text after recording stops.
    if(el.querySelector && el.querySelector('.hd-selected-files')) return;
    el.textContent = text;
  }
  function initVoiceButton(btn){
    if(btn.dataset.hdVoiceReady==='1') return;
    btn.dataset.hdVoiceReady='1';
    if(!btn.textContent.trim()) btn.innerHTML='<i class="bi bi-mic-fill"></i> '+t('hold');
    let stream=null, recorder=null, chunks=[], startTime=0, timer=null, recording=false, stoppedByUser=false;
    const targetId=btn.getAttribute('data-target-input') || 'attachment';
    const input=()=>document.getElementById(targetId);
    const panel=getPanel(btn);
    const timeEl=panel.querySelector('.hd-wa-voice-time');
    const statusText=panel.querySelector('.hd-wa-status-text');
    const audio=panel.querySelector('audio');
    const del=panel.querySelector('.hd-wa-delete-voice');
    function resetPanel(){
      panel.classList.remove('show'); audio.style.display='none'; audio.removeAttribute('src');
      statusText.textContent=t('recording'); timeEl.textContent='00:00'; btn.classList.remove('is-recording');
      const inp=input();
      if(inp && btn.dataset.hdVoiceFileName){ removeNamedFileFromInput(inp, btn.dataset.hdVoiceFileName); }
      btn.dataset.hdVoiceFileName='';
      setPreviewText(btn, t('deleted'));
    }
    del.addEventListener('click', resetPanel);
    async function start(e){
      if(e) e.preventDefault();
      if(recording) return;
      if(!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder){ alert(t('unsupported')); return; }
      try{
        stoppedByUser=false; chunks=[];
        stream = await navigator.mediaDevices.getUserMedia({audio:true});
        const mime=chooseMime();
        recorder = mime ? new MediaRecorder(stream,{mimeType:mime}) : new MediaRecorder(stream);
        recorder.ondataavailable = ev => { if(ev.data && ev.data.size>0) chunks.push(ev.data); };
        recorder.onstop = function(){
          clearInterval(timer); timer=null; recording=false; btn.classList.remove('is-recording');
          try{stream && stream.getTracks().forEach(tr=>tr.stop());}catch(e){}
          const dur=Math.max(1, Math.round((Date.now()-startTime)/1000));
          const mimeType=(recorder && recorder.mimeType) || mime || 'audio/webm';
          const blob = new Blob(chunks, {type:mimeType});
          const ext=extFromMime(mimeType);
          const file = new File([blob], 'voice_'+Date.now()+'.'+ext, {type:mimeType});
          btn.dataset.hdVoiceFileName = file.name;
          setFileToInput(input(), file);
          const url=URL.createObjectURL(blob);
          audio.src=url; audio.style.display='block';
          panel.classList.add('show'); statusText.textContent=t('ready'); timeEl.textContent=fmt(dur);
          setPreviewText(btn, t('ready')+' '+fmt(dur)+' · '+file.name);
        };
        recorder.start(); recording=true; startTime=Date.now(); btn.classList.add('is-recording'); panel.classList.add('show'); audio.style.display='none'; statusText.textContent=t('release');
        timer=setInterval(()=>{timeEl.textContent=fmt((Date.now()-startTime)/1000);},250);
      }catch(err){ alert(t('micDenied')); }
    }
    function stop(e){
      if(e) e.preventDefault();
      if(!recording || !recorder) return;
      stoppedByUser=true;
      try{recorder.stop();}catch(e){}
    }
    btn.addEventListener('pointerdown', start);
    btn.addEventListener('pointerup', stop);
    btn.addEventListener('pointerleave', function(e){ if(recording) stop(e); });
    btn.addEventListener('pointercancel', stop);
    btn.addEventListener('contextmenu', e=>e.preventDefault());
    // fallback for keyboards/desktops: click toggles recording
    btn.addEventListener('click', function(e){ if(e.pointerType) return; if(recording) stop(e); else start(e); });
  }
  function initAllVoice(root){(root||document).querySelectorAll('.hd-wa-record-btn').forEach(initVoiceButton);}


  function initAttachmentAccumulators(root){
    if(!window.DataTransfer) return;
    (root || document).querySelectorAll('input[type="file"][multiple][name="attachments[]"]').forEach(function(input){
      if(input.dataset.hdMultiAccumReady === '1') return;
      input.dataset.hdMultiAccumReady = '1';
      input._hdFiles = input._hdFiles || [];

      function keyOf(f){
        return [f.name || '', f.size || 0, f.lastModified || 0, f.type || ''].join('::');
      }
      function applyFiles(files){
        try{
          const dt = new DataTransfer();
          files.forEach(function(f){ dt.items.add(f); });
          input.dataset.hdAccumulating = '1';
          input.files = dt.files;
          input.dispatchEvent(new Event('change', {bubbles:true}));
          setTimeout(function(){ input.dataset.hdAccumulating = ''; }, 0);
        }catch(e){
          input.dataset.hdAccumulating = '';
        }
      }

      input.addEventListener('change', function(){
        if(input.dataset.hdAccumulating === '1') return;
        const newlySelected = Array.from(input.files || []);
        if(!newlySelected.length){
          input._hdFiles = [];
          return;
        }

        const map = new Map();
        (input._hdFiles || []).forEach(function(f){ map.set(keyOf(f), f); });
        newlySelected.forEach(function(f){ map.set(keyOf(f), f); });
        input._hdFiles = Array.from(map.values());

        if(input._hdFiles.length !== newlySelected.length ||
           input._hdFiles.some(function(f, i){ return f !== newlySelected[i]; })){
          applyFiles(input._hdFiles);
        }
      });
    });
  }

  const HD_PULL_REFRESH_ALLOWED = <?= json_encode(in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), [
    'ticket_list.php',
    'closed_tickets.php',
    'asset_list.php',
    'knowledge_base.php',
    'announcements.php',
    'notifications.php',
    'audit_logs.php'
], true)); ?>;

  function initPullRefresh(){
    if(!HD_PULL_REFRESH_ALLOWED) return;
    if(!('ontouchstart' in window) || document.body.dataset.hdPullRefreshReady==='1') return;
    if(document.querySelector('form')) return;
    document.body.dataset.hdPullRefreshReady='1';
    let startY=0, pulling=false, distance=0;
    const indicator=document.createElement('div');
    indicator.className='hd-pull-refresh-indicator';
    indicator.innerHTML='<i class="bi bi-arrow-down-circle"></i> '+window.refreshLabels.pull;
    document.body.appendChild(indicator);
    document.addEventListener('touchstart', function(e){
      if(window.scrollY<=0 && e.touches && e.touches.length===1){startY=e.touches[0].clientY;pulling=true;distance=0;}
    }, {passive:true});
    document.addEventListener('touchmove', function(e){
      if(!pulling || !e.touches || e.touches.length!==1) return;
      distance=e.touches[0].clientY-startY;
      if(distance>35){indicator.classList.add('show');
        if(distance>90){indicator.classList.add('ready');indicator.innerHTML='<i class="bi bi-arrow-clockwise"></i> '+window.refreshLabels.release;}
        else{indicator.classList.remove('ready');indicator.innerHTML='<i class="bi bi-arrow-down-circle"></i> '+window.refreshLabels.pull;}
      }
    }, {passive:true});
    document.addEventListener('touchend', function(){
      if(pulling && distance>90){indicator.innerHTML='<i class="bi bi-arrow-repeat"></i> '+window.refreshLabels.refreshing;setTimeout(function(){location.reload();},180);} 
      else{indicator.classList.remove('show','ready');}
      pulling=false;distance=0;
    }, {passive:true});
  }

  document.addEventListener('DOMContentLoaded', function(){ initAttachmentAccumulators(document); initAllVoice(document); initPullRefresh(); });
  const mo=new MutationObserver(ms=>ms.forEach(m=>m.addedNodes.forEach(n=>{if(n.nodeType===1){ initAttachmentAccumulators(n); initAllVoice(n); }}))); 
  document.addEventListener('DOMContentLoaded', function(){ if(document.body) mo.observe(document.body,{childList:true,subtree:true}); });
})();
</script>


</body>

</html>
