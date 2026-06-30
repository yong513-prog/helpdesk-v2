<?php
require 'header.php';
require 'db.php';
require_once 'audit_log.php';
require_once 'kb_attachment_lib.php';
require_once 'kb_org_lib.php';
require_once 'kb_content_translate.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'module_permissions.php';
require_action_permission('manage_kb');

kb_ensure_attachment_table($pdo);
kb_org_ensure_schema($pdo);
hd_ensure_kb_translation_columns($pdo);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) die(__('Invalid article id'));

$categories = kb_org_fetch_categories($pdo);
$branches = kb_org_fetch_branches($pdo);
$types = kb_org_types();
$error = '';

$stmt = $pdo->prepare('SELECT * FROM knowledge_base WHERE id=?');
$stmt->execute([$id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$article) die(__('Article not found'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        foreach (($_POST['delete_attachment_ids'] ?? []) as $aid) {
            kb_delete_attachment($pdo, $id, (int)$aid);
        }

        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $knowledge_type = trim($_POST['knowledge_type'] ?? 'Guide');
        $tags = kb_org_normalize_csv($_POST['tags'] ?? '');
        $branch_scope_mode = trim($_POST['branch_scope_mode'] ?? 'ALL');
        $branch_scope = $branch_scope_mode === 'SELECTED' ? kb_org_normalize_csv($_POST['branch_scope'] ?? []) : 'ALL';
        $content = trim($_POST['content'] ?? '');
        $status = trim($_POST['status'] ?? 'Published');
        if ($title === '' || $category === '' || $content === '') throw new Exception(__('Please fill in all required fields.'));

        $tr = hd_build_kb_translations($title, $content);
        $stmt = $pdo->prepare("UPDATE knowledge_base
            SET title=?, category=?, knowledge_type=?, content=?, tags=?, branch_scope=?, status=?,
                title_en=?, title_ms=?, title_zh=?, content_en=?, content_ms=?, content_zh=?, updated_at=NOW()
            WHERE id=?");
        $stmt->execute([$title,$category,$knowledge_type,$content,$tags,$branch_scope,$status,
            $tr['title_en'],$tr['title_ms'],$tr['title_zh'],$tr['content_en'],$tr['content_ms'],$tr['content_zh'],$id]);

        $newFiles = kb_upload_multiple_attachments('attachments', $id);
        kb_save_attachments($pdo, $id, $newFiles);

        $pdo->commit();
        audit_log($pdo, 'Edit Article', 'Updated article '.$title.'; added '.count($newFiles).' attachment(s)');
        header('Location: view_article.php?id='.$id.'&updated=1'); exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }

    $stmt = $pdo->prepare('SELECT * FROM knowledge_base WHERE id=?');
    $stmt->execute([$id]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);
}

$attachments = kb_get_attachments($pdo, $id, $article);
$currentScope = trim((string)($article['branch_scope'] ?? 'ALL'));
$currentScopeArr = kb_org_csv_array($currentScope);
$scopeMode = ($currentScope === '' || strtoupper($currentScope) === 'ALL') ? 'ALL' : 'SELECTED';
?>
<style>
.kb-form-wrap{max-width:1180px;margin:0 auto}.page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:22px}.head-left{display:flex;align-items:center;gap:15px}.head-icon{width:58px;height:58px;border-radius:18px;background:linear-gradient(135deg,#fff7ed,#ffedd5);color:#ea580c;display:flex;align-items:center;justify-content:center;font-size:28px}.page-title{font-weight:850;color:#182033;margin:0}.muted{color:#64748b}.form-shell{background:#fff;border:1px solid #e8edf5;border-radius:20px;box-shadow:0 12px 30px rgba(15,23,42,.07);overflow:hidden}.form-body{padding:26px}.section-title{font-size:14px;font-weight:800;color:#334155;text-transform:uppercase;letter-spacing:.04em;margin-bottom:14px}.form-control,.form-select{border-radius:12px;border-color:#dbe3ee;padding:12px 14px}.content-box{min-height:300px;font-family:Consolas,Menlo,monospace;line-height:1.55}.upload-zone{border:2px dashed #cbd5e1;border-radius:16px;padding:18px;background:#fbfdff}.footer-bar{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;background:#fbfdff;border-top:1px solid #e8edf5;padding:18px 26px}.btn{border-radius:12px;font-weight:700}.hint{font-size:13px;color:#64748b}.required{color:#ef4444}.attach-row{display:flex;align-items:center;justify-content:space-between;gap:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:12px;margin-bottom:10px}.attach-name{font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.branch-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px}.branch-chip{border:1px solid #e2e8f0;border-radius:12px;padding:8px 10px;background:#fff}
</style>
<div class="kb-form-wrap">
    <div class="page-head">
        <div class="head-left"><div class="head-icon"><i class="bi bi-pencil-square"></i></div><div><h2 class="page-title"><?= __('Edit Article') ?></h2><div class="muted"><?= __('Update category, type, tags and branch scope') ?></div></div></div>
        <a href="view_article.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> <?= __('Back') ?></a>
    </div>

    <?php if($error): ?><div class="alert alert-danger"><?= kb_org_h($error) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-shell">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <div class="form-body">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="section-title"><?= __('Article Content') ?></div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('Title') ?> <span class="required">*</span></label>
                        <input type="text" name="title" class="form-control" required value="<?= kb_org_h($article['title'] ?? '') ?>">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= __('Category') ?> <span class="required">*</span></label>
                            <select name="category" class="form-select" required>
                                <?php foreach($categories as $cat): ?><option value="<?= kb_org_h($cat) ?>" <?= (($article['category'] ?? '')===$cat)?'selected':'' ?>><?= kb_org_h($cat) ?></option><?php endforeach; ?>
                            </select>
                            <div class="hint"><?= __('Linked to Knowledge Category Management.') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('Knowledge Type') ?></label>
                            <select name="knowledge_type" class="form-select">
                                <?php foreach($types as $t): ?><option value="<?= kb_org_h($t) ?>" <?= (($article['knowledge_type'] ?? 'Guide')===$t)?'selected':'' ?>><?= kb_org_h($t) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label"><?= __('Tags') ?></label>
                        <input type="text" name="tags" class="form-control" value="<?= kb_org_h($article['tags'] ?? '') ?>" placeholder="<?= __('POS, printer, barcode, sync') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('Content') ?> <span class="required">*</span></label>
                        <textarea name="content" rows="14" class="form-control content-box" required><?= kb_org_h($article['content'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3 upload-zone">
                        <label class="form-label"><?= __('Current Attachments') ?></label>
                        <?php if(!$attachments): ?><div class="hint mb-2"><?= __('No attachment.') ?></div><?php endif; ?>
                        <?php foreach($attachments as $a): ?>
                        <div class="attach-row">
                            <div class="attach-name"><?= kb_org_h($a['original_name'] ?? basename($a['file_path'] ?? 'file')) ?></div>
                            <label class="text-danger"><input type="checkbox" name="delete_attachment_ids[]" value="<?= (int)$a['id'] ?>"> <?= __('Delete') ?></label>
                        </div>
                        <?php endforeach; ?>

                        <label class="form-label mt-3"><?= __('Add More Attachments') ?></label>
                        <input type="file" name="attachments[]" multiple class="form-control">
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="p-3 border rounded-4 bg-light mb-3">
                        <div class="section-title"><?= __('Branch Scope') ?></div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="branch_scope_mode" id="scopeAll" value="ALL" <?= $scopeMode==='ALL'?'checked':'' ?>>
                            <label class="form-check-label fw-bold" for="scopeAll"><?= __('All Branch') ?></label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="branch_scope_mode" id="scopeSelected" value="SELECTED" <?= $scopeMode==='SELECTED'?'checked':'' ?>>
                            <label class="form-check-label fw-bold" for="scopeSelected"><?= __('Selected Branch Only') ?></label>
                        </div>
                        <div class="branch-grid mt-2">
                            <?php foreach($branches as $b): ?>
                            <label class="branch-chip hd-no-translate"><input type="checkbox" name="branch_scope[]" value="<?= kb_org_h($b['branch_code']) ?>" <?= in_array($b['branch_code'],$currentScopeArr,true)?'checked':'' ?>> <?= kb_org_h($b['branch_code']) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="p-3 border rounded-4 bg-light">
                        <div class="section-title"><?= __('Status') ?></div>
                        <select name="status" class="form-select">
                            <option value="Published" <?= (($article['status'] ?? 'Published')==='Published')?'selected':'' ?>><?= __('Published') ?></option>
                            <option value="Draft" <?= (($article['status'] ?? '')==='Draft')?'selected':'' ?>><?= __('Draft') ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer-bar">
            <a href="view_article.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary"><?= __('Cancel') ?></a>
            <button class="btn btn-primary"><i class="bi bi-save"></i> <?= __('Save Changes') ?></button>
        </div>
    </form>
</div>
<?php require 'footer.php'; ?>
