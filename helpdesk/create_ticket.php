<?php

require 'header.php';
if(!function_exists('h')){ function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); } }
require 'db.php';
require_once 'access_control.php';
require_once 'audit_log.php';
require_once 'ticket_history.php';
require_once 'send_mail.php';
require_once 'module_permissions.php';
require_once 'ticket_master_options.php';
require_once 'ticket_status_options.php';
require_once 'kb_org_lib.php';
require_once 'kb_content_translate.php';
require_once 'notification_helper.php';
require_once 'attachment_upload_helper.php';
require_once 'ticket_attachment_helper.php';

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}
kb_org_ensure_schema($pdo);
try { hd_ensure_kb_translation_columns($pdo); } catch (Exception $e) {}
ticket_status_ensure_ticket_column($pdo);
ticket_status_ensure_last_update_columns($pdo);

/*
 * Create Ticket page local i18n fallback.
 * Keeps database values unchanged, translates only UI/template display.
 */
if(!function_exists('ct_lang_code')) {
    function ct_lang_code() {
        $lang = $_SESSION['helpdesk_lang'] ?? $_COOKIE['helpdesk_lang'] ?? $_SESSION['lang'] ?? $_SESSION['language'] ?? $_GET['lang'] ?? $_COOKIE['lang'] ?? 'en';
        $lang = strtolower((string)$lang);
        if($lang === 'bm') $lang = 'ms';
        if(!in_array($lang, ['en','ms','zh'], true)) $lang = 'en';
        return $lang;
    }
}
if(!function_exists('ct_t')) {
    function ct_t($key) {
        static $dict = [
            'en' => [
                'create_ticket'=>'Create Ticket','create_ticket_subtitle'=>'Submit branch issue, attach proof, and route it to the correct PIC.','back_to_ticket_list'=>'Back to Ticket List',
                'ticket_information'=>'Ticket Information','title'=>'Title','title_placeholder'=>'Example: POS cannot connect to server','title_help'=>'Use a clear and short title so HQ can understand quickly.',
                'branch'=>'Branch','person_in_charge'=>'Person In Charge','no_profile_pic'=>'No Profile PIC selected for this user','pic_help'=>'Choose the PIC responsible for this ticket.',
                'assign_to'=>'Assign To','unassigned'=>'Unassigned','assign_help'=>'Admin / Head can assign this ticket directly when creating it.',
                'asset_equipment'=>'Asset / Equipment','no_permission_asset'=>'No Permission To Select Asset','no_asset_selected'=>'No Asset Selected','asset_no_permission_help'=>'Controlled by Role Permission Matrix: enable Asset List or Select Asset In Ticket.','asset_none_help'=>'No active/repair asset found for your allowed branch. Add asset in Asset Management or check asset branch/status.','asset_help'=>'Asset dropdown is linked to Role Permission Matrix and active/repair Asset Management records.',
                'category'=>'Category','priority'=>'Priority','description'=>'Description','description_placeholder'=>'Describe the problem, error message, affected counter/device, and what you already tried.','characters'=>'characters','hours'=>'hours',
                'upload_attachment'=>'Upload Attachment','camera'=>'Camera','gallery_file'=>'Gallery / File','file_note'=>'Allowed: Camera photo, gallery image, audio/voice, PDF, Word, Excel. Max 10MB.','submit_hint'=>'Check branch, category and priority before submitting.','cancel'=>'Cancel','submit_ticket'=>'Submit Ticket',
                'quick_templates'=>'Quick Templates','tpl_pos_btn'=>'POS / System Issue','tpl_printer_btn'=>'Printer Issue','tpl_network_btn'=>'Network / Internet Issue','tpl_maintenance_btn'=>'Maintenance Issue','tpl_help'=>'Click template to auto-fill description structure.',
                'suggested_knowledge'=>'Suggested Knowledge','select_category_articles'=>'Select a category to see related articles.','no_suggested_article'=>'No suggested article for this category yet.','views'=>'views','article'=>'Article','guide'=>'Guide',
                'live_preview'=>'Live Preview','preview_empty'=>'Preview will appear here...','no_title'=>'No title','no_description_yet'=>'No description yet',
                'good_ticket_checklist'=>'Good Ticket Checklist','checklist_1'=>'Write clear issue title.','checklist_2'=>'Select correct branch and asset.','checklist_3'=>'Attach photo/screenshot if possible.','checklist_4'=>'Choose urgent only when operation is affected.',
                'sla_target'=>'SLA target','controlled_sla'=>'Controlled by SLA Management.','selected_file'=>'Selected','hold_voice'=>'Hold Voice',
                'preview_title'=>'Title','preview_category'=>'Category','preview_priority'=>'Priority',
                'tpl_pos'=>"Issue / Problem:\n\nAffected Counter / Device:\n\nError Message:\n\nWhen it happened:\n\nWhat already tried:\n\nExpected result:",
                'tpl_printer'=>"Printer Type:\n\nPrinter Location:\n\nProblem:\n\nError Light / Message:\n\nPaper / Ribbon checked: Yes / No\n\nPhoto attached: Yes / No",
                'tpl_network'=>"Affected Area:\n\nInternet / WiFi / LAN:\n\nHow many devices affected:\n\nRouter / switch status:\n\nWhen it started:\n\nUrgency / business impact:",
                'tpl_maintenance'=>"Location:\n\nEquipment / Area:\n\nProblem Description:\n\nSafety Risk: Yes / No\n\nPhoto attached: Yes / No\n\nPreferred visit time:",
                'Low'=>'Low','Medium'=>'Medium','High'=>'High','Urgent'=>'Urgent','Critical'=>'Critical',
                'POS System'=>'POS System','Printer / Barcode Printer'=>'Printer / Barcode Printer','Network Issue'=>'Network Issue','Inventory Issue'=>'Inventory Issue','Purchasing Issue'=>'Purchasing Issue','Maintenance / Electrical'=>'Maintenance / Electrical','HR / Staff Issue'=>'HR / Staff Issue','Other'=>'Other'
            ],
            'ms' => [
                'create_ticket'=>'Cipta Tiket','create_ticket_subtitle'=>'Hantar isu cawangan, lampirkan bukti, dan salurkan kepada PIC yang betul.','back_to_ticket_list'=>'Kembali ke Senarai Tiket',
                'ticket_information'=>'Maklumat Tiket','title'=>'Tajuk','title_placeholder'=>'Contoh: POS tidak boleh sambung ke server','title_help'=>'Gunakan tajuk yang jelas dan ringkas supaya HQ cepat faham.',
                'branch'=>'Cawangan','person_in_charge'=>'Pegawai Bertanggungjawab','no_profile_pic'=>'Tiada PIC profil dipilih untuk pengguna ini','pic_help'=>'Pilih PIC yang bertanggungjawab untuk tiket ini.',
                'assign_to'=>'Tugaskan Kepada','unassigned'=>'Belum Ditugaskan','assign_help'=>'Admin / Head boleh menugaskan tiket ini terus semasa mencipta.',
                'asset_equipment'=>'Aset / Peralatan','no_permission_asset'=>'Tiada Kebenaran Memilih Aset','no_asset_selected'=>'Tiada Aset Dipilih','asset_no_permission_help'=>'Dikawal oleh Role Permission Matrix: aktifkan Asset List atau Select Asset In Ticket.','asset_none_help'=>'Tiada aset aktif/baik pulih dijumpai untuk cawangan dibenarkan. Tambah aset dalam Pengurusan Aset atau semak cawangan/status aset.','asset_help'=>'Dropdown aset dipautkan kepada Role Permission Matrix dan rekod Pengurusan Aset aktif/baik pulih.',
                'category'=>'Kategori','priority'=>'Keutamaan','description'=>'Penerangan','description_placeholder'=>'Terangkan masalah, mesej ralat, kaunter/peranti terjejas, dan tindakan yang telah dicuba.','characters'=>'aksara','hours'=>'jam',
                'upload_attachment'=>'Muat Naik Lampiran','camera'=>'Kamera','gallery_file'=>'Galeri / Fail','file_note'=>'Dibenarkan: foto kamera, imej galeri, audio/suara, PDF, Word, Excel. Maksimum 10MB.','submit_hint'=>'Semak cawangan, kategori dan keutamaan sebelum hantar.','cancel'=>'Batal','submit_ticket'=>'Hantar Tiket',
                'quick_templates'=>'Templat Pantas','tpl_pos_btn'=>'Isu POS / Sistem','tpl_printer_btn'=>'Isu Pencetak','tpl_network_btn'=>'Isu Rangkaian / Internet','tpl_maintenance_btn'=>'Isu Penyelenggaraan','tpl_help'=>'Klik templat untuk mengisi struktur penerangan secara automatik.',
                'suggested_knowledge'=>'Cadangan Pengetahuan','select_category_articles'=>'Pilih kategori untuk melihat artikel berkaitan.','no_suggested_article'=>'Tiada artikel cadangan untuk kategori ini.','views'=>'paparan','article'=>'Artikel','guide'=>'Panduan',
                'live_preview'=>'Pratonton Langsung','preview_empty'=>'Pratonton akan dipaparkan di sini...','no_title'=>'Tiada tajuk','no_description_yet'=>'Tiada penerangan lagi',
                'good_ticket_checklist'=>'Senarai Semak Tiket Baik','checklist_1'=>'Tulis tajuk isu yang jelas.','checklist_2'=>'Pilih cawangan dan aset yang betul.','checklist_3'=>'Lampirkan gambar/screenshot jika boleh.','checklist_4'=>'Pilih segera hanya apabila operasi terjejas.',
                'sla_target'=>'Sasaran SLA','controlled_sla'=>'Dikawal oleh Pengurusan SLA.','selected_file'=>'Dipilih','hold_voice'=>'Tahan Suara',
                'preview_title'=>'Tajuk','preview_category'=>'Kategori','preview_priority'=>'Keutamaan',
                'tpl_pos'=>"Isu / Masalah:\n\nKaunter / Peranti Terjejas:\n\nMesej Ralat:\n\nBila berlaku:\n\nTindakan yang telah dicuba:\n\nKeputusan yang dijangka:",
                'tpl_printer'=>"Jenis Pencetak:\n\nLokasi Pencetak:\n\nMasalah:\n\nLampu / Mesej Ralat:\n\nKertas / Ribbon telah diperiksa: Ya / Tidak\n\nGambar dilampirkan: Ya / Tidak",
                'tpl_network'=>"Kawasan Terjejas:\n\nInternet / WiFi / LAN:\n\nBerapa peranti terjejas:\n\nStatus router / switch:\n\nBila bermula:\n\nKesan segera / operasi:",
                'tpl_maintenance'=>"Lokasi:\n\nPeralatan / Kawasan:\n\nPenerangan Masalah:\n\nRisiko Keselamatan: Ya / Tidak\n\nGambar dilampirkan: Ya / Tidak\n\nMasa lawatan pilihan:",
                'Low'=>'Rendah','Medium'=>'Sederhana','High'=>'Tinggi','Urgent'=>'Segera','Critical'=>'Kritikal',
                'POS System'=>'Sistem POS','Printer / Barcode Printer'=>'Pencetak / Pencetak Barcode','Network Issue'=>'Isu Rangkaian','Inventory Issue'=>'Isu Inventori','Purchasing Issue'=>'Isu Pembelian','Maintenance / Electrical'=>'Penyelenggaraan / Elektrik','HR / Staff Issue'=>'Isu HR / Staf','Other'=>'Lain-lain'
            ],
            'zh' => [
                'create_ticket'=>'创建工单','create_ticket_subtitle'=>'提交分行问题、上传证明，并分配给正确负责人。','back_to_ticket_list'=>'返回工单列表',
                'ticket_information'=>'工单资料','title'=>'标题','title_placeholder'=>'例如：POS 无法连接服务器','title_help'=>'请使用清楚简短的标题，方便 HQ 快速理解。',
                'branch'=>'分行','person_in_charge'=>'负责人','no_profile_pic'=>'此用户没有选择 Profile PIC','pic_help'=>'选择负责此工单的 PIC。',
                'assign_to'=>'指派给','unassigned'=>'未指派','assign_help'=>'Admin / Head 创建时可以直接指派此工单。',
                'asset_equipment'=>'资产 / 设备','no_permission_asset'=>'无权限选择资产','no_asset_selected'=>'未选择资产','asset_no_permission_help'=>'由角色权限矩阵控制：启用 Asset List 或 Select Asset In Ticket。','asset_none_help'=>'没有找到允许分行的启用/维修资产。请在资产管理新增资产或检查资产分行/状态。','asset_help'=>'资产下拉列表已连接角色权限矩阵和启用/维修中的资产管理记录。',
                'category'=>'分类','priority'=>'优先级','description'=>'描述','description_placeholder'=>'描述问题、错误信息、受影响柜台/设备，以及你已经尝试过的处理方式。','characters'=>'字符','hours'=>'小时',
                'upload_attachment'=>'上传附件','camera'=>'拍照','gallery_file'=>'相册 / 文件','file_note'=>'允许：拍照、相册图片、语音/音频、PDF、Word、Excel。最大 10MB。','submit_hint'=>'提交前请检查分行、分类和优先级。','cancel'=>'取消','submit_ticket'=>'提交工单',
                'quick_templates'=>'快速模板','tpl_pos_btn'=>'POS / 系统问题','tpl_printer_btn'=>'打印机问题','tpl_network_btn'=>'网络 / Internet 问题','tpl_maintenance_btn'=>'维修问题','tpl_help'=>'点击模板可自动填入描述结构。',
                'suggested_knowledge'=>'推荐知识库','select_category_articles'=>'选择分类以查看相关知识库文章。','no_suggested_article'=>'此分类暂时没有推荐文章。','views'=>'浏览','article'=>'文章','guide'=>'指南',
                'live_preview'=>'实时预览','preview_empty'=>'预览会显示在这里...','no_title'=>'无标题','no_description_yet'=>'还没有描述',
                'good_ticket_checklist'=>'优质工单检查表','checklist_1'=>'填写清楚的问题标题。','checklist_2'=>'选择正确的分行和资产。','checklist_3'=>'可以的话请附上照片/截图。','checklist_4'=>'只有影响营运时才选择紧急。',
                'sla_target'=>'SLA 目标','controlled_sla'=>'由 SLA 管理控制。','selected_file'=>'已选择','hold_voice'=>'按住语音',
                'preview_title'=>'标题','preview_category'=>'分类','preview_priority'=>'优先级',
                'tpl_pos'=>"问题 / 故障：\n\n受影响柜台 / 设备：\n\n错误信息：\n\n发生时间：\n\n已经尝试的处理：\n\n期望结果：",
                'tpl_printer'=>"打印机类型：\n\n打印机位置：\n\n问题：\n\n错误灯号 / 信息：\n\n纸张 / Ribbon 已检查：是 / 否\n\n已附照片：是 / 否",
                'tpl_network'=>"受影响区域：\n\nInternet / WiFi / LAN：\n\n受影响设备数量：\n\nRouter / Switch 状态：\n\n开始时间：\n\n紧急程度 / 营运影响：",
                'tpl_maintenance'=>"位置：\n\n设备 / 区域：\n\n问题描述：\n\n安全风险：是 / 否\n\n已附照片：是 / 否\n\n希望到访时间：",
                'Low'=>'低','Medium'=>'中','High'=>'高','Urgent'=>'紧急','Critical'=>'严重',
                'POS System'=>'POS系统','Printer / Barcode Printer'=>'打印机 / 条码打印机','Network Issue'=>'网络问题','Inventory Issue'=>'库存问题','Purchasing Issue'=>'采购问题','Maintenance / Electrical'=>'维修 / 电气','HR / Staff Issue'=>'HR / 员工问题','Other'=>'其他'
            ]
        ];
        $lang = ct_lang_code();
        if(isset($dict[$lang][$key])) return $dict[$lang][$key];
        if(function_exists('__')) {
            $v = __($key);
            if($v !== $key) return $v;
        }
        return $dict['en'][$key] ?? $key;
    }
}
if(!function_exists('ct_label')) {
    function ct_label($value) {
        return ct_t((string)$value);
    }
}




// Load assets for ticket selection.
// Linked to Role Permission Matrix:
// - Admin: all active/repair assets.
// - User with Asset List module OR Select Asset In Ticket action: can select assets in Create Ticket.
// - Asset branch filtering is removed: permitted users can select assets from all branches.
$currentRole = function_exists('normalize_permission_role') ? normalize_permission_role($_SESSION['role'] ?? 'staff') : strtolower($_SESSION['role'] ?? 'staff');
$canSelectAssetInTicket = ($currentRole === 'admin')
    || current_user_has_permission('asset_list')
    || has_action_permission('select_asset_in_ticket');

$assetParams = [];
$assetSql = "
    SELECT id, asset_code, asset_name, branch, location, status
    FROM assets
    WHERE status IN ('Active','Repair')
";

if(!$canSelectAssetInTicket)
{
    $assetSql .= " AND 1=0 ";
}
else
{
    // Asset selection is intentionally global after latest requirement:
    // Staff / Head / Admin with permission can select assets from all branches.
    // Branch filtering is removed from Create Ticket > Asset / Equipment.
}

$assetSql .= " ORDER BY branch, asset_code ";

$stmtAssets = $pdo->prepare($assetSql);
$stmtAssets->execute($assetParams);
$assets = $stmtAssets->fetchAll(PDO::FETCH_ASSOC);

function local_csv_array($value)
{
    $items = [];
    foreach(explode(',', (string)$value) as $item)
    {
        $item = trim($item);
        if($item !== '' && $item !== '-')
        {
            $items[] = $item;
        }
    }
    return array_values(array_unique($items));
}


function ct_normalize_pic_value($pic)
{
    $pic = trim((string)$pic);
    $legacyPicLabels = ['负责人','負責人','Pegawai Bertanggungjawab','Person In Charge','person in charge','PIC'];
    return in_array($pic, $legacyPicLabels, true) ? 'PIC' : $pic;
}

function load_active_pic_list_for_ticket($pdo)
{
    try
    {
        $stmt = $pdo->query("SELECT pic_name FROM pic_master WHERE status = 1 ORDER BY pic_name ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $rows = array_values(array_unique(array_filter(array_map('ct_normalize_pic_value', $rows))));
        if(count($rows) > 0)
        {
            return $rows;
        }
    }
    catch(Exception $e)
    {
        // Fallback for older database before PIC Management table exists.
    }

    return ['KIAT','ANDY','LAS','KRISHNAN','HQ','ADMIN','PIC'];
}

function get_create_ticket_pic_list($pdo, $allPicList)
{
    $role = strtolower($_SESSION['role'] ?? 'staff');

    // Admin can route tickets to every active PIC from PIC Management.
    if($role === 'admin')
    {
        return $allPicList;
    }

    $allowed = [];

    try
    {
        // IMPORTANT:
        // Create Ticket > Person In Charge must follow Edit User > Profile PIC / Department.
        // Do NOT merge ticket_pic_access here, otherwise Staff/Head will see PICs that were
        // only meant for ticket visibility, not for create-ticket routing.
        $stmt = $pdo->prepare("SELECT department FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id'] ?? 0]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $allowed = local_csv_array($user['department'] ?? '');
    }
    catch(Exception $e)
    {
        $allowed = [];
    }

    // Session fallback only for the same Profile PIC / Department field.
    if(count($allowed) === 0)
    {
        $allowed = local_csv_array($_SESSION['department'] ?? '');
    }

    $allowed = array_values(array_unique(array_filter(array_map('ct_normalize_pic_value', $allowed))));

    // Only keep PICs that are active in PIC Management, preserving PIC Management ordering.
    $allowed = array_values(array_intersect($allPicList, $allowed));

    return $allowed;
}

$allPicList = load_active_pic_list_for_ticket($pdo);
$picList = get_create_ticket_pic_list($pdo, $allPicList);
$selectedDefaultPics = $picList;

// Load Assign To options from master table
$assignStmt = $pdo->query("
    SELECT assign_name, assign_email
    FROM assign_to_master
    WHERE status = 1
    ORDER BY assign_name
");

$assignList = $assignStmt->fetchAll(PDO::FETCH_ASSOC);

$canAssignOnCreate = has_action_permission('assign_ticket');

$branchMasterList = master_fetch_active_branches($pdo);
$categoryMasterList = master_fetch_active_categories($pdo);
$slaMasterList = master_fetch_active_sla($pdo);

// Knowledge Base suggestions for Create Ticket.
// It is linked by selected Ticket Category and Branch Scope.
$kbSuggestionPayload = [];
try {
    $stmtKb = $pdo->query("SELECT * FROM knowledge_base WHERE (status IS NULL OR status='Published') ORDER BY COALESCE(views,0) DESC, COALESCE(updated_at,created_at) DESC, title ASC LIMIT 200");
    $kbSuggestionPayload = $stmtKb->fetchAll(PDO::FETCH_ASSOC);
    foreach ($kbSuggestionPayload as &$kbRow) {
        if (function_exists('hd_kb_title')) $kbRow['title'] = hd_kb_title($pdo, $kbRow);
        if (function_exists('hd_kb_content')) $kbRow['content'] = hd_kb_content($pdo, $kbRow);
    }
    unset($kbRow);
} catch(Exception $e) {
    $kbSuggestionPayload = [];
}


if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $role = $_SESSION['role'] ?? 'staff';

    if($title == '' || $description == '')
    {
        die("Title and description are required.");
    }

    if($role == 'admin')
    {
        $branch = $_POST['branch'] ?? '';
    }
    elseif($role == 'head')
    {
        $allowedBranches = get_user_allowed_branches();
        $branch = $_POST['branch'] ?? '';

        if(!in_array($branch, $allowedBranches))
        {
            die("Access denied: invalid branch");
        }
    }
    else
    {
        $branch = $_SESSION['branch'] ?? '';
    }

    // Head and branch/staff users can now select Person In Charge when creating tickets.
    $department = trim($_POST['department'] ?? '');

    if($department != '-' && $department != '' && !in_array($department, $picList, true))
    {
        die("Invalid Person In Charge selected.");
    }

    $category = trim($_POST['category'] ?? 'Other');
    $priority = trim($_POST['priority'] ?? '');

    if(!master_category_exists($categoryMasterList, $category))
    {
        die("Invalid category selected. Please enable it in Category Management first.");
    }

    if($priority === '')
    {
        $priority = master_category_default_priority($categoryMasterList, $category);
    }

    $sla_hours = master_priority_hours($slaMasterList, $priority);
    if($sla_hours === null)
    {
        die("Invalid priority selected. Please enable it in SLA Management first.");
    }
    $assigned_to = '';

    if(has_action_permission('assign_ticket'))
    {
        $assigned_to = trim($_POST['assigned_to'] ?? '');
    }

    $asset_id = (int)($_POST['asset_id'] ?? 0);
    $attachment = null;
    $ticketAttachments = [];

    if($branch == '')
    {
        die("Branch is required.");
    }

    if($department == '')
    {
        $department = '-';
    }

    if($assigned_to != '')
    {
        $stmtAssignCheck = $pdo->prepare("
            SELECT assign_name
            FROM assign_to_master
            WHERE assign_name = ?
            AND status = 1
            LIMIT 1
        ");

        $stmtAssignCheck->execute([$assigned_to]);

        if(!$stmtAssignCheck->fetch())
        {
            die("Invalid assigned user selected.");
        }
    }


    if($asset_id > 0)
    {
        if(!$canSelectAssetInTicket)
        {
            die("Access denied: no permission to select asset in ticket.");
        }

        $stmtAssetCheck = $pdo->prepare("
            SELECT id, branch, asset_code, asset_name, status
            FROM assets
            WHERE id = ?
            AND status IN ('Active','Repair')
            LIMIT 1
        ");
        $stmtAssetCheck->execute([$asset_id]);
        $selectedAsset = $stmtAssetCheck->fetch(PDO::FETCH_ASSOC);

        if(!$selectedAsset)
        {
            die("Invalid asset selected.");
        }

        if(($role != 'admin') && !current_user_has_permission('asset_list') && (($selectedAsset['branch'] ?? '') != $branch))
        {
            die("Access denied: invalid asset branch.");
        }
    }
    else
    {
        $asset_id = null;
    }

    $due_date = date('Y-m-d H:i:s', strtotime("+".$sla_hours." hours"));

    // Ticket No format:
    // Branch-YearMonth-RunningNumber
    // Example: KB-202506-001
    // Running number resets every month and is counted separately by branch.
    $monthcode = date('Ym');

    $stmtTicket = $pdo->prepare("
        SELECT ticket_no
        FROM tickets
        WHERE branch = ?
        AND ticket_no LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmtTicket->execute([
        $branch,
        $branch . '-' . $monthcode . '-%'
    ]);

    $lastTicket = $stmtTicket->fetch(PDO::FETCH_ASSOC);

    if($lastTicket)
    {
        preg_match('/(\d+)$/', $lastTicket['ticket_no'], $m);
        $running = isset($m[1]) ? intval($m[1]) + 1 : 1;
    }
    else
    {
        $running = 1;
    }

    $defaultTicketStatus = ticket_status_default_open_name($pdo);
    $actorName = $_SESSION['username'] ?? ('User ID '.$_SESSION['user_id']);

    $ticket_no =
        $branch .
        '-' .
        $monthcode .
        '-' .
        str_pad($running, 3, '0', STR_PAD_LEFT);

    hd_ta_ensure_table($pdo);
    $ticketAttachments = hd_ta_upload_many(
        $_FILES,
        hd_entity_upload_dir('tickets', $ticket_no),
        ['attachment','attachments','attachmentCamera','attachmentGallery','attachmentVoice','voiceAttachment']
    );
    if(!empty($ticketAttachments)) {
        $attachment = $ticketAttachments[0]['path'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO tickets
        (
            ticket_no,
            title,
            description,
            branch,
            department,
            assigned_to,
            category,
            priority,
            asset_id,
            attachment,
            created_by,
            sla_hours,
            due_date,
            status,
            last_update,
            last_updated_by
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            NOW(),
            ?
        )
    ");

    $stmt->execute([
        $ticket_no,
        $title,
        $description,
        $branch,
        $department,
        $assigned_to,
        $category,
        $priority,
        $asset_id,
        $attachment,
        $_SESSION['user_id'],
        $sla_hours,
        $due_date,
        $defaultTicketStatus,
        $actorName
    ]);

    $ticket_id = (int)$pdo->lastInsertId();

    $ticketId = $ticket_id;
    if(!empty($ticketAttachments)) {
        hd_ta_insert_many($pdo, $ticketId, null, $ticketAttachments, (int)$_SESSION['user_id']);
    }

    audit_log(
        $pdo,
        'Create Ticket',
        'Created Ticket '.$ticket_no
    );

    ticket_history(
        $pdo,
        $ticketId,
        '工单已创建',
        $_SESSION['user_id']
    );

    if(!empty($asset_id))
    {
        ticket_history(
            $pdo,
            $ticketId,
            'Asset Linked',
            $_SESSION['user_id']
        );
    }

    if($assigned_to != '')
    {
        ticket_history(
            $pdo,
            $ticketId,
            'Assigned To: '.$assigned_to,
            $_SESSION['user_id']
        );
    }

    if(!empty($ticketAttachments))
    {
        ticket_history(
            $pdo,
            $ticketId,
            'Attachment Uploaded ('.count($ticketAttachments).')',
            $_SESSION['user_id']
        );
    }

    notify_ticket_created($pdo, [
        'id' => $ticket_id,
        'ticket_no' => $ticket_no,
        'title' => $title,
        'branch' => $branch,
        'department' => $department,
        'assigned_to' => $assigned_to,
        'category' => $category,
        'priority' => $priority,
        'asset_id' => $asset_id,
        'due_date' => $due_date,
        'created_by' => $_SESSION['user_id']
    ]);

    notify_ticket_created_internal($pdo, [
        'id' => $ticket_id,
        'ticket_no' => $ticket_no,
        'title' => $title,
        'branch' => $branch,
        'department' => $department,
        'assigned_to' => $assigned_to,
        'category' => $category,
        'priority' => $priority,
        'asset_id' => $asset_id,
        'due_date' => $due_date,
        'created_by' => $_SESSION['user_id']
    ]);

    $_SESSION['success_message'] = 'Ticket submitted successfully.';
    header('Location: ticket_list.php');
    exit;
}
?>

<style>
.create-ticket-page{max-width:1180px;margin:0 auto 28px auto}.ct-hero{display:flex;justify-content:space-between;gap:16px;align-items:center;background:linear-gradient(135deg,#fff,#f7f9ff);border:1px solid #e8edf7;border-radius:18px;padding:22px 24px;margin-bottom:18px;box-shadow:0 8px 24px rgba(18,38,63,.06)}.ct-title-wrap{display:flex;gap:14px;align-items:center}.ct-icon{width:54px;height:54px;border-radius:16px;background:#eef2ff;display:flex;align-items:center;justify-content:center;font-size:26px}.ct-hero h2{margin:0;font-weight:800;color:#172033}.ct-hero p{margin:5px 0 0;color:#667085}.ct-grid{display:grid;grid-template-columns:1.6fr .9fr;gap:18px}.ct-card{background:#fff;border:1px solid #e8edf7;border-radius:18px;box-shadow:0 8px 24px rgba(18,38,63,.06);overflow:hidden}.ct-card-header{padding:16px 18px;border-bottom:1px solid #eef2f7;font-weight:800;color:#1f2937;display:flex;align-items:center;gap:10px}.ct-card-body{padding:18px}.ct-section-title{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#667085;font-weight:800;margin:6px 0 14px}.form-label{font-weight:700;color:#344054}.form-control,.form-select{border-radius:12px;border-color:#d9e1ec;padding:10px 12px}.form-control:focus,.form-select:focus{box-shadow:0 0 0 .2rem rgba(54,96,255,.12);border-color:#5b6cff}.help-text{font-size:12px;color:#667085;margin-top:6px}.ct-two{display:grid;grid-template-columns:1fr 1fr;gap:14px}.ct-three{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}.quick-btn{width:100%;text-align:left;border:1px solid #e1e8f5;background:#fff;border-radius:14px;padding:13px 14px;margin-bottom:10px;font-weight:700;color:#344054;transition:.15s}.quick-btn:hover{background:#f6f8ff;border-color:#7c8cff;transform:translateY(-1px)}.priority-info{border-radius:14px;background:#f8fafc;border:1px solid #e8edf7;padding:14px;margin-top:12px}.priority-pill{display:inline-block;border-radius:999px;padding:5px 10px;font-weight:800;font-size:12px}.pill-low{background:#e8f5e9;color:#188038}.pill-medium{background:#eef4ff;color:#2454d6}.pill-high{background:#fff4e5;color:#b54708}.pill-urgent{background:#ffe9e9;color:#b42318}.upload-box{border:1.5px dashed #cbd5e1;border-radius:16px;padding:14px;background:#fbfdff}.hd-mobile-upload-input{display:none!important;}.file-note{font-size:12px;color:#667085;margin-top:8px}
.hd-selected-files{margin-top:12px;border:1px solid #e5e7eb;background:#fff;border-radius:14px;padding:10px;display:grid;gap:8px}
.hd-selected-files-head{display:flex;align-items:center;justify-content:space-between;gap:8px;color:#334155;font-size:13px;margin-bottom:2px}
.hd-selected-file-row{display:grid;grid-template-columns:auto minmax(0,1fr) auto auto;align-items:center;gap:8px;border:1px solid #eef2f7;background:#f8fafc;border-radius:12px;padding:8px 10px}
.hd-selected-file-icon{font-size:18px;line-height:1}.hd-selected-file-name{font-weight:800;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.hd-selected-file-size{font-size:12px;color:#64748b;white-space:nowrap}.hd-selected-file-remove{border:1px solid #fecaca;background:#fff5f5;color:#dc2626;border-radius:10px;padding:6px 10px;font-weight:900}
@media(max-width:768px){.hd-selected-file-row{grid-template-columns:auto minmax(0,1fr) auto}.hd-selected-file-size{display:none}.hd-selected-file-remove{padding:7px 10px}.hd-selected-files-head{font-size:13px}}
.preview-box{min-height:140px;border:1px solid #e8edf7;background:#fbfdff;border-radius:14px;padding:14px;white-space:pre-wrap;color:#344054;font-size:13px}.ct-live-preview-top{margin-bottom:18px}.ct-live-preview-top .preview-box{min-height:180px;font-size:14px;line-height:1.7}.submit-bar{position:sticky;bottom:0;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border-top:1px solid #eef2f7;padding:14px 18px;display:flex;justify-content:space-between;align-items:center;gap:12px}.btn-main{background:linear-gradient(135deg,#3454f5,#6938ef);border:0;color:white;border-radius:12px;padding:10px 18px;font-weight:800}.btn-soft{border:1px solid #d0d5dd;background:#fff;border-radius:12px;padding:10px 14px;color:#344054;text-decoration:none}.ct-top-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;flex:0 0 auto}.ct-top-actions .btn-soft,.ct-top-actions .btn-main{min-height:42px;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap}.ct-top-actions .btn-main{min-width:118px}.ct-top-actions .btn-soft{min-width:86px}.required-star{color:#dc2626}.meta-list{display:grid;gap:10px}.meta-item{display:flex;gap:10px;align-items:flex-start;padding:12px;border:1px solid #edf2f7;border-radius:14px;background:#fbfdff}.kb-suggest-link{text-decoration:none;font-weight:800;color:#2563eb}.kb-suggest-meta{font-size:12px;color:#667085;margin-top:3px}.meta-dot{width:32px;height:32px;border-radius:10px;background:#eef2ff;display:flex;align-items:center;justify-content:center;flex:0 0 auto}.char-counter{font-size:12px;color:#667085;text-align:right;margin-top:5px}.alert{border-radius:14px}.success-actions{margin-bottom:15px}.success-actions a{margin-right:8px}
@media(max-width:900px){.ct-grid,.ct-two,.ct-three{grid-template-columns:1fr}.ct-hero{align-items:flex-start;flex-direction:column}.submit-bar{position:static;flex-direction:column;align-items:stretch}.btn-main,.btn-soft{width:100%;text-align:center}}

@media(max-width:768px){
.create-ticket-page{margin:0 auto 80px}.ct-hero{padding:16px;border-radius:14px}.ct-icon{width:44px;height:44px}.ct-hero h2{font-size:22px}
.ct-card{border-radius:14px}.ct-card-body{padding:14px}.ct-card-header{padding:13px 14px}
.ct-grid,.ct-two,.ct-three{display:block!important}.ct-two>*,.ct-three>*{margin-bottom:12px}
.form-control,.form-select{font-size:16px;min-height:46px}.quick-btn{padding:12px}
.submit-bar{position:fixed;left:0;right:0;bottom:66px;background:#fff;border-top:1px solid #e5e7eb;padding:10px;z-index:1040;box-shadow:0 -8px 18px rgba(15,23,42,.08)}
.submit-bar .btn-main,.submit-bar .btn-soft{width:100%}
}


/* Create Ticket Mobile Optimization */
@media(max-width:768px){
    .create-ticket-page{
        margin:0 auto 76px!important;
        padding:0 2px!important;
    }
    .ct-hero{
        display:block!important;
        padding:16px!important;
        border-radius:18px!important;
        margin-bottom:14px!important;
    }
    .ct-title-wrap{
        align-items:flex-start!important;
        gap:12px!important;
    }
    .ct-icon{
        width:44px!important;
        height:44px!important;
        border-radius:14px!important;
        font-size:22px!important;
        flex:0 0 44px!important;
    }
    .ct-hero h2{
        font-size:28px!important;
        line-height:1.15!important;
        margin-bottom:6px!important;
    }
    .ct-hero p{
        font-size:16px!important;
        line-height:1.45!important;
        margin-bottom:14px!important;
    }
    .ct-hero .btn-soft{
        width:100%!important;
        min-height:48px!important;
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        font-size:16px!important;
        margin-top:12px!important;
    }
    .ct-grid,.ct-two,.ct-three{
        display:block!important;
        grid-template-columns:1fr!important;
    }
    .ct-card{
        border-radius:18px!important;
        margin-bottom:14px!important;
        overflow:hidden!important;
    }
    .ct-card-header{
        padding:14px 16px!important;
        font-size:18px!important;
    }
    .ct-card-body{
        padding:16px!important;
    }
    .mb-3{
        margin-bottom:16px!important;
    }
    .form-label{
        font-size:16px!important;
        margin-bottom:8px!important;
    }
    .form-control,.form-select{
        min-height:52px!important;
        font-size:16px!important;
        border-radius:15px!important;
        padding:12px 14px!important;
    }
    textarea.form-control{
        min-height:150px!important;
    }
    .help-text,.file-note{
        font-size:13px!important;
        line-height:1.45!important;
    }
    .upload-box{
        border-radius:16px!important;
        padding:14px!important;
    }
    .quick-btn{
        min-height:50px!important;
        border-radius:15px!important;
        font-size:15px!important;
    }

    /* Important: do not let submit bar cover later fields on mobile */
    .submit-bar{
        position:static!important;
        bottom:auto!important;
        margin:16px 0 0!important;
        padding:14px 0 0!important;
        border-top:1px solid #eef2f7!important;
        background:#fff!important;
        box-shadow:none!important;
        backdrop-filter:none!important;
        display:block!important;
    }
    .submit-bar > span{
        display:block!important;
        margin-bottom:10px!important;
        font-size:13px!important;
    }
    .submit-bar > div{
        display:grid!important;
        grid-template-columns:1fr!important;
        gap:10px!important;
    }
    .btn-main,.btn-soft{
        width:100%!important;
        min-height:52px!important;
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        border-radius:15px!important;
        font-size:17px!important;
    }

    /* Put support panels below ticket fields with good spacing */
    .meta-list{
        gap:10px!important;
    }
    .meta-item{
        border-radius:15px!important;
    }
}



/* Move Create Ticket actions to top right */
@media(max-width:768px){
    .ct-top-actions{
        width:100%!important;
        display:grid!important;
        grid-template-columns:1fr 1fr!important;
        gap:10px!important;
        margin-top:12px!important;
    }
    .ct-top-actions .btn-soft,
    .ct-top-actions .btn-main{
        width:100%!important;
        min-height:50px!important;
        border-radius:15px!important;
        font-size:16px!important;
        margin-top:0!important;
    }
}

/* Optimized Live Preview Card */
.ct-live-preview-top{margin-bottom:18px}
.ct-preview-card{border:1px solid #e5e7eb;background:linear-gradient(180deg,#ffffff,#fbfdff);border-radius:18px;padding:14px}
.ct-preview-title-row{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:12px}
.ct-preview-main-title{font-size:20px;font-weight:900;color:#0f172a;line-height:1.25;word-break:break-word}
.ct-preview-badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;background:#eef4ff;color:#1d4ed8;border:1px solid #dbeafe;padding:6px 10px;font-size:12px;font-weight:900;white-space:nowrap}
.ct-preview-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:12px}
.ct-preview-item{border:1px solid #eef2f7;background:#fff;border-radius:14px;padding:10px 12px;min-width:0}
.ct-preview-label{font-size:11px;color:#64748b;font-weight:900;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
.ct-preview-value{font-size:14px;color:#0f172a;font-weight:800;line-height:1.35;word-break:break-word}
.ct-preview-desc{border:1px solid #eef2f7;background:#f8fafc;border-radius:14px;padding:12px;margin-top:10px}
.ct-preview-desc-title{font-weight:900;color:#334155;margin-bottom:6px;font-size:13px}
.ct-preview-desc-body{white-space:pre-wrap;color:#334155;line-height:1.55;font-size:14px;word-break:break-word}
.ct-preview-attachments{display:grid;gap:7px;margin-top:6px}
.ct-preview-file{display:flex;align-items:center;gap:8px;border:1px solid #e5e7eb;background:#fff;border-radius:12px;padding:8px 10px;min-width:0}
.ct-preview-file-icon{font-size:16px;flex:0 0 auto}.ct-preview-file-name{font-weight:800;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ct-preview-file-size{margin-left:auto;color:#64748b;font-size:12px;white-space:nowrap}
.ct-preview-empty{color:#94a3b8;font-style:italic;font-weight:700}
@media(max-width:768px){.ct-live-preview-top .ct-card-body{padding:12px!important}.ct-preview-card{padding:12px;border-radius:16px}.ct-preview-title-row{display:block}.ct-preview-main-title{font-size:18px;margin-bottom:8px}.ct-preview-badge{font-size:11px;padding:5px 9px}.ct-preview-grid{grid-template-columns:1fr;gap:8px}.ct-preview-item{padding:9px 10px}.ct-preview-value{font-size:13px}.ct-preview-desc-body{font-size:13px}.ct-preview-file-size{display:none}}

</style>

<div class="create-ticket-page">
    <div class="ct-hero">
        <div class="ct-title-wrap">
            <div class="ct-icon">🎫</div>
            <div>
                <h2><?= htmlspecialchars(ct_t('create_ticket')); ?></h2>
                <p><?= htmlspecialchars(ct_t('create_ticket_subtitle')); ?></p>
            </div>
        </div>
        <div class="ct-top-actions">
            <a href="ticket_list.php" class="btn-soft"><?= htmlspecialchars(ct_t('cancel')); ?></a>
            <button type="submit" form="ticketForm" class="btn-main"><?= htmlspecialchars(ct_t('submit_ticket')); ?></button>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" id="ticketForm">
        <div class="ct-card mb-3 ct-live-preview-top">
            <div class="ct-card-header">👁️ <?= htmlspecialchars(ct_t('live_preview')); ?></div>
            <div class="ct-card-body">
                <div class="preview-box" id="livePreview"><?= htmlspecialchars(ct_t('preview_empty')); ?></div>
            </div>
        </div>

        <div class="ct-grid">
            <div class="ct-card">
                <div class="ct-card-header">📝 <?= htmlspecialchars(ct_t('ticket_information')); ?></div>
                <div class="ct-card-body">
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars(ct_t('title')); ?> <span class="required-star">*</span></label>
                        <input type="text" name="title" id="title" class="form-control" required placeholder="<?= htmlspecialchars(ct_t('title_placeholder')); ?>">
                        <div class="help-text"><?= htmlspecialchars(ct_t('title_help')); ?></div>
                    </div>

                    <div class="ct-two">
                        <div class="mb-3">
                            <label class="form-label"><?= htmlspecialchars(ct_t('branch')); ?> <span class="required-star">*</span></label>
                            <?php if(($_SESSION['role'] ?? 'staff') == 'admin'): ?>
                            <select name="branch" id="branch" class="form-select" required>
                                <?php foreach($branchMasterList as $b): ?>
                                <option class="hd-no-translate" value="<?= htmlspecialchars($b['branch_code']); ?>"><?= htmlspecialchars($b['branch_code'].' - '.$b['branch_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php elseif(($_SESSION['role'] ?? 'staff') == 'head'): ?>
                            <select name="branch" id="branch" class="form-select" required>
                                <?php foreach(get_user_allowed_branches() as $allowedBranch): ?>
                                <option value="<?= htmlspecialchars($allowedBranch); ?>"><?= htmlspecialchars($allowedBranch); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <input type="text" id="branch" class="form-control" value="<?= htmlspecialchars($_SESSION['branch'] ?? ''); ?>" readonly>
                            <input type="hidden" name="branch" value="<?= htmlspecialchars($_SESSION['branch'] ?? ''); ?>">
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?= htmlspecialchars(ct_t('person_in_charge')); ?> <span class="required-star">*</span></label>
                            <select name="department" id="department" class="form-select" required>
                                <?php if(count($picList) === 0): ?>
                                <option value="" selected disabled><?= htmlspecialchars(ct_t('no_profile_pic')); ?></option>
                                <?php else: ?>
                                <?php foreach($picList as $pic): $pic = ct_normalize_pic_value($pic); ?>
                                <option value="<?= htmlspecialchars($pic); ?>" <?= in_array($pic, $selectedDefaultPics, true) ? 'selected' : ''; ?>><?= htmlspecialchars($pic); ?></option>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="help-text"><?= htmlspecialchars(ct_t('pic_help')); ?></div>
                        </div>
                    </div>

                    <?php if($canAssignOnCreate): ?>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars(ct_t('assign_to')); ?></label>
                        <select name="assigned_to" id="assigned_to" class="form-select">
                            <option value=""><?= htmlspecialchars(ct_t('unassigned')); ?></option>
                            <?php foreach($assignList as $assign): ?>
                            <option value="<?= htmlspecialchars($assign['assign_name']); ?>"><?= htmlspecialchars($assign['assign_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text"><?= htmlspecialchars(ct_t('assign_help')); ?></div>
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars(ct_t('asset_equipment')); ?></label>
                        <select name="asset_id" id="asset_id" class="form-select" <?= !$canSelectAssetInTicket ? 'disabled' : ''; ?>>
                            <option value=""><?= !$canSelectAssetInTicket ? htmlspecialchars(ct_t('no_permission_asset')) : htmlspecialchars(ct_t('no_asset_selected')); ?></option>
                            <?php foreach($assets as $asset): ?>
                            <option value="<?= (int)$asset['id']; ?>" data-branch="<?= htmlspecialchars($asset['branch'] ?? ''); ?>"><?= htmlspecialchars($asset['asset_code'].' - '.$asset['asset_name'].' ['.($asset['branch'] ?? '-').']'.(!empty($asset['location']) ? ' - '.$asset['location'] : '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">
                            <?php if(!$canSelectAssetInTicket): ?>
                                <?= htmlspecialchars(ct_t('asset_no_permission_help')); ?>
                            <?php elseif(count($assets) === 0): ?>
                                <?= htmlspecialchars(ct_t('asset_none_help')); ?>
                            <?php else: ?>
                                <?= htmlspecialchars(ct_t('asset_help')); ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="ct-two">
                        <div class="mb-3">
                            <label class="form-label"><?= htmlspecialchars(ct_t('category')); ?> <span class="required-star">*</span></label>
                            <select name="category" id="category" class="form-select" required>
                                <?php foreach($categoryMasterList as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['category_name']); ?>" data-default-priority="<?= htmlspecialchars($cat['default_priority']); ?>"><?= htmlspecialchars(ct_label($cat['category_name'])); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= htmlspecialchars(ct_t('priority')); ?> <span class="required-star">*</span></label>
                            <select name="priority" id="priority" class="form-select" required>
                                <?php foreach($slaMasterList as $sla): ?>
                                <option value="<?= htmlspecialchars($sla['priority_name']); ?>" data-sla-hours="<?= (int)$sla['sla_hours']; ?>" <?= ($sla['priority_name'] == 'Medium') ? 'selected' : ''; ?>><?= htmlspecialchars(ct_label($sla['priority_name'])); ?> (<?= (int)$sla['sla_hours']; ?> <?= htmlspecialchars(ct_t('hours') ?: 'hours'); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="priority-info" id="priorityInfo"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars(ct_t('description')); ?> <span class="required-star">*</span></label>
                        <textarea name="description" id="description" rows="8" class="form-control" required placeholder="<?= htmlspecialchars(ct_t('description_placeholder')); ?>"></textarea>
                        <div class="char-counter"><span id="descCount">0</span> <?= htmlspecialchars(ct_t('characters')); ?></div>
                    </div>

                    <div class="mb-3 upload-box">
                        <label class="form-label"><?= htmlspecialchars(ct_t('upload_attachment')); ?></label>
                        <div class="mobile-upload-actions mb-2">
                            <label class="mobile-upload-btn camera-btn" for="attachmentCamera"><i class="bi bi-camera-fill"></i> <?= h(ct_t('camera')); ?></label>
                            <label class="mobile-upload-btn gallery-btn" for="attachmentGallery"><i class="bi bi-images"></i> <?= h(ct_t('gallery_file')); ?></label>
                            <button type="button" class="mobile-upload-btn voice-btn hd-wa-record-btn" data-target-input="attachment" data-preview="filePreview"><i class="bi bi-mic-fill"></i> <?= h(ct_t('hold_voice')); ?></button>
                        </div>
                        <input type="file" name="attachments[]" id="attachmentCamera" class="form-control hd-mobile-upload-input" accept="image/*" capture="environment" multiple data-hd-file-ui="1">
                        <input type="file" name="attachments[]" id="attachmentGallery" class="form-control hd-mobile-upload-input" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.mp3,.m4a,.wav,.aac,.ogg,.webm,.mp4,.mov" multiple data-hd-file-ui="1">
                        <input type="file" name="attachments[]" id="attachment" class="form-control desktop-upload-input" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.mp3,.m4a,.wav,.aac,.ogg,.webm,.mp4,.mov" multiple>
                        <div class="file-note"><?= htmlspecialchars(ct_t('file_note')); ?></div>
                        <div class="help-text" id="filePreview"></div>
                    </div>
                </div>

            </div>

            <div>
                <div class="ct-card mb-3">
                    <div class="ct-card-header">⚡ <?= htmlspecialchars(ct_t('quick_templates')); ?></div>
                    <div class="ct-card-body">
                        <button type="button" class="quick-btn" data-template="pos">🖥️ <?= htmlspecialchars(ct_t('tpl_pos_btn')); ?></button>
                        <button type="button" class="quick-btn" data-template="printer">🖨️ <?= htmlspecialchars(ct_t('tpl_printer_btn')); ?></button>
                        <button type="button" class="quick-btn" data-template="network">🌐 <?= htmlspecialchars(ct_t('tpl_network_btn')); ?></button>
                        <button type="button" class="quick-btn" data-template="maintenance">🔧 <?= htmlspecialchars(ct_t('tpl_maintenance_btn')); ?></button>
                        <div class="help-text"><?= htmlspecialchars(ct_t('tpl_help')); ?></div>
                    </div>
                </div>

                <div class="ct-card mb-3">
                    <div class="ct-card-header">📚 <?= htmlspecialchars(ct_t('suggested_knowledge')); ?></div>
                    <div class="ct-card-body">
                        <div id="kbSuggestedList" class="meta-list">
                            <div class="help-text"><?= htmlspecialchars(ct_t('select_category_articles')); ?></div>
                        </div>
                    </div>
                </div>


                <div class="ct-card">
                    <div class="ct-card-header">✅ <?= htmlspecialchars(ct_t('good_ticket_checklist')); ?></div>
                    <div class="ct-card-body meta-list">
                        <div class="meta-item"><div class="meta-dot">1</div><div><?= htmlspecialchars(ct_t('checklist_1')); ?></div></div>
                        <div class="meta-item"><div class="meta-dot">2</div><div><?= htmlspecialchars(ct_t('checklist_2')); ?></div></div>
                        <div class="meta-item"><div class="meta-dot">3</div><div><?= htmlspecialchars(ct_t('checklist_3')); ?></div></div>
                        <div class="meta-item"><div class="meta-dot">4</div><div><?= htmlspecialchars(ct_t('checklist_4')); ?></div></div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(function(){
const desc=document.getElementById('description');
const title=document.getElementById('title');
const category=document.getElementById('category');
const priority=document.getElementById('priority');
const department=document.getElementById('department');
const assignedTo=document.getElementById('assigned_to');
const asset=document.getElementById('asset_id');
const preview=document.getElementById('livePreview');
const count=document.getElementById('descCount');
const priorityInfo=document.getElementById('priorityInfo');
const attachment=document.getElementById('attachment');
const mobileAttachments=[document.getElementById('attachmentCamera'),document.getElementById('attachmentGallery')].filter(Boolean);
const filePreview=document.getElementById('filePreview');
const kbSuggestedList=document.getElementById('kbSuggestedList');
const kbArticles=<?= json_encode($kbSuggestionPayload, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const CT_I18N = <?= json_encode([
    'no_suggested_article'=>ct_t('no_suggested_article'),
    'article'=>ct_t('article'),
    'guide'=>ct_t('guide'),
    'views'=>ct_t('views'),
    'no_title'=>ct_t('no_title'),
    'no_description_yet'=>ct_t('no_description_yet'),
    'preview_title'=>ct_t('preview_title'),
    'preview_category'=>ct_t('preview_category'),
    'preview_priority'=>ct_t('preview_priority'),
    'branch'=>ct_t('branch'),
    'person_in_charge'=>ct_t('person_in_charge'),
    'assign_to'=>ct_t('assign_to'),
    'asset_equipment'=>ct_t('asset_equipment'),
    'description'=>ct_t('description'),
    'upload_attachment'=>ct_t('upload_attachment'),
    'sla_target'=>ct_t('sla_target'),
    'controlled_sla'=>ct_t('controlled_sla'),
    'selected_file'=>ct_t('selected_file'),
    'hours'=>ct_t('hours'),
    'tpl_pos'=>ct_t('tpl_pos'),
    'tpl_printer'=>ct_t('tpl_printer'),
    'tpl_network'=>ct_t('tpl_network'),
    'tpl_maintenance'=>ct_t('tpl_maintenance')
], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;

function currentBranchValue(){
 const branchEl=document.getElementById('branch');
 return branchEl ? (branchEl.value || '') : '';
}
function scopeAllows(article, branch){
 const scope=(article.branch_scope||'').trim();
 if(!scope || scope.toUpperCase()==='ALL') return true;
 if(!branch) return false;
 return scope.split(',').map(x=>x.trim()).includes(branch);
}
function updateKbSuggestions(){
 const c=category.value||'';
 const branch=currentBranchValue();
 let items=kbArticles.filter(a=>{
   const cat=(a.category||'');
   const tags=(a.tags||'');
   const title=(a.title||'');
   return scopeAllows(a, branch) && (cat===c || tags.includes(c) || title.toLowerCase().includes(c.toLowerCase()));
 }).slice(0,5);
 if(!kbSuggestedList) return;
 if(items.length===0){
   kbSuggestedList.innerHTML='<div class="help-text">'+escapeHtml(CT_I18N.no_suggested_article)+'</div>';
   return;
 }
 kbSuggestedList.innerHTML=items.map((a,i)=>`<div class="meta-item"><div class="meta-dot">${i+1}</div><div><a class="kb-suggest-link" href="view_article.php?id=${a.id}" target="_blank">${escapeHtml(a.title||CT_I18N.article)}</a><div class="kb-suggest-meta">${escapeHtml(a.knowledge_type||CT_I18N.guide)} · ${escapeHtml(a.category||'-')} · ${parseInt(a.views||0)} ${escapeHtml(CT_I18N.views)}</div></div></div>`).join('');
}
function escapeHtml(s){return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
const templates={
pos:CT_I18N.tpl_pos,
printer:CT_I18N.tpl_printer,
network:CT_I18N.tpl_network,
maintenance:CT_I18N.tpl_maintenance
};
function selectedOptionText(el, fallback){
 if(!el) return fallback || '-';
 if(el.tagName && el.tagName.toLowerCase()==='select'){
   const opt=el.options[el.selectedIndex];
   return opt ? opt.textContent.trim() : (el.value || fallback || '-');
 }
 return el.value || fallback || '-';
}
function updatePreview(){
 if(!preview) return;
 const t=title.value.trim()||('('+CT_I18N.no_title+')');
 const branchText=selectedOptionText(document.getElementById('branch'), '-');
 const picText=selectedOptionText(department, '-');
 const assignText=assignedTo ? selectedOptionText(assignedTo, '-') : '-';
 const assetText=asset ? selectedOptionText(asset, '-') : '-';
 const c=(category.options[category.selectedIndex] ? category.options[category.selectedIndex].textContent.trim() : (category.value||'-'));
 const p=(priority.options[priority.selectedIndex] ? priority.options[priority.selectedIndex].textContent.trim() : (priority.value||'-'));
 const opt=priority.options[priority.selectedIndex];
 const slaHours=opt ? (opt.dataset.slaHours || '-') : '-';
 const d=desc.value.trim()||('('+CT_I18N.no_description_yet+')');
 const filesHtml=(typeof hdSelectedFiles !== 'undefined' && hdSelectedFiles.length)
   ? '<div class="ct-preview-attachments">' + hdSelectedFiles.map(function(f){
       return '<div class="ct-preview-file">'
         + '<span class="ct-preview-file-icon">' + fileIcon(f) + '</span>'
         + '<span class="ct-preview-file-name" title="' + escapeHtml(f.name||'attachment') + '">' + escapeHtml(f.name||'attachment') + '</span>'
         + '<span class="ct-preview-file-size">' + escapeHtml(formatFileSize(f.size||0)) + '</span>'
         + '</div>';
     }).join('') + '</div>'
   : '<div class="ct-preview-empty">-</div>';
 const safeTitle=escapeHtml(t);
 preview.innerHTML =
   '<div class="ct-preview-card">'
   + '<div class="ct-preview-title-row">'
   + '<div class="ct-preview-main-title">' + safeTitle + '</div>'
   + '<div class="ct-preview-badge">⏱ ' + escapeHtml(slaHours) + ' ' + escapeHtml(CT_I18N.hours) + '</div>'
   + '</div>'
   + '<div class="ct-preview-grid">'
   + '<div class="ct-preview-item"><div class="ct-preview-label">' + escapeHtml(CT_I18N.branch) + '</div><div class="ct-preview-value">' + escapeHtml(branchText) + '</div></div>'
   + '<div class="ct-preview-item"><div class="ct-preview-label">' + escapeHtml(CT_I18N.person_in_charge) + '</div><div class="ct-preview-value">' + escapeHtml(picText) + '</div></div>'
   + '<div class="ct-preview-item"><div class="ct-preview-label">' + escapeHtml(CT_I18N.assign_to) + '</div><div class="ct-preview-value">' + escapeHtml(assignText) + '</div></div>'
   + '<div class="ct-preview-item"><div class="ct-preview-label">' + escapeHtml(CT_I18N.asset_equipment) + '</div><div class="ct-preview-value">' + escapeHtml(assetText) + '</div></div>'
   + '<div class="ct-preview-item"><div class="ct-preview-label">' + escapeHtml(CT_I18N.preview_category) + '</div><div class="ct-preview-value">' + escapeHtml(c) + '</div></div>'
   + '<div class="ct-preview-item"><div class="ct-preview-label">' + escapeHtml(CT_I18N.preview_priority) + '</div><div class="ct-preview-value">' + escapeHtml(p) + '</div></div>'
   + '</div>'
   + '<div class="ct-preview-desc"><div class="ct-preview-desc-title">' + escapeHtml(CT_I18N.upload_attachment) + '</div>' + filesHtml + '</div>'
   + '<div class="ct-preview-desc"><div class="ct-preview-desc-title">' + escapeHtml(CT_I18N.description) + '</div><div class="ct-preview-desc-body">' + escapeHtml(d) + '</div></div>'
   + '</div>';
 count.textContent=desc.value.length;
}
function updatePriority(){
 const opt=priority.options[priority.selectedIndex];
 const hours=opt ? (opt.dataset.slaHours || '-') : '-';
 const v=priority.value || '-';
 const cls=(v==='Urgent')?'pill-urgent':(v==='High')?'pill-high':(v==='Low')?'pill-low':'pill-medium';
 priorityInfo.innerHTML=`<span class="priority-pill ${cls}">${escapeHtml(priority.options[priority.selectedIndex] ? priority.options[priority.selectedIndex].textContent.replace(/\s*\(.*/, '').trim() : v)}</span><div class="help-text" style="margin-top:8px">${escapeHtml(CT_I18N.sla_target)}: <b>${hours} ${escapeHtml(CT_I18N.hours)}</b><br>${escapeHtml(CT_I18N.controlled_sla)}</div>`;
 updatePreview();
}
document.querySelectorAll('.quick-btn').forEach(btn=>btn.addEventListener('click',()=>{desc.value=templates[btn.dataset.template]||'';desc.focus();updatePreview();}));
[desc,title,category,department,assignedTo,asset].filter(Boolean).forEach(el=>{el.addEventListener('input',updatePreview);el.addEventListener('change',updatePreview);});
category.addEventListener('change',()=>{ const opt=category.options[category.selectedIndex]; if(opt && opt.dataset.defaultPriority){ priority.value=opt.dataset.defaultPriority; } updatePriority(); updateKbSuggestions(); });
const branchEl=document.getElementById('branch'); if(branchEl){branchEl.addEventListener('change', function(){ updateKbSuggestions(); updatePreview(); });}
priority.addEventListener('change',updatePriority);
const uploadInputs = mobileAttachments.concat([attachment]).filter(Boolean);
let hdSelectedFiles = [];
let hdSyncingFiles = false;
function fileKey(f){
 return [f.name || '', f.size || 0, f.lastModified || 0, f.type || ''].join('|');
}
function fileIcon(f){
 const type=(f.type||'').toLowerCase();
 const name=(f.name||'').toLowerCase();
 if(type.indexOf('image/')===0 || /\.(jpg|jpeg|png|gif|webp)$/.test(name)) return '🖼️';
 if(type.indexOf('video/')===0 || /\.(mp4|mov|m4v|webm)$/.test(name)) return '🎥';
 if(type.indexOf('audio/')===0 || /\.(mp3|m4a|wav|aac|ogg|webm)$/.test(name)) return '🎤';
 if(/\.pdf$/.test(name)) return '📄';
 if(/\.(doc|docx)$/.test(name)) return '📝';
 if(/\.(xls|xlsx)$/.test(name)) return '📊';
 return '📎';
}
function formatFileSize(bytes){
 bytes = Number(bytes || 0);
 if(bytes >= 1024*1024) return (bytes/1024/1024).toFixed(2) + ' MB';
 if(bytes >= 1024) return (bytes/1024).toFixed(1) + ' KB';
 return bytes + ' B';
}
function syncSelectedFilesToInput(){
 if(!attachment || typeof DataTransfer === 'undefined') return;
 const dt = new DataTransfer();
 hdSelectedFiles.forEach(function(f){ dt.items.add(f); });
 hdSyncingFiles = true;
 attachment.files = dt.files;
 hdSyncingFiles = false;
 mobileAttachments.forEach(function(el){ try{ el.value=''; }catch(e){} });
}
function renderSelectedFiles(){
 if(!filePreview) return;
 if(hdSelectedFiles.length===0){
   filePreview.innerHTML='';
   updatePreview();
   return;
 }
 const total = hdSelectedFiles.reduce(function(s,f){ return s + (f.size || 0); }, 0);
 filePreview.innerHTML = '<div class="hd-selected-files">'
   + '<div class="hd-selected-files-head">'
   + '<strong>' + escapeHtml(CT_I18N.selected_file || 'Selected') + ': ' + hdSelectedFiles.length + ' file(s)</strong>'
   + '<span>' + escapeHtml(formatFileSize(total)) + '</span>'
   + '</div>'
   + hdSelectedFiles.map(function(f,idx){
       return '<div class="hd-selected-file-row" data-file-index="' + idx + '">'
         + '<span class="hd-selected-file-icon">' + fileIcon(f) + '</span>'
         + '<span class="hd-selected-file-name" title="' + escapeHtml(f.name || 'attachment') + '">' + escapeHtml(f.name || 'attachment') + '</span>'
         + '<span class="hd-selected-file-size">' + escapeHtml(formatFileSize(f.size || 0)) + '</span>'
         + '<button type="button" class="hd-selected-file-remove" data-remove-file="' + idx + '">Delete</button>'
         + '</div>';
     }).join('')
   + '</div>';
 updatePreview();
}
function addFilesFromInput(input){
 if(hdSyncingFiles) return;
 const files = Array.from((input && input.files) ? input.files : []);
 if(files.length===0){ renderSelectedFiles(); return; }
 const existing = new Set(hdSelectedFiles.map(fileKey));
 files.forEach(function(f){
   const key = fileKey(f);
   if(!existing.has(key)){
     hdSelectedFiles.push(f);
     existing.add(key);
   }
 });
 syncSelectedFilesToInput();
 renderSelectedFiles();
}
function removeSelectedFile(index){
 index = Number(index);
 if(!Number.isInteger(index) || index < 0 || index >= hdSelectedFiles.length) return;
 hdSelectedFiles.splice(index, 1);
 syncSelectedFilesToInput();
 renderSelectedFiles();
}
uploadInputs.forEach(function(el){
 el.addEventListener('change', function(){ addFilesFromInput(el); });
});
if(filePreview){
 filePreview.addEventListener('click', function(e){
   const btn = e.target.closest('[data-remove-file]');
   if(!btn) return;
   e.preventDefault();
   removeSelectedFile(btn.getAttribute('data-remove-file'));
 });
}
updatePriority();updatePreview();updateKbSuggestions();
})();
</script>

<?php require 'footer.php'; ?>
