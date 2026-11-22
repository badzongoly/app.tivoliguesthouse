<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
require_once dirname(__DIR__).'/bootstrap/stays.php';
use Tivoli\Service\WeeklyAuditService;
use Tivoli\Service\WeeklyAuditEvidence;
use Tivoli\Service\FaultAttachment;
use Tivoli\Service\AuditAreas;
$s=new WeeklyAuditService(access_service());$actor=(int)$user['id'];$owner=$s->owner($actor);$auditor=false;$admin=access_service()->repo->isAdmin($actor)&&in_array('rooms.manage',access_service()->repo->privileges($actor),true);
$page=is_string($_GET['page']??null)&&in_array($_GET['page'],['followups','room','area','duty','legacy'],true)?$_GET['page']:'followups';
$id=max(0,filter_var($_GET['id']??0,FILTER_VALIDATE_INT)?:0);$before=max(0,filter_var($_GET['before']??0,FILTER_VALIDATE_INT)?:0);$closed=($_GET['status']??'')==='closed';$all=($_GET['range']??'')==='all';$form=($_GET['form']??'')==='new'?'new':'';
$values=[];$key=bin2hex(random_bytes(16));$error='';$case=null;$source=null;$rows=[];$next=0;$staff=[];$rooms=[];$areas=['revision'=>'','entries'=>[]];$links=[];$files=[];$history=[];$historyNext=null;
try{
 $s->permit($actor);$auditor=$s->auditor($actor);
 if(isset($_GET['download'])){$event=filter_var($_GET['download'],FILTER_VALIDATE_INT);if(!$event||$event<1)throw new RuntimeException('Invalid evidence record.');$file=WeeklyAuditEvidence::download(access_service(),$actor,$event);header('Content-Type: '.$file['mime']);header('Content-Disposition: attachment; filename="'.$file['filename'].'"');header('Content-Length: '.strlen($file['bytes']));header('Content-Security-Policy: sandbox');echo $file['bytes'];exit;}
 if($_SERVER['REQUEST_METHOD']==='POST'){
  access_check_csrf();foreach($_POST as $v)if(!is_string($v))throw new RuntimeException('Invalid audit form.');$values=$_POST;$key=preg_match('/^[a-f0-9]{32}$/D',$values['operation_key']??'')?$values['operation_key']:$key;$action=$values['action']??'';$input=$values;
  if(in_array($action,['room_audit','area_audit'],true)){$result=stay_service()->execute($actor,$action,$input,$values['operation_key']??'');$target=$result['followup_id']?['id'=>$result['followup_id']]:['page'=>$action==='room_audit'?'room':'area'];}
  else{
   if(isset($_FILES['evidence'])&&($_FILES['evidence']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$input['_evidence']=FaultAttachment::uploaded($_FILES['evidence']);
   $result=$s->execute($actor,$action,$input,$values['operation_key']??'');$target=$action==='duty'?['page'=>'duty']:['id'=>$result['id']];
  }
  $_SESSION['weekly_audit_notice']='Action recorded.';header('Location: '.access_url('weekly-audits.php?'.http_build_query($target)).'#audit-action');exit;
 }
}catch(Throwable $e){$error=access_error($e);http_response_code($e instanceof \Tivoli\Security\PermissionDenied||http_response_code()===403?403:($e instanceof PDOException?503:400));}
if(http_response_code()!==403)try{
 $data=access_service()->transaction(function()use($s,$actor,$owner,$page,$id,$before,$closed,$all,$form){
  $data=[];if($id){$data['case']=$s->detail($actor,$id);$files=[];foreach($data['case']['history'] as $e)if(WeeklyAuditEvidence::available(access_service(),(int)$e['id']))$files[(int)$e['id']]=true;$data['files']=$files;}
  elseif($page==='duty'){if(!$owner)throw new \Tivoli\Security\PermissionDenied('Only Eugene manages audit duty.');}
  elseif($page==='legacy'){$list=$s->legacyOpen($actor,$before);$data['rows']=$list['rows'];$data['next']=$list['next'];}
  elseif(in_array($page,['room','area'],true)){
   if(isset($_GET['source_id'])){$data['source']=$s->source($actor,$page,is_string($_GET['source_id'])?$_GET['source_id']:'');if($page==='area'){$original=\Tivoli\Service\WeeklyAreaAudit::listing(access_service()->repo,['range'=>'all','history'=>$data['source']['source_id'],'history_before'=>is_string($_GET['history_before']??null)?$_GET['history_before']:'0'],gmdate('Y-m-d H:i:s'));$data['history']=$original['history'];$data['historyNext']=$original['history_next'];}}
   else{$s->requireAuditor($actor);$list=$s->register($actor,$page,$before,$all);$data['rows']=$list['rows'];$data['next']=$list['next'];}
   if($form){$s->requireAuditor($actor);$data['rooms']=access_service()->repo->run('SELECT id,name FROM rooms ORDER BY name,id')->fetchAll();$data['areas']=AuditAreas::current(access_service()->repo);$data['links']=$s->openChoices($actor,$page);}
  }else{$list=$s->listing($actor,$closed,$before);$data['rows']=$list['rows'];$data['next']=$list['next'];}
  if($owner&&($page==='duty'||isset($data['case'])&&in_array($data['case']['status'],['unassigned','assigned'],true)))$data['staff']=$page==='duty'?$s->dutyRoster($actor):$s->staff($actor);access_service()->audit($actor,'weekly_audit.viewed','weekly_audit',$id?(string)$id:null,'succeeded',['page'=>$page]);return $data;
 });extract($data,EXTR_OVERWRITE);
}catch(Throwable $e){$error=access_error($e);$case=null;$source=null;$rows=[];http_response_code($e instanceof \Tivoli\Security\PermissionDenied?403:($e instanceof PDOException?503:400));}
$notice=is_string($_SESSION['weekly_audit_notice']??null)?$_SESSION['weekly_audit_notice']:'';unset($_SESSION['weekly_audit_notice']);
function w_url(array $q=[]):string{return access_url('weekly-audits.php'.($q?'?'.http_build_query($q):''));}
function w_fields(string $action,array $extra=[]):void{global $key;foreach(['csrf'=>access_csrf(),'operation_key'=>$key,'action'=>$action]+$extra as $n=>$v)echo '<input type="hidden" name="'.access_h($n).'" value="'.access_h($v).'">';}
function w_link(string $label,array $q=[],bool $primary=false):void{echo '<a class="wa-button'.($primary?' wa-primary':'').'" href="'.access_h(w_url($q)).'#audit-action">'.access_h($label).'</a>';}
function w_text(string $label='Comment',string $name='comment',bool $required=false,string $value=''):void{global $values;echo '<label>'.access_h($label).($required?' *':' (Optional)').'<textarea name="'.access_h($name).'" rows="3" maxlength="2000"'.($required?' required':'').'>'.access_h($values[$name]??$value).'</textarea></label>';}
function w_status(string $status):string{return ['unassigned'=>'Needs Assignment','assigned'=>'In Progress','review'=>'Awaiting Verification','closed'=>'Verified & Closed'][$status]??stay_label($status);}
function w_result(string $result):string{return ['pass'=>'Pass','corrective'=>'Needs Attention','unsafe'=>'Unsafe'][$result]??$result;}
function w_source(array $source):void{?><article class="wa-source"><div><strong><?=access_h($source['kind']==='room'?'Room '.$source['location']:$source['location'])?></strong><span><?=access_h(w_result($source['result']))?> · <?=access_h(display_date($source['created_at']))?> UTC</span></div><p><?=access_h($source['comment']?:'No Comment Recorded')?></p><?php if($source['legacy_owner']!==''):?><small>Original Owner Note: <?=access_h($source['legacy_owner'])?><?=!empty($source['legacy_due_date'])?' · Original Due Date: '.access_h(display_date($source['legacy_due_date'])):''?> · Account Not Inferred</small><?php endif;?><?php w_link('Original Audit',['page'=>$source['kind'],'source_id'=>$source['source_id']]);?></article><?php }
$layoutTitle='Weekly Audits & Follow-Ups';
require dirname(__DIR__).'/templates/weekly-audits/index.php';
