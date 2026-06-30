<?php
if(!function_exists('master_fetch_active_branches')){
function master_fetch_active_branches(PDO $pdo): array {
    try { $rows = $pdo->query("SELECT branch_code, branch_name FROM branch_master WHERE status = 1 ORDER BY branch_code ASC")->fetchAll(PDO::FETCH_ASSOC); if($rows) return $rows; } catch(Exception $e) {}
    return [['branch_code'=>'HQ','branch_name'=>'Head Quarter'],['branch_code'=>'KB','branch_name'=>'Kota Bharu'],['branch_code'=>'KC','branch_name'=>'Kampung Chempaka'],['branch_code'=>'KJ','branch_name'=>'Kota Jembal'],['branch_code'=>'KK','branch_name'=>'Kubang Kerian'],['branch_code'=>'KL','branch_name'=>'Kok Lanas'],['branch_code'=>'KR','branch_name'=>'Ketereh'],['branch_code'=>'KS','branch_name'=>'Kampung Serendah'],['branch_code'=>'ML','branch_name'=>'Melor'],['branch_code'=>'PC','branch_name'=>'Pengkalan Chepa'],['branch_code'=>'PJ','branch_name'=>'Panji'],['branch_code'=>'PKL','branch_name'=>'Pasaraya Kok Lanas'],['branch_code'=>'PM','branch_name'=>'Pasir Mas'],['branch_code'=>'SE','branch_name'=>'Sering'],['branch_code'=>'TM','branch_name'=>'Tanah Merah'],['branch_code'=>'TPC','branch_name'=>'Tumpat Cabang Empat'],['branch_code'=>'TPN','branch_name'=>'Tumpat New Town'],['branch_code'=>'TPT','branch_name'=>'Tumpat'],['branch_code'=>'WC','branch_name'=>'Wakaf Che Yeh'],['branch_code'=>'WK','branch_name'=>'Wakaf Kebakat']];
}}
if(!function_exists('master_fetch_active_categories')){
function master_fetch_active_categories(PDO $pdo): array {
    try { $rows = $pdo->query("SELECT category_name, default_priority FROM ticket_category_master WHERE status = 1 ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC); if($rows) return $rows; } catch(Exception $e) {}
    return [['category_name'=>'POS System','default_priority'=>'High'],['category_name'=>'Printer / Barcode Printer','default_priority'=>'Medium'],['category_name'=>'Network Issue','default_priority'=>'High'],['category_name'=>'Inventory Issue','default_priority'=>'Medium'],['category_name'=>'Purchasing Issue','default_priority'=>'Medium'],['category_name'=>'Maintenance / Electrical','default_priority'=>'Medium'],['category_name'=>'HR / Staff Issue','default_priority'=>'Medium'],['category_name'=>'Other','default_priority'=>'Low']];
}}
if(!function_exists('master_fetch_active_sla')){
function master_fetch_active_sla(PDO $pdo): array {
    try { $rows = $pdo->query("SELECT priority_name, sla_hours FROM sla_master WHERE status = 1 ORDER BY sla_hours ASC")->fetchAll(PDO::FETCH_ASSOC); if($rows) return $rows; } catch(Exception $e) {}
    return [['priority_name'=>'Urgent','sla_hours'=>4],['priority_name'=>'High','sla_hours'=>8],['priority_name'=>'Medium','sla_hours'=>24],['priority_name'=>'Low','sla_hours'=>48]];
}}
if(!function_exists('master_category_exists')){
function master_category_exists(array $categories, string $name): bool { foreach($categories as $c){ if(($c['category_name'] ?? '') === $name) return true; } return false; }
}
if(!function_exists('master_priority_hours')){
function master_priority_hours(array $slaList, string $priority): ?int { foreach($slaList as $s){ if(($s['priority_name'] ?? '') === $priority) return (int)($s['sla_hours'] ?? 24); } return null; }
}
if(!function_exists('master_category_default_priority')){
function master_category_default_priority(array $categories, string $category): string { foreach($categories as $c){ if(($c['category_name'] ?? '') === $category) return (string)($c['default_priority'] ?? 'Medium'); } return 'Medium'; }
}

if(!function_exists('master_fetch_active_asset_types')){
function master_fetch_active_asset_types(PDO $pdo): array {
    try {
        $rows = $pdo->query("SELECT type_name FROM asset_type_master WHERE status = 1 ORDER BY sort_order ASC, type_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        if($rows) return array_map(function($r){ return $r['type_name']; }, $rows);
    } catch(Exception $e) {}
    return ['POS','Printer','Barcode Printer','Scanner','PC','Laptop','Server','Network Switch','Router','Firewall','CCTV','DVR','NVR','UPS','Cash Drawer','Barcode Scanner','Weighing Scale','Touch Screen','Customer Display','Tablet','Mobile Device','Other'];
}}

?>
