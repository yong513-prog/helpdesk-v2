<?php
require 'db.php';

$pdo->exec("
CREATE TABLE IF NOT EXISTS asset_type_master (
    id INT NOT NULL AUTO_INCREMENT,
    type_name VARCHAR(100) NOT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_asset_type_name (type_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$defaults = [
    'POS','Printer','Barcode Printer','Scanner','PC','Laptop','Server',
    'Network Switch','Router','Firewall','CCTV','DVR','NVR','UPS',
    'Cash Drawer','Barcode Scanner','Weighing Scale','Touch Screen',
    'Customer Display','Tablet','Mobile Device','Other'
];

$stmt = $pdo->prepare("INSERT IGNORE INTO asset_type_master (type_name, status, sort_order) VALUES (?, 1, ?)");
foreach($defaults as $i => $name)
{
    $stmt->execute([$name, ($i + 1) * 10]);
}

echo "<h3>Asset Type Management installed successfully.</h3>";
echo "<p>You may now open <a href='asset_type_management.php'>Asset Type Management</a>.</p>";
