<?php
require_once dirname(__DIR__).'/bootstrap/security.php';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');if(getenv('TIVOLI_TEST_MODE')==='1')header('X-Tivoli-Test-Mode: synthetic');
try{
    $config=access_config();$wa=$config['notifications']['whatsapp']??[];
    if($_SERVER['REQUEST_METHOD']==='GET'){
        $token=$wa['verify_token']??'';if(!is_string($token)||$token===''||($_GET['hub_mode']??'')!=='subscribe'||!is_string($_GET['hub_verify_token']??null)||!hash_equals($token,$_GET['hub_verify_token'])||!is_string($_GET['hub_challenge']??null)||!preg_match('/^[a-zA-Z0-9_-]{1,200}$/D',$_GET['hub_challenge']))throw new \Tivoli\Security\PermissionDenied('Invalid verification request.');header('Content-Type: text/plain; charset=utf-8');echo $_GET['hub_challenge'];exit;
    }
    if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: GET, POST');http_response_code(405);exit('Unsupported delivery receipt.');}
    $body=file_get_contents('php://input',false,null,0,100001);if(!is_string($body)||strlen($body)>100000)throw new RuntimeException('Invalid delivery receipt.');$signature=$_SERVER['HTTP_X_HUB_SIGNATURE_256']??'';(new \Tivoli\Service\ReminderService(access_service(),null,$config))->receiveStatus($body,$signature);header('Content-Type: application/json');echo '{"received":true}';
}catch(Throwable $e){http_response_code($e instanceof \Tivoli\Security\PermissionDenied?403:($e instanceof PDOException?503:400));echo 'Delivery receipt unavailable.';}
