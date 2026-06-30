<?php
// Knowledge Base Organization helper
// Links Knowledge Base with Category Management, Branch Management and Create Ticket suggestions.

if (!function_exists('kb_org_col_exists')) {
    function kb_org_col_exists(PDO $pdo, string $table, string $column): bool {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) { return false; }
    }
}

if (!function_exists('kb_org_table_exists')) {
    function kb_org_table_exists(PDO $pdo, string $table): bool {
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) { return false; }
    }
}

if (!function_exists('kb_org_ensure_schema')) {
    function kb_org_ensure_schema(PDO $pdo): void {
        if (!kb_org_table_exists($pdo, 'knowledge_base')) return;

        $adds = [];
        if (!kb_org_col_exists($pdo, 'knowledge_base', 'knowledge_type')) {
            $adds[] = "ADD COLUMN knowledge_type VARCHAR(50) DEFAULT 'Guide' AFTER category";
        }
        if (!kb_org_col_exists($pdo, 'knowledge_base', 'tags')) {
            $adds[] = "ADD COLUMN tags VARCHAR(255) DEFAULT NULL AFTER content";
        }
        if (!kb_org_col_exists($pdo, 'knowledge_base', 'branch_scope')) {
            $adds[] = "ADD COLUMN branch_scope TEXT NULL AFTER tags";
        }
        if (!kb_org_col_exists($pdo, 'knowledge_base', 'views')) {
            $adds[] = "ADD COLUMN views INT DEFAULT 0";
        }
        if (!kb_org_col_exists($pdo, 'knowledge_base', 'status')) {
            $adds[] = "ADD COLUMN status ENUM('Published','Draft') DEFAULT 'Published'";
        }
        if (!kb_org_col_exists($pdo, 'knowledge_base', 'updated_at')) {
            $adds[] = "ADD COLUMN updated_at DATETIME DEFAULT NULL";
        }
        if ($adds) {
            $pdo->exec("ALTER TABLE knowledge_base ".implode(", ", $adds));
        }
    }
}

if (!function_exists('kb_org_h')) {
    function kb_org_h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('kb_org_csv_array')) {
    function kb_org_csv_array($value): array {
        $out = [];
        foreach (explode(',', (string)$value) as $item) {
            $item = trim($item);
            if ($item !== '' && $item !== '-') $out[] = $item;
        }
        return array_values(array_unique($out));
    }
}

if (!function_exists('kb_org_normalize_csv')) {
    function kb_org_normalize_csv($items): string {
        if (!is_array($items)) $items = kb_org_csv_array($items);
        $clean = [];
        foreach ($items as $item) {
            $item = trim((string)$item);
            if ($item !== '' && $item !== '-') $clean[] = $item;
        }
        return implode(',', array_values(array_unique($clean)));
    }
}

if (!function_exists('kb_org_types')) {
    function kb_org_types(): array {
        return ['Guide','SOP','FAQ','Troubleshooting','Patch','Video','Form','Policy'];
    }
}



if (!function_exists('kb_category_master_ensure')) {
    function kb_category_master_ensure(PDO $pdo): void {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS kb_category_master (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category_name VARCHAR(150) NOT NULL,
                status TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                UNIQUE KEY uniq_kb_category_name (category_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $count = (int)$pdo->query("SELECT COUNT(*) FROM kb_category_master")->fetchColumn();
            if ($count === 0) {
                $seed = [];
                try {
                    $seed = $pdo->query("SELECT category_name FROM ticket_category_master WHERE status=1 ORDER BY category_name ASC")->fetchAll(PDO::FETCH_COLUMN);
                } catch (Exception $e) {}
                try {
                    $kbCats = $pdo->query("SELECT DISTINCT category FROM knowledge_base WHERE category IS NOT NULL AND category<>'' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
                    $seed = array_merge($seed ?: [], $kbCats ?: []);
                } catch (Exception $e) {}
                if (!$seed) {
                    $seed = ['HR / Staff Issue','Inventory Issue','Maintenance / Electrical','Network Issue','POS System','Printer / Barcode Printer','Purchasing Issue','Other'];
                }
                $seed = array_values(array_unique(array_filter(array_map('trim', $seed))));
                $stmt = $pdo->prepare("INSERT IGNORE INTO kb_category_master (category_name,status,sort_order) VALUES (?,1,?)");
                $i = 10;
                foreach ($seed as $name) {
                    $stmt->execute([$name, $i]);
                    $i += 10;
                }
            }
        } catch (Exception $e) {}
    }
}

if (!function_exists('kb_org_fetch_categories')) {
    function kb_org_fetch_categories(PDO $pdo): array {
        kb_category_master_ensure($pdo);
        try {
            $rows = $pdo->query("SELECT category_name FROM kb_category_master WHERE status=1 ORDER BY sort_order ASC, category_name ASC")->fetchAll(PDO::FETCH_COLUMN);
            $rows = array_values(array_filter(array_map('trim', $rows)));
            if ($rows) return $rows;
        } catch (Exception $e) {}
        try {
            $rows = $pdo->query("SELECT DISTINCT category FROM knowledge_base WHERE category IS NOT NULL AND category<>'' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
            $rows = array_values(array_filter(array_map('trim', $rows)));
            if ($rows) return $rows;
        } catch (Exception $e) {}
        return ['HR / Staff Issue','Inventory Issue','Maintenance / Electrical','Network Issue','POS System','Printer / Barcode Printer','Purchasing Issue','Other'];
    }
}

if (!function_exists('kb_org_fetch_branches')) {
    function kb_org_fetch_branches(PDO $pdo): array {
        try {
            $rows = $pdo->query("SELECT branch_code, branch_name FROM branch_master WHERE status=1 ORDER BY branch_code ASC")->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) return $rows;
        } catch (Exception $e) {}
        return [
            ['branch_code'=>'HQ','branch_name'=>'Head Quarter'],
            ['branch_code'=>'KB','branch_name'=>'Kota Bharu'],
            ['branch_code'=>'KC','branch_name'=>'Kampung Chempaka'],
            ['branch_code'=>'PJ','branch_name'=>'Panji'],
            ['branch_code'=>'SE','branch_name'=>'Sering']
        ];
    }
}

if (!function_exists('kb_org_can_view_article')) {
    function kb_org_can_view_article(array $article): bool {
        $scope = trim((string)($article['branch_scope'] ?? ''));
        if ($scope === '' || strtoupper($scope) === 'ALL') return true;
        $branches = kb_org_csv_array($scope);
        $userBranch = trim((string)($_SESSION['branch'] ?? ''));
        $role = strtolower((string)($_SESSION['role'] ?? 'staff'));
        if ($role === 'admin') return true;
        return $userBranch !== '' && in_array($userBranch, $branches, true);
    }
}

if (!function_exists('kb_org_scope_label')) {
    function kb_org_scope_label($scope): string {
        $scope = trim((string)$scope);
        if ($scope === '' || strtoupper($scope) === 'ALL') return 'All Branch';
        return $scope;
    }
}

if (!function_exists('kb_org_fetch_suggested')) {
    function kb_org_fetch_suggested(PDO $pdo, string $category = '', string $branch = '', int $limit = 5): array {
        kb_org_ensure_schema($pdo);
        $where = [];
        $params = [];

        if (kb_org_col_exists($pdo, 'knowledge_base', 'status')) {
            $where[] = "(status IS NULL OR status='Published')";
        }

        if ($category !== '') {
            $where[] = "(category = ? OR title LIKE ? OR tags LIKE ?)";
            $params[] = $category;
            $params[] = '%'.$category.'%';
            $params[] = '%'.$category.'%';
        }

        if ($branch !== '') {
            $where[] = "(branch_scope IS NULL OR branch_scope='' OR UPPER(branch_scope)='ALL' OR FIND_IN_SET(?, REPLACE(branch_scope,' ','')) > 0)";
            $params[] = str_replace(' ', '', $branch);
        }

        $sql = "SELECT id,title,category,knowledge_type,tags,branch_scope,views FROM knowledge_base";
        if ($where) $sql .= " WHERE ".implode(' AND ', $where);
        $sql .= " ORDER BY COALESCE(views,0) DESC, COALESCE(updated_at,created_at) DESC, title ASC LIMIT ".(int)$limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>