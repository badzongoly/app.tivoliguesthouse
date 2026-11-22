<?php
// Copy to db.php locally and provide your own database configuration.
$mysqli = new mysqli('127.0.0.1', 'YOUR_DATABASE_USER', 'YOUR_DATABASE_PASSWORD', 'YOUR_DATABASE_NAME', 3306);
if ($mysqli->connect_errno) { throw new RuntimeException('Database connection failed'); }
$mysqli->set_charset('utf8mb4');
$mysqli->query("SET time_zone = '+00:00'");
date_default_timezone_set('UTC');