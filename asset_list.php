<?php
require 'header.php';
require 'db.php';
require_once 'access_control.php';
require_once 'module_permissions.php';
if(!isset($_SESSION['user_id'])){ header("Location: login.php"); exit; }
function ensure_asset_photo_column($pdo){ try{ $pdo->query("SELECT asset_photo FROM assets LIMIT 1"); } catch(Exception $e){ try{ $pdo->exec("ALTER TABLE assets ADD COLUMN asset_photo VARCHAR(255) NULL AFTER remark"); } catch(Exception $ignore){} } }
function h($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

if(!function_exists('asset_lang_code')){
    function asset_lang_code(){
        $lang = $_SESSION['helpdesk_lang'] ?? $_COOKIE['helpdesk_lang'] ?? $_SESSION['lang'] ?? $_SESSION['language'] ?? $_GET['lang'] ?? $_COOKIE['lang'] ?? 'en';
        $lang = strtolower((string)$lang);
        if($lang === 'bm') $lang = 'ms';
        if(!in_array($lang, ['en','ms','zh'], true)) $lang = 'en';
        return $lang;
    }
}
if(!function_exists('asset_t')){
    function asset_t($key){
        static $dict = [
            'en' => [
                'no_assets_found' => 'No assets found.'
            ],
            'ms' => [
                'no_assets_found' => 'Tiada aset ditemui.'
            ],
            'zh' => [
                'no_assets_found' => '没有找到资产。'
            ]
        ];
        $lang = asset_lang_code();
        return $dict[$lang][$key] ?? $dict['en'][$key] ?? $key;
    }
}

ensure_asset_photo_column($pdo);
$role=$_SESSION['role'] ?? 'staff';
$canManageAsset = has_action_permission('manage_asset'); $keyword=trim($_GET['keyword'] ?? ''); $status=trim($_GET['status'] ?? '');
$sql="SELECT a.*, (SELECT COUNT(*) FROM tickets t WHERE t.asset_id = a.id) AS linked_ticket_count FROM assets a WHERE 1=1"; $params=[];
// Asset List is intentionally global after latest requirement:
// If the role has Asset List permission, it can view all assets from all branches.
// Do not apply branch restriction for Admin / Head / Staff.
if($keyword!=''){ $sql.=" AND (a.asset_code LIKE ? OR a.asset_name LIKE ? OR a.asset_type LIKE ? OR a.brand LIKE ? OR a.model LIKE ? OR a.serial_no LIKE ? OR a.branch LIKE ? OR a.location LIKE ?) "; for($i=0;$i<8;$i++) $params[]="%".$keyword."%"; }
if($status!=''){ $sql.=" AND a.status = ? "; $params[]=$status; }
$sql.=" ORDER BY a.asset_code ASC, a.asset_name ASC, a.branch ASC "; $stmt=$pdo->prepare($sql); $stmt->execute($params); $assets=$stmt->fetchAll(PDO::FETCH_ASSOC);
$statusClass=['Active'=>'bg-success','Repair'=>'bg-warning text-dark','Inactive'=>'bg-secondary','Disposed'=>'bg-danger'];
$assetMessage = $_SESSION['asset_message'] ?? '';
$assetError = $_SESSION['asset_error'] ?? '';
unset($_SESSION['asset_message'], $_SESSION['asset_error']);
?>

<style>
.asset-page-head{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:16px}
.asset-page-title h2{font-weight:950;color:#0f172a}
.asset-page-title .text-muted{font-size:15px;line-height:1.45}
.asset-filter-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:16px;margin-bottom:16px;box-shadow:0 10px 24px rgba(15,23,42,.05)}
.asset-table-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 10px 24px rgba(15,23,42,.05);overflow:hidden}
.asset-photo-thumb{width:64px;height:64px;object-fit:cover;border-radius:12px;border:1px solid #dee2e6}
.asset-no-photo{width:64px;height:64px;border-radius:12px;border:1px solid #dee2e6;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:12px;background:#f8fafc}

.asset-photo-btn{border:0;background:transparent;padding:0;line-height:0;cursor:zoom-in;border-radius:12px}
.asset-photo-btn:focus{outline:3px solid rgba(37,99,235,.25);outline-offset:3px}
.asset-photo-mobile-btn{display:block;width:86px;height:86px}
.asset-photo-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.88);z-index:3000;display:none;align-items:center;justify-content:center;padding:18px}
.asset-photo-modal-backdrop.show{display:flex}
.asset-photo-modal-box{width:min(96vw,1080px);max-height:92vh;background:#fff;border-radius:18px;box-shadow:0 24px 80px rgba(0,0,0,.35);overflow:hidden;display:flex;flex-direction:column}
.asset-photo-modal-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border-bottom:1px solid #e5e7eb}
.asset-photo-modal-title{font-weight:900;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.asset-photo-modal-close{border:0;background:#ef4444;color:#fff;border-radius:12px;width:42px;height:42px;font-size:22px;font-weight:900;line-height:1}
.asset-photo-modal-body{background:#f8fafc;padding:14px;display:flex;align-items:center;justify-content:center;overflow:auto}
.asset-photo-modal-body img{max-width:100%;max-height:78vh;object-fit:contain;border-radius:12px;background:#fff}
@media(max-width:768px){.asset-photo-modal-backdrop{padding:0}.asset-photo-modal-box{width:100vw;height:100vh;max-height:100vh;border-radius:0}.asset-photo-modal-body{flex:1}.asset-photo-modal-body img{max-height:calc(100vh - 82px)}.asset-photo-modal-close{width:48px;height:48px}}
.asset-mobile-cards{display:none}
@media(max-width:768px){
    .asset-page-head{display:grid;grid-template-columns:1fr auto;align-items:start;margin-bottom:14px}
    .asset-page-title h2{font-size:30px;line-height:1.2;margin:0 0 8px}
    .asset-page-title .text-muted{font-size:16px}
    .asset-page-head .btn{width:92px;min-height:72px;border-radius:18px;font-size:17px;font-weight:900;white-space:normal;line-height:1.2}
    .asset-filter-card{padding:14px;border-radius:18px}
    .asset-filter-card .row>[class*="col-"]{margin-bottom:10px}
    .asset-filter-card .form-control,.asset-filter-card .form-select{min-height:50px;font-size:16px;border-radius:13px}
    .asset-filter-card .btn{min-height:48px;border-radius:13px;font-size:16px;font-weight:900}
    .asset-filter-actions{display:grid!important;grid-template-columns:1fr 1fr;gap:10px}
    .asset-filter-actions .btn{width:100%}
    .asset-table-card{display:none!important}
    .asset-mobile-cards{display:block;padding-bottom:88px}
    .asset-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:15px;margin-bottom:12px;box-shadow:0 10px 24px rgba(15,23,42,.06)}
    .asset-card-top{display:flex;gap:14px;align-items:flex-start}
    .asset-card-photo{width:86px;height:86px;flex:0 0 86px}
    .asset-card-photo img{width:86px;height:86px;object-fit:cover;border-radius:16px;border:1px solid #e5e7eb}
    .asset-card-photo .asset-no-photo{width:86px;height:86px;border-radius:16px;font-size:13px;text-align:center}
    .asset-card-main{min-width:0;flex:1}
    .asset-code{font-weight:950;color:#0f172a;font-size:15px}
    .asset-name{font-size:20px;font-weight:900;color:#1e293b;line-height:1.25;margin:4px 0}
    .asset-badges{display:flex;gap:6px;flex-wrap:wrap;margin:9px 0}
    .asset-meta{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:12px;color:#64748b;font-size:13px}
    .asset-meta strong{display:block;color:#334155;font-size:12px;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px}
    .asset-actions{display:flex;gap:8px;margin-top:14px}
    .asset-actions .btn{flex:1;min-height:42px;border-radius:12px;font-weight:900}
    .asset-empty{background:#fff;border:1px dashed #cbd5e1;border-radius:18px;padding:28px;text-align:center;color:#64748b}
}

/* Asset Mobile Cards Display Fix */
@media(max-width:768px){
    .asset-table-card{
        display:none!important;
    }
    .asset-mobile-cards{
        display:block!important;
        padding-bottom:88px!important;
    }
}
@media(min-width:769px){
    .asset-mobile-cards{
        display:none!important;
    }
}

</style>

<div class="asset-page-head">
    <div class="asset-page-title">
        <h2 class="mb-1">Asset Management</h2>
        <div class="text-muted">Manage POS, printers, scanners, PCs, network equipment and photos.</div>
    </div>
    <?php if($canManageAsset): ?><a href="add_asset.php" class="btn btn-primary">Add Asset</a><?php endif; ?>
</div>
<?php if($assetMessage !== ''): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?= h($assetMessage); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if($assetError !== ''): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?= h($assetError); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<form method="get" class="row g-2 asset-filter-card"><div class="col-md-6"><input type="text" name="keyword" value="<?= h($keyword); ?>" class="form-control" placeholder="Search asset code, name, branch, serial no..."></div><div class="col-md-3"><select name="status" class="form-select"><option value="">All Status</option><option value="Active" <?= $status=='Active'?'selected':''; ?>>Active</option><option value="Repair" <?= $status=='Repair'?'selected':''; ?>>Repair</option><option value="Inactive" <?= $status=='Inactive'?'selected':''; ?>>Inactive</option><option value="Disposed" <?= $status=='Disposed'?'selected':''; ?>>Disposed</option></select></div><div class="col-md-3 asset-filter-actions"><button class="btn btn-primary" type="submit">Search</button><a href="asset_list.php" class="btn btn-outline-secondary">Reset</a></div></form>
<div class="asset-table-card"><div class="card-body"><div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-dark"><tr><th>Photo</th><th>No.</th><th>Asset Code</th><th>Name</th><th>Type</th><th>Branch</th><th>Location</th><th>Status</th><th>Serial No</th><th>Tickets</th><th>Action</th></tr></thead><tbody>
<?php $seqNo = 1; foreach($assets as $asset): ?><tr><td><?php if(!empty($asset['asset_photo'])): ?><button type="button" class="asset-photo-btn" data-asset-photo="<?= h($asset['asset_photo']); ?>" data-asset-title="<?= h(($asset['asset_code'] ?? '').' - '.($asset['asset_name'] ?? '')); ?>"><img src="<?= h($asset['asset_photo']); ?>" class="asset-photo-thumb" alt="Asset photo"></button><?php else: ?><div class="asset-no-photo">No Photo</div><?php endif; ?></td><td class="fw-bold text-center"><?= $seqNo++; ?></td><td><strong><?= h($asset['asset_code']); ?></strong></td><td><?= h($asset['asset_name']); ?></td><td><?= h($asset['asset_type']); ?></td><td><?= h($asset['branch']); ?></td><td><?= h($asset['location']); ?></td><td><span class="badge <?= $statusClass[$asset['status']] ?? 'bg-secondary'; ?>"><?= h($asset['status']); ?></span></td><td><?= h($asset['serial_no']); ?></td><td><span class="badge bg-primary-subtle text-primary"><?= (int)($asset['linked_ticket_count'] ?? 0); ?></span></td><td>
    <div class="d-flex gap-1 flex-wrap">
        <a href="asset_history.php?id=<?= (int)$asset['id']; ?>" class="btn btn-sm btn-outline-primary">History</a>
        <?php if($canManageAsset): ?>
            <a href="edit_asset.php?id=<?= (int)$asset['id']; ?>" class="btn btn-sm btn-outline-warning">Edit</a>
            <?php if(($asset['status'] ?? '') === 'Active' || ($asset['status'] ?? '') === 'Repair'): ?>
            <form method="post" action="asset_status_action.php" class="d-inline" onsubmit="return confirm('Disable this asset? It will not appear in Create/Edit Ticket asset dropdown.\n\n<?= h($asset['asset_code']); ?> - <?= h($asset['asset_name']); ?>');">
                <input type="hidden" name="id" value="<?= (int)$asset['id']; ?>">
                <input type="hidden" name="action" value="disable">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Disable</button>
            </form>
            <?php else: ?>
            <form method="post" action="asset_status_action.php" class="d-inline" onsubmit="return confirm('Enable this asset? It will appear again in Create/Edit Ticket asset dropdown.\n\n<?= h($asset['asset_code']); ?> - <?= h($asset['asset_name']); ?>');">
                <input type="hidden" name="id" value="<?= (int)$asset['id']; ?>">
                <input type="hidden" name="action" value="enable">
                <button type="submit" class="btn btn-sm btn-outline-success">Enable</button>
            </form>
            <?php endif; ?>

            <form method="post" action="asset_status_action.php" class="d-inline" onsubmit="return confirm('Delete this asset permanently?\n\nIf this asset has related tickets, deletion will be blocked.');">
                <input type="hidden" name="id" value="<?= (int)$asset['id']; ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn btn-sm btn-outline-danger" <?= ((int)($asset['linked_ticket_count'] ?? 0) > 0) ? 'disabled title="Cannot delete asset with linked tickets"' : ''; ?>>Delete</button>
            </form>
        <?php endif; ?>
    </div>
</td></tr><?php endforeach; ?>
<?php if(count($assets)==0): ?><tr><td colspan="11" class="text-center text-muted"><?= h(asset_t('no_assets_found')); ?></td></tr><?php endif; ?>
</tbody></table></div></div></div>

<div class="asset-mobile-cards">
    <?php $seqNoMobile = 1; foreach($assets as $asset): ?>
    <div class="asset-card">
        <div class="asset-card-top">
            <div class="asset-card-photo">
                <?php if(!empty($asset['asset_photo'])): ?>
                    <button type="button" class="asset-photo-btn asset-photo-mobile-btn" data-asset-photo="<?= h($asset['asset_photo']); ?>" data-asset-title="<?= h(($asset['asset_code'] ?? '').' - '.($asset['asset_name'] ?? '')); ?>">
                        <img src="<?= h($asset['asset_photo']); ?>" alt="Asset photo">
                    </button>
                <?php else: ?>
                    <div class="asset-no-photo">No Photo</div>
                <?php endif; ?>
            </div>

            <div class="asset-card-main">
                <div class="asset-code"><?= h($asset['asset_code']); ?></div>
                <div class="asset-name"><?= h($asset['asset_name']); ?></div>

                <div class="asset-badges">
                    <span class="badge bg-dark">#<?= $seqNoMobile++; ?></span>
                    <span class="badge <?= $statusClass[$asset['status']] ?? 'bg-secondary'; ?>"><?= h($asset['status']); ?></span>
                    <span class="badge bg-info text-dark"><?= h($asset['asset_type']); ?></span>
                </div>
            </div>
        </div>

        <div class="asset-meta">
            <div><strong>Branch</strong><?= h($asset['branch']); ?></div>
            <div><strong>Location</strong><?= h($asset['location'] ?: '-'); ?></div>
            <div><strong>Serial No</strong><?= h($asset['serial_no'] ?: '-'); ?></div>
            <div><strong>Brand / Model</strong><?= h(trim(($asset['brand'] ?? '').' '.($asset['model'] ?? '')) ?: '-'); ?></div>
            <div><strong><?= h(__('Linked Tickets')) ?></strong><?= (int)($asset['linked_ticket_count'] ?? 0); ?></div>
        </div>

        <div class="asset-actions">
            <a href="asset_history.php?id=<?= (int)$asset['id']; ?>" class="btn btn-outline-primary">History</a>
            <?php if($canManageAsset): ?>
                <a href="edit_asset.php?id=<?= (int)$asset['id']; ?>" class="btn btn-warning">Edit</a>
                <?php if(($asset['status'] ?? '') === 'Active' || ($asset['status'] ?? '') === 'Repair'): ?>
                <form method="post" action="asset_status_action.php" class="d-inline flex-fill" onsubmit="return confirm('Disable this asset?');">
                    <input type="hidden" name="id" value="<?= (int)$asset['id']; ?>">
                    <input type="hidden" name="action" value="disable">
                    <button type="submit" class="btn btn-outline-secondary w-100">Disable</button>
                </form>
                <?php else: ?>
                <form method="post" action="asset_status_action.php" class="d-inline flex-fill" onsubmit="return confirm('Enable this asset?');">
                    <input type="hidden" name="id" value="<?= (int)$asset['id']; ?>">
                    <input type="hidden" name="action" value="enable">
                    <button type="submit" class="btn btn-outline-success w-100">Enable</button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if(count($assets) == 0): ?>
        <div class="asset-empty">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            <?= h(asset_t('no_assets_found')); ?>
        </div>
    <?php endif; ?>
</div>


<div class="asset-photo-modal-backdrop" id="assetPhotoModal" aria-hidden="true">
    <div class="asset-photo-modal-box" role="dialog" aria-modal="true" aria-labelledby="assetPhotoModalTitle">
        <div class="asset-photo-modal-head">
            <div class="asset-photo-modal-title" id="assetPhotoModalTitle">Asset Photo</div>
            <button type="button" class="asset-photo-modal-close" id="assetPhotoClose" aria-label="Close">×</button>
        </div>
        <div class="asset-photo-modal-body">
            <img src="" alt="Asset photo preview" id="assetPhotoPreview">
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const modal = document.getElementById('assetPhotoModal');
    const preview = document.getElementById('assetPhotoPreview');
    const title = document.getElementById('assetPhotoModalTitle');
    const closeBtn = document.getElementById('assetPhotoClose');
    let lastFocus = null;

    function openAssetPhoto(src, name){
        if(!src || !modal || !preview){ return; }
        lastFocus = document.activeElement;
        preview.src = src;
        title.textContent = name || 'Asset Photo';
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if(closeBtn){ closeBtn.focus(); }
    }

    function closeAssetPhoto(){
        if(!modal || !preview){ return; }
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        preview.src = '';
        document.body.style.overflow = '';
        if(lastFocus && typeof lastFocus.focus === 'function'){
            lastFocus.focus();
        }
    }

    document.querySelectorAll('[data-asset-photo]').forEach(function(btn){
        btn.addEventListener('click', function(){
            openAssetPhoto(this.getAttribute('data-asset-photo'), this.getAttribute('data-asset-title'));
        });
    });

    if(closeBtn){
        closeBtn.addEventListener('click', closeAssetPhoto);
    }

    if(modal){
        modal.addEventListener('click', function(e){
            if(e.target === modal){
                closeAssetPhoto();
            }
        });
    }

    document.addEventListener('keydown', function(e){
        if(e.key === 'Escape' && modal && modal.classList.contains('show')){
            closeAssetPhoto();
        }
    });

    // Mobile/PWA: browser back button closes preview first instead of trapping user.
    window.addEventListener('popstate', function(){
        if(modal && modal.classList.contains('show')){
            closeAssetPhoto();
        }
    });
});
</script>

<?php require 'footer.php'; ?>
