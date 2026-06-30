<?php
require 'header.php';
require 'db.php';
require_once 'module_permissions.php';
require_once 'kb_org_lib.php';
require_once 'kb_content_translate.php';

if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

kb_org_ensure_schema($pdo);
hd_ensure_kb_translation_columns($pdo);

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$type = trim($_GET['type'] ?? '');
$branch = trim($_GET['branch'] ?? '');
$status = trim($_GET['status'] ?? '');

$categories = kb_org_fetch_categories($pdo);
$types = kb_org_types();
$branches = kb_org_fetch_branches($pdo);

$where = [];
$params = [];

if($search !== '') {
    $where[] = "(title LIKE ? OR title_en LIKE ? OR title_ms LIKE ? OR title_zh LIKE ? OR category LIKE ? OR content LIKE ? OR content_en LIKE ? OR content_ms LIKE ? OR content_zh LIKE ? OR tags LIKE ? OR knowledge_type LIKE ?)";
    $sv = '%'.$search.'%';
    array_push($params, $sv, $sv, $sv, $sv, $sv, $sv, $sv, $sv, $sv, $sv, $sv);
}
if($category !== '') { $where[] = "category = ?"; $params[] = $category; }
if($type !== '') { $where[] = "knowledge_type = ?"; $params[] = $type; }
if($status !== '') { $where[] = "status = ?"; $params[] = $status; }
if($branch !== '') {
    $where[] = "(branch_scope IS NULL OR branch_scope='' OR UPPER(branch_scope)='ALL' OR FIND_IN_SET(?, REPLACE(branch_scope,' ','')) > 0)";
    $params[] = str_replace(' ', '', $branch);
}

$sql = "SELECT * FROM knowledge_base";
if($where) { $sql .= " WHERE ".implode(' AND ', $where); }
$sql .= " ORDER BY category ASC, knowledge_type ASC, title ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rawArticles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$articles = [];
foreach($rawArticles as $a) {
    if(kb_org_can_view_article($a)) $articles[] = $a;
}

$totalArticles = (int)$pdo->query("SELECT COUNT(*) FROM knowledge_base")->fetchColumn();
$totalCategories = count($categories);
$totalViews = (int)$pdo->query("SELECT COALESCE(SUM(views),0) FROM knowledge_base")->fetchColumn();
$lastUpdated = $pdo->query("SELECT COALESCE(MAX(updated_at), MAX(created_at)) FROM knowledge_base")->fetchColumn() ?: '-';
$canManageKb = has_action_permission('manage_kb');

$topViewed = $pdo->query("SELECT * FROM knowledge_base WHERE (status IS NULL OR status='Published') ORDER BY COALESCE(views,0) DESC, title ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

$grouped = [];
foreach($articles as $article) {
    $group = $article['category'] ?: 'Uncategorized';
    if(!isset($grouped[$group])) $grouped[$group] = [];
    $grouped[$group][] = $article;
}
?>

<style>
.kb-page .page-title{font-weight:800;color:#182033}.kb-hero{display:flex;align-items:center;gap:16px;margin-bottom:20px}.kb-icon{width:54px;height:54px;border-radius:16px;background:linear-gradient(135deg,#eef2ff,#e0e7ff);display:flex;align-items:center;justify-content:center;color:#4f46e5;font-size:28px}.kb-card{background:#fff;border:1px solid #e8edf5;border-radius:16px;box-shadow:0 8px 22px rgba(15,23,42,.06)}.kb-stat{padding:22px;display:flex;align-items:center;gap:16px;height:100%}.kb-stat .circle{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px}.kb-stat h3{font-size:24px;font-weight:800;margin:0}.kb-stat p{margin:0;color:#64748b;font-size:13px}.kb-toolbar{padding:18px}.kb-table th{font-size:13px;color:#334155;background:#fbfdff;padding:14px}.kb-table td{vertical-align:middle;padding:14px}.kb-title-link{font-weight:750;text-decoration:none;color:#2563eb}.kb-excerpt{color:#64748b;font-size:13px;margin-top:6px;max-width:520px}.kb-badge{border-radius:8px;padding:6px 10px;font-weight:650;font-size:12px}.kb-footer{padding:16px;color:#64748b;font-size:13px}.action-btn{border-radius:8px;font-weight:650}.tips{background:#eff6ff;border:1px solid #bfdbfe;border-left:4px solid #3b82f6;border-radius:14px;padding:16px;color:#1e3a8a}.empty-state{padding:50px;text-align:center;color:#64748b}.input-group-text{background:#fff}.form-control,.form-select{border-radius:10px}.btn{border-radius:10px}.category-head{padding:14px 18px;background:#f8fafc;border-top:1px solid #e8edf5;border-bottom:1px solid #e8edf5;font-weight:900;color:#0f172a;display:flex;justify-content:space-between}.tag-pill{display:inline-block;background:#f1f5f9;border:1px solid #e2e8f0;color:#475569;border-radius:999px;padding:3px 8px;font-size:11px;margin:2px}.top-list a{text-decoration:none;font-weight:700}.top-list li{margin-bottom:8px}.layout-grid{display:grid;grid-template-columns:1fr 320px;gap:18px}@media(max-width:992px){.layout-grid{grid-template-columns:1fr}}

@media (max-width:768px){
.kb-table thead{display:none!important;}
.kb-table,.kb-table tbody,.kb-table tr,.kb-table td{display:block;width:100%!important;}
.kb-table tr{background:#fff;border:1px solid #e5eaf2;border-radius:14px;margin:12px;padding:12px;}
.kb-table td{text-align:left!important;border:none!important;padding:6px 0!important;}
.kb-excerpt{max-width:100%!important;}
.action-btn{margin-top:6px;}
.kb-toolbar .row>div{width:100%!important;}
}
</style>

<div class="kb-page">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div class="kb-hero">
            <div class="kb-icon"><i class="bi bi-journal-bookmark-fill"></i></div>
            <div>
                <h2 class="page-title mb-1">Knowledge Base</h2>
                <div class="text-muted">Organized by Category, Type, Tags and Branch Scope</div>
            </div>
        </div>
        <?php if($canManageKb): ?>
        <a href="add_article.php" class="btn btn-primary px-4"><i class="bi bi-plus-lg me-1"></i> <?= __('Add Article') ?></a>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="kb-card kb-stat"><div class="circle text-primary" style="background:#eef2ff"><i class="bi bi-file-earmark-text-fill"></i></div><div><h3><?= $totalArticles ?></h3><strong>Total Articles</strong><p>All articles</p></div></div></div>
        <div class="col-md-3"><div class="kb-card kb-stat"><div class="circle text-success" style="background:#dcfce7"><i class="bi bi-folder-fill"></i></div><div><h3><?= $totalCategories ?></h3><strong>Categories</strong><p>From Category Management</p></div></div></div>
        <div class="col-md-3"><div class="kb-card kb-stat"><div class="circle text-warning" style="background:#ffedd5"><i class="bi bi-eye-fill"></i></div><div><h3><?= $totalViews ?></h3><strong>Total Views</strong><p>Article views</p></div></div></div>
        <div class="col-md-3"><div class="kb-card kb-stat"><div class="circle text-info" style="background:#dbeafe"><i class="bi bi-calendar-check-fill"></i></div><div><h3 style="font-size:18px"><?= htmlspecialchars($lastUpdated === '-' ? '-' : date('d/m/Y', strtotime($lastUpdated))) ?></h3><strong>Last Updated</strong><p>Most recent update</p></div></div></div>
    </div>

    <div class="layout-grid">
        <div>
            <div class="kb-card mb-4">
                <form method="get" class="kb-toolbar">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-4"><div class="input-group"><input type="text" name="search" class="form-control" placeholder="Search title, content, tags..." value="<?= kb_org_h($search) ?>"><span class="input-group-text"><i class="bi bi-search"></i></span></div></div>
                        <div class="col-lg-2"><select name="category" class="form-select"><option value="">All Categories</option><?php foreach($categories as $cat): ?><option value="<?= kb_org_h($cat) ?>" <?= $category===$cat?'selected':'' ?>><?= kb_org_h($cat) ?></option><?php endforeach; ?></select></div>
                        <div class="col-lg-2"><select name="type" class="form-select"><option value="">All Types</option><?php foreach($types as $t): ?><option value="<?= kb_org_h($t) ?>" <?= $type===$t?'selected':'' ?>><?= kb_org_h($t) ?></option><?php endforeach; ?></select></div>
                        <div class="col-lg-2"><select name="branch" class="form-select"><option value="">All Branch Scope</option><?php foreach($branches as $b): ?><option class="hd-no-translate" value="<?= kb_org_h($b['branch_code']) ?>" <?= $branch===$b['branch_code']?'selected':'' ?>><?= kb_org_h($b['branch_code']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-lg-2"><select name="status" class="form-select"><option value="">All Status</option><option value="Published" <?= $status==='Published'?'selected':'' ?>>Published</option><option value="Draft" <?= $status==='Draft'?'selected':'' ?>>Draft</option></select></div>
                        <div class="col-12 d-flex gap-2"><button class="btn btn-primary px-4"><i class="bi bi-search me-1"></i> Search</button><a href="knowledge_base.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i> Reset</a></div>
                    </div>
                </form>

                <?php if(!$grouped): ?>
                    <div class="empty-state"><i class="bi bi-inbox fs-1 d-block mb-2"></i><?= __('No articles found') ?></div>
                <?php endif; ?>

                <?php foreach($grouped as $groupName => $items): ?>
                    <div class="category-head">
                        <span><i class="bi bi-folder2-open me-1"></i><?= kb_org_h($groupName) ?></span>
                        <span class="text-muted small"><?= count($items) ?> article(s)</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table kb-table mb-0">
                            <thead><tr><th>Title</th><th>Type</th><th>Scope</th><th>Updated</th><th>Views</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                            <?php foreach($items as $article): ?>
                                <?php
                                $displayTitle = hd_kb_title($pdo, $article);
                                $displayContent = hd_kb_content($pdo, $article);
                                $content = trim(strip_tags($displayContent ?? ''));
                                $excerpt = mb_strlen($content) > 110 ? mb_substr($content,0,110).'...' : $content;
                                $rowStatus = $article['status'] ?: 'Published';
                                $updated = !empty($article['updated_at']) ? $article['updated_at'] : ($article['created_at'] ?? '');
                                $tagList = kb_org_csv_array($article['tags'] ?? '');
                                ?>
                                <tr>
                                    <td>
                                        <a class="kb-title-link" href="view_article.php?id=<?= (int)$article['id'] ?>"><?= kb_org_h($displayTitle) ?></a>
                                        <div class="kb-excerpt"><?= kb_org_h($excerpt) ?></div>
                                        <div><?php foreach($tagList as $tag): ?><span class="tag-pill">#<?= kb_org_h($tag) ?></span><?php endforeach; ?></div>
                                    </td>
                                    <td><span class="kb-badge bg-light text-primary border"><?= kb_org_h($article['knowledge_type'] ?: 'Guide') ?></span></td>
                                    <td><span class="kb-badge bg-light text-secondary border"><?= kb_org_h(kb_org_scope_label($article['branch_scope'] ?? 'ALL')) ?></span></td>
                                    <td><?= !empty($updated) ? date('d/m/Y', strtotime($updated)) : '-' ?><br><span class="kb-badge <?= $rowStatus==='Draft'?'bg-warning-subtle text-warning':'bg-success-subtle text-success' ?>"><?= kb_org_h($rowStatus) ?></span></td>
                                    <td><?= (int)($article['views'] ?? 0) ?></td>
                                    <td class="text-end">
                                        <a href="view_article.php?id=<?= (int)$article['id'] ?>" class="btn btn-outline-primary btn-sm action-btn"><i class="bi bi-eye"></i> View</a>
                                        <?php if($canManageKb): ?>
                                        <a href="edit_article.php?id=<?= (int)$article['id'] ?>" class="btn btn-outline-warning btn-sm action-btn"><i class="bi bi-pencil-square"></i> Edit</a>
                                        <a href="delete_article.php?id=<?= (int)$article['id'] ?>" class="btn btn-outline-danger btn-sm action-btn" onclick="return confirm('Delete this article?')"><i class="bi bi-trash"></i> Delete</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>

                <div class="kb-footer d-flex justify-content-between flex-wrap gap-2"><span>Showing <?= count($articles) ?> of <?= $totalArticles ?> articles</span><span>Branch scoped articles are filtered by user branch.</span></div>
            </div>
        </div>

        <div>
            <div class="kb-card mb-4 p-3">
                <h5 class="fw-bold mb-3"><i class="bi bi-fire text-warning me-1"></i>Top Viewed</h5>
                <ol class="top-list mb-0">
                    <?php foreach($topViewed as $tv): ?>
                    <li><a href="view_article.php?id=<?= (int)$tv['id'] ?>"><?= kb_org_h(hd_kb_title($pdo, $tv)) ?></a><br><small class="text-muted"><?= kb_org_h($tv['category']) ?> · <?= (int)$tv['views'] ?> views</small></li>
                    <?php endforeach; ?>
                    <?php if(!$topViewed): ?><li class="text-muted"><?= __('No views yet.') ?></li><?php endif; ?>
                </ol>
            </div>

            <div class="tips mb-4">
                <strong><i class="bi bi-info-circle-fill me-1"></i> New Structure</strong><br>
                Articles now support <b>Type</b>, <b>Tags</b> and <b>Branch Scope</b>. Create Ticket will suggest related articles by selected Category.
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
