<?php
/*
|--------------------------------------------------------------------------
| User Privilege Matrix Helper
|--------------------------------------------------------------------------
| Provides ARMS-style privilege rows x branch columns for User Management.
| The matrix stores branch-level privilege ticks, while still syncing back
| to existing user_permissions and user_action_permissions for compatibility.
*/

if(!function_exists('um_branch_list'))
{
    function um_branch_list()
    {
        return ['HQ','KB','KC','KJ','KK','KL','KR','KS','ML','PC','PJ','PKL','PM','SE','TM','TPC','TPN','TPT','WC','WK'];
    }
}

if(!function_exists('um_branch_names'))
{
    function um_branch_names()
    {
        return [
            'HQ'=>'Head Quarter','KB'=>'Kota Bharu','KC'=>'Kampung Chempaka','KJ'=>'Kota Jembal','KK'=>'Kubang Kerian','KL'=>'Kok Lanas','KR'=>'Ketereh','KS'=>'Kampung Serendah','ML'=>'Melor','PC'=>'Pengkalan Chepa','PJ'=>'Panji','PKL'=>'Pasaraya Kok Lanas','PM'=>'Pasir Mas','SE'=>'Sering','TM'=>'Tanah Merah','TPC'=>'Tumpat Cabang Empat','TPN'=>'Tumpat New Town','TPT'=>'Tumpat','WC'=>'Wakaf Che Yeh','WK'=>'Wakaf Kebakat'
        ];
    }
}

if(!function_exists('ensure_user_privilege_matrix_table'))
{
    function ensure_user_privilege_matrix_table(PDO $pdo)
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_privilege_matrix (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            privilege_key VARCHAR(100) NOT NULL,
            branch_code VARCHAR(20) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_priv_branch (user_id, privilege_key, branch_code),
            KEY idx_user_privilege_matrix_user (user_id),
            KEY idx_user_privilege_matrix_priv (privilege_key),
            KEY idx_user_privilege_matrix_branch (branch_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if(!function_exists('privilege_matrix_catalog'))
{
    function privilege_matrix_catalog()
    {
        $modules = function_exists('module_permission_list') ? module_permission_list() : [];
        $actions = function_exists('action_permission_list') ? action_permission_list() : [];

        $rows = [];
        $add = function($group, $type, $key, $label, $description='') use (&$rows) {
            $rows[] = [
                'group' => $group,
                'type' => $type,
                'key' => $type.':'.$key,
                'base_key' => $key,
                'label' => $label,
                'description' => $description
            ];
        };

        $moduleGroups = [
            'DASHBOARD' => ['dashboard'],
            'ADMINISTRATION HOME' => ['administration'],
            'TICKET' => ['create_ticket','ticket_list'],
            'ASSET' => ['asset_list','add_asset'],
            'COMMUNICATION' => ['announcements','add_announcement','knowledge_base'],
            'REPORT' => ['report_kpi','audit_logs'],
            'MASTER / MANAGEMENT' => ['users','assign_to_management','pic_management','category_management','sla_management','branch_management']
        ];

        foreach($moduleGroups as $group => $keys) {
            foreach($keys as $key) {
                if(isset($modules[$key])) {
                    $add($group, 'module', $key, $modules[$key]['label'] ?? $key, $modules[$key]['description'] ?? '');
                }
            }
        }

        $actionGroups = [
            'TICKET ACTION' => ['show_in_assign_to','assign_ticket','change_status','edit_ticket','delete_ticket','export_ticket'],
            'ASSET ACTION' => ['manage_asset'],
            'COMMUNICATION ACTION' => ['manage_announcement','manage_kb'],
            'USER ACTION' => ['manage_user'],
            'SYSTEM ACTION' => ['export_audit','print_report']
        ];

        foreach($actionGroups as $group => $keys) {
            foreach($keys as $key) {
                if(isset($actions[$key])) {
                    $add($group, 'action', $key, $actions[$key]['label'] ?? $key, $actions[$key]['description'] ?? '');
                }
            }
        }

        return $rows;
    }
}

if(!function_exists('get_user_privilege_matrix'))
{
    function get_user_privilege_matrix(PDO $pdo, int $userId)
    {
        ensure_user_privilege_matrix_table($pdo);
        $out = [];
        $stmt = $pdo->prepare("SELECT privilege_key, branch_code FROM user_privilege_matrix WHERE user_id=?");
        $stmt->execute([$userId]);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $key = $r['privilege_key'];
            if(!isset($out[$key])) $out[$key] = [];
            $out[$key][] = $r['branch_code'];
        }
        return $out;
    }
}

if(!function_exists('normalize_privilege_matrix_input'))
{
    function normalize_privilege_matrix_input($matrix, array $branchList)
    {
        $validBranches = array_merge(['ALL'], $branchList);
        $validKeys = array_map(function($r){ return $r['key']; }, privilege_matrix_catalog());
        $out = [];

        if(!is_array($matrix)) return $out;

        foreach($matrix as $key => $branches) {
            if(!in_array($key, $validKeys, true)) continue;
            if(!is_array($branches)) $branches = [$branches];
            $clean = [];
            foreach($branches as $b) {
                $b = strtoupper(trim((string)$b));
                if(in_array($b, $validBranches, true)) $clean[] = $b;
            }
            $clean = array_values(array_unique($clean));
            if(count($clean) > 0) $out[$key] = $clean;
        }
        return $out;
    }
}

if(!function_exists('sync_user_privilege_matrix'))
{
    function sync_user_privilege_matrix(PDO $pdo, int $userId, $matrix, array $branchList)
    {
        ensure_user_privilege_matrix_table($pdo);
        $matrix = normalize_privilege_matrix_input($matrix, $branchList);
        $pdo->prepare("DELETE FROM user_privilege_matrix WHERE user_id=?")->execute([$userId]);
        if(count($matrix) == 0) return;
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_privilege_matrix (user_id, privilege_key, branch_code) VALUES (?,?,?)");
        foreach($matrix as $key => $branches) {
            foreach($branches as $branch) {
                $stmt->execute([$userId, $key, $branch]);
            }
        }
    }
}

if(!function_exists('derive_permissions_from_privilege_matrix'))
{
    function derive_permissions_from_privilege_matrix($matrix)
    {
        $module = [];
        $action = [];
        if(!is_array($matrix)) return ['module'=>[], 'action'=>[]];
        foreach($matrix as $key => $branches) {
            if(!is_array($branches) || count($branches) == 0) continue;
            if(strpos($key, 'module:') === 0) $module[] = substr($key, 7);
            if(strpos($key, 'action:') === 0) $action[] = substr($key, 7);
        }
        return ['module'=>array_values(array_unique($module)), 'action'=>array_values(array_unique($action))];
    }
}

if(!function_exists('build_default_privilege_matrix'))
{
    function build_default_privilege_matrix(array $modulePermissions, array $actionPermissions, array $branchList)
    {
        $matrix = [];
        foreach($modulePermissions as $p) $matrix['module:'.$p] = ['ALL'];
        foreach($actionPermissions as $p) $matrix['action:'.$p] = ['ALL'];
        return $matrix;
    }
}

if(!function_exists('matrix_allowed_branches'))
{
    function matrix_allowed_branches($matrix, array $branchList)
    {
        $branches = [];
        if(!is_array($matrix)) return $branches;
        foreach($matrix as $key => $codes) {
            if(!is_array($codes)) continue;
            foreach($codes as $code) {
                $code = strtoupper(trim((string)$code));
                if($code === 'ALL') return $branchList;
                if(in_array($code, $branchList, true)) $branches[] = $code;
            }
        }
        return array_values(array_unique($branches));
    }
}

if(!function_exists('render_user_privilege_matrix'))
{
    function render_user_privilege_matrix(array $selectedMatrix, array $branchList, array $branchNames)
    {
        $rows = privilege_matrix_catalog();
        $currentGroup = null;
        echo '<div class="privilege-matrix-wrap">';
        echo '<div class="privilege-toolbar mb-3">';
        echo '<button type="button" class="btn btn-sm btn-outline-primary" onclick="pmToggleAll(true)">Select All</button> ';
        echo '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="pmToggleAll(false)">Clear All</button> ';
        echo '<button type="button" class="btn btn-sm btn-outline-success" onclick="pmTickAllBranchOnly()">Tick ALL Column Only</button>';
        echo '<span class="text-muted small ms-2">ALL = all branches for that privilege.</span>';
        echo '</div>';
        echo '<div class="table-responsive privilege-matrix-table">';
        echo '<table class="table table-sm table-bordered align-middle">';
        echo '<thead><tr><th class="sticky-col privilege-name">Privilege</th><th class="text-center pm-branch-head">ALL<br><input type="checkbox" onclick="pmToggleColumn(\'ALL\',this.checked)"></th>';
        foreach($branchList as $b) {
            echo '<th class="text-center pm-branch-head" title="'.htmlspecialchars($branchNames[$b] ?? $b).'">'.htmlspecialchars($b).'<br><input type="checkbox" onclick="pmToggleColumn(\''.htmlspecialchars($b).'\',this.checked)"></th>';
        }
        echo '<th>Description</th></tr></thead><tbody>';
        foreach($rows as $row) {
            if($currentGroup !== $row['group']) {
                $currentGroup = $row['group'];
                
                $groupClass = 'pm-group-'.preg_replace('/[^A-Za-z0-9_-]/','-', $currentGroup);
                echo '<tr class="pm-group"><td colspan="'.(count($branchList)+3).'"><div class="d-flex flex-wrap gap-2 align-items-center justify-content-between"><span>'.htmlspecialchars($currentGroup).'</span><span><button type="button" class="btn btn-xs btn-outline-primary py-0" onclick="pmToggleGroup(\''.$groupClass.'\',true)">Tick Group</button> <button type="button" class="btn btn-xs btn-outline-secondary py-0" onclick="pmToggleGroup(\''.$groupClass.'\',false)">Clear Group</button></span></div></td></tr>';

            }
            $key = $row['key'];
            echo '<tr class="pm-row '.htmlspecialchars($groupClass).'" data-key="'.htmlspecialchars($key).'">';
            echo '<td class="sticky-col privilege-name"><strong>'.htmlspecialchars($row['label']).'</strong><div class="small text-muted">'.htmlspecialchars($row['base_key']).'</div></td>';
            $checkedAll = in_array('ALL', $selectedMatrix[$key] ?? [], true) ? 'checked' : '';
            echo '<td class="text-center"><input class="pm-box pm-col-ALL" type="checkbox" name="privilege_matrix['.htmlspecialchars($key).'][]" value="ALL" '.$checkedAll.'></td>';
            foreach($branchList as $b) {
                $checked = in_array($b, $selectedMatrix[$key] ?? [], true) ? 'checked' : '';
                echo '<td class="text-center"><input class="pm-box pm-col-'.htmlspecialchars($b).'" type="checkbox" name="privilege_matrix['.htmlspecialchars($key).'][]" value="'.htmlspecialchars($b).'" '.$checked.'></td>';
            }
            echo '<td class="small text-muted">'.htmlspecialchars($row['description']).'</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></div>';
    }
}
?>
