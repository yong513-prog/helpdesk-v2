<?php
if(!function_exists('h')){ function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); } }
require 'header.php';
require 'db.php';
require_once 'audit_log.php';
require_once 'kb_attachment_lib.php';
require_once 'kb_org_lib.php';
require_once 'kb_content_translate.php';
require_once 'notification_helper.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'module_permissions.php';
require_action_permission('manage_kb');

kb_ensure_attachment_table($pdo);
kb_org_ensure_schema($pdo);
hd_ensure_kb_translation_columns($pdo);

$categories = kb_org_fetch_categories($pdo);
$branches = kb_org_fetch_branches($pdo);
$types = kb_org_types();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $knowledge_type = trim($_POST['knowledge_type'] ?? 'Guide');
    $tags = kb_org_normalize_csv($_POST['tags'] ?? '');
    $branch_scope_mode = trim($_POST['branch_scope_mode'] ?? 'ALL');
    $branch_scope = $branch_scope_mode === 'SELECTED' ? kb_org_normalize_csv($_POST['branch_scope'] ?? []) : 'ALL';
    $content = trim($_POST['content'] ?? '');
    $status = trim($_POST['status'] ?? 'Published');

    if ($title === '' || $category === '' || $content === '') {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $tr = hd_build_kb_translations($title, $content);
            $stmt = $pdo->prepare("INSERT INTO knowledge_base
                (title, category, knowledge_type, content, tags, branch_scope, status, title_en, title_ms, title_zh, content_en, content_ms, content_zh, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $stmt->execute([$title,$category,$knowledge_type,$content,$tags,$branch_scope,$status,
                $tr['title_en'],$tr['title_ms'],$tr['title_zh'],$tr['content_en'],$tr['content_ms'],$tr['content_zh']]);
            $articleId = (int)$pdo->lastInsertId();

            $attachments = kb_upload_multiple_attachments('attachments', $articleId);
            kb_save_attachments($pdo, $articleId, $attachments);


            // Internal notification for published Knowledge Base articles.
            if(strtolower($status) === 'published') {
                try {
                    notification_ensure_schema($pdo);
                    $stmtUsers = $pdo->query("SELECT id FROM users WHERE COALESCE(status,'active')='active'");
                    $userIds = array_map('intval', $stmtUsers->fetchAll(PDO::FETCH_COLUMN));
                    notification_create_many(
                        $pdo,
                        $userIds,
                        null,
                        'knowledge_base',
                        'New Knowledge Base Article: '.$title,
                        mb_substr(strip_tags($content), 0, 180),
                        'view_article.php?id='.$articleId,
                        (int)($_SESSION['user_id'] ?? 0)
                    );
                } catch(Exception $e) {}
            }

            audit_log($pdo, 'Create Article', 'Created article '.$title.' with '.count($attachments).' attachment(s)');
            header('Location: view_article.php?id='.$articleId.'&created=1'); exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<style>
.kb-form-wrap{max-width:1180px;margin:0 auto}.page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:22px}.head-left{display:flex;align-items:center;gap:15px}.head-icon{width:58px;height:58px;border-radius:18px;background:linear-gradient(135deg,#eef2ff,#dbeafe);color:#4f46e5;display:flex;align-items:center;justify-content:center;font-size:28px}.page-title{font-weight:850;color:#182033;margin:0}.muted{color:#64748b}.form-shell{background:#fff;border:1px solid #e8edf5;border-radius:20px;box-shadow:0 12px 30px rgba(15,23,42,.07);overflow:hidden}.form-body{padding:26px}.section-title{font-size:14px;font-weight:800;color:#334155;text-transform:uppercase;letter-spacing:.04em;margin-bottom:14px}.form-control,.form-select{border-radius:12px;border-color:#dbe3ee;padding:12px 14px}.content-box{min-height:300px;font-family:Consolas,Menlo,monospace;line-height:1.55}.quick-card{background:#f8fafc;border:1px solid #e8edf5;border-radius:16px;padding:16px}.quick-btn{border-radius:12px;text-align:left;width:100%;margin-bottom:10px;background:#fff;border:1px solid #e2e8f0;padding:10px 12px;font-weight:650}.upload-zone{border:2px dashed #cbd5e1;border-radius:16px;padding:18px;background:#fbfdff}.footer-bar{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;background:#fbfdff;border-top:1px solid #e8edf5;padding:18px 26px}.btn{border-radius:12px;font-weight:700}.hint{font-size:13px;color:#64748b}.counter{font-size:12px;color:#64748b}.preview-card{white-space:pre-wrap;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;max-height:240px;overflow:auto}.required{color:#ef4444}.file-list{font-size:13px;margin-top:10px;color:#334155}.branch-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px}.branch-chip{border:1px solid #e2e8f0;border-radius:12px;padding:8px 10px;background:#fff}.tag-help{font-size:12px;color:#64748b;margin-top:6px}
</style>
<div class="kb-form-wrap">
    <div class="page-head">
        <div class="head-left"><div class="head-icon"><i class="bi bi-journal-plus"></i></div><div><h2 class="page-title">Add Article</h2><div class="muted">Create organized knowledge article with type, tags and branch scope</div></div></div>
        <a href="knowledge_base.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <?php if($error): ?><div class="alert alert-danger"><?= kb_org_h($error) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-shell">
        <div class="form-body">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="section-title">Article Content</div>
                    <div class="mb-3">
                        <label class="form-label">Title <span class="required">*</span></label>
                        <input type="text" name="title" id="title" class="form-control" required>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Category <span class="required">*</span></label>
                            <select name="category" class="form-select" required>
                                <?php foreach($categories as $cat): ?><option value="<?= kb_org_h($cat) ?>"><?= kb_org_h($cat) ?></option><?php endforeach; ?>
                            </select>
                            <div class="hint"><?= h(__('Linked to Knowledge Category Management.')) ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Knowledge Type</label>
                            <select name="knowledge_type" class="form-select">
                                <?php foreach($types as $t): ?><option value="<?= kb_org_h($t) ?>"><?= kb_org_h($t) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label">Tags</label>
                        <input type="text" name="tags" class="form-control" placeholder="POS, printer, barcode, sync">
                        <div class="tag-help">Separate tags by comma. Example: POS, sync, price update</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Content <span class="required">*</span></label>
                        <textarea name="content" id="content" class="form-control content-box" required placeholder="Purpose:&#10;&#10;Scope:&#10;&#10;Step-by-step Procedure:&#10;1. &#10;2. &#10;3. &#10;&#10;Important Notes:&#10;- "></textarea>
                        <div class="counter"><span id="charCount">0</span> characters</div>
                    </div>

                    <div class="mb-3 upload-zone">
                        <label class="form-label">Attachments</label>
                        <input type="file" name="attachments[]" multiple class="form-control">
                        <div class="hint">PDF, image, Word, Excel, PowerPoint, TXT, CSV, ZIP. Max 10MB each.</div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="quick-card mb-3">
                        <div class="section-title">Quick Template</div>
                        <button type="button" class="quick-btn" data-tpl="sop">SOP Template</button>
                        <button type="button" class="quick-btn" data-tpl="faq">FAQ Template</button>
                        <button type="button" class="quick-btn" data-tpl="troubleshoot">Troubleshooting Template</button>
                    </div>

                    <div class="quick-card mb-3">
                        <div class="section-title">Branch Scope</div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="branch_scope_mode" id="scopeAll" value="ALL" checked>
                            <label class="form-check-label fw-bold" for="scopeAll">All Branch</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="branch_scope_mode" id="scopeSelected" value="SELECTED">
                            <label class="form-check-label fw-bold" for="scopeSelected">Selected Branch Only</label>
                        </div>
                        <div class="branch-grid mt-2" id="branchGrid">
                            <?php foreach($branches as $b): ?>
                            <label class="branch-chip hd-no-translate"><input type="checkbox" name="branch_scope[]" value="<?= kb_org_h($b['branch_code']) ?>"> <?= kb_org_h($b['branch_code']) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="quick-card">
                        <div class="section-title">Status</div>
                        <select name="status" class="form-select">
                            <option value="Published">Published</option>
                            <option value="Draft">Draft</option>
                        </select>
                        <div class="hint mt-2">Draft articles are for admin review.</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer-bar">
            <a href="knowledge_base.php" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-primary"><i class="bi bi-save"></i> 保存文章</button>
        </div>
    </form>
</div>

<script>
(function(){
const content=document.getElementById('content'), count=document.getElementById('charCount');
const templates={
sop:<?= json_encode(hd_t("Purpose:")."\n\n".hd_t("Scope:")."\n\n".hd_t("Step-by-step Procedure:")."\n1. \n2. \n3. \n\n".hd_t("Important Notes:")."\n- \n\nPIC / Department:", JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>,
faq:<?= json_encode(hd_t("Question:")."\n\n".hd_t("Answer:")."\n\n".hd_t("When to use this guide:")."\n\n".hd_t("Related Ticket Category:")."\n\n".hd_t("Remark:"), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>,
troubleshoot:<?= json_encode(hd_t("Problem:")."\n\n".hd_t("Possible Cause:")."\n\n".hd_t("Checklist:")."\n- \n- \n\n".hd_t("Solution:")."\n1. \n2. \n3. \n\n".hd_t("Escalate to:"), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>
};
document.querySelectorAll('.quick-btn').forEach(btn=>btn.addEventListener('click',()=>{content.value=templates[btn.dataset.tpl]||'';content.dispatchEvent(new Event('input'));content.focus();}));
content.addEventListener('input',()=>count.textContent=content.value.length);
content.dispatchEvent(new Event('input'));
})();
</script>
<?php require 'footer.php'; ?>
