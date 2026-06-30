<?php
require 'header.php';
require 'db.php';
require_once 'module_permissions.php';
require_once 'kb_org_lib.php';

require_module_permission('kb_category_management');
kb_org_ensure_schema($pdo);
kb_category_master_ensure($pdo);

function kbc_h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function kbc_redirect($msg='', $err=''){
    header('Location: kb_category_management.php?msg='.urlencode($msg).'&err='.urlencode($err));
    exit;
}
function kbc_usage_count(PDO $pdo, string $name): int {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM knowledge_base WHERE UPPER(TRIM(category)) = UPPER(TRIM(?))");
        $stmt->execute([$name]);
        return (int)$stmt->fetchColumn();
    } catch(Exception $e) { return 0; }
}
function kbc_name_by_id(PDO $pdo, int $id): string {
    $stmt = $pdo->prepare("SELECT category_name FROM kb_category_master WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    return (string)$stmt->fetchColumn();
}

if(isset($_GET['delete_id'])){
    $id = (int)$_GET['delete_id'];
    if($id <= 0) kbc_redirect('', 'Invalid category.');
    $name = kbc_name_by_id($pdo, $id);
    if($name === '') kbc_redirect('', 'Category not found.');
    $used = kbc_usage_count($pdo, $name);
    if($used > 0){
        $stmt = $pdo->prepare("UPDATE kb_category_master SET status=0, updated_at=NOW() WHERE id=?");
        $stmt->execute([$id]);
        kbc_redirect('This category is used by '.$used.' article(s), so it was disabled instead of deleted.');
    }
    $stmt = $pdo->prepare("DELETE FROM kb_category_master WHERE id=?");
    $stmt->execute([$id]);
    kbc_redirect('Unused Knowledge Category deleted successfully.');
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $action = $_POST['action'] ?? '';
    if($action === 'add'){
        $name = trim($_POST['category_name'] ?? '');
        $sort = (int)($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        if($name === '') kbc_redirect('', 'Category name cannot be empty.');
        try {
            $stmt = $pdo->prepare("INSERT INTO kb_category_master (category_name,status,sort_order,updated_at) VALUES (?,?,?,NOW())");
            $stmt->execute([$name,$status,$sort]);
            kbc_redirect('Knowledge Category added successfully.');
        } catch(Exception $e){ kbc_redirect('', 'Add failed. Category may already exist.'); }
    }
    if($action === 'edit'){
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['category_name'] ?? '');
        $sort = (int)($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        if($id <= 0 || $name === '') kbc_redirect('', 'Invalid category.');
        try {
            $stmt = $pdo->prepare("UPDATE kb_category_master SET category_name=?, status=?, sort_order=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$name,$status,$sort,$id]);
            kbc_redirect('Knowledge Category updated successfully.');
        } catch(Exception $e){ kbc_redirect('', 'Update failed. Category may already exist.'); }
    }
    if($action === 'toggle'){
        $id = (int)($_POST['id'] ?? 0);
        if($id <= 0) kbc_redirect('', 'Invalid category.');
        $stmt = $pdo->prepare("UPDATE kb_category_master SET status = IF(status=1,0,1), updated_at=NOW() WHERE id=?");
        $stmt->execute([$id]);
        kbc_redirect('Knowledge Category status updated.');
    }
}

$rows = $pdo->query("SELECT * FROM kb_category_master ORDER BY sort_order ASC, category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$usage = [];
try {
    $uRows = $pdo->query("SELECT UPPER(TRIM(category)) AS category_key, COUNT(*) AS total FROM knowledge_base WHERE category IS NOT NULL AND category<>'' GROUP BY UPPER(TRIM(category))")->fetchAll(PDO::FETCH_ASSOC);
    foreach($uRows as $u){ $usage[(string)$u['category_key']] = (int)$u['total']; }
} catch(Exception $e) {}
?>

<style>
.kbc-page{max-width:1120px;margin:0 auto 28px}.kbc-hero{background:linear-gradient(135deg,#fff,#f8fbff);border:1px solid #e7edf7;border-radius:18px;padding:22px;margin-bottom:18px;box-shadow:0 8px 24px rgba(15,23,42,.05)}
.kbc-grid{display:grid;grid-template-columns:360px 1fr;gap:18px}.kbc-card{background:#fff;border:1px solid #e7edf7;border-radius:18px;box-shadow:0 8px 24px rgba(15,23,42,.045);overflow:hidden}.kbc-card-body{padding:18px}.kbc-card-header{padding:16px 18px;border-bottom:1px solid #eef2f7;font-weight:900}.form-control{border-radius:12px}.btn{border-radius:12px;font-weight:800}.table td,.table th{vertical-align:middle}.small-muted{font-size:12px;color:#64748b}@media(max-width:900px){.kbc-grid{grid-template-columns:1fr}}
</style>

<div class="kbc-page">
    <div class="kbc-hero">
        <h2 class="mb-1"><i class="bi bi-journal-bookmark me-2 text-primary"></i>Knowledge Category Management</h2>
        <div class="text-muted">Maintain Knowledge Base categories used by Add Article and Edit Article.</div>
    </div>

    <?php if(!empty($_GET['msg'])): ?><div class="alert alert-success"><?= kbc_h($_GET['msg']); ?></div><?php endif; ?>
    <?php if(!empty($_GET['err'])): ?><div class="alert alert-danger"><?= kbc_h($_GET['err']); ?></div><?php endif; ?>

    <div class="kbc-grid">
        <div class="kbc-card">
            <div class="kbc-card-header">Add Knowledge Category</div>
            <div class="kbc-card-body">
                <form method="post" action="kb_category_management.php">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category Name</label>
                        <input type="text" name="category_name" class="form-control" required placeholder="Example: POS System">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="10">
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="status" id="kbcStatus" checked>
                        <label class="form-check-label" for="kbcStatus">Enabled</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-circle me-1"></i>Add Category</button>
                </form>
            </div>
        </div>

        <div class="kbc-card">
            <div class="kbc-card-header">Knowledge Category List</div>
            <div class="kbc-card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead><tr><th style="width:70px">Order</th><th>Category</th><th style="width:110px">Articles</th><th style="width:100px">Status</th><th style="width:250px">Action</th></tr></thead>
                        <tbody>
                        <?php foreach($rows as $row): $id=(int)$row['id']; $key=strtoupper(trim((string)$row['category_name'])); $used=$usage[$key] ?? 0; $form='kbc_edit_'.$id; ?>
                            <tr>
                                <td><input form="<?= $form; ?>" type="number" name="sort_order" class="form-control form-control-sm" value="<?= (int)$row['sort_order']; ?>"></td>
                                <td>
                                    <form id="<?= $form; ?>" method="post" action="kb_category_management.php"></form>
                                    <input form="<?= $form; ?>" type="hidden" name="action" value="edit">
                                    <input form="<?= $form; ?>" type="hidden" name="id" value="<?= $id; ?>">
                                    <input form="<?= $form; ?>" type="text" name="category_name" class="form-control form-control-sm" value="<?= kbc_h($row['category_name']); ?>">
                                </td>
                                <td><span class="badge bg-light text-dark"><?= $used; ?></span></td>
                                <td>
                                    <label class="form-check form-switch m-0">
                                        <input form="<?= $form; ?>" class="form-check-input" type="checkbox" name="status" <?= ((int)$row['status']===1?'checked':''); ?>>
                                    </label>
                                </td>
                                <td>
                                    <button form="<?= $form; ?>" type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save"></i> Save</button>
                                    <form method="post" action="kb_category_management.php" class="d-inline">
                                        <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $id; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary"><?= ((int)$row['status']===1?'Disable':'Enable'); ?></button>
                                    </form>
                                    <a class="btn btn-sm btn-outline-danger" href="kb_category_management.php?delete_id=<?= $id; ?>" onclick="return confirm('Delete this category? Used categories will be disabled instead.');"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">No category found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info small-muted mb-0"><strong>Linked pages:</strong> Add Article and Edit Article automatically use enabled categories from this list. Used categories cannot be hard-deleted; they will be disabled instead.</div>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
