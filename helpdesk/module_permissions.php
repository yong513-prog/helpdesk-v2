<?php
/*
|--------------------------------------------------------------------------
| Role Based Module + Action Permission Helper
|--------------------------------------------------------------------------
| Only 3 roles are supported: admin, head, staff.
| Admin always has full access. Head/Staff permissions are controlled globally
| from Administration by checkbox. Editing one role affects all users in that role.
*/

if(!function_exists('module_permission_list'))
{
    function module_permission_list()
    {
        return [
            'administration' => ['label'=>'Administration','description'=>'Central administration dashboard and role permission checkbox matrix.','icon'=>'bi-sliders','default_staff'=>false,'default_head'=>false],
            'dashboard' => ['label'=>'Dashboard','description'=>'View main dashboard and live summary.','icon'=>'bi-speedometer2','default_staff'=>true,'default_head'=>true],
            'create_ticket' => ['label'=>'Create Ticket','description'=>'Create new support tickets.','icon'=>'bi-plus-circle','default_staff'=>true,'default_head'=>true],
            'ticket_list' => ['label'=>'Ticket List','description'=>'View tickets allowed by ticket visibility rule.','icon'=>'bi-list-task','default_staff'=>true,'default_head'=>true],
            'asset_list' => ['label'=>'Asset List','description'=>'View asset list and asset history.','icon'=>'bi-pc-display','default_staff'=>false,'default_head'=>true],
            'add_asset' => ['label'=>'Add Asset','description'=>'Create new asset records.','icon'=>'bi-plus-square','default_staff'=>false,'default_head'=>false],
            'knowledge_base' => ['label'=>'Knowledge Base','description'=>'View/add/edit knowledge base articles based on action permission.','icon'=>'bi-book','default_staff'=>true,'default_head'=>true],
            'announcements' => ['label'=>'Announcements','description'=>'View company announcements and read status.','icon'=>'bi-megaphone','default_staff'=>true,'default_head'=>true],
            'add_announcement' => ['label'=>'Add Announcement','description'=>'Create company announcements.','icon'=>'bi-megaphone-fill','default_staff'=>false,'default_head'=>false],
            'report_kpi' => ['label'=>'KPI Report','description'=>'View monthly KPI reports.','icon'=>'bi-bar-chart','default_staff'=>false,'default_head'=>true],
            'audit_logs' => ['label'=>'Audit Logs','description'=>'View system audit logs.','icon'=>'bi-clock-history','default_staff'=>false,'default_head'=>false],
            'users' => ['label'=>'User Management','description'=>'Create, edit, disable and manage users.','icon'=>'bi-people','default_staff'=>false,'default_head'=>false],
            'assign_to_management' => ['label'=>'Assign To Management','description'=>'Maintain Assign To list / ticket assignees.','icon'=>'bi-person-check','default_staff'=>false,'default_head'=>false],
            'pic_management' => ['label'=>'PIC Management','description'=>'Maintain Person In Charge list.','icon'=>'bi-person-lines-fill','default_staff'=>false,'default_head'=>false],
            'category_management' => ['label'=>'Category Management','description'=>'Maintain ticket category master data.','icon'=>'bi-tags','default_staff'=>false,'default_head'=>false],
            'kb_category_management' => ['label'=>'Knowledge Category Management','description'=>'Maintain Knowledge Base category master data.','icon'=>'bi-journal-bookmark','default_staff'=>false,'default_head'=>false],
            'sla_management' => ['label'=>'SLA Management','description'=>'Maintain SLA rules and due date settings.','icon'=>'bi-hourglass-split','default_staff'=>false,'default_head'=>false],
            'branch_management' => ['label'=>'Branch Management','description'=>'Maintain branch master data.','icon'=>'bi-building','default_staff'=>false,'default_head'=>false],
            'asset_type_management' => ['label'=>'Asset Type Management','description'=>'Maintain asset type master data for Add/Edit Asset.','icon'=>'bi-hdd-stack','default_staff'=>false,'default_head'=>false],
            'ticket_status_management' => ['label'=>'Ticket Status Management','description'=>'Maintain ticket status list, color and closed/archive behaviour.','icon'=>'bi-kanban','default_staff'=>false,'default_head'=>false]
        ];
    }
}

if(!function_exists('module_permission_page_map'))
{
    function module_permission_page_map()
    {
        return [
            'administration.php'=>'administration','dashboard.php'=>'dashboard','create_ticket.php'=>'create_ticket',
            'ticket_list.php'=>'ticket_list','closed_tickets.php'=>'ticket_list','view_ticket.php'=>'ticket_list','edit_ticket.php'=>'ticket_list','ticket_history.php'=>'ticket_list','assign_ticket.php'=>'ticket_list','update_status.php'=>'ticket_list','reply_ticket.php'=>'ticket_list','delete_ticket.php'=>'ticket_list','export_tickets.php'=>'ticket_list',
            'asset_list.php'=>'asset_list','asset_history.php'=>'asset_list','edit_asset.php'=>'asset_list','delete_asset.php'=>'asset_list','add_asset.php'=>'add_asset',
            'knowledge_base.php'=>'knowledge_base','view_article.php'=>'knowledge_base','add_article.php'=>'knowledge_base','edit_article.php'=>'knowledge_base','delete_article.php'=>'knowledge_base',
            'announcements.php'=>'announcements','view_announcement.php'=>'announcements','announcement_read_report.php'=>'announcements','mark_announcement_read.php'=>'announcements','add_announcement.php'=>'add_announcement','delete_announcement.php'=>'add_announcement',
            'report_kpi.php'=>'report_kpi','export_report.php'=>'report_kpi','audit_logs.php'=>'audit_logs','export_audit.php'=>'audit_logs',
            'users.php'=>'users','add_user.php'=>'users','edit_user.php'=>'users','delete_user.php'=>'users','toggle_user_status.php'=>'users','system_check.php'=>'users',
            'assign_to_management.php'=>'assign_to_management','pic_management.php'=>'pic_management','category_management.php'=>'category_management','kb_category_management.php'=>'kb_category_management','sla_management.php'=>'sla_management','branch_management.php'=>'branch_management','asset_type_management.php'=>'asset_type_management','ticket_status_management.php'=>'ticket_status_management'
        ];
    }
}

if(!function_exists('action_permission_list'))
{
    function action_permission_list()
    {
        return [
            'show_in_assign_to' => ['label'=>'Show In Assign To','description'=>'User appears in Assign To dropdown.','icon'=>'bi-person-check','default_staff'=>false,'default_head'=>true],
            'assign_ticket' => ['label'=>'指派工单','description'=>'Can assign ticket to another user.','icon'=>'bi-person-plus','default_staff'=>false,'default_head'=>true],
            'change_status' => ['label'=>'Change Ticket Status','description'=>'Can update ticket status.','icon'=>'bi-arrow-repeat','default_staff'=>false,'default_head'=>true],
            'reply_ticket' => ['label'=>'Reply Ticket','description'=>'Can reply to tickets and upload reply attachments.','icon'=>'bi-reply-all','default_staff'=>true,'default_head'=>true],
            'edit_ticket' => ['label'=>'Edit Ticket','description'=>'Can edit ticket information.','icon'=>'bi-pencil-square','default_staff'=>false,'default_head'=>true],
            'delete_ticket' => ['label'=>'Delete Ticket','description'=>'Can delete tickets.','icon'=>'bi-trash','default_staff'=>false,'default_head'=>false],
            'export_ticket' => ['label'=>'Export Ticket','description'=>'Can export ticket CSV/report.','icon'=>'bi-download','default_staff'=>false,'default_head'=>false],
            'manage_asset' => ['label'=>'Manage Asset','description'=>'Can add/edit/delete asset records.','icon'=>'bi-pc-display','default_staff'=>false,'default_head'=>false],
            'select_asset_in_ticket' => ['label'=>'Select Asset In Ticket','description'=>'Can select Asset / Equipment when creating or editing tickets.','icon'=>'bi-link-45deg','default_staff'=>true,'default_head'=>true],
            'manage_announcement' => ['label'=>'Manage Announcement','description'=>'Can add/delete announcements and view read report.','icon'=>'bi-megaphone-fill','default_staff'=>false,'default_head'=>false],
            'manage_kb' => ['label'=>'Manage Knowledge Base','description'=>'Can add/edit/delete knowledge base articles and attachments.','icon'=>'bi-bookmark-plus','default_staff'=>false,'default_head'=>false],
            'manage_user' => ['label'=>'Manage Users','description'=>'Can create, edit, disable and delete users.','icon'=>'bi-people','default_staff'=>false,'default_head'=>false],
            'export_audit' => ['label'=>'Export Audit Log','description'=>'Can export audit log CSV.','icon'=>'bi-file-earmark-arrow-down','default_staff'=>false,'default_head'=>false],
            'print_report' => ['label'=>'Print Report','description'=>'Can use print buttons on reports/detail pages.','icon'=>'bi-printer','default_staff'=>false,'default_head'=>true]
        ];
    }
}

if(!function_exists('normalize_permission_role'))
{
    function normalize_permission_role($role)
    {
        $role = strtolower(trim((string)$role));
        if(in_array($role, ['administrator','admin'], true)) return 'admin';
        if($role === 'head') return 'head';
        return 'staff';
    }
}

if(!function_exists('ensure_role_permissions_table'))
{
    function ensure_role_permissions_table(PDO $pdo)
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (
            id INT NOT NULL AUTO_INCREMENT,
            role_name VARCHAR(20) NOT NULL,
            permission_type ENUM('module','action') NOT NULL,
            permission_key VARCHAR(100) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_role_permission (role_name, permission_type, permission_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $count = (int)$pdo->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();
        if($count === 0) {
            seed_default_role_permissions($pdo);
        } else {
            seed_missing_default_role_permissions($pdo);
        }
    }
}

if(!function_exists('default_permissions_for_role'))
{
    function default_permissions_for_role(string $role)
    {
        $role = normalize_permission_role($role);
        if($role == 'admin') return array_keys(module_permission_list());
        $field = ($role == 'head') ? 'default_head' : 'default_staff';
        $defaults = [];
        foreach(module_permission_list() as $key => $info) if(!empty($info[$field])) $defaults[] = $key;
        return $defaults;
    }
}

if(!function_exists('default_action_permissions_for_role'))
{
    function default_action_permissions_for_role(string $role)
    {
        $role = normalize_permission_role($role);
        if($role == 'admin') return array_keys(action_permission_list());
        $field = ($role == 'head') ? 'default_head' : 'default_staff';
        $defaults = [];
        foreach(action_permission_list() as $key => $info) if(!empty($info[$field])) $defaults[] = $key;
        return $defaults;
    }
}

if(!function_exists('seed_default_role_permissions'))
{
    function seed_default_role_permissions(PDO $pdo)
    {
        $stmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_name, permission_type, permission_key) VALUES (?,?,?)");
        foreach(['admin','head','staff'] as $role) {
            foreach(default_permissions_for_role($role) as $p) $stmt->execute([$role,'module',$p]);
            foreach(default_action_permissions_for_role($role) as $p) $stmt->execute([$role,'action',$p]);
        }
    }
}



if(!function_exists('seed_missing_default_role_permissions'))
{
    /**
     * Safe incremental seeding for Role Permission Matrix.
     * - Does NOT reset existing Head/Staff checkbox selections.
     * - Keeps Admin with full module/action permissions.
     * - If a role has no saved rows yet, seed its default permissions once.
     */
    function seed_missing_default_role_permissions(PDO $pdo)
    {
        $stmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_name, permission_type, permission_key) VALUES (?,?,?)");

        // Admin must always have every current permission key.
        foreach(array_keys(module_permission_list()) as $p) $stmt->execute(['admin','module',$p]);
        foreach(array_keys(action_permission_list()) as $p) $stmt->execute(['admin','action',$p]);

        // For Head/Staff, only seed defaults if that role/type has no records yet.
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_name=? AND permission_type=?");
        foreach(['head','staff'] as $role) {
            foreach(['module','action'] as $type) {
                $countStmt->execute([$role,$type]);
                $exists = (int)$countStmt->fetchColumn();
                if($exists === 0) {
                    $defaults = ($type === 'module') ? default_permissions_for_role($role) : default_action_permissions_for_role($role);
                    foreach($defaults as $p) $stmt->execute([$role,$type,$p]);
                }
            }
        }
    }
}

if(!function_exists('get_role_permissions'))
{
    function get_role_permissions(PDO $pdo, string $role, string $type)
    {
        $role = normalize_permission_role($role);
        if($role === 'admin') return $type === 'module' ? array_keys(module_permission_list()) : array_keys(action_permission_list());
        try {
            ensure_role_permissions_table($pdo);
            $stmt = $pdo->prepare("SELECT permission_key FROM role_permissions WHERE role_name=? AND permission_type=? ORDER BY permission_key");
            $stmt->execute([$role,$type]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch(Exception $e) {
            return $type === 'module' ? default_permissions_for_role($role) : default_action_permissions_for_role($role);
        }
    }
}

if(!function_exists('save_role_permissions'))
{
    function save_role_permissions(PDO $pdo, string $role, string $type, array $permissions)
    {
        $role = normalize_permission_role($role);
        if($role === 'admin') return;
        $valid = $type === 'module' ? array_keys(module_permission_list()) : array_keys(action_permission_list());
        $permissions = array_values(array_unique(array_intersect($permissions, $valid)));
        ensure_role_permissions_table($pdo);
        $pdo->prepare("DELETE FROM role_permissions WHERE role_name=? AND permission_type=?")->execute([$role,$type]);
        $stmt = $pdo->prepare("INSERT INTO role_permissions (role_name, permission_type, permission_key) VALUES (?,?,?)");
        foreach($permissions as $p) $stmt->execute([$role,$type,$p]);
    }
}

if(!function_exists('get_user_permissions'))
{
    function get_user_permissions(PDO $pdo, int $userId)
    {
        try { $stmt = $pdo->prepare("SELECT permission_key FROM user_permissions WHERE user_id = ?"); $stmt->execute([$userId]); return $stmt->fetchAll(PDO::FETCH_COLUMN); }
        catch(Exception $e) { return []; }
    }
}

if(!function_exists('save_user_permissions'))
{
    function save_user_permissions(PDO $pdo, int $userId, array $permissions)
    {
        $valid = array_keys(module_permission_list());
        $permissions = array_values(array_unique(array_intersect($permissions, $valid)));
        $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$userId]);
        if(count($permissions) == 0) return;
        $stmt = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_key) VALUES (?, ?)");
        foreach($permissions as $permission) $stmt->execute([$userId, $permission]);
    }
}

if(!function_exists('get_user_action_permissions'))
{
    function get_user_action_permissions(PDO $pdo, int $userId)
    {
        try { $stmt = $pdo->prepare("SELECT permission_key FROM user_action_permissions WHERE user_id = ?"); $stmt->execute([$userId]); return $stmt->fetchAll(PDO::FETCH_COLUMN); }
        catch(Exception $e) { return []; }
    }
}

if(!function_exists('save_user_action_permissions'))
{
    function save_user_action_permissions(PDO $pdo, int $userId, array $permissions)
    {
        $valid = array_keys(action_permission_list());
        $permissions = array_values(array_unique(array_intersect($permissions, $valid)));
        $pdo->prepare("DELETE FROM user_action_permissions WHERE user_id = ?")->execute([$userId]);
        if(count($permissions) == 0) return;
        $stmt = $pdo->prepare("INSERT INTO user_action_permissions (user_id, permission_key) VALUES (?, ?)");
        foreach($permissions as $permission) $stmt->execute([$userId, $permission]);
    }
}

if(!function_exists('current_user_has_permission'))
{
    function current_user_has_permission(string $permission)
    {
        $role = function_exists('normalize_role') ? normalize_role($_SESSION['role'] ?? '') : normalize_permission_role($_SESSION['role'] ?? 'staff');
        if($role === 'admin') return true;
        if(!isset($_SESSION['user_id'])) return false;
        global $pdo;
        if(!isset($pdo)) require_once __DIR__ . '/db.php';
        return in_array($permission, get_role_permissions($pdo, $role, 'module'), true);
    }
}

if(!function_exists('require_module_permission'))
{
    function require_module_permission(string $permission)
    {
        if(current_user_has_permission($permission)) return true;
        http_response_code(403); die("Access Denied");
    }
}

if(!function_exists('require_module_permission_for_current_page'))
{
    function require_module_permission_for_current_page()
    {
        $page = basename($_SERVER['PHP_SELF']);
        $map = module_permission_page_map();
        if(!isset($map[$page])) return true;
        return require_module_permission($map[$page]);
    }
}

if(!function_exists('has_action_permission'))
{
    function has_action_permission(string $permission)
    {
        $role = function_exists('normalize_role') ? normalize_role($_SESSION['role'] ?? '') : normalize_permission_role($_SESSION['role'] ?? 'staff');
        if($role === 'admin') return true;
        if(!isset($_SESSION['user_id'])) return false;
        global $pdo;
        if(!isset($pdo)) require_once __DIR__ . '/db.php';
        return in_array($permission, get_role_permissions($pdo, $role, 'action'), true);
    }
}

if(!function_exists('require_action_permission'))
{
    function require_action_permission(string $permission)
    {
        if(has_action_permission($permission)) return true;
        http_response_code(403); die("Access Denied");
    }
}
?>
