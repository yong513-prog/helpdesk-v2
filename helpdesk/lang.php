<?php
// Helpdesk multilingual layer: English / Bahasa Melayu / 中文
if(session_status() === PHP_SESSION_NONE){ session_start(); }

$HD_LANG_ALLOWED = ['en','ms','zh'];
if(isset($_GET['lang']) && in_array($_GET['lang'], $HD_LANG_ALLOWED, true)){
    $_SESSION['helpdesk_lang'] = $_GET['lang'];
    setcookie('helpdesk_lang', $_GET['lang'], time()+60*60*24*365, '/');
}
$GLOBALS['HD_LANG'] = $_SESSION['helpdesk_lang'] ?? ($_COOKIE['helpdesk_lang'] ?? 'en');
if(!in_array($GLOBALS['HD_LANG'], $HD_LANG_ALLOWED, true)){ $GLOBALS['HD_LANG'] = 'en'; }

function hd_lang(){ return $GLOBALS['HD_LANG'] ?? 'en'; }
function hd_lang_url($lang){
    $params = $_GET;
    $params['lang'] = $lang;
    return htmlspecialchars(basename($_SERVER['PHP_SELF']) . (count($params) ? '?' . http_build_query($params) : ''), ENT_QUOTES, 'UTF-8');
}


// Branch code safety helper: branch codes are system data and must never be translated.
if(!function_exists('hd_branch_code_raw')){
    function hd_branch_code_raw($value){
        $value = trim((string)$value);
        if($value === '') return $value;
        $map = [
            '电脑'=>'PC','電腦'=>'PC','Computer'=>'PC','computer'=>'PC','Komputer'=>'PC','komputer'=>'PC',
            'HQ'=>'HQ','hq'=>'HQ','KB'=>'KB','kb'=>'KB','KC'=>'KC','kc'=>'KC','KJ'=>'KJ','kj'=>'KJ','KK'=>'KK','kk'=>'KK','KL'=>'KL','kl'=>'KL','KR'=>'KR','kr'=>'KR','KS'=>'KS','ks'=>'KS','ML'=>'ML','ml'=>'ML',
            'PC'=>'PC','pc'=>'PC','PJ'=>'PJ','pj'=>'PJ','PKL'=>'PKL','pkl'=>'PKL','PM'=>'PM','pm'=>'PM','SE'=>'SE','se'=>'SE','TM'=>'TM','tm'=>'TM','TPC'=>'TPC','tpc'=>'TPC','TPN'=>'TPN','tpn'=>'TPN','TPT'=>'TPT','tpt'=>'TPT','WC'=>'WC','wc'=>'WC','WK'=>'WK','wk'=>'WK','BS'=>'BS','bs'=>'BS','LAS'=>'LAS','las'=>'LAS'
        ];
        if(isset($map[$value])) return $map[$value];
        if(preg_match('/^(电脑|電腦|Computer|computer|Komputer|komputer)-?(\d{6}-\d+)$/u', $value, $m)) return 'PC-'.$m[2];
        if(preg_match('/^(电脑|電腦|Computer|computer|Komputer|komputer)(\d{6}-\d+)$/u', $value, $m)) return 'PC-'.$m[2];
        if(preg_match('/^pc-?(\d{6}-\d+)$/i', $value, $m)) return 'PC-'.$m[1];
        return $value;
    }
}
if(!function_exists('hd_ticket_no_raw')){
    function hd_ticket_no_raw($value){ return hd_branch_code_raw($value); }
}

function __($text){ return hd_t($text); }
function t($text){ return hd_t($text); }
function hd_t($text){
    $lang = hd_lang();
    $custom = [
        'en' => [
            // Final no-translate / audit-language fixes
            'PC'=>'PC','Computer'=>'PC','Komputer'=>'PC','电脑'=>'PC',
            'No'=>'No','Yes'=>'Yes',
            'Asset'=>'Asset','Permission'=>'Permission','Assign'=>'Assign','Create'=>'Create',
            'Delete Asset'=>'Delete Asset','Deleted Asset'=>'Deleted Asset','Enable Asset'=>'Enable Asset','Disable Asset'=>'Disable Asset','Edit Asset'=>'Edit Asset','Add Asset'=>'Add Asset',
            'Assign Ticket'=>'Assign Ticket','Create Announcement'=>'Create Announcement','Update Permission'=>'Update Permission',
            // Timeline action labels (circle-only fix)
            'Reply Added'=>'Reply Added','回复 Added'=>'Reply Added','回复已添加'=>'Reply Added',
            'Reply Attachment Uploaded'=>'Reply Attachment Uploaded','回复附件已上传'=>'Reply Attachment Uploaded',
            'Attachment Uploaded'=>'Attachment Uploaded','附件已上传'=>'Attachment Uploaded',
            'Ticket Created'=>'Ticket Created','工单已创建'=>'Ticket Created','工單已創建'=>'Ticket Created',
            'Uploaded'=>'Uploaded','已上传'=>'Uploaded',
            'PIC Management'=>'Responsible Person Management','Administration / PIC Management'=>'Administration / Responsible Person Management','Add PIC'=>'Add Responsible Person','PIC Name'=>'Responsible Person Name','PIC List'=>'Responsible Person List','Add, edit, disable or delete Person In Charge options.'=>'Add, edit, disable or delete responsible person options.','Assign To Management'=>'Assignee Management','Administration / Assign To Management'=>'Administration / Assignee Management','Add Assign To'=>'Add Assignee','Assign To Name'=>'Assignee Name','Assign To List'=>'Assignee List','Add, edit, disable or delete Assign To options used in tickets.'=>'Add, edit, disable or delete assignee options used in tickets.','No Assign To records found.'=>'No assignee records found.','Delete this Assign To option?'=>'Delete this assignee option?','Inactive items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.'=>'Inactive items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.',
        ],
        'ms' => [
            'PC'=>'PC','Computer'=>'PC','Komputer'=>'PC','电脑'=>'PC',
            'No'=>'Tidak','Yes'=>'Ya',
            'Asset'=>'Aset','Permission'=>'Kebenaran','Assign'=>'Tugaskan','Create'=>'Cipta',
            'Delete Asset'=>'Padam Aset','Deleted Asset'=>'Aset Dipadam','Enable Asset'=>'Aktifkan Aset','Disable Asset'=>'Nyahaktif Aset','Edit Asset'=>'Edit Aset','Add Asset'=>'Tambah Aset',
            'Assign Ticket'=>'Tugaskan Tiket','Create Announcement'=>'Cipta Pengumuman','Update Permission'=>'Kemas Kini Kebenaran',
            'Audit Logs'=>'Log Audit','Total Logs'=>'Jumlah Log','Today Activity'=>'Aktiviti Hari Ini','Active Users'=>'Pengguna Aktif','Last Activity'=>'Aktiviti Terakhir','Start Date'=>'Tarikh Mula','End Date'=>'Tarikh Tamat','User'=>'Pengguna','Action'=>'Tindakan','All Users'=>'Semua Pengguna','All Actions'=>'Semua Tindakan','Per Page'=>'Setiap Halaman','Search'=>'Cari','Keyword'=>'Kata Kunci','Reset'=>'Tetapkan Semula','Export CSV'=>'Eksport CSV','Activity List'=>'Senarai Aktiviti','Date'=>'Tarikh','Details'=>'Butiran','No audit logs found.'=>'Tiada log audit dijumpai.',
            'PIC Management'=>'Pengurusan Pegawai Bertanggungjawab','Administration / PIC Management'=>'Pentadbiran / Pengurusan Pegawai Bertanggungjawab','Add PIC'=>'Tambah Pegawai Bertanggungjawab','PIC Name'=>'Nama Pegawai Bertanggungjawab','PIC List'=>'Senarai Pegawai Bertanggungjawab','Add, edit, disable or delete Person In Charge options.'=>'Tambah, edit, nyahaktif atau padam pilihan pegawai bertanggungjawab.','Assign To Management'=>'Pengurusan Orang Ditugaskan','Administration / Assign To Management'=>'Pentadbiran / Pengurusan Orang Ditugaskan','Add Assign To'=>'Tambah Orang Ditugaskan','Assign To Name'=>'Nama Orang Ditugaskan','Assign To List'=>'Senarai Orang Ditugaskan','Add, edit, disable or delete Assign To options used in tickets.'=>'Tambah, edit, nyahaktif atau padam pilihan orang ditugaskan yang digunakan dalam tiket.','No Assign To records found.'=>'Tiada rekod orang ditugaskan dijumpai.','Delete this Assign To option?'=>'Padam pilihan orang ditugaskan ini?','Inactive items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.'=>'Item tidak aktif tidak akan muncul dalam senarai pilihan. Rekod yang telah digunakan akan dinyahaktifkan dan bukan dipadam untuk melindungi tiket lama.',
            'Hold Voice'=>'Tahan Suara','Allowed: multiple photos, videos, voice/audio, PDF, Word and Excel. Max 50MB each.'=>'Dibenarkan: banyak foto, video, suara/audio, PDF, Word dan Excel. Maksimum 50MB setiap fail.',
            'Attachment Uploaded'=>'Lampiran Dimuat Naik','Reply Attachment Uploaded'=>'Lampiran Balasan Dimuat Naik','Update Status'=>'Kemas Kini Status','No asset linked.'=>'Tiada aset dipautkan.','No timeline records found.'=>'Tiada rekod garis masa dijumpai.',
            'KPI Person In Charge Summary'=>'Ringkasan Pegawai Bertanggungjawab','KPI Person In Charge'=>'Pegawai Bertanggungjawab','Knowledge Category List'=>'Senarai Kategori Pengetahuan','Order'=>'Susunan','Articles'=>'Artikel','Disable'=>'Nyahaktif','Enable'=>'Aktifkan','Delete this category? Used categories will be disabled instead.'=>'Padam kategori ini? Kategori yang digunakan akan dinyahaktifkan.',
            'No category found.'=>'Tiada kategori dijumpai.','Add Article and Edit Article automatically use enabled categories from this list. Used categories cannot be hard-deleted; they will be disabled instead.'=>'Tambah Artikel dan Edit Artikel akan menggunakan kategori aktif daripada senarai ini secara automatik. Kategori yang telah digunakan tidak boleh dipadam terus; ia akan dinyahaktifkan.',
            'PIC Name'=>'Nama Pegawai Bertanggungjawab','Search...'=>'Cari...','No.'=>'No.'
        ],
        'zh' => [
            'PC'=>'PC','Computer'=>'PC','Komputer'=>'PC','电脑'=>'PC',
            'No'=>'否','Yes'=>'是',
            'Asset'=>'资产','Permission'=>'权限','Assign'=>'指派','Create'=>'创建',
            'Delete Asset'=>'删除资产','Deleted Asset'=>'已删除资产','Enable Asset'=>'启用资产','Disable Asset'=>'停用资产','Edit Asset'=>'编辑资产','Add Asset'=>'添加资产',
            'Assign Ticket'=>'指派工单','Create Announcement'=>'创建公告','Update Permission'=>'更新权限',
            'Audit Logs'=>'审计日志','Total Logs'=>'总日志','Today Activity'=>'今日活动','Active Users'=>'启用用户','Last Activity'=>'最后活动','Start Date'=>'开始日期','End Date'=>'结束日期','User'=>'用户','Action'=>'操作','All Users'=>'全部用户','All Actions'=>'全部操作','Per Page'=>'每页','Search'=>'搜索','Keyword'=>'关键字','Reset'=>'重置','Export CSV'=>'导出 CSV','Activity List'=>'活动列表','Date'=>'日期','Details'=>'详情','No audit logs found.'=>'没有审计日志。',
            'PIC Management'=>'负责人管理','Administration / PIC Management'=>'管理 / 负责人管理','Add PIC'=>'添加负责人','PIC Name'=>'负责人名称','PIC List'=>'负责人列表','Add, edit, disable or delete Person In Charge options.'=>'添加、编辑、停用或删除负责人选项。','Assign To Management'=>'指派人管理','Administration / Assign To Management'=>'管理 / 指派人管理','Add Assign To'=>'添加指派人','Assign To Name'=>'指派人名称','Assign To List'=>'指派人列表','Add, edit, disable or delete Assign To options used in tickets.'=>'添加、编辑、停用或删除工单使用的指派人选项。','No Assign To records found.'=>'没有找到指派人记录。','Delete this Assign To option?'=>'删除这个指派人选项？','Inactive items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.'=>'停用项目不会出现在下拉选单中。已使用记录会改为停用而不是删除，以保护旧工单。',
            'Hold Voice'=>'按住语音','Allowed: multiple photos, videos, voice/audio, PDF, Word and Excel. Max 50MB each.'=>'允许：多张照片、视频、语音/音频、PDF、Word 和 Excel。每个最大 50MB。',
            'Attachment Uploaded'=>'附件已上传','Reply Attachment Uploaded'=>'回复附件已上传','Update Status'=>'更新状态','No asset linked.'=>'没有关联资产。','No timeline records found.'=>'没有工单时间线记录。',
            'KPI Person In Charge Summary'=>'负责人汇总','KPI Person In Charge'=>'负责人','Knowledge Category List'=>'知识库分类列表','Order'=>'排序','Articles'=>'文章','Disable'=>'停用','Enable'=>'启用','Delete this category? Used categories will be disabled instead.'=>'删除这个分类？已使用的分类会改为停用。',
            'No category found.'=>'没有找到分类。','Add Article and Edit Article automatically use enabled categories from this list. Used categories cannot be hard-deleted; they will be disabled instead.'=>'添加文章和编辑文章会自动使用此列表中的启用分类。已被使用的分类不能硬删除，将改为停用。',
            'PIC Name'=>'负责人名称','Search...'=>'搜索...','No.'=>'序号'
        ]
    ];
    if(isset($custom[$lang][$text])) return $custom[$lang][$text];
    if($lang === 'en') return $text;
    $dict = hd_translation_dict();
    return $dict[$lang][$text] ?? $text;
}
function hd_translation_dict(){
    static $dict = null;
    if($dict !== null) return $dict;
    $ms = [
        'Dashboard'=>'Papan Pemuka','Main'=>'Utama','Ticket Management'=>'Pengurusan Tiket','Create Ticket'=>'Cipta Tiket','Ticket List'=>'Senarai Tiket','Overdue'=>'Lewat','Closed Tickets'=>'Tiket Ditutup','Asset Management'=>'Pengurusan Aset','Asset List'=>'Senarai Aset','Add Asset'=>'Tambah Aset','Knowledge'=>'Pengetahuan','Knowledge Base'=>'Pangkalan Pengetahuan','Communication'=>'Komunikasi','Announcements'=>'Pengumuman','Add Announcement'=>'Tambah Pengumuman','Reports'=>'Laporan','KPI Report'=>'Laporan KPI','Audit Logs'=>'Log Audit','Administration'=>'Pentadbiran','User Management'=>'Pengurusan Pengguna','Assign To Management'=>'Pengurusan Tugasan','PIC Management'=>'Pengurusan PIC','Category Management'=>'Pengurusan Kategori','SLA Management'=>'Pengurusan SLA','Branch Management'=>'Pengurusan Cawangan','Asset Type Management'=>'Pengurusan Jenis Aset','Ticket Status Management'=>'Pengurusan Status Tiket','Account'=>'Akaun','Logout'=>'Log Keluar','Live Summary'=>'Ringkasan Langsung','Active Tickets'=>'Tiket Aktif','Closed'=>'Ditutup','Assets'=>'Aset','Helpdesk System'=>'Sistem Helpdesk','WLS internal support portal.'=>'Portal sokongan dalaman WLS.','Ticket Management / Create Ticket'=>'Pengurusan Tiket / Cipta Tiket','Ticket Management / Ticket List'=>'Pengurusan Tiket / Senarai Tiket','Ticket Management / Closed Tickets'=>'Pengurusan Tiket / Tiket Ditutup','Ticket Management / Ticket Details'=>'Pengurusan Tiket / Butiran Tiket','Ticket Management / Edit Ticket'=>'Pengurusan Tiket / Edit Tiket','Asset Management / Asset List'=>'Pengurusan Aset / Senarai Aset','Asset Management / Add Asset'=>'Pengurusan Aset / Tambah Aset','Reports / KPI Report'=>'Laporan / Laporan KPI','Reports / Audit Logs'=>'Laporan / Log Audit','Administration / Control Panel'=>'Pentadbiran / Panel Kawalan','Administration / Users'=>'Pentadbiran / Pengguna','Administration / Category Management'=>'Pentadbiran / Pengurusan Kategori',
        'Welcome back'=>'Selamat kembali','New Ticket'=>'Tiket Baharu','In Progress'=>'Dalam Proses','Waiting Reply'=>'Menunggu Balasan','Solved'=>'Selesai','Total Tickets'=>'Jumlah Tiket','Open Tickets'=>'Tiket Terbuka','Pending Tickets'=>'Tiket Tertangguh','Latest Tickets'=>'Tiket Terkini','Urgent Tickets'=>'Tiket Urgent','View All'=>'Lihat Semua','No Ticket'=>'No Tiket','Title'=>'Tajuk','Branch'=>'Cawangan','Priority'=>'Keutamaan','Status'=>'Status','Action'=>'Tindakan','Date'=>'Tarikh','User'=>'Pengguna','Role'=>'Peranan','Username'=>'Nama Pengguna','Password'=>'Kata Laluan','Login'=>'Log Masuk','Login to continue to your account'=>'Log masuk untuk terus ke akaun anda','Remember ID'=>'Ingat ID','Create New Ticket'=>'Cipta Tiket Baharu','Description'=>'Penerangan','Category'=>'Kategori','Person In Charge'=>'PIC','Assign To'=>'Tugaskan Kepada','Attachment'=>'Lampiran','Submit'=>'Hantar','Cancel'=>'Batal','Save'=>'Simpan','Update'=>'Kemas Kini','Delete'=>'Padam','Edit'=>'Edit','View'=>'Lihat','Export'=>'Eksport','Search'=>'Cari','Print'=>'Cetak','Back'=>'Kembali','Name'=>'Nama','Email'=>'E-mel','Phone'=>'Telefon','Department'=>'Jabatan','Active'=>'Aktif','Inactive'=>'Tidak Aktif','High'=>'Tinggi','Medium'=>'Sederhana','Low'=>'Rendah','Urgent'=>'Segera','Created At'=>'Dicipta Pada','Updated At'=>'Dikemas Kini Pada','Ticket Baru'=>'Tiket Baharu','Dalam Proses'=>'Dalam Proses','Waiting Balas'=>'Menunggu Balasan','Selesai'=>'Selesai','Ditutup'=>'Ditutup','Batalled'=>'Dibatalkan',
        'Title and description are required.'=>'Tajuk dan penerangan diperlukan.','Access denied: invalid branch'=>'Akses ditolak: cawangan tidak sah','Invalid Person In Charge selected.'=>'Pegawai Bertanggungjawab tidak sah.','Invalid category selected. Please enable it in Category Management first.'=>'Kategori tidak sah. Sila aktifkan di Pengurusan Kategori dahulu.','Invalid priority selected. Please enable it in SLA Management first.'=>'Keutamaan tidak sah. Sila aktifkan di Pengurusan SLA dahulu.'
    ];
    $zh = [
        'Dashboard'=>'仪表板','Main'=>'主页','Ticket Management'=>'工单管理','Create Ticket'=>'创建工单','Ticket List'=>'工单列表','Overdue'=>'逾期','Closed Tickets'=>'已关闭工单','Asset Management'=>'资产管理','Asset List'=>'资产列表','Add Asset'=>'添加资产','Knowledge'=>'知识库','Knowledge Base'=>'知识库','Communication'=>'沟通','Announcements'=>'公告','Add Announcement'=>'添加公告','Reports'=>'报告','KPI Report'=>'KPI报告','Audit Logs'=>'审计日志','Administration'=>'管理','User Management'=>'用户管理','Assign To Management'=>'指派人管理','PIC Management'=>'PIC管理','Category Management'=>'分类管理','SLA Management'=>'SLA管理','Branch Management'=>'分行管理','Asset Type Management'=>'资产类型管理','Ticket Status Management'=>'工单状态管理','Account'=>'账户','Logout'=>'退出登录','Live Summary'=>'实时摘要','Active Tickets'=>'活跃工单','Closed'=>'已关闭','Assets'=>'资产','Helpdesk System'=>'Helpdesk 系统','WLS internal support portal.'=>'WLS 内部支援平台。','Ticket Management / Create Ticket'=>'工单管理 / 创建工单','Ticket Management / Ticket List'=>'工单管理 / 工单列表','Ticket Management / Closed Tickets'=>'工单管理 / 已关闭工单','Ticket Management / Ticket Details'=>'工单管理 / 工单详情','Ticket Management / Edit Ticket'=>'工单管理 / 编辑工单','Asset Management / Asset List'=>'资产管理 / 资产列表','Asset Management / Add Asset'=>'资产管理 / 添加资产','Reports / KPI Report'=>'报告 / KPI报告','Reports / Audit Logs'=>'报告 / 审计日志','Administration / Control Panel'=>'管理 / 控制面板','Administration / Users'=>'管理 / 用户','Administration / Category Management'=>'管理 / 分类管理',
        'Welcome back'=>'欢迎回来','New Ticket'=>'新工单','In Progress'=>'处理中','Waiting Reply'=>'等待回复','Solved'=>'已解决','Total Tickets'=>'总工单','Open Tickets'=>'未关闭工单','Pending Tickets'=>'待处理工单','Latest Tickets'=>'最新工单','Urgent Tickets'=>'紧急工单','View All'=>'查看全部','No Ticket'=>'工单号','Title'=>'标题','Branch'=>'分行','Priority'=>'优先级','Status'=>'状态','Action'=>'操作','Date'=>'日期','User'=>'用户','Role'=>'角色','Username'=>'用户名','Password'=>'密码','Login'=>'登录','Login to continue to your account'=>'登录以继续使用你的账户','Remember ID'=>'记住ID','Create New Ticket'=>'创建新工单','Description'=>'描述','Category'=>'分类','Person In Charge'=>'PIC','Assign To'=>'指派给','Attachment'=>'附件','Submit'=>'提交','Cancel'=>'取消','Save'=>'保存','Update'=>'更新','Delete'=>'删除','Edit'=>'编辑','View'=>'查看','Export'=>'导出','Search'=>'搜索','Print'=>'打印','Back'=>'返回','Name'=>'名称','Email'=>'邮箱','Phone'=>'电话','Department'=>'部门','Active'=>'启用','Inactive'=>'停用','High'=>'高','Medium'=>'中','Low'=>'低','Urgent'=>'紧急','Created At'=>'创建时间','Updated At'=>'更新时间','Ticket Baru'=>'新工单','Dalam Proses'=>'处理中','Waiting Balas'=>'等待回复','Selesai'=>'已解决','Ditutup'=>'已关闭','Batalled'=>'已取消','All'=>'全部','All Tickets'=>'全部工单',
        'Title and description are required.'=>'标题和描述不能为空。','Access denied: invalid branch'=>'拒绝访问：无效分行','Invalid Person In Charge selected.'=>'选择的负责人无效。','Invalid category selected. Please enable it in Category Management first.'=>'选择的分类无效，请先在分类管理启用。','Invalid priority selected. Please enable it in SLA Management first.'=>'选择的优先级无效，请先在SLA管理启用。'
    ];

    $extra_ms = [
        'All'=>'Semua','All Tickets'=>'Semua Tiket','Total Articles'=>'Jumlah Artikel','Total Views'=>'Jumlah Paparan','Last Updated'=>'Kemas Kini Terakhir','Most recent update'=>'Kemas kini terkini','All articles'=>'Semua artikel','From Category Management'=>'Daripada Pengurusan Kategori','Article views'=>'Paparan artikel','Search title, content, tags...'=>'Cari tajuk, kandungan, tag...','All Categories'=>'Semua Kategori','All Types'=>'Semua Jenis','All Scope'=>'Semua Skop','All Status'=>'Semua Status','Reset'=>'Set Semula','Top Viewed'=>'Paling Banyak Dilihat','New Structure'=>'Struktur Baharu','Articles now support'=>'Artikel kini menyokong','Create Ticket'=>'Cipta Tiket','will suggest related articles by selected'=>'akan mencadangkan artikel berkaitan mengikut pilihan','Type'=>'Jenis','Scope'=>'Skop','Updated'=>'Dikemas Kini','Views'=>'Paparan','Operations'=>'Tindakan','Showing'=>'Memaparkan','of'=>'daripada','articles'=>'artikel','article(s)'=>'artikel','Published'=>'Diterbitkan','Guide'=>'Panduan','Procedure'=>'Prosedur','FAQ'=>'Soalan Lazim','All Branches'=>'Semua Cawangan','All Branch'=>'Semua Cawangan','All Priorities'=>'Semua Keutamaan','Export CSV'=>'Eksport CSV','Export Report'=>'Eksport Laporan','Export By Date'=>'Eksport Mengikut Tarikh','Start Date'=>'Tarikh Mula','End Date'=>'Tarikh Akhir','Search / Filter'=>'Cari / Tapis','Search ticket no, title, description, department, assignee'=>'Cari no tiket, tajuk, penerangan, jabatan, penerima tugas','Ticket No'=>'No Tiket','Assignee'=>'Penerima Tugas','Assigned To'=>'Ditugaskan Kepada','SLA'=>'SLA','Last Update'=>'Kemas Kini Terakhir','Last Updated By'=>'Dikemas Kini Oleh','Within SLA'=>'Dalam SLA','Overdue'=>'Lewat','Unassigned'=>'Belum Ditugaskan','New Ticket'=>'Tiket Baharu','In Progress'=>'Dalam Proses','Waiting Reply'=>'Menunggu Balasan','Solved'=>'Selesai','Closed'=>'Ditutup','Cancelled'=>'Dibatalkan','Create New Ticket'=>'Cipta Tiket Baharu','Submit branch issue, attach proof, and route it to the correct PIC.'=>'Hantar isu cawangan, lampirkan bukti, dan hantar kepada PIC yang betul.','Back to Ticket List'=>'Kembali ke Senarai Tiket','Ticket Information'=>'Maklumat Tiket','Title'=>'Tajuk','Use a clear and short title so HQ can understand quickly.'=>'Gunakan tajuk yang jelas dan ringkas supaya HQ cepat faham.','Branch'=>'Cawangan','Person In Charge'=>'PIC','Choose the PIC responsible for this ticket.'=>'Pilih PIC yang bertanggungjawab untuk tiket ini.','Direct Assign'=>'Tugasan Terus','Admin / Head can assign this ticket directly when creating it.'=>'Admin / Head boleh menugaskan tiket ini terus semasa mencipta.','Asset / Equipment'=>'Aset / Peralatan','No Asset Selected'=>'Tiada Aset Dipilih','Asset dropdown is linked to Role Permission Matrix and active/repair asset records.'=>'Pilihan aset dipautkan kepada Matrix Kebenaran Peranan dan rekod aset aktif/baik pulih.','Priority'=>'Keutamaan','SLA target'=>'Sasaran SLA','Controlled by SLA Management.'=>'Dikawal oleh Pengurusan SLA.','Description'=>'Penerangan','Upload Attachment'=>'Muat Naik Lampiran','Camera'=>'Kamera','Gallery / File'=>'Galeri / Fail','Voice'=>'Suara','Allowed'=>'Dibenarkan','Max'=>'Maks','Quick Templates'=>'Templat Pantas','Click template to auto-fill description structure.'=>'Klik templat untuk mengisi struktur penerangan secara automatik.','Suggested Knowledge Base'=>'Cadangan Pangkalan Pengetahuan','No suggested article for this category yet.'=>'Tiada artikel dicadangkan untuk kategori ini lagi.','Live Preview'=>'Pratonton Langsung','No title'=>'Tiada tajuk','No description yet'=>'Belum ada penerangan','Good Ticket Checklist'=>'Senarai Semak Tiket Baik','Write clear issue title.'=>'Tulis tajuk isu yang jelas.','Select correct branch and asset.'=>'Pilih cawangan dan aset yang betul.','Attach photo/screenshot if possible.'=>'Lampirkan gambar/tangkapan skrin jika boleh.','Choose urgent only when operation is affected.'=>'Pilih segera hanya apabila operasi terjejas.','POS / System Issue'=>'Isu POS / Sistem','Printer Issue'=>'Isu Pencetak','Network / Internet Issue'=>'Isu Rangkaian / Internet','Maintenance Issue'=>'Isu Penyelenggaraan','Example: POS cannot connect to server'=>'Contoh: POS tidak boleh sambung ke server','Describe the problem, error message, affected counter/device, and what you already tried.'=>'Terangkan masalah, mesej ralat, kaunter/peranti terjejas, dan perkara yang telah dicuba.','Ticket List'=>'Senarai Tiket','Tickets are shown here. Solved tickets are separated into closed tickets.'=>'Tiket dipaparkan di sini. Tiket selesai diasingkan ke tiket ditutup.','Search'=>'Cari','Filter'=>'Tapis','View'=>'Lihat','Delete'=>'Padam','Edit'=>'Edit','No records found'=>'Tiada rekod dijumpai','Showing 3 of 3 articles'=>'Memaparkan 3 daripada 3 artikel','Branch scoped articles are filtered by user branch.'=>'Artikel skop cawangan ditapis mengikut cawangan pengguna.','POS System'=>'Sistem POS','Printer'=>'Pencetak','Network Issue'=>'Isu Rangkaian','Inventory Issue'=>'Isu Inventori','Purchasing Issue'=>'Isu Pembelian','Maintenance / Electrical'=>'Penyelenggaraan / Elektrik','HR / Staff Issue'=>'Isu HR / Staf','Other'=>'Lain-lain','High'=>'Tinggi','Medium'=>'Sederhana','Low'=>'Rendah','Urgent'=>'Segera'
    ];
    $extra_zh = [
        'All'=>'全部','All Tickets'=>'全部工单','Total Articles'=>'文章总数','Total Views'=>'总浏览','Last Updated'=>'最后更新','Most recent update'=>'最近更新','All articles'=>'全部文章','From Category Management'=>'来自分类管理','Article views'=>'文章浏览','Search title, content, tags...'=>'搜索标题、内容、标签...','All Categories'=>'全部分类','All Types'=>'全部类型','All Scope'=>'全部范围','All Status'=>'全部状态','Reset'=>'重置','Top Viewed'=>'最多浏览','New Structure'=>'新结构','Articles now support'=>'文章现在支持','will suggest related articles by selected'=>'会根据选择推荐相关文章','Type'=>'类型','Scope'=>'范围','Updated'=>'已更新','Views'=>'浏览','Operations'=>'操作','Showing'=>'显示','of'=>'共','articles'=>'文章','article(s)'=>'篇文章','Published'=>'已发布','Guide'=>'指南','Procedure'=>'流程','FAQ'=>'常见问题','All Branches'=>'全部分行','All Branch'=>'全部分行','All Priorities'=>'全部优先级','Export CSV'=>'导出CSV','Export Report'=>'导出报告','Export By Date'=>'按日期导出','Start Date'=>'开始日期','End Date'=>'结束日期','Search / Filter'=>'搜索 / 筛选','Search ticket no, title, description, department, assignee'=>'搜索工单号、标题、描述、部门、负责人','Ticket No'=>'工单号','Assignee'=>'负责人','Assigned To'=>'指派给','SLA'=>'SLA','Last Update'=>'最后更新','Last Updated By'=>'最后更新者','Within SLA'=>'SLA内','Overdue'=>'逾期','Unassigned'=>'未指派','New Ticket'=>'新工单','In Progress'=>'处理中','Waiting Reply'=>'等待回复','Solved'=>'已解决','Closed'=>'已关闭','Cancelled'=>'已取消','Create New Ticket'=>'创建工单','Submit branch issue, attach proof, and route it to the correct PIC.'=>'提交分行问题、附上证明，并转给正确PIC。','Back to Ticket List'=>'返回工单列表','Ticket Information'=>'工单资料','Title'=>'标题','Use a clear and short title so HQ can understand quickly.'=>'请使用清楚简短的标题，方便总部快速理解。','Branch'=>'分行','Person In Charge'=>'PIC','Choose the PIC responsible for this ticket.'=>'选择负责此工单的PIC。','Direct Assign'=>'直接指派','Admin / Head can assign this ticket directly when creating it.'=>'Admin / Head 创建时可以直接指派此工单。','Asset / Equipment'=>'资产 / 设备','No Asset Selected'=>'未选择资产','Asset dropdown is linked to Role Permission Matrix and active/repair asset records.'=>'资产下拉选单已联动角色权限矩阵和启用/维修资产记录。','Priority'=>'优先级','SLA target'=>'SLA目标','Controlled by SLA Management.'=>'由SLA管理控制。','Description'=>'描述','Upload Attachment'=>'上传附件','Camera'=>'拍照','Gallery / File'=>'相册 / 文件','Voice'=>'语音','Allowed'=>'允许','Max'=>'最大','Quick Templates'=>'快速模板','Click template to auto-fill description structure.'=>'点击模板可自动填入描述结构。','Suggested Knowledge Base'=>'推荐知识库','No suggested article for this category yet.'=>'此分类暂时没有推荐文章。','Live Preview'=>'实时预览','No title'=>'无标题','No description yet'=>'暂无描述','Good Ticket Checklist'=>'优质工单检查表','Write clear issue title.'=>'填写清楚的问题标题。','Select correct branch and asset.'=>'选择正确分行和资产。','Attach photo/screenshot if possible.'=>'尽量附上照片/截图。','Choose urgent only when operation is affected.'=>'只有影响营运时才选择紧急。','POS / System Issue'=>'POS / 系统问题','Printer Issue'=>'打印机问题','Network / Internet Issue'=>'网络 / Internet 问题','Maintenance Issue'=>'维修问题','Example: POS cannot connect to server'=>'例如：POS 无法连接服务器','Describe the problem, error message, affected counter/device, and what you already tried.'=>'描述问题、错误信息、受影响柜台/设备，以及你已尝试的方法。','Ticket List'=>'工单列表','Tickets are shown here. Solved tickets are separated into closed tickets.'=>'这里显示工单。已解决工单会分开到已关闭工单。','Search'=>'搜索','Filter'=>'筛选','View'=>'查看','Delete'=>'删除','Edit'=>'编辑','No records found'=>'没有记录','Showing 3 of 3 articles'=>'显示 3 / 3 篇文章','Branch scoped articles are filtered by user branch.'=>'分行范围文章会根据用户分行筛选。','POS System'=>'POS系统','Printer'=>'打印机','Network Issue'=>'网络问题','Inventory Issue'=>'库存问题','Purchasing Issue'=>'采购问题','Maintenance / Electrical'=>'维修 / 电气','HR / Staff Issue'=>'HR / 员工问题','Other'=>'其他','High'=>'高','Medium'=>'中','Low'=>'中','Urgent'=>'紧急'
    ];


    // v3补强：补齐 Dashboard / Ticket List / Knowledge Base / Create Ticket 中仍显示英文或混合语言的固定字串。
    $fix_ms = [
        'ALL'=>'SEMUA','NEW TICKET'=>'TIKET BAHARU','IN PROGRESS'=>'DALAM PROSES','WAITING REPLY'=>'MENUNGGU BALASAN','SOLVED'=>'SELESAI','CLOSED'=>'DITUTUP',
        'Welcome back,'=>'Selamat kembali,','Here is what is happening with your helpdesk today.'=>'Ini ringkasan helpdesk anda hari ini.','Malaysia Time'=>'Masa Malaysia',
        'All active + closed'=>'Semua aktif + ditutup','Active status'=>'Status aktif','Closed / archived'=>'Ditutup / arkib','Past due date'=>'Melebihi tarikh akhir',
        'Ticket Status Overview'=>'Gambaran Status Tiket','Latest 5 Tickets'=>'5 Tiket Terkini','No tickets found'=>'Tiada tiket dijumpai','Top Branches'=>'Cawangan Teratas','Top Branch'=>'Cawangan Teratas','Top Assignees'=>'Penerima Tugas Teratas','Top Person In Charge'=>'PIC Teratas','Top Categories'=>'Kategori Teratas','Asset Overview'=>'Gambaran Aset','Total Assets'=>'Jumlah Aset','Under Repair'=>'Dalam Baik Pulih','Available Assets'=>'Aset Tersedia','Repair'=>'Baik Pulih','Inactive / Disposed'=>'Tidak Aktif / Dilupuskan','Linked with Asset Management and Asset Type Management.'=>'Dipautkan dengan Pengurusan Aset dan Pengurusan Jenis Aset.','Open KB'=>'Buka KB','Published Articles'=>'Artikel Diterbitkan','Troubleshooting / FAQ / SOP'=>'Penyelesaian Masalah / FAQ / SOP','Linked with Category Management and Branch Scope.'=>'Dipautkan dengan Pengurusan Kategori dan Skop Cawangan.','Top Knowledge Articles'=>'Artikel Pengetahuan Teratas','No knowledge articles found'=>'Tiada artikel pengetahuan dijumpai','Recent Audit Logs'=>'Log Audit Terkini','Details'=>'Butiran','No audit logs found'=>'Tiada log audit dijumpai','No urgent tickets'=>'Tiada tiket urgent','Great! All clear for now.'=>'Bagus! Tiada isu buat masa ini.',
        'Create Ticket'=>'Cipta Tiket','Ticket List'=>'Senarai Tiket','Export CSV'=>'Eksport CSV','Export Report'=>'Eksport Laporan','Export By Date'=>'Eksport Mengikut Tarikh','Start Date'=>'Tarikh Mula','End Date'=>'Tarikh Akhir','Search / Filter'=>'Cari / Tapis','Search ticket no, title, description, department, assignee'=>'Cari no tiket, tajuk, penerangan, jabatan, penerima tugas','All Status'=>'Semua Status','All Branches'=>'Semua Cawangan','All Priorities'=>'Semua Keutamaan',
        'Ticket No'=>'No Tiket','Assignee'=>'Penerima Tugas','Assigned To'=>'Ditugaskan Kepada','Last Update'=>'Kemas Kini Terakhir','Last Updated By'=>'Dikemas Kini Oleh','Within SLA'=>'Dalam SLA','Within SLA?'=>'Dalam SLA?','Overdue'=>'Lewat','Overdue '=>'Lewat ','Unassigned'=>'Belum Ditugaskan','Search'=>'Cari','Filter'=>'Tapis','Reset'=>'Set Semula','View'=>'Lihat','Delete'=>'Padam','Edit'=>'Edit','Created Time'=>'Masa Dicipta','Created At'=>'Dicipta Pada',
        'Knowledge Base'=>'Pangkalan Pengetahuan','Organized by'=>'Diatur mengikut','Category'=>'Kategori','Categories'=>'Kategori','Type'=>'Jenis','Tags'=>'Tag','Scope'=>'Skop','Total Articles'=>'Jumlah Artikel','Total Views'=>'Jumlah Paparan','Last Updated'=>'Kemas Kini Terakhir','Search title, content, tags...'=>'Cari tajuk, kandungan, tag...','All Categories'=>'Semua Kategori','All Types'=>'Semua Jenis','All Scope'=>'Semua Skop','Top Viewed'=>'Paling Banyak Dilihat','New Structure'=>'Struktur Baharu','Article views'=>'Paparan artikel','Most recent update'=>'Kemas kini terkini','all Branches'=>'Semua Cawangan','all branches'=>'Semua cawangan','article(s)'=>'artikel','articles'=>'artikel','Operations'=>'Tindakan','Published'=>'Diterbitkan','Guide'=>'Panduan','Procedure'=>'Prosedur','FAQ'=>'Soalan Lazim',
        'Submit branch issue, attach proof, and route it to the correct PIC.'=>'Hantar isu cawangan, lampirkan bukti, dan hantar kepada PIC yang betul.','Ticket Information'=>'Maklumat Tiket','Use a clear and short title so HQ can understand quickly.'=>'Gunakan tajuk yang jelas dan ringkas supaya HQ cepat faham.','Choose the PIC responsible for this ticket.'=>'Pilih PIC yang bertanggungjawab untuk tiket ini.','Direct Assign'=>'Tugasan Terus','Admin / Head can assign this ticket directly when creating it.'=>'Admin / Head boleh menugaskan tiket ini terus semasa mencipta.','Asset / Equipment'=>'Aset / Peralatan','No Asset Selected'=>'Tiada Aset Dipilih','Quick Templates'=>'Templat Pantas','Suggested Knowledge Base'=>'Cadangan Pangkalan Pengetahuan','Live Preview'=>'Pratonton Langsung','Good Ticket Checklist'=>'Senarai Semak Tiket Baik','Upload Attachment'=>'Muat Naik Lampiran','Camera'=>'Kamera','Gallery / File'=>'Galeri / Fail','Voice'=>'Suara','Choose File'=>'Pilih Fail','No file chosen'=>'Tiada fail dipilih',
        'HR / Staff Issue'=>'Isu HR / Staf','POS System'=>'Sistem POS','Printer'=>'Pencetak','Network Issue'=>'Isu Rangkaian','Inventory Issue'=>'Isu Inventori','Purchasing Issue'=>'Isu Pembelian','Maintenance / Electrical'=>'Penyelenggaraan / Elektrik','Other'=>'Lain-lain','Medium'=>'Sederhana','Low'=>'Rendah','High'=>'Tinggi','Urgent'=>'Segera'
    ];
    $fix_zh = [
        'ALL'=>'全部','NEW TICKET'=>'新工单','IN PROGRESS'=>'处理中','WAITING REPLY'=>'等待回复','SOLVED'=>'已解决','CLOSED'=>'已关闭',
        'Welcome back,'=>'欢迎回来，','Here is what is happening with your helpdesk today.'=>'这里是今天 Helpdesk 的摘要。','Malaysia Time'=>'马来西亚时间',
        'All active + closed'=>'全部活跃 + 已关闭','Active status'=>'活跃状态','Closed / archived'=>'已关闭 / 已归档','Past due date'=>'超过截止日期',
        'Ticket Status Overview'=>'工单状态总览','Latest 5 Tickets'=>'最新 5 张工单','No tickets found'=>'没有找到工单','Top Branches'=>'Top 分行','Top Branch'=>'Top 分行','Top Assignees'=>'Top 负责人','Top Person In Charge'=>'Top 负责人','Top Categories'=>'Top 分类','Asset Overview'=>'资产总览','Total Assets'=>'资产总数','Under Repair'=>'维修中','Available Assets'=>'可用资产','Repair'=>'维修','Inactive / Disposed'=>'停用 / 已报废','Linked with Asset Management and Asset Type Management.'=>'已联动资产管理和资产类型管理。','Open KB'=>'打开知识库','Published Articles'=>'已发布文章','Troubleshooting / FAQ / SOP'=>'故障排除 / FAQ / SOP','Linked with Category Management and Branch Scope.'=>'已联动分类管理和分行范围。','Top Knowledge Articles'=>'Top 知识文章','No knowledge articles found'=>'没有知识库文章','Recent Audit Logs'=>'最近审计日志','Details'=>'详情','No audit logs found'=>'没有审计日志','No urgent tickets'=>'没有紧急工单','Great! All clear for now.'=>'很好！目前没有紧急事项。',
        'Create Ticket'=>'创建工单','Ticket List'=>'工单列表','Export CSV'=>'导出 CSV','Export Report'=>'导出报告','Export By Date'=>'按日期导出','Start Date'=>'开始日期','End Date'=>'结束日期','Search / Filter'=>'搜索 / 筛选','Search ticket no, title, description, department, assignee'=>'搜索工单号、标题、描述、部门、负责人','All Status'=>'全部状态','All Branches'=>'全部分行','All Priorities'=>'全部优先级',
        'Ticket No'=>'工单号','Assignee'=>'负责人','Assigned To'=>'指派给','Last Update'=>'最后更新','Last Updated By'=>'最后更新者','Within SLA'=>'SLA内','Within SLA?'=>'SLA内？','Overdue'=>'逾期','Overdue '=>'逾期 ','Unassigned'=>'未指派','Search'=>'搜索','Filter'=>'筛选','Reset'=>'重置','View'=>'查看','Delete'=>'删除','Edit'=>'编辑','Created Time'=>'创建时间','Created At'=>'创建时间',
        'Knowledge Base'=>'知识库','Organized by'=>'按','Category'=>'分类','Categories'=>'分类','Type'=>'类型','Tags'=>'标签','Scope'=>'范围','Total Articles'=>'文章总数','Total Views'=>'总浏览','Last Updated'=>'最后更新','Search title, content, tags...'=>'搜索标题、内容、标签...','All Categories'=>'全部分类','All Types'=>'全部类型','All Scope'=>'全部范围','Top Viewed'=>'最多浏览','New Structure'=>'新结构','Article views'=>'文章浏览','Most recent update'=>'最近更新','all Branches'=>'全部分行','all branches'=>'全部分行','article(s)'=>'篇文章','articles'=>'文章','Operations'=>'操作','Published'=>'已发布','Guide'=>'指南','Procedure'=>'流程','FAQ'=>'常见问题',
        'Submit branch issue, attach proof, and route it to the correct PIC.'=>'提交分行问题、附上证明，并转给正确 PIC。','Ticket Information'=>'工单资料','Use a clear and short title so HQ can understand quickly.'=>'请使用清楚简短的标题，方便总部快速理解。','Choose the PIC responsible for this ticket.'=>'选择负责此工单的 PIC。','Direct Assign'=>'直接指派','Admin / Head can assign this ticket directly when creating it.'=>'Admin / Head 创建时可以直接指派此工单。','Asset / Equipment'=>'资产 / 设备','No Asset Selected'=>'未选择资产','Quick Templates'=>'快速模板','Suggested Knowledge Base'=>'推荐知识库','Live Preview'=>'实时预览','Good Ticket Checklist'=>'优质工单检查表','Upload Attachment'=>'上传附件','Camera'=>'拍照','Gallery / File'=>'相册 / 文件','Voice'=>'语音','Choose File'=>'选择文件','No file chosen'=>'未选择文件',
        'HR / Staff Issue'=>'HR / 员工问题','POS System'=>'POS系统','Printer'=>'打印机','Network Issue'=>'网络问题','Inventory Issue'=>'库存问题','Purchasing Issue'=>'采购问题','Maintenance / Electrical'=>'维修 / 电气','Other'=>'其他','Medium'=>'中','Low'=>'低','High'=>'高','Urgent'=>'紧急'
    ];
    $ms = array_merge($ms, $fix_ms);
    $zh = array_merge($zh, $fix_zh);

    $ms = array_merge($ms, $extra_ms);
    $zh = array_merge($zh, $extra_zh);
    $ms = array_merge($ms, $fix_ms);
    $zh = array_merge($zh, $fix_zh);


    // v4/v100 comprehensive UI dictionary. Values used in database/form POST remain unchanged; only displayed text/placeholder/title/button labels are translated by output buffer.
    $v100_ms = [
        'ALL'=>'SEMUA','All'=>'Semua','NEW TICKET'=>'TIKET BAHARU','New Ticket'=>'Tiket Baharu','IN PROGRESS'=>'DALAM PROSES','In Progress'=>'Dalam Proses','WAITING REPLY'=>'MENUNGGU BALASAN','Waiting Reply'=>'Menunggu Balasan','SOLVED'=>'SELESAI','Solved'=>'Selesai','CLOSED'=>'DITUTUP','Closed'=>'Ditutup','Cancelled'=>'Dibatalkan','Canceled'=>'Dibatalkan',
        'Dashboard'=>'Papan Pemuka','Welcome back'=>'Selamat kembali','Welcome back,'=>'Selamat kembali,','Here is what is happening with your helpdesk today.'=>'Ini ringkasan helpdesk anda hari ini.','Malaysia Time'=>'Masa Malaysia','View All'=>'Lihat Semua','View all'=>'Lihat semua','Create Ticket'=>'Cipta Tiket','Ticket List'=>'Senarai Tiket','Closed Tickets'=>'Tiket Ditutup','Overdue'=>'Lewat','Active status'=>'Status aktif','All active + closed'=>'Semua aktif + ditutup','Closed / archived'=>'Ditutup / diarkib','Past due date'=>'Melebihi tarikh akhir','Total Tickets'=>'Jumlah Tiket','Total Ticket'=>'Jumlah Tiket',
        'Ticket Status Overview'=>'Gambaran Status Tiket','Latest 5 Tickets'=>'5 Tiket Terkini','Urgent Tickets'=>'Tiket Urgent','No urgent tickets'=>'Tiada tiket urgent','Great! All clear for now.'=>'Bagus! Tiada isu buat masa ini.','No tickets found'=>'Tiada tiket dijumpai','Top Branches'=>'Cawangan Teratas','Top Branch'=>'Cawangan Teratas','Top Assignees'=>'Penerima Tugas Teratas','Top Person In Charge'=>'PIC Teratas','Top Categories'=>'Kategori Teratas','Recent Audit Logs'=>'Log Audit Terkini','No audit logs found'=>'Tiada log audit dijumpai','Details'=>'Butiran',
        'Asset Overview'=>'Gambaran Aset','Total Assets'=>'Jumlah Aset','Assets'=>'Aset','Available Assets'=>'Aset Tersedia','Under Repair'=>'Dalam Baik Pulih','Repair'=>'Baik Pulih','Inactive / Disposed'=>'Tidak Aktif / Dilupuskan','Asset Management'=>'Pengurusan Aset','Asset List'=>'Senarai Aset','Add Asset'=>'Tambah Aset','Asset / Equipment'=>'Aset / Peralatan','No Asset Selected'=>'Tiada Aset Dipilih','Asset dropdown is linked to Role Permission Matrix and active/repair asset records.'=>'Pilihan aset dipautkan kepada Matrix Kebenaran Peranan dan rekod aset aktif/baik pulih.',
        'Knowledge Base'=>'Pangkalan Pengetahuan','Knowledge'=>'Pengetahuan','Open KB'=>'Buka KB','Published Articles'=>'Artikel Diterbitkan','Top Knowledge Articles'=>'Artikel Pengetahuan Teratas','No knowledge articles found'=>'Tiada artikel pengetahuan dijumpai','Troubleshooting / FAQ / SOP'=>'Penyelesaian Masalah / FAQ / SOP','Linked with Category Management and Branch Scope.'=>'Dipautkan dengan Pengurusan Kategori dan Skop Cawangan.','Organized by'=>'Diatur mengikut','Total Articles'=>'Jumlah Artikel','All articles'=>'Semua artikel','Total Views'=>'Jumlah Paparan','Article views'=>'Paparan artikel','Last Updated'=>'Kemas Kini Terakhir','Most recent update'=>'Kemas kini terkini','Top Viewed'=>'Paling Banyak Dilihat','New Structure'=>'Struktur Baharu','Articles now support'=>'Artikel kini menyokong','will suggest related articles by selected'=>'akan mencadangkan artikel berkaitan mengikut pilihan','article(s)'=>'artikel','articles'=>'artikel','Published'=>'Diterbitkan','Guide'=>'Panduan','Procedure'=>'Prosedur','FAQ'=>'Soalan Lazim','Search title, content, tags...'=>'Cari tajuk, kandungan, tag...',
        'Category'=>'Kategori','Categories'=>'Kategori','Type'=>'Jenis','Tags'=>'Tag','Scope'=>'Skop','Updated'=>'Dikemas Kini','Views'=>'Paparan','Operations'=>'Tindakan','Action'=>'Tindakan','All Categories'=>'Semua Kategori','All Types'=>'Semua Jenis','All Scope'=>'Semua Skop','All Status'=>'Semua Status','All Branches'=>'Semua Cawangan','All Branch'=>'Semua Cawangan','All Priorities'=>'Semua Keutamaan','Branch scoped articles are filtered by user branch.'=>'Artikel skop cawangan ditapis mengikut cawangan pengguna.',
        'Export CSV'=>'Eksport CSV','Export Report'=>'Eksport Laporan','Export By Date'=>'Eksport Mengikut Tarikh','Start Date'=>'Tarikh Mula','End Date'=>'Tarikh Akhir','Search / Filter'=>'Cari / Tapis','Search'=>'Cari','Filter'=>'Tapis','Reset'=>'Set Semula','Search ticket no, title, description, department, assignee'=>'Cari no tiket, tajuk, penerangan, jabatan, penerima tugas','Ticket No'=>'No Tiket','No Ticket'=>'No Tiket','Title'=>'Tajuk','Branch'=>'Cawangan','Assignee'=>'Penerima Tugas','Assigned To'=>'Ditugaskan Kepada','Person In Charge'=>'PIC','Priority'=>'Keutamaan','Status'=>'Status','SLA'=>'SLA','Within SLA'=>'Dalam SLA','Within SLA?'=>'Dalam SLA?','Last Update'=>'Kemas Kini Terakhir','Last Updated By'=>'Dikemas Kini Oleh','Created Time'=>'Masa Dicipta','Created At'=>'Dicipta Pada','Updated At'=>'Dikemas Kini Pada','Unassigned'=>'Belum Ditugaskan','View'=>'Lihat','Delete'=>'Padam','Edit'=>'Edit','Submit'=>'Hantar','Save'=>'Simpan','Update'=>'Kemas Kini','Cancel'=>'Batal','Back'=>'Kembali','Print'=>'Cetak','Export'=>'Eksport',
        'Create New Ticket'=>'Cipta Tiket Baharu','Submit branch issue, attach proof, and route it to the correct PIC.'=>'Hantar isu cawangan, lampirkan bukti, dan hantar kepada PIC yang betul.','Back to Ticket List'=>'Kembali ke Senarai Tiket','Ticket Information'=>'Maklumat Tiket','Use a clear and short title so HQ can understand quickly.'=>'Gunakan tajuk yang jelas dan ringkas supaya HQ cepat faham.','Choose the PIC responsible for this ticket.'=>'Pilih PIC yang bertanggungjawab untuk tiket ini.','Direct Assign'=>'Tugasan Terus','Admin / Head can assign this ticket directly when creating it.'=>'Admin / Head boleh menugaskan tiket ini terus semasa mencipta.','Description'=>'Penerangan','Upload Attachment'=>'Muat Naik Lampiran','Camera'=>'Kamera','Gallery / File'=>'Galeri / Fail','Voice'=>'Suara','Attachment'=>'Lampiran','Choose File'=>'Pilih Fail','No file chosen'=>'Tiada fail dipilih','Allowed'=>'Dibenarkan','Max'=>'Maks','Quick Templates'=>'Templat Pantas','Click template to auto-fill description structure.'=>'Klik templat untuk mengisi struktur penerangan secara automatik.','Suggested Knowledge Base'=>'Cadangan Pangkalan Pengetahuan','No suggested article for this category yet.'=>'Tiada artikel dicadangkan untuk kategori ini lagi.','Live Preview'=>'Pratonton Langsung','No title'=>'Tiada tajuk','No description yet'=>'Belum ada penerangan','Good Ticket Checklist'=>'Senarai Semak Tiket Baik','Write clear issue title.'=>'Tulis tajuk isu yang jelas.','Select correct branch and asset.'=>'Pilih cawangan dan aset yang betul.','Attach photo/screenshot if possible.'=>'Lampirkan gambar/tangkapan skrin jika boleh.','Choose urgent only when operation is affected.'=>'Pilih segera hanya apabila operasi terjejas.','Example: POS cannot connect to server'=>'Contoh: POS tidak boleh sambung ke server','Describe the problem, error message, affected counter/device, and what you already tried.'=>'Terangkan masalah, mesej ralat, kaunter/peranti terjejas, dan perkara yang telah dicuba.',
        'POS / System Issue'=>'Isu POS / Sistem','Printer Issue'=>'Isu Pencetak','Network / Internet Issue'=>'Isu Rangkaian / Internet','Maintenance Issue'=>'Isu Penyelenggaraan','HR / Staff Issue'=>'Isu HR / Staf','POS System'=>'Sistem POS','Printer'=>'Pencetak','Network Issue'=>'Isu Rangkaian','Inventory Issue'=>'Isu Inventori','Purchasing Issue'=>'Isu Pembelian','Maintenance / Electrical'=>'Penyelenggaraan / Elektrik','Other'=>'Lain-lain','High'=>'Tinggi','Medium'=>'Sederhana','Low'=>'Rendah','Urgent'=>'Segera',
        'Main'=>'Utama','Reports'=>'Laporan','KPI Report'=>'Laporan KPI','Audit Logs'=>'Log Audit','Administration'=>'Pentadbiran','User Management'=>'Pengurusan Pengguna','Assign To Management'=>'Pengurusan Tugasan','PIC Management'=>'Pengurusan PIC','Category Management'=>'Pengurusan Kategori','SLA Management'=>'Pengurusan SLA','Branch Management'=>'Pengurusan Cawangan','Asset Type Management'=>'Pengurusan Jenis Aset','Ticket Status Management'=>'Pengurusan Status Tiket','Communication'=>'Komunikasi','Announcements'=>'Pengumuman','Add Announcement'=>'Tambah Pengumuman','Account'=>'Akaun','Logout'=>'Log Keluar','Live Summary'=>'Ringkasan Langsung','Active Tickets'=>'Tiket Aktif','Active'=>'Aktif','Inactive'=>'Tidak Aktif','Name'=>'Nama','Username'=>'Nama Pengguna','Password'=>'Kata Laluan','Email'=>'E-mel','Phone'=>'Telefon','Department'=>'Jabatan','Role'=>'Peranan','User'=>'Pengguna','Date'=>'Tarikh','Login'=>'Log Masuk','Login to continue to your account'=>'Log masuk untuk terus ke akaun anda','Remember ID'=>'Ingat ID'
    ];
    $v100_zh = [
        'ALL'=>'全部','All'=>'全部','NEW TICKET'=>'新工单','New Ticket'=>'新工单','IN PROGRESS'=>'处理中','In Progress'=>'处理中','WAITING REPLY'=>'等待回复','Waiting Reply'=>'等待回复','SOLVED'=>'已解决','Solved'=>'已解决','CLOSED'=>'已关闭','Closed'=>'已关闭','Cancelled'=>'已取消','Canceled'=>'已取消',
        'Dashboard'=>'仪表板','Welcome back'=>'欢迎回来','Welcome back,'=>'欢迎回来，','Here is what is happening with your helpdesk today.'=>'这里是今天 Helpdesk 的摘要。','Malaysia Time'=>'马来西亚时间','View All'=>'查看全部','View all'=>'查看全部','Create Ticket'=>'创建工单','Ticket List'=>'工单列表','Closed Tickets'=>'已关闭工单','Overdue'=>'逾期','Active status'=>'活跃状态','All active + closed'=>'全部活跃 + 已关闭','Closed / archived'=>'已关闭 / 已归档','Past due date'=>'超过截止日期','Total Tickets'=>'总工单','Total Ticket'=>'总工单',
        'Ticket Status Overview'=>'工单状态总览','Latest 5 Tickets'=>'最新 5 张工单','Urgent Tickets'=>'紧急工单','No urgent tickets'=>'没有紧急工单','Great! All clear for now.'=>'很好！目前没有紧急事项。','No tickets found'=>'没有找到工单','Top Branches'=>'Top 分行','Top Branch'=>'Top 分行','Top Assignees'=>'Top 负责人','Top Person In Charge'=>'Top 负责人','Top Categories'=>'Top 分类','Recent Audit Logs'=>'最近审计日志','No audit logs found'=>'没有审计日志','Details'=>'详情',
        'Asset Overview'=>'资产总览','Total Assets'=>'资产总数','Assets'=>'资产','Available Assets'=>'可用资产','Under Repair'=>'维修中','Repair'=>'维修','Inactive / Disposed'=>'停用 / 已报废','Asset Management'=>'资产管理','Asset List'=>'资产列表','Add Asset'=>'添加资产','Asset / Equipment'=>'资产 / 设备','No Asset Selected'=>'未选择资产','Asset dropdown is linked to Role Permission Matrix and active/repair asset records.'=>'资产下拉选项已联动权限矩阵和启用/维修中的资产记录。',
        'Knowledge Base'=>'知识库','Knowledge'=>'知识库','Open KB'=>'打开知识库','Published Articles'=>'已发布文章','Top Knowledge Articles'=>'Top 知识文章','No knowledge articles found'=>'没有知识库文章','Troubleshooting / FAQ / SOP'=>'故障排除 / FAQ / SOP','Linked with Category Management and Branch Scope.'=>'已联动分类管理和分行范围。','Organized by'=>'按','Total Articles'=>'文章总数','All articles'=>'全部文章','Total Views'=>'总浏览','Article views'=>'文章浏览','Last Updated'=>'最后更新','Most recent update'=>'最近更新','Top Viewed'=>'最多浏览','New Structure'=>'新结构','Articles now support'=>'文章现在支持','will suggest related articles by selected'=>'会根据选择推荐相关文章','article(s)'=>'篇文章','articles'=>'文章','Published'=>'已发布','Guide'=>'指南','Procedure'=>'流程','FAQ'=>'常见问题','Search title, content, tags...'=>'搜索标题、内容、标签...',
        'Category'=>'分类','Categories'=>'分类','Type'=>'类型','Tags'=>'标签','Scope'=>'范围','Updated'=>'已更新','Views'=>'浏览','Operations'=>'操作','Action'=>'操作','All Categories'=>'全部分类','All Types'=>'全部类型','All Scope'=>'全部范围','All Status'=>'全部状态','All Branches'=>'全部分行','All Branch'=>'全部分行','All Priorities'=>'全部优先级','Branch scoped articles are filtered by user branch.'=>'分行范围文章会根据用户分行筛选。',
        'Export CSV'=>'导出 CSV','Export Report'=>'导出报告','Export By Date'=>'按日期导出','Start Date'=>'开始日期','End Date'=>'结束日期','Search / Filter'=>'搜索 / 筛选','Search'=>'搜索','Filter'=>'筛选','Reset'=>'重置','Search ticket no, title, description, department, assignee'=>'搜索工单号、标题、描述、部门、负责人','Ticket No'=>'工单号','No Ticket'=>'工单号','Title'=>'标题','Branch'=>'分行','Assignee'=>'负责人','Assigned To'=>'指派给','Person In Charge'=>'PIC','Priority'=>'优先级','Status'=>'状态','SLA'=>'SLA','Within SLA'=>'SLA内','Within SLA?'=>'SLA内？','Last Update'=>'最后更新','Last Updated By'=>'最后更新者','Created Time'=>'创建时间','Created At'=>'创建时间','Updated At'=>'更新时间','Unassigned'=>'未指派','View'=>'查看','Delete'=>'删除','Edit'=>'编辑','Submit'=>'提交','Save'=>'保存','Update'=>'更新','Cancel'=>'取消','Back'=>'返回','Print'=>'打印','Export'=>'导出',
        'Create New Ticket'=>'创建新工单','Submit branch issue, attach proof, and route it to the correct PIC.'=>'提交分行问题、附上证明，并转给正确 PIC。','Back to Ticket List'=>'返回工单列表','Ticket Information'=>'工单资料','Use a clear and short title so HQ can understand quickly.'=>'请使用清楚简短的标题，方便总部快速理解。','Choose the PIC responsible for this ticket.'=>'选择负责此工单的 PIC。','Direct Assign'=>'直接指派','Admin / Head can assign this ticket directly when creating it.'=>'Admin / Head 创建时可以直接指派此工单。','Description'=>'描述','Upload Attachment'=>'上传附件','Camera'=>'拍照','Gallery / File'=>'相册 / 文件','Voice'=>'语音','Attachment'=>'附件','Choose File'=>'选择文件','No file chosen'=>'未选择文件','Allowed'=>'允许','Max'=>'最大','Quick Templates'=>'快速模板','Click template to auto-fill description structure.'=>'点击模板可自动填入描述结构。','Suggested Knowledge Base'=>'推荐知识库','No suggested article for this category yet.'=>'此分类暂时没有推荐文章。','Live Preview'=>'实时预览','No title'=>'无标题','No description yet'=>'暂无描述','Good Ticket Checklist'=>'优质工单检查表','Write clear issue title.'=>'填写清楚的问题标题。','Select correct branch and asset.'=>'选择正确分行和资产。','Attach photo/screenshot if possible.'=>'尽量附上照片/截图。','Choose urgent only when operation is affected.'=>'只有影响营运时才选择紧急。','Example: POS cannot connect to server'=>'例如：POS 无法连接服务器','Describe the problem, error message, affected counter/device, and what you already tried.'=>'描述问题、错误信息、受影响柜台/设备，以及你已尝试的方法。',
        'POS / System Issue'=>'POS / 系统问题','Printer Issue'=>'打印机问题','Network / Internet Issue'=>'网络 / Internet 问题','Maintenance Issue'=>'维修问题','HR / Staff Issue'=>'HR / 员工问题','POS System'=>'POS系统','Printer'=>'打印机','Network Issue'=>'网络问题','Inventory Issue'=>'库存问题','Purchasing Issue'=>'采购问题','Maintenance / Electrical'=>'维修 / 电气','Other'=>'其他','High'=>'高','Medium'=>'中','Low'=>'低','Urgent'=>'紧急',
        'Main'=>'主页','Reports'=>'报告','KPI Report'=>'KPI报告','Audit Logs'=>'审计日志','Administration'=>'管理','User Management'=>'用户管理','Assign To Management'=>'指派人管理','PIC Management'=>'PIC管理','Category Management'=>'分类管理','SLA Management'=>'SLA管理','Branch Management'=>'分行管理','Asset Type Management'=>'资产类型管理','Ticket Status Management'=>'工单状态管理','Communication'=>'沟通','Announcements'=>'公告','Add Announcement'=>'添加公告','Account'=>'账户','Logout'=>'退出登录','Live Summary'=>'实时摘要','Active Tickets'=>'活跃工单','Active'=>'启用','Inactive'=>'停用','Name'=>'名称','Username'=>'用户名','Password'=>'密码','Email'=>'邮箱','Phone'=>'电话','Department'=>'部门','Role'=>'角色','User'=>'用户','Date'=>'日期','Login'=>'登录','Login to continue to your account'=>'登录以继续使用你的账户','Remember ID'=>'记住ID'
    ];
    $ms = array_merge($ms, $v100_ms);
    $zh = array_merge($zh, $v100_zh);

    $ann_ms = [
        'Communication / Announcements'=>'Komunikasi / Pengumuman','Communication / Add Announcement'=>'Komunikasi / Tambah Pengumuman','Announcement'=>'Pengumuman','Company notices and internal updates'=>'Notis syarikat dan kemas kini dalaman','Read Status'=>'Status Bacaan','Search announcement...'=>'Cari pengumuman...','Active Only'=>'Aktif Sahaja','Total Announcements'=>'Jumlah Pengumuman','Active announcements'=>'Pengumuman aktif','Unread (You)'=>'Belum Dibaca (Anda)','Branches'=>'Cawangan','Total Branches'=>'Jumlah Cawangan','Total branches'=>'Jumlah cawangan','Read / Total'=>'Dibaca / Jumlah','read rate'=>'kadar bacaan','Target'=>'Sasaran','Read Info'=>'Maklumat Bacaan','View Details'=>'Lihat Butiran','No announcements found.'=>'Tiada pengumuman dijumpai.','Posted by'=>'Dihantar oleh','Posted By'=>'Dihantar Oleh','Read on'=>'Dibaca pada','Read'=>'Dibaca','Unread'=>'Belum Dibaca','Delete this announcement?'=>'Padam pengumuman ini?','Opened announcement will be automatically marked as read'=>'Pengumuman yang dibuka akan ditanda sebagai dibaca secara automatik','This announcement has been marked as read automatically'=>'Pengumuman ini telah ditanda sebagai dibaca secara automatik','Read Rate'=>'Kadar Bacaan','Created at'=>'Dicipta pada','Open / Download'=>'Buka / Muat Turun','Save Announcement'=>'Simpan Pengumuman','Announcement Details'=>'Butiran Pengumuman','Title and content are required.'=>'Tajuk dan kandungan diperlukan.','Content'=>'Kandungan','Create company notice with optional attachment.'=>'Cipta notis syarikat dengan lampiran pilihan.','Users need to open the announcement details page; it will auto mark as read.'=>'Pengguna perlu membuka halaman butiran pengumuman; ia akan ditanda sebagai dibaca secara automatik.'
    ];
    $ann_zh = [
        'Communication / Announcements'=>'沟通 / 公告','Communication / Add Announcement'=>'沟通 / 添加公告','Announcement'=>'公告','Company notices and internal updates'=>'公司通知和内部更新','Read Status'=>'阅读状态','Search announcement...'=>'搜索公告...','Active Only'=>'只看启用','Total Announcements'=>'公告总数','Active announcements'=>'启用公告','Unread (You)'=>'未读（你）','Branches'=>'分行','Total Branches'=>'分行总数','Total branches'=>'分行总数','Read / Total'=>'已读 / 总数','read rate'=>'阅读率','Target'=>'对象','Read Info'=>'阅读资料','View Details'=>'查看详情','No announcements found.'=>'没有找到公告。','Posted by'=>'发布者','Posted By'=>'发布者','Read on'=>'阅读于','Read'=>'已读','Unread'=>'未读','Delete this announcement?'=>'确定删除此公告？','Opened announcement will be automatically marked as read'=>'打开公告后会自动标记为已读','This announcement has been marked as read automatically'=>'此公告已自动标记为已读','Read Rate'=>'阅读率','Created at'=>'创建时间','Open / Download'=>'打开 / 下载','Save Announcement'=>'保存公告','Announcement Details'=>'公告详情','Title and content are required.'=>'标题和内容不能为空。','Content'=>'内容','Create company notice with optional attachment.'=>'创建公司通知，可选择上传附件。','Users need to open the announcement details page; it will auto mark as read.'=>'用户需要打开公告详情页，系统会自动标记为已读。'
    ];
    $ms = array_merge($ms, $ann_ms);
    $zh = array_merge($zh, $ann_zh);



    // Final UI i18n fix for KPI, Asset, Closed Ticket, Ticket List, Knowledge Base and Create Ticket hardcoded text.
    $final_ms = [
        'Dynamic status report linked with Ticket Status Management.'=>'Laporan status dinamik dipautkan dengan Pengurusan Status Tiket.',
        'Month'=>'Bulan','View'=>'Lihat','Total Tickets'=>'Jumlah Tiket','Active Tickets'=>'Tiket Aktif','Closed Tickets'=>'Tiket Ditutup','Overdue Tickets'=>'Tiket Lewat','SLA Compliance'=>'Pematuhan SLA','Status Summary'=>'Ringkasan Status','Closed?'=>'Ditutup?','Yes'=>'Ya','No'=>'Tidak','No status found'=>'Tiada status dijumpai','Branch Summary'=>'Ringkasan Cawangan','Person In Charge Summary'=>'Ringkasan PIC','No data'=>'Tiada data',
        'Manage POS, printers, scanners, PCs, network equipment and photos.'=>'Urus POS, pencetak, pengimbas, PC, peralatan rangkaian dan gambar.',
        'Search asset code, name, branch, serial no...'=>'Cari kod aset, nama, cawangan, no siri...','Photo'=>'Gambar','Asset Code'=>'Kod Aset','Type'=>'Jenis','Serial No'=>'No Siri','No Photo'=>'Tiada Gambar','History'=>'Sejarah','Disable'=>'Nyahaktif','Repair'=>'Baik Pulih','Disposed'=>'Dilupuskan',
        'Register equipment with photo, branch, serial number and purchase information.'=>'Daftar peralatan dengan gambar, cawangan, nombor siri dan maklumat pembelian.',
        'Back to Asset List'=>'Kembali ke Senarai Aset','Asset Name'=>'Nama Aset','Asset Type'=>'Jenis Aset','Select Asset Type'=>'Pilih Jenis Aset','Controlled by Asset Type Management.'=>'Dikawal oleh Pengurusan Jenis Aset.','Brand'=>'Jenama','Model'=>'Model','Serial number'=>'Nombor siri','Location'=>'Lokasi','Purchase Date'=>'Tarikh Pembelian','Remark'=>'Catatan','Warranty, supplier, maintenance note...'=>'Waranti, pembekal, nota penyelenggaraan...','Asset Photo'=>'Gambar Aset','Upload equipment photo. JPG, PNG, GIF or WEBP. Maximum 5MB.'=>'Muat naik gambar peralatan. JPG, PNG, GIF atau WEBP. Maksimum 5MB.','No photo selected'=>'Tiada gambar dipilih','Save Asset'=>'Simpan Aset',
        'Tickets marked as closed in Ticket Status Management are separated here and will not appear in Ticket List or Overdue.'=>'Tiket yang ditanda ditutup dalam Pengurusan Status Tiket diasingkan di sini dan tidak akan muncul dalam Senarai Tiket atau Lewat.',
        'Closed-status tickets are archived in this page only. They are excluded from normal Ticket List and Overdue counts.'=>'Tiket status ditutup diarkibkan hanya di halaman ini. Ia dikecualikan daripada Senarai Tiket biasa dan kiraan Lewat.','Active Ticket List'=>'Senarai Tiket Aktif','All Closed'=>'Semua Ditutup',
        'Active tickets are shown here. Closed tickets are separated into Closed Tickets.'=>'Tiket aktif dipaparkan di sini. Tiket ditutup diasingkan ke Tiket Ditutup.',
        'Enabled tickets are shown here. Closed tickets are separated into Closed Tickets.'=>'Tiket aktif dipaparkan di sini. Tiket ditutup diasingkan ke Tiket Ditutup.',
        'Add Article'=>'Tambah Artikel','No views yet.'=>'Belum ada paparan.','No articles found'=>'Tiada artikel dijumpai','Showing'=>'Memaparkan','Branch scoped articles are filtered by user branch.'=>'Artikel skop cawangan ditapis mengikut cawangan pengguna.','Article structure supports Type, Tags and Branch Scope. Create Ticket will suggest related articles by selected Category.'=>'Struktur artikel menyokong Jenis, Tag dan Skop Cawangan. Cipta Tiket akan mencadangkan artikel berkaitan berdasarkan Kategori yang dipilih.','Organized by Category, Type, Tags and Branch Scope'=>'Disusun mengikut Kategori, Jenis, Tag dan Skop Cawangan','No views yet'=>'Belum ada paparan',
        'Example: POS Maintenance'=>'Contoh: Penyelenggaraan POS','Write announcement content, date, time and instructions...'=>'Tulis kandungan pengumuman, tarikh, masa dan arahan...','Leave empty = active immediately'=>'Biarkan kosong = aktif serta-merta','Leave empty = no expiry'=>'Biarkan kosong = tiada tamat tempoh',
        'Printer Type:'=>'Jenis Pencetak:','Printer Location:'=>'Lokasi Pencetak:','Problem:'=>'Masalah:','Error Light / Message:'=>'Lampu / Mesej Ralat:','Paper / Ribbon checked: Yes / No'=>'Kertas / Ribbon diperiksa: Ya / Tidak','Photo attached: Yes / No'=>'Gambar dilampirkan: Ya / Tidak','Affected Area:'=>'Kawasan Terjejas:','How many devices affected:'=>'Berapa peranti terjejas:','Router / switch status:'=>'Status router / switch:','When it started:'=>'Bila bermula:','Urgency / business impact:'=>'Kesan segera / operasi:','Title:'=>'Tajuk:','Category:'=>'Kategori:','Priority:'=>'Keutamaan:',
        'Select a category to see related articles.'=>'Pilih kategori untuk melihat artikel berkaitan.','No suggested article for this category yet.'=>'Tiada artikel cadangan untuk kategori ini lagi.','Suggested Knowledge'=>'Cadangan Pengetahuan','Live Preview'=>'Pratonton Langsung','Preview will appear here...'=>'Pratonton akan dipaparkan di sini...','SLA target:'=>'Sasaran SLA:','Selected:'=>'Dipilih:'
    ];
    $final_zh = [
        'Dynamic status report linked with Ticket Status Management.'=>'动态状态报告已连接工单状态管理。',
        'Month'=>'月份','View'=>'查看','Total Tickets'=>'总工单','Active Tickets'=>'活跃工单','Closed Tickets'=>'已关闭工单','Overdue Tickets'=>'逾期工单','SLA Compliance'=>'SLA 达标率','Status Summary'=>'状态汇总','Closed?'=>'已关闭？','Yes'=>'是','No'=>'否','No status found'=>'没有找到状态','Branch Summary'=>'分行汇总','Person In Charge Summary'=>'PIC 汇总','No data'=>'没有数据',
        'Manage POS, printers, scanners, PCs, network equipment and photos.'=>'管理 POS、打印机、扫描器、电脑、网络设备和照片。',
        'Search asset code, name, branch, serial no...'=>'搜索资产编号、名称、分行、序列号...','Photo'=>'照片','Asset Code'=>'资产编号','Type'=>'类型','Serial No'=>'序列号','No Photo'=>'无照片','History'=>'历史','Disable'=>'停用','Repair'=>'维修','Disposed'=>'已报废',
        'Register equipment with photo, branch, serial number and purchase information.'=>'登记设备照片、分行、序列号和购买资料。',
        'Back to Asset List'=>'返回资产列表','Asset Name'=>'资产名称','Asset Type'=>'资产类型','Select Asset Type'=>'选择资产类型','Controlled by Asset Type Management.'=>'由资产类型管理控制。','Brand'=>'品牌','Model'=>'型号','Serial number'=>'序列号','Location'=>'位置','Purchase Date'=>'购买日期','Remark'=>'备注','Warranty, supplier, maintenance note...'=>'保修、供应商、维修备注...','Asset Photo'=>'资产照片','Upload equipment photo. JPG, PNG, GIF or WEBP. Maximum 5MB.'=>'上传设备照片。JPG、PNG、GIF 或 WEBP。最大 5MB。','No photo selected'=>'未选择照片','Save Asset'=>'保存资产',
        'Tickets marked as closed in Ticket Status Management are separated here and will not appear in Ticket List or Overdue.'=>'在工单状态管理标记为已关闭的工单会分开显示在这里，不会出现在工单列表或逾期。',
        'Closed-status tickets are archived in this page only. They are excluded from normal Ticket List and Overdue counts.'=>'已关闭状态的工单只归档在此页面，不计入普通工单列表和逾期数量。','Active Ticket List'=>'启用工单列表','All Closed'=>'全部已关闭',
        'Active tickets are shown here. Closed tickets are separated into Closed Tickets.'=>'这里显示活跃工单。已关闭工单会分开到已关闭工单。',
        'Enabled tickets are shown here. Closed tickets are separated into Closed Tickets.'=>'这里显示启用工单。已关闭工单会分开到已关闭工单。',
        'Add Article'=>'添加文章','No views yet.'=>'暂无浏览。','No articles found'=>'没有找到文章','Showing'=>'显示','Branch scoped articles are filtered by user branch.'=>'分行范围文章会根据用户分行筛选。','Article structure supports Type, Tags and Branch Scope. Create Ticket will suggest related articles by selected Category.'=>'文章结构支持类型、标签和分行范围。创建工单会根据所选分类推荐相关文章。','Organized by Category, Type, Tags and Branch Scope'=>'按分类、类型、标签和分行范围整理','No views yet'=>'暂无浏览',
        'Example: POS Maintenance'=>'例如：POS 维护','Write announcement content, date, time and instructions...'=>'填写公告内容、日期、时间和指示...','Leave empty = active immediately'=>'留空 = 立即启用','Leave empty = no expiry'=>'留空 = 不过期',
        'Printer Type:'=>'打印机类型：','Printer Location:'=>'打印机位置：','Problem:'=>'问题：','Error Light / Message:'=>'错误灯号 / 信息：','Paper / Ribbon checked: Yes / No'=>'纸张 / Ribbon 已检查：是 / 否','Photo attached: Yes / No'=>'已附照片：是 / 否','Affected Area:'=>'受影响区域：','How many devices affected:'=>'受影响设备数量：','Router / switch status:'=>'Router / Switch 状态：','When it started:'=>'开始时间：','Urgency / business impact:'=>'紧急程度 / 营运影响：','Title:'=>'标题：','Category:'=>'分类：','Priority:'=>'优先级：',
        'Select a category to see related articles.'=>'选择分类以查看相关文章。','No suggested article for this category yet.'=>'此分类暂时没有推荐文章。','Suggested Knowledge'=>'推荐知识库','Live Preview'=>'实时预览','Preview will appear here...'=>'预览会显示在这里...','SLA target:'=>'SLA 目标：','Selected:'=>'已选择：'
    ];


    // Extra full UI coverage for management/report/asset/KB/ticket pages.
    $ultra_ms = [
        'Add user'=>'Tambah pengguna','Add User'=>'Tambah Pengguna','Create Admin / Head / Staff user with checkbox ticket visibility.'=>'Cipta pengguna Admin / Head / Staff dengan pilihan keterlihatan tiket.','Basic Info'=>'Maklumat Asas','Basic User Info'=>'Maklumat Asas Pengguna','Login information.'=>'Maklumat log masuk.','Full Name'=>'Nama Penuh','Role & Primary Branch'=>'Peranan & Cawangan Utama','Only Admin, Head and Staff are supported.'=>'Hanya Admin, Head dan Staff disokong.','Primary Branch'=>'Cawangan Utama','Profile PIC / Department'=>'PIC Profil / Jabatan','Staff: only own Primary Branch + checked User Own PIC tickets.'=>'Staff: hanya tiket Cawangan Utama sendiri + PIC sendiri yang ditanda.','Ticket Visibility Permission'=>'Kebenaran Keterlihatan Tiket','Checkbox access. Staff = Own Branch + User Own PIC.'=>'Akses kotak semak. Staff = Cawangan sendiri + PIC sendiri.','Final rule:'=>'Peraturan akhir:','Admin sees all. Head sees checked Branch + checked PIC. Staff sees own Primary Branch + checked User Own PIC only.'=>'Admin melihat semua. Head melihat Cawangan + PIC yang ditanda. Staff hanya melihat Cawangan Utama sendiri + PIC sendiri yang ditanda.','Branch Access for Head'=>'Akses Cawangan untuk Head','Select All Branch'=>'Pilih Semua Cawangan','Clear Branch'=>'Kosongkan Cawangan','Create User'=>'Cipta Pengguna','Add users'=>'Tambah pengguna','Manage login, branch access and PIC visibility. Module / Action permissions are inherited from Role Permission Matrix.'=>'Urus log masuk, akses cawangan dan keterlihatan PIC. Kebenaran modul / tindakan diwarisi daripada Matriks Kebenaran Peranan.','Total Users'=>'Jumlah Pengguna','Enabled'=>'Aktif','Head / Staff'=>'Head / Staff','Search user, branch, PIC, permission...'=>'Cari pengguna, cawangan, PIC, kebenaran...','Compact'=>'Padat','Expand All'=>'Kembang Semua','System Administrator'=>'Pentadbir Sistem','All Tickets'=>'Semua Tiket','Branch / PIC Tickets'=>'Tiket Cawangan / PIC','Copy'=>'Salin','Disable'=>'Nyahaktif','Enable'=>'Aktifkan','Control Panel'=>'Panel Kawalan','3-role system only: Admin, Head, Staff. Tick the functions here once and every user under that role follows it.'=>'Sistem 3 peranan sahaja: Admin, Head, Staff. Tandakan fungsi di sini sekali dan semua pengguna di bawah peranan itu akan mengikutinya.','Total Users'=>'Jumlah Pengguna','Enabled PIC'=>'PIC Aktif','Controls ticket assign dropdown. Staff visibility follows assigned_to.'=>'Mengawal senarai tugasan tiket. Keterlihatan Staff mengikut assigned_to.','Asset type master list used by Add Asset and Edit Asset.'=>'Senarai induk jenis aset digunakan oleh Tambah Aset dan Edit Aset.','Ticket category setup linked to Create Ticket and reports.'=>'Tetapan kategori tiket dihubungkan dengan Cipta Tiket dan laporan.','SLA rules linked to due date and monitoring.'=>'Peraturan SLA dihubungkan dengan tarikh akhir dan pemantauan.','Controls ticket status dropdown, badge color and closed/archive logic.'=>'Mengawal status tiket, warna lencana dan logik tutup/arkib.','Review administration changes.'=>'Semak perubahan pentadbiran.','Role Permission Checkbox Matrix'=>'Matriks Kotak Semak Kebenaran Peranan','Admin always has all permissions and is not shown. Head / Staff are controlled here globally.'=>'Admin sentiasa mempunyai semua kebenaran dan tidak dipaparkan. Head / Staff dikawal secara global di sini.','Tick all Head'=>'Tanda semua Head','Clear Head'=>'Kosongkan Head','Tick all Staff'=>'Tanda semua Staff','Clear Staff'=>'Kosongkan Staff','Function'=>'Fungsi','Menu / Page Access'=>'Akses Menu / Halaman','Save Role Permissions'=>'Simpan Kebenaran Peranan','Central administration dashboard and role permission checkbox matrix.'=>'Papan pemuka pentadbiran pusat dan matriks kebenaran peranan.','View main dashboard and live summary.'=>'Lihat papan pemuka utama dan ringkasan langsung.','Create new support tickets.'=>'Cipta tiket sokongan baharu.',
        'Add Branch'=>'Tambah Cawangan','Add, edit, disable or delete branch records.'=>'Tambah, edit, nyahaktif atau padam rekod cawangan.','Branch Code'=>'Kod Cawangan','Branch Name'=>'Nama Cawangan','Branch List'=>'Senarai Cawangan','Disabled items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.'=>'Item yang dinyahaktif tidak akan muncul dalam dropdown. Rekod yang digunakan akan dinyahaktif dan bukan dipadam untuk melindungi tiket lama.','Toggle'=>'Tukar','Add PIC'=>'Tambah PIC','PIC List'=>'Senarai PIC','Add Assign To'=>'Tambah Tugasan Kepada','Example: IT Team, KIAT, Vendor, POS Support.'=>'Contoh: IT Team, KIAT, Vendor, POS Support.','Assign To List'=>'Senarai Tugasan Kepada','Optional Email'=>'E-mel Pilihan','Email Optional'=>'E-mel Pilihan','optional@email.com'=>'optional@email.com','Optional'=>'Pilihan','Add SLA'=>'Tambah SLA','SLA Hours'=>'Jam SLA','SLA List'=>'Senarai SLA','Add Category'=>'Tambah Kategori','Category List'=>'Senarai Kategori','Default Priority'=>'Keutamaan Lalai','Add Asset Type'=>'Tambah Jenis Aset','Asset Type Name'=>'Nama Jenis Aset','Example: POS, Printer, Router'=>'Contoh: POS, Pencetak, Router','Sort Order'=>'Urutan Susun','Asset Type List'=>'Senarai Jenis Aset','Used'=>'Digunakan','Linked pages:'=>'Halaman berkaitan:','Add Asset and Edit Asset will automatically use active Asset Types from this list. Asset Types already used by assets cannot be deleted; use Disable instead. Deleted unused default types will no longer come back automatically.'=>'Tambah Aset dan Edit Aset akan menggunakan Jenis Aset aktif daripada senarai ini secara automatik. Jenis Aset yang telah digunakan tidak boleh dipadam; gunakan Nyahaktif. Jenis lalai yang dipadam dan tidak digunakan tidak akan kembali secara automatik.','Add Ticket Status'=>'Tambah Status Tiket','Fully dynamic: rename, color, sort, active and closed/archive behaviour.'=>'Sepenuhnya dinamik: tukar nama, warna, susunan, aktif dan tingkah laku tutup/arkib.','Example: Waiting Vendor, Waiting Branch, On Hold, Cancelled.'=>'Contoh: Menunggu Vendor, Menunggu Cawangan, On Hold, Dibatalkan.','Status Name'=>'Nama Status','Badge Color'=>'Warna Lencana','Sort'=>'Susun','Sort Order'=>'Urutan Susun','Closed?'=>'Ditutup?','Status List'=>'Senarai Status','Rename will sync existing tickets. Delete is only allowed when Usage = 0.'=>'Tukar nama akan menyelaraskan tiket sedia ada. Padam hanya dibenarkan apabila Usage = 0.','Color'=>'Warna','Active?'=>'Aktif?','Usage'=>'Penggunaan','Preview'=>'Pratonton','In Use'=>'Digunakan','Red / Danger'=>'Merah / Bahaya','Yellow / Warning'=>'Kuning / Amaran','Blue / Info'=>'Biru / Info','Green / Success'=>'Hijau / Berjaya','Grey / Secondary'=>'Kelabu / Sekunder','Dark'=>'Gelap','Bootstrap badge color class.'=>'Kelas warna lencana Bootstrap.',
        'Audit Log'=>'Log Audit','Track login, logout, create, edit, delete and export activities.'=>'Jejak aktiviti log masuk, log keluar, cipta, edit, padam dan eksport.','Total Logs'=>'Jumlah Log','Today Activity'=>'Aktiviti Hari Ini','Last Activity'=>'Aktiviti Terakhir','Per Page'=>'Setiap Halaman','Keyword'=>'Kata Kunci','Search user, action or details...'=>'Cari pengguna, tindakan atau butiran...','Activity List'=>'Senarai Aktiviti','filtered records'=>'rekod ditapis','Page'=>'Halaman','Details'=>'Butiran','Reply Ticket'=>'Balas Tiket','Quick Action Confirm'=>'Pengesahan Tindakan Pantas','logged in'=>'log masuk','Edit Ticket'=>'Edit Tiket','Updated Ticket'=>'Tiket Dikemas Kini','Print'=>'Cetak','Export CSV'=>'Eksport CSV',
        'ARTICLE'=>'ARTIKEL','Article Content'=>'Kandungan Artikel','Create organized knowledge article with type, tags and branch scope'=>'Cipta artikel pengetahuan tersusun dengan jenis, tag dan skop cawangan','Knowledge Type'=>'Jenis Pengetahuan','Tags'=>'Tag','Separate tags by comma. Example: POS, sync, price update'=>'Asingkan tag dengan koma. Contoh: POS, sync, kemas kini harga','Attachments'=>'Lampiran','QUICK TEMPLATE'=>'TEMPLAT PANTAS','SOP Template'=>'Templat SOP','FAQ Template'=>'Templat Soalan Lazim','Troubleshooting Template'=>'Templat Penyelesaian Masalah','Branch Scope'=>'Skop Cawangan','Selected Branch Only'=>'Cawangan Dipilih Sahaja','All Branches'=>'Semua Cawangan','Add Article'=>'Tambah Artikel','Create Article'=>'Cipta Artikel','Back to Knowledge Base'=>'Kembali ke Pangkalan Pengetahuan',
        'CANCELLED'=>'DIBATALKAN','Cancelled'=>'Dibatalkan','Export'=>'Eksport','Active Ticket List'=>'Senarai Tiket Aktif','All Closed'=>'Semua Ditutup','Closed-status tickets are archived in this page only. They are excluded from normal Ticket List and Overdue counts.'=>'Tiket status ditutup hanya diarkibkan di halaman ini. Ia tidak termasuk kiraan Senarai Tiket biasa dan Lewat.','Tickets marked as closed in Ticket Status Management are separated here and will not appear in Ticket List or Overdue.'=>'Tiket yang ditanda ditutup dalam Pengurusan Status Tiket diasingkan di sini dan tidak muncul dalam Senarai Tiket atau Lewat.',
        'Dynamic status report linked with Ticket Status Management.'=>'Laporan status dinamik dihubungkan dengan Pengurusan Status Tiket.','Summary'=>'Ringkasan','Branch Summary'=>'Ringkasan Cawangan','PIC Summary'=>'Ringkasan PIC','Person In Charge Summary'=>'Ringkasan PIC','SLA Compliance'=>'Pematuhan SLA','Overdue Tickets'=>'Tiket Lewat','Closed Tickets'=>'Tiket Ditutup',
        'Total'=>'Jumlah','Read / Total'=>'Dibaca / Jumlah','Unread (You)'=>'Belum Dibaca (Anda)','Posted by'=>'Dihantar oleh','Posted By'=>'Dihantar Oleh','Read Info'=>'Maklumat Bacaan','Target'=>'Sasaran','Company notices and internal updates'=>'Notis syarikat dan kemas kini dalaman','Search announcement...'=>'Cari pengumuman...','Enabled Only'=>'Aktif Sahaja','Opened announcement will be automatically marked as read.'=>'Pengumuman yang dibuka akan ditanda dibaca secara automatik.','This announcement has been marked as read automatically.'=>'Pengumuman ini telah ditanda dibaca secara automatik.','Read Rate'=>'Kadar Bacaan','Created at'=>'Dicipta pada','Save Announcement'=>'Simpan Pengumuman','Announcement Details'=>'Butiran Pengumuman','Users need to open the announcement details before the system automatically marks it as read.'=>'Pengguna perlu membuka butiran pengumuman sebelum sistem menandanya sebagai dibaca secara automatik.',
        'Asset dropdown is linked to Role Permission Matrix and active/repair Asset Management records.'=>'Dropdown aset dihubungkan dengan Matriks Kebenaran Peranan dan rekod Pengurusan Aset aktif/baik pulih.','No active/repair asset found for your allowed branch. Add asset in Asset Management or check asset branch/status.'=>'Tiada aset aktif/baik pulih ditemui untuk cawangan dibenarkan. Tambah aset dalam Pengurusan Aset atau semak cawangan/status aset.','Controlled by Role Permission Matrix: enable Asset List or Select Asset In Ticket.'=>'Dikawal oleh Matriks Kebenaran Peranan: aktifkan Senarai Aset atau Pilih Aset Dalam Tiket.','No Permission To Select Asset'=>'Tiada Kebenaran Memilih Aset','No Asset Selected'=>'Tiada Aset Dipilih','No suggested article for this category yet.'=>'Tiada artikel cadangan untuk kategori ini lagi.','Title: (No title)'=>'Tajuk: (Tiada tajuk)','Title:'=>'Tajuk:','Category:'=>'Kategori:','Priority:'=>'Keutamaan:','SLA target:'=>'Sasaran SLA:','Controlled by SLA Management.'=>'Dikawal oleh Pengurusan SLA.','Printer Type:'=>'Jenis Pencetak:','Printer Location:'=>'Lokasi Pencetak:','Error Light / Message:'=>'Lampu / Mesej Ralat:','Paper / Ribbon checked: Yes / No'=>'Kertas / Ribbon diperiksa: Ya / Tidak','Photo attached: Yes / No'=>'Gambar dilampirkan: Ya / Tidak'
    ];

    $ultra_zh = [
        'Add user'=>'添加用户','Add User'=>'添加用户','Create Admin / Head / Staff user with checkbox ticket visibility.'=>'创建 Admin / Head / Staff 用户，并可勾选工单可见权限。','Basic Info'=>'基本资料','Basic User Info'=>'基本用户资料','Login information.'=>'登录资料。','Full Name'=>'全名','Role & Primary Branch'=>'角色与主要分行','Only Admin, Head and Staff are supported.'=>'仅支持 Admin、Head 和 Staff。','Primary Branch'=>'主要分行','Profile PIC / Department'=>'Profile PIC / 部门','Staff: only own Primary Branch + checked User Own PIC tickets.'=>'Staff：只可查看自己主要分行 + 已勾选的自己 PIC 工单。','Ticket Visibility Permission'=>'工单可见权限','Checkbox access. Staff = Own Branch + User Own PIC.'=>'勾选权限。Staff = 自己分行 + 自己 PIC。','Final rule:'=>'最终规则：','Admin sees all. Head sees checked Branch + checked PIC. Staff sees own Primary Branch + checked User Own PIC only.'=>'Admin 可看全部。Head 可看已勾选分行 + 已勾选 PIC。Staff 只看自己主要分行 + 已勾选的自己 PIC。','Branch Access for Head'=>'Head 分行权限','Select All Branch'=>'选择全部分行','Clear Branch'=>'清除分行','Create User'=>'创建用户','Add users'=>'添加用户','Manage login, branch access and PIC visibility. Module / Action permissions are inherited from Role Permission Matrix.'=>'管理登录、分行权限和 PIC 可见范围。模块 / 操作权限继承自角色权限矩阵。','Total Users'=>'用户总数','Enabled'=>'启用','Head / Staff'=>'Head / Staff','Search user, branch, PIC, permission...'=>'搜索用户、分行、PIC、权限...','Compact'=>'紧凑','Expand All'=>'展开全部','System Administrator'=>'系统管理员','All Tickets'=>'全部工单','Branch / PIC Tickets'=>'分行 / PIC 工单','Copy'=>'复制','Disable'=>'停用','Enable'=>'启用','Control Panel'=>'控制面板','3-role system only: Admin, Head, Staff. Tick the functions here once and every user under that role follows it.'=>'仅三种角色系统：Admin、Head、Staff。这里勾选一次，该角色下所有用户都会跟随。','Total Users'=>'用户总数','Enabled PIC'=>'启用 PIC','Controls ticket assign dropdown. Staff visibility follows assigned_to.'=>'控制工单指派下拉选单。Staff 可见性跟随 assigned_to。','Asset type master list used by Add Asset and Edit Asset.'=>'资产类型主列表用于添加资产和编辑资产。','Ticket category setup linked to Create Ticket and reports.'=>'工单分类设置会连接创建工单和报表。','SLA rules linked to due date and monitoring.'=>'SLA 规则连接到到期日和监控。','Controls ticket status dropdown, badge color and closed/archive logic.'=>'控制工单状态下拉、标签颜色和关闭/归档逻辑。','Review administration changes.'=>'查看管理变更。','Role Permission Checkbox Matrix'=>'角色权限勾选矩阵','Admin always has all permissions and is not shown. Head / Staff are controlled here globally.'=>'Admin 默认拥有所有权限，不会显示。Head / Staff 在这里统一控制。','Tick all Head'=>'勾选全部 Head','Clear Head'=>'清除 Head','Tick all Staff'=>'勾选全部 Staff','Clear Staff'=>'清除 Staff','Function'=>'功能','Menu / Page Access'=>'菜单 / 页面权限','Save Role Permissions'=>'保存角色权限','Central administration dashboard and role permission checkbox matrix.'=>'中央管理仪表板和角色权限勾选矩阵。','View main dashboard and live summary.'=>'查看主仪表板和实时摘要。','Create new support tickets.'=>'创建新的支援工单。',
        'Add Branch'=>'添加分行','Add, edit, disable or delete branch records.'=>'添加、编辑、停用或删除分行记录。','Branch Code'=>'分行代码','Branch Name'=>'分行名称','Branch List'=>'分行列表','Disabled items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.'=>'停用项目不会出现在下拉选单。已使用记录会改为停用而不是删除，以保护旧工单。','Toggle'=>'切换','Add PIC'=>'添加 PIC','PIC List'=>'PIC 列表','Add Assign To'=>'添加指派对象','Example: IT Team, KIAT, Vendor, POS Support.'=>'例如：IT Team、KIAT、Vendor、POS Support。','Assign To List'=>'指派对象列表','Optional Email'=>'可选邮箱','Email Optional'=>'可选邮箱','optional@email.com'=>'optional@email.com','Optional'=>'可选','Add SLA'=>'添加 SLA','SLA Hours'=>'SLA 小时','SLA List'=>'SLA 列表','Add Category'=>'添加分类','Category List'=>'分类列表','Default Priority'=>'默认优先级','Add Asset Type'=>'添加资产类型','Asset Type Name'=>'资产类型名称','Example: POS, Printer, Router'=>'例如：POS、打印机、Router','Sort Order'=>'排序','Asset Type List'=>'资产类型列表','Used'=>'已使用','Linked pages:'=>'关联页面：','Add Asset and Edit Asset will automatically use active Asset Types from this list. Asset Types already used by assets cannot be deleted; use Disable instead. Deleted unused default types will no longer come back automatically.'=>'添加资产和编辑资产会自动使用此列表中的启用资产类型。已被资产使用的资产类型不能删除，请使用停用。已删除且未使用的默认类型不会自动恢复。','Add Ticket Status'=>'添加工单状态','Fully dynamic: rename, color, sort, active and closed/archive behaviour.'=>'完全动态：名称、颜色、排序、启用和关闭/归档行为。','Example: Waiting Vendor, Waiting Branch, On Hold, Cancelled.'=>'例如：等待供应商、等待分行、暂停、已取消。','Status Name'=>'状态名称','Badge Color'=>'标签颜色','Sort'=>'排序','Closed?'=>'已关闭？','Status List'=>'状态列表','Rename will sync existing tickets. Delete is only allowed when Usage = 0.'=>'改名会同步现有工单。只有 Usage = 0 时才允许删除。','Color'=>'颜色','Active?'=>'启用？','Usage'=>'使用量','Preview'=>'预览','In Use'=>'使用中','Red / Danger'=>'红色 / 危险','Yellow / Warning'=>'黄色 / 警告','Blue / Info'=>'蓝色 / 信息','Green / Success'=>'绿色 / 成功','Grey / Secondary'=>'灰色 / 次要','Dark'=>'深色','Bootstrap badge color class.'=>'Bootstrap 标签颜色类别。',
        'Audit Log'=>'审计日志','Track login, logout, create, edit, delete and export activities.'=>'追踪登录、退出、创建、编辑、删除和导出活动。','Total Logs'=>'日志总数','Today Activity'=>'今日活动','Last Activity'=>'最后活动','Per Page'=>'每页','Keyword'=>'关键词','Search user, action or details...'=>'搜索用户、操作或详情...','Activity List'=>'活动列表','filtered records'=>'筛选记录','Page'=>'页','Details'=>'详情','Reply Ticket'=>'回复工单','Quick Action Confirm'=>'快速操作确认','logged in'=>'已登录','Edit Ticket'=>'编辑工单','Updated Ticket'=>'已更新工单','Print'=>'打印','Export CSV'=>'导出 CSV',
        'ARTICLE'=>'文章','Article Content'=>'文章内容','Create organized knowledge article with type, tags and branch scope'=>'创建带类型、标签和分行范围的知识文章','Knowledge Type'=>'知识库类型','Tags'=>'标签','Separate tags by comma. Example: POS, sync, price update'=>'用逗号分隔标签。例如：POS、同步、价格更新','Attachments'=>'附件','QUICK TEMPLATE'=>'快速模板','SOP Template'=>'SOP 模板','FAQ Template'=>'常见问题模板','Troubleshooting Template'=>'故障排查模板','Branch Scope'=>'分行范围','Selected Branch Only'=>'仅选择的分行','All Branches'=>'全部分行','Add Article'=>'添加文章','Create Article'=>'创建文章','Back to Knowledge Base'=>'返回知识库',
        'CANCELLED'=>'已取消','Cancelled'=>'已取消','Export'=>'导出','Active Ticket List'=>'启用工单列表','All Closed'=>'全部已关闭','Closed-status tickets are archived in this page only. They are excluded from normal Ticket List and Overdue counts.'=>'已关闭状态的工单只归档在此页面，不计入普通工单列表和逾期数量。','Tickets marked as closed in Ticket Status Management are separated here and will not appear in Ticket List or Overdue.'=>'在工单状态管理标记为已关闭的工单会分开显示在这里，不会出现在工单列表或逾期。',
        'Dynamic status report linked with Ticket Status Management.'=>'动态状态报告已连接工单状态管理。','Summary'=>'汇总','Branch Summary'=>'分行汇总','PIC Summary'=>'PIC 汇总','Person In Charge Summary'=>'PIC 汇总','SLA Compliance'=>'SLA 达标率','Overdue Tickets'=>'逾期工单','Closed Tickets'=>'已关闭工单',
        'Total'=>'总数','Read / Total'=>'已读 / 总数','Unread (You)'=>'未读（你）','Posted by'=>'发布者','Posted By'=>'发布者','Read Info'=>'阅读资料','Target'=>'目标','Company notices and internal updates'=>'公司通知和内部更新','Search announcement...'=>'搜索公告...','Enabled Only'=>'只显示启用','Opened announcement will be automatically marked as read.'=>'打开公告后会自动标记为已读。','This announcement has been marked as read automatically.'=>'此公告已自动标记为已读。','Read Rate'=>'阅读率','Created at'=>'创建时间','Save Announcement'=>'保存公告','Announcement Details'=>'公告详情','Users need to open the announcement details before the system automatically marks it as read.'=>'用户需要打开公告详情，系统才会自动标记为已读。',
        'Asset dropdown is linked to Role Permission Matrix and active/repair Asset Management records.'=>'资产下拉选单已连接角色权限矩阵和启用/维修中的资产管理记录。','No active/repair asset found for your allowed branch. Add asset in Asset Management or check asset branch/status.'=>'你的允许分行没有启用/维修中的资产。请在资产管理添加资产或检查资产分行/状态。','Controlled by Role Permission Matrix: enable Asset List or Select Asset In Ticket.'=>'由角色权限矩阵控制：请启用资产列表或工单中选择资产。','No Permission To Select Asset'=>'无权限选择资产','No Asset Selected'=>'未选择资产','No suggested article for this category yet.'=>'此分类暂时没有推荐文章。','Title: (No title)'=>'标题：（无标题）','Title:'=>'标题：','Category:'=>'分类：','Priority:'=>'优先级：','SLA target:'=>'SLA 目标：','Controlled by SLA Management.'=>'由 SLA 管理控制。','Printer Type:'=>'打印机类型：','Printer Location:'=>'打印机位置：','Error Light / Message:'=>'错误灯号 / 信息：','Paper / Ribbon checked: Yes / No'=>'纸张 / Ribbon 已检查：是 / 否','Photo attached: Yes / No'=>'已附照片：是 / 否'
    ];
    $ms = array_merge($ms, $ultra_ms);
    $zh = array_merge($zh, $ultra_zh);

    $final_ms = array_merge($final_ms, [
        'Update asset details and equipment photo.'=>'Kemas kini maklumat aset dan gambar peralatan.',
        'Linked Tickets'=>'Tiket Berkaitan',
        'Select Person In Charge'=>'Pilih Pegawai Bertanggungjawab'
    ]);

    $ms = array_merge($ms, $final_ms);
    $zh = array_merge($zh, $final_zh);


    // Extra UI translations added for full three-language free switching.
    $extra_ms = [
        'Add' => 'Tambah',
        'Toggle' => 'Tukar',
        'In Use' => 'Digunakan',
        'Used' => 'Digunakan',
        'Usage' => 'Penggunaan',
        'Preview' => 'Pratonton',
        'Color' => 'Warna',
        'Sort' => 'Susunan',
        'Sort Order' => 'Susunan',
        'Badge Color' => 'Warna Label',
        'Red / Danger' => 'Merah / Bahaya',
        'Yellow / Warning' => 'Kuning / Amaran',
        'Blue / Info' => 'Biru / Maklumat',
        'Green / Success' => 'Hijau / Berjaya',
        'Grey / Secondary' => 'Kelabu / Sekunder',
        'Dark' => 'Gelap',
        'Add Ticket Status' => 'Tambah Status Tiket',
        'Status List' => 'Senarai Status',
        'Status Name' => 'Nama Status',
        'Fully dynamic: rename, color, sort, active and closed/archive behaviour.' => 'Dinamik sepenuhnya: nama, warna, susunan, aktif dan kelakuan tutup/arkib.',
        'Example: Waiting Vendor, Waiting Branch, On Hold, Cancelled.' => 'Contoh: Menunggu Vendor, Menunggu Cawangan, Ditahan, Dibatalkan.',
        'Bootstrap badge color class.' => 'Kelas warna label.',
        'Rename allowed; existing tickets will sync.' => 'Penamaan semula akan menyegerakkan tiket sedia ada.',
        'Rename will sync existing tickets. Delete is only allowed when Usage = 0.' => 'Penamaan semula akan menyegerakkan tiket sedia ada. Padam hanya dibenarkan apabila penggunaan = 0.',
        'Add Asset Type' => 'Tambah Jenis Aset',
        'Asset Type List' => 'Senarai Jenis Aset',
        'Asset Type Name' => 'Nama Jenis Aset',
        'Main asset type list used by Add Asset, Edit Asset and Asset filtering.' => 'Urus jenis aset untuk tambah aset, edit aset dan penapisan aset.',
        'Linked pages: Add Asset and Edit Asset will automatically use active Asset Types from this list. Asset Types already used by assets cannot be deleted; use disable instead. Deleted unused default types will no longer come back automatically.' => 'Halaman berkaitan: Tambah Aset dan Edit Aset akan menggunakan jenis aset aktif dari senarai ini. Jenis aset yang telah digunakan tidak boleh dipadam; gunakan nyahaktif.',
        'Add Branch' => 'Tambah Cawangan',
        'Branch List' => 'Senarai Cawangan',
        'Branch Code' => 'Kod Cawangan',
        'Branch Name' => 'Nama Cawangan',
        'Add, edit, disable or delete branch records.' => 'Tambah, edit, nyahaktif atau padam rekod cawangan.',
        'Disabled items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.' => 'Item dinyahaktif tidak akan muncul dalam senarai pilihan. Rekod digunakan akan dinyahaktif, bukan dipadam.',
        'Add PIC' => 'Tambah PIC',
        'PIC List' => 'Senarai PIC',
        'PIC Name' => 'Nama PIC',
        'Add, edit, disable or delete Person In Charge options.' => 'Tambah, edit, nyahaktif atau padam pilihan PIC.',
        'Add, edit, disable or delete 负责人 options.' => 'Tambah, edit, nyahaktif atau padam pilihan PIC.',
        'Used records are disabled instead of deleted to protect old tickets.' => 'Rekod digunakan akan dinyahaktif, bukan dipadam, untuk melindungi tiket lama.',
        'Disabled items will not appear in dropdowns.' => 'Item dinyahaktif tidak akan muncul dalam senarai pilihan.',
        'Add Assign To' => 'Tambah Tugasan',
        'Assign To List' => 'Senarai Tugasan',
        'Assign To Name' => 'Nama Tugasan',
        'Optional Email' => 'Emel Pilihan',
        'optional@email.com' => 'emel pilihan',
        'email optional' => 'emel pilihan',
        'Add, edit, disable or delete Assign To options used in tickets.' => 'Tambah, edit, nyahaktif atau padam pilihan tugasan tiket.',
        'Example: IT Team, KIAT, Vendor, POS Support.' => 'Contoh: IT Team, KIAT, Vendor, POS Support.',
        'Disabled items will not appear in Create Ticket / 指派工单 dropdown.' => 'Item dinyahaktif tidak akan muncul dalam pilihan Cipta Tiket / Tugaskan Tiket.',
        'Add SLA' => 'Tambah SLA',
        'SLA List' => 'Senarai SLA',
        'SLA Hours' => 'Jam SLA',
        'Priority Name' => 'Nama Keutamaan',
        'Add, edit, disable or delete SLA priority rules.' => 'Tambah, edit, nyahaktif atau padam peraturan keutamaan SLA.',
        'Add Category' => 'Tambah Kategori',
        'Category List' => 'Senarai Kategori',
        'Default Priority' => 'Keutamaan Lalai',
        'Add, edit, disable or delete Ticket Category options.' => 'Tambah, edit, nyahaktif atau padam pilihan kategori tiket.',
        'Add user' => 'Tambah Pengguna',
        'Add User' => 'Tambah Pengguna',
        'Create Admin / Head / Staff user with checkbox ticket visibility.' => 'Cipta pengguna Admin / Head / Staff dengan tetapan keterlihatan tiket.',
        'Basic User Info' => 'Maklumat Asas Pengguna',
        'Login information.' => 'Maklumat log masuk.',
        'Full Name' => 'Nama Penuh',
        'Role & Primary Branch' => 'Peranan & Cawangan Utama',
        'Only Admin, Head and Staff are supported.' => 'Hanya Admin, Head dan Staff disokong.',
        'Primary Branch' => 'Cawangan Utama',
        'Profile PIC / Department' => 'PIC Profil / Jabatan',
        'Staff: only own Primary Branch + checked User Own PIC tickets.' => 'Staff: hanya cawangan utama sendiri + tiket PIC sendiri yang ditanda.',
        'Ticket Visibility Permission' => 'Kebenaran Keterlihatan Tiket',
        'Checkbox access. Staff = Own Branch + Own PIC.' => 'Akses kotak semak. Staff = Cawangan sendiri + PIC sendiri.',
        'Final rule: Admin sees all. Head sees checked Branch + checked PIC. Staff sees own Primary Branch + checked User Own PIC only.' => 'Peraturan akhir: Admin melihat semua. Head melihat cawangan dan PIC ditanda. Staff hanya melihat cawangan utama sendiri + PIC sendiri ditanda.',
        'Head Branch Access' => 'Akses Cawangan Head',
        'Select All Branch' => 'Pilih Semua Cawangan',
        'Clear Branch' => 'Kosongkan Cawangan',
        'Create User' => 'Cipta Pengguna',
        'Manage login, branch access and PIC visibility. Module / action permissions are inherited from role Permission Matrix.' => 'Urus log masuk, akses cawangan dan keterlihatan PIC. Kebenaran modul/tindakan diwarisi daripada matriks kebenaran peranan.',
        '100% view optimized. All permission info is shown without +more.' => 'Maklumat kebenaran dipaparkan penuh.',
        'Compact' => 'Padat',
        'Expand All' => 'Kembangkan Semua',
        'Branch Access' => 'Akses Cawangan',
        'Ticket Branch Access' => 'Akses Cawangan Tiket',
        'Ticket PIC Access' => 'Akses PIC Tiket',
        'Role Module Permission' => 'Kebenaran Modul Peranan',
        'Role Action Permission' => 'Kebenaran Tindakan Peranan',
        'All Modules' => 'Semua Modul',
        'All Actions' => 'Semua Tindakan',
        'Copy' => 'Salin',
        'Head / Staff' => 'Head / Staff',
        'Control Panel' => 'Panel Kawalan',
        '3-role system only: Admin, Head, Staff. Tick the functions here once and every user under that role follows it.' => 'Sistem 3 peranan sahaja: Admin, Head, Staff. Tandakan fungsi di sini sekali dan semua pengguna peranan itu akan mengikut.',
        'Role Permission Checkbox Matrix' => 'Matriks Kotak Semak Kebenaran Peranan',
        'Admin always has all permissions and is not shown. Head / Staff are controlled here globally.' => 'Admin sentiasa mempunyai semua kebenaran dan tidak dipaparkan. Head / Staff dikawal di sini secara global.',
        'Tick all Head' => 'Tanda Semua Head',
        'Clear Head' => 'Kosongkan Head',
        'Tick all Staff' => 'Tanda Semua Staff',
        'Clear Staff' => 'Kosongkan Staff',
        'Menu / Page Access' => 'Akses Menu / Halaman',
        'Function' => 'Fungsi',
        'Save Role Permissions' => 'Simpan Kebenaran Peranan',
        'Add Article' => 'Tambah Artikel',
        'Add article' => 'Tambah Artikel',
        'Create organized knowledge article with type, tags and branch scope' => 'Cipta artikel pengetahuan dengan jenis, tag dan skop cawangan',
        'Article Content' => 'Kandungan Artikel',
        'QUICK TEMPLATE' => 'Templat Pantas',
        'SOP Template' => 'Templat SOP',
        'FAQ Template' => 'Templat FAQ',
        'Troubleshooting Template' => 'Templat Penyelesaian Masalah',
        'Question:' => 'Soalan:',
        'Answer:' => 'Jawapan:',
        'When to use this guide:' => 'Bila menggunakan panduan ini:',
        'Related Ticket Category:' => 'Kategori Tiket Berkaitan:',
        'Remark:' => 'Catatan:',
        'Purpose:' => 'Tujuan:',
        'Scope:' => 'Skop:',
        'Step-by-step Procedure:' => 'Prosedur Langkah demi Langkah:',
        'Important Notes:' => 'Nota Penting:',
        'Problem:' => 'Masalah:',
        'Possible Cause:' => 'Kemungkinan Punca:',
        'Checklist:' => 'Senarai Semak:',
        'Solution:' => 'Penyelesaian:',
        'Escalate to:' => 'Eskalasi kepada:',
        'Selected Branch Only' => 'Cawangan Dipilih Sahaja',
        'All Branches' => 'Semua Cawangan',
        'Branch Scope' => 'Skop Cawangan',
        'Tags' => 'Tag',
        'Separate tags by comma. Example: POS, sync, price update' => 'Pisahkan tag dengan koma. Contoh: POS, sync, kemas kini harga',
        'No views yet.' => 'Belum ada paparan.',
        'New Structure' => 'Struktur Baharu',
        'Articles now support Type, Tags and Branch Scope. Create Ticket will suggest related articles by selected Category.' => 'Artikel kini menyokong jenis, tag dan skop cawangan. Cipta Tiket akan mencadangkan artikel berkaitan berdasarkan kategori.',
        'Asset Photo' => 'Foto Aset',
        'Upload equipment photo. JPG, PNG, GIF or WEBP. Max 5MB.' => 'Muat naik foto peralatan. JPG, PNG, GIF atau WEBP. Maksimum 5MB.',
        'No photo selected' => 'Tiada foto dipilih',
        'Register equipment with photo, branch, serial number and purchase information.' => 'Daftar peralatan dengan foto, cawangan, nombor siri dan maklumat pembelian.',
        'Manage POS, printers, scanners, PCs, network equipment and photos.' => 'Urus POS, pencetak, scanner, PC, peralatan rangkaian dan foto.',
        'Warranty, supplier, maintenance note...' => 'Waranti, pembekal, nota penyelenggaraan...',
        'Return to Asset List' => 'Kembali ke Senarai Aset',
        'Back to Asset List' => 'Kembali ke Senarai Aset',
        'Save Asset' => 'Simpan Aset',
        'Dynamic status report linked with Ticket Status Management.' => 'Laporan status dinamik dipautkan kepada Pengurusan Status Tiket.',
        'Status Summary' => 'Ringkasan Status',
        'Branch Summary' => 'Ringkasan Cawangan',
        'PIC Summary' => 'Ringkasan PIC',
        'Month' => 'Bulan',
        'View' => 'Lihat',
        'Total Logs' => 'Jumlah Log',
        'Today Activity' => 'Aktiviti Hari Ini',
        'Last Activity' => 'Aktiviti Terakhir',
        'Track login, logout, create, edit, delete and export activities.' => 'Jejak aktiviti log masuk, log keluar, cipta, edit, padam dan eksport.',
        'Activity List' => 'Senarai Aktiviti',
        'Keyword' => 'Kata Kunci',
        'Per Page' => 'Setiap Halaman',
        'All Users' => 'Semua Pengguna',
        'Print' => 'Cetak',
        'Tickets marked as closed in Ticket Status Management are separated here and will not appear in Ticket List or Overdue.' => 'Tiket yang ditanda tutup dalam Pengurusan Status Tiket dipisahkan di sini dan tidak muncul dalam Senarai Tiket atau Lewat.',
        'CANCELLED' => 'DIBATALKAN',
        'Cancelled' => 'Dibatalkan',
        'Select Branch' => 'Pilih Cawangan',
        'Select Asset Type' => 'Pilih Jenis Aset',
        'Choose Files' => 'Pilih Fail',
        'No file chosen' => 'Tiada fail dipilih',
        'No files chosen' => 'Tiada fail dipilih',
    ];
    $extra_zh = [
        'Add' => '添加',
        'Toggle' => '切换',
        'In Use' => '使用中',
        'Used' => '已使用',
        'Usage' => '使用量',
        'Preview' => '预览',
        'Color' => '颜色',
        'Sort' => '排序',
        'Sort Order' => '排序',
        'Badge Color' => '标签颜色',
        'Red / Danger' => '红色 / 危险',
        'Yellow / Warning' => '黄色 / 警告',
        'Blue / Info' => '蓝色 / 信息',
        'Green / Success' => '绿色 / 成功',
        'Grey / Secondary' => '灰色 / 次要',
        'Dark' => '深色',
        'Add Ticket Status' => '添加工单状态',
        'Status List' => '状态列表',
        'Status Name' => '状态名称',
        'Fully dynamic: rename, color, sort, active and closed/archive behaviour.' => '完整动态：名称、颜色、排序、启用和关闭/归档行为。',
        'Example: Waiting Vendor, Waiting Branch, On Hold, Cancelled.' => '例如：等待供应商、等待分行、暂停、已取消。',
        'Bootstrap badge color class.' => '标签颜色类别。',
        'Rename allowed; existing tickets will sync.' => '改名会同步现有工单。',
        'Rename will sync existing tickets. Delete is only allowed when Usage = 0.' => '改名会同步现有工单；只有使用量为 0 时才可删除。',
        'Add 资产类型' => '添加资产类型',
        '资产类型 List' => '资产类型列表',
        'Asset 类型' => '资产类型',
        'Add Asset Type' => '添加资产类型',
        'Asset Type List' => '资产类型列表',
        'Asset Type Name' => '资产类型名称',
        'Main asset type list used by Add Asset, Edit Asset and Asset filtering.' => '管理资产类型，用于新增资产、编辑资产和资产筛选。',
        'Linked pages: Add Asset and Edit Asset will automatically use active Asset Types from this list. Asset Types already used by assets cannot be deleted; use disable instead. Deleted unused default types will no longer come back automatically.' => '关联页面：新增资产和编辑资产会自动使用启用的资产类型。已被资产使用的类型不能删除，请改为停用。删除未使用的默认类型后不会自动恢复。',
        'Add 分行' => '添加分行',
        '分行 List' => '分行列表',
        'Branch Code' => '分行代码',
        'Branch Name' => '分行名称',
        'Add, edit, disable or delete branch records.' => '添加、编辑、停用或删除分行记录。',
        'Disabled items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.' => '停用项目不会出现在下拉列表；已使用记录会被停用而不是删除，以保护旧工单。',
        'Add PIC' => '添加 PIC',
        'PIC List' => 'PIC列表',
        'PIC Name' => 'PIC名称',
        'Add, edit, disable or delete 负责人 options.' => '添加、编辑、停用或删除负责人选项。',
        'Add, edit, disable or delete 负责人 options used in tickets.' => '添加、编辑、停用或删除工单负责人选项。',
        'Used records are disabled instead of deleted to protect old tickets.' => '已使用记录会被停用而不是删除，以保护旧工单。',
        'Disabled items will not appear in dropdowns.' => '停用项目不会出现在下拉列表。',
        'Add 指派给' => '添加指派对象',
        '指派给 List' => '指派对象列表',
        'Assign To List' => '指派对象列表',
        'Add Assign To' => '添加指派对象',
        'Assign To Name' => '指派对象名称',
        'Optional Email' => '可选邮箱',
        'optional@email.com' => '可选邮箱',
        'email optional' => '可选邮箱',
        'Add, edit, disable or delete 指派给 options used in tickets.' => '添加、编辑、停用或删除工单指派对象。',
        'Example: IT Team, KIAT, Vendor, POS Support.' => '例如：IT Team、KIAT、Vendor、POS Support。',
        'Disabled items will not appear in Create Ticket / 指派工单 dropdown.' => '停用项目不会出现在创建工单或指派工单下拉列表。',
        'Add SLA' => '添加 SLA',
        'SLA List' => 'SLA列表',
        'SLA Hours' => 'SLA小时',
        'Priority Name' => '优先级名称',
        'Add, edit, disable or delete SLA priority rules.' => '添加、编辑、停用或删除SLA优先级规则。',
        'Add 分类' => '添加分类',
        '分类 List' => '分类列表',
        'Default 优先级' => '默认优先级',
        'Default Priority' => '默认优先级',
        'Add, edit, disable or delete Ticket 分类 options.' => '添加、编辑、停用或删除工单分类选项。',
        'Add user' => '添加用户',
        'Add User' => '添加用户',
        'Create Admin / Head / Staff user with checkbox ticket visibility.' => '创建 Admin / Head / Staff 用户，并可勾选工单可见权限。',
        'Basic 用户 Info' => '基本用户资料',
        'Basic User Info' => '基本用户资料',
        'Login information.' => '登录资料。',
        'Full Name' => '全名',
        'Role & Primary Branch' => '角色与主要分行',
        'Role & Primary 分行' => '角色与主要分行',
        'Only Admin, Head and Staff are supported.' => '仅支持 Admin、Head 和 Staff。',
        'Primary Branch' => '主要分行',
        'Profile PIC / Department' => 'Profile PIC / 部门',
        'Pr共ile PIC / 部门' => 'Profile PIC / 部门',
        'Staff: only own Primary Branch + checked User Own PIC tickets.' => 'Staff：仅可查看主要分行 + 已勾选的自己PIC工单。',
        'Ticket Visibility Permission' => '工单可见权限',
        'Checkbox access. Staff = Own Branch + Own PIC.' => '勾选权限。Staff = 自己分行 + 自己PIC。',
        'Final rule: Admin sees all. Head sees checked Branch + checked PIC. Staff sees own Primary Branch + checked User Own PIC only.' => '最终规则：Admin 可看全部；Head 可看已勾选分行 + 已勾选PIC；Staff 只看自己的主要分行 + 已勾选的自己PIC。',
        'Head Branch Access' => 'Head 分行权限',
        'Select All Branch' => '选择全部分行',
        'Clear Branch' => '清除分行',
        'Create User' => '创建用户',
        'User Management' => '用户管理',
        'Manage login, branch access and PIC visibility. Module / action permissions are inherited from role Permission Matrix.' => '管理登录、分行权限和PIC可见范围。模块/操作权限继承自角色权限矩阵。',
        '100% view optimized. All permission info is shown without +more.' => '权限信息已完整显示。',
        'Compact' => '紧凑',
        'Expand All' => '展开全部',
        'Branch Access' => '分行权限',
        'Ticket Branch Access' => '工单分行权限',
        'Ticket PIC Access' => '工单PIC权限',
        'Role Module Permission' => '角色模块权限',
        'Role Action Permission' => '角色操作权限',
        'All Modules' => '全部模块',
        'All Actions' => '全部操作',
        'Copy' => '复制',
        'Head / Staff' => 'Head / Staff',
        'Control Panel' => '控制面板',
        '3-role system only: Admin, Head, Staff. Tick the functions here once and every user under that role follows it.' => '仅三种角色系统：Admin、Head、Staff。这里勾选一次，该角色下所有用户都会跟随。',
        'Role Permission Checkbox Matrix' => '角色权限勾选矩阵',
        'Admin always has all permissions and is not shown. Head / Staff are controlled here globally.' => 'Admin 默认拥有所有权限，不会显示。Head / Staff 在这里统一控制。',
        'Tick all Head' => '勾选全部 Head',
        'Clear Head' => '清除 Head',
        'Tick all Staff' => '勾选全部 Staff',
        'Clear Staff' => '清除 Staff',
        'Menu / Page Access' => '菜单 / 页面权限',
        'Function' => '功能',
        'Save Role Permissions' => '保存角色权限',
        'Add Article' => '添加文章',
        'Add article' => '添加文章',
        'Create organized knowledge article with type, tags and branch scope' => '创建带类型、标签和分行范围的知识文章',
        'Article Content' => '文章内容',
        'ARTICLE 内容' => '文章内容',
        'QUICK TEMPLATE' => '快速模板',
        'SOP Template' => 'SOP模板',
        'FAQ Template' => '常见问题模板',
        'Troubleshooting Template' => '故障排查模板',
        'Question:' => '问题：',
        'Answer:' => '答案：',
        'When to use this guide:' => '适用情况：',
        'Related Ticket Category:' => '关联工单分类：',
        'Remark:' => '备注：',
        'Purpose:' => '目的：',
        'Scope:' => '范围：',
        'Step-by-step Procedure:' => '步骤：',
        'Important Notes:' => '重要备注：',
        'Problem:' => '问题：',
        'Possible Cause:' => '可能原因：',
        'Checklist:' => '检查清单：',
        'Solution:' => '解决方案：',
        'Escalate to:' => '升级给：',
        'Selected Branch Only' => '仅选择的分行',
        'All Branches' => '全部分行',
        'Branch Scope' => '分行范围',
        'Tags' => '标签',
        'Separate tags by comma. Example: POS, sync, price update' => '用逗号分隔标签。例如：POS、同步、价格更新',
        'No views yet.' => '暂无浏览。',
        'New Structure' => '新结构',
        'Articles now support Type, Tags and Branch Scope. Create Ticket will suggest related articles by selected Category.' => '文章现在支持类型、标签和分行范围。创建工单时会根据所选分类推荐相关文章。',
        'No Article found' => '没有找到文章',
        'Showing 0 of 0 articles' => '显示 0 共 0 篇文章',
        'Asset Photo' => '资产照片',
        'Upload equipment photo. JPG, PNG, GIF or WEBP. Max 5MB.' => '上传设备照片。支持 JPG、PNG、GIF 或 WEBP，最大 5MB。',
        'No photo selected' => '未选择照片',
        'Register equipment with photo, branch, serial number and purchase information.' => '登记设备照片、分行、序列号和购买资料。',
        'Manage POS, printers, scanners, PCs, network equipment and photos.' => '管理 POS、打印机、扫描器、电脑、网络设备和照片。',
        'Warranty, supplier, maintenance note...' => '保修、供应商、维修备注...',
        'Return to Asset List' => '返回资产列表',
        'Back to Asset List' => '返回资产列表',
        'Save Asset' => '保存资产',
        'Dynamic status report linked with Ticket Status Management.' => '动态状态报告已连接工单状态管理。',
        'Status Summary' => '状态汇总',
        'Branch Summary' => '分行汇总',
        'PIC Summary' => 'PIC汇总',
        'Month' => '月份',
        'View' => '查看',
        'Total Logs' => '总日志',
        'Today Activity' => '今日活动',
        'Last Activity' => '最后活动',
        'Track login, logout, create, edit, delete and export activities.' => '追踪登录、登出、新增、编辑、删除和导出活动。',
        'Activity List' => '活动列表',
        'Keyword' => '关键字',
        'Per Page' => '每页',
        'All Users' => '全部用户',
        'Print' => '打印',
        'Tickets marked as closed in Ticket Status Management are separated here and will not appear in Ticket List or Overdue.' => '在工单状态管理标记为已关闭的工单会分开显示在这里，不会出现在工单列表或逾期。',
        'CANCELLED' => '已取消',
        'Cancelled' => '已取消',
        'Enabled items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.' => '停用项目不会出现在下拉列表；已使用记录会被停用而不是删除，以保护旧工单。',
        'Select Branch' => '选择分行',
        'Select Asset Type' => '选择资产类型',
        'Choose Files' => '选择文件',
        'No file chosen' => '未选择文件',
        'No files chosen' => '未选择文件',
    ];
    $ms = array_merge($ms, $extra_ms);
    $zh = array_merge($zh, $extra_zh);


    // Final exact UI phrase patches for management pages and free language switching.
    $final2_ms = [
        'Return to Management'=>'Kembali ke Pengurusan','Return to 管理'=>'Kembali ke Pengurusan','Back to Management'=>'Kembali ke Pengurusan','Back to 管理'=>'Kembali ke Pengurusan',
        'Main asset type list used by Add Asset, Edit Asset and Asset filtering.'=>'Urus jenis aset untuk Tambah Aset, Edit Aset dan penapisan aset.',
        'Asset type master list used by Add Asset and Edit Asset.'=>'Senarai induk jenis aset digunakan oleh Tambah Aset dan Edit Aset.',
        'Linked pages: Add Asset and Edit Asset will automatically use active Asset Types from this list. Asset Types already used by assets cannot be deleted; use Disable instead. Deleted unused default types will no longer come back automatically.'=>'Halaman berkaitan: Tambah Aset dan Edit Aset akan menggunakan jenis aset aktif daripada senarai ini. Jenis aset yang telah digunakan tidak boleh dipadam; gunakan Nyahaktif.',
        'Linked pages: Add Asset and Edit Asset will automatically use active Asset Types from this list. Asset Types already used by assets cannot be deleted; use disable instead. Deleted unused default types will no longer come back automatically.'=>'Halaman berkaitan: Tambah Aset dan Edit Aset akan menggunakan jenis aset aktif daripada senarai ini. Jenis aset yang telah digunakan tidak boleh dipadam; gunakan Nyahaktif.',
        'Add, edit, disable or delete Ticket Category options.'=>'Tambah, edit, nyahaktif atau padam pilihan kategori tiket.',
        'Add, edit, disable or delete Person In Charge options.'=>'Tambah, edit, nyahaktif atau padam pilihan PIC.',
        'Add, edit, disable or delete Assign To options used in tickets.'=>'Tambah, edit, nyahaktif atau padam pilihan penerima tugasan yang digunakan dalam tiket.',
        'Disabled items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.'=>'Item dinyahaktif tidak akan muncul dalam senarai pilihan. Rekod yang telah digunakan akan dinyahaktifkan, bukan dipadam, untuk melindungi tiket lama.',
        'Disabled items will not appear in Create Ticket / 指派工单 dropdown.'=>'Item dinyahaktif tidak akan muncul dalam pilihan Cipta Tiket / Tugaskan Tiket.',
        'Only Admin / Head / Staff user with checkbox ticket visibility.'=>'Cipta pengguna Admin / Head / Staff dengan pilihan keterlihatan tiket.',
        'Only Admin, Head and Staff are supported.'=>'Hanya Admin, Head dan Staff disokong.',
        'Staff: only own Primary Branch + checked User Own PIC tickets.'=>'Staff: hanya cawangan utama sendiri dan tiket PIC sendiri yang ditanda.',
        'Own / Related'=>'Sendiri / Berkaitan','Own / 关联工单'=>'Tiket Sendiri / Berkaitan',
        'Branch Access'=>'Akses Cawangan','Ticket Branch Access'=>'Akses Cawangan Tiket','Ticket PIC Access'=>'Akses PIC Tiket',
        'Role Module Permission'=>'Kebenaran Modul Peranan','Role Action Permission'=>'Kebenaran Tindakan Peranan',
        '100% view optimized. All permission info is shown without +more.'=>'Paparan dioptimumkan. Semua maklumat kebenaran dipaparkan tanpa +more.',
        'Question:'=>'Soalan:','Answer:'=>'Jawapan:','When to use this guide:'=>'Bila menggunakan panduan ini:','Related Ticket Category:'=>'Kategori tiket berkaitan:','Remark:'=>'Catatan:',
        'QUICK TEMPLATE'=>'TEMPLAT PANTAS','SOP Template'=>'Templat SOP','FAQ Template'=>'Templat FAQ','Troubleshooting Template'=>'Templat Penyelesaian Masalah',
        'Selected Branch Only'=>'Cawangan Dipilih Sahaja','Create organized knowledge article with type, tags and branch scope'=>'Cipta artikel pengetahuan mengikut jenis, tag dan skop cawangan',
        'Add, edit, disable or delete SLA priority rules.'=>'Tambah, edit, nyahaktif atau padam peraturan keutamaan SLA.',
        'Add, edit, disable or delete branch records.'=>'Tambah, edit, nyahaktif atau padam rekod cawangan.',
        'Add, edit, disable or delete Asset Type options.'=>'Tambah, edit, nyahaktif atau padam pilihan jenis aset.',
        'Add, edit, disable or delete PIC options.'=>'Tambah, edit, nyahaktif atau padam pilihan PIC.',
        'Rename allowed; existing tickets will sync.'=>'Penamaan semula dibenarkan; tiket sedia ada akan disegerakkan.',
        'Bootstrap badge color class.'=>'Kelas warna label Bootstrap.',
        'Canceled'=>'Dibatalkan','CANCELLED'=>'DIBATALKAN','Cancelled'=>'Dibatalkan','Low'=>'Rendah','Medium'=>'Sederhana','High'=>'Tinggi','Urgent'=>'Segera',
        'Low (48 hours)'=>'Rendah (48 jam)','Medium (24 hours)'=>'Sederhana (24 jam)','High (8 hours)'=>'Tinggi (8 jam)','Urgent (4 hours)'=>'Segera (4 jam)',
        'No.'=>'No.','No'=>'No.','Yes'=>'Ya','hours'=>'jam','Optional'=>'Pilihan','Email Optional'=>'E-mel Pilihan',
    ];
    $final2_zh = [
        'Return to Management'=>'返回管理','Return to 管理'=>'返回管理','Back to Management'=>'返回管理','Back to 管理'=>'返回管理',
        'Main asset type list used by Add Asset, Edit Asset and Asset filtering.'=>'管理资产类型，用于添加资产、编辑资产和资产筛选。',
        'Asset type master list used by Add Asset and Edit Asset.'=>'资产类型主列表用于添加资产和编辑资产。',
        'Linked pages: Add Asset and Edit Asset will automatically use active Asset Types from this list. Asset Types already used by assets cannot be deleted; use Disable instead. Deleted unused default types will no longer come back automatically.'=>'关联页面：添加资产和编辑资产会自动使用此列表中的启用资产类型。已被资产使用的类型不能删除，请使用停用。',
        'Linked pages: Add Asset and Edit Asset will automatically use active Asset Types from this list. Asset Types already used by assets cannot be deleted; use disable instead. Deleted unused default types will no longer come back automatically.'=>'关联页面：添加资产和编辑资产会自动使用此列表中的启用资产类型。已被资产使用的类型不能删除，请使用停用。',
        'Add, edit, disable or delete Ticket Category options.'=>'添加、编辑、停用或删除工单分类选项。',
        'Add, edit, disable or delete Person In Charge options.'=>'添加、编辑、停用或删除负责人选项。',
        'Add, edit, disable or delete Assign To options used in tickets.'=>'添加、编辑、停用或删除工单指派对象。',
        'Disabled items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.'=>'停用项目不会出现在下拉列表；已使用记录会被停用而不是删除，以保护旧工单。',
        'Disabled items will not appear in Create Ticket / 指派工单 dropdown.'=>'停用项目不会出现在创建工单或指派工单下拉列表。',
        'Only Admin / Head / Staff user with checkbox ticket visibility.'=>'创建 Admin / Head / Staff 用户，并可勾选工单可见权限。',
        'Only Admin, Head and Staff are supported.'=>'只支持 Admin、Head 和 Staff。',
        'Staff: only own Primary Branch + checked User Own PIC tickets.'=>'Staff 只能查看自己的主要分行和已勾选的所属 PIC 工单。',
        'Own / Related'=>'所属 / 相关','Own / 关联工单'=>'所属 / 相关工单',
        'Branch Access'=>'分行权限','Ticket Branch Access'=>'工单分行权限','Ticket PIC Access'=>'工单PIC权限',
        'Role Module Permission'=>'角色模块权限','Role Action Permission'=>'角色操作权限',
        '100% view optimized. All permission info is shown without +more.'=>'显示已优化，所有权限资料会完整显示。',
        'Question:'=>'问题：','Answer:'=>'答案：','When to use this guide:'=>'适用情况：','Related Ticket Category:'=>'关联工单分类：','Remark:'=>'备注：',
        'QUICK TEMPLATE'=>'快速模板','SOP Template'=>'SOP模板','FAQ Template'=>'常见问题模板','Troubleshooting Template'=>'故障排查模板',
        'Selected Branch Only'=>'仅选择的分行','Create organized knowledge article with type, tags and branch scope'=>'创建带类型、标签和分行范围的知识文章',
        'Add, edit, disable or delete SLA priority rules.'=>'添加、编辑、停用或删除SLA优先级规则。',
        'Add, edit, disable or delete branch records.'=>'添加、编辑、停用或删除分行记录。',
        'Add, edit, disable or delete Asset Type options.'=>'添加、编辑、停用或删除资产类型选项。',
        'Add, edit, disable or delete PIC options.'=>'添加、编辑、停用或删除PIC选项。',
        'Rename allowed; existing tickets will sync.'=>'允许改名；现有工单会同步更新。',
        'Bootstrap badge color class.'=>'Bootstrap 标签颜色类别。',
        'Canceled'=>'已取消','CANCELLED'=>'已取消','Cancelled'=>'已取消','Low'=>'低','Medium'=>'中','High'=>'高','Urgent'=>'紧急',
        'Low (48 hours)'=>'低（48小时）','Medium (24 hours)'=>'中（24小时）','High (8 hours)'=>'高（8小时）','Urgent (4 hours)'=>'紧急（4小时）',
        'No.'=>'序号','No'=>'序号','Yes'=>'是','hours'=>'小时','Optional'=>'可选','Email Optional'=>'可选邮箱',
    ];
    $ms = array_merge($ms, $final2_ms);
    $zh = array_merge($zh, $final2_zh);


    // Final admin management page translation fixes.
    $final3_ms = [
        'Administration / Assign To Management'=>'Pentadbiran / Pengurusan Tugasan',
        'Administration / PIC Management'=>'Pentadbiran / Pengurusan PIC',
        'Administration / Category Management'=>'Pentadbiran / Pengurusan Kategori',
        'Administration / SLA Management'=>'Pentadbiran / Pengurusan SLA',
        'Administration / Branch Management'=>'Pentadbiran / Pengurusan Cawangan',
        'Administration / Asset Type Management'=>'Pentadbiran / Pengurusan Jenis Aset',
        'Back to Administration'=>'Kembali ke Pentadbiran',
        'Back to 管理'=>'Kembali ke Pentadbiran',
        '返回 to 管理'=>'Kembali ke Pentadbiran',
        'Search...'=>'Cari...',
        'Email optional'=>'E-mel pilihan',
        'Email Optional'=>'E-mel Pilihan',
        'optional@email.com'=>'optional@email.com',
        'Inactive items will not appear in Create Ticket / 指派工单 dropdown.'=>'Item tidak aktif tidak akan muncul dalam senarai Cipta Tiket / Tugaskan Tiket.',
        'Inactive items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.'=>'Item tidak aktif tidak akan muncul dalam senarai pilihan. Rekod yang telah digunakan akan dinyahaktif, bukan dipadam, untuk melindungi tiket lama.',
        'Maintain Asset Type list used by Add Asset, Edit Asset and Asset filtering.'=>'Urus senarai jenis aset untuk Tambah Aset, Edit Aset dan penapisan aset.',
        'Linked pages: Add Asset and Edit Asset will automatically use active Asset Types from this list. Asset Types already used by assets cannot be deleted; use Disable instead. Deleted unused default types will no longer come back automatically.'=>'Halaman berkaitan: Tambah Aset dan Edit Aset akan menggunakan jenis aset aktif daripada senarai ini. Jenis aset yang telah digunakan tidak boleh dipadam; gunakan Nyahaktif. Jenis lalai yang tidak digunakan dan telah dipadam tidak akan kembali secara automatik.',
        'Add, edit, disable or delete Assign To options used in tickets.'=>'Tambah, edit, nyahaktif atau padam pilihan tugasan yang digunakan dalam tiket.',
        'Add, edit, disable or delete PIC options.'=>'Tambah, edit, nyahaktif atau padam pilihan PIC.',
        'Add, edit, disable or delete Ticket Category options.'=>'Tambah, edit, nyahaktif atau padam pilihan kategori tiket.',
        'Add, edit, disable or delete SLA priority rules.'=>'Tambah, edit, nyahaktif atau padam peraturan keutamaan SLA.',
        'Add, edit, disable or delete branch records.'=>'Tambah, edit, nyahaktif atau padam rekod cawangan.',
        'Add, edit, disable or delete Asset Type options.'=>'Tambah, edit, nyahaktif atau padam pilihan jenis aset.',
        'Example: IT Team, KIAT, Vendor, POS Support.'=>'Contoh: IT Team, KIAT, Vendor, POS Support.',
        'Assign To Name'=>'Nama Tugasan',
        'Assign To List'=>'Senarai Tugasan',
        'PIC List'=>'Senarai PIC',
        'PIC Name'=>'Nama PIC',
        'Category Name'=>'Nama Kategori',
        'Default Priority'=>'Keutamaan Lalai',
        'Branch Code'=>'Kod Cawangan',
        'Branch Name'=>'Nama Cawangan',
        'Asset Type Name'=>'Nama Jenis Aset',
        'Asset Type List'=>'Senarai Jenis Aset',
        'SLA List'=>'Senarai SLA',
        'SLA Hours'=>'Jam SLA',
        'Priority Name'=>'Nama Keutamaan',
        'Status Name'=>'Nama Status',
        'Badge Color'=>'Warna Lencana',
        'Closed?'=>'Ditutup?',
        'Active?'=>'Aktif?',
        'Rename allowed; existing tickets will sync.'=>'Penamaan semula dibenarkan; tiket sedia ada akan disegerakkan.',
        'Bootstrap badge color class.'=>'Kelas warna lencana Bootstrap.',
        'Add'=>'Tambah','Save'=>'Simpan','Delete'=>'Padam','Toggle'=>'Tukar','In Use'=>'Digunakan','Disable'=>'Nyahaktif',
        'No.'=>'No.','No'=>'No.','Name'=>'Nama','Email'=>'E-mel','Status'=>'Status','Action'=>'Tindakan','Used'=>'Digunakan','Sort'=>'Susunan','Sort Order'=>'Susunan',
        'Low (48 hours)'=>'Rendah (48 jam)','Medium (24 hours)'=>'Sederhana (24 jam)','High (8 hours)'=>'Tinggi (8 jam)','Urgent (4 hours)'=>'Segera (4 jam)',
    ];
    $final3_zh = [
        'Administration / Assign To Management'=>'管理 / 负责人管理',
        'Administration / PIC Management'=>'管理 / PIC管理',
        'Administration / Category Management'=>'管理 / 分类管理',
        'Administration / SLA Management'=>'管理 / SLA管理',
        'Administration / Branch Management'=>'管理 / 分行管理',
        'Administration / Asset Type Management'=>'管理 / 资产类型管理',
        'Back to Administration'=>'返回管理',
        'Back to 管理'=>'返回管理',
        '返回 to 管理'=>'返回管理',
        'Search...'=>'搜索...',
        'Email optional'=>'可选邮箱',
        'Email Optional'=>'可选邮箱',
        'optional@email.com'=>'可选邮箱',
        'Inactive items will not appear in Create Ticket / 指派工单 dropdown.'=>'停用项目不会出现在创建工单 / 指派工单下拉列表。',
        'Inactive items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.'=>'停用项目不会出现在下拉列表。已使用记录会改为停用而不是删除，以保护旧工单。',
        'Maintain Asset Type list used by Add Asset, Edit Asset and Asset filtering.'=>'管理新增资产、编辑资产和资产筛选所使用的资产类型列表。',
        'Linked pages: Add Asset and Edit Asset will automatically use active Asset Types from this list. Asset Types already used by assets cannot be deleted; use Disable instead. Deleted unused default types will no longer come back automatically.'=>'关联页面：新增资产和编辑资产会自动使用此列表中的启用资产类型。已被资产使用的类型不能删除，请使用停用。已删除且未使用的默认类型不会自动恢复。',
        'Add, edit, disable or delete Assign To options used in tickets.'=>'新增、编辑、停用或删除工单使用的负责人选项。',
        'Add, edit, disable or delete PIC options.'=>'新增、编辑、停用或删除PIC选项。',
        'Add, edit, disable or delete Ticket Category options.'=>'新增、编辑、停用或删除工单分类选项。',
        'Add, edit, disable or delete SLA priority rules.'=>'新增、编辑、停用或删除SLA优先级规则。',
        'Add, edit, disable or delete branch records.'=>'新增、编辑、停用或删除分行记录。',
        'Add, edit, disable or delete Asset Type options.'=>'新增、编辑、停用或删除资产类型选项。',
        'Example: IT Team, KIAT, Vendor, POS Support.'=>'例如：IT Team、KIAT、Vendor、POS Support。',
        'Assign To Name'=>'负责人名称',
        'Assign To List'=>'负责人列表',
        'PIC List'=>'PIC列表',
        'PIC Name'=>'PIC名称',
        'Category Name'=>'分类名称',
        'Default Priority'=>'默认优先级',
        'Branch Code'=>'分行代码',
        'Branch Name'=>'分行名称',
        'Asset Type Name'=>'资产类型名称',
        'Asset Type List'=>'资产类型列表',
        'SLA List'=>'SLA列表',
        'SLA Hours'=>'SLA小时',
        'Priority Name'=>'优先级名称',
        'Status Name'=>'状态名称',
        'Badge Color'=>'标签颜色',
        'Closed?'=>'已关闭？',
        'Active?'=>'启用？',
        'Rename allowed; existing tickets will sync.'=>'允许改名；现有工单会同步更新。',
        'Bootstrap badge color class.'=>'Bootstrap标签颜色类别。',
        'Add'=>'添加','Save'=>'保存','Delete'=>'删除','Toggle'=>'切换','In Use'=>'使用中','Disable'=>'停用',
        'No.'=>'序号','No'=>'序号','Name'=>'名称','Email'=>'邮箱','Status'=>'状态','Action'=>'操作','Used'=>'已使用','Sort'=>'排序','Sort Order'=>'排序',
        'Low (48 hours)'=>'低（48小时）','Medium (24 hours)'=>'中（24小时）','High (8 hours)'=>'高（8小时）','Urgent (4 hours)'=>'紧急（4小时）',
    ];
    $ms = array_merge($ms, $final3_ms);
    $zh = array_merge($zh, $final3_zh);



    // Final hardcoded UI cleanup translations for Administration / master data pages.
    $last_ms = [
        'Administration / Assign To Management'=>'Pentadbiran / Pengurusan Tugasan',
        'Administration / PIC Management'=>'Pentadbiran / Pengurusan PIC',
        'Administration / Category Management'=>'Pentadbiran / Pengurusan Kategori',
        'Administration / SLA Management'=>'Pentadbiran / Pengurusan SLA',
        'Administration / Branch Management'=>'Pentadbiran / Pengurusan Cawangan',
        'Administration / Asset Type Management'=>'Pentadbiran / Pengurusan Jenis Aset',
        'Administration / Ticket Status Management'=>'Pentadbiran / Pengurusan Status Tiket',
        'Back to Administration'=>'Kembali ke Pentadbiran',
        'Search...'=>'Cari...',
        'Email optional'=>'E-mel pilihan',
        'Email Optional'=>'E-mel Pilihan',
        'Inactive items will not appear in Create Ticket / 指派工单 dropdown.'=>'Item tidak aktif tidak akan muncul dalam senarai Cipta Tiket / Tugaskan Tiket.',
        'Inactive items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.'=>'Item tidak aktif tidak akan muncul dalam senarai pilihan. Rekod yang telah digunakan akan dinyahaktifkan, bukan dipadam, untuk melindungi tiket lama.',
        'Items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.'=>'Item tidak akan muncul dalam senarai pilihan. Rekod yang telah digunakan akan dinyahaktifkan, bukan dipadam, untuk melindungi tiket lama.',
        'Maintain Asset Type list used by Add Asset, Edit Asset and Asset filtering.'=>'Urus senarai jenis aset yang digunakan oleh Tambah Aset, Edit Aset dan tapisan aset.',
        'Linked pages: Add Asset and Edit Asset will automatically use active Asset Types from this list. Asset Types already used by assets cannot be deleted; use Disable instead. Deleted unused default types will no longer come back automatically.'=>'Halaman berkaitan: Tambah Aset dan Edit Aset akan menggunakan jenis aset aktif daripada senarai ini secara automatik. Jenis aset yang telah digunakan tidak boleh dipadam; gunakan Nyahaktif. Jenis lalai yang tidak digunakan dan telah dipadam tidak akan muncul semula secara automatik.',
        'Linked pages:'=>'Halaman berkaitan:',
        'Bootstrap badge color class.'=>'Kelas warna label Bootstrap.',
        'Rename allowed; existing tickets will sync.'=>'Penamaan semula dibenarkan; tiket sedia ada akan disegerakkan.',
        'Controls User / Head / Staff, then assign branch and PIC access.'=>'Kawal pengguna Admin / Head / Staff, kemudian tetapkan akses cawangan dan PIC.',
        'Only choose Admin / Head / Staff. Tick the functions here once and every user under that role follows it.'=>'Hanya pilih Admin / Head / Staff. Tandakan fungsi di sini sekali dan semua pengguna bawah peranan itu akan mengikut tetapan ini.',
        'Controls ticket assign dropdown. Staff visibility follows assigned_to.'=>'Kawal senarai tugasan tiket. Paparan Staff mengikut assigned_to.',
        'Controls Assign To choices for ticket and Head visibility.'=>'Kawal pilihan Tugaskan Kepada untuk tiket dan paparan Head.',
        'Assign master data for tickets and Head/Staff access.'=>'Tetapkan data induk tugasan untuk tiket dan akses Head/Staff.',
        'Asset type master list used by Add Asset and Edit Asset.'=>'Senarai induk jenis aset yang digunakan oleh Tambah Aset dan Edit Aset.',
        'Ticket category setup linked to Create Ticket and reports.'=>'Tetapan kategori tiket dipautkan kepada Cipta Tiket dan laporan.',
        'SLA rules linked to due date and monitoring.'=>'Peraturan SLA dipautkan kepada tarikh akhir dan pemantauan.',
        'Controls ticket status dropdown, badge color and closed/archive logic.'=>'Kawal senarai status tiket, warna label dan logik tutup/arkib.',
        'Review administration changes.'=>'Semak perubahan pentadbiran.',
        'Central administration dashboard and role permission checkbox matrix.'=>'Papan pemuka pentadbiran pusat dan matriks kebenaran peranan.',
        'View main dashboard and live summary.'=>'Lihat papan pemuka utama dan ringkasan langsung.',
        'Create new support tickets.'=>'Cipta tiket sokongan baharu.',
        'View tickets allowed by ticket visibility rule.'=>'Lihat tiket yang dibenarkan oleh peraturan paparan tiket.',
        'View asset list and asset history.'=>'Lihat senarai aset dan sejarah aset.',
        'Create new asset records.'=>'Cipta rekod aset baharu.',
        'View/add/edit knowledge base articles based on action permission.'=>'Lihat/tambah/edit artikel pangkalan pengetahuan berdasarkan kebenaran tindakan.',
        'View company announcements and read status.'=>'Lihat pengumuman syarikat dan status bacaan.',
        'Create company announcements.'=>'Cipta pengumuman syarikat.',
        'View monthly KPI reports.'=>'Lihat laporan KPI bulanan.',
        'View system audit logs.'=>'Lihat log audit sistem.',
        'Create, edit, disable and manage users.'=>'Cipta, edit, nyahaktif dan urus pengguna.',
        'Maintain Assign To list / ticket assignees.'=>'Urus senarai Tugaskan Kepada / penerima tiket.',
        'Maintain Person In Charge list.'=>'Urus senarai Person In Charge.',
        'Maintain ticket category master data.'=>'Urus data induk kategori tiket.',
        'Maintain SLA rules and due date settings.'=>'Urus peraturan SLA dan tetapan tarikh akhir.',
        'Maintain branch master data.'=>'Urus data induk cawangan.',
        'Maintain asset type master data for Add/Edit Asset.'=>'Urus data induk jenis aset untuk Tambah/Edit Aset.',
        'Maintain ticket status list, color and closed/archive behaviour.'=>'Urus senarai status tiket, warna dan tingkah laku tutup/arkib.',
        'User appears in Assign To dropdown.'=>'Pengguna muncul dalam senarai Tugaskan Kepada.',
        'Can assign ticket to another user.'=>'Boleh menugaskan tiket kepada pengguna lain.',
        'Can update ticket status.'=>'Boleh mengemas kini status tiket.',
        'Can reply to tickets and upload reply attachments.'=>'Boleh membalas tiket dan memuat naik lampiran balasan.',
        'Can edit ticket information.'=>'Boleh mengedit maklumat tiket.',
        'Can delete tickets.'=>'Boleh memadam tiket.',
        'Can export ticket CSV/report.'=>'Boleh mengeksport CSV/laporan tiket.',
        'Can add/edit/delete asset records.'=>'Boleh menambah/edit/memadam rekod aset.',
        'Can select Asset / Equipment when creating or editing tickets.'=>'Boleh memilih Aset / Peralatan semasa mencipta atau mengedit tiket.',
        'Can add/delete announcements and view read report.'=>'Boleh menambah/memadam pengumuman dan melihat laporan bacaan.',
        'Can add/edit/delete knowledge base articles and attachments.'=>'Boleh menambah/edit/memadam artikel pangkalan pengetahuan dan lampiran.',
        'Can create, edit, disable and delete users.'=>'Boleh mencipta, mengedit, menyahaktif dan memadam pengguna.',
        'Can export audit log CSV.'=>'Boleh mengeksport CSV log audit.',
        'Can use print buttons on reports/detail pages.'=>'Boleh menggunakan butang cetak pada halaman laporan/perincian.',
        'Operation Permission'=>'Kebenaran Operasi',
        'Action Permission'=>'Kebenaran Tindakan',
        'Show In Assign To'=>'Papar dalam Tugaskan Kepada',
        'Change Ticket Status'=>'Tukar Status Tiket',
        'Manage Announcement'=>'Urus Pengumuman',
        'Manage Knowledge Base'=>'Urus Pangkalan Pengetahuan',
        'Manage Users'=>'Urus Pengguna',
        'Export Audit Log'=>'Eksport Log Audit',
        'Print Report'=>'Cetak Laporan',
        'Select Asset In Ticket'=>'Pilih Aset Dalam Tiket',
        'Manage Asset'=>'Urus Aset',
        'User appears in 指派给 dropdown.'=>'Pengguna muncul dalam senarai Tugaskan Kepada.',
        'Can select 资产 / 设备 when creating or editing tickets.'=>'Boleh memilih Aset / Peralatan semasa mencipta atau mengedit tiket.',
    ];
    $last_zh = [
        'Administration / Assign To Management'=>'管理 / 负责人管理',
        'Administration / PIC Management'=>'管理 / PIC管理',
        'Administration / Category Management'=>'管理 / 分类管理',
        'Administration / SLA Management'=>'管理 / SLA管理',
        'Administration / Branch Management'=>'管理 / 分行管理',
        'Administration / Asset Type Management'=>'管理 / 资产类型管理',
        'Administration / Ticket Status Management'=>'管理 / 工单状态管理',
        'Back to Administration'=>'返回管理',
        'Search...'=>'搜索...',
        'Email optional'=>'可选邮箱',
        'Email Optional'=>'可选邮箱',
        'Inactive items will not appear in Create Ticket / 指派工单 dropdown.'=>'停用项目不会出现在创建工单 / 指派工单下拉选单。',
        'Inactive items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.'=>'停用项目不会出现在下拉选单。已使用记录会改为停用而不是删除，以保护旧工单。',
        'Items will not appear in dropdowns. Used records are disabled instead of deleted to protect old tickets.'=>'项目不会出现在下拉选单。已使用记录会改为停用而不是删除，以保护旧工单。',
        'Maintain Asset Type list used by Add Asset, Edit Asset and Asset filtering.'=>'管理添加资产、编辑资产和资产筛选所使用的资产类型列表。',
        'Linked pages: Add Asset and Edit Asset will automatically use active Asset Types from this list. Asset Types already used by assets cannot be deleted; use Disable instead. Deleted unused default types will no longer come back automatically.'=>'关联页面：添加资产和编辑资产会自动使用此列表中的启用资产类型。已被资产使用的资产类型不能删除，请改用停用。已删除且未使用的默认类型不会再自动恢复。',
        'Linked pages:'=>'关联页面：',
        'Bootstrap badge color class.'=>'Bootstrap 标签颜色类别。',
        'Rename allowed; existing tickets will sync.'=>'允许改名；现有工单会同步更新。',
        'Controls User / Head / Staff, then assign branch and PIC access.'=>'管理 Admin / Head / Staff 用户，并分配分行和 PIC 权限。',
        'Only choose Admin / Head / Staff. Tick the functions here once and every user under that role follows it.'=>'仅选择 Admin / Head / Staff。这里勾选一次，该角色下所有用户都会跟随。',
        'Controls ticket assign dropdown. Staff visibility follows assigned_to.'=>'控制工单指派下拉选单。Staff 可见性跟随 assigned_to。',
        'Controls Assign To choices for ticket and Head visibility.'=>'控制工单和 Head 可见性的指派对象选择。',
        'Assign master data for tickets and Head/Staff access.'=>'为工单和 Head/Staff 权限设置指派主资料。',
        'Asset type master list used by Add Asset and Edit Asset.'=>'添加资产和编辑资产使用的资产类型主列表。',
        'Ticket category setup linked to Create Ticket and reports.'=>'工单分类设置会连接创建工单和报表。',
        'SLA rules linked to due date and monitoring.'=>'SLA 规则连接到到期日和监控。',
        'Controls ticket status dropdown, badge color and closed/archive logic.'=>'控制工单状态下拉、标签颜色和关闭/归档逻辑。',
        'Review administration changes.'=>'查看管理变更。',
        'Central administration dashboard and role permission checkbox matrix.'=>'中央管理仪表板和角色权限勾选矩阵。',
        'View main dashboard and live summary.'=>'查看主仪表板和实时摘要。',
        'Create new support tickets.'=>'创建新的支援工单。',
        'View tickets allowed by ticket visibility rule.'=>'查看工单可见规则允许的工单。',
        'View asset list and asset history.'=>'查看资产列表和资产历史。',
        'Create new asset records.'=>'创建新的资产记录。',
        'View/add/edit knowledge base articles based on action permission.'=>'根据操作权限查看/添加/编辑知识库文章。',
        'View company announcements and read status.'=>'查看公司公告和阅读状态。',
        'Create company announcements.'=>'创建公司公告。',
        'View monthly KPI reports.'=>'查看每月KPI报告。',
        'View system audit logs.'=>'查看系统审计日志。',
        'Create, edit, disable and manage users.'=>'创建、编辑、停用和管理用户。',
        'Maintain Assign To list / ticket assignees.'=>'维护指派对象 / 工单负责人列表。',
        'Maintain Person In Charge list.'=>'维护负责人列表。',
        'Maintain ticket category master data.'=>'维护工单分类主资料。',
        'Maintain SLA rules and due date settings.'=>'维护SLA规则和到期日设置。',
        'Maintain branch master data.'=>'维护分行主资料。',
        'Maintain asset type master data for Add/Edit Asset.'=>'维护添加/编辑资产使用的资产类型主资料。',
        'Maintain ticket status list, color and closed/archive behaviour.'=>'维护工单状态列表、颜色和关闭/归档行为。',
        'User appears in Assign To dropdown.'=>'用户会出现在指派给下拉选单。',
        'Can assign ticket to another user.'=>'可以把工单指派给其他用户。',
        'Can update ticket status.'=>'可以更新工单状态。',
        'Can reply to tickets and upload reply attachments.'=>'可以回复工单并上传回复附件。',
        'Can edit ticket information.'=>'可以编辑工单资料。',
        'Can delete tickets.'=>'可以删除工单。',
        'Can export ticket CSV/report.'=>'可以导出工单CSV/报告。',
        'Can add/edit/delete asset records.'=>'可以添加/编辑/删除资产记录。',
        'Can select Asset / Equipment when creating or editing tickets.'=>'创建或编辑工单时可以选择资产 / 设备。',
        'Can add/delete announcements and view read report.'=>'可以添加/删除公告并查看阅读报告。',
        'Can add/edit/delete knowledge base articles and attachments.'=>'可以添加/编辑/删除知识库文章和附件。',
        'Can create, edit, disable and delete users.'=>'可以创建、编辑、停用和删除用户。',
        'Can export audit log CSV.'=>'可以导出审计日志CSV。',
        'Can use print buttons on reports/detail pages.'=>'可以在报告/详情页面使用打印按钮。',
        'Operation Permission'=>'操作权限',
        'Action Permission'=>'操作权限',
        'Show In Assign To'=>'显示在指派给',
        'Change Ticket Status'=>'更改工单状态',
        'Manage Announcement'=>'管理公告',
        'Manage Knowledge Base'=>'管理知识库',
        'Manage Users'=>'管理用户',
        'Export Audit Log'=>'导出审计日志',
        'Print Report'=>'打印报告',
        'Select Asset In Ticket'=>'工单中选择资产',
        'Manage Asset'=>'管理资产',
        'User appears in 指派给 dropdown.'=>'用户会出现在指派给下拉选单。',
        'Can select 资产 / 设备 when creating or editing tickets.'=>'创建或编辑工单时可以选择资产 / 设备。',
    ];
    $ms = array_merge($ms, $last_ms);
    $zh = array_merge($zh, $last_zh);


    // Extra hardcoded UI phrase translations added for full 3-language switching
    $ms = array_merge($ms, [
        '快捷操作' => 'Tindakan Pantas',
        '一键确认' => 'Sahkan Sekali Klik',
        'Created By' => 'Dicipta Oleh',
        '工单时间线' => 'Garis Masa Tiket',
        '工单已创建' => 'Tiket Dicipta',
        'No asset linked.' => 'Tiada aset dipautkan.',
        '资产历史' => 'Sejarah Aset',
        '关联工单' => 'Tiket Berkaitan',
        'No related tickets found for this asset.' => 'Tiada tiket berkaitan untuk aset ini.',
        'Edit Asset' => 'Edit Aset',
        'View 资产历史' => 'Lihat Sejarah Aset',
        'Message' => 'Mesej',
        'Type update, troubleshooting result, or instruction for branch...' => 'Taip kemas kini, hasil penyelesaian masalah atau arahan untuk cawangan...',
        'Choose File' => 'Pilih Fail',
        'Choose Files' => 'Pilih Fail',
        'No file chosen' => 'Tiada fail dipilih',
        'No file selected' => 'Tiada fail dipilih',
        'No photo selected' => 'Tiada foto dipilih',
        'file selected' => 'fail dipilih',
        'Quick Template' => 'Templat Pantas',
        'QUICK TEMPLATE' => 'Templat Pantas',
        'SOP Template' => 'Templat SOP',
        'FAQ Template' => 'Templat Soalan Lazim',
        'Troubleshooting Template' => 'Templat Penyelesaian Masalah',
        'Draft articles are for admin review.' => 'Artikel draf untuk semakan admin.',
        'Draft article for admin review.' => 'Artikel draf untuk semakan admin.',
        '保存文章' => 'Simpan Artikel',
        'active/repair' => 'aktif/dibaiki',
        'active/repair asset' => 'aset aktif/dibaiki',
        'active/repair Asset Management records' => 'rekod Pengurusan Aset aktif/dibaiki',
        'Asset dropdown is linked to Role Permission Matrix and active/repair Asset Management records.' => 'Senarai aset dipautkan kepada Matriks Kebenaran Peranan dan rekod Pengurusan Aset aktif/dibaiki.',
        'No active/repair asset found for your allowed branch. Add asset in Asset Management or check asset branch/status.' => 'Tiada aset aktif/dibaiki ditemui untuk cawangan dibenarkan. Tambah aset dalam Pengurusan Aset atau semak cawangan/status aset.',
        'Linked pages:' => 'Halaman berkaitan:',
        'Add Asset and Edit Asset will automatically use active Asset Types from this list.' => 'Tambah Aset dan Edit Aset akan menggunakan Jenis Aset aktif daripada senarai ini secara automatik.',
        'Asset Types already used by assets cannot be deleted; use Disable instead. Deleted unused default types will no longer come back automatically.' => 'Jenis Aset yang sudah digunakan tidak boleh dipadam; gunakan Nyahaktif. Jenis lalai tidak digunakan yang dipadam tidak akan kembali secara automatik.',
        'Maintain Asset Type list used by Add Asset, Edit Asset and Asset filtering.' => 'Urus senarai Jenis Aset yang digunakan oleh Tambah Aset, Edit Aset dan penapisan aset.',
        'Bootstrap badge color class.' => 'Kelas warna label Bootstrap.',
        'Only choose Admin / Head / Staff, then assign branch and PIC access.' => 'Pilih Admin / Head / Staff sahaja, kemudian tetapkan akses cawangan dan PIC.',
        'Controls ticket Assign To dropdown. Staff visibility follows assigned_to.' => 'Mengawal senarai Tugaskan Kepada untuk tiket. Paparan Staff mengikut assigned_to.',
        'Controls Person In Charge choices for ticket and Head visibility.' => 'Mengawal pilihan PIC untuk tiket dan paparan Head.',
        'Branch master data for tickets and Head/Staff access.' => 'Data induk cawangan untuk tiket dan akses Head/Staff.',
        'Asset type master list used by Add Asset and Edit Asset.' => 'Senarai induk Jenis Aset untuk Tambah Aset dan Edit Aset.',
        'Ticket category setup linked to Create Ticket and reports.' => 'Tetapan kategori tiket dipautkan kepada Cipta Tiket dan laporan.',
        'SLA rules linked to due date and monitoring.' => 'Peraturan SLA dipautkan kepada tarikh akhir dan pemantauan.',
        'Controls ticket status dropdown, badge color and closed/archive logic.' => 'Mengawal status tiket, warna label dan logik tutup/arkib.',
        'Review administration changes.' => 'Semak perubahan pentadbiran.',
        '指派工单' => 'Tugaskan Tiket',
        'Change Ticket Status' => 'Tukar Status Tiket',
        'Edit Ticket' => 'Edit Tiket',
        'Delete Ticket' => 'Padam Tiket',
        'Export Ticket' => 'Eksport Tiket',
        'Manage Asset' => 'Urus Aset',
        'Select Asset In Ticket' => 'Pilih Aset Dalam Tiket',
        'Can assign ticket to another user.' => 'Boleh tugaskan tiket kepada pengguna lain.',
        'Can update ticket status.' => 'Boleh kemas kini status tiket.',
        'Can reply to tickets and upload reply attachments.' => 'Boleh membalas tiket dan memuat naik lampiran.',
        'Can edit ticket information.' => 'Boleh edit maklumat tiket.',
        'Can delete tickets.' => 'Boleh padam tiket.',
        'Can export ticket CSV/report.' => 'Boleh eksport tiket CSV/laporan.',
        'Can add/edit/delete asset records.' => 'Boleh tambah/edit/padam rekod aset.',
        'Can select asset/equipment when creating or editing tickets.' => 'Boleh pilih aset/peralatan semasa mencipta atau mengedit tiket.',
        'Can add/delete announcements and view read report.' => 'Boleh tambah/padam pengumuman dan lihat laporan bacaan.',
        'Can add/edit/delete knowledge base articles and attachments.' => 'Boleh tambah/edit/padam artikel pengetahuan dan lampiran.',
        'Can create, edit, disable and delete users.' => 'Boleh cipta, edit, nyahaktif dan padam pengguna.',
        'Can export audit log CSV.' => 'Boleh eksport log audit CSV.',
        'Can use print buttons on reports/detail pages.' => 'Boleh guna butang cetak pada halaman laporan/perincian.',
        'Asset / Equipment' => 'Aset / Peralatan',
        'No Permission To Select Asset' => 'Tiada Kebenaran Memilih Aset',
        'No Asset Selected' => 'Tiada Aset Dipilih',
        'Counter 1 / Office / Server Rack' => 'Kaunter 1 / Pejabat / Rak Server',
        'Quick Templates' => 'Templat Pantas',
    ]);
    $zh = array_merge($zh, [
        '快捷操作' => '快捷操作',
        '一键确认' => '一键确认',
        'Created By' => '创建人',
        '工单时间线' => '工单时间线',
        '工单已创建' => '工单已创建',
        'No asset linked.' => '未关联资产。',
        '资产历史' => '资产历史',
        '关联工单' => '关联工单',
        'No related tickets found for this asset.' => '此资产暂无关联工单。',
        'Edit Asset' => '编辑资产',
        'View 资产历史' => '查看资产历史',
        'Message' => '信息',
        'Type update, troubleshooting result, or instruction for branch...' => '填写更新内容、处理结果或给分行的指示...',
        'Choose File' => '选择文件',
        'Choose Files' => '选择文件',
        'No file chosen' => '未选择文件',
        'No file selected' => '未选择文件',
        'No photo selected' => '未选择照片',
        'file selected' => '已选择文件',
        'Quick Template' => '快速模板',
        'QUICK TEMPLATE' => '快速模板',
        'SOP Template' => 'SOP模板',
        'FAQ Template' => '常见问题模板',
        'Troubleshooting Template' => '故障排查模板',
        'Draft articles are for admin review.' => '草稿文章供管理员审核。',
        'Draft article for admin review.' => '草稿文章供管理员审核。',
        '保存文章' => '保存文章',
        'active/repair' => '启用/维修',
        'active/repair asset' => '启用/维修中的资产',
        'active/repair Asset Management records' => '启用/维修中的资产管理记录',
        'Asset dropdown is linked to Role Permission Matrix and active/repair Asset Management records.' => '资产下拉列表已连接角色权限矩阵和启用/维修中的资产管理记录。',
        'No active/repair asset found for your allowed branch. Add asset in Asset Management or check asset branch/status.' => '没有找到允许分行的启用/维修资产。请在资产管理新增资产或检查资产分行/状态。',
        'Linked pages:' => '关联页面：',
        'Add Asset and Edit Asset will automatically use active Asset Types from this list.' => '添加资产和编辑资产会自动使用此列表中的启用资产类型。',
        'Asset Types already used by assets cannot be deleted; use Disable instead. Deleted unused default types will no longer come back automatically.' => '已被资产使用的资产类型不能删除，请改用停用。已删除且未使用的默认类型不会再自动恢复。',
        'Maintain Asset Type list used by Add Asset, Edit Asset and Asset filtering.' => '管理新增资产、编辑资产和资产筛选使用的资产类型列表。',
        'Bootstrap badge color class.' => 'Bootstrap 标签颜色类别。',
        'Only choose Admin / Head / Staff, then assign branch and PIC access.' => '只需选择 Admin / Head / Staff，然后分配分行和 PIC 权限。',
        'Controls ticket Assign To dropdown. Staff visibility follows assigned_to.' => '控制工单“指派给”下拉列表。Staff 可见性跟随指派对象。',
        'Controls Person In Charge choices for ticket and Head visibility.' => '控制工单负责人选项和 Head 可见范围。',
        'Branch master data for tickets and Head/Staff access.' => '用于工单及 Head/Staff 权限的分行主资料。',
        'Asset type master list used by Add Asset and Edit Asset.' => '新增资产和编辑资产使用的资产类型主列表。',
        'Ticket category setup linked to Create Ticket and reports.' => '工单分类设置会连接创建工单和报表。',
        'SLA rules linked to due date and monitoring.' => 'SLA规则连接到到期日和监控。',
        'Controls ticket status dropdown, badge color and closed/archive logic.' => '控制工单状态下拉、标签颜色和关闭/归档逻辑。',
        'Review administration changes.' => '查看管理变更。',
        '指派工单' => '指派工单',
        'Change Ticket Status' => '更改工单状态',
        'Edit Ticket' => '编辑工单',
        'Delete Ticket' => '删除工单',
        'Export Ticket' => '导出工单',
        'Manage Asset' => '管理资产',
        'Select Asset In Ticket' => '工单中选择资产',
        'Can assign ticket to another user.' => '可以把工单指派给其他用户。',
        'Can update ticket status.' => '可以更新工单状态。',
        'Can reply to tickets and upload reply attachments.' => '可以回复工单并上传附件。',
        'Can edit ticket information.' => '可以编辑工单资料。',
        'Can delete tickets.' => '可以删除工单。',
        'Can export ticket CSV/report.' => '可以导出工单CSV/报告。',
        'Can add/edit/delete asset records.' => '可以新增/编辑/删除资产记录。',
        'Can select asset/equipment when creating or editing tickets.' => '创建或编辑工单时可以选择资产/设备。',
        'Can add/delete announcements and view read report.' => '可以新增/删除公告并查看阅读报告。',
        'Can add/edit/delete knowledge base articles and attachments.' => '可以新增/编辑/删除知识库文章和附件。',
        'Can create, edit, disable and delete users.' => '可以创建、编辑、停用和删除用户。',
        'Can export audit log CSV.' => '可以导出审计日志CSV。',
        'Can use print buttons on reports/detail pages.' => '可以使用报告/详情页的打印按钮。',
        'Asset / Equipment' => '资产 / 设备',
        'No Permission To Select Asset' => '无权限选择资产',
        'No Asset Selected' => '未选择资产',
        'Counter 1 / Office / Server Rack' => '柜台 1 / 办公室 / 服务器架',
        'Quick Templates' => '快速模板',
    ]);

    // Final patch: view announcement topbar title
    $ms = array_merge($ms, [
        'View Announcement' => 'Lihat Pengumuman',
        'Communication / View Announcement' => 'Komunikasi / Lihat Pengumuman',
        'Attachment' => 'Lampiran',

        'No ticket found for this filter.' => 'Tiada tiket dijumpai untuk tapisan ini.',
        'Person In Charge' => 'PIC',
        'Assigned To' => 'Ditugaskan Kepada',
        'Created At' => 'Dicipta Pada',
        'Last Update' => 'Kemaskini Terakhir',
        'Unassigned' => 'Belum Ditugaskan',
    ]);
    $zh = array_merge($zh, [
        'View Announcement' => '查看公告',
        'Communication / View Announcement' => '沟通 / 查看公告',
        'Attachment' => '附件',

        'No ticket found for this filter.' => '没有符合条件的工单',
        'Person In Charge' => 'PIC',
        'Assigned To' => '指派给',
        'Created At' => '创建时间',
        'Last Update' => '最后更新',
        'Unassigned' => '未指派',
    ]);


    // Notification center translations
    $ms = array_merge($ms, [
        'Notifications' => 'Notifikasi',
        'System notifications linked with tickets, announcements, knowledge base and status changes.' => 'Notifikasi sistem berkaitan tiket, pengumuman, pangkalan pengetahuan dan perubahan status.',
        'Mark All Read' => 'Tanda Semua Dibaca',
        'All' => 'Semua',
        'Unread' => 'Belum Dibaca',
        'Read' => 'Dibaca',
        'READ' => 'DIBACA',
        'UNREAD' => 'BELUM DIBACA',
        'Ticket' => 'Tiket',
        'Announcement' => 'Pengumuman',
        'No notifications found.' => 'Tiada notifikasi dijumpai.',
        'No new notifications' => 'Tiada notifikasi baharu',
        'Loading...' => 'Memuatkan...',
        'New Ticket Assigned' => 'Tiket Baharu Ditugaskan',
        'Ticket Assigned To You' => 'Tiket Ditugaskan Kepada Anda',
        'Assigned' => 'Ditugaskan',
        'Assigned:' => 'Ditugaskan:',
        'Assigned by' => 'Ditugaskan oleh',
        'Branch:' => 'Cawangan:',
        'Priority:' => 'Keutamaan:',
        'Title:' => 'Tajuk:',
    ]);
    $zh = array_merge($zh, [
        'Notifications' => '通知',
        'System notifications linked with tickets, announcements, knowledge base and status changes.' => '系统通知会连接工单、公告、知识库和状态变化。',
        'Mark All Read' => '全部标记已读',
        'All' => '全部',
        'Unread' => '未读',
        'Read' => '已读',
        'READ' => '已读',
        'UNREAD' => '未读',
        'Ticket' => '工单',
        'Announcement' => '公告',
        'No notifications found.' => '没有找到通知。',
        'No new notifications' => '没有新通知',
        'Loading...' => '加载中...',
        'New Ticket Assigned' => '新工单已指派',
        'Ticket Assigned To You' => '工单已指派给你',
        'Assigned' => '已指派',
        'Assigned:' => '已指派:',
        'Assigned by' => '指派者',
        'Branch:' => '分行:',
        'Priority:' => '优先级:',
        'Title:' => '标题:',
    ]);



    // Final mobile/detail/notification language coverage patch.
    $final_ms = [
        'KB'=>'Pangkalan Pengetahuan','Ticket'=>'Tiket','Announcement'=>'Pengumuman','Unread'=>'Belum Dibaca','READ'=>'DIBACA','UNREAD'=>'BELUM DIBACA','Mark All Read'=>'Tanda Semua Dibaca','No notifications found.'=>'Tiada notifikasi dijumpai.','System notifications linked with tickets, announcements, knowledge base and status changes.'=>'Notifikasi sistem dihubungkan dengan tiket, pengumuman, pangkalan pengetahuan dan perubahan status.','New Ticket Assigned'=>'Tiket Baharu Ditugaskan','Ticket Assigned To You'=>'Tiket Ditugaskan Kepada Anda','Assigned'=>'Ditugaskan','Assigned by'=>'Ditugaskan oleh','Title:'=>'Tajuk:','Branch:'=>'Cawangan:','Priority:'=>'Keutamaan:',
        'Created'=>'Dicipta','Ticket No'=>'No. Tiket','Back'=>'Kembali','Print'=>'Cetak','Edit Ticket'=>'Edit Tiket','Show Summary'=>'Tunjuk Ringkasan','Hide Summary'=>'Sembunyi Ringkasan','Open All'=>'Buka Semua','Close All'=>'Tutup Semua','Ticket Information'=>'Maklumat Tiket','Created By'=>'Dicipta Oleh','Created At'=>'Dicipta Pada','Last Updated'=>'Kemas Kini Terakhir','Last Updated By'=>'Dikemas Kini Oleh','Closed At'=>'Ditutup Pada','SLA Hours'=>'Jam SLA','Due Date'=>'Tarikh Tamat','Assigned To'=>'Ditugaskan Kepada','Unassigned'=>'Belum Ditugaskan','Within SLA'=>'Dalam SLA','Completed'=>'Selesai','Ticket Attachment'=>'Lampiran Tiket','View / Download'=>'Lihat / Muat Turun','Reply Ticket'=>'Balas Tiket','Message'=>'Mesej','Type update, troubleshooting result, or instruction for branch...'=>'Tulis kemas kini, hasil penyelesaian masalah atau arahan kepada cawangan...','Upload Attachment'=>'Muat Naik Lampiran','Camera'=>'Kamera','Gallery / File'=>'Galeri / Fail','Voice'=>'Suara','Choose File / Photo'=>'Pilih Fail / Foto','No file selected'=>'Tiada fail dipilih','Allowed: jpg, jpeg, png, pdf, doc, docx, xls, xlsx. Max 10MB.'=>'Dibenarkan: jpg, jpeg, png, pdf, doc, docx, xls, xlsx. Maksimum 10MB.','Submit Reply'=>'Hantar Balasan','Reply History'=>'Sejarah Balasan','No replies yet.'=>'Belum ada balasan.','Reply Attachment'=>'Lampiran Balasan','Quick Actions'=>'Tindakan Pantas'
    ];
    $final_zh = [
        'KB'=>'知识库','Ticket'=>'工单','Announcement'=>'公告','Unread'=>'未读','READ'=>'已读','UNREAD'=>'未读','Mark All Read'=>'全部标记已读','No notifications found.'=>'没有通知。','System notifications linked with tickets, announcements, knowledge base and status changes.'=>'系统通知会连接工单、公告、知识库和状态变化。','New Ticket Assigned'=>'新工单已指派','Ticket Assigned To You'=>'工单已指派给你','Assigned'=>'已指派','Assigned by'=>'指派者','Title:'=>'标题：','Branch:'=>'分行：','Priority:'=>'优先级：',
        'Created'=>'创建时间','Ticket No'=>'工单号','Back'=>'返回','Print'=>'打印','Edit Ticket'=>'编辑工单','Show Summary'=>'显示汇总','Hide Summary'=>'隐藏汇总','Open All'=>'展开全部','Close All'=>'收起全部','Ticket Information'=>'工单资料','Created By'=>'创建人','Created At'=>'创建时间','Last Updated'=>'最后更新','Last Updated By'=>'最后更新者','Closed At'=>'关闭时间','SLA Hours'=>'SLA小时','Due Date'=>'截止日期','Assigned To'=>'指派给','Unassigned'=>'未指派','Within SLA'=>'SLA内','Completed'=>'已完成','Ticket Attachment'=>'工单附件','View / Download'=>'查看 / 下载','Reply Ticket'=>'回复工单','Message'=>'信息','Type update, troubleshooting result, or instruction for branch...'=>'填写更新内容、处理结果或给分行的指示...','Upload Attachment'=>'上传附件','Camera'=>'拍照','Gallery / File'=>'相册 / 文件','Voice'=>'语音','Choose File / Photo'=>'选择文件 / 照片','No file selected'=>'未选择文件','Allowed: jpg, jpeg, png, pdf, doc, docx, xls, xlsx. Max 10MB.'=>'允许：jpg、jpeg、png、pdf、doc、docx、xls、xlsx。最大10MB。','Submit Reply'=>'提交回复','Reply History'=>'回复历史','No replies yet.'=>'还没有回复。','Reply Attachment'=>'回复附件','Quick Actions'=>'快捷操作'
    ];
    $final_ms = array_merge($final_ms, [
        'Update asset details and equipment photo.'=>'Kemas kini maklumat aset dan gambar peralatan.',
        'Linked Tickets'=>'Tiket Berkaitan',
        'Select Person In Charge'=>'Pilih Pegawai Bertanggungjawab'
    ]);

    $ms = array_merge($ms, $final_ms);
    $zh = array_merge($zh, $final_zh);



    // Knowledge Base article edit/view final i18n patch
    $ms = array_merge($ms, [
        'Knowledge Base / Add Article' => 'Pangkalan Pengetahuan / Tambah Artikel',
        'Knowledge Base / Edit Article' => 'Pangkalan Pengetahuan / Edit Artikel',
        'Knowledge Base / View Article' => 'Pangkalan Pengetahuan / Lihat Artikel',
        'Add Article' => 'Tambah Artikel',
        'Edit Article' => 'Edit Artikel',
        'View Article' => 'Lihat Artikel',
        'Article' => 'Artikel',
        'article' => 'artikel',
        'Article Content' => 'Kandungan Artikel',
        'Article Info' => 'Maklumat Artikel',
        'Update category, type, tags and branch scope' => 'Kemas kini kategori, jenis, tag dan skop cawangan',
        'Edit category, type, tags and branch scope' => 'Edit kategori, jenis, tag dan skop cawangan',
        'Linked to Category Management.' => 'Dipautkan kepada Pengurusan Kategori.',
        'Knowledge Type' => 'Jenis Pengetahuan',
        'Tags' => 'Tag',
        'Content' => 'Kandungan',
        'Current Attachments' => 'Lampiran Semasa',
        'Current Attachment' => 'Lampiran Semasa',
        'Add More Attachments' => 'Tambah Lampiran Lain',
        'More Attachment' => 'Lampiran Tambahan',
        'Attachments' => 'Lampiran',
        'No attachment.' => 'Tiada lampiran.',
        'Branch Scope' => 'Skop Cawangan',
        'Selected Branch Only' => 'Cawangan Dipilih Sahaja',
        'Draft' => 'Draf',
        'Published' => 'Diterbitkan',
        'Save Changes' => 'Simpan Perubahan',
        'Open' => 'Buka',
        'views' => 'paparan',
        'attachment(s)' => 'lampiran',
        'Invalid article id' => 'ID artikel tidak sah',
        'Article not found' => 'Artikel tidak dijumpai',
        'Please fill in all required fields.' => 'Sila isi semua medan wajib.',
        'You do not have permission to view this article.' => 'Anda tiada kebenaran untuk melihat artikel ini.',
        'POS, printer, barcode, sync' => 'POS, pencetak, kod bar, sync',
    ]);
    $zh = array_merge($zh, [
        'Knowledge Base / Add Article' => '知识库 / 添加文章',
        'Knowledge Base / Edit Article' => '知识库 / 编辑文章',
        'Knowledge Base / View Article' => '知识库 / 查看文章',
        'Add Article' => '添加文章',
        'Edit Article' => '编辑文章',
        'View Article' => '查看文章',
        'Article' => '文章',
        'article' => '文章',
        'Article Content' => '文章内容',
        'Article Info' => '文章信息',
        'Update category, type, tags and branch scope' => '更新分类、类型、标签和分行范围',
        'Edit category, type, tags and branch scope' => '编辑分类、类型、标签和分行范围',
        'Linked to Category Management.' => '关联分类管理。',
        'Knowledge Type' => '知识库类型',
        'Tags' => '标签',
        'Content' => '内容',
        'Current Attachments' => '当前附件',
        'Current Attachment' => '当前附件',
        'Add More Attachments' => '添加更多附件',
        'More Attachment' => '更多附件',
        'Attachments' => '附件',
        'No attachment.' => '无附件。',
        'Branch Scope' => '分行范围',
        'Selected Branch Only' => '仅选择的分行',
        'Draft' => '草稿',
        'Published' => '已发布',
        'Save Changes' => '保存更改',
        'Open' => '打开',
        'views' => '浏览',
        'attachment(s)' => '附件',
        'Invalid article id' => '无效文章ID',
        'Article not found' => '找不到文章',
        'Please fill in all required fields.' => '请填写所有必填项目。',
        'You do not have permission to view this article.' => '你没有权限查看这篇文章。',
        'POS, printer, barcode, sync' => 'POS、打印机、条码、同步',
    ]);


    // FULL-SCAN FIX 2026-06-24: remaining hard-coded UI labels across KB, tickets, notifications, reports and mobile views.
    $fullscan_ms = [
        'Knowledge Base / Add Article'=>'Pangkalan Pengetahuan / Tambah Artikel',
        'Knowledge Base / Edit Article'=>'Pangkalan Pengetahuan / Edit Artikel',
        'Knowledge Base / View Article'=>'Pangkalan Pengetahuan / Lihat Artikel',
        'Add Article'=>'Tambah Artikel','Edit Article'=>'Edit Artikel','View Article'=>'Lihat Artikel','Article'=>'Artikel','article'=>'artikel',
        'Article Content'=>'Kandungan Artikel','Article Info'=>'Maklumat Artikel','Article Information'=>'Maklumat Artikel',
        'Update category, type, tags and branch scope'=>'Kemas kini kategori, jenis, tag dan skop cawangan',
        'Edit category, type, tags and branch scope'=>'Edit kategori, jenis, tag dan skop cawangan',
        'Create organized knowledge article with type, tags and branch scope'=>'Cipta artikel pengetahuan tersusun dengan jenis, tag dan skop cawangan',
        'Linked to Category Management.'=>'Dipautkan kepada Pengurusan Kategori.',
        'Linked to 分类管理.'=>'Dipautkan kepada Pengurusan Kategori.',
        'Current Attachments'=>'Lampiran Semasa','Current Attachment'=>'Lampiran Semasa','Current 附件'=>'Lampiran Semasa',
        'Add More Attachments'=>'Tambah Lampiran','More Attachment'=>'Lampiran Tambahan','More 附件'=>'Lampiran Tambahan',
        'Save Changes'=>'Simpan Perubahan','No attachment.'=>'Tiada lampiran.','No attachments.'=>'Tiada lampiran.',
        'No content.'=>'Tiada kandungan.','attachment(s)'=>'lampiran','views'=>'paparan','Draft'=>'Draf','Published'=>'Diterbitkan',
        'Created'=>'Dicipta','Updated'=>'Dikemas kini','Created By'=>'Dicipta Oleh','Created at'=>'Dicipta pada','Created At'=>'Dicipta Pada',
        'Show Summary'=>'Tunjuk Ringkasan','Hide Summary'=>'Sembunyi Ringkasan','Open All'=>'Buka Semua','Close All'=>'Tutup Semua',
        'Reply Ticket'=>'Balas Tiket','Submit Reply'=>'Hantar Balasan','Reply History'=>'Sejarah Balasan','Reply Attachment'=>'Lampiran Balasan','No replies yet.'=>'Belum ada balasan.',
        'Quick Actions'=>'Tindakan Pantas','One Click Confirm'=>'Sahkan Sekali Klik','Ticket Timeline'=>'Garis Masa Tiket','Ticket Created'=>'Tiket Dicipta',
        'Asset linked.'=>'Aset dipautkan.','No asset linked.'=>'Tiada aset dipautkan.','Message'=>'Mesej','Information'=>'Maklumat',
        'Notifications'=>'Notifikasi','System notifications linked with tickets, announcements, knowledge base and status changes.'=>'Notifikasi sistem dipautkan dengan tiket, pengumuman, pangkalan pengetahuan dan perubahan status.',
        'Mark All Read'=>'Tanda Semua Dibaca','Unread'=>'Belum Dibaca','READ'=>'DIBACA','UNREAD'=>'BELUM DIBACA','Ticket'=>'Tiket','Announcement'=>'Pengumuman','KB'=>'KB',
        'New Ticket Assigned'=>'Tiket Baharu Ditugaskan','Ticket Assigned To You'=>'Tiket Ditugaskan Kepada Anda','Assigned'=>'Ditugaskan','Assigned by'=>'Ditugaskan oleh','Title:'=>'Tajuk:','Branch:'=>'Cawangan:','Priority:'=>'Keutamaan:',
        'No notifications found.'=>'Tiada notifikasi dijumpai.',
        'Branch Summary'=>'Ringkasan Cawangan','Person In Charge Summary'=>'Ringkasan PIC','Status Summary'=>'Ringkasan Status','Closed?'=>'Ditutup?',
        'Total'=>'Jumlah','SLA Compliance'=>'Pematuhan SLA','Month'=>'Bulan','View'=>'Lihat','Person In Charge'=>'PIC','PIC'=>'PIC',
        '负责人'=>'Pegawai Bertanggungjawab','指派给'=>'Ditugaskan Kepada','创建时间'=>'Masa Dicipta','最后更新'=>'Kemas Kini Terakhir',
        'DICIPTA'=>'DICIPTA','DITUGASKAN'=>'DITUGASKAN','KEMASKINI TERAKHIR'=>'KEMAS KINI TERAKHIR',
        'Camera'=>'Kamera','Gallery / File'=>'Galeri / Fail','Voice'=>'Suara','Choose File / Photo'=>'Pilih Fail / Foto','Choose File / Photo'=>'Pilih Fail / Foto',
        'Pull to refresh'=>'Tarik untuk segar semula','Release to refresh'=>'Lepas untuk segar semula','Refreshing...'=>'Sedang menyegar semula...',
        'Select File / Photo'=>'Pilih Fail / Foto','No file selected'=>'Tiada fail dipilih','No file chosen'=>'Tiada fail dipilih',
        'Choose File'=>'Pilih Fail','Choose Files'=>'Pilih Fail','Submit Ticket'=>'Hantar Tiket','Cancel'=>'Batal','Back'=>'Kembali','Print'=>'Cetak'
    ];
    $fullscan_zh = [
        'Knowledge Base / Add Article'=>'知识库 / 添加文章',
        'Knowledge Base / Edit Article'=>'知识库 / 编辑文章',
        'Knowledge Base / View Article'=>'知识库 / 查看文章',
        'Add Article'=>'添加文章','Edit Article'=>'编辑文章','View Article'=>'查看文章','Article'=>'文章','article'=>'文章',
        'Article Content'=>'文章内容','Article Info'=>'文章信息','Article Information'=>'文章信息',
        'Update category, type, tags and branch scope'=>'更新分类、类型、标签和分行范围',
        'Edit category, type, tags and branch scope'=>'编辑分类、类型、标签和分行范围',
        'Create organized knowledge article with type, tags and branch scope'=>'创建带类型、标签和分行范围的知识文章',
        'Linked to Category Management.'=>'关联分类管理。',
        'Linked to 分类管理.'=>'关联分类管理。',
        'Current Attachments'=>'当前附件','Current Attachment'=>'当前附件','Current 附件'=>'当前附件',
        'Add More Attachments'=>'添加更多附件','More Attachment'=>'更多附件','More 附件'=>'更多附件',
        'Save Changes'=>'保存更改','No attachment.'=>'无附件。','No attachments.'=>'无附件。',
        'No content.'=>'无内容。','attachment(s)'=>'附件','views'=>'浏览','Draft'=>'草稿','Published'=>'已发布',
        'Created'=>'创建','Updated'=>'已更新','Created By'=>'创建人','Created at'=>'创建时间','Created At'=>'创建时间',
        'Show Summary'=>'显示汇总','Hide Summary'=>'隐藏汇总','Open All'=>'打开全部','Close All'=>'关闭全部',
        'Reply Ticket'=>'回复工单','Submit Reply'=>'提交回复','Reply History'=>'回复历史','Reply Attachment'=>'回复附件','No replies yet.'=>'暂无回复。',
        'Quick Actions'=>'快捷操作','One Click Confirm'=>'一键确认','Ticket Timeline'=>'工单时间线','Ticket Created'=>'工单已创建',
        'Asset linked.'=>'已关联资产。','No asset linked.'=>'未关联资产。','Message'=>'信息','Information'=>'信息',
        'Notifications'=>'通知','System notifications linked with tickets, announcements, knowledge base and status changes.'=>'系统通知会连接工单、公告、知识库和状态变化。',
        'Mark All Read'=>'全部标记已读','Unread'=>'未读','READ'=>'已读','UNREAD'=>'未读','Ticket'=>'工单','Announcement'=>'公告','KB'=>'KB',
        'New Ticket Assigned'=>'新工单已指派','Ticket Assigned To You'=>'工单已指派给你','Assigned'=>'已指派','Assigned by'=>'指派人','Title:'=>'标题：','Branch:'=>'分行：','Priority:'=>'优先级：',
        'No notifications found.'=>'没有通知。',
        'Branch Summary'=>'分行汇总','Person In Charge Summary'=>'PIC 汇总','Status Summary'=>'状态汇总','Closed?'=>'已关闭？',
        'Total'=>'总数','SLA Compliance'=>'SLA达标率','Month'=>'月份','View'=>'查看','Person In Charge'=>'PIC','PIC'=>'PIC',
        'DICIPTA'=>'创建时间','DITUGASKAN'=>'指派给','KEMASKINI TERAKHIR'=>'最后更新','KEMAS KINI TERAKHIR'=>'最后更新',
        'Camera'=>'拍照','Gallery / File'=>'相册 / 文件','Voice'=>'语音','Choose File / Photo'=>'选择文件 / 照片',
        'Pull to refresh'=>'下拉刷新','Release to refresh'=>'松开刷新','Refreshing...'=>'正在刷新...',
        'Select File / Photo'=>'选择文件 / 照片','No file selected'=>'未选择文件','No file chosen'=>'未选择文件',
        'Choose File'=>'选择文件','Choose Files'=>'选择文件','Submit Ticket'=>'提交工单','Cancel'=>'取消','Back'=>'返回','Print'=>'打印'
    ];
    $ms = array_merge($ms, $fullscan_ms);
    $zh = array_merge($zh, $fullscan_zh);


    // CIRCLE-FIX FINAL: exact labels marked by user screenshots only.
    $circle_ms = [
        'Knowledge Base'=>'Pangkalan Pengetahuan','KB'=>'Pangkalan Pengetahuan','Ticket'=>'Tiket','Announcement'=>'Pengumuman','Notifications'=>'Notifikasi',
        'All'=>'Semua','Unread'=>'Belum Dibaca','READ'=>'DIBACA','UNREAD'=>'BELUM DIBACA','Mark All Read'=>'Tanda Semua Dibaca',
        'New Ticket Assigned'=>'Tiket Baharu Ditugaskan','Assigned'=>'Ditugaskan','Assigned by'=>'Ditugaskan oleh',
        'Created'=>'Dicipta','Created At'=>'Dicipta Pada','Created Time'=>'Masa Dicipta','Last Update'=>'Kemas Kini Terakhir','Last Updated'=>'Kemas Kini Terakhir',
        'Person In Charge'=>'PIC','PIC'=>'PIC','Assigned To'=>'Ditugaskan Kepada','Assign To'=>'Tugaskan Kepada','Assignee'=>'Penerima Tugas',
        'Show Summary'=>'Tunjuk Ringkasan','Hide Summary'=>'Sembunyi Ringkasan','Open All'=>'Buka Semua','Close All'=>'Tutup Semua',
        'Reply Ticket'=>'Balas Tiket','Submit Reply'=>'Hantar Balasan','Reply History'=>'Sejarah Balasan','No replies yet.'=>'Belum ada balasan.',
        'No related tickets found for this asset.'=>'Tiada tiket berkaitan untuk aset ini.','Desktop table can scroll horizontally if needed.'=>'Jadual desktop boleh ditatal secara mendatar jika perlu.',
        'Asset Photo'=>'Foto Aset','Upload new photo to replace current photo. Maximum 5MB.'=>'Muat naik foto baharu untuk menggantikan foto semasa. Maksimum 5MB.',
        'Remove current photo'=>'Buang foto semasa','No photo uploaded'=>'Tiada foto dimuat naik','Photo Preview'=>'Pratonton Foto','Update Asset'=>'Kemas Kini Aset','Edit Asset'=>'Edit Aset','Back to Asset List'=>'Kembali ke Senarai Aset','History'=>'Sejarah','Asset Code'=>'Kod Aset','Asset Name'=>'Nama Aset','Asset Type'=>'Jenis Aset','Brand'=>'Jenama','Model'=>'Model','Serial No'=>'No Siri','Location'=>'Lokasi','Purchase Date'=>'Tarikh Beli','Remark'=>'Catatan','Controlled by Asset Type Management.'=>'Dikawal oleh Pengurusan Jenis Aset.','Related Tickets'=>'Tiket Berkaitan','Tickets'=>'Tiket','View Ticket'=>'Lihat Tiket',
        'Edit Article'=>'Edit Artikel','View Article'=>'Lihat Artikel','Article'=>'Artikel','article'=>'artikel','Article Info'=>'Maklumat Artikel','Article Information'=>'Maklumat Artikel',
        'Current Attachments'=>'Lampiran Semasa','Current Attachment'=>'Lampiran Semasa','Linked to Category Management.'=>'Dipautkan kepada Pengurusan Kategori.','Save Changes'=>'Simpan Perubahan',
        'Choose File'=>'Pilih Fail','Choose Files'=>'Pilih Fail','No file chosen'=>'Tiada fail dipilih','No file selected'=>'Tiada fail dipilih',
        'Read Report'=>'Laporan Bacaan','read report'=>'laporan bacaan','Announcement Read Status'=>'Status Bacaan Pengumuman','Total Users'=>'Jumlah Pengguna','Read'=>'Dibaca','Unread'=>'Belum Dibaca','Read Rate'=>'Kadar Bacaan','No announcement found.'=>'Tiada pengumuman dijumpai.','Select Announcement'=>'Pilih Pengumuman',
        'Title:'=>'Tajuk:','Branch:'=>'Cawangan:','Priority:'=>'Keutamaan:',
        '负责人'=>'Pegawai Bertanggungjawab','指派给'=>'Ditugaskan Kepada','创建时间'=>'Masa Dicipta','最后更新'=>'Kemas Kini Terakhir','序号'=>'Tiada',
        'Show'=>'Tunjuk','Open'=>'Buka','Reply'=>'Balasan'
    ];
    $circle_zh = [
        'Knowledge Base'=>'知识库','KB'=>'知识库','Ticket'=>'工单','Announcement'=>'公告','Notifications'=>'通知',
        'All'=>'全部','Unread'=>'未读','READ'=>'已读','UNREAD'=>'未读','Mark All Read'=>'全部标记已读',
        'New Ticket Assigned'=>'新工单已指派','Assigned'=>'已指派','Assigned by'=>'指派人',
        'Created'=>'创建','Created At'=>'创建时间','Created Time'=>'创建时间','Last Update'=>'最后更新','Last Updated'=>'最后更新',
        'Person In Charge'=>'PIC','PIC'=>'PIC','Assigned To'=>'指派给','Assign To'=>'指派给','Assignee'=>'负责人',
        'Show Summary'=>'显示汇总','Hide Summary'=>'隐藏汇总','Open All'=>'展开全部','Close All'=>'收起全部',
        'Reply Ticket'=>'回复工单','Submit Reply'=>'提交回复','Reply History'=>'回复历史','No replies yet.'=>'暂无回复。',
        'No related tickets found for this asset.'=>'此资产暂无关联工单。','Desktop table can scroll horizontally if needed.'=>'桌面表格可横向滚动。',
        'Asset Photo'=>'资产照片','Upload new photo to replace current photo. Maximum 5MB.'=>'上传新照片以替换当前照片。最大 5MB。',
        'Remove current photo'=>'移除当前照片','No photo uploaded'=>'未上传照片','Photo Preview'=>'照片预览','Update Asset'=>'更新资产','Edit Asset'=>'编辑资产','Back to Asset List'=>'返回资产列表','History'=>'历史','Asset Code'=>'资产编号','Asset Name'=>'资产名称','Asset Type'=>'资产类型','Brand'=>'品牌','Model'=>'型号','Serial No'=>'序列号','Location'=>'位置','Purchase Date'=>'购买日期','Remark'=>'备注','Controlled by Asset Type Management.'=>'由资产类型管理控制。','Related Tickets'=>'关联工单','Tickets'=>'工单','View Ticket'=>'查看工单',
        'Edit Article'=>'编辑文章','View Article'=>'查看文章','Article'=>'文章','article'=>'文章','Article Info'=>'文章信息','Article Information'=>'文章信息',
        'Current Attachments'=>'当前附件','Current Attachment'=>'当前附件','Linked to Category Management.'=>'关联分类管理。','Save Changes'=>'保存更改',
        'Choose File'=>'选择文件','Choose Files'=>'选择文件','No file chosen'=>'未选择文件','No file selected'=>'未选择文件',
        'Read Report'=>'阅读报告','read report'=>'阅读报告','Announcement Read Status'=>'公告阅读状态','Total Users'=>'用户总数','Read'=>'已读','Unread'=>'未读','Read Rate'=>'阅读率','No announcement found.'=>'没有找到公告。','Select Announcement'=>'选择公告',
        'Title:'=>'标题：','Branch:'=>'分行：','Priority:'=>'优先级：',
        'Show'=>'显示','Open'=>'展开','Reply'=>'回复'
    ];
    $ms = array_merge($ms, $circle_ms);
    $zh = array_merge($zh, $circle_zh);

    // Extra final fix for items circled after testing. Kept at the very end so it overrides older wording.
    $circle2_ms = [
        'System notifications linked with tickets, announcements, knowledge base and status changes.'=>'Notifikasi sistem dipautkan dengan tiket, pengumuman, pangkalan pengetahuan dan perubahan status.',
        'New Ticket Assigned'=>'Tiket Baharu Ditugaskan','New ticket assigned'=>'Tiket baharu ditugaskan','Assigned:'=>'Ditugaskan:','Assigned:'=>'Ditugaskan:',
        'Ticket'=>'Tiket','KB'=>'Pangkalan Pengetahuan','Mark All Read'=>'Tanda Semua Dibaca','Ticket List'=>'Senarai Tiket','No notifications found.'=>'Tiada notifikasi dijumpai.',
        'Created'=>'Dicipta','Created '=>'Dicipta ','Ticket No:'=>'No Tiket:','Show Summary'=>'Tunjuk Ringkasan','Open All'=>'Buka Semua','Open'=>'Buka','Show'=>'Tunjuk',
        'Submit Reply'=>'Hantar Balasan','Reply History'=>'Sejarah Balasan','Reply Attachment'=>'Lampiran Balasan','View / Download'=>'Lihat / Muat Turun',
        'No replies yet.'=>'Belum ada balasan.','No timeline records found.'=>'Tiada rekod garis masa dijumpai.','No asset linked.'=>'Tiada aset dipautkan.',
        'Choose File / Photo'=>'Pilih Fail / Foto','Choose File'=>'Pilih Fail','No file chosen'=>'Tiada fail dipilih','No file selected'=>'Tiada fail dipilih',
        'Camera'=>'Kamera','Gallery / File'=>'Galeri / Fail','Voice'=>'Suara','Upload Attachment'=>'Muat Naik Lampiran',
        'Article Info'=>'Maklumat Artikel','Article Information'=>'Maklumat Artikel','Article Content'=>'Kandungan Artikel','View Article'=>'Lihat Artikel','Edit Article'=>'Edit Artikel','Edit article'=>'Edit artikel','article'=>'artikel','Article'=>'Artikel',
        'Knowledge Base / View Article'=>'Pangkalan Pengetahuan / Lihat Artikel','Knowledge Base / Edit Article'=>'Pangkalan Pengetahuan / Edit Artikel','Knowledge Base / Article'=>'Pangkalan Pengetahuan / Artikel',
        'Linked to Category Management.'=>'Dipautkan kepada Pengurusan Kategori.','Current Attachments'=>'Lampiran Semasa','Current Attachment'=>'Lampiran Semasa','Save Changes'=>'Simpan Perubahan',
        'read report'=>'laporan bacaan','Read Report'=>'Laporan Bacaan','Check which branch / user has read company announcements.'=>'Semak cawangan / pengguna yang telah membaca pengumuman syarikat.',
        'Select Announcement'=>'Pilih Pengumuman','User Count'=>'Jumlah Pengguna','Read Count'=>'Jumlah Dibaca','Unread Count'=>'Jumlah Belum Dibaca',
        'Open only when needed'=>'Buka hanya apabila perlu','only when needed'=>'hanya apabila perlu','Search ticket no, title, description, PIC, assignee...'=>'Cari no tiket, tajuk, penerangan, PIC, penerima tugas...',
        'Asset Photo'=>'Foto Aset','Remove current photo'=>'Buang foto semasa','Upload new photo to replace current photo. Maximum 5MB.'=>'Muat naik foto baharu untuk menggantikan foto semasa. Maksimum 5MB.',
        'Desktop table can scroll horizontally if needed.'=>'Jadual desktop boleh ditatal secara mendatar jika perlu.','No related tickets found for this asset.'=>'Tiada tiket berkaitan untuk aset ini.',
        'How to reset GRN document'=>'Cara menetapkan semula dokumen GRN','How to Reset GRN document'=>'Cara menetapkan semula dokumen GRN'
    ];
    $circle2_zh = [
        'System notifications linked with tickets, announcements, knowledge base and status changes.'=>'系统通知会连接工单、公告、知识库和状态变化。',
        'New Ticket Assigned'=>'新工单已指派','New ticket assigned'=>'新工单已指派','Assigned:'=>'已指派：','Ticket'=>'工单','KB'=>'知识库','Mark All Read'=>'全部标记已读','Ticket List'=>'工单列表','No notifications found.'=>'没有通知。',
        'Created'=>'创建于','Created '=>'创建于 ','Ticket No:'=>'工单号：','Show Summary'=>'显示汇总','Open All'=>'全部展开','Open'=>'展开','Show'=>'显示',
        'Submit Reply'=>'提交回复','Reply History'=>'回复历史','Reply Attachment'=>'回复附件','View / Download'=>'查看 / 下载',
        'No replies yet.'=>'暂无回复。','No timeline records found.'=>'没有时间线记录。','No asset linked.'=>'没有关联资产。',
        'Choose File / Photo'=>'选择文件 / 照片','Choose File'=>'选择文件','No file chosen'=>'未选择文件','No file selected'=>'未选择文件',
        'Camera'=>'拍照','Gallery / File'=>'相册 / 文件','Voice'=>'语音','Upload Attachment'=>'上传附件',
        'Article Info'=>'文章信息','Article Information'=>'文章信息','Article Content'=>'文章内容','View Article'=>'查看文章','Edit Article'=>'编辑文章','Edit article'=>'编辑文章','article'=>'文章','Article'=>'文章',
        'Knowledge Base / View Article'=>'知识库 / 查看文章','Knowledge Base / Edit Article'=>'知识库 / 编辑文章','Knowledge Base / Article'=>'知识库 / 文章',
        'Linked to Category Management.'=>'已关联分类管理。','Current Attachments'=>'当前附件','Current Attachment'=>'当前附件','Save Changes'=>'保存修改',
        'read report'=>'阅读报告','Read Report'=>'阅读报告','Check which branch / user has read company announcements.'=>'查看哪些分行 / 用户已阅读公司公告。',
        'Select Announcement'=>'选择公告','User Count'=>'用户数','Read Count'=>'已读数','Unread Count'=>'未读数',
        'Open only when needed'=>'需要时才打开','only when needed'=>'需要时才打开','Search ticket no, title, description, PIC, assignee...'=>'搜索工单号、标题、描述、负责人、指派人...',
        'Asset Photo'=>'资产照片','Remove current photo'=>'移除当前照片','Upload new photo to replace current photo. Maximum 5MB.'=>'上传新照片以替换当前照片。最大 5MB。',
        'Desktop table can scroll horizontally if needed.'=>'桌面表格需要时可横向滚动。','No related tickets found for this asset.'=>'此资产暂无关联工单。',
        'How to reset GRN document'=>'如何重置GRN文档','How to Reset GRN document'=>'如何重置GRN文档'
    ];
    $ms = array_merge($ms, $circle2_ms);
    $zh = array_merge($zh, $circle2_zh);


    // FINAL fullscan UI translation patch 2026-06-25
    $final_ms = [
        'Release to refresh'=>'Lepaskan untuk segar semula','Pull down to refresh'=>'Tarik turun untuk segar semula','Refreshing...'=>'Sedang segar semula...',
        'Show Summary'=>'Tunjuk Ringkasan','Hide Summary'=>'Sembunyikan Ringkasan','Open All'=>'Buka Semua','Close All'=>'Tutup Semua','Open'=>'Buka','Show'=>'Tunjuk','Created'=>'Dicipta','Created '=>'Dicipta ',
        'Submit Reply'=>'Hantar Balasan','Reply History'=>'Sejarah Balasan','No replies yet.'=>'Belum ada balasan.','No timeline records found.'=>'Tiada rekod garis masa dijumpai.','No asset linked.'=>'Tiada aset dipautkan.',
        'Ticket Created'=>'Tiket Dicipta','Ticket Timeline'=>'Garis Masa Tiket','Quick Actions'=>'Tindakan Pantas','One Click Confirm'=>'Sahkan Sekali Klik',
        'Notifications'=>'Notifikasi','Notification'=>'Notifikasi','System notifications linked with tickets, announcements, knowledge base and status changes.'=>'Notifikasi sistem dipautkan dengan tiket, pengumuman, pangkalan pengetahuan dan perubahan status.',
        'All'=>'Semua','Unread'=>'Belum Dibaca','Ticket'=>'Tiket','Announcement'=>'Pengumuman','KB'=>'KB','READ'=>'DIBACA','UNREAD'=>'BELUM DIBACA','Mark All Read'=>'Tanda Semua Dibaca','No notifications found.'=>'Tiada notifikasi dijumpai.',
        'New Ticket Assigned'=>'Tiket Baharu Ditugaskan','Ticket Assigned To You'=>'Tiket Ditugaskan Kepada Anda','Assigned'=>'Ditugaskan','Assigned:'=>'Ditugaskan:','Assigned by'=>'Ditugaskan oleh','Title:'=>'Tajuk:','Branch:'=>'Cawangan:','Priority:'=>'Keutamaan:',
        'Person In Charge'=>'PIC','PIC'=>'PIC','Assigned To'=>'Ditugaskan Kepada','Assignee'=>'Penerima Tugas','Created At'=>'Dicipta Pada','Created Time'=>'Masa Dicipta','Last Update'=>'Kemas Kini Terakhir','Last Updated'=>'Kemas Kini Terakhir','Last Updated By'=>'Dikemas Kini Oleh',
        'View Article'=>'Lihat Artikel','Edit Article'=>'Edit Artikel','Add Article'=>'Tambah Artikel','Article Content'=>'Kandungan Artikel','Article Info'=>'Maklumat Artikel','Current Attachments'=>'Lampiran Semasa','Add More Attachments'=>'Tambah Lampiran Lagi','Save Changes'=>'Simpan Perubahan','Save Article'=>'Simpan Artikel','Linked to Category Management.'=>'Dipautkan kepada Pengurusan Kategori.',
        'View Announcement'=>'Lihat Pengumuman','Announcement Read Report'=>'Laporan Bacaan Pengumuman','Announcement Read Status'=>'Status Bacaan Pengumuman','Read Report'=>'Laporan Bacaan','Check which branch / user has read company announcements.'=>'Semak cawangan / pengguna yang telah membaca pengumuman syarikat.',
        'Asset History'=>'Sejarah Aset','Related Tickets'=>'Tiket Berkaitan','No related tickets found for this asset.'=>'Tiada tiket berkaitan untuk aset ini.','Desktop table can scroll horizontally if needed.'=>'Jadual desktop boleh ditatal secara mendatar jika perlu.','Edit Asset'=>'Edit Aset','Update Asset'=>'Kemas Kini Aset','Remove current photo'=>'Buang foto semasa','Upload new photo to replace current photo. Maximum 5MB.'=>'Muat naik foto baharu untuk menggantikan foto semasa. Maksimum 5MB.',
        'Asset Type'=>'Jenis Aset','Asset Type Name'=>'Nama Jenis Aset','POS'=>'POS','Printer'=>'Pencetak','Barcode Printer'=>'Pencetak Barcode','Scanner'=>'Pengimbas','PC'=>'PC','CCTV'=>'CCTV','Cash Drawer'=>'Laci Tunai','Barcode Scanner'=>'Pengimbas Barcode','Weighing Scale'=>'Penimbang',
        'Active'=>'Aktif','Repair'=>'Baik Pulih','Inactive'=>'Tidak Aktif','Disposed'=>'Dilupuskan','No photo selected'=>'Tiada foto dipilih','Photo Preview'=>'Pratonton Foto','Save Asset'=>'Simpan Aset','Back to Asset List'=>'Kembali ke Senarai Aset',
        'Open only when needed'=>'Buka hanya bila perlu','Open filter only when needed'=>'Buka tapisan hanya bila perlu','Active filter'=>'Tapisan aktif','Search ticket no, title, description, person in charge, assignee...'=>'Cari no tiket, tajuk, penerangan, PIC, penerima tugas...',
        'Knowledge'=>'Pengetahuan','Knowledge Base'=>'Pangkalan Pengetahuan','Knowledge Base / View Article'=>'Pangkalan Pengetahuan / Lihat Artikel','Knowledge Base / Edit Article'=>'Pangkalan Pengetahuan / Edit Artikel','Knowledge Base / Add Article'=>'Pangkalan Pengetahuan / Tambah Artikel'
    ];
    $final_zh = [
        'Release to refresh'=>'松开刷新','Pull down to refresh'=>'下拉刷新','Refreshing...'=>'刷新中...',
        'Show Summary'=>'显示汇总','Hide Summary'=>'隐藏汇总','Open All'=>'展开全部','Close All'=>'收起全部','Open'=>'展开','Show'=>'显示','Created'=>'创建时间','Created '=>'创建时间 ',
        'Submit Reply'=>'提交回复','Reply History'=>'回复历史','No replies yet.'=>'暂无回复。','No timeline records found.'=>'没有时间线记录。','No asset linked.'=>'没有关联资产。',
        'Ticket Created'=>'工单已创建','Ticket Timeline'=>'工单时间线','Quick Actions'=>'快捷操作','One Click Confirm'=>'一键确认',
        'Notifications'=>'通知','Notification'=>'通知','System notifications linked with tickets, announcements, knowledge base and status changes.'=>'系统通知会连接工单、公告、知识库和状态变化。',
        'All'=>'全部','Unread'=>'未读','Ticket'=>'工单','Announcement'=>'公告','KB'=>'KB','READ'=>'已读','UNREAD'=>'未读','Mark All Read'=>'全部标记已读','No notifications found.'=>'没有通知。',
        'New Ticket Assigned'=>'新工单已指派','Ticket Assigned To You'=>'工单已指派给你','Assigned'=>'已指派','Assigned:'=>'已指派：','Assigned by'=>'指派人','Title:'=>'标题：','Branch:'=>'分行：','Priority:'=>'优先级：',
        'Person In Charge'=>'PIC','PIC'=>'PIC','Assigned To'=>'指派给','Assignee'=>'负责人','Created At'=>'创建时间','Created Time'=>'创建时间','Last Update'=>'最后更新','Last Updated'=>'最后更新','Last Updated By'=>'最后更新者',
        'View Article'=>'查看文章','Edit Article'=>'编辑文章','Add Article'=>'添加文章','Article Content'=>'文章内容','Article Info'=>'文章信息','Current Attachments'=>'当前附件','Add More Attachments'=>'添加更多附件','Save Changes'=>'保存修改','Save Article'=>'保存文章','Linked to Category Management.'=>'已联动分类管理。',
        'View Announcement'=>'查看公告','Announcement Read Report'=>'公告阅读报告','Announcement Read Status'=>'公告阅读状态','Read Report'=>'阅读报告','Check which branch / user has read company announcements.'=>'查看哪些分行 / 用户已阅读公司公告。',
        'Asset History'=>'资产历史','Related Tickets'=>'关联工单','No related tickets found for this asset.'=>'此资产暂无关联工单。','Desktop table can scroll horizontally if needed.'=>'桌面表格需要时可横向滚动。','Edit Asset'=>'编辑资产','Update Asset'=>'更新资产','Remove current photo'=>'移除当前照片','Upload new photo to replace current photo. Maximum 5MB.'=>'上传新照片以替换当前照片。最大 5MB。',
        'Asset Type'=>'资产类型','Asset Type Name'=>'资产类型名称','POS'=>'POS','Printer'=>'打印机','Barcode Printer'=>'条码打印机','Scanner'=>'扫描器','PC'=>'PC','CCTV'=>'CCTV','Cash Drawer'=>'钱箱','Barcode Scanner'=>'条码扫描器','Weighing Scale'=>'电子秤',
        'Active'=>'启用','Repair'=>'维修','Inactive'=>'停用','Disposed'=>'已报废','No photo selected'=>'未选择照片','Photo Preview'=>'照片预览','Save Asset'=>'保存资产','Back to Asset List'=>'返回资产列表',
        'Open only when needed'=>'需要时才打开','Open filter only when needed'=>'需要时才打开筛选','Active filter'=>'筛选已启用','Search ticket no, title, description, person in charge, assignee...'=>'搜索工单号、标题、描述、负责人、指派给...',
        'Knowledge'=>'知识库','Knowledge Base'=>'知识库','Knowledge Base / View Article'=>'知识库 / 查看文章','Knowledge Base / Edit Article'=>'知识库 / 编辑文章','Knowledge Base / Add Article'=>'知识库 / 添加文章',
        'Update asset details and equipment photo.'=>'更新资产资料及设备照片。',
        'Linked Tickets'=>'关联工单',
        'Select Person In Charge'=>'选择负责人'
    ];
    $final_ms = array_merge($final_ms, [
        'Update asset details and equipment photo.'=>'Kemas kini maklumat aset dan gambar peralatan.',
        'Linked Tickets'=>'Tiket Berkaitan',
        'Select Person In Charge'=>'Pilih Pegawai Bertanggungjawab'
    ]);

    $ms = array_merge($ms, $final_ms);
    $zh = array_merge($zh, $final_zh);


    // Circle-only UI label language fixes (display text only; no database/user values changed).
    $ms = array_merge($ms, [
        'Top Branch' => 'Cawangan Teratas',
        'Top Person In Charge' => 'Pegawai Bertanggungjawab Teratas',
        'Top Categories' => 'Kategori Teratas',
        'Top Knowledge Articles' => 'Artikel Pengetahuan Teratas',
        'Top' => 'Teratas',
        'Hold Voice' => 'Tahan Suara',
        'Attachment Uploaded' => 'Lampiran Dimuat Naik',
        'Reply Attachment Uploaded' => 'Lampiran Balasan Dimuat Naik',
        'Reply Added' => 'Balasan Ditambah',
        'Ticket Created' => 'Tiket Dicipta',
        '工单已创建' => 'Tiket Dicipta',
        '工單已創建' => 'Tiket Dicipta',
        '回复 Added' => 'Balasan Ditambah',
        '回复已添加' => 'Balasan Ditambah',
        '回复附件已上传' => 'Lampiran Balasan Dimuat Naik',
        '附件已上传' => 'Lampiran Dimuat Naik',
        'Uploaded' => 'Dimuat Naik',
        'Allowed: multiple photos, videos, voice/audio, PDF, Word and Excel. Max 50MB each.' => 'Dibenarkan: banyak foto, video, suara/audio, PDF, Word dan Excel. Maksimum 50MB setiap fail.',
        'KPI Person In Charge Summary' => 'Ringkasan Pegawai Bertanggungjawab',
        'KPI Person In Charge' => 'Pegawai Bertanggungjawab',
        'PIC Summary' => 'Ringkasan Pegawai Bertanggungjawab',
        'PIC Management' => 'Pengurusan Pegawai Bertanggungjawab',
        'Add PIC' => 'Tambah Pegawai Bertanggungjawab',
        'PIC Name' => 'Nama Pegawai Bertanggungjawab',
        'PIC List' => 'Senarai Pegawai Bertanggungjawab',
        'PIC' => 'PIC',
        'Maintain Knowledge Base categories used by Add Article and Edit Article.' => 'Urus kategori Pangkalan Pengetahuan yang digunakan oleh Tambah Artikel dan Edit Artikel.',
        'Add Article and Edit Article automatically use enabled categories from this list. Used categories cannot be hard-deleted; they will be disabled instead.' => 'Tambah Artikel dan Edit Artikel akan menggunakan kategori aktif daripada senarai ini secara automatik. Kategori yang telah digunakan tidak boleh dipadam terus; ia akan dinyahaktifkan.'
    ]);
    $zh = array_merge($zh, [
        'Top Branch' => '热门分行',
        'Top Person In Charge' => '热门负责人',
        'Top Categories' => '热门分类',
        'Top Knowledge Articles' => '热门知识文章',
        'Top' => '热门',
        'Hold Voice' => '按住语音',
        'Attachment Uploaded' => '附件已上传',
        'Reply Attachment Uploaded' => '回复附件已上传',
        'Reply Added' => '回复已添加',
        'Ticket Created' => '工单已创建',
        '工单已创建' => '工单已创建',
        '工單已創建' => '工单已创建',
        '回复 Added' => '回复已添加',
        '回复已添加' => '回复已添加',
        '回复附件已上传' => '回复附件已上传',
        '附件已上传' => '附件已上传',
        'Uploaded' => '已上传',
        'Allowed: multiple photos, videos, voice/audio, PDF, Word and Excel. Max 50MB each.' => '允许：多张照片、视频、语音/音频、PDF、Word 和 Excel。每个最大 50MB。',
        'KPI Person In Charge Summary' => '负责人汇总',
        'KPI Person In Charge' => '负责人',
        'PIC Summary' => '负责人汇总',
        'PIC Management' => '负责人管理',
        'Add PIC' => '添加负责人',
        'PIC Name' => '负责人名称',
        'PIC List' => '负责人列表',
        'PIC' => 'PIC',
        'Maintain Knowledge Base categories used by Add Article and Edit Article.' => '维护添加文章和编辑文章使用的知识库分类。',
        'Add Article and Edit Article automatically use enabled categories from this list. Used categories cannot be hard-deleted; they will be disabled instead.' => '添加文章和编辑文章会自动使用此列表中的启用分类。已使用的分类不能硬删除，将改为停用。'
    ]);

    $dict = ['ms'=>$ms,'zh'=>$zh];
    return $dict;
}
function hd_translate_text_segment($segment){
    if(hd_lang()==='en' || trim($segment)==='') return $segment;

    // Protect branch codes and ticket numbers before global UI text replacement.
    $branchProtected = [];
    $segment = preg_replace_callback('/\b(?:HQ|KB|KC|KJ|KK|KL|KR|KS|ML|PC|PJ|PKL|PM|SE|TM|TPC|TPN|TPT|WC|WK|BS|LAS)(?:-?\d{6}-\d+)?\b/u', function($m) use (&$branchProtected){
        $key = '%%HD_BRANCH_RAW_'.count($branchProtected).'%%';
        $branchProtected[$key] = $m[0];
        return $key;
    }, $segment);
    $segment = preg_replace_callback('/(?:电脑|電腦|Computer|Komputer)-?\d{6}-\d+/u', function($m) use (&$branchProtected){
        $key = '%%HD_BRANCH_RAW_'.count($branchProtected).'%%';
        $branchProtected[$key] = hd_branch_code_raw($m[0]);
        return $key;
    }, $segment);

    $dict = hd_translation_dict()[hd_lang()] ?? [];
    uksort($dict, function($a,$b){ return mb_strlen($b) <=> mb_strlen($a); });
    foreach($dict as $from=>$to){
        if($from === '' || $from === $to) continue;
        if(in_array($from, ['HQ','KB','KC','KJ','KK','KL','KR','KS','ML','PC','PJ','PKL','PM','SE','TM','TPC','TPN','TPT','WC','WK','BS','LAS'], true)) continue;
        // Use exact phrase replacement. For single words, require word boundaries to avoid corrupting user/data text
        // such as Maintenance -> 主页tenance. For phrases containing CJK or punctuation, replace exact text only.
        if(preg_match('/^[A-Za-z0-9_ \/-]+$/u', $from)){
            $pattern = '/(?<![A-Za-z0-9_])'.preg_quote($from, '/').'(?![A-Za-z0-9_])/u';
            $segment = preg_replace($pattern, $to, $segment);
        } else {
            $segment = str_replace($from, $to, $segment);
        }
    }
    if(!empty($branchProtected)) $segment = strtr($segment, $branchProtected);
    return $segment;
}
function hd_translate_html_attributes($tag){
    if(hd_lang()==='en' || $tag==='') return $tag;
    // Translate visible/helpful attributes only. Keep normal value="..." unchanged to avoid breaking POST validation.
    $attrs = ['placeholder','title','alt','aria-label'];
    foreach($attrs as $attr){
        $tag = preg_replace_callback('/\b'.preg_quote($attr,'/').'\s*=\s*(["\'])(.*?)\1/isu', function($m) use ($attr){
            return $attr.'='.$m[1].hd_translate_text_segment($m[2]).$m[1];
        }, $tag);
    }
    // Translate input button labels safely without touching select/hidden/form values.
    if(preg_match('/^<input\b/i', $tag) && preg_match('/\btype\s*=\s*(["\']?)(submit|button|reset)\1/i', $tag)){
        $tag = preg_replace_callback('/\bvalue\s*=\s*(["\'])(.*?)\1/isu', function($m){
            return 'value='.$m[1].hd_translate_text_segment($m[2]).$m[1];
        }, $tag);
    }
    return $tag;
}
function hd_translate_buffer($html){
    if(hd_lang()==='en' || $html==='') return $html;

    // Protect user/database content from the global UI translator.
    // Without this, words inside announcements such as "Maintenance" can be partially changed.
    $protected = [];
    $html = preg_replace_callback('/<(?P<tag>span|div|p|h[1-6]|label|option|td|th|a|button|select)\b(?=[^>]*(?:\bhd-no-translate\b|\bnotranslate\b|translate\s*=\s*["\']no["\']))[^>]*>.*?<\/(?P=tag)>/isu', function($m) use (&$protected){
        $key = '%%HD_NOTRANS_'.count($protected).'%%';
        $protected[$key] = $m[0];
        return $key;
    }, $html);

    $parts = preg_split('/(<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>|<[^>]+>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach($parts as &$part){
        if($part === '') continue;
        if($part[0] === '<'){
            if(!preg_match('/^<\/?(?:script|style)\b/i', $part)){
                $part = hd_translate_html_attributes($part);
            }
            continue;
        }
        $part = hd_translate_text_segment($part);
    }
    $html = implode('', $parts);
    if($protected){ $html = strtr($html, $protected); }
    return $html;
}

function hd_auto_translate_announcement($text){
    // Safe built-in auto translation for announcement title/content.
    // It does not use blind substring replacement, so user content will not become broken text.
    $lang = hd_lang();
    if($lang === 'en' || trim((string)$text)==='') return (string)$text;
    $map = [
        'zh' => [
            'announcement'=>'公告','material announcement'=>'重大公告','maintenance'=>'维护','server maintenance'=>'服务器维护','POS Server'=>'POS服务器','POS Maintenance'=>'POS维护','opened announcement will be automatically marked as read'=>'打开公告后会自动标记为已读','This announcement has been marked as read automatically'=>'此公告已自动标记为已读','Created at'=>'创建时间','Posted by'=>'发布者','Read Rate'=>'阅读率','Read Status'=>'阅读状态','Open / Download'=>'打开 / 下载','Start Date'=>'开始日期','End Date'=>'结束日期','Active'=>'启用','Inactive'=>'停用','Read'=>'已读','Unread'=>'未读','Back'=>'返回','KUALA LUMPUR'=>'吉隆坡','shares'=>'股票','trading suspension'=>'暂停交易','pending the release'=>'等待发布','Bursa Malaysia'=>'马来西亚交易所','material'=>'重大','market capitalisation'=>'市值','about'=>'约','closed'=>'收盘','lower'=>'下跌','year-to-date'=>'今年至今'
        ],
        'ms' => [
            'announcement'=>'pengumuman','material announcement'=>'pengumuman penting','maintenance'=>'penyelenggaraan','server maintenance'=>'penyelenggaraan server','POS Server'=>'Server POS','POS Maintenance'=>'Penyelenggaraan POS','opened announcement will be automatically marked as read'=>'Pengumuman yang dibuka akan ditanda sebagai dibaca secara automatik','This announcement has been marked as read automatically'=>'Pengumuman ini telah ditanda sebagai dibaca secara automatik','Created at'=>'Dicipta pada','Posted by'=>'Dihantar oleh','Read Rate'=>'Kadar bacaan','Read Status'=>'Status bacaan','Open / Download'=>'Buka / Muat Turun','Start Date'=>'Tarikh Mula','End Date'=>'Tarikh Tamat','Active'=>'Aktif','Inactive'=>'Tidak Aktif','Read'=>'Dibaca','Unread'=>'Belum Dibaca','Back'=>'Kembali','KUALA LUMPUR'=>'KUALA LUMPUR','shares'=>'saham','trading suspension'=>'penggantungan dagangan','pending the release'=>'menunggu pengumuman','Bursa Malaysia'=>'Bursa Malaysia','material'=>'penting','market capitalisation'=>'modal pasaran','about'=>'kira-kira','closed'=>'ditutup','lower'=>'lebih rendah','year-to-date'=>'sejak awal tahun'
        ]
    ];
    $dict = $map[$lang] ?? [];
    uksort($dict, function($a,$b){ return mb_strlen($b) <=> mb_strlen($a); });
    $out = (string)$text;
    foreach($dict as $from=>$to){
        $out = preg_replace('/(?<![\p{L}\p{N}])'.preg_quote($from,'/').'(?![\p{L}\p{N}])/iu', $to, $out);
    }
    return $out;
}
?>
