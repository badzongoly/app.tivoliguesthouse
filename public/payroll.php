<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
require_once dirname(__DIR__).'/bootstrap/stays.php';
use Tivoli\Service\PayrollService;
use Tivoli\Service\FinanceAttachment;
use Tivoli\Domain\Stay\StayRules as R;
$s=new PayrollService(access_service());$actor=(int)$user['id'];$owner=$s->owner($actor);
$page=is_string($_GET['page']??null)?$_GET['page']:'register';
if(!in_array($page,['register','owner','costs','statement','activity','corrections'],true))$page='register';
$id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT)?:0;
$publication=max(0,filter_var($_GET['statement_id']??0,FILTER_VALIDATE_INT)?:0);$correctionForm=($_GET['form']??'')==='correct';$legacy=max(0,filter_var($_GET['legacy_request']??0,FILTER_VALIDATE_INT)?:0);$statementVersions=[];
$before=max(0,filter_var($_GET['before']??0,FILTER_VALIDATE_INT)?:0);
$period=is_string($_GET['period']??null)?$_GET['period']:gmdate('Y-m');
$start=is_string($_GET['start']??null)?$_GET['start']:gmdate('Y-m-01');
$end=is_string($_GET['end']??null)?$_GET['end']:gmdate('Y-m-d');
$values=$_SERVER['REQUEST_METHOD']==='POST'?array_filter($_POST,'is_string'):[];
$key=$_SERVER['REQUEST_METHOD']==='POST'?($values['operation_key']??''):bin2hex(random_bytes(16));
$error='';$case=null;$statement=null;$rows=[];$options=[];$report=null;$next=0;$files=[];
try{
 $s->permit($actor);if(($page==='corrections'||$correctionForm||$legacy)&&!$owner)throw new \Tivoli\Security\PermissionDenied('Only the designated finance owner with salary authority may review payroll corrections.');
 if($legacy)throw new RuntimeException('This retained legacy request has no frozen salary/payee snapshot. Reconcile its original evidence before correction; do not create a replacement staff/month payroll.');
 if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
  access_check_csrf();$input=$values;
  if(isset($_FILES['evidence'])&&($_FILES['evidence']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$input['_evidence']=FinanceAttachment::uploaded($_FILES['evidence']);
  $result=$s->execute($actor,$values['action']??'',$input,$key);
  $_SESSION['payroll_notice']='Payroll action recorded.';
  header('Location: '.access_url('payroll.php?id='.$result['id']).'#payroll-action');exit;
  }catch(Throwable $e){if($e instanceof \Tivoli\Security\PermissionDenied||$e instanceof PDOException||http_response_code()===403)throw $e;$error=access_error($e);http_response_code(400);}
 }
 $data=access_service()->transaction(function()use($s,$actor,$owner,$id,$page,$period,$before,$start,$end,$publication){
  $d=[];
  if(!$owner){$d=$id?['statement'=>$s->statement($actor,$id,$publication)]:['rows'=>$s->ownStatements($actor)];}
  elseif($page==='statement'&&$id)$d['statement']=$s->statement($actor,$id,$publication);
  elseif($id){
   $d['case']=$s->detail($actor,$id);$d['files']=[];
   foreach($d['case']['history'] as $e)if(FinanceAttachment::available(access_service(),'payroll',(int)$e['id']))$d['files'][(int)$e['id']]=true;
  }elseif($page==='activity')$d=$s->activity($actor,$before);
  elseif($page==='costs')$d['report']=$s->reports($actor,$start,$end);
  else{$d=$page==='owner'?$s->attention($actor):$s->register($actor,$period,$before);$d['options']=$s->options($actor);}
  if(isset($d['statement']))$d['statementVersions']=$s->statementVersions($actor,$id);
  access_service()->audit($actor,'payroll.viewed','payroll',$id?(string)$id:null,'succeeded',['page'=>$page]);return $d;
 });extract($data,EXTR_OVERWRITE);
}catch(Throwable $e){$error=access_error($e);http_response_code($e instanceof \Tivoli\Security\PermissionDenied||http_response_code()===403?403:($e instanceof PDOException?503:400));}
$notice=is_string($_SESSION['payroll_notice']??null)?$_SESSION['payroll_notice']:'';unset($_SESSION['payroll_notice']);
function p_url(array $q=[]):string{return access_url('payroll.php'.($q?'?'.http_build_query($q):''));}
function p_money(int $n):string{return 'GHS '.R::decimal($n);}
function p_fields(string $action):void{global $key,$case;foreach(['csrf'=>access_csrf(),'operation_key'=>$key,'action'=>$action]+($case?['id'=>(string)$case['root']['id'],'version'=>$case['version']]:[]) as $n=>$v)echo '<input type="hidden" name="'.access_h($n).'" value="'.access_h($v).'">';}
function p_input(string $label,string $name,string $default='',string $type='text',bool $required=true):void{global $values;echo '<label>'.access_h($label).($required?' *':' (Optional)').'<input name="'.access_h($name).'" type="'.access_h($type).'" value="'.access_h($values[$name]??$default).'"'.($required?' required':'').' maxlength="120"'.($type==='datetime-local'?' step="1"':'').'></label>';}
function p_text(string $label,string $name,bool $required=false,bool $conditional=false):void{global $values;echo '<label>'.access_h($label).($conditional?' (Required For Nonzero Adjustments)':($required?' *':' (Optional)')).'<textarea name="'.access_h($name).'" maxlength="2000" rows="3"'.($required?' required':'').'>'.access_h($values[$name]??'').'</textarea></label>';}
function p_check(string $name,string $label):void{echo '<label class="payroll-check"><input type="checkbox" name="'.access_h($name).'" value="1" required> '.access_h($label).'</label>';}
function p_link(string $label,array $q):void{echo '<a class="payroll-button" href="'.access_h(p_url($q)).'#payroll-action">'.access_h($label).'</a>';}
require dirname(__DIR__).'/templates/payroll/index.php';
