<?php
require 'header.php';
require 'db.php';
require_once 'module_permissions.php';

if(!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_action_permission('manage_user');

function sc_h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function sc_table_exists(PDO $pdo, $table){ try{$s=$pdo->prepare("SHOW TABLES LIKE ?");$s->execute([$table]);return (bool)$s->fetchColumn();}catch(Exception $e){return false;} }
function sc_col_exists(PDO $pdo, $table, $col){ try{$s=$pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");$s->execute([$col]);return (bool)$s->fetchColumn();}catch(Exception $e){return false;} }
function sc_file_exists($file){ return file_exists(__DIR__.'/'.$file); }
function sc_dir_ready($dir){ return is_dir(__DIR__.'/'.$dir) && is_writable(__DIR__.'/'.$dir); }

$checks=[];
$add=function($group,$item,$ok,$detail='') use (&$checks){ $checks[]=['group'=>$group,'item'=>$item,'ok'=>(bool)$ok,'detail'=>$detail]; };

$requiredTables=[
    'users','user_permissions','user_action_permissions','tickets','ticket_replies','ticket_history',
    'assets','knowledge_base','knowledge_base_attachments','announcements','announcement_reads',
    'audit_logs','branch_master','pic_master','assign_to_master','ticket_category_master','sla_master'
];
foreach($requiredTables as $t) $add('Database Tables',$t,sc_table_exists($pdo,$t),sc_table_exists($pdo,$t)?'OK':'Missing table');

$requiredColumns=[
 'users'=>['id','username','password','full_name','role','branch','branch_access','department','status','ticket_scope','ticket_branch_access','ticket_pic_access','failed_login_attempts','locked_until'],
 'tickets'=>['id','ticket_no','title','description','category','priority','status','created_by','branch','department','assigned_to','due_date','closed_at','updated_at','asset_id'],
 'ticket_replies'=>['id','ticket_id','user_id','message','attachment','created_at'],
 'ticket_history'=>['id','ticket_id','action','created_by','created_at'],
 'assets'=>['id','asset_code','asset_name','asset_type','branch','status','asset_photo'],
 'knowledge_base'=>['id','title','category','content','status','views','attachment','attachment_name','updated_at'],
 'knowledge_base_attachments'=>['id','article_id','file_path','original_name','file_size','uploaded_at'],
 'announcements'=>['id','title','content','start_date','end_date','attachment_path','attachment_name','created_by','created_at'],
 'announcement_reads'=>['id','announcement_id','user_id','branch','read_at'],
 'audit_logs'=>['id','username','action','details','created_at']
];
foreach($requiredColumns as $table=>$cols)
{
    foreach($cols as $col)
    {
        $add('Database Columns',"$table.$col",sc_col_exists($pdo,$table,$col),sc_col_exists($pdo,$table,$col)?'OK':'Missing column');
    }
}

$requiredFiles=[
    'access_control.php','module_permissions.php','header.php','dashboard.php','ticket_list.php','closed_tickets.php',
    'create_ticket.php','view_ticket.php','edit_ticket.php','assign_ticket.php','update_status.php','reply_ticket.php',
    'announcements.php','announcement_read_report.php','mark_announcement_read.php',
    'knowledge_base.php','add_article.php','edit_article.php','kb_attachment_lib.php',
    'users.php','add_user.php','edit_user.php','system_check.php'
];
foreach($requiredFiles as $f) $add('PHP Files',$f,sc_file_exists($f),sc_file_exists($f)?'OK':'Missing file');

$uploadDirs=['uploads','uploads/tickets','uploads/knowledge_base','uploads/assets','uploads/announcements'];
foreach($uploadDirs as $dir)
{
    if(!is_dir(__DIR__.'/'.$dir)) @mkdir(__DIR__.'/'.$dir,0777,true);
    $add('Upload Directories',$dir,sc_dir_ready($dir),sc_dir_ready($dir)?'Writable':'Missing or not writable');
}

$htaccess = __DIR__.'/uploads/.htaccess';
if(!file_exists($htaccess))
{
    @file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|php3|php4|php5|phtml|phar|cgi|pl|asp|aspx|jsp|sh)$\">\nRequire all denied\n</FilesMatch>\n");
}
$add('Upload Security','uploads/.htaccess',file_exists($htaccess),'Prevents script execution/listing on Apache');

$total=count($checks);
$passed=count(array_filter($checks,fn($c)=>$c['ok']));
$failed=$total-$passed;
$grouped=[];
foreach($checks as $c) $grouped[$c['group']][]=$c;
?>

<style>
.check-hero{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:20px;box-shadow:0 8px 20px rgba(15,23,42,.06);margin-bottom:18px}
.check-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px}
.check-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:16px;box-shadow:0 8px 20px rgba(15,23,42,.05)}
.check-num{font-size:28px;font-weight:900}
.group-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;margin-bottom:16px}
.group-head{padding:14px 16px;border-bottom:1px solid #edf2f7;font-weight:900}
.table td,.table th{vertical-align:middle}
@media(max-width:900px){.check-grid{grid-template-columns:1fr}}
</style>

<div class="check-hero">
    <h2 class="mb-1">System Check</h2>
    <div class="text-muted">Final Function Alignment v4: database, permission, ticket visibility, upload and file checks.</div>
</div>

<div class="check-grid">
    <div class="check-card"><div class="check-num text-primary"><?= $total ?></div><div>Total Checks</div></div>
    <div class="check-card"><div class="check-num text-success"><?= $passed ?></div><div>Passed</div></div>
    <div class="check-card"><div class="check-num text-danger"><?= $failed ?></div><div>Need Fix</div></div>
</div>

<?php if($failed>0): ?>
<div class="alert alert-warning">
    Some items are missing. Run <strong>install_final_function_alignment_v4.sql</strong> in phpMyAdmin, then refresh this page.
</div>
<?php else: ?>
<div class="alert alert-success">All checked functions are aligned.</div>
<?php endif; ?>

<?php foreach($grouped as $group=>$items): ?>
<div class="group-card">
    <div class="group-head"><?= sc_h($group) ?></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th style="width:120px;">Status</th><th>Item</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach($items as $c): ?>
                <tr>
                    <td><?= $c['ok'] ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">Missing</span>' ?></td>
                    <td><?= sc_h($c['item']) ?></td>
                    <td><?= sc_h($c['detail']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php require 'footer.php'; ?>
