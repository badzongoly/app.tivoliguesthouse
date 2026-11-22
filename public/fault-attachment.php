<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
require_once dirname(__DIR__).'/bootstrap/hr.php';
try{
    if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');http_response_code(405);exit('Method not allowed.');}
    $id=filter_var($_GET['fault_id'] ?? '',FILTER_VALIDATE_INT);
    if(!$id||$id<1){http_response_code(400);exit('Invalid fault attachment request.');}
    $kind=$_GET['kind'] ?? 'fault';if(!in_array($kind,['fault','repair'],true)){http_response_code(400);exit('Invalid evidence request.');}
    $file=\Tivoli\Service\FaultAttachment::download(access_service(),(int)$user['id'],$id,$kind);
    header('Content-Type: '.$file['mime']);header('Content-Disposition: attachment; filename="'.$file['filename'].'"');
    header('Content-Security-Policy: sandbox');header('X-Content-Type-Options: nosniff');header('Cache-Control: no-store, private');header('Content-Length: '.strlen($file['bytes']));echo $file['bytes'];
}catch(Throwable $e){http_response_code($e instanceof \Tivoli\Security\PermissionDenied?403:503);exit('Fault attachment is unavailable.');}
