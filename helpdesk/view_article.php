<?php
require 'header.php';
require 'db.php';
require_once 'module_permissions.php';
require_once 'kb_attachment_lib.php';
require_once 'kb_org_lib.php';
require_once 'kb_content_translate.php';
require_once 'attachment_preview.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
kb_org_ensure_schema($pdo);
hd_ensure_kb_translation_columns($pdo);

function kb_render_content_multi($text){
    $text = trim((string)$text);
    if ($text === '') return '<div class="empty-content">'.__('No content.').'</div>';
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $html=''; $inList=false;
    foreach($lines as $line){
        $line=trim($line);
        if($line===''){ if($inList){$html.='</ol>'; $inList=false;} $html.='<div class="kb-gap"></div>'; continue; }
        if(preg_match('/^(\d+)[\.)]\s*(.+)$/',$line,$m)){ if(!$inList){$html.='<ol class="kb-steps">';$inList=true;} $html.='<li>'.kb_h($m[2]).'</li>'; continue; }
        if($inList){$html.='</ol>'; $inList=false;}
        if(preg_match('/^[-•]\s*(.+)$/',$line,$m)) $html.='<div class="kb-bullet"><span>•</span><p>'.kb_h($m[1]).'</p></div>';
        elseif(preg_match('/^(Purpose|Scope|Step-by-step Procedure|Procedure|Important Notes|PIC\s*\/\s*Department|Department|Solution|Problem|Cause|Checklist|Remark)\s*:?(.*)$/i',$line,$m)){ $html.='<div class="kb-section-title"><i class="bi bi-bookmark-check"></i>'.kb_h($m[1]).'</div>'; if(trim($m[2]??'')!=='') $html.='<p class="kb-p">'.kb_h(trim($m[2])).'</p>'; }
        elseif(substr($line,-1)===':' && strlen($line)<=60) $html.='<div class="kb-section-title"><i class="bi bi-bookmark-check"></i>'.kb_h(rtrim($line,':')).'</div>';
        else $html.='<p class="kb-p">'.kb_h($line).'</p>';
    }
    if($inList) $html.='</ol>';
    return $html;
}

$id=(int)($_GET['id']??0);
if($id<=0) die(__('Invalid article id'));
$hasViews=kb_col_exists($pdo,'knowledge_base','views');
$hasStatus=kb_col_exists($pdo,'knowledge_base','status');
$hasUpdatedAt=kb_col_exists($pdo,'knowledge_base','updated_at');
if($hasViews) $pdo->prepare('UPDATE knowledge_base SET views=COALESCE(views,0)+1 WHERE id=?')->execute([$id]);
$stmt=$pdo->prepare('SELECT * FROM knowledge_base WHERE id=?'); $stmt->execute([$id]); $article=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$article) die(__('Article not found'));
$displayTitle = hd_kb_title($pdo, $article);
$displayContent = hd_kb_content($pdo, $article);
if(!kb_org_can_view_article($article)) die(__('You do not have permission to view this article.'));
$attachments=kb_get_attachments($pdo,$id,$article);
$canManageKb=has_action_permission('manage_kb');
$created=!empty($article['created_at'])?date('d/m/Y h:i A',strtotime($article['created_at'])):'-';
$updated=($hasUpdatedAt&&!empty($article['updated_at']))?date('d/m/Y h:i A',strtotime($article['updated_at'])):'-';
$status=$hasStatus?($article['status']??'Published'):'Published';
$isDraft=strtolower($status)==='draft';
?>
<style>
.kb-view-wrap{max-width:1180px;margin:0 auto}.kb-hero{background:linear-gradient(135deg,#f8fbff,#eef4ff);border:1px solid #e4ebf7;border-radius:22px;padding:24px 26px;margin-bottom:18px;box-shadow:0 12px 35px rgba(15,23,42,.06)}.kb-icon-lg{width:64px;height:64px;border-radius:20px;background:linear-gradient(135deg,#4f46e5,#2563eb);color:#fff;display:flex;align-items:center;justify-content:center;font-size:30px}.kb-title{font-size:30px;font-weight:900;color:#111827;margin:0}.kb-sub{color:#64748b;font-size:14px;margin-top:6px}.kb-pill{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:7px 12px;font-weight:800;font-size:12px}.kb-pill-blue{background:#eef2ff;color:#4338ca}.kb-pill-green{background:#dcfce7;color:#15803d}.kb-pill-yellow{background:#fef3c7;color:#b45309}.kb-pill-gray{background:#f8fafc;color:#334155;border:1px solid #e2e8f0}.kb-action-btn{border-radius:12px;font-weight:800;padding:9px 14px}.kb-layout{display:grid;grid-template-columns:1fr 340px;gap:18px}.kb-card{background:#fff;border:1px solid #e5eaf2;border-radius:20px;box-shadow:0 10px 30px rgba(15,23,42,.05);overflow:hidden}.kb-card-head{padding:18px 22px;border-bottom:1px solid #edf2f7;font-weight:900;color:#0f172a}.kb-content{padding:26px 30px;font-size:15px;color:#1f2937;line-height:1.75}.kb-section-title{margin:22px 0 10px;font-size:16px;font-weight:900;color:#111827;display:flex;align-items:center;gap:8px}.kb-section-title:first-child{margin-top:0}.kb-section-title i{color:#4f46e5}.kb-p{margin:0 0 12px}.kb-gap{height:8px}.kb-steps{counter-reset:item;margin:8px 0 18px;padding:0;list-style:none}.kb-steps li{position:relative;margin:10px 0;padding:13px 14px 13px 48px;background:#f8fafc;border:1px solid #e5eaf2;border-radius:14px}.kb-steps li:before{content:counter(item);counter-increment:item;position:absolute;left:13px;top:12px;width:24px;height:24px;border-radius:50%;background:#2563eb;color:#fff;font-size:12px;font-weight:900;display:flex;align-items:center;justify-content:center}.kb-bullet{display:flex;gap:10px;background:#fbfdff;border-left:4px solid #60a5fa;padding:10px 12px;margin:8px 0;border-radius:10px}.kb-bullet span{font-size:20px;color:#2563eb;line-height:1}.kb-bullet p{margin:0}.kb-side{padding:18px}.kb-info-row{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #edf2f7;padding:13px 0}.kb-info-label{color:#64748b;font-size:13px}.kb-info-val{font-weight:900;color:#111827;text-align:right}.attach-box{display:flex;gap:12px;align-items:center;justify-content:space-between;background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:12px;margin-top:10px}.attach-ico{width:42px;height:42px;border-radius:14px;background:#eef2ff;color:#4338ca;display:flex;align-items:center;justify-content:center;font-size:21px}.empty-content{padding:30px;text-align:center;color:#94a3b8;background:#f8fafc;border-radius:14px}@media(max-width:900px){.kb-layout{grid-template-columns:1fr}.kb-title{font-size:24px}}@media print{.sidebar,.topbar,.btn,.kb-action-btn,.no-print{display:none!important}.kb-card,.kb-hero{box-shadow:none;border:1px solid #ddd}}
</style>
<div class="kb-view-wrap">
    <div class="kb-hero"><div class="d-flex justify-content-between align-items-start flex-wrap gap-3"><div class="d-flex gap-3 align-items-start"><div class="kb-icon-lg"><i class="bi bi-journal-text"></i></div><div><div class="small text-muted fw-bold mb-1"><i class="bi bi-grid-3x3-gap me-1"></i> <?= __('Knowledge Base / View Article') ?></div><h1 class="kb-title"><?= kb_h($displayTitle) ?></h1><div class="kb-sub"><?= __('Created') ?> <?= kb_h($created) ?><?= $updated!=='-'?' • '.__('Updated').' '.kb_h($updated):'' ?></div><div class="d-flex flex-wrap gap-2 mt-3"><span class="kb-pill kb-pill-blue"><i class="bi bi-folder2-open"></i><?= kb_h($article['category']??'-') ?></span><span class="kb-pill <?= $isDraft?'kb-pill-yellow':'kb-pill-green' ?>"><i class="bi <?= $isDraft?'bi-pencil-square':'bi-check-circle' ?>"></i><?= kb_h($status) ?></span><?php if($hasViews): ?><span class="kb-pill kb-pill-gray"><i class="bi bi-eye"></i><?= (int)($article['views']??0) ?> <?= __('views') ?></span><?php endif; ?><span class="kb-pill kb-pill-gray"><i class="bi bi-paperclip"></i><?= count($attachments) ?> <?= __('attachment(s)') ?></span></div></div></div><div class="d-flex gap-2 no-print"><a href="knowledge_base.php" class="btn btn-outline-secondary kb-action-btn"><i class="bi bi-arrow-left me-1"></i><?= __('Back') ?></a><button type="button" onclick="window.print()" class="btn btn-outline-primary kb-action-btn"><i class="bi bi-printer me-1"></i><?= __('Print') ?></button><?php if($canManageKb): ?><a href="edit_article.php?id=<?= $id ?>" class="btn btn-warning kb-action-btn"><i class="bi bi-pencil-square me-1"></i><?= __('Edit') ?></a><?php endif; ?></div></div></div>
    <div class="kb-layout"><div class="kb-card"><div class="kb-card-head"><i class="bi bi-file-earmark-text me-2 text-primary"></i><?= __('Article Content') ?></div><div class="kb-content"><?= kb_render_content_multi($displayContent ?? '') ?></div></div>
    <div class="kb-card"><div class="kb-card-head"><i class="bi bi-info-circle me-2 text-primary"></i><?= __('Article Info') ?></div><div class="kb-side"><div class="kb-info-row"><div class="kb-info-label"><?= __('Category') ?></div><div class="kb-info-val"><?= kb_h($article['category']??'-') ?></div></div><div class="kb-info-row"><div class="kb-info-label"><?= __('Type') ?></div><div class="kb-info-val"><?= kb_h($article['knowledge_type']??'Guide') ?></div></div><div class="kb-info-row"><div class="kb-info-label"><?= __('Branch Scope') ?></div><div class="kb-info-val"><?= kb_h(kb_org_scope_label($article['branch_scope']??'ALL')) ?></div></div><div class="kb-info-row"><div class="kb-info-label"><?= __('Tags') ?></div><div class="kb-info-val"><?= kb_h(function_exists('hd_kb_display_text') ? hd_kb_display_text($article['tags']??'-') : ($article['tags']??'-')) ?></div></div><div class="kb-info-row"><div class="kb-info-label"><?= __('Status') ?></div><div class="kb-info-val"><?= kb_h($status) ?></div></div><div class="kb-info-row"><div class="kb-info-label"><?= __('Created') ?></div><div class="kb-info-val"><?= kb_h($created) ?></div></div><?php if($hasUpdatedAt): ?><div class="kb-info-row"><div class="kb-info-label"><?= __('Updated') ?></div><div class="kb-info-val"><?= kb_h($updated) ?></div></div><?php endif; ?><?php if($hasViews): ?><div class="kb-info-row"><div class="kb-info-label"><?= __('Views') ?></div><div class="kb-info-val"><?= (int)($article['views']??0) ?></div></div><?php endif; ?>
        <div class="mt-3"><div class="fw-bold mb-2"><i class="bi bi-paperclip me-1"></i><?= __('Attachments') ?></div><?php if($attachments): foreach($attachments as $a): ?><?= hd_ap_render($a['file_path'] ?? '', $a['original_name'] ?? basename($a['file_path'] ?? 'file'), __('Article Attachment'), true); ?><?php endforeach; else: ?><div class="p-3 rounded-3 bg-light text-muted small"><i class="bi bi-paperclip me-1"></i><?= __('No attachment.') ?></div><?php endif; ?></div>
    </div></div></div>
</div>
<?= hd_ap_assets(); ?>
<?php require 'footer.php'; ?>
