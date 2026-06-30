<?php
require 'header.php';
require 'db.php';
require_once 'audit_log.php';
require_once 'module_permissions.php';
require_once 'announcement_content_translate.php';
require_once 'notification_helper.php';
require_once 'entity_upload_helper.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_action_permission('manage_announcement');
function ensure_announcement_attachment_columns(PDO $pdo) {
    $cols = [];
    try {
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
    } catch(Exception $e) {
        // Keep page usable; form will show normal error if insert fails.
    }
}
ensure_announcement_attachment_columns($pdo);
hd_ensure_announcement_translation_columns($pdo);

$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');

    $attachmentPath = null;
    $attachmentName = null;

    if($title === '' || $content === '') {
        $error = 'Title and content are required.';
    } else {
        if(isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            if($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Attachment upload failed. Please try again.';
            } else {
                $maxSize = 10 * 1024 * 1024; // 10MB
                if($_FILES['attachment']['size'] > $maxSize) {
                    $error = 'Attachment size cannot exceed 10MB.';
                } else {
                    $original = $_FILES['attachment']['name'];
                    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
                    $allowed = ['pdf','jpg','jpeg','png','gif','webp','doc','docx','xls','xlsx','ppt','pptx','txt','zip','rar'];
                    if(!in_array($ext, $allowed, true)) {
                        $error = 'File type not allowed. Allowed: PDF, image, Word, Excel, PowerPoint, TXT, ZIP, RAR.';
                    } else {
                        $announcementFolder = '_pending';
                        $uploadDir = hd_entity_upload_dir('announcements', $announcementFolder);
                        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($original, PATHINFO_FILENAME));
                        $newName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeName . '.' . $ext;
                        $target = rtrim($uploadDir, '/\\') . '/' . $newName;
                        if(move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
                            $attachmentPath = hd_entity_upload_relative('announcements', $announcementFolder, $newName);
                            $attachmentName = $original;
                        } else {
                            $error = 'Cannot save attachment. Please check uploads/announcements folder permission.';
                        }
                    }
                }
            }
        }
    }

    if($error === '') {
        $start_date = ($start_date !== '') ? $start_date : null;
        $end_date = ($end_date !== '') ? $end_date : null;

        try {
            $tr = hd_build_announcement_translations($title, $content);
            $stmt = $pdo->prepare("INSERT INTO announcements (title, content, title_en, title_ms, title_zh, content_en, content_ms, content_zh, start_date, end_date, attachment_path, attachment_name, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $content, $tr['title_en'], $tr['title_ms'], $tr['title_zh'], $tr['content_en'], $tr['content_ms'], $tr['content_zh'], $start_date, $end_date, $attachmentPath, $attachmentName, $_SESSION['user_id']]);
        } catch(Exception $e) {
            // Fallback for old database without translation/attachment columns.
            try {
                $stmt = $pdo->prepare("INSERT INTO announcements (title, content, start_date, end_date, attachment_path, attachment_name, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $content, $start_date, $end_date, $attachmentPath, $attachmentName, $_SESSION['user_id']]);
            } catch(Exception $e2) {
                $stmt = $pdo->prepare("INSERT INTO announcements (title, content, start_date, end_date, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $content, $start_date, $end_date, $_SESSION['user_id']]);
            }
        }

        $announcementId = (int)$pdo->lastInsertId();
        if($announcementId > 0 && !empty($attachmentPath)) {
            try {
                $oldFull = __DIR__ . '/' . ltrim(str_replace('\\','/', $attachmentPath), '/');
                if(is_file($oldFull)) {
                    $annFolder = 'ANN-' . $announcementId;
                    $newDir = hd_entity_upload_dir('announcements', $annFolder);
                    $newFull = rtrim($newDir, '/\\') . '/' . basename($attachmentPath);
                    if(@rename($oldFull, $newFull) || (@copy($oldFull, $newFull) && @unlink($oldFull))) {
                        $attachmentPath = hd_entity_upload_relative('announcements', $annFolder, basename($newFull));
                        try {
                            $pdo->prepare("UPDATE announcements SET attachment_path=? WHERE id=?")->execute([$attachmentPath, $announcementId]);
                        } catch(Exception $ignore) {}
                    }
                }
            } catch(Exception $ignore) {}
        }

        if(function_exists('audit_log')) {
            audit_log($pdo, 'Create Announcement', 'Created announcement: '.$title);
        }


        // Internal notification for active users.
        try {
            notification_ensure_schema($pdo);
            $stmtUsers = $pdo->query("SELECT id FROM users WHERE COALESCE(status,'active')='active'");
            $userIds = array_map('intval', $stmtUsers->fetchAll(PDO::FETCH_COLUMN));
            notification_create_many(
                $pdo,
                $userIds,
                null,
                'announcement',
                'New Announcement: '.$title,
                mb_substr(strip_tags($content), 0, 180),
                'announcements.php',
                (int)($_SESSION['user_id'] ?? 0)
            );
        } catch(Exception $e) {}

        header("Location: announcements.php");
        exit;
    }
}
?>

<style>
.add-ann-wrap{max-width:1100px;margin:0 auto;}
.add-ann-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:22px;}
.add-ann-title{font-size:30px;font-weight:850;color:#0f172a;margin:0;}
.add-ann-sub{color:#64748b;margin-top:5px;}
.add-ann-card{background:#fff;border:1px solid #e8eef8;border-radius:20px;box-shadow:0 14px 32px rgba(15,23,42,.06);overflow:hidden;}
.add-ann-card-head{padding:22px 26px;background:linear-gradient(135deg,#f8faff,#ffffff);border-bottom:1px solid #eef2f7;display:flex;align-items:center;gap:14px;}
.add-ann-icon{width:48px;height:48px;border-radius:16px;background:linear-gradient(135deg,#6366f1,#2563eb);color:#fff;display:flex;align-items:center;justify-content:center;font-size:23px;}
.add-ann-body{padding:26px;}
.form-label{font-weight:750;color:#334155;}
.form-control,.form-select{border-radius:12px;border-color:#dbe4f0;}
.form-control:focus{box-shadow:0 0 0 .2rem rgba(37,99,235,.12);}
.attach-box{border:2px dashed #cbd5e1;border-radius:18px;background:#f8fafc;padding:22px;transition:.2s;}
.attach-box:hover{border-color:#6366f1;background:#f8faff;}
.attach-help{font-size:13px;color:#64748b;margin-top:8px;}
.preview-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:14px;margin-top:12px;display:none;}
@media(max-width:800px){.add-ann-head{flex-direction:column}}
</style>

<div class="add-ann-wrap">
    <div class="add-ann-head">
        <div>
            <h2 class="add-ann-title"><i class="bi bi-plus-circle text-primary me-2"></i>Add Announcement</h2>
            <div class="add-ann-sub">Create company notice with optional attachment.</div>
        </div>
        <a href="announcements.php" class="btn btn-outline-secondary px-4"><i class="bi bi-arrow-left me-2"></i>Back</a>
    </div>

    <?php if($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="add-ann-card">
        <div class="add-ann-card-head">
            <div class="add-ann-icon"><i class="bi bi-megaphone-fill"></i></div>
            <div>
                <div class="fw-bold fs-5">Announcement Details</div>
                <div class="text-muted small">Users need to open the announcement details page; it will auto mark as read.</div>
            </div>
        </div>

        <div class="add-ann-body">
            <div class="mb-3">
                <label class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control form-control-lg" placeholder="<?= __('Example: POS Maintenance') ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Content <span class="text-danger">*</span></label>
                <textarea name="content" rows="8" class="form-control" placeholder="<?= __('Write announcement content, date, time and instructions...') ?>" required></textarea>
            </div>

            <div class="mb-4">
                <label class="form-label">Attachment</label>
                <div class="attach-box">
                    <input type="file" name="attachment" id="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                    <div class="attach-help"><i class="bi bi-paperclip me-1"></i>Optional. Max 10MB. Allowed: PDF, image, Word, Excel, PowerPoint, TXT, ZIP, RAR.</div>
                    <div id="filePreview" class="preview-box"><i class="bi bi-file-earmark me-2"></i><span id="fileName"></span></div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-lg">
                    <small class="text-muted"><?= __('Leave empty = active immediately') ?></small>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-lg">
                    <small class="text-muted"><?= __('Leave empty = no expiry') ?></small>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-lg px-4"><i class="bi bi-save me-2"></i>Save Announcement</button>
                <a href="announcements.php" class="btn btn-light btn-lg px-4">Cancel</a>
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
