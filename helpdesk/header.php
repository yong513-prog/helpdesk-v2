<?php

if(session_status() === PHP_SESSION_NONE)
{
    session_start();
}

require_once __DIR__ . '/lang.php';
ob_start('hd_translate_buffer');


if(!isset($pdo))
{
    require_once __DIR__ . '/db.php';
}

if(file_exists(__DIR__ . '/remember_me.php'))
{
    require_once __DIR__ . '/remember_me.php';
    hd_restore_remembered_login($pdo);
}

$currentPage = basename($_SERVER['PHP_SELF']);

function active_menu($page, $currentPage)
{
    return $page == $currentPage ? 'active' : '';
}

function active_menu_group($pages, $currentPage)
{
    return in_array($currentPage, $pages) ? 'active' : '';
}


// Restore Remember Me login as early as possible for PWA/mobile direct entry.
if(empty($_SESSION['user_id']) && !empty($_COOKIE['wls_helpdesk_remember']))
{
    if(!isset($pdo))
    {
        require_once __DIR__ . '/db.php';
    }
}

$role = $_SESSION['role'] ?? 'staff';

if(isset($_SESSION['user_id']))
{
    if(!isset($pdo))
    {
        require_once __DIR__ . '/db.php';
    }

    require_once __DIR__ . '/module_permissions.php';
    require_once __DIR__ . '/ticket_status_options.php';
    require_once __DIR__ . '/notification_helper.php';
    require_once __DIR__ . '/ticket_auto_close.php';

    $permissionPageMap = module_permission_page_map();

    if(isset($permissionPageMap[$currentPage]))
    {
        require_module_permission($permissionPageMap[$currentPage]);
    }
}

$notificationUnreadCount = 0;

$sidebarCounts = [
    'open' => 0,
    'overdue' => 0,
    'assets' => 0,
    'announcements' => 0,
    'closed' => 0
];

if(isset($_SESSION['user_id']))
{
    try
    {
        if(function_exists('notification_unread_count'))
        {
            $notificationUnreadCount = notification_unread_count($pdo, (int)$_SESSION['user_id']);
        }

        if(!isset($pdo))
        {
            require_once __DIR__ . '/db.php';
        }

        if(file_exists(__DIR__ . '/access_control.php'))
        {
            require_once __DIR__ . '/access_control.php';
        }

        ticket_auto_close_solved_tickets($pdo, 5);

        $countWhereSql = " WHERE 1=1 ";
        $countWhereParams = [];
        $closedStatusNames = function_exists('ticket_status_closed_names') ? ticket_status_closed_names($pdo) : ['Solved','Closed'];
        $closedStatusPlaceholders = function_exists('ticket_status_sql_in_placeholders') ? ticket_status_sql_in_placeholders($closedStatusNames) : implode(',', array_fill(0, count($closedStatusNames), '?'));

        if(function_exists('apply_ticket_access_filter'))
        {
            apply_ticket_access_filter($countWhereSql, $countWhereParams);
        }

        $openStatusNames = function_exists('ticket_status_open_names') ? ticket_status_open_names($pdo) : [];
        $openStatusPlaceholders = function_exists('ticket_status_sql_in_placeholders') ? ticket_status_sql_in_placeholders($openStatusNames) : implode(',', array_fill(0, count($openStatusNames), '?'));

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets " . $countWhereSql . " AND status IN (" . $openStatusPlaceholders . ")");
        $stmt->execute(array_merge($countWhereParams, $openStatusNames));
        $sidebarCounts['open'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("\n            SELECT COUNT(*)\n            FROM tickets\n            " . $countWhereSql . "\n            AND due_date IS NOT NULL\n            AND due_date < NOW()\n            AND status NOT IN (" . $closedStatusPlaceholders . ")\n        ");
        $stmt->execute(array_merge($countWhereParams, $closedStatusNames));
        $sidebarCounts['overdue'] = (int)$stmt->fetchColumn();

        try
        {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM tickets
                " . $countWhereSql . "
                AND status IN (" . $closedStatusPlaceholders . ")
            ");
            $stmt->execute(array_merge($countWhereParams, $closedStatusNames));
            $sidebarCounts['closed'] = (int)$stmt->fetchColumn();
        }
        catch(Exception $e)
        {
            $sidebarCounts['closed'] = 0;
        }

        try
        {
            $sidebarCounts['assets'] = (int)$pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
        }
        catch(Exception $e)
        {
            $sidebarCounts['assets'] = 0;
        }

        try
        {
            $sidebarCounts['announcements'] = (int)$pdo->query("\n                SELECT COUNT(*)\n                FROM announcements\n                WHERE (start_date IS NULL OR start_date <= CURDATE())\n                AND (end_date IS NULL OR end_date >= CURDATE())\n            ")->fetchColumn();
        }
        catch(Exception $e)
        {
            $sidebarCounts['announcements'] = 0;
        }
    }
    catch(Exception $e)
    {
        $sidebarCounts = [
            'open' => 0,
            'overdue' => 0,
            'assets' => 0,
            'announcements' => 0,
            'closed' => 0
        ];
    }
}

$pageTitles = [
    'dashboard.php' => 'Dashboard',
    'create_ticket.php' => 'Ticket Management / Create Ticket',
    'ticket_list.php' => 'Ticket Management / Ticket List',
    'closed_tickets.php' => 'Ticket Management / Closed Tickets',
    'view_ticket.php' => 'Ticket Management / Ticket Details',
    'edit_ticket.php' => 'Ticket Management / Edit Ticket',
    'asset_list.php' => 'Asset Management / Asset List',
    'add_asset.php' => 'Asset Management / Add Asset',
    'edit_asset.php' => 'Asset Management / Edit Asset',
    'asset_history.php' => 'Asset Management / 资产历史',
    'knowledge_base.php' => 'Knowledge Base',
    'add_article.php' => 'Knowledge Base / Add Article',
    'edit_article.php' => 'Knowledge Base / Edit Article',
    'view_article.php' => 'Knowledge Base / View Article',
    'announcements.php' => 'Communication / Announcements',
    'add_announcement.php' => 'Communication / Add Announcement',
    'view_announcement.php' => 'View Announcement',
    'report_kpi.php' => 'Reports / KPI Report',
    'audit_logs.php' => 'Reports / Audit Logs',
    'administration.php' => 'Administration / Control Panel',
    'users.php' => 'Administration / Users',
    'assign_to_management.php' => 'Administration / Assign To Management',
    'pic_management.php' => 'Administration / PIC Management',
    'category_management.php' => 'Administration / Category Management',
    'kb_category_management.php' => 'Administration / Knowledge Category Management',
    'sla_management.php' => 'Administration / SLA Management',
    'branch_management.php' => 'Administration / Branch Management',
    'asset_type_management.php' => 'Administration / Asset Type Management',
    'ticket_status_management.php' => 'Administration / Ticket Status Management'
];

$topbarTitle = $pageTitles[$currentPage] ?? ucfirst(str_replace(['_', '.php'], [' ', ''], $currentPage));

?>
<!DOCTYPE html>
<html lang="en">

<head>
<meta name="google" content="notranslate">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>WLS ENTERPRISE SDN BHD - Helpdesk</title>

<link rel="icon" type="image/png" href="assets/logo.png">
<link rel="apple-touch-icon" href="assets/logo.png">
<link rel="manifest" href="manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="WLS Helpdesk">


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<script>
(function(){
    try{
        if(window.innerWidth > 768 && localStorage.getItem('sidebarCollapsed') === 'yes')
        {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    }catch(e){}
})();
</script>

<style>

:root{
    --sidebar-bg:#071b3a;
    --sidebar-bg2:#0b2b63;
    --primary:#2563eb;
    --soft-bg:#f4f7fb;
    --card-border:#e9eef7;
    --text-dark:#0f172a;
    --text-muted:#64748b;
}

*{
    box-sizing:border-box;
}

body{
    margin:0;
    background:var(--soft-bg);
    color:var(--text-dark);
    font-family:Inter,Segoe UI,Arial,sans-serif;
}

.app-shell{
    display:flex;
    min-height:100vh;
}

.sidebar{
    width:265px;
    min-height:100vh;
    max-height:100vh;
    position:fixed;
    left:0;
    top:0;
    bottom:0;
    padding:18px 14px;
    background:
        radial-gradient(circle at 30% 90%, rgba(37,99,235,.35), transparent 28%),
        linear-gradient(180deg,var(--sidebar-bg),#020617);
    color:#fff;
    z-index:1000;
    overflow-y:auto;
    overflow-x:hidden;
    scrollbar-width:thin;
    scrollbar-color:rgba(255,255,255,.18) transparent;
}

.sidebar::-webkit-scrollbar{
    width:6px;
}

.sidebar::-webkit-scrollbar-thumb{
    background:rgba(255,255,255,.18);
    border-radius:99px;
}

.sidebar-brand{
    display:block;
    text-align:center;
    margin-bottom:16px;
    padding:6px;
}

.company-logo{
    margin-bottom:16px;
}

.wls-logo{
    width:125px;
    max-width:100%;
    height:auto;
    display:block;
    margin:auto;
    background:white;
    padding:7px;
    border-radius:14px;
    box-shadow:0 10px 25px rgba(0,0,0,.20);
}

.sidebar-section{
    color:#93a4bd;
    text-transform:uppercase;
    font-size:10.5px;
    letter-spacing:.13em;
    padding:14px 12px 7px;
}

.sidebar-menu{
    display:flex;
    flex-direction:column;
    gap:6px;
}

.sidebar-link{
    display:flex;
    align-items:center;
    gap:12px;
    padding:11px 13px;
    color:#dbeafe;
    text-decoration:none;
    border-radius:14px;
    font-weight:650;
    transition:.18s ease;
    min-height:45px;
}

.sidebar-link i{
    font-size:18px;
    width:22px;
    text-align:center;
    flex:0 0 22px;
}

.sidebar-link .menu-text{
    flex:1;
    min-width:0;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.sidebar-link .menu-badge{
    margin-left:auto;
    font-size:11px;
    font-weight:800;
    padding:.28rem .48rem;
    border-radius:999px;
}

.sidebar-link:hover,
.sidebar-link.active{
    color:#fff;
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    box-shadow:0 12px 24px rgba(37,99,235,.25);
    transform:translateX(2px);
}

.sidebar-link.active .menu-badge,
.sidebar-link:hover .menu-badge{
    background:rgba(255,255,255,.22) !important;
    color:#fff !important;
}

.sidebar-stats{
    margin:18px 0 10px;
    padding:14px;
    border-radius:18px;
    background:rgba(255,255,255,.07);
    border:1px solid rgba(255,255,255,.08);
}

.sidebar-stats-title{
    color:#bfdbfe;
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.12em;
    margin-bottom:10px;
}

.sidebar-stat-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    color:#e0f2fe;
    font-size:13px;
    padding:6px 0;
    border-bottom:1px solid rgba(255,255,255,.07);
}

.sidebar-stat-row:last-child{
    border-bottom:0;
}

.sidebar-stat-row strong{
    color:#fff;
}

.sidebar-card{
    position:relative;
    margin-top:18px;
    padding:16px;
    border-radius:20px;
    background:rgba(37,99,235,.16);
    border:1px solid rgba(255,255,255,.08);
    box-shadow:inset 0 1px 0 rgba(255,255,255,.06);
}

.sidebar-card .icon{
    width:44px;
    height:44px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:16px;
    background:rgba(37,99,235,.35);
    font-size:21px;
    margin-bottom:10px;
}

.main-area{
    margin-left:265px;
    width:calc(100% - 265px);
    min-height:100vh;
}

.topbar{
    height:72px;
    background:#fff;
    border-bottom:1px solid #edf2f7;
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:0 28px;
    position:sticky;
    top:0;
    z-index:900;
}

.topbar-left{
    display:flex;
    align-items:center;
    gap:16px;
}

.topbar-title{
    font-weight:800;
    font-size:20px;
}

.topbar-user{
    display:flex;
    align-items:center;
    gap:12px;
}

.user-avatar{
    width:42px;
    height:42px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#e0ecff;
    color:#1d4ed8;
    font-size:20px;
}

.content-wrap{
    padding:24px 28px 34px;
}

.pro-card{
    background:#fff;
    border:1px solid var(--card-border);
    border-radius:18px;
    box-shadow:0 12px 30px rgba(15,23,42,.06);
}

.pro-card-header{
    padding:18px 20px;
    border-bottom:1px solid #edf2f7;
    display:flex;
    align-items:center;
    justify-content:space-between;
    font-weight:800;
}

.pro-card-body{
    padding:20px;
}

.table{
    margin-bottom:0;
}

.table thead th{
    background:#f8fafc !important;
    color:#334155;
    border-bottom:1px solid #e2e8f0;
    font-size:13px;
    text-transform:none;
}

.table tbody td{
    vertical-align:middle;
    color:#334155;
}

.table-hover tbody tr:hover{
    background:#f8fbff;
}

.btn{
    border-radius:10px;
    font-weight:600;
}

.badge{
    border-radius:10px;
    padding:.45em .7em;
}

/* Sidebar Collapse */
.sidebar,
.main-area,
.sidebar-link span,
.sidebar-brand,
.sidebar-card,
.sidebar-stats,
.topbar-title{
    transition:all .25s ease;
}

html.sidebar-collapsed .sidebar,
body.sidebar-collapsed .sidebar{
    width:86px;
    padding-left:12px;
    padding-right:12px;
}

html.sidebar-collapsed .main-area,
body.sidebar-collapsed .main-area{
    margin-left:86px;
    width:calc(100% - 86px);
}

html.sidebar-collapsed .sidebar-brand,
body.sidebar-collapsed .sidebar-brand{
    padding:4px 0;
    margin-bottom:14px;
}

html.sidebar-collapsed .wls-logo,
body.sidebar-collapsed .wls-logo{
    width:48px;
    padding:5px;
    border-radius:12px;
}

html.sidebar-collapsed .sidebar-link,
body.sidebar-collapsed .sidebar-link{
    justify-content:center;
    padding:13px 10px;
}

html.sidebar-collapsed .sidebar-link .menu-text,
html.sidebar-collapsed .sidebar-link .menu-badge,
html.sidebar-collapsed .sidebar-section,
html.sidebar-collapsed .sidebar-card,
html.sidebar-collapsed .sidebar-stats,
body.sidebar-collapsed .sidebar-link .menu-text,
body.sidebar-collapsed .sidebar-link .menu-badge,
body.sidebar-collapsed .sidebar-section,
body.sidebar-collapsed .sidebar-card,
body.sidebar-collapsed .sidebar-stats{
    display:none;
}

.sidebar-toggle{
    width:42px;
    height:42px;
    border:0;
    border-radius:14px;
    background:#eef4ff;
    color:#2563eb;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:22px;
    transition:.2s ease;
}

.sidebar-toggle:hover{
    background:#2563eb;
    color:#fff;
}


/* Sidebar Section Collapse (group title expand/collapse) */
.sidebar-section.section-toggle{
    cursor:pointer;
    user-select:none;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    border-radius:10px;
    transition:.18s ease;
}
.sidebar-section.section-toggle:hover{
    color:#e0f2fe;
    background:rgba(255,255,255,.06);
}
.sidebar-section .section-arrow{
    font-size:13px;
    opacity:.78;
    transition:transform .18s ease;
}
.sidebar-section.is-collapsed .section-arrow{
    transform:rotate(-90deg);
}
.sidebar-group-items{
    display:flex;
    flex-direction:column;
    gap:6px;
    margin:0;
}
.sidebar-group-items.is-collapsed{
    display:none;
}
html.sidebar-collapsed .sidebar-section.section-toggle,
body.sidebar-collapsed .sidebar-section.section-toggle{
    display:none;
}

@media(max-width:992px){
    .sidebar{
        position:relative;
        width:100%;
        min-height:auto;
        max-height:none;
        border-radius:0;
    }

    html.sidebar-collapsed .sidebar,
html.sidebar-collapsed .sidebar,
    body.sidebar-collapsed .sidebar{
        width:100%;
    }

    .sidebar-card{
        position:relative;
        left:auto;
        right:auto;
        bottom:auto;
        margin-top:16px;
    }

    .app-shell{
        flex-direction:column;
    }

    .main-area,
    html.sidebar-collapsed .main-area,
html.sidebar-collapsed .main-area,
    body.sidebar-collapsed .main-area{
        margin-left:0;
        width:100%;
    }

    .topbar{
        position:relative;
        padding:0 16px;
    }

    .content-wrap{
        padding:18px 16px 28px;
    }
}

.notification-bell{position:relative;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:12px;background:#f8fafc;border:1px solid #e5e7eb;color:#2563eb;text-decoration:none;margin-right:8px}.notification-bell:hover{background:#eff6ff;color:#1d4ed8}.notification-bell .badge{position:absolute;top:-7px;right:-7px;border-radius:999px;font-size:10px;padding:4px 6px}.notification-bell.has-unread{background:#eff6ff;border-color:#bfdbfe}

.notification-live-wrap{position:relative;display:inline-flex;align-items:center}
.notification-bell{cursor:pointer}
.notification-dropdown{display:none;position:absolute;right:0;top:48px;width:360px;max-width:calc(100vw - 24px);background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 18px 45px rgba(15,23,42,.18);z-index:2500;overflow:hidden}
.notification-dropdown.show{display:block}
.notification-dropdown-head{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #eef2f7;background:#f8fafc}
.notification-dropdown-list{max-height:420px;overflow-y:auto}
.notification-mini-item{display:flex;gap:10px;padding:12px 14px;border-bottom:1px solid #eef2f7;text-decoration:none;color:#0f172a;background:#fff}
.notification-mini-item:hover{background:#f8fafc;color:#0f172a}
.notification-mini-item.unread{background:#eff6ff}
.notification-mini-icon{width:36px;height:36px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#dbeafe;color:#2563eb;flex:0 0 auto}
.notification-mini-title{font-weight:900;font-size:13px;line-height:1.25}
.notification-mini-msg{font-size:12px;color:#64748b;margin-top:3px;white-space:pre-line;line-height:1.3}
.notification-mini-time{font-size:11px;color:#94a3b8;margin-top:4px}
.notification-empty{padding:18px;text-align:center;color:#64748b}
@media(max-width:768px){.notification-dropdown{right:-92px;width:330px}.notification-dropdown-list{max-height:70vh}}

/* Dashboard Home Button beside Notification */
.dashboard-home-btn{
    position:relative;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:38px;
    height:38px;
    border-radius:12px;
    background:#f8fafc;
    border:1px solid #e5e7eb;
    color:#2563eb;
    text-decoration:none;
    margin-right:8px;
    font-size:18px;
    transition:.18s ease;
}
.dashboard-home-btn:hover{
    background:#eff6ff;
    color:#1d4ed8;
    border-color:#bfdbfe;
    transform:translateY(-1px);
}
.dashboard-home-btn:focus{
    outline:2px solid rgba(37,99,235,.25);
    outline-offset:2px;
}
.dashboard-home-btn .dashboard-home-label{
    display:none;
}
@media(max-width:768px){
    .dashboard-home-btn{
        width:34px;
        height:34px;
        margin-right:4px;
        font-size:16px;
        border-radius:12px;
        flex:0 0 auto;
    }
}


.lang-switch{display:flex;border:1px solid #2563eb;border-radius:999px;overflow:hidden;background:#fff;margin-right:8px}
.lang-switch a{padding:5px 9px;font-size:12px;font-weight:800;text-decoration:none;color:#2563eb;border-right:1px solid #dbeafe}
.lang-switch a:last-child{border-right:0}
.lang-switch a.active{background:#2563eb;color:#fff}

/* Mobile / PWA Enhancement */
@media(max-width:768px){
    html,body{max-width:100%;overflow-x:hidden;}
    body{padding-bottom:76px;}
    .main-area{margin-left:0!important;width:100%!important;}
    .content-wrap{padding:14px 10px 86px!important;}
    .sidebar{transform:translateX(-105%);transition:.22s ease;z-index:1060;}
    html.sidebar-collapsed .sidebar,body.sidebar-collapsed .sidebar{transform:translateX(0);}
    .topbar{position:sticky;top:0;z-index:1030;background:#fff;padding:8px 10px!important;box-shadow:0 6px 18px rgba(15,23,42,.06);}
    .topbar-title{font-size:14px;max-width:42vw;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .topbar-user{gap:5px;}
    .lang-switch a{padding:4px 6px;font-size:10px;}
    .notification-bell{width:34px;height:34px;margin-right:4px;}
    .user-avatar{width:34px;height:34px;}
    .mobile-bottom-nav{display:flex!important;}
}
.mobile-bottom-nav{display:none;position:fixed;left:0;right:0;bottom:0;height:66px;background:#fff;border-top:1px solid #e5e7eb;box-shadow:0 -8px 20px rgba(15,23,42,.08);z-index:1050;align-items:center;justify-content:space-around;}
.mobile-bottom-nav a{flex:1;text-align:center;text-decoration:none;color:#64748b;font-size:11px;font-weight:800;padding:7px 4px;}
.mobile-bottom-nav a i{display:block;font-size:20px;margin-bottom:3px;}
.mobile-bottom-nav a.active{color:#2563eb;}


/* Mobile Top Blank Fix */
@media(max-width:768px){
    .app-shell{
        display:block!important;
        min-height:auto!important;
        height:auto!important;
    }

    .sidebar{
        position:fixed!important;
        top:0!important;
        left:0!important;
        bottom:0!important;
        width:280px!important;
        max-width:86vw!important;
        height:100vh!important;
        min-height:100vh!important;
        max-height:100vh!important;
        overflow-y:auto!important;
        border-radius:0!important;
        transform:translateX(-105%)!important;
        z-index:1060!important;
    }

    html.sidebar-collapsed .sidebar,
    body.sidebar-collapsed .sidebar{
        transform:translateX(0)!important;
        width:280px!important;
    }

    .main-area{
        display:block!important;
        margin-left:0!important;
        width:100%!important;
        min-height:auto!important;
    }

    .topbar{
        position:sticky!important;
        top:0!important;
        margin-top:0!important;
    }

    .content-wrap{
        margin-top:0!important;
        min-height:auto!important;
    }

    .sidebar-card{
        position:relative!important;
        left:auto!important;
        right:auto!important;
        bottom:auto!important;
    }
}


/* Mobile Menu Polish */
.mobile-sidebar-close{display:none;}

@media(max-width:768px){
    html,body{
        background:#f4f7fb!important;
        -webkit-text-size-adjust:100%;
    }

    .sidebar{
        width:82vw!important;
        max-width:330px!important;
        min-width:286px!important;
        padding:18px 14px 96px!important;
        background:
            radial-gradient(circle at 18% 5%, rgba(37,99,235,.38), transparent 34%),
            linear-gradient(180deg,#061a39 0%,#031126 100%)!important;
        box-shadow:20px 0 50px rgba(2,6,23,.34)!important;
        transform:translateX(-110%)!important;
    }

    html.sidebar-collapsed .sidebar,
    body.sidebar-collapsed .sidebar{
        transform:translateX(0)!important;
        width:82vw!important;
        max-width:330px!important;
        min-width:286px!important;
        padding:18px 14px 96px!important;
    }

    /* Force full menu text on mobile even when desktop collapsed state is saved */
    html.sidebar-collapsed .sidebar-link,
    body.sidebar-collapsed .sidebar-link{
        justify-content:flex-start!important;
        padding:12px 14px!important;
    }

    html.sidebar-collapsed .sidebar-link .menu-text,
    html.sidebar-collapsed .sidebar-link .menu-badge,
    html.sidebar-collapsed .sidebar-section,
    html.sidebar-collapsed .sidebar-card,
    html.sidebar-collapsed .sidebar-stats,
    body.sidebar-collapsed .sidebar-link .menu-text,
    body.sidebar-collapsed .sidebar-link .menu-badge,
    body.sidebar-collapsed .sidebar-section,
    body.sidebar-collapsed .sidebar-card,
    body.sidebar-collapsed .sidebar-stats{
        display:flex!important;
    }

    html.sidebar-collapsed .sidebar-link .menu-text,
    body.sidebar-collapsed .sidebar-link .menu-text{
        display:block!important;
    }

    html.sidebar-collapsed .wls-logo,
    body.sidebar-collapsed .wls-logo{
        width:86px!important;
        padding:7px!important;
        border-radius:18px!important;
    }

    .sidebar-brand{
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        margin:10px 42px 22px!important;
        padding:0!important;
    }

    .wls-logo{
        width:86px!important;
        border-radius:18px!important;
        box-shadow:0 14px 34px rgba(0,0,0,.28)!important;
    }

    .mobile-sidebar-close{
        display:flex!important;
        position:absolute;
        top:16px;
        right:14px;
        width:38px;
        height:38px;
        border:0;
        border-radius:14px;
        align-items:center;
        justify-content:center;
        color:#dbeafe;
        background:rgba(255,255,255,.08);
        backdrop-filter:blur(8px);
        font-size:17px;
        z-index:1070;
    }

    .mobile-sidebar-close:active{
        transform:scale(.96);
    }

    .sidebar-section{
        display:flex!important;
        align-items:center!important;
        justify-content:space-between!important;
        color:#8ea3c3!important;
        font-size:11px!important;
        letter-spacing:.10em!important;
        padding:16px 12px 8px!important;
        margin-top:4px!important;
    }

    .sidebar-link{
        min-height:52px!important;
        border-radius:16px!important;
        padding:12px 14px!important;
        gap:12px!important;
        color:#dbeafe!important;
        font-size:15px!important;
        font-weight:800!important;
    }

    .sidebar-link i{
        width:28px!important;
        flex:0 0 28px!important;
        font-size:20px!important;
    }

    .sidebar-link .menu-text{
        white-space:normal!important;
        overflow:visible!important;
        text-overflow:clip!important;
    }

    .sidebar-link.active{
        background:linear-gradient(135deg,#2f6df6,#1d4ed8)!important;
        color:#fff!important;
        box-shadow:0 14px 28px rgba(37,99,235,.32)!important;
        transform:none!important;
    }

    .sidebar-link:hover{
        transform:none!important;
    }

    .sidebar-link .menu-badge{
        display:inline-flex!important;
        align-items:center!important;
        justify-content:center!important;
        min-width:24px!important;
        height:24px!important;
        font-size:11px!important;
        margin-left:auto!important;
    }

    .sidebar-stats{
        display:block!important;
        border-radius:18px!important;
        margin:18px 0 10px!important;
    }

    .sidebar-card{
        display:block!important;
        margin-top:16px!important;
        border-radius:18px!important;
    }

    .mobile-sidebar-backdrop{
        display:none;
        position:fixed;
        inset:0;
        background:rgba(15,23,42,.42);
        backdrop-filter:blur(2px);
        z-index:1055;
    }

    html.sidebar-collapsed .mobile-sidebar-backdrop,
    body.sidebar-collapsed .mobile-sidebar-backdrop{
        display:block;
    }

    .topbar{
        height:auto!important;
        min-height:64px!important;
    }

    .sidebar-toggle{
        width:42px!important;
        height:42px!important;
        border-radius:14px!important;
    }

    .main-area{
        filter:none;
    }
}


/* Mobile Close Button Fix */
@media(max-width:768px){
    .mobile-sidebar-close{
        z-index:3000!important;
        pointer-events:auto!important;
        touch-action:manipulation!important;
        cursor:pointer!important;
        -webkit-tap-highlight-color:transparent!important;
    }

    .sidebar{
        z-index:2500!important;
        pointer-events:auto!important;
    }

    .mobile-sidebar-backdrop{
        z-index:2400!important;
        pointer-events:auto!important;
    }

    .main-area{
        position:relative!important;
        z-index:1!important;
    }

    html.sidebar-collapsed .main-area,
    body.sidebar-collapsed .main-area{
        pointer-events:none!important;
    }

    html.sidebar-collapsed .sidebar,
    body.sidebar-collapsed .sidebar{
        pointer-events:auto!important;
    }

    html.sidebar-collapsed .mobile-sidebar-backdrop,
    body.sidebar-collapsed .mobile-sidebar-backdrop{
        pointer-events:auto!important;
    }
}


/* Mobile Fixed Top Bar */
@media(max-width:768px){
    .topbar{
        position:fixed!important;
        top:0!important;
        left:0!important;
        right:0!important;
        width:100%!important;
        z-index:2300!important;
        min-height:64px!important;
        height:auto!important;
        background:rgba(255,255,255,.94)!important;
        backdrop-filter:blur(14px)!important;
        -webkit-backdrop-filter:blur(14px)!important;
        border-bottom:1px solid rgba(226,232,240,.9)!important;
        box-shadow:0 8px 24px rgba(15,23,42,.08)!important;
        padding:9px 10px!important;
    }

    .main-area{
        padding-top:76px!important;
    }

    .content-wrap{
        padding-top:14px!important;
    }

    .topbar-left{
        min-width:0!important;
        flex:1 1 auto!important;
    }

    .topbar-title{
        max-width:42vw!important;
        white-space:nowrap!important;
        overflow:hidden!important;
        text-overflow:ellipsis!important;
    }

    .topbar-user{
        flex:0 0 auto!important;
    }

    .lang-switch{
        max-width:184px!important;
        flex-shrink:0!important;
    }

    .lang-switch a{
        white-space:normal!important;
        line-height:1.1!important;
        text-align:center!important;
    }
}

</style>

<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0d6efd">
</head>

<body>
<script>if('serviceWorker' in navigator){navigator.serviceWorker.register('service-worker.js').catch(function(e){});}</script>

<div class="app-shell">

<?php if(isset($_SESSION['user_id'])): ?>

<aside class="sidebar">

    <button type="button" class="mobile-sidebar-close" id="mobileSidebarClose" aria-label="Close menu" onclick="document.documentElement.classList.remove('sidebar-collapsed');document.body.classList.remove('sidebar-collapsed');return false;"><i class="bi bi-x-lg"></i></button>

    <div class="sidebar-brand company-logo">
        <img
            src="assets/logo.png"
            alt="WLS Enterprise"
            class="wls-logo">
    </div>

    <div class="sidebar-menu">

        <?php $canDashboard = current_user_has_permission('dashboard'); ?>
        <?php $canCreateTicket = current_user_has_permission('create_ticket'); ?>
        <?php $canTicketList = current_user_has_permission('ticket_list'); ?>
        <?php $canAssetList = current_user_has_permission('asset_list'); ?>
        <?php $canAddAsset = current_user_has_permission('add_asset'); ?>
        <?php $canKnowledge = current_user_has_permission('knowledge_base'); ?>
        <?php $canAnnouncements = current_user_has_permission('announcements'); ?>
        <?php $canAddAnnouncement = current_user_has_permission('add_announcement'); ?>
        <?php $canReportKpi = current_user_has_permission('report_kpi'); ?>
        <?php $canAuditLogs = current_user_has_permission('audit_logs'); ?>
        <?php $canAdministration = current_user_has_permission('administration'); ?>
        <?php $canUsers = current_user_has_permission('users'); ?>
        <?php $canAssignToManagement = current_user_has_permission('assign_to_management'); ?>
        <?php $canPicManagement = current_user_has_permission('pic_management'); ?>
        <?php $canCategoryManagement = current_user_has_permission('category_management'); ?>
        <?php $canKbCategoryManagement = current_user_has_permission('kb_category_management'); ?>
        <?php $canSlaManagement = current_user_has_permission('sla_management'); ?>
        <?php $canBranchManagement = current_user_has_permission('branch_management'); ?>
        <?php $canAssetTypeManagement = current_user_has_permission('asset_type_management'); ?>
        <?php $canTicketStatusManagement = current_user_has_permission('ticket_status_management'); ?>

        <?php if($canDashboard): ?>
        <div class="sidebar-section">Main</div>

        <a class="sidebar-link <?= active_menu('dashboard.php', $currentPage); ?>" href="dashboard.php" title="Dashboard">
            <i class="bi bi-speedometer2"></i>
            <span class="menu-text">Dashboard</span>
        </a>
        <?php endif; ?>

        <?php if($canCreateTicket || $canTicketList): ?>
        <div class="sidebar-section">Ticket Management</div>

        <?php if($canCreateTicket): ?>
        <a class="sidebar-link <?= active_menu('create_ticket.php', $currentPage); ?>" href="create_ticket.php" title="Create Ticket">
            <i class="bi bi-plus-circle"></i>
            <span class="menu-text">Create Ticket</span>
        </a>
        <?php endif; ?>

        <?php if($canTicketList): ?>
        <a class="sidebar-link <?= active_menu_group(['ticket_list.php','view_ticket.php','edit_ticket.php'], $currentPage); ?>" href="ticket_list.php" title="Ticket List">
            <i class="bi bi-list-task"></i>
            <span class="menu-text">Ticket List</span>
            <?php if($sidebarCounts['open'] > 0): ?>
            <span class="badge bg-primary-subtle text-primary menu-badge"><?= $sidebarCounts['open']; ?></span>
            <?php endif; ?>
        </a>

        <?php if($sidebarCounts['overdue'] > 0): ?>
        <a class="sidebar-link" href="ticket_list.php?status=overdue" title="Overdue Tickets">
            <i class="bi bi-alarm"></i>
            <span class="menu-text">Overdue</span>
            <span class="badge bg-danger menu-badge"><?= $sidebarCounts['overdue']; ?></span>
        </a>
        <?php endif; ?>

        <a class="sidebar-link <?= active_menu('closed_tickets.php', $currentPage); ?>" href="closed_tickets.php" title="Closed Tickets">
            <i class="bi bi-archive"></i>
            <span class="menu-text">Closed Tickets</span>
            <?php if($sidebarCounts['closed'] > 0): ?>
            <span class="badge bg-success menu-badge"><?= $sidebarCounts['closed']; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if($canAssetList || $canAddAsset): ?>
        <div class="sidebar-section">Asset Management</div>

        <?php if($canAssetList): ?>
        <a class="sidebar-link <?= active_menu_group(['asset_list.php','edit_asset.php','asset_history.php'], $currentPage); ?>" href="asset_list.php" title="Asset List">
            <i class="bi bi-pc-display"></i>
            <span class="menu-text">Asset List</span>
            <?php if($sidebarCounts['assets'] > 0): ?>
            <span class="badge bg-light text-dark menu-badge"><?= $sidebarCounts['assets']; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if($canAddAsset): ?>
        <a class="sidebar-link <?= active_menu('add_asset.php', $currentPage); ?>" href="add_asset.php" title="Add Asset">
            <i class="bi bi-plus-square"></i>
            <span class="menu-text">Add Asset</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if($canKnowledge): ?>
        <div class="sidebar-section">Knowledge</div>

        <a class="sidebar-link <?= active_menu('knowledge_base.php', $currentPage); ?>" href="knowledge_base.php" title="Knowledge Base">
            <i class="bi bi-book"></i>
            <span class="menu-text">Knowledge Base</span>
        </a>
        <?php endif; ?>

        <?php if($canAnnouncements || $canAddAnnouncement): ?>
        <div class="sidebar-section">Communication</div>

        <?php if($canAnnouncements): ?>
        <a class="sidebar-link <?= active_menu('announcements.php', $currentPage); ?>" href="announcements.php" title="Announcements">
            <i class="bi bi-megaphone"></i>
            <span class="menu-text">Announcements</span>
            <?php if($sidebarCounts['announcements'] > 0): ?>
            <span class="badge bg-warning text-dark menu-badge"><?= $sidebarCounts['announcements']; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if($canAddAnnouncement): ?>
        <a class="sidebar-link <?= active_menu('add_announcement.php', $currentPage); ?>" href="add_announcement.php" title="Add Announcement">
            <i class="bi bi-megaphone-fill"></i>
            <span class="menu-text">Add Announcement</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if($canReportKpi || $canAuditLogs): ?>
        <div class="sidebar-section">Reports</div>

        <?php if($canReportKpi): ?>
        <a class="sidebar-link <?= active_menu('report_kpi.php', $currentPage); ?>" href="report_kpi.php" title="KPI Report">
            <i class="bi bi-bar-chart"></i>
            <span class="menu-text">KPI Report</span>
        </a>
        <?php endif; ?>

        <?php if($canAuditLogs): ?>
        <a class="sidebar-link <?= active_menu('audit_logs.php', $currentPage); ?>" href="audit_logs.php" title="Audit Logs">
            <i class="bi bi-clock-history"></i>
            <span class="menu-text">Audit Logs</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if($canAdministration || $canUsers || $canAssignToManagement || $canPicManagement || $canCategoryManagement || $canKbCategoryManagement || $canSlaManagement || $canBranchManagement || $canAssetTypeManagement): ?>
        <div class="sidebar-section">Administration</div>

        <?php if($canAdministration): ?>
        <a class="sidebar-link <?= active_menu('administration.php', $currentPage); ?>" href="administration.php" title="Administration Control Panel">
            <i class="bi bi-sliders"></i>
            <span class="menu-text">Administration</span>
        </a>
        <?php endif; ?>

        <?php if($canUsers): ?>
        <a class="sidebar-link <?= active_menu_group(['users.php','add_user.php','edit_user.php'], $currentPage); ?>" href="users.php" title="User Management">
            <i class="bi bi-people"></i>
            <span class="menu-text">User Management</span>
        </a>
        <?php endif; ?>

        <?php if($canAssignToManagement): ?>
        <a class="sidebar-link <?= active_menu('assign_to_management.php', $currentPage); ?>" href="assign_to_management.php" title="Assign To Management">
            <i class="bi bi-person-check"></i>
            <span class="menu-text">Assign To Management</span>
        </a>
        <?php endif; ?>

        <?php if($canPicManagement): ?>
        <a class="sidebar-link <?= active_menu('pic_management.php', $currentPage); ?>" href="pic_management.php" title="PIC Management">
            <i class="bi bi-person-badge"></i>
            <span class="menu-text">PIC Management</span>
        </a>
        <?php endif; ?>

        <?php if($canCategoryManagement): ?>
        <a class="sidebar-link <?= active_menu('category_management.php', $currentPage); ?>" href="category_management.php" title="Category Management">
            <i class="bi bi-tags"></i>
            <span class="menu-text">Category Management</span>
        </a>
        <?php endif; ?>

        <?php if($canKbCategoryManagement): ?>
        <a class="sidebar-link <?= active_menu('kb_category_management.php', $currentPage); ?>" href="kb_category_management.php" title="Knowledge Category Management">
            <i class="bi bi-journal-bookmark"></i>
            <span class="menu-text">Knowledge Category Management</span>
        </a>
        <?php endif; ?>

        <?php if($canSlaManagement): ?>
        <a class="sidebar-link <?= active_menu('sla_management.php', $currentPage); ?>" href="sla_management.php" title="SLA Management">
            <i class="bi bi-stopwatch"></i>
            <span class="menu-text">SLA Management</span>
        </a>
        <?php endif; ?>

        <?php if($canBranchManagement): ?>
        <a class="sidebar-link <?= active_menu('branch_management.php', $currentPage); ?>" href="branch_management.php" title="Branch Management">
            <i class="bi bi-shop"></i>
            <span class="menu-text">Branch Management</span>
        </a>
        <?php endif; ?>

        <?php if($canAssetTypeManagement): ?>
        <a class="sidebar-link <?= active_menu('asset_type_management.php', $currentPage); ?>" href="asset_type_management.php" title="Asset Type Management">
            <i class="bi bi-hdd-stack"></i>
            <span class="menu-text">Asset Type Management</span>
        </a>
        <?php endif; ?>

        <?php if($canTicketStatusManagement): ?>
        <a class="sidebar-link <?= active_menu('ticket_status_management.php', $currentPage); ?>" href="ticket_status_management.php" title="Ticket Status Management">
            <i class="bi bi-kanban"></i>
            <span class="menu-text">Ticket Status Management</span>
        </a>
        <?php endif; ?>

        <?php endif; ?>

        <div class="sidebar-section">Account</div>

        <a class="sidebar-link" href="logout.php" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
            <span class="menu-text">Logout</span>
        </a>

    </div>

    <div class="sidebar-stats">
        <div class="sidebar-stats-title">Live Summary</div>
        <div class="sidebar-stat-row">
            <span>Active Tickets</span>
            <strong><?= $sidebarCounts['open']; ?></strong>
        </div>
        <div class="sidebar-stat-row">
            <span>Overdue</span>
            <strong><?= $sidebarCounts['overdue']; ?></strong>
        </div>
        <div class="sidebar-stat-row">
            <span>Closed</span>
            <strong><?= $sidebarCounts['closed']; ?></strong>
        </div>
        <div class="sidebar-stat-row">
            <span>Assets</span>
            <strong><?= $sidebarCounts['assets']; ?></strong>
        </div>
    </div>

    <div class="sidebar-card">
        <div class="icon">
            <i class="bi bi-rocket-takeoff"></i>
        </div>

        <h6 class="mb-1">Helpdesk System</h6>
        <small class="text-white-50">WLS internal support portal.</small>
    </div>

</aside>

<div class="mobile-sidebar-backdrop" id="mobileSidebarBackdrop"></div>

<div class="main-area">

    <div class="topbar">
        <div class="topbar-left">
            <button type="button" class="sidebar-toggle" id="sidebarToggle" title="Toggle Menu">
                <i class="bi bi-grid-3x3-gap"></i>
            </button>

            <div class="topbar-title">
                <?= htmlspecialchars(__($topbarTitle)); ?>
            </div>
        </div>

        <div class="topbar-user">
            <a href="dashboard.php" class="dashboard-home-btn" title="Dashboard" aria-label="Dashboard">
                <i class="bi bi-house-fill"></i>
                <span class="dashboard-home-label">Dashboard</span>
            </a>
            <div class="notification-live-wrap">
    <button type="button" id="notificationBellBtn" class="notification-bell <?= ($notificationUnreadCount ?? 0) > 0 ? 'has-unread' : ''; ?>" title="<?= htmlspecialchars(__('Notifications'), ENT_QUOTES, 'UTF-8') ?>">
        <i class="bi bi-bell-fill"></i>
        <span id="notificationBellCount" class="badge bg-danger" style="<?= (($notificationUnreadCount ?? 0) > 0) ? '' : 'display:none;'; ?>"><?= (int)($notificationUnreadCount ?? 0); ?></span>
    </button>
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-dropdown-head">
            <strong><i class="bi bi-bell me-1"></i> <?= htmlspecialchars(__('Notifications')) ?></strong>
            <a href="notifications.php" class="small"><?= htmlspecialchars(__('View All')) ?></a>
        </div>
        <div id="notificationDropdownList" class="notification-dropdown-list">
            <div class="notification-empty"><?= htmlspecialchars(__('Loading...')) ?></div>
        </div>
    </div>
</div>
<button id="langToggleMobile" class="btn btn-sm btn-outline-primary d-md-none">🌐</button><div class="lang-switch" id="langSwitchBox" title="Language">
                <a class="<?= hd_lang()==='en' ? 'active' : ''; ?>" href="<?= hd_lang_url('en'); ?>">English</a>
                <a class="<?= hd_lang()==='ms' ? 'active' : ''; ?>" href="<?= hd_lang_url('ms'); ?>">Bahasa Melayu</a>
                <a class="<?= hd_lang()==='zh' ? 'active' : ''; ?>" href="<?= hd_lang_url('zh'); ?>">中文</a>
            </div>
            <div class="text-end">
                <div class="fw-bold">
                    <?= htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                </div>
                <small class="text-muted">
                    <?= htmlspecialchars(ucfirst($role)); ?>
                </small>
            </div>

            <div class="user-avatar">
                <i class="bi bi-person-fill"></i>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const toggle = document.getElementById('sidebarToggle');
    const icon = toggle ? toggle.querySelector('i') : null;

    function applySidebarState(collapsed)
    {
        document.documentElement.classList.toggle('sidebar-collapsed', collapsed);
        document.body.classList.toggle('sidebar-collapsed', collapsed);

        if(icon)
        {
            icon.className = collapsed ? 'bi bi-layout-sidebar-inset' : 'bi bi-grid-3x3-gap';
        }
    }

    let savedCollapsed = false;

    try
    {
        savedCollapsed = (window.innerWidth > 768) && localStorage.getItem('sidebarCollapsed') === 'yes';
    }
    catch(e)
    {
        savedCollapsed = false;
    }

    applySidebarState(savedCollapsed);

    if(toggle)
    {
        toggle.addEventListener('click', function(e){
            e.preventDefault();
            const collapsed = !document.documentElement.classList.contains('sidebar-collapsed');
            applySidebarState(collapsed);

            try
            {
                if(window.innerWidth > 768)
                {
                    localStorage.setItem('sidebarCollapsed', collapsed ? 'yes' : 'no');
                }
            }
            catch(err){}
        });
    }

    /* Section title collapse/expand + keep sidebar scroll position */
    const sidebar = document.querySelector('.sidebar');
    const sidebarMenu = document.querySelector('.sidebar-menu');

    if(sidebar && sidebarMenu)
    {
        const sections = Array.from(sidebarMenu.querySelectorAll('.sidebar-section'));

        sections.forEach(function(section, index){
            const titleText = (section.textContent || ('section_' + index)).trim();
            const key = 'sidebarSectionOpen_' + titleText.toLowerCase().replace(/[^a-z0-9]+/g, '_');

            section.classList.add('section-toggle');
            section.setAttribute('role', 'button');
            section.setAttribute('tabindex', '0');

            if(!section.querySelector('.section-arrow'))
            {
                const arrow = document.createElement('i');
                arrow.className = 'bi bi-chevron-down section-arrow';
                section.appendChild(arrow);
            }

            const group = document.createElement('div');
            group.className = 'sidebar-group-items';
            section.parentNode.insertBefore(group, section.nextSibling);

            let node = group.nextElementSibling;
            while(node && !node.classList.contains('sidebar-section'))
            {
                const next = node.nextElementSibling;
                group.appendChild(node);
                node = next;
            }

            const hasActive = !!group.querySelector('.sidebar-link.active');
            let openState = hasActive;

            try
            {
                const saved = localStorage.getItem(key);
                if(saved === 'open') openState = true;
                if(saved === 'closed' && !hasActive) openState = false;
            }
            catch(e){}

            function setGroup(open)
            {
                group.classList.toggle('is-collapsed', !open);
                section.classList.toggle('is-collapsed', !open);
                try{ localStorage.setItem(key, open ? 'open' : 'closed'); }catch(e){}
            }

            setGroup(openState);

            section.addEventListener('click', function(){
                setGroup(group.classList.contains('is-collapsed'));
            });

            section.addEventListener('keydown', function(e){
                if(e.key === 'Enter' || e.key === ' ')
                {
                    e.preventDefault();
                    setGroup(group.classList.contains('is-collapsed'));
                }
            });
        });

        requestAnimationFrame(function(){
            try
            {
                const savedScroll = parseInt(localStorage.getItem('helpdeskSidebarScrollTop') || '0', 10);
                if(savedScroll > 0) sidebar.scrollTop = savedScroll;
            }
            catch(e){}
        });

        let scrollTimer = null;
        sidebar.addEventListener('scroll', function(){
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(function(){
                try{ localStorage.setItem('helpdeskSidebarScrollTop', sidebar.scrollTop); }catch(e){}
            }, 120);
        });

        sidebar.querySelectorAll('a.sidebar-link').forEach(function(link){
            link.addEventListener('click', function(){
                try{ localStorage.setItem('helpdeskSidebarScrollTop', sidebar.scrollTop); }catch(e){}
            });
        });
    }
});
</script>


<script>
(function(){
    function closeMobileSidebar(){
        document.documentElement.classList.remove('sidebar-collapsed');
        document.body.classList.remove('sidebar-collapsed');
        try{
            if(window.innerWidth <= 768){
                localStorage.setItem('sidebarCollapsed','no');
            }
        }catch(e){}
    }

    document.addEventListener('click', function(e){
        var closeBtn = e.target.closest ? e.target.closest('#mobileSidebarClose') : null;
        var backdrop = e.target.closest ? e.target.closest('#mobileSidebarBackdrop') : null;

        if(closeBtn || backdrop){
            e.preventDefault();
            e.stopPropagation();
            closeMobileSidebar();
            return false;
        }
    }, true);

    document.addEventListener('touchend', function(e){
        var closeBtn = e.target.closest ? e.target.closest('#mobileSidebarClose') : null;
        var backdrop = e.target.closest ? e.target.closest('#mobileSidebarBackdrop') : null;

        if(closeBtn || backdrop){
            e.preventDefault();
            e.stopPropagation();
            closeMobileSidebar();
            return false;
        }
    }, {capture:true, passive:false});
})();
</script>


<script>
(function(){
    const bellBtn = document.getElementById('notificationBellBtn');
    const dropdown = document.getElementById('notificationDropdown');
    const countEl = document.getElementById('notificationBellCount');
    const listEl = document.getElementById('notificationDropdownList');

    function esc(s){return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}

    function renderNotifications(items){
        if(!listEl) return;
        if(!items || items.length === 0){
            listEl.innerHTML = '<div class="notification-empty"><i class="bi bi-check-circle text-success"></i><br><?= addslashes(htmlspecialchars(__('No new notifications'), ENT_QUOTES, 'UTF-8')) ?></div>';
            return;
        }
        listEl.innerHTML = items.map(n => {
            const unread = String(n.is_read) === '0' ? ' unread' : '';
            const url = n.url || 'notifications.php';
            return `<a class="notification-mini-item${unread}" href="notifications.php?read=${encodeURIComponent(n.id)}">
                <div class="notification-mini-icon"><i class="bi ${esc(n.icon || 'bi-bell')}"></i></div>
                <div style="min-width:0">
                    <div class="notification-mini-title">${esc(n.title)}</div>
                    <div class="notification-mini-msg">${esc(n.message || '')}</div>
                    <div class="notification-mini-time">${esc(n.created_at || '')}</div>
                </div>
            </a>`;
        }).join('');
    }

    function setCount(count){
        count = parseInt(count || 0, 10);
        if(!countEl || !bellBtn) return;
        countEl.textContent = count;
        countEl.style.display = count > 0 ? '' : 'none';
        bellBtn.classList.toggle('has-unread', count > 0);
    }

    async function loadNotifications(){
        try{
            const res = await fetch('notification_live_api.php', {cache:'no-store'});
            if(!res.ok) return;
            const data = await res.json();
            setCount(data.unread || 0);
            renderNotifications(data.latest || []);
        }catch(e){}
    }

    async function loadDashboardCounts(){
        if(!document.querySelector('[data-live-count]')) return;
        try{
            const res = await fetch('dashboard_live_api.php', {cache:'no-store'});
            if(!res.ok) return;
            const data = await res.json();
            Object.keys(data.counts || {}).forEach(key => {
                document.querySelectorAll('[data-live-count="'+key+'"]').forEach(el => el.textContent = data.counts[key]);
            });
        }catch(e){}
    }

    if(bellBtn && dropdown){
        bellBtn.addEventListener('click', function(e){
            e.preventDefault();
            dropdown.classList.toggle('show');
            loadNotifications();
        });
        document.addEventListener('click', function(e){
            if(!dropdown.contains(e.target) && !bellBtn.contains(e.target)){
                dropdown.classList.remove('show');
            }
        });
    }

    loadNotifications();
    loadDashboardCounts();
    setInterval(loadNotifications, 30000);
    setInterval(loadDashboardCounts, 30000);
})();
</script>

    <main class="content-wrap">

<?php else: ?>

<div class="container mt-4">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark rounded">
<div class="container-fluid">

<a class="navbar-brand" href="login.php">
Helpdesk
</a>

<div class="d-flex flex-wrap gap-2">
<a class="btn btn-light" href="login.php">Login</a>
</div>

</div>
</nav>

<br>

<?php endif; ?>

<style>@media(max-width:768px){#langSwitchBox{display:none;position:absolute;top:60px;right:70px;background:#fff;padding:6px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.15)}#langSwitchBox.open{display:flex!important;}}</style><script>document.addEventListener('DOMContentLoaded',function(){var b=document.getElementById('langToggleMobile');var x=document.getElementById('langSwitchBox');if(b&&x){b.onclick=function(){x.classList.toggle('open')}}});</script>