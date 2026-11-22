<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
require_once dirname(__DIR__).'/bootstrap/stays.php';
$kind=in_array($_GET['type'] ?? '',['inspection','areas','extras'],true)?$_GET['type']:'cleaning';$checklistTitle=match($kind){'inspection'=>'Inspection Checklist','areas'=>'Audit Areas','extras'=>'Extra Categories',default=>'Cleaning Checklist'};
$actor=(int)$user['id'];$error='';$list=null;
$notice=$_SESSION['cleaning_settings_notice'] ?? '';unset($_SESSION['cleaning_settings_notice']);
try{
    $service=stay_service();$service->installed();
    if(!access_service()->repo->isAdmin($actor))throw new \Tivoli\Security\PermissionDenied('Only administrators can update these settings.');
    access_service()->requirePrivilege($actor,$kind==='extras'?'extras.manage':'rooms.manage');
    if($_SERVER['REQUEST_METHOD']==='POST'){
        access_check_csrf();foreach($_POST as $v)if(!is_string($v))throw new RuntimeException('Invalid settings input.');
        $service->execute($actor,match($kind){'areas'=>'area_catalogue','extras'=>'extra_categories',default=>$kind.'_checklist'},$_POST,$_POST['operation_key'] ?? '');
        $_SESSION['cleaning_settings_notice']=match($kind){'areas'=>'Area list updated. Existing audits retain their recorded names.','extras'=>'Extra categories updated.',default=>'Checklist updated. Completed records retain their original checklist.'};
        header('Location: '.access_url('cleaning-settings.php?type='.$kind).'#cleaning-settings');exit;
    }
    $list=match($kind){
        'areas'=>\Tivoli\Service\AuditAreas::current($service->repo),
        'extras'=>\Tivoli\Service\ExtraCategories::current($service->repo),
        default=>\Tivoli\Service\CleaningChecklist::current($service->repo,$kind),
    };
}catch(Throwable $e){$error=access_error($e);http_response_code($e instanceof PDOException?503:($e instanceof \Tivoli\Security\PermissionDenied?403:400));}
$layoutTitle=$checklistTitle.' Settings';require dirname(__DIR__).match($kind){
    'areas'=>'/templates/stays/area-settings.php','extras'=>'/templates/stays/extra-category-settings.php',
    default=>'/templates/stays/cleaning-settings.php',
};
