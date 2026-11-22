<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
require_once dirname(__DIR__).'/bootstrap/stay-identity.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        header('Allow: GET');
        http_response_code(405);
        exit('Method not allowed.');
    }
    $id = filter_var($_GET['booking_id'] ?? $_GET['id'] ?? '', FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        http_response_code(400);
        exit('Invalid document request.');
    }
    $document = stay_download_identity((int)$user['id'], $id);
    header('Content-Type: '.$document['mime']);
    header('Content-Disposition: attachment; filename="'.$document['filename'].'"');
    header('Content-Security-Policy: sandbox');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, private');
    header('Content-Length: '.strlen($document['bytes']));
    echo $document['bytes'];
} catch (Throwable $error) {
    http_response_code($error instanceof \Tivoli\Security\PermissionDenied ? 403 : 503);
    exit('Guest identity document is unavailable.');
}
