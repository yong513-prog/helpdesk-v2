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


/* Mobile Card UI - only affects phone screens. Desktop table layout remains unchanged. */
@media(max-width:768px){
    .master-page-header,.assign-page-header{
        gap:10px!important;
        margin-bottom:14px!important;
    }
    .master-page-header h2,.assign-page-header h2,.kbc-hero h2{
        font-size:24px!important;
        line-height:1.2!important;
    }
    .master-page-header .btn,.assign-page-header .btn{
        width:100%!important;
        min-height:46px!important;
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        border-radius:14px!important;
    }
    .master-card,.assign-card,.kbc-card{
        border-radius:18px!important;
        margin-bottom:14px!important;
        overflow:hidden!important;
    }
    .master-card-header,.assign-card-header,.kbc-card-header{
        display:block!important;
        padding:15px 16px!important;
    }
    .master-card-body,.assign-card-body,.kbc-card-body{
        padding:15px!important;
    }
    .search-input{
        max-width:none!important;
        width:100%!important;
        margin-top:12px!important;
    }
    .table-responsive{
        overflow:visible!important;
    }
    .master-table,.assign-table,.kbc-card .table{
        border-collapse:separate!important;
        border-spacing:0 12px!important;
        margin-bottom:0!important;
    }
    .master-table thead,.assign-table thead,.kbc-card .table thead{
        display:none!important;
    }
    .master-table tbody,.assign-table tbody,.kbc-card .table tbody,
    .master-table tr,.assign-table tr,.kbc-card .table tr,
    .master-table td,.assign-table td,.kbc-card .table td{
        display:block!important;
        width:100%!important;
    }
    .master-table tbody tr,.assign-table tbody tr,.kbc-card .table tbody tr{
        background:#fff!important;
        border:1px solid #e5e7eb!important;
        border-radius:18px!important;
        box-shadow:0 8px 22px rgba(15,23,42,.06)!important;
        padding:14px!important;
        margin-bottom:12px!important;
    }
    .master-table td,.assign-table td,.kbc-card .table td{
        border:0!important;
        padding:8px 0!important;
        min-height:auto!important;
    }
    .master-table td::before,.assign-table td::before,.kbc-card .table td::before{
        display:block!important;
        font-size:11px!important;
        font-weight:900!important;
        color:#64748b!important;
        text-transform:uppercase!important;
        letter-spacing:.04em!important;
        margin-bottom:5px!important;
    }
    .master-table td input,.master-table td select,
    .assign-table td input,.assign-table td select,
    .kbc-card .table td input,.kbc-card .table td select{
        width:100%!important;
        min-height:46px!important;
        font-size:16px!important;
        border-radius:13px!important;
    }
    .action-buttons,.status-action-group{
        display:grid!important;
        grid-template-columns:repeat(3,minmax(0,1fr))!important;
        gap:8px!important;
        width:100%!important;
    }
    .action-buttons form,.status-action-group form,
    .action-buttons .d-inline,.status-action-group .d-inline{
        display:block!important;
        width:100%!important;
        margin:0!important;
    }
    .action-buttons .btn,.status-action-group .btn,
    .kbc-card .table td .btn{
        width:100%!important;
        min-height:42px!important;
        border-radius:12px!important;
        font-size:14px!important;
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        gap:5px!important;
        margin:0!important;
    }
    .badge,.usage-badge{
        min-height:28px!important;
        display:inline-flex!important;
        align-items:center!important;
        justify-content:center!important;
        border-radius:999px!important;
        padding:6px 10px!important;
        font-weight:900!important;
    }
    .form-check.form-switch{
        min-height:38px!important;
        display:flex!important;
        align-items:center!important;
        gap:10px!important;
    }
    .form-check-input{
        width:3em!important;
        height:1.55em!important;
    }
}

@media(max-width:768px){
    .kbc-page{margin:0 auto 76px!important;}
    .kbc-hero{padding:16px!important;border-radius:18px!important;margin-bottom:14px!important;}
    .kbc-grid{display:block!important;}
    .kbc-grid>.kbc-card{margin-bottom:14px!important;}
    .kbc-card .table td:nth-child(1)::before{content:'Order';}
    .kbc-card .table td:nth-child(2)::before{content:'Category';}
    .kbc-card .table td:nth-child(3)::before{content:'Articles';}
    .kbc-card .table td:nth-child(4)::before{content:'Status';}
    .kbc-card .table td:nth-child(5)::before{content:'Action';}
    .kbc-card .table td:nth-child(5){display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:8px!important;}
    .kbc-card .table td:nth-child(5)::before{grid-column:1 / -1;}
    .kbc-card .table td:nth-child(5) form{display:block!important;width:100%!important;margin:0!important;}
    .kbc-card .table td:nth-child(5) a{width:100%!important;}
}
</style>


<style>
/* AH style / color alignment */
.master-page-header,.ah-page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    margin-bottom:18px;
}
.master-page-header h2,.ah-page-header h2{
    font-size:30px;
    font-weight:850;
    color:#0f172a;
}
.master-card,.ah-card{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:18px;
    box-shadow:0 10px 24px rgba(15,23,42,.06);
    overflow:hidden;
    margin-bottom:18px;
}
.master-card-header,.ah-card-header{
    padding:18px 20px;
    border-bottom:1px solid #eef2f7;
    display:flex;
    justify-content:space-between;
    align-items:center;
    background:#fff;
}
.master-card-body,.ah-card-body{
    padding:20px;
}
.master-table td,.master-table th,
.ah-table td,.ah-table th{
    vertical-align:middle;
}
.ah-actions,.action-buttons,.status-action-group{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
}
.ah-search,.search-input{max-width:360px;}
.ah-btn-add{background:#198754;border-color:#198754;color:#fff;font-weight:800;}
.ah-btn-save{background:#0d6efd;border-color:#0d6efd;color:#fff;font-weight:800;}
.ah-btn-toggle{background:#ffc107;border-color:#ffc107;color:#111827;font-weight:800;}
.ah-btn-delete{background:#dc3545;border-color:#dc3545;color:#fff;font-weight:800;}
.ah-info{
    background:#cff4fc;
    border:1px solid #9eeaf9;
    color:#055160;
    border-radius:12px;
}
@media(max-width:768px){
    .master-page-header,.ah-page-header{
        flex-direction:column;
        align-items:flex-start;
    }
    .master-page-header .btn,.ah-page-header .btn{
        width:100%;
        min-height:44px;
        border-radius:13px;
        display:flex;
        justify-content:center;
        align-items:center;
    }
}
</style>

<style>
/* Mobile-only card format; desktop table remains unchanged */
@media (max-width: 768px){
  .table-responsive{overflow:visible!important;}
  table.hd-mobile-card-table{border-collapse:separate!important;border-spacing:0 14px!important;width:100%!important;}
  table.hd-mobile-card-table thead{display:none!important;}
  table.hd-mobile-card-table tbody, table.hd-mobile-card-table tr, table.hd-mobile-card-table td{display:block!important;width:100%!important;}
  table.hd-mobile-card-table tr{background:#fff!important;border:1px solid #e5e7eb!important;border-radius:18px!important;margin:0 0 14px!important;padding:14px!important;box-shadow:0 10px 24px rgba(15,23,42,.06)!important;overflow:hidden!important;}
  table.hd-mobile-card-table td{border:0!important;border-bottom:1px dashed #e5e7eb!important;padding:10px 0!important;min-height:0!important;}
  table.hd-mobile-card-table td:last-child{border-bottom:0!important;padding-bottom:0!important;}
  table.hd-mobile-card-table td::before{display:block!important;font-size:12px!important;font-weight:900!important;color:#64748b!important;text-transform:uppercase!important;letter-spacing:.02em!important;margin-bottom:6px!important;}
  table.hd-mobile-card-table td:first-child{font-size:18px!important;font-weight:900!important;color:#0f172a!important;}
  table.hd-mobile-card-table input.form-control,
  table.hd-mobile-card-table select.form-select{width:100%!important;min-height:44px!important;font-size:16px!important;border-radius:12px!important;}
  table.hd-mobile-card-table .btn{min-height:42px!important;border-radius:12px!important;font-weight:900!important;padding:.55rem .8rem!important;}
  table.hd-mobile-card-table td:last-child,
  table.hd-mobile-card-table .action-buttons,
  table.hd-mobile-card-table .status-action-group,
  table.hd-mobile-card-table .ah-actions{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:8px!important;align-items:stretch!important;}
  table.hd-mobile-card-table td:last-child .btn,
  table.hd-mobile-card-table .action-buttons .btn,
  table.hd-mobile-card-table .status-action-group .btn,
  table.hd-mobile-card-table .ah-actions .btn{width:100%!important;margin:0!important;white-space:normal!important;}
  table.hd-mobile-card-table .badge,
  table.hd-mobile-card-table .usage-badge{font-size:13px!important;min-height:28px!important;padding:.35rem .65rem!important;border-radius:999px!important;}
  .master-card-header,.ah-card-header{flex-direction:column!important;align-items:stretch!important;gap:12px!important;}
  .search-input,.ah-search{max-width:100%!important;width:100%!important;}
}
</style>

<style>
/* AH style alignment for Knowledge Category page */
.kbc-page{max-width:none!important;margin:0 0 28px!important;}
.kbc-hero{
    background:transparent!important;
    border:0!important;
    box-shadow:none!important;
    padding:0!important;
    margin-bottom:18px!important;
    border-radius:0!important;
}
.kbc-hero h2{font-size:30px!important;font-weight:850!important;color:#0f172a!important;}
.kbc-grid{display:block!important;}
.kbc-card{
    background:#fff!important;
    border:1px solid #e5e7eb!important;
    border-radius:18px!important;
    box-shadow:0 10px 24px rgba(15,23,42,.06)!important;
    overflow:hidden!important;
    margin-bottom:18px!important;
}
.kbc-card-header{
    padding:18px 20px!important;
    border-bottom:1px solid #eef2f7!important;
    background:#fff!important;
    font-weight:900!important;
}
.kbc-card-body{padding:20px!important;}
.kbc-card .btn-primary{background:#0d6efd!important;border-color:#0d6efd!important;color:#fff!important;font-weight:800!important;}
.kbc-card .btn-outline-secondary{background:#ffc107!important;border-color:#ffc107!important;color:#111827!important;font-weight:800!important;}
.kbc-card .btn-outline-danger{background:#dc3545!important;border-color:#dc3545!important;color:#fff!important;font-weight:800!important;}
.kbc-card .alert-info{
    background:#cff4fc!important;
    border:1px solid #9eeaf9!important;
    color:#055160!important;
    border-radius:12px!important;
}
.kbc-card table thead{background:#212529!important;color:#fff!important;}
.kbc-card table thead th{background:#212529!important;color:#fff!important;}
@media(max-width:768px){
    .kbc-hero h2{font-size:24px!important;}
    .kbc-card{border-radius:18px!important;margin-bottom:14px!important;}
}
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
                    <table class="table table-hover align-middle hd-mobile-card-table">
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


<style>
@media(max-width:768px){
  .kbc-card table.hd-mobile-card-table td:nth-child(1)::before{content:"排序";}
  .kbc-card table.hd-mobile-card-table td:nth-child(2)::before{content:"分类";}
  .kbc-card table.hd-mobile-card-table td:nth-child(3)::before{content:"文章";}
  .kbc-card table.hd-mobile-card-table td:nth-child(4)::before{content:"状态";}
  .kbc-card table.hd-mobile-card-table td:nth-child(5)::before{content:"操作";}
}
</style>


<style>
.kbc-page{max-width:none!important;margin:0!important}
.kbc-grid{display:block!important}
.kbc-hero{background:transparent!important;border:0!important;box-shadow:none!important;padding:0!important;margin-bottom:18px!important}
.kbc-card{border:1px solid #e5e7eb!important;border-radius:18px!important;box-shadow:0 10px 24px rgba(15,23,42,.06)!important;margin-bottom:18px!important}
.kbc-card-header{background:#fff!important;border-bottom:1px solid #eef2f7!important;padding:18px 20px!important;font-weight:800!important}
.kbc-card-body{padding:20px!important}
.kbc-card .table thead th{background:#212529!important;color:#fff!important}
</style>

<?php require 'footer.php'; ?>
