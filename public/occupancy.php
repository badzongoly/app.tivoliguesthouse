<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
require_once dirname(__DIR__).'/bootstrap/stays.php';
$service=stay_service();$actor=(int)$user['id'];$isAdmin=access_service()->repo->isAdmin($actor);$canAuthorizeDeparture=(new \Tivoli\Service\FinanceService(access_service()))->canPostLate($actor);
$error='';$notice='';$confirmation=null;$departurePreview=null;$departureCases=[];$incidentAssignees=[];$view=[];$folioDirectory=['rows'=>[],'next_before'=>null,'filters'=>[]];$operationalActivity=['rows'=>[],'next_before'=>null];$areaAudits=['rows'=>[],'week'=>gmdate('Y-m-d'),'next_before'=>null];
$before=filter_var($_GET['before'] ?? '0',FILTER_VALIDATE_INT);$before=$before===false?0:max(0,$before);
$bookingId=filter_var($_GET['booking_id'] ?? '0',FILTER_VALIDATE_INT);
$bookingId=$bookingId===false?0:max(0,$bookingId);
$section=is_string($_GET['section'] ?? null)?$_GET['section']:'board';
if(!in_array($section,['board','extras','rooms','folios','activity','areas','departures'],true))$section='board';
$tab=is_string($_GET['tab'] ?? null)?$_GET['tab']:($section==='board'&&!$bookingId?'board':'overnight');
if(!in_array($tab,['board','overnight','short','recent','bill','payments','statement','activity','master','faults','audits','catalogue','stock'],true))$tab='overnight';
if($section==='board'&&$tab==='recent'){$section='folios';$tab='overnight';}
$form=is_string($_GET['form'] ?? null)?$_GET['form']:'';
if($form==='release_fault')$form='repair';
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&$form==='area_followup'&&is_string($_GET['audit_key']??null)){
 $task=(new \Tivoli\Service\WeeklyAuditService(access_service()))->association('area',$_GET['audit_key']);header('Location: '.access_url('weekly-audits.php?'.http_build_query($task?['id'=>$task]:['page'=>'area','source_id'=>$_GET['audit_key']])).'#audit-action');exit;
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&in_array($form,['clean','inspect'],true)){header('Location: '.access_url('housekeeping.php?room_id='.(int)($_GET['room_id']??$_GET['id']??0)).'#housekeeping-action');exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&$form==='stock_count'){header('Location: '.access_url('stock-counts.php?page=new').'#stockcount-action');exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&$form==='stock_in'){header('Location: '.access_url('purchases.php?page=new&type=stock&extra_id='.(int)($_GET['id']??0)).'#purchase-action');exit;}
if($section==='rooms'){if(in_array($form,['fault','repair'],true))$tab='faults';elseif($form==='room_audit')$tab='audits';elseif($form==='room_save')$tab='master';}
$roomId=max(0,(int)($_GET['room_id'] ?? 0));$editId=max(0,(int)($_GET['id'] ?? 0));
$auditAreas=['revision'=>'','entries'=>[]];$cleaningChecklist=['revision'=>'','sections'=>[]];$inspectionChecklist=$cleaningChecklist;$formValues=[];$extraCategories=[];$operationKey=bin2hex(random_bytes(16));
$layoutTitle=match($section){'extras'=>'Extras & Stock','rooms'=>'Property & Audits','folios'=>'Guest Stay Account','activity'=>'Activity','areas'=>'Property & Audits','departures'=>'Departure Incidents',default=>'Check-In & Occupancy'};
try {
    $service->installed();if($form==='refund'&&!$canAuthorizeDeparture)throw new \Tivoli\Security\PermissionDenied('Only the configured finance owner records actual guest refunds.');
    if($form==='extra_save'&&$isAdmin)$extraCategories=\Tivoli\Service\ExtraCategories::current($service->repo)['entries'];
    if($section==='areas')$auditAreas=\Tivoli\Service\AuditAreas::current($service->repo);
    $cleaningChecklist=\Tivoli\Service\CleaningChecklist::current($service->repo);
    $inspectionChecklist=\Tivoli\Service\CleaningChecklist::current($service->repo,'inspection');
    if(($_GET['export'] ?? '')==='csv'){require dirname(__DIR__).'/bootstrap/stay-export.php';stay_export_csv($actor);}
    // Expire abandoned confirmations; keep the action identity across uncertain outcomes.
    foreach($_SESSION['stay_confirmations'] ?? [] as $token=>$pending){if(($pending['expires'] ?? 0)<time())unset($_SESSION['stay_confirmations'][$token]);}
    if(($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST'){
        access_check_csrf();
        foreach($_POST as $value)if(!is_string($value))throw new RuntimeException('Invalid form input.');
        $input=$_POST;$action=$input['action'] ?? '';$operationKey=$input['operation_key'] ?? '';
        if(in_array($action,['clean','inspect'],true)){access_service()->audit($actor,'housekeeping.obsolete_form','room',null,'denied');http_response_code(409);throw new RuntimeException('Use Housekeeping to retain the assigned cleaner, independent inspection and owner exceptions.');}
        if($action==='stock_count'){access_service()->audit($actor,'stockcount.obsolete_form','stock',null,'denied');http_response_code(409);throw new RuntimeException('Submit a Stock Count for owner review. Immediate stock adjustment is no longer available.');}
        if($action==='stock_in'){access_service()->audit($actor,'purchase.obsolete_stock_form','stock',null,'denied');http_response_code(409);throw new RuntimeException('Receive purchased stock through its approved Purchase Request to retain the cost and source link.');}
        if(!preg_match('/^[a-f0-9]{32}$/D',$operationKey))throw new RuntimeException('This form expired. Reload and try again.');
        $bookingId=max(0,(int)($input['booking_id'] ?? 0));
        if($action==='preview_departure'){
            $departurePreview=$service->departurePreview($actor,$bookingId,$input['actual_departed_at']??'');
            $form='review_departure';
        }else{
        if($action==='confirm_action'){
            $token=$input['confirmation_token'] ?? '';$pending=$_SESSION['stay_confirmations'][$token] ?? null;
            if(!$pending||$pending['actor']!==$actor||$pending['expires']<time()||!hash_equals($pending['operation_key'],$operationKey))throw new RuntimeException('This confirmation expired. Review the guest account again.');
            $action=$pending['action'];$input=$pending['input'];$bookingId=(int)$input['booking_id'];
            $input['expected_balance']=(string)$pending['balance'];
        }elseif(in_array($action,['clear_balance','checkout'],true)){
            $snapshot=$service->snapshot($actor,$bookingId);$guest=$snapshot['booking'] ?? null;
            if(!$guest||$guest['status']!=='active')throw new RuntimeException('This guest has already checked out or is unavailable.');
            $balance=(int)($snapshot['bill']['balance'] ?? 0);
            if($action==='checkout'&&$balance>0&&empty($snapshot['bill']['approval']))throw new RuntimeException('Clear the outstanding balance or obtain Eugene’s checkout authorization.');
            if($action==='clear_balance'&&$balance<=0)throw new RuntimeException('There is no outstanding balance to clear.');
            if($action==='checkout'&&(!isset($input['extras_checked'])||!isset($input['keys_returned'])))throw new RuntimeException('Review the guest bill and recover the keys before confirming departure.');
            $token=bin2hex(random_bytes(24));
            $_SESSION['stay_confirmations'][$token]=['actor'=>$actor,'action'=>$action,'operation_key'=>$operationKey,'input'=>$input,'balance'=>$balance,'guest'=>$guest['guest_name'],'room'=>$guest['room_name'] ?? $guest['room_id'],'expires'=>time()+600];
            header('Location: '.stay_url(['booking_id'=>$bookingId,'confirm'=>$token]).'#stay-action');exit;
        }
        if($action==='checkin'&&!empty($_FILES['identity_file'])&&($_FILES['identity_file']['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
            if(!function_exists('stay_prepare_identity'))throw new RuntimeException('Private guest-document storage must be configured before an ID can be attached. Check-in remains available without an attachment.');
            $input['_identity']=stay_prepare_identity($actor,$_FILES['identity_file']);
        }
        if(in_array($action,['fault','repair'],true)&&!empty($_FILES['fault_file'])&&($_FILES['fault_file']['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
            access_service()->requirePrivilege($actor,'rooms.manage');if(!$isAdmin)throw new \Tivoli\Security\PermissionDenied('Only administrators can attach room evidence.');
            $input['_fault_file']=\Tivoli\Service\FaultAttachment::uploaded($_FILES['fault_file']);
        }
        if($action==='report_departure'&&isset($_FILES['evidence'])&&($_FILES['evidence']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
            $input['_incident_file']=\Tivoli\Service\FinanceAttachment::uploaded($_FILES['evidence']);
        }
        $result=$service->execute($actor,$action,$input,$operationKey);
        if(isset($token))unset($_SESSION['stay_confirmations'][$token]);
        $_SESSION['stay_notice']=match($action){'report_departure'=>'Departure reported. Eugene’s review is pending.','secure_departure_room'=>'Room vacancy and access security recorded.','review_departure'=>'Departure review recorded. Original evidence is retained.','departure_followup'=>'Collection follow-up recorded.','collect_outstanding'=>'Outstanding payment recorded by '.(string)$user['name'].'.','checkin'=>'Guest checked in. Payment recorded by '.(string)$user['name'].'.','clear_balance'=>'Full payment recorded by '.(string)$user['name'].'. Review and confirm departure separately.','checkout'=>'Departure recorded. Room sent for cleaning.','allow_checkout'=>'Checkout exception saved. The outstanding balance remains on the guest account.','payment'=>'Payment recorded by '.(string)$user['name'].'.','recover_receipt'=>'Recovered receipt recorded. The checkout snapshot is retained.','extra'=>'Extra posted to the guest account.','extra_save'=>'Extra saved. Existing purchases retain their original prices.',default=>'Action recorded.'};
        $targetId=(int)($result['booking_id'] ?? $bookingId);
        $query=$targetId?['booking_id'=>$targetId,'tab'=>'bill']:['section'=>$section,'tab'=>$tab];
        if(in_array($action,['clear_balance','allow_checkout'],true))$query=['booking_id'=>$targetId,'form'=>'checkout'];
        elseif(in_array($action,['extra_save','stock_in','stock_count'],true))$query=['section'=>'extras','tab'=>$action==='extra_save'?'catalogue':'stock'];
        elseif(in_array($action,['clean','inspect'],true))$query=['section'=>'board'];
        elseif(in_array($action,['area_audit','area_followup'],true))$query=['section'=>'areas','range'=>'all'];
        elseif(in_array($action,['room_save','fault','repair','release_fault','room_audit'],true))$query=['section'=>'rooms','tab'=>in_array($action,['fault','repair','release_fault'],true)?'faults':($action==='room_audit'?'audits':'master')];
        header('Location: '.stay_url($query).'#'.(in_array($action,['clear_balance','allow_checkout'],true)?'stay-action':'stay-result'));exit;
        }
    }
    if(isset($_GET['confirm'])&&is_string($_GET['confirm'])){
        $token=$_GET['confirm'];$pending=$_SESSION['stay_confirmations'][$token] ?? null;
        if(!$pending||$pending['actor']!==$actor||$pending['expires']<time())throw new RuntimeException('Confirmation expired. Review the guest account again.');
        $confirmation=$pending+['token'=>$token];$bookingId=(int)$pending['input']['booking_id'];
    }
    $view=$service->snapshot($actor,$bookingId);
    if($section==='departures')$departureCases=$service->departureCases($actor);
    if($canAuthorizeDeparture&&in_array($form,['review_departure','departure_followup'],true)){
        $incidentAssignees=$service->repo->run('SELECT u.id,u.name FROM users u JOIN security_accounts a ON a.user_id=u.id WHERE a.active=1 ORDER BY u.name,u.id')->fetchAll();
    }
    if(!$bookingId&&$section==='folios')$folioDirectory=$service->folios($actor,$before,array_intersect_key($_GET,array_flip(['start','end','range'])));
    if(!$bookingId&&$section==='activity')$operationalActivity=$service->operationalActivity($actor,$before);
    if($section==='areas')$areaAudits=$service->areaAudits($actor,array_intersect_key($_GET,array_flip(['week','range','before','history','history_before','audit_key'])));
    $notice=is_string($_SESSION['stay_notice'] ?? null)?$_SESSION['stay_notice']:'';unset($_SESSION['stay_notice']);
}catch(Throwable $exception){
    $error=access_error($exception);$formValues=$_POST;
    try{$view=$service->snapshot($actor,$bookingId);}catch(Throwable $unavailable){$view=[];}
    if($section==='areas'){try{$areaAudits=$service->areaAudits($actor,array_intersect_key($_GET,array_flip(['week','range','before','history','history_before','audit_key'])));}catch(Throwable $ignored){}}
    if($exception instanceof \Tivoli\Security\PermissionDenied)http_response_code(403);elseif($exception instanceof PDOException)http_response_code(503);elseif(http_response_code()<400)http_response_code(400);
}
require dirname(__DIR__).'/templates/stays/index.php';
