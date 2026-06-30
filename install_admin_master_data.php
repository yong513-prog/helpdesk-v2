<?php
require 'header.php';
require 'db.php';
require_once 'kb_org_lib.php';
require_once 'ticket_master_options.php';
kb_org_ensure_schema($pdo);
kb_category_master_ensure($pdo);
try { if(function_exists('ensure_asset_type_master')) { ensure_asset_type_master($pdo); } } catch(Exception $e) {}
?>
<div class="container py-4">
  <div class="alert alert-success">
    <h4 class="alert-heading">Administration master data upgrade completed.</h4>
    <p class="mb-1">Knowledge Category Management table is ready and seeded from current ticket/knowledge categories.</p>
    <p class="mb-0">Asset Type Management continues to drive Add/Edit Asset dropdowns.</p>
  </div>
  <a class="btn btn-primary" href="kb_category_management.php">Open Knowledge Category Management</a>
  <a class="btn btn-outline-primary" href="asset_type_management.php">Open Asset Type Management</a>
</div>
<?php require 'footer.php'; ?>
