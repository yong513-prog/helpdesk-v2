<?php
require 'header.php';
require 'db.php';
require_once 'audit_log.php';
require_once 'module_permissions.php';
require_once 'announcement_content_translate.php';
require_once 'entity_upload_helper.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_action_permission('manage_announcement');

if(!function_exists('h')) {
    function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

function ensure_announcement_attachment_columns(PDO $pdo) {
    try {
        $cols = [];
        $stmt = $pdo->query("SHOW COLUMNS FROM announcements");
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $cols[$c['Field']] = true;
        }
        if(empty($cols['attachment_path'])) {
            $pdo->exec("ALTER TABLE announcements ADD COLUMN attachment_path varchar(255) DEFAULT NULL AFTER end_date");
        }
        if(empty($cols['attachment_name'])) {
            $pdo->exec("ALTER TABLE announcements ADD COLUMN attachment_name varchar(255) DEFAULT NULL AFTER attachment_path");
        }
    } catch(Exception $e) {}
}

ensure_announcement_attachment_columns($pdo);
hd_ensure_announcement_translation_columns($pdo);

$announcementId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if($announcementId <= 0) {
    echo '<div class="alert alert-danger">'.h(__('Invalid announcement.')).'</div>';
    require 'footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ? LIMIT 1");
$stmt->execute([$announcementId]);
$announcement = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$announcement) {
    echo '<div class="alert alert-warning">'.h(__('Announcement not found.')).'</div>';
    require 'footer.php';
    exit;
}

$error = '';

function hd_ann_delete_old_attachment($path) {
    $path = trim((string)$path);
    if($path === '') return;
    $full = __DIR__ . '/' . ltrim(str_replace('\\', '/', $path), '/');
    if(is_file($full)) {
        @unlink($full);
    }
}

function hd_ann_upload_attachment($announcementId, &$error) {
    if(!isset($_FILES['attachment']) || $_FILES['attachment']['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }

    if($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Attachment upload failed. Please try again.';
        return [null, null];
    }

    $maxSize = 10 * 1024 * 1024;
    if((int)$_FILES['attachment']['size'] > $maxSize) {
        $error = 'Attachment size cannot exceed 10MB.';
        return [null, null];
    }

    $original = (string)$_FILES['attachment']['name'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['pdf','jpg','jpeg','png','gif','webp','doc','docx','xls','xlsx','ppt','pptx','txt','zip','rar'];

    if(!in_array($ext, $allowed, true)) {
        $error = 'File type not allowed. Allowed: PDF, image, Word, Excel, PowerPoint, TXT, ZIP, RAR.';
        return [null, null];
    }

    $annFolder = 'ANN-' . (int)$announcementId;
    $uploadDir = hd_entity_upload_dir('announcements', $annFolder);
    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($original, PATHINFO_FILENAME));
    $safeName = trim($safeName, '._-');
    if($safeName === '') $safeName = 'attachment';
    $newName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeName . '.' . $ext;
    $target = rtrim($uploadDir, '/\\') . '/' . $newName;

    if(!move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
        $error = 'Cannot save attachment. Please check uploads/announcements folder permission.';
        return [null, null];
    }

    return [hd_entity_upload_relative('announcements', $annFolder, $newName), $original];
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $removeAttachment = isset($_POST['remove_attachment']) && $_POST['remove_attachment'] === '1';

    if($title === '' || $content === '') {
        $error = 'Title and content are required.';
    }

    $attachmentPath = $announcement['attachment_path'] ?? null;
    $attachmentName = $announcement['attachment_name'] ?? null;

    if($error === '') {
        [$newAttachmentPath, $newAttachmentName] = hd_ann_upload_attachment($announcementId, $error);

        if($error === '') {
            if($newAttachmentPath !== null) {
                hd_ann_delete_old_attachment($attachmentPath);
                $attachmentPath = $newAttachmentPath;
                $attachmentName = $newAttachmentName;
            } elseif($removeAttachment) {
                hd_ann_delete_old_attachment($attachmentPath);
                $attachmentPath = null;
                $attachmentName = null;
            }
        }
    }

    if($error === '') {
        $start_date = ($start_date !== '') ? $start_date : null;
        $end_date = ($end_date !== '') ? $end_date : null;
        $tr = hd_build_announcement_translations($title, $content);

        try {
            $stmt = $pdo->prepare("
                UPDATE announcements
                SET title = ?,
                    content = ?,
                    title_en = ?,
                    title_ms = ?,
                    title_zh = ?,
                    content_en = ?,
                    content_ms = ?,
                    content_zh = ?,
                    start_date = ?,
                    end_date = ?,
                    attachment_path = ?,
                    attachment_name = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $title,
                $content,
                $tr['title_en'],
                $tr['title_ms'],
                $tr['title_zh'],
                $tr['content_en'],
                $tr['content_ms'],
                $tr['content_zh'],
                $start_date,
                $end_date,
                $attachmentPath,
                $attachmentName,
                $announcementId
            ]);
        } catch(Exception $e) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE announcements
                    SET title = ?,
                        content = ?,
                        start_date = ?,
                        end_date = ?,
                        attachment_path = ?,
                        attachment_name = ?
                    WHERE id = ?
                ");
                $stmt->execute([$title, $content, $start_date, $end_date, $attachmentPath, $attachmentName, $announcementId]);
            } catch(Exception $e2) {
                $stmt = $pdo->prepare("
                    UPDATE announcements
                    SET title = ?,
                        content = ?,
                        start_date = ?,
                        end_date = ?
                    WHERE id = ?
                ");
                $stmt->execute([$title, $content, $start_date, $end_date, $announcementId]);
            }
        }

        if(function_exists('audit_log')) {
            audit_log($pdo, 'Edit Announcement', 'Edited announcement: '.$title);
        }

        header("Location: view_announcement.php?id=".$announcementId);
        exit;
    }
}

?>
<style>
.edit-ann-wrap{max-width:1100px;margin:0 auto;}
.edit-ann-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:22px;}
.edit-ann-title{font-size:30px;font-weight:850;color:#0f172a;margin:0;}
.edit-ann-sub{color:#64748b;margin-top:5px;}
.edit-ann-card{background:#fff;border:1px solid #e8eef8;border-radius:20px;box-shadow:0 14px 32px rgba(15,23,42,.06);overflow:hidden;}
.edit-ann-card-head{padding:22px 26px;background:linear-gradient(135deg,#f8faff,#ffffff);border-bottom:1px solid #eef2f7;display:flex;align-items:center;gap:14px;}
.edit-ann-icon{width:48px;height:48px;border-radius:16px;background:linear-gradient(135deg,#f59e0b,#f97316);color:#fff;display:flex;align-items:center;justify-content:center;font-size:23px;}
.edit-ann-body{padding:26px;}
.form-label{font-weight:750;color:#334155;}
.form-control,.form-select{border-radius:12px;border-color:#dbe4f0;}
.form-control:focus{box-shadow:0 0 0 .2rem rgba(245,158,11,.12);}
.attach-box{border:2px dashed #cbd5e1;border-radius:18px;background:#f8fafc;padding:22px;transition:.2s;}
.attach-box:hover{border-color:#f59e0b;background:#fffaf0;}
.attach-help{font-size:13px;color:#64748b;margin-top:8px;}
.current-attach{border:1px solid #e8eef8;border-radius:14px;background:#fff;padding:12px 14px;margin-bottom:12px;display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;}
.preview-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:14px;margin-top:12px;display:none;}
@media(max-width:800px){.edit-ann-head{flex-direction:column}.edit-ann-body{padding:18px}.current-attach{display:block}.current-attach .btn{margin-top:10px}}
</style>

<div class="edit-ann-wrap">
    <div class="edit-ann-head">
        <div>
            <h2 class="edit-ann-title"><i class="bi bi-pencil-square text-warning me-2"></i><?= h(__('Edit Announcement')) ?></h2>
            <div class="edit-ann-sub"><?= h(__('Update published announcement details.')) ?></div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="view_announcement.php?id=<?= (int)$announcementId; ?>" class="btn btn-outline-primary px-4"><i class="bi bi-eye me-2"></i><?= h(__('View')) ?></a>
            <a href="announcements.php" class="btn btn-outline-secondary px-4"><i class="bi bi-arrow-left me-2"></i><?= h(__('Back')) ?></a>
        </div>
    </div>

    <?php if($error !== ''): ?>
        <div class="alert alert-danger"><?= h(__($error)); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="edit-ann-card">
        <input type="hidden" name="id" value="<?= (int)$announcementId; ?>">

        <div class="edit-ann-card-head">
            <div class="edit-ann-icon"><i class="bi bi-megaphone-fill"></i></div>
            <div>
                <div class="fw-bold fs-5"><?= h(__('Announcement Details')) ?></div>
                <div class="text-muted small"><?= h(__('Save changes to the existing announcement.')) ?></div>
            </div>
        </div>

        <div class="edit-ann-body">
            <div class="mb-3">
                <label class="form-label"><?= h(__('Title')) ?> <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control form-control-lg" value="<?= h($announcement['title'] ?? ''); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= h(__('Content')) ?> <span class="text-danger">*</span></label>
                <textarea name="content" rows="8" class="form-control" required><?= h($announcement['content'] ?? ''); ?></textarea>
            </div>

            <div class="mb-4">
                <label class="form-label"><?= h(__('Attachment')) ?></label>
                <div class="attach-box">
                    <?php if(!empty($announcement['attachment_path'])): ?>
                        <div class="current-attach">
                            <div>
                                <div class="fw-bold"><i class="bi bi-paperclip me-1"></i><?= h(__('Current Attachment')) ?></div>
                                <div class="text-muted small"><?= h($announcement['attachment_name'] ?? basename($announcement['attachment_path'])); ?></div>
                            </div>
                            <label class="btn btn-outline-danger btn-sm mb-0">
                                <input type="checkbox" name="remove_attachment" value="1" class="form-check-input me-1">
                                <?= h(__('Remove Attachment')) ?>
                            </label>
                        </div>
                    <?php endif; ?>

                    <input type="file" name="attachment" id="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                    <div class="attach-help"><i class="bi bi-paperclip me-1"></i><?= h(__('Optional. Uploading a new file will replace the current attachment. Max 10MB.')) ?></div>
                    <div id="filePreview" class="preview-box"><i class="bi bi-file-earmark me-2"></i><span id="fileName"></span></div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><?= h(__('Start Date')) ?></label>
                    <input type="date" name="start_date" class="form-control form-control-lg" value="<?= h($announcement['start_date'] ?? ''); ?>">
                    <small class="text-muted"><?= h(__('Leave empty = active immediately')) ?></small>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><?= h(__('End Date')) ?></label>
                    <input type="date" name="end_date" class="form-control form-control-lg" value="<?= h($announcement['end_date'] ?? ''); ?>">
                    <small class="text-muted"><?= h(__('Leave empty = no expiry')) ?></small>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3 flex-wrap">
                <button type="submit" class="btn btn-warning btn-lg px-4"><i class="bi bi-save me-2"></i><?= h(__('Save Changes')) ?></button>
                <a href="view_announcement.php?id=<?= (int)$announcementId; ?>" class="btn btn-light btn-lg px-4"><?= h(__('Cancel')) ?></a>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('attachment')?.addEventListener('change', function(){
    const box = document.getElementById('filePreview');
    const name = document.getElementById('fileName');
    if(this.files && this.files.length > 0){
        name.textContent = this.files[0].name + ' (' + Math.round(this.files[0].size / 1024) + ' KB)';
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }
});
</script>

<?php require 'footer.php'; ?>
