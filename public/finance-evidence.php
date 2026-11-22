<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
try{
 if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');http_response_code(405);exit('Method not allowed.');}
 $id=filter_var($_GET['id']??'',FILTER_VALIDATE_INT);$kind=$_GET['kind']??'';if(!$id||$id<1||!is_string($kind)){http_response_code(400);exit('Invalid evidence request.');}
 $f=\Tivoli\Service\FinanceAttachment::download(access_service(),(int)$user['id'],$kind,$id);
 header('Content-Type: '.$f['mime']);header('Content-Disposition: attachment; filename="'.$f['filename'].'"');header('Content-Security-Policy: sandbox');header('X-Content-Type-Options: nosniff');header('Cache-Control: no-store, private');header('Content-Length: '.strlen($f['bytes']));echo $f['bytes'];
}catch(Throwable $e){http_response_code($e instanceof \Tivoli\Security\PermissionDenied?403:404);exit('Evidence is unavailable.');}
