<?php
require 'db.php';
require_once 'kb_org_lib.php';

try {
    kb_org_ensure_schema($pdo);

    // Optional: backfill empty fields with safe defaults.
    $pdo->exec("UPDATE knowledge_base SET knowledge_type='Guide' WHERE knowledge_type IS NULL OR knowledge_type=''");
    $pdo->exec("UPDATE knowledge_base SET branch_scope='ALL' WHERE branch_scope IS NULL OR branch_scope=''");

    echo "<h3>Knowledge Base organization installed successfully.</h3>";
    echo "<ul>";
    echo "<li>knowledge_type added/checked</li>";
    echo "<li>tags added/checked</li>";
    echo "<li>branch_scope added/checked</li>";
    echo "<li>views/status/updated_at checked</li>";
    echo "</ul>";
    echo "<p><a href='knowledge_base.php'>Go to Knowledge Base</a></p>";
} catch (Exception $e) {
    echo "<h3>Install failed</h3>";
    echo "<pre>".htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')."</pre>";
}
?>