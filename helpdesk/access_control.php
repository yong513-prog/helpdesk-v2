<?php
/*
|--------------------------------------------------------------------------
| Access Control: Admin / Head / Staff
|--------------------------------------------------------------------------
| Admin: view all tickets.
| Head : linked permission mode. View tickets only when Branch is allowed
|        AND (PIC is allowed OR assigned to self/allowed assignee OR created by self).
| Staff: view tickets from own Primary Branch OR assigned to self/allowed assignee
|        OR created by self.
|
| IMPORTANT:
| - Dashboard, Ticket List, Closed Tickets, Overdue, Export, KPI and Notification
|   must use this same filter through apply_ticket_access_filter().
*/

if(session_status() === PHP_SESSION_NONE) session_start();

if(!function_exists('normalize_role')){
    function normalize_role($role){
        $role = strtolower(trim((string)$role));
        if(in_array($role, ['administrator','admin'], true)) return 'admin';
        if($role === 'head') return 'head';
        return 'staff';
    }
}

if(!function_exists('csv_to_array')){
    function csv_to_array($value){
        $items = [];
        foreach(explode(',', (string)$value) as $item){
            $item = trim($item);
            if($item !== '') $items[] = $item;
        }
        return array_values(array_unique($items));
    }
}

if(!function_exists('append_condition')){
    function append_condition(&$sql, $condition){
        if(stripos($sql, 'WHERE') === false) $sql .= " WHERE ".$condition." ";
        else $sql .= " AND ".$condition." ";
    }
}

if(!function_exists('default_ticket_scope_for_role')){
    function default_ticket_scope_for_role($role){
        $role = normalize_role($role);
        if($role === 'admin') return 'ALL';
        if($role === 'head') return 'BRANCH';
        return 'OWN';
    }
}

if(!function_exists('get_current_user_record')){
    function get_current_user_record(){
        global $pdo;
        if(!isset($_SESSION['user_id']) || !isset($pdo)) return null;
        static $cachedUser = null;
        if($cachedUser !== null) return $cachedUser;

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $cachedUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if($cachedUser){
            $_SESSION['role'] = normalize_role($cachedUser['role'] ?? 'staff');
            $_SESSION['branch'] = $cachedUser['branch'] ?? '';
            $_SESSION['branch_access'] = $cachedUser['branch_access'] ?? '';
            $_SESSION['ticket_branch_access'] = $cachedUser['ticket_branch_access'] ?? '';
            $_SESSION['ticket_assign_access'] = $cachedUser['ticket_assign_access'] ?? '';
            $_SESSION['department'] = $cachedUser['department'] ?? '';
            $_SESSION['ticket_pic_access'] = $cachedUser['ticket_pic_access'] ?? '';
            $_SESSION['ticket_scope'] = $cachedUser['ticket_scope'] ?? default_ticket_scope_for_role($_SESSION['role']);
            $_SESSION['username'] = $cachedUser['username'] ?? '';
            $_SESSION['full_name'] = $cachedUser['full_name'] ?? '';
        }
        return $cachedUser;
    }
}

if(!function_exists('get_ticket_scope')){
    function get_ticket_scope(){
        $user = get_current_user_record();
        $role = normalize_role($_SESSION['role'] ?? ($user['role'] ?? 'staff'));
        $scope = $user['ticket_scope'] ?? ($_SESSION['ticket_scope'] ?? default_ticket_scope_for_role($role));
        if(!in_array($scope, ['ALL','BRANCH','OWN'], true)) $scope = default_ticket_scope_for_role($role);
        if($role !== 'admin' && $scope === 'ALL') $scope = default_ticket_scope_for_role($role);
        return $scope;
    }
}

if(!function_exists('get_user_allowed_branches')){
    function get_user_allowed_branches(){
        $user = get_current_user_record();
        $branches = array_merge(
            csv_to_array($user['branch_access'] ?? ($_SESSION['branch_access'] ?? '')),
            csv_to_array($user['ticket_branch_access'] ?? ($_SESSION['ticket_branch_access'] ?? ''))
        );
        $primary = trim((string)($user['branch'] ?? ($_SESSION['branch'] ?? '')));
        if($primary !== '') $branches[] = $primary;
        return array_values(array_unique($branches));
    }
}

if(!function_exists('get_user_ticket_branches')){
    function get_user_ticket_branches(){
        $user = get_current_user_record();
        $branches = csv_to_array($user['ticket_branch_access'] ?? ($_SESSION['ticket_branch_access'] ?? ''));
        return array_values(array_unique($branches));
    }
}

if(!function_exists('get_user_ticket_pics')){
    function get_user_ticket_pics(){
        $user = get_current_user_record();
        $pics = csv_to_array($user['ticket_pic_access'] ?? ($_SESSION['ticket_pic_access'] ?? ''));
        return array_values(array_unique($pics));
    }
}

if(!function_exists('get_user_ticket_assignees')){
    function get_user_ticket_assignees(){
        $user = get_current_user_record();
        $assignees = csv_to_array($user['ticket_assign_access'] ?? ($_SESSION['ticket_assign_access'] ?? ''));
        return array_values(array_unique($assignees));
    }
}

if(!function_exists('get_current_user_identity_names')){
    function get_current_user_identity_names(){
        $user = get_current_user_record();
        $names = [];
        foreach([
            $user['username'] ?? ($_SESSION['username'] ?? ''),
            $user['full_name'] ?? ($_SESSION['full_name'] ?? ''),
            $user['department'] ?? ($_SESSION['department'] ?? '')
        ] as $n){
            $n = trim((string)$n);
            if($n !== '') $names[] = $n;
        }
        foreach(get_user_ticket_assignees() as $n){
            $n = trim((string)$n);
            if($n !== '') $names[] = $n;
        }
        return array_values(array_unique($names));
    }
}

if(!function_exists('apply_ticket_access_filter')){
    function apply_ticket_access_filter(&$sql, &$params){
        get_current_user_record();
        $role = normalize_role($_SESSION['role'] ?? 'staff');
        $userId = (int)($_SESSION['user_id'] ?? 0);

        if($role === 'admin') return;

        if($role === 'head'){
            // FINAL HEAD LINKED MODE:
            // Branch Access / Ticket Branch Access limits the branch scope.
            // Inside allowed branches, Head can see checked PIC, assigned-to-self/allowed assignee,
            // or tickets created by self. If no branch is configured, PIC/Assigned/Created still applies.
            $branches = get_user_allowed_branches();
            $pics = get_user_ticket_pics();
            $assignees = get_current_user_identity_names();

            $andParts = [];
            $andParams = [];

            if(count($branches) > 0){
                $bp = implode(',', array_fill(0, count($branches), '?'));
                $andParts[] = "branch IN ($bp)";
                foreach($branches as $b) $andParams[] = $b;
            }

            $inner = [];
            $innerParams = [];

            if(count($pics) > 0){
                $pp = implode(',', array_fill(0, count($pics), '?'));
                $inner[] = "department IN ($pp)";
                foreach($pics as $p) $innerParams[] = $p;
            }

            if(count($assignees) > 0){
                $ap = implode(',', array_fill(0, count($assignees), '?'));
                $inner[] = "assigned_to IN ($ap)";
                foreach($assignees as $a) $innerParams[] = $a;
            }

            if($userId > 0){
                $inner[] = "created_by = ?";
                $innerParams[] = $userId;
            }

            if(count($inner) === 0){ append_condition($sql, '1=0'); return; }

            $andParts[] = '(' . implode(' OR ', $inner) . ')';
            append_condition($sql, '(' . implode(' AND ', $andParts) . ')');
            foreach(array_merge($andParams, $innerParams) as $v) $params[] = $v;
            return;
        }

        // Staff: own Primary Branch OR assigned-to-self/allowed assignee OR created by self.
        $orParts = [];
        $orParams = [];
        $branch = trim((string)($_SESSION['branch'] ?? ''));
        $assignees = get_current_user_identity_names();

        if($branch !== ''){
            $orParts[] = "branch = ?";
            $orParams[] = $branch;
        }

        if(count($assignees) > 0){
            $ap = implode(',', array_fill(0, count($assignees), '?'));
            $orParts[] = "assigned_to IN ($ap)";
            foreach($assignees as $a) $orParams[] = $a;
        }

        if($userId > 0){
            $orParts[] = "created_by = ?";
            $orParams[] = $userId;
        }

        if(count($orParts) === 0){ append_condition($sql, '1=0'); return; }
        append_condition($sql, '(' . implode(' OR ', $orParts) . ')');
        foreach($orParams as $v) $params[] = $v;
    }
}

if(!function_exists('can_access_ticket')){
    function can_access_ticket($ticket){
        get_current_user_record();
        $role = normalize_role($_SESSION['role'] ?? 'staff');
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if($role === 'admin') return true;

        $branch = (string)($ticket['branch'] ?? '');
        $pic = (string)($ticket['department'] ?? '');
        $assignedTo = (string)($ticket['assigned_to'] ?? '');
        $createdBy = (int)($ticket['created_by'] ?? 0);

        if($role === 'head'){
            $branches = get_user_allowed_branches();
            $branchOk = count($branches) === 0 || ($branch !== '' && in_array($branch, $branches, true));
            if(!$branchOk) return false;

            return ($pic !== '' && in_array($pic, get_user_ticket_pics(), true))
                || ($assignedTo !== '' && in_array($assignedTo, get_current_user_identity_names(), true))
                || ($userId > 0 && $createdBy === $userId);
        }

        return ($branch !== '' && $branch === (string)($_SESSION['branch'] ?? ''))
            || ($assignedTo !== '' && in_array($assignedTo, get_current_user_identity_names(), true))
            || ($userId > 0 && $createdBy === $userId);
    }
}

if(!function_exists('can_manage_ticket')){
    function can_manage_ticket($ticket){ return can_access_ticket($ticket); }
}

if(!function_exists('user_can_edit_ticket')){
    function user_can_edit_ticket(){ return in_array(normalize_role($_SESSION['role'] ?? 'staff'), ['admin','head'], true); }
}

if(!function_exists('require_admin')){
    function require_admin(){ if(normalize_role($_SESSION['role'] ?? '') !== 'admin'){ http_response_code(403); die('Access Denied'); } }
}
?>
