<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
use Tivoli\Service\HandoverService;
$h=new HandoverService(access_service());$actor=(int)$user['id'];$owner=$h->owner($actor);
$pages=['prepare'=>'Prepare Handover','incoming'=>'Incoming Review','cash'=>'Cash Transfer','history'=>'History','owner'=>'Owner Attention'];
$page=is_string($_GET['page']??null)&&isset($pages[$_GET['page']])?$_GET['page']:'prepare';
$id=is_string($_GET['id']??null)&&preg_match('/^[1-9][0-9]{0,17}$/D',$_GET['id'])?(int)$_GET['id']:0;
$before=is_string($_GET['before']??null)&&ctype_digit($_GET['before'])?(int)$_GET['before']:0;
$error='';$case=null;$draft=[];$register=[];$attention=[];$cash=[];$recipients=[];
$values=$_SERVER['REQUEST_METHOD']==='POST'?array_filter($_POST,'is_string'):[];
$key=is_string($_POST['operation_key']??null)&&preg_match('/^[a-f0-9]{32}$/D',$_POST['operation_key'])?$_POST['operation_key']:bin2hex(random_bytes(16));
try{
 $h->permit($actor);
 if($page==='owner'&&!$owner)throw new \Tivoli\Security\PermissionDenied('Only the designated owner may view Owner Attention.');
 if($_SERVER['REQUEST_METHOD']==='POST'){
  access_check_csrf();$action=is_string($_POST['action']??null)?$_POST['action']:'';
  $result=$h->execute($actor,$action,$_POST,$key);
  $record=in_array($action,['submit','cash_send'],true)?$result['id']:(int)($_POST['id']??0);
  $_SESSION['handover_notice']='Action recorded. Original records, your identity and the action time are retained.';
  header('Location: '.access_url('handover.php?'.http_build_query(['page'=>$action==='submit'?'incoming':($action==='cash_send'?'cash':$page),'id'=>$record])).'#handover-status');exit;
 }
}catch(Throwable $e){$error=access_error($e);http_response_code($e instanceof \Tivoli\Security\PermissionDenied||http_response_code()===403?403:($e instanceof PDOException?503:400));}
if(http_response_code()!==403){
 try{
  $view=access_service()->transaction(function()use($h,$actor,$page,$id,$before){
   $data=[];
   if($id)$data['case']=$h->detail($actor,$id);
   elseif($page==='prepare')$data['draft']=$h->draft($actor);
   elseif($page==='owner')$data['attention']=$h->attention($actor);
   else $data['register']=$h->register($actor,$before);
   if($page==='cash'&&!$id){$data['cash']=(new \Tivoli\Service\FinanceService(access_service()))->cash();$data['recipients']=$h->recipients($actor);}
   access_service()->audit($actor,'handover.viewed','handover',$id?(string)$id:null,'succeeded',['page'=>$page]);return $data;
  });extract($view,EXTR_OVERWRITE);
 }catch(Throwable $e){$error=access_error($e);http_response_code($e instanceof \Tivoli\Security\PermissionDenied?403:($e instanceof PDOException?503:400));}
}
$notice=is_string($_SESSION['handover_notice']??null)?$_SESSION['handover_notice']:'';unset($_SESSION['handover_notice']);
function ho_url(array $q=[]):string{return access_url('handover.php'.($q?'?'.http_build_query($q):''));}
function ho_fields(string $action,array $extra=[]):void{global $key;foreach(['csrf'=>access_csrf(),'operation_key'=>$key,'action'=>$action]+$extra as $n=>$v)echo '<input type="hidden" name="'.access_h($n).'" value="'.access_h($v).'">';}
function ho_value(string $name,string $fallback=''):string{global $values;return $values[$name]??$fallback;}
function ho_text(string $label,string $name,bool $required=false):void{echo '<label>'.access_h($label).($required?' *':'').'<textarea name="'.access_h($name).'" rows="3" maxlength="2000"'.($required?' required':'').'>'.access_h(ho_value($name)).'</textarea></label>';}
function ho_check(string $name,string $label):void{echo '<label class="ho-check"><input type="checkbox" name="'.access_h($name).'" value="1" required'.(ho_value($name)==='1'?' checked':'').'> <span>'.access_h($label).'</span></label>';}
function ho_status(string $status):string{return match($status){'pending'=>'Awaiting Acknowledgement','exception'=>'Acknowledged With Exception','disputed'=>'Cash Disputed','partially_acknowledged'=>'Partially Acknowledged / Residual Reserved','resolved_returned'=>'Transfer Resolved / Cash Returned',default=>'Acknowledged'};}
function ho_sections(array $snapshot,bool $review=false):void{
 foreach($snapshot['sections'] as $section=>$data){
  echo '<section class="card"><h2>'.access_h($data['title']).'</h2>';
  if(!$data['items'])echo '<p>No open items recorded in this section.</p>';
  foreach($data['items'] as $item){echo '<article class="ho-item"><strong>'.access_h($item['label']).'</strong><p>'.access_h($item['detail']).'</p>';
   if(!empty($item['link'])&&preg_match('/^(?:occupancy|finance|expenses|purchases|stock-counts|housekeeping)\.php(?:\?|$)/D',$item['link']))echo '<a href="'.access_h(access_url($item['link'])).'" target="_blank" rel="noopener">Open Source Record</a>';
   echo '</article>';
  }
  if($review)ho_check('review_'.$section,'I have reviewed '.strtolower($data['title']).'.');
  echo '</section>';
 }
}
require dirname(__DIR__).'/templates/handover/index.php';
