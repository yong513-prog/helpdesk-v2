<?php
require 'db.php';
require_once 'ticket_attachment_helper.php';
hd_ta_ensure_table($pdo);
echo 'ticket_attachments table ready.';
?>
