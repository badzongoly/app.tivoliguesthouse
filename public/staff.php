<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
require_once dirname(__DIR__).'/bootstrap/hr.php';
$actor=(int)$user['id'];$hr=hr_service();$can=static fn(string $p):bool=>$hr->can($actor,$p);
$id=filter_var($_GET['id'] ?? '0',FILTER_VALIDATE_INT);if($id===false||$id<0){http_response_code(400);exit('Invalid staff identifier.');}
$tab=is_string($_GET['tab'] ?? null)?$_GET['tab']:'overview';if(!in_array($tab,['overview','employment','salary','leave','documents','access','activity'],true))$tab='overview';
$restoreDraft=false;$editing=isset($_GET['new'])||isset($_GET['edit']);$error='';$notice=$_SESSION['hr_notice'] ?? '';unset($_SESSION['hr_notice']);$directory=[];$snapshot=null;$catalogue=['document'=>[],'momo'=>[],'bank'=>[]];$documents=[];$salary=null;$candidates=[];
try {
    if($editing&&!$can('hr.manage'))throw new \Tivoli\Security\PermissionDenied('You cannot edit staff records.');
    if($_SERVER['REQUEST_METHOD']==='POST'){
        access_check_csrf();foreach($_POST as $v){if(!is_string($v))throw new RuntimeException('Invalid form input.');}
        $input=$_POST;$target=filter_var($input['id'] ?? '',FILTER_VALIDATE_INT);if($target===false||$target<0)throw new RuntimeException('Invalid staff identifier.');
        $action=$input['action'] ?? '';
        if($action==='upload_document'){$hr->snapshot($actor,$target);hr_private()->upload($actor,$target,(int)($input['type_id'] ?? 0),$_FILES['document'] ?? []);$tab='documents';$saved=$target;}
        elseif($action==='save_salary'){$hr->snapshot($actor,$target);hr_private()->saveSalary($actor,$target,$input);$tab='salary';$saved=$target;}
        else{$saved=$hr->command($actor,$action,$input);$tab=match($action){'request_leave','review_leave'=>'leave','link_user','new_account'=>'access','issue_item','return_item'=>'overview','offboard','suspend','resume','confirm_probation'=>'employment',default=>'overview'};}
        $_SESSION['hr_notice']=match($action){'new_account'=>'Staff account created; password setup invitation queued for delivery.','link_user'=>'Existing account linked. Its sign-in status, password, roles and history were retained.','save_salary'=>'Salary revision saved. Earlier revisions are retained.','upload_document'=>'Document saved privately and recorded in the activity trail.',default=>'Changes saved and recorded in the activity trail.'};
        // Supporting uploads are independent, optional audited operations; profile success is retained if a file fails.
        if(in_array($action,['create','save_profile'],true)&&$can('hr.documents')){
            foreach(['contract','identity'] as $kind){$file=$_FILES[$kind.'_file'] ?? null;if($file&&($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){try{foreach(hr_uploaded_entries($file) as $entry){if(($entry['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)hr_private()->upload($actor,$saved,(int)($input[$kind.'_type_id'] ?? 0),$entry);}}catch(Throwable $attachmentError){$_SESSION['hr_notice']='Staff details saved. A supporting document was not saved; add it from Documents.';}}}
        }
        header('Location: '.access_url('staff.php?id='.$saved.'&tab='.$tab));exit;
    }
    $directory=$hr->directory($actor);
    if($id>0)$snapshot=$hr->snapshot($actor,$id);
    if($can('hr.documents')||$can('hr.salary')||$can('hr.settings'))$catalogue=hr_private()->catalogue($actor);
    if($snapshot){
        if($tab==='documents'){if(!$can('hr.documents'))throw new \Tivoli\Security\PermissionDenied('You cannot access staff documents.');$documents=hr_private()->documents($actor,$id);}
        if($tab==='salary'){if(!$can('hr.salary'))throw new \Tivoli\Security\PermissionDenied('You cannot access salary details.');$salary=hr_private()->salary($actor,$id);}
        if($tab==='access'&&$can('hr.link'))$candidates=access_service()->repo->run('SELECT u.id,u.name,u.email,a.active,s.staff_no, GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ", ") AS roles FROM users u JOIN security_accounts a ON a.user_id=u.id LEFT JOIN hr_staff s ON s.user_id=u.id LEFT JOIN security_user_roles ur ON ur.user_id=u.id LEFT JOIN security_roles r ON r.id=ur.role_id GROUP BY u.id,u.name,u.email,a.active,s.staff_no ORDER BY u.name,u.id')->fetchAll();
    }
}catch(Throwable $e){$error=access_error($e);http_response_code($e instanceof PDOException?503:($e instanceof \Tivoli\Security\PermissionDenied||http_response_code()===403?403:400));if($_SERVER['REQUEST_METHOD']==='POST'){try{access_service()->audit($actor,'hr.form_rejected','staff',$id?(string)$id:null,'denied',['reason'=>'validation_permission_or_storage_failure']);}catch(Throwable $auditError){http_response_code(503);$error='Staff records are temporarily unavailable.';}}$documents=[];$salary=null;}
if($error&&http_response_code()===400&&$can('hr.manage')&&in_array($_POST['action'] ?? '',['create','save_profile'],true)){
    $restoreDraft=true;$editing=true;$draft=[];foreach(['name','preferred','email','phone','address','dob','job','department','supervisor','employment','start','probation','contact_name','relationship','contact_phone','notes','contract','identity','sop','safety','training'] as $key){$draft[$key]=is_string($_POST[$key] ?? null)?mb_substr($_POST[$key],0,2000):'';}
    $snapshot=['staff'=>['id'=>(int)($_POST['id'] ?? 0),'version'=>(int)($_POST['version'] ?? 0),'data'=>$draft]];
}
$layoutTitle='Staff & HR';$layoutPage='hr';require dirname(__DIR__).'/templates/hr/staff.php';
