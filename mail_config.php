<?php

/*
|--------------------------------------------------------------------------
| Gmail SMTP Configuration
|--------------------------------------------------------------------------
| IMPORTANT:
| Do not paste this file into chat after filling password.
| Use Gmail App Password, not your normal Gmail password.
*/

return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',

    // Fill in your Gmail address
    'smtp_username' => 'wlsentpayslip@gmail.com',

    // Fill in your NEW Gmail App Password without spaces
    // Example format: abcdefghijklmnop
    'smtp_password' => 'nirvfzmiqrpotezz',

    'from_email' => 'wlsentpayslip@gmail.com',
    'from_name' => 'Helpdesk System',

    // Default notification email. Put your admin / company email here.
    // If user email is empty, system will send notification here.
    'default_notify_email' => 'wlsentpayslip@gmail.com',

    // Set false if you want to temporarily disable email notification.
    'enabled' => true,
];
