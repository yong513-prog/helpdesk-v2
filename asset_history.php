<?php
if(!function_exists('h')){ function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); } }

require 'header.php';
require 'db.php';
require_once 'access_control.php';
require_once 'module_permissions.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);

function ah($value)
{
    $value = trim((string)($value ?? ''));
    return htmlspecialchars($value !== '' ? $value : '-', ENT_QUOTES, 'UTF-8');
}

$stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
$stmt->execute([$id]);
$asset = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$asset)
{
    die("Asset not found");
}

$stmt = $pdo->prepare("SELECT * FROM tickets WHERE asset_id = ? ORDER BY id DESC");
$stmt->execute([$id]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusClass = [
    'Open' => 'bg-danger',
    'In Progress' => 'bg-warning text-dark',
    'Pending' => 'bg-info text-dark',
    'Solved' => 'bg-success',
    'Closed' => 'bg-secondary'
];

$priorityClass = [
    'Low' => 'bg-success-subtle text-success',
    'Medium' => 'bg-warning-subtle text-warning',
    'High' => 'bg-danger-subtle text-danger',
    'Urgent' => 'bg-danger text-white',
    'Critical' => 'bg-danger text-white'
];

$assetStatus = trim((string)($asset['status'] ?? '-'));
$assetStatusClass = strtolower($assetStatus) === 'active' ? 'bg-success' : 'bg-secondary';
?>

<style>
.asset-history-page{max-width:1180px;margin:0 auto}.asset-hero{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:18px}.asset-title-block{min-width:0;flex:1}.asset-title{font-size:32px;font-weight:900;color:#0f172a;margin:0 0 6px;line-height:1.15}.asset-subtitle{font-size:18px;color:#64748b;line-height:1.35;word-break:break-word}.asset-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.asset-actions .btn{border-radius:12px;font-weight:800;padding:10px 18px}.asset-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:16px}.info-card,.tickets-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 10px 24px rgba(15,23,42,.055);overflow:hidden}.info-card{padding:20px}.info-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}.info-item{min-width:0}.info-label{font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:#64748b;font-weight:800;margin-bottom:5px}.info-value{font-size:16px;color:#111827;font-weight:750;word-break:break-word}.info-value.large{font-size:20px;font-weight:900}.section-divider{height:1px;background:#e5e7eb;margin:18px 0}.remark-box{background:#f8fafc;border:1px solid #edf2f7;border-radius:14px;padding:14px;color:#334155;white-space:pre-line}.tickets-head{padding:16px 18px;border-bottom:1px solid #edf2f7;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.tickets-head h5{font-weight:900;margin:0}.tickets-count{border-radius:999px;padding:6px 11px;font-weight:850}.tickets-body{padding:0}.ticket-table{min-width:820px;margin:0}.ticket-table th{background:#f8fafc!important;color:#334155;font-size:13px;white-space:nowrap}.ticket-table td{vertical-align:middle}.ticket-title{max-width:260px;white-space:normal;word-break:break-word}.empty-state{text-align:center;padding:34px 18px;color:#64748b}.empty-state i{font-size:38px;color:#94a3b8;display:block;margin-bottom:8px}.mobile-ticket-list{display:none;padding:12px}.mobile-ticket{border:1px solid #edf2f7;border-radius:16px;padding:14px;margin-bottom:12px;background:#fff}.mobile-ticket-top{display:flex;justify-content:space-between;gap:10px;margin-bottom:8px}.mobile-ticket-no{font-weight:900;color:#2563eb;text-decoration:none}.mobile-ticket-title{font-weight:850;color:#0f172a;margin-bottom:10px;word-break:break-word}.mobile-ticket-meta{display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;color:#64748b;margin-bottom:12px}.mobile-ticket-meta strong{display:block;color:#334155}.mobile-ticket-actions{display:flex;justify-content:flex-end}.badge{border-radius:10px;padding:.45em .7em}.scroll-hint{display:none;font-size:12px;color:#64748b;padding:10px 14px;border-bottom:1px solid #edf2f7;background:#fbfdff}@media(max-width:992px){.asset-history-page{max-width:100%}.asset-title{font-size:28px}.asset-summary,.info-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:768px){.asset-hero{display:block}.asset-actions{margin-top:12px;display:grid;grid-template-columns:1fr 1fr}.asset-actions .btn{width:100%;padding:11px 10px}.asset-title{font-size:30px}.asset-subtitle{font-size:20px}.info-card{padding:18px}.info-grid{grid-template-columns:1fr;gap:14px}.info-label{font-size:14px}.info-value{font-size:18px}.info-value.large{font-size:22px}.desktop-ticket-table{display:none}.mobile-ticket-list{display:block}.scroll-hint{display:none}.tickets-head{padding:15px}.tickets-card{border-radius:16px}.asset-summary{grid-template-columns:1fr}.content-wrap{padding-left:14px!important;padding-right:14px!important}}@media(min-width:769px){.desktop-ticket-table{display:block}.scroll-hint{display:block}}
</style>

<div class="asset-history-page">

    <div class="asset-hero">
        <div class="asset-title-block">
            <h2 class="asset-title">资产历史</h2>
            <div class="asset-subtitle">
                <?= ah($asset['asset_code']); ?> - <?= ah($asset['asset_name']); ?>
            </div>
        </div>

        <div class="asset-actions">
            <a href="asset_list.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>

            <?php if(has_action_permission('manage_asset')): ?>
            <a href="edit_asset.php?id=<?= (int)$asset['id']; ?>" class="btn btn-warning">
                <i class="bi bi-pencil-square me-1"></i> <?= h(__('Edit Asset')) ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="info-card mb-3">
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Asset Code</div>
                <div class="info-value large"><?= ah($asset['asset_code']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Type</div>
                <div class="info-value"><?= ah($asset['asset_type'] ?? '-'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Branch</div>
                <div class="info-value"><?= ah($asset['branch'] ?? '-'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Status</div>
                <div class="info-value"><span class="badge <?= $assetStatusClass; ?>"><?= ah($assetStatus); ?></span></div>
            </div>
        </div>

        <div class="section-divider"></div>

        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Brand</div>
                <div class="info-value"><?= ah($asset['brand'] ?? '-'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Model</div>
                <div class="info-value"><?= ah($asset['model'] ?? '-'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Serial No</div>
                <div class="info-value"><?= ah($asset['serial_no'] ?? '-'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Location</div>
                <div class="info-value"><?= ah($asset['location'] ?? '-'); ?></div>
            </div>
        </div>

        <div class="section-divider"></div>

        <div class="info-item">
            <div class="info-label">Remark</div>
            <div class="remark-box"><?= nl2br(ah($asset['remark'] ?? '-')); ?></div>
        </div>
    </div>

    <div class="tickets-card">
        <div class="tickets-head">
            <h5><i class="bi bi-link-45deg me-1"></i> <?= h(__('Related Tickets')) ?></h5>
            <span class="badge bg-primary tickets-count"><?= count($tickets); ?> <?= h(__('Tickets')) ?></span>
        </div>

        <div class="scroll-hint">
            <i class="bi bi-arrows"></i> <?= h(__('Desktop table can scroll horizontally if needed.')) ?>
        </div>

        <div class="tickets-body">
            <div class="table-responsive desktop-ticket-table">
                <table class="table table-hover align-middle ticket-table">
                    <thead>
                        <tr>
                            <th><?= h(__('Ticket No')) ?></th>
                            <th><?= h(__('Title')) ?></th>
                            <th><?= h(__('Branch')) ?></th>
                            <th><?= h(__('Priority')) ?></th>
                            <th><?= h(__('Status')) ?></th>
                            <th><?= h(__('Created At')) ?></th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tickets as $ticket): ?>
                        <tr>
                            <td><strong><?= ah($ticket['ticket_no']); ?></strong></td>
                            <td class="ticket-title"><?= ah($ticket['title']); ?></td>
                            <td><?= ah($ticket['branch'] ?? '-'); ?></td>
                            <td><span class="badge <?= $priorityClass[$ticket['priority']] ?? 'bg-secondary-subtle text-secondary'; ?>"><?= ah($ticket['priority'] ?? '-'); ?></span></td>
                            <td><span class="badge <?= $statusClass[$ticket['status']] ?? 'bg-secondary'; ?>"><?= ah($ticket['status']); ?></span></td>
                            <td><?= ah($ticket['created_at'] ?? '-'); ?></td>
                            <td class="text-end"><a href="view_ticket.php?id=<?= (int)$ticket['id']; ?>" class="btn btn-sm btn-outline-primary"><?= h(__('View')) ?></a></td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if(count($tickets) == 0): ?>
                        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-inbox"></i><?= h(__('No related tickets found for this asset.')) ?></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mobile-ticket-list">
                <?php foreach($tickets as $ticket): ?>
                <div class="mobile-ticket">
                    <div class="mobile-ticket-top">
                        <a class="mobile-ticket-no" href="view_ticket.php?id=<?= (int)$ticket['id']; ?>"><?= ah($ticket['ticket_no']); ?></a>
                        <span class="badge <?= $statusClass[$ticket['status']] ?? 'bg-secondary'; ?>"><?= ah($ticket['status']); ?></span>
                    </div>
                    <div class="mobile-ticket-title"><?= ah($ticket['title']); ?></div>
                    <div class="mobile-ticket-meta">
                        <div><strong><?= h(__('Branch')) ?></strong><?= ah($ticket['branch'] ?? '-'); ?></div>
                        <div><strong><?= h(__('Priority')) ?></strong><span class="badge <?= $priorityClass[$ticket['priority']] ?? 'bg-secondary-subtle text-secondary'; ?>"><?= ah($ticket['priority'] ?? '-'); ?></span></div>
                        <div style="grid-column:1 / -1;"><strong><?= h(__('Created At')) ?></strong><?= ah($ticket['created_at'] ?? '-'); ?></div>
                    </div>
                    <div class="mobile-ticket-actions">
                        <a href="view_ticket.php?id=<?= (int)$ticket['id']; ?>" class="btn btn-sm btn-outline-primary"><?= h(__('View Ticket')) ?></a>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if(count($tickets) == 0): ?>
                <div class="empty-state"><i class="bi bi-inbox"></i><?= h(__('No related tickets found for this asset.')) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php require 'footer.php'; ?>
