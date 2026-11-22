<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
use Tivoli\Service\PurchaseService;use Tivoli\Service\FinanceAttachment;
$p=new PurchaseService(access_service());$actor=(int)$user['id'];$owner=$p->owner($actor);
$pages=['register'=>'Requests','new'=>'New Request','owner'=>'Owner Review Queue','history'=>'History'];
$page=is_string($_GET['page']??null)&&isset($pages[$_GET['page']])?$_GET['page']:'register';$tab=is_string($_GET['tab']??null)&&in_array($_GET['tab'],['approval','delivery','payment','activity'],true)?$_GET['tab']:'approval';
$id=is_string($_GET['id']??null)&&preg_match('/^[1-9][0-9]{0,17}$/D',$_GET['id'])?(int)$_GET['id']:0;$before=max(0,(int)($_GET['before']??0));$form=is_string($_GET['form']??null)?$_GET['form']:'';
$values=$_SERVER['REQUEST_METHOD']==='POST'?array_filter($_POST,'is_string'):[];$key=is_string($values['operation_key']??null)&&preg_match('/^[a-f0-9]{32}$/D',$values['operation_key'])?$values['operation_key']:bin2hex(random_bytes(16));
$error='';$case=null;$register=[];$attention=[];$options=[];$files=[];
try{
 $p->permit($actor);if($page==='owner'&&!$owner)throw new \Tivoli\Security\PermissionDenied('Only the designated owner may view the purchase review queue.');
 if($_SERVER['REQUEST_METHOD']==='POST'){
  access_check_csrf();$action=is_string($_POST['action']??null)?$_POST['action']:'';$input=$_POST;
  if(isset($_FILES['evidence'])&&($_FILES['evidence']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$input['_evidence']=FinanceAttachment::uploaded($_FILES['evidence']);
  $result=$p->execute($actor,$action,$input,$key);$record=in_array($action,['request','report'],true)?$result['id']:(int)($_POST['id']??0);
  $_SESSION['purchase_notice']='Action recorded. The original scope, decisions, evidence and financial links are retained.';
  header('Location: '.access_url('purchases.php?'.http_build_query(['id'=>$record,'tab'=>in_array($action,['receive','post','report','clarify','dispute'],true)?'delivery':($action==='pay'?'payment':'approval')])).'#purchase-status');exit;
 }
}catch(Throwable $e){$error=access_error($e);http_response_code($e instanceof \Tivoli\Security\PermissionDenied||http_response_code()===403?403:($e instanceof PDOException?503:400));}
if(http_response_code()!==403)try{
 $view=access_service()->transaction(function()use($p,$actor,$id,$page,$before,$form){
  $data=['options'=>$p->options($actor)];
  if($id){
   $c=$p->detail($actor,$id);$owner=$p->owner($actor);$requester=(int)$c['root']['actor_id']===$actor;
   $allowed=match($form){''=>true,'approve'=>$owner&&in_array($c['status'],['pending','change'],true),'post','dispute'=>$owner&&in_array($c['status'],['declared','exception'],true),'pay'=>$owner&&$c['status']==='posted'&&$c['paid']<$c['receipt']['cost'],'amend','commit'=>$requester&&$c['status']==='approved','receive'=>$requester&&$c['status']==='committed','clarify'=>$requester&&$c['status']==='disputed','cancel'=>$requester&&in_array($c['status'],['pending','approved'],true),default=>false};
   if(!$allowed)throw new \Tivoli\Security\PermissionDenied('This action is not available to your account for this purchase.');
   $data['case']=$c;$files=[];foreach(array_merge([$c['root']],$c['history']) as $event)if(FinanceAttachment::available(access_service(),'purchase',(int)$event['id']))$files[(int)$event['id']]=true;$data['files']=$files;
  }elseif($page==='owner')$data['attention']=$p->attention($actor);elseif($page!=='new')$data['register']=$p->register($actor,$before);
  access_service()->audit($actor,'purchase.viewed','purchase',$id?(string)$id:null,'succeeded',['page'=>$page]);return $data;
 });extract($view,EXTR_OVERWRITE);
}catch(Throwable $e){$error=access_error($e);http_response_code($e instanceof \Tivoli\Security\PermissionDenied?403:($e instanceof PDOException?503:400));}
$notice=is_string($_SESSION['purchase_notice']??null)?$_SESSION['purchase_notice']:'';unset($_SESSION['purchase_notice']);
function pur_url(array $q=[]):string{return access_url('purchases.php'.($q?'?'.http_build_query($q):''));}
function pur_fields(string $action,array $extra=[]):void{global $key;foreach(['csrf'=>access_csrf(),'operation_key'=>$key,'action'=>$action]+$extra as $n=>$v)echo '<input type="hidden" name="'.access_h($n).'" value="'.access_h($v).'">';}
function pur_value(string $name,string $default=''):string{global $values;return $values[$name]??$default;}
function pur_input(string $label,string $name,string $value='',string $type='text',bool $required=true):void{echo '<label>'.access_h($label).($required?' *':' (Optional)').'<input name="'.access_h($name).'" type="'.access_h($type).'" value="'.access_h(pur_value($name,$value)).'" maxlength="2000"'.($type==='datetime-local'?' step="1"':'').($required?' required':'').'></label>';}
function pur_text(string $label,string $name,string $value='',bool $required=false):void{echo '<label>'.access_h($label).($required?' *':' (Optional)').'<textarea name="'.access_h($name).'" maxlength="2000" rows="3"'.($required?' required':'').'>'.access_h(pur_value($name,$value)).'</textarea></label>';}
function pur_select(string $label,string $name,array $choices,string $value='',bool $required=true):void{echo '<label>'.access_h($label).($required?' *':'').'<select name="'.access_h($name).'"'.($required?' required':'').'>';foreach($choices as $id=>$text)echo '<option value="'.access_h($id).'"'.(pur_value($name,$value)===(string)$id?' selected':'').'>'.access_h($text).'</option>';echo '</select></label>';}
function pur_check(string $name,string $label):void{echo '<label class="pu-check"><input type="checkbox" name="'.access_h($name).'" value="1" required'.(pur_value($name)==='1'?' checked':'').'> <span>'.access_h($label).'</span></label>';}
function pur_upload(string $label):void{echo '<label>'.access_h($label).' (Optional)<input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png"><small>PDF, JPEG or PNG · Maximum 2 MB · Private evidence.</small></label>';}
function pur_money(int $amount):string{return 'GHS '.\Tivoli\Domain\Stay\StayRules::decimal($amount);}
function pur_status(array $c):string{return match($c['status']){'pending'=>'Awaiting Approval','change'=>'Revised Approval Requested','approved'=>'Approved To Commit','committed'=>'Committed · Awaiting Delivery','declared'=>'Delivery & Cost Awaiting Review','exception'=>'After-The-Fact Exception','disputed'=>'Declaration Disputed','posted'=>$c['paid']===$c['receipt']['cost']?'Completed':($c['paid']>$c['receipt']['cost']?'Supplier Recovery Due':'Cost Recorded · Payment Due'),'cancelled'=>'Cancelled',default=>'Rejected'};}
function pur_link(string $label,string $action,array $c,bool $primary=false):void{echo '<a class="pu-cancel'.($primary?' pu-primary':'').'" href="'.access_h(pur_url(['id'=>$c['root']['id'],'form'=>$action])).'#purchase-action">'.access_h($label).'</a>';}
function pur_file(int $event):void{global $files;if(isset($files[$event]))echo '<a href="'.access_h(access_url('finance-evidence.php?kind=purchase&id='.$event)).'">Download Private Evidence</a>';}
require dirname(__DIR__).'/templates/purchases/index.php';
