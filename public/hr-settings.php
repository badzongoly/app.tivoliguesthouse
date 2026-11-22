<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';require_once dirname(__DIR__).'/bootstrap/hr.php';
$actor=(int)$user['id'];$error='';$notice=$_SESSION['hr_settings_notice'] ?? '';unset($_SESSION['hr_settings_notice']);$catalogue=['document'=>[],'momo'=>[],'bank'=>[]];
$section=($_GET['section'] ?? '')==='documents'?'documents':'payments';
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){access_check_csrf();foreach($_POST as $v){if(!is_string($v))throw new RuntimeException('Invalid settings input.');}hr_private()->saveCatalogue($actor,$_POST);$_SESSION['hr_settings_notice']='Settings saved. Existing document and staff references are retained.';header('Location: '.access_url('hr-settings.php?section='.$section));exit;}
    $catalogue=hr_private()->catalogue($actor);
}catch(Throwable $e){$error=access_error($e);http_response_code($e instanceof PDOException?503:($e instanceof \Tivoli\Security\PermissionDenied?403:400));try{access_service()->audit($actor,'hr.settings_rejected','settings',null,'denied',['reason'=>'validation_permission_or_storage_failure']);}catch(Throwable $auditError){http_response_code(503);$error='HR settings are temporarily unavailable.';}}
$layoutTitle='HR settings';$layoutPage='hr';require dirname(__DIR__).'/templates/hr/settings.php';
