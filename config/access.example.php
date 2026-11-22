<?php
return [
    'dsn'=>'mysql:host=127.0.0.1;port=3306;dbname=YOUR_DATABASE;charset=utf8mb4',
    'username'=>'YOUR_DATABASE_USER',
    'password'=>'YOUR_DATABASE_PASSWORD',
    'audit_key'=>'GENERATE_A_RANDOM_32_BYTE_HEX_SECRET',
    'base_url'=>'http://localhost/app.tivoliguesthouse',
    'reset_delivery'=>'smtp',
    'smtp'=>[
        'host'=>'YOUR_SMTP_HOST','port'=>587,'encryption'=>'tls',
        'username'=>'YOUR_SMTP_USERNAME','password'=>'YOUR_SMTP_PASSWORD',
        'from_email'=>'YOUR_VERIFIED_SENDER_EMAIL','from_name'=>'Tivoli Guesthouse',
    ],
];
