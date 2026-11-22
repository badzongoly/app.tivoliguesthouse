<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
require_once dirname(__DIR__).'/bootstrap/stays.php';
use Tivoli\Service\StockIssueService;
use Tivoli\Service\FinanceAttachment;
$s=new StockIssueService(access_service());$actor=(int)$user['id'];$owner=$s->owner($actor);
$page=is_string($_GET['page']??null)&&in_array($_GET['page'],['register','new'],true)?$_GET['page']:'register';
$id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT)?:0;$extraId=filter_var($_GET['extra_id']??0,FILTER_VALIDATE_INT)?:0;
$form=is_string($_GET['form']??null)?$_GET['form']:'';$before=filter_var($_GET['before']??0,FILTER_VALIDATE_INT)?:0;
$values=$_SERVER['REQUEST_METHOD']==='POST'?array_filter($_POST,'is_string'):[];
$operationKey=$_SERVER['REQUEST_METHOD']==='POST'?($values['operation_key']??''):bin2hex(random_bytes(16));
$extraId=(int)($values['extra_id']??$extraId);
$error='';$case=null;$rows=[];$options=[];$counts=[];$files=[];$next=0;
try{
 $s->permit($actor);
 if($_SERVER['REQUEST_METHOD']==='POST'){
  access_check_csrf();$input=$values;
  if(isset($_FILES['evidence'])&&($_FILES['evidence']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$input['_evidence']=FinanceAttachment::uploaded($_FILES['evidence']);
  if(isset($_POST['_retry_proof'])||isset($_POST['_retry_proof_signature'])){
   $proof=$values['_retry_proof']??'';$signature=$values['_retry_proof_signature']??'';
   if(!hash_equals(si_retry_signature($proof),$signature))throw new RuntimeException('This evidence retry form changed. Reload the original source.');
   if(!isset($input['_evidence'])||$proof!==json_encode(FinanceAttachment::meta($input['_evidence']),JSON_THROW_ON_ERROR))throw new RuntimeException('Reattach the exact original supporting file before retrying this action.');
   unset($input['_retry_proof'],$input['_retry_proof_signature'],$values['_retry_proof'],$values['_retry_proof_signature']);
  }
  $result=$s->execute($actor,$values['action']??'',$input,$operationKey);$_SESSION['stock_issue_notice']='Action recorded. Original evidence and stock history are retained.';
  header('Location: '.access_url('stock-issues.php?id='.$result['id']).'#stockissue-status');exit;
 }
}catch(Throwable $e){$error=access_error($e);http_response_code($e instanceof \Tivoli\Security\PermissionDenied||http_response_code()===403?403:($e instanceof PDOException?503:400));}
if(!in_array(http_response_code(),[403,503],true))try{
 $data=access_service()->transaction(function()use($s,$actor,$owner,$id,$page,$form,$before,$extraId){
  $data=[];
  if($id){$c=$s->detail($actor,$id);$reporter=(int)$c['root']['actor_id']===$actor;
   $allowed=match($form){''=>true,'approve','return'=>$owner&&in_array($c['status'],['pending','returned'],true),'dispose'=>$owner&&$c['status']==='approved','correct_report'=>$owner&&!$c['root']['data']['count_id']&&in_array($c['status'],['pending','returned','approved'],true),'clarify'=>$reporter&&$c['status']==='returned',default=>false};
   if(!$allowed)throw new \Tivoli\Security\PermissionDenied('This stock action is unavailable for this account or source.');
   $data['case']=$c;foreach(array_merge([$c['root']],$c['history']) as $e)if(FinanceAttachment::available(access_service(),'stockissue',(int)$e['id']))$data['files'][(int)$e['id']]=true;
  }elseif($page==='new'){$data['options']=$s->options($actor);$data['counts']=$extraId>0?$s->countOptions($actor,$extraId):[];}
  else{$list=$s->register($actor,max(0,$before));$data['rows']=$list['rows'];$data['next']=$list['next'];}
  access_service()->audit($actor,'stockissue.viewed','stock_issue',$id?(string)$id:null,'succeeded',['page'=>$page]);return $data;
 });extract($data,EXTR_OVERWRITE);
}catch(Throwable $e){$case=null;$error=access_error($e);http_response_code($e instanceof \Tivoli\Security\PermissionDenied?403:($e instanceof PDOException?503:400));}
$notice=is_string($_SESSION['stock_issue_notice']??null)?$_SESSION['stock_issue_notice']:'';unset($_SESSION['stock_issue_notice']);
function si_url(array $q=[]):string{return access_url('stock-issues.php'.($q?'?'.http_build_query($q):''));}
function si_fields(string $action,array $fields=[]):void{global $operationKey;foreach(['csrf'=>access_csrf(),'operation_key'=>$operationKey,'action'=>$action]+$fields as $name=>$value)echo '<input type="hidden" name="'.access_h($name).'" value="'.access_h($value).'">';}
function si_value(string $key,string $default=''):string{global $values;return $values[$key]??$default;}
function si_qty(int $n):string{return \Tivoli\Domain\Stay\StayRules::decimal($n);}
function si_link(string $text,array $q=[],bool $primary=false):void{echo '<a class="si-button'.($primary?' si-primary':'').'" href="'.access_h(si_url($q)).'#stockissue-action">'.access_h($text).'</a>';}
function si_check(string $text):void{echo '<label class="si-check"><input type="checkbox" name="verified" value="1" required><span>'.access_h($text).'</span></label>';}
function si_text(string $label,string $name='comment',bool $required=false):void{echo '<label>'.access_h($label).($required?' *':' (Optional)').'<textarea name="'.access_h($name).'" rows="3" maxlength="2000"'.($required?' required':'').'>'.access_h(si_value($name)).'</textarea></label>';}
function si_retry_signature(string $metadata):string{global $actor,$operationKey;return hash_hmac('sha256','stockissue.retry.file|'.$actor.'|'.$operationKey.'|'.$metadata,access_config()['audit_key']);}
function si_upload(bool $required=false):void{echo '<label>'.($required?'Reattach Original Supporting File *':'Supporting File (Optional)').'<input name="evidence" type="file" accept=".pdf,.jpg,.jpeg,.png"'.($required?' required':'').'><small>PDF, JPEG Or PNG · Up To 2 MB · Protected Storage</small></label>';}
function si_status(array $c):string{return match($c['status']){'pending'=>'Awaiting Admin Review','returned'=>'Returned · Hold Retained','approved'=>$c['root']['data']['kind']==='complimentary'?'Approved · Awaiting Supply':'Approved · Awaiting Disposition','completed'=>'Completed','cancelled'=>'Cancelled · Verified Mistaken Report',default=>$c['status']};}
require dirname(__DIR__).'/templates/stock-issues/index.php';
