<?php

require 'header.php';
require 'db.php';
require_once 'ticket_status_options.php';
require_once 'access_control.php';
require_once 'module_permissions.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

require_module_permission('report_kpi');
ticket_status_ensure_ticket_column($pdo);
ticket_status_ensure_last_update_columns($pdo);

function h($v)
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function kpi_branch_code_no_translate($value)
{
    $value = trim((string)$value);
    if($value === '') return $value;

    // Branch code is system data. Never send it to language translation.
    $map = [
        '电脑'=>'PC','電腦'=>'PC','Computer'=>'PC','computer'=>'PC','Komputer'=>'PC','komputer'=>'PC',
        'HQ'=>'HQ','hq'=>'HQ','KB'=>'KB','kb'=>'KB','KC'=>'KC','kc'=>'KC','KJ'=>'KJ','kj'=>'KJ','KK'=>'KK','kk'=>'KK','KL'=>'KL','kl'=>'KL','KR'=>'KR','kr'=>'KR','KS'=>'KS','ks'=>'KS','ML'=>'ML','ml'=>'ML',
        'PC'=>'PC','pc'=>'PC','PJ'=>'PJ','pj'=>'PJ','PKL'=>'PKL','pkl'=>'PKL','PM'=>'PM','pm'=>'PM','SE'=>'SE','se'=>'SE','TM'=>'TM','tm'=>'TM','TPC'=>'TPC','tpc'=>'TPC','TPN'=>'TPN','tpn'=>'TPN','TPT'=>'TPT','tpt'=>'TPT','WC'=>'WC','wc'=>'WC','WK'=>'WK','wk'=>'WK',
        'BS'=>'BS','bs'=>'BS','LAS'=>'LAS','las'=>'LAS'
    ];
    if(isset($map[$value])) return $map[$value];

    if(preg_match('/^(电脑|電腦|Computer|computer|Komputer|komputer)-?(\d{6}-\d+)$/u', $value, $m)) return 'PC-' . $m[2];
    if(preg_match('/^pc(-?\d{6}-\d+)$/i', $value, $m)) return 'PC' . (strpos($m[1], '-') === 0 ? $m[1] : '-' . $m[1]);

    return $value;
}


function kpi_month_name($monthNum)
{
    $lang = function_exists('hd_lang') ? hd_lang() : ($_SESSION['helpdesk_lang'] ?? 'en');
    $names = [
        'en' => [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'],
        'ms' => [1=>'Januari',2=>'Februari',3=>'Mac',4=>'April',5=>'Mei',6=>'Jun',7=>'Julai',8=>'Ogos',9=>'September',10=>'Oktober',11=>'November',12=>'Disember'],
        'zh' => [1=>'1月',2=>'2月',3=>'3月',4=>'4月',5=>'5月',6=>'6月',7=>'7月',8=>'8月',9=>'9月',10=>'10月',11=>'11月',12=>'12月']
    ];
    return $names[$lang][(int)$monthNum] ?? $names['en'][(int)$monthNum] ?? '';
}

function kpi_month_label($date)
{
    $ts = strtotime($date);
    if(!$ts) return '';
    $lang = function_exists('hd_lang') ? hd_lang() : ($_SESSION['helpdesk_lang'] ?? 'en');
    $m = kpi_month_name((int)date('n', $ts));
    $y = date('Y', $ts);
    return $lang === 'zh' ? ($y.'年'.$m) : ($m.' '.$y);
}

function kpi_day_label($date)
{
    $ts = strtotime($date);
    if(!$ts) return '';
    $lang = function_exists('hd_lang') ? hd_lang() : ($_SESSION['helpdesk_lang'] ?? 'en');
    if($lang === 'zh') return date('n月 j日', $ts);
    return date('M d', $ts);
}

function kpi_health_label($overPct)
{
    if($overPct <= 0) return __('Excellent');
    if($overPct <= 30) return __('Warning');
    return __('Critical');
}

function kpi_health_class($overPct)
{
    if($overPct <= 0) return 'health-good';
    if($overPct <= 30) return 'health-warn';
    return 'health-bad';
}

$whereSql = " WHERE 1=1 ";
$whereParams = [];
apply_ticket_access_filter($whereSql, $whereParams);

$month = $_GET['month'] ?? date('Y-m');
if(!preg_match('/^\d{4}-\d{2}$/', $month))
{
    $month = date('Y-m');
}

$startDate = $month . '-01 00:00:00';
$endDate = date('Y-m-t 23:59:59', strtotime($startDate));

$statusRows = ticket_status_fetch_all($pdo, false);
$statusClass = ticket_status_color_map($pdo);
$closedStatusNames = ticket_status_closed_names($pdo);
$closedStatusQuoted = count($closedStatusNames) ? implode(',', array_map([$pdo, 'quote'], $closedStatusNames)) : "''";

function kpi_count(PDO $pdo, string $whereSql, array $whereParams, string $extraCondition, array $extraParams = []): int
{
    $sql = "SELECT COUNT(*) FROM tickets ".$whereSql." AND created_at BETWEEN ? AND ?";
    $params = array_merge($whereParams, [$GLOBALS['startDate'], $GLOBALS['endDate']]);

    if($extraCondition !== '')
    {
        $sql .= " AND ".$extraCondition;
        $params = array_merge($params, $extraParams);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

$totalTickets = kpi_count($pdo, $whereSql, $whereParams, "");
$closedTickets = kpi_count($pdo, $whereSql, $whereParams, "status IN (".$closedStatusQuoted.")");
$activeTickets = kpi_count($pdo, $whereSql, $whereParams, "status NOT IN (".$closedStatusQuoted.")");
$overdueTickets = kpi_count($pdo, $whereSql, $whereParams, "due_date IS NOT NULL AND due_date < NOW() AND status NOT IN (".$closedStatusQuoted.")");

$slaWithin = kpi_count($pdo, $whereSql, $whereParams, "due_date IS NOT NULL AND status IN (".$closedStatusQuoted.") AND COALESCE(closed_at, updated_at, last_update) <= due_date");
$slaPercent = ($closedTickets > 0) ? round(($slaWithin / $closedTickets) * 100, 1) : 0;

$statusSummary = [];
foreach($statusRows as $sr)
{
    $sn = (string)$sr['status_name'];
    $count = kpi_count($pdo, $whereSql, $whereParams, "status = ?", [$sn]);
    $statusSummary[] = [
        'name' => $sn,
        'count' => $count,
        'is_closed' => (int)$sr['is_closed'],
        'color' => $statusClass[$sn] ?? 'bg-secondary',
        'percent' => $totalTickets > 0 ? round(($count / $totalTickets) * 100, 1) : 0
    ];
}

$branchRows = [];
try {
    $sql = "
        SELECT branch, COUNT(*) total,
            SUM(CASE WHEN status IN (".$closedStatusQuoted.") THEN 1 ELSE 0 END) closed_total,
            SUM(CASE WHEN due_date IS NOT NULL AND due_date < NOW() AND status NOT IN (".$closedStatusQuoted.") THEN 1 ELSE 0 END) overdue_total
        FROM tickets
        ".$whereSql."
        AND created_at BETWEEN ? AND ?
        GROUP BY branch
        ORDER BY total DESC
    ";
    $params = array_merge($whereParams, [$startDate, $endDate]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $branchRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $merged = [];
    foreach($branchRows as $br){
        $code = kpi_branch_code_no_translate($br['branch'] ?? '');
        if(!isset($merged[$code])) $merged[$code] = ['branch'=>$code,'total'=>0,'closed_total'=>0,'overdue_total'=>0];
        $merged[$code]['total'] += (int)($br['total'] ?? 0);
        $merged[$code]['closed_total'] += (int)($br['closed_total'] ?? 0);
        $merged[$code]['overdue_total'] += (int)($br['overdue_total'] ?? 0);
    }
    $branchRows = array_values($merged);
} catch(Exception $e) { $branchRows = []; }

$picRows = [];
try {
    $sql = "
        SELECT department, COUNT(*) total,
            SUM(CASE WHEN status IN (".$closedStatusQuoted.") THEN 1 ELSE 0 END) closed_total,
            SUM(CASE WHEN due_date IS NOT NULL AND due_date < NOW() AND status NOT IN (".$closedStatusQuoted.") THEN 1 ELSE 0 END) overdue_total
        FROM tickets
        ".$whereSql."
        AND created_at BETWEEN ? AND ?
        GROUP BY department
        ORDER BY total DESC
    ";
    $params = array_merge($whereParams, [$startDate, $endDate]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $picRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) { $picRows = []; }



$kpiSafeTotal = max(1, (int)$totalTickets);
$activePercent = $totalTickets > 0 ? round(($activeTickets / $totalTickets) * 100, 1) : 0;
$closedPercent = $totalTickets > 0 ? round(($closedTickets / $totalTickets) * 100, 1) : 0;
$overduePercent = $totalTickets > 0 ? round(($overdueTickets / $totalTickets) * 100, 1) : 0;

$topBranchName = '-';
$topBranchTotal = 0;
foreach($branchRows as $br){
    if((int)($br['total'] ?? 0) > $topBranchTotal){
        $topBranchTotal = (int)$br['total'];
        $topBranchName = kpi_branch_code_no_translate($br['branch'] ?? '-');
    }
}
$topPicName = '-';
$topPicTotal = 0;
foreach($picRows as $pr){
    if((int)($pr['total'] ?? 0) > $topPicTotal){
        $topPicTotal = (int)$pr['total'];
        $topPicName = (string)($pr['department'] ?? '-');
    }
}

$chartColors = ['#ef4444','#f59e0b','#06b6d4','#22c55e','#94a3b8','#111827','#6366f1','#ec4899','#14b8a6','#8b5cf6'];
$statusChart = [];
foreach($statusSummary as $idx => $row){
    $statusChart[] = [
        'name' => (string)$row['name'],
        'count' => (int)$row['count'],
        'percent' => (float)$row['percent'],
        'color' => $chartColors[$idx % count($chartColors)]
    ];
}

$dailyLabels = [];
$dailyTotals = [];
$dailyStatus = [];
$daysInMonth = (int)date('t', strtotime($startDate));
for($d=1; $d <= $daysInMonth; $d++){
    $dayKey = sprintf('%s-%02d', $month, $d);
    $dailyLabels[] = kpi_day_label($dayKey);
    $dailyTotals[$dayKey] = 0;
    foreach($statusSummary as $row){
        $dailyStatus[$row['name']][$dayKey] = 0;
    }
}
try {
    $sql = "
        SELECT DATE(created_at) day_key, status, COUNT(*) total
        FROM tickets
        ".$whereSql."
        AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at), status
        ORDER BY day_key ASC
    ";
    $params = array_merge($whereParams, [$startDate, $endDate]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $dr){
        $dayKey = (string)$dr['day_key'];
        $statusName = (string)$dr['status'];
        $count = (int)$dr['total'];
        if(isset($dailyTotals[$dayKey])){
            $dailyTotals[$dayKey] += $count;
            if(!isset($dailyStatus[$statusName])) $dailyStatus[$statusName] = array_fill_keys(array_keys($dailyTotals), 0);
            $dailyStatus[$statusName][$dayKey] = $count;
        }
    }
} catch(Exception $e) {}
$dailyTotalValues = array_values($dailyTotals);
$trendMax = max(1, max($dailyTotalValues ?: [0]));
$trendPoints = [];
$trendWidth = max(1, count($dailyTotalValues) - 1);
foreach($dailyTotalValues as $idx => $val){
    $x = $idx * (100 / $trendWidth);
    $y = 100 - ((int)$val / $trendMax * 86) - 7;
    $trendPoints[] = round($x,2).','.round($y,2);
}
$trendAreaPoints = '0,100 '.implode(' ', $trendPoints).' 100,100';
$trendLinePoints = implode(' ', $trendPoints);

?>

<style>
.report-wrap{max-width:100%;}
.report-head{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:18px;flex-wrap:wrap;}
.report-title{font-size:30px;font-weight:950;color:#0f172a;letter-spacing:-.03em;margin:0;display:flex;align-items:center;gap:10px;}
.report-subtitle{font-size:14px;color:#64748b;margin-top:5px;}
.report-filter{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:12px;box-shadow:0 10px 24px rgba(15,23,42,.06);}
.kpi-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:16px;}
.kpi-box{position:relative;background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:18px;box-shadow:0 12px 28px rgba(15,23,42,.07);overflow:hidden;transition:.18s ease;min-height:118px;}
.kpi-box:hover{transform:translateY(-2px);box-shadow:0 18px 36px rgba(15,23,42,.10);}
.kpi-label{font-size:12px;color:#475569;font-weight:900;text-transform:uppercase;letter-spacing:.04em;}
.kpi-value{font-size:32px;font-weight:950;color:#0f172a;line-height:1;margin-top:10px;}
.kpi-note{font-size:13px;color:#64748b;margin-top:10px;font-weight:700;}
.kpi-icon{position:absolute;right:18px;top:34px;width:54px;height:54px;border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:25px;}
.kpi-blue{background:#eff6ff;color:#2563eb;}.kpi-green{background:#ecfdf5;color:#16a34a;}.kpi-purple{background:#eef2ff;color:#4f46e5;}.kpi-red{background:#fef2f2;color:#dc2626;}.kpi-teal{background:#f0fdfa;color:#0f766e;}
.report-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 12px 28px rgba(15,23,42,.06);overflow:hidden;margin-bottom:16px;}
.report-card-head{padding:16px 18px;border-bottom:1px solid #edf2f7;font-weight:950;color:#0f172a;display:flex;justify-content:space-between;align-items:center;gap:10px;}
.report-card-body{padding:18px;}
.status-layout{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(320px,.9fr);gap:16px;margin-bottom:16px;}
.table td,.table th{font-size:13px;vertical-align:middle;}
.table thead th{background:#f8fafc!important;color:#334155!important;border-color:#e5e7eb!important;font-weight:900;}
.status-badge{border-radius:999px;padding:6px 10px;font-weight:900;font-size:11px;}
.donut-wrap{display:grid;grid-template-columns:180px 1fr;gap:20px;align-items:center;}
.donut{width:178px;height:178px;border-radius:50%;background:conic-gradient(var(--donut-stops));display:grid;place-items:center;box-shadow:inset 0 0 0 1px rgba(15,23,42,.04);}
.donut-inner{width:104px;height:104px;border-radius:50%;background:#fff;display:grid;place-items:center;text-align:center;box-shadow:0 4px 18px rgba(15,23,42,.10);}
.donut-number{font-size:28px;font-weight:950;color:#0f172a;line-height:1;}.donut-label{font-size:12px;color:#64748b;font-weight:800;margin-top:4px;}
.legend-row{display:grid;grid-template-columns:14px 1fr auto;gap:9px;align-items:center;margin:10px 0;font-size:13px;font-weight:800;color:#334155;}
.legend-dot{width:10px;height:10px;border-radius:50%;display:inline-block;}
.summary-grid{display:grid;grid-template-columns:1fr 1fr .78fr;gap:16px;}
.progress-mini{height:9px;background:#e5e7eb;border-radius:999px;overflow:hidden;min-width:70px;}
.progress-mini span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#22c55e,#16a34a);}
.progress-mini.danger span{background:linear-gradient(90deg,#ef4444,#dc2626);}
.insight-list{display:flex;flex-direction:column;gap:12px;}
.insight-item{display:grid;grid-template-columns:42px 1fr;gap:12px;align-items:center;padding:12px;border:1px solid #eef2f7;border-radius:16px;background:#fbfdff;}
.insight-icon{width:42px;height:42px;border-radius:14px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;}
.insight-title{font-weight:950;color:#0f172a;font-size:14px;}.insight-text{font-size:12px;color:#64748b;margin-top:2px;}
.trend-card{margin-top:0;}
.trend-svg{width:100%;height:210px;display:block;overflow:visible;}
.trend-grid-line{stroke:#e5e7eb;stroke-width:.5;}.trend-area{fill:url(#trendGradient);opacity:.9;}.trend-line{fill:none;stroke:#2563eb;stroke-width:2.6;stroke-linecap:round;stroke-linejoin:round;}.trend-point{fill:#2563eb;stroke:#fff;stroke-width:1;}
.trend-labels{display:flex;justify-content:space-between;color:#64748b;font-size:11px;font-weight:800;margin-top:-8px;}
.no-translate-code,.hd-no-translate{font-weight:900;}
@media(max-width:1300px){.kpi-grid{grid-template-columns:repeat(2,1fr);}.status-layout,.summary-grid{grid-template-columns:1fr;}.donut-wrap{grid-template-columns:180px 1fr;}}
@media(max-width:768px){.report-title{font-size:24px}.kpi-grid{grid-template-columns:1fr;}.donut-wrap{grid-template-columns:1fr;justify-items:center}.report-filter{width:100%;}.report-filter form,.report-head form{width:100%;}.summary-grid{grid-template-columns:1fr;}.trend-svg{height:160px;}}

.kpi-note strong{font-weight:950;}
.kpi-note.good{color:#16a34a}.kpi-note.warn{color:#ca8a04}.kpi-note.bad{color:#dc2626}
.progress-percent{display:flex;align-items:center;gap:10px;}
.progress-percent strong{min-width:48px;text-align:right;}
.health-pill{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:5px 10px;font-weight:900;font-size:11px;white-space:nowrap;}
.health-good{background:#dcfce7;color:#166534}.health-warn{background:#fef3c7;color:#92400e}.health-bad{background:#fee2e2;color:#991b1b}
.insight-item{transition:.18s ease}.insight-item:hover{transform:translateY(-1px);box-shadow:0 10px 24px rgba(15,23,42,.06)}
.insight-metric{font-size:18px;font-weight:950;color:#0f172a;line-height:1.1}
.insight-sub{font-size:12px;color:#64748b;font-weight:800;margin-top:3px}
.trend-card .report-card-head{background:linear-gradient(90deg,#ffffff,#f8fbff);}

</style>

<?php
$donutStops = [];
$cursor = 0;
foreach($statusChart as $s){
    $deg = $totalTickets > 0 ? (($s['count'] / $totalTickets) * 360) : 0;
    if($deg <= 0) continue;
    $donutStops[] = $s['color'].' '.$cursor.'deg '.($cursor + $deg).'deg';
    $cursor += $deg;
}
if(!$donutStops){ $donutStops[] = '#e5e7eb 0deg 360deg'; }
?>

<div class="report-wrap">
    <div class="report-head">
        <div>
            <h2 class="report-title"><i class="bi bi-graph-up-arrow text-primary"></i><?= __('KPI Report') ?></h2>
            <div class="report-subtitle"><?= __('Dynamic status report linked with Ticket Status Management.') ?></div>
        </div>
        <div class="report-filter">
            <form method="get" class="d-flex gap-2 align-items-end">
                <div>
                    <label class="form-label mb-1 fw-bold"><?= __('Month') ?></label>
                    <input type="month" name="month" value="<?= h($month); ?>" class="form-control">
                </div>
                <button class="btn btn-primary px-4" type="submit"><i class="bi bi-search me-1"></i><?= __('View') ?></button>
            </form>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-box">
            <div class="kpi-label"><?= __('Total Tickets') ?></div><div class="kpi-value"><?= (int)$totalTickets; ?></div><div class="kpi-note good"><i class="bi bi-arrow-up-short"></i><strong>100%</strong> <?= __('of Total Tickets') ?></div><div class="kpi-icon kpi-blue"><i class="bi bi-clipboard-data"></i></div>
        </div>
        <div class="kpi-box">
            <div class="kpi-label"><?= __('Active Tickets') ?></div><div class="kpi-value"><?= (int)$activeTickets; ?></div><div class="kpi-note good"><i class="bi bi-arrow-up-short"></i><strong><?= h($activePercent); ?>%</strong> <?= __('Active Ratio') ?></div><div class="kpi-icon kpi-green"><i class="bi bi-activity"></i></div>
        </div>
        <div class="kpi-box">
            <div class="kpi-label"><?= __('Closed Tickets') ?></div><div class="kpi-value"><?= (int)$closedTickets; ?></div><div class="kpi-note good"><i class="bi bi-arrow-up-short"></i><strong><?= h($closedPercent); ?>%</strong> <?= __('Closed Ratio') ?></div><div class="kpi-icon kpi-purple"><i class="bi bi-check2-circle"></i></div>
        </div>
        <div class="kpi-box">
            <div class="kpi-label"><?= __('Overdue Tickets') ?></div><div class="kpi-value text-danger"><?= (int)$overdueTickets; ?></div><div class="kpi-note <?= $overdueTickets > 0 ? 'bad' : 'good' ?>"><i class="bi <?= $overdueTickets > 0 ? 'bi-arrow-down-short' : 'bi-check2' ?>"></i><strong><?= h($overduePercent); ?>%</strong> <?= __('Overdue Ratio') ?></div><div class="kpi-icon kpi-red"><i class="bi bi-alarm"></i></div>
        </div>
        <div class="kpi-box">
            <div class="kpi-label"><?= __('SLA Compliance') ?></div><div class="kpi-value"><?= h($slaPercent); ?>%</div><div class="kpi-note"><?= __('Target') ?> ≥ 95%</div><div class="kpi-icon kpi-teal"><i class="bi bi-shield-check"></i></div>
        </div>
    </div>

    <div class="status-layout">
        <div class="report-card">
            <div class="report-card-head">
                <span><i class="bi bi-list-check text-primary me-2"></i><?= __('Status Summary') ?></span>
                <span class="text-muted small"><?= h($startDate); ?> - <?= h($endDate); ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th><?= __('Status') ?></th><th><?= __('Closed?') ?></th><th><?= __('Total') ?></th><th>%</th><th><?= __('Trend') ?></th></tr></thead>
                    <tbody>
                        <?php foreach($statusSummary as $row): ?>
                        <tr>
                            <td><span class="badge <?= h($row['color']); ?> status-badge"><?= h(__($row['name'])); ?></span></td>
                            <td><?= $row['is_closed'] ? __('Yes') : __('No'); ?></td>
                            <td class="fw-bold"><?= (int)$row['count']; ?></td>
                            <td class="fw-bold"><?= h($row['percent']); ?>%</td>
                            <td><div class="progress-percent"><strong><?= h($row['percent']); ?>%</strong><div class="progress-mini <?= ((int)$row['is_closed'] || (int)$row['count'] === 0) ? '' : 'danger' ?>"><span style="width:<?= h(min(100, (float)$row['percent'])); ?>%"></span></div></div></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(!$statusSummary): ?><tr><td colspan="5" class="text-center text-muted py-4"><?= __('No status found') ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="report-card">
            <div class="report-card-head"><span><i class="bi bi-pie-chart text-primary me-2"></i><?= __('Ticket Status Distribution') ?></span></div>
            <div class="report-card-body donut-wrap">
                <div class="donut" style="--donut-stops: <?= h(implode(',', $donutStops)); ?>;">
                    <div class="donut-inner"><div><div class="donut-number"><?= (int)$totalTickets; ?></div><div class="donut-label"><?= __('Total') ?></div></div></div>
                </div>
                <div>
                    <?php foreach($statusChart as $s): ?>
                    <div class="legend-row"><span class="legend-dot" style="background:<?= h($s['color']); ?>"></span><span><?= h(__($s['name'])); ?></span><strong><?= h($s['percent']); ?>% (<?= (int)$s['count']; ?>)</strong></div>
                    <?php endforeach; ?>
                    <?php if(!$statusChart): ?><div class="text-muted"><?= __('No data') ?></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="summary-grid">
        <div class="report-card">
            <div class="report-card-head"><span><i class="bi bi-buildings text-primary me-2"></i><?= __('Branch Summary') ?></span></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th><?= __('Branch') ?></th><th><?= __('Total') ?></th><th><?= __('Closed') ?></th><th><?= __('Overdue') ?></th><th>% <?= __('Overdue') ?></th><th><?= __('Health') ?></th></tr></thead>
                    <tbody>
                        <?php foreach($branchRows as $r): $brTotal=max(1,(int)$r['total']); $brOverPct=round(((int)$r['overdue_total']/$brTotal)*100,1); ?>
                        <tr>
                            <td class="hd-no-translate notranslate fw-bold" translate="no"><?= h(kpi_branch_code_no_translate($r['branch'] ?: '-')); ?></td><td><?= (int)$r['total']; ?></td><td><?= (int)$r['closed_total']; ?></td><td><?= (int)$r['overdue_total']; ?></td>
                            <td><div class="d-flex align-items-center gap-2"><span class="fw-bold"><?= h($brOverPct); ?>%</span><div class="progress-mini danger"><span style="width:<?= h(min(100,$brOverPct)); ?>%"></span></div></div></td><td><span class="health-pill <?= h(kpi_health_class($brOverPct)); ?>"><?= h(kpi_health_label($brOverPct)); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(!$branchRows): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= __('No data') ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="report-card">
            <div class="report-card-head"><span><i class="bi bi-person-badge text-primary me-2"></i><?= __('KPI Person In Charge Summary') ?></span></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th><?= __('KPI Person In Charge') ?></th><th><?= __('Total') ?></th><th><?= __('Closed') ?></th><th><?= __('Overdue') ?></th><th>% <?= __('Overdue') ?></th><th><?= __('Health') ?></th></tr></thead>
                    <tbody>
                        <?php foreach($picRows as $r): $picTotal=max(1,(int)$r['total']); $picOverPct=round(((int)$r['overdue_total']/$picTotal)*100,1); ?>
                        <tr><td class="fw-bold"><?= h($r['department'] ?: '-'); ?></td><td><?= (int)$r['total']; ?></td><td><?= (int)$r['closed_total']; ?></td><td><?= (int)$r['overdue_total']; ?></td><td><div class="d-flex align-items-center gap-2"><span class="fw-bold"><?= h($picOverPct); ?>%</span><div class="progress-mini danger"><span style="width:<?= h(min(100,$picOverPct)); ?>%"></span></div></div></td><td><span class="health-pill <?= h(kpi_health_class($picOverPct)); ?>"><?= h(kpi_health_label($picOverPct)); ?></span></td></tr>
                        <?php endforeach; ?>
                        <?php if(!$picRows): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= __('No data') ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="report-card">
            <div class="report-card-head"><span><i class="bi bi-lightbulb text-primary me-2"></i><?= __('Quick Insights') ?></span></div>
            <div class="report-card-body insight-list">
                <div class="insight-item"><div class="insight-icon" style="background:#3b82f6"><i class="bi bi-ticket-detailed"></i></div><div><div class="insight-metric"><?= (int)$activeTickets; ?> <?= __('Tickets') ?></div><div class="insight-title"><?= __('Active Tickets') ?> · <?= h($activePercent); ?>%</div><div class="insight-sub"><?= __('Need triage and assignment') ?></div></div></div>
                <div class="insight-item"><div class="insight-icon" style="background:#22c55e"><i class="bi bi-shield-check"></i></div><div><div class="insight-metric"><?= h($slaPercent); ?>%</div><div class="insight-title"><?= __('SLA') ?> · <?= $slaPercent >= 95 ? __('Excellent') : __('Needs Attention') ?></div><div class="insight-sub"><?= __('Target') ?> ≥ 95%</div></div></div>
                <div class="insight-item"><div class="insight-icon" style="background:#f59e0b"><i class="bi bi-alarm"></i></div><div><div class="insight-metric"><?= (int)$overdueTickets; ?> <?= __('Tickets') ?></div><div class="insight-title"><?= __('Overdue') ?></div><div class="insight-sub"><?= __('Requires immediate attention') ?></div></div></div>
                <div class="insight-item"><div class="insight-icon" style="background:#6366f1"><i class="bi bi-buildings"></i></div><div><div class="insight-title"><?= __('Top Branch') ?>: <span class="hd-no-translate notranslate" translate="no"><?= h($topBranchName); ?></span></div><div class="insight-text"><?= (int)$topBranchTotal; ?> <?= __('tickets') ?></div></div></div>
                <div class="insight-item"><div class="insight-icon" style="background:#0f766e"><i class="bi bi-person"></i></div><div><div class="insight-title"><?= __('Top Person In Charge') ?>: <?= h($topPicName); ?></div><div class="insight-text"><?= (int)$topPicTotal; ?> <?= __('tickets') ?></div></div></div>
            </div>
        </div>
    </div>

    <div class="report-card trend-card">
        <div class="report-card-head"><span><i class="bi bi-graph-up text-primary me-2"></i><?= __('Ticket Trend') ?> (<?= h(kpi_month_label($startDate)); ?>)</span><span class="text-muted small"><?= __('Daily') ?></span></div>
        <div class="report-card-body">
            <svg class="trend-svg" viewBox="0 0 100 100" preserveAspectRatio="none" role="img" aria-label="Ticket trend">
                <defs><linearGradient id="trendGradient" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#60a5fa" stop-opacity=".38"/><stop offset="1" stop-color="#60a5fa" stop-opacity="0"/></linearGradient></defs>
                <line class="trend-grid-line" x1="0" y1="25" x2="100" y2="25"></line><line class="trend-grid-line" x1="0" y1="50" x2="100" y2="50"></line><line class="trend-grid-line" x1="0" y1="75" x2="100" y2="75"></line>
                <polygon class="trend-area" points="<?= h($trendAreaPoints); ?>"></polygon>
                <polyline class="trend-line" points="<?= h($trendLinePoints); ?>"></polyline>
                <?php foreach($trendPoints as $pt): $xy=explode(',',$pt); ?><circle class="trend-point" cx="<?= h($xy[0]); ?>" cy="<?= h($xy[1]); ?>" r="1.1"></circle><?php endforeach; ?>
            </svg>
            <div class="trend-labels"><span><?= h($dailyLabels[0] ?? '') ?></span><span><?= h($dailyLabels[(int)floor(count($dailyLabels)/2)] ?? '') ?></span><span><?= h(end($dailyLabels) ?: '') ?></span></div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
