<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';require_once dirname(__DIR__).'/bootstrap/hr.php';
try{
    if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit('Method not allowed.');}
    $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT);if(!$id)throw new RuntimeException('Invalid document identifier.');
    $document=hr_private()->download((int)$user['id'],$id);
    header('Content-Type: '.$document['mime']);header('Content-Disposition: attachment; filename="document-'.$id.'"; filename*=UTF-8\'\''.rawurlencode($document['filename']));
    header('Content-Security-Policy: sandbox');header('Content-Length: '.strlen($document['bytes']));echo $document['bytes'];
}catch(Throwable $e){http_response_code($e instanceof \Tivoli\Security\PermissionDenied?403:503);exit('Document is unavailable.');}
