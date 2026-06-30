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
        $status = 1;
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
        $status = (int)($_POST['status'] ?? 1);
        if($id <= 0 || $name === '') kbc_redirect('', 'Invalid category.');
        try {
            $stmt = $pdo->prepare("UPDATE kb_category_master SET category_name=?, status=?, sort_order=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$name, ($status === 1 ? 1 : 0), $sort, $id]);
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
.master-page-header{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:18px;}
.master-page-header h2{font-size:30px;font-weight:850;color:#0f172a;}
.master-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 10px 24px rgba(15,23,42,.06);overflow:hidden;margin-bottom:18px;}
.master-card-header{padding:18px 20px;border-bottom:1px solid #eef2f7;display:flex;justify-content:space-between;align-items:center;background:#fff;}
.master-card-body{padding:20px;}
.master-table td,.master-table th{vertical-align:middle;}
.master-table thead th{background:#f8fafc!important;color:#334155!important;border-bottom:1px solid #e5e7eb!important;font-weight:800!important;}
.action-buttons{display:flex;flex-wrap:wrap;gap:6px;}
.search-input{max-width:360px;}
.btn{border-radius:10px;font-weight:800;}
.form-control,.form-select{border-radius:10px;}
.kbc-info{background:#cff4fc;border:1px solid #9eeaf9;color:#055160;border-radius:12px;}
.usage-badge{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:26px;border-radius:999px;font-weight:800;font-size:12px;background:#f1f5f9;color:#0f172a;}
@media(max-width:768px){
  .master-page-header{flex-direction:column;align-items:flex-start;}
  .master-page-header .btn{width:100%;min-height:44px;border-radius:13px;display:flex;justify-content:center;align-items:center;}
  .master-card-header{flex-direction:column;align-items:stretch;gap:12px;}
  .search-input{max-width:100%;width:100%;}
  .table-responsive{overflow:visible!important;}
  table.hd-mobile-card-table{border-collapse:separate!important;border-spacing:0 14px!important;width:100%!important;}
  table.hd-mobile-card-table thead{display:none!important;}
  table.hd-mobile-card-table tbody,table.hd-mobile-card-table tr,table.hd-mobile-card-table td{display:block!important;width:100%!important;}
  table.hd-mobile-card-table tr{background:#fff!important;border:1px solid #e5e7eb!important;border-radius:18px!important;margin:0 0 14px!important;padding:14px!important;box-shadow:0 10px 24px rgba(15,23,42,.06)!important;overflow:hidden!important;}
  table.hd-mobile-card-table td{border:0!important;border-bottom:1px dashed #e5e7eb!important;padding:10px 0!important;min-height:0!important;}
  table.hd-mobile-card-table td:last-child{border-bottom:0!important;padding-bottom:0!important;}
  table.hd-mobile-card-table td::before{display:block!important;font-size:12px!important;font-weight:900!important;color:#64748b!important;text-transform:uppercase!important;letter-spacing:.02em!important;margin-bottom:6px!important;}
  table.hd-mobile-card-table td:nth-child(1)::before{content:"序号";}
  table.hd-mobile-card-table td:nth-child(2)::before{content:"排序";}
  table.hd-mobile-card-table td:nth-child(3)::before{content:"分类名称";}
  table.hd-mobile-card-table td:nth-child(4)::before{content:"文章";}
  table.hd-mobile-card-table td:nth-child(5)::before{content:"状态";}
  table.hd-mobile-card-table td:nth-child(6)::before{content:"操作";}
  table.hd-mobile-card-table td:first-child{font-size:18px!important;font-weight:900!important;color:#0f172a!important;}
  table.hd-mobile-card-table input.form-control,table.hd-mobile-card-table select.form-select{width:100%!important;min-height:44px!important;font-size:16px!important;border-radius:12px!important;}
  table.hd-mobile-card-table .btn{min-height:42px!important;border-radius:12px!important;font-weight:900!important;padding:.55rem .8rem!important;}
  table.hd-mobile-card-table .action-buttons{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:8px!important;align-items:stretch!important;}
  table.hd-mobile-card-table .action-buttons .btn{width:100%!important;margin:0!important;white-space:normal!important;}
}
</style>

<div class="master-page-header">
    <div>
        <h2 class="mb-1">Knowledge Category Management</h2>
        <div class="text-muted">Add, edit, disable or delete Knowledge Base category options.</div>
    </div>
    <a href="administration.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<?php if(!empty($_GET['msg'])): ?><div class="alert alert-success"><?= kbc_h($_GET['msg']); ?></div><?php endif; ?>
<?php if(!empty($_GET['err'])): ?><div class="alert alert-danger"><?= kbc_h($_GET['err']); ?></div><?php endif; ?>

<div class="master-card">
    <div class="master-card-header">
        <div>
            <strong>Add Knowledge Category</strong>
            <div class="text-muted small">Add, edit, disable or delete Knowledge Base category options.</div>
        </div>
    </div>
    <div class="master-card-body">
        <form method="post" action="kb_category_management.php" class="row g-3">
            <input type="hidden" name="action" value="add">
            <div class="col-md">
                <label class="form-label fw-semibold">Category Name</label>
                <input type="text" name="category_name" class="form-control" required placeholder="Example: POS System">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="10">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100">
                    <i class="bi bi-plus-circle"></i> Add
                </button>
            </div>
        </form>
    </div>
</div>

<div class="master-card">
    <div class="master-card-header">
        <div>
            <strong>Knowledge Category List</strong>
            <div class="text-muted small">Inactive categories will not appear in article dropdowns. Used categories are disabled instead of deleted.</div>
        </div>
        <div class="input-group search-input">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="masterSearch" class="form-control" placeholder="Search...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover master-table mb-0 hd-mobile-card-table" id="masterTable">
            <thead>
                <tr>
                    <th style="width:70px;">No.</th>
                    <th style="width:110px;">Sort</th>
                    <th>Category Name</th>
                    <th style="width:110px;">Articles</th>
                    <th style="width:140px;">Status</th>
                    <th style="width:260px;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php $no = 1; foreach($rows as $row): ?>
                <?php $id=(int)$row['id']; $key=strtoupper(trim((string)$row['category_name'])); $used=$usage[$key] ?? 0; $form='kbc_edit_'.$id; ?>
                <tr>
                    <td class="fw-bold text-center"><?= $no++; ?></td>
                    <td>
                        <input form="<?= $form; ?>" type="number" name="sort_order" class="form-control form-control-sm" value="<?= (int)$row['sort_order']; ?>">
                    </td>
                    <td>
                        <form id="<?= $form; ?>" method="post" action="kb_category_management.php"></form>
                        <input form="<?= $form; ?>" type="hidden" name="action" value="edit">
                        <input form="<?= $form; ?>" type="hidden" name="id" value="<?= $id; ?>">
                        <input form="<?= $form; ?>" type="text" name="category_name" class="form-control form-control-sm" value="<?= kbc_h($row['category_name']); ?>" required>
                    </td>
                    <td><span class="usage-badge"><?= $used; ?></span></td>
                    <td>
                        <select form="<?= $form; ?>" name="status" class="form-select form-select-sm">
                            <option value="1" <?= ((int)$row['status'] === 1 ? 'selected' : ''); ?>>Active</option>
                            <option value="0" <?= ((int)$row['status'] === 0 ? 'selected' : ''); ?>>Inactive</option>
                        </select>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button form="<?= $form; ?>" type="submit" class="btn btn-sm btn-primary">Save</button>
                            <form method="post" action="kb_category_management.php" class="d-inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $id; ?>">
                                <button type="submit" class="btn btn-sm btn-warning"><?= ((int)$row['status'] === 1 ? 'Toggle' : 'Toggle'); ?></button>
                            </form>
                            <a class="btn btn-sm btn-danger" href="kb_category_management.php?delete_id=<?= $id; ?>" onclick="return confirm('Delete this category? Used categories will be disabled instead.');">Delete</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$rows): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No category found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="master-card-body pt-0">
        <div class="alert kbc-info mb-0">
            <strong>Linked pages:</strong> Add Article and Edit Article automatically use active Knowledge Categories from this list.
            Categories already used by articles cannot be deleted; use Disable instead.
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const search = document.getElementById('masterSearch');
    const table = document.getElementById('masterTable');
    if(search && table){
        search.addEventListener('keyup', function(){
            const q = this.value.toLowerCase();
            table.querySelectorAll('tbody tr').forEach(function(row){
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }
});
</script>

<?php require 'footer.php'; ?>
