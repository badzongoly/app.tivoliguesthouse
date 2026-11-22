<?php
// Optional 'notifications' section in protected access configuration. Never commit actual credentials.
// No values here activate a service. The deployed base_url must match public_url and use HTTPS.
return [
    'public_url'=>'',
    'email_enabled'=>false, // Uses the existing protected smtp configuration and PHPMailer.
    'whatsapp_enabled'=>false,
    'production_worker_enabled'=>false,
    'approved_dsn_hash'=>'', // Set only during separately authorized production setup.
    'whatsapp'=>[
        'version'=>'', // Select a currently supported official Cloud API version during setup.
        'sender_id'=>'',
        'access_token'=>'',
        'app_secret'=>'',
        'verify_token'=>'',
        'template'=>'', // Approved generic zero-parameter operational notice with a secure sign-in link.
        'language'=>'en',
    ],
];
