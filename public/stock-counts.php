<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
require_once dirname(__DIR__).'/bootstrap/stays.php';
use Tivoli\Service\StockCountService;
use Tivoli\Service\FinanceAttachment;
$s=new StockCountService(access_service());$actor=(int)$user['id'];$owner=$s->owner($actor);
$page=is_string($_GET['page']??null)&&in_array($_GET['page'],['register','new','owner','activity'],true)?$_GET['page']:'register';
$id=is_string($_GET['id']??null)&&preg_match('/^[1-9][0-9]{0,17}$/D',$_GET['id'])?(int)$_GET['id']:0;
$itemId=is_string($_GET['item']??null)?max(0,(int)$_GET['item']):0;
$form=is_string($_GET['form']??null)?$_GET['form']:'';$before=is_string($_GET['before']??null)?max(0,(int)$_GET['before']):0;
$values=$_SERVER['REQUEST_METHOD']==='POST'?array_filter($_POST,'is_string'):[];$key=preg_match('/^[a-f0-9]{32}$/D',$values['operation_key']??'')?$values['operation_key']:bin2hex(random_bytes(16));
$error='';$case=null;$rows=[];$next=0;$options=[];$files=[];$notice='';$lastWeeklyItems=[];
try{
 $s->permit($actor);
 if($page==='owner'&&!$owner)throw new \Tivoli\Security\PermissionDenied('Only the designated owner may open the review queue.');
 if($_SERVER['REQUEST_METHOD']==='POST'){
  access_check_csrf();$action=$values['action']??'';$input=$values;
  if($action==='start'&&isset($_POST['items'])&&is_array($_POST['items']))$input['items']=json_encode(array_values($_POST['items']),JSON_THROW_ON_ERROR);
  if($action==='submit'){
   $reasons=[];foreach($values as $name=>$value)if(preg_match('/^reason_([1-9][0-9]*)$/D',$name,$m))$reasons[$m[1]]=['reason'=>$value,'note'=>$values['note_'.$m[1]]??''];
   if(!isset($input['reasons']))$input['reasons']=json_encode($reasons,JSON_THROW_ON_ERROR);
  }
  if(isset($_FILES['evidence'])&&($_FILES['evidence']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$input['_evidence']=FinanceAttachment::uploaded($_FILES['evidence']);
  $result=$s->execute($actor,$action,$input,$key);
  $query=['id'=>$result['id']];
  $anchor='stockcount-status';
  $message='Action recorded. Original count evidence and stock movements are retained.';
  $selected=(int)($result['next_item']??0);
  if($selected||$action==='begin'){
   $query['item']=$selected?:((int)($input['extra_id']??0));
   $anchor='stockcount-step';
  }
  if($action==='finish'&&($input['direction']??'')==='pause'){
   $query=['page'=>'register'];
   $message='Count saved. Use Resume Count when you are ready to continue.';
  }elseif($action==='finish'&&($input['direction']??'')==='next'){
   $message=$selected?'Count saved. The next item is ready to count.':'Count saved. Review the completed count.';
   if(!$selected)$anchor='stockcount-review';
  }
  $_SESSION['stockcount_notice']=$message;
  header('Location: '.sc_url($query).'#'.$anchor);exit;
 }
}catch(Throwable $e){$error=access_error($e);http_response_code($e instanceof \Tivoli\Security\PermissionDenied||http_response_code()===403?403:($e instanceof PDOException?503:400));}
if(http_response_code()!==403)try{
 $data=access_service()->transaction(function()use($s,$actor,$id,$page,$before,$form,$owner){
  $data=[];
  if(!$id&&$page==='new'){
   $data['options']=$s->options($actor);
   $data['lastWeeklyItems']=array_values(array_intersect($s->lastWeeklyItems($actor),array_column($data['options'],'id')));
  }
  if($id){
   $c=$s->detail($actor,$id);$staff=(int)$c['root']['actor_id']===$actor;
   $allowed=match($form){''=>true,'approve'=>$owner&&$c['status']==='submitted','dispute','recount'=>$owner&&in_array($c['status'],['submitted','disputed'],true),'clarify'=>$staff&&$c['status']==='disputed','cancel'=>$staff&&in_array($c['status'],['counting','ready'],true),default=>false};
   if(!$allowed)throw new \Tivoli\Security\PermissionDenied('This action is unavailable for this account or count.');
   $data['case']=$c;$files=[];foreach($c['history'] as $ev)if(FinanceAttachment::available(access_service(),'stockcount',(int)$ev['id']))$files[(int)$ev['id']]=true;$data['files']=$files;
  }elseif($page==='owner')$data['rows']=$s->attention($actor)['rows'];elseif($page!=='new'){$list=$s->register($actor,$before);$data['rows']=$list['rows'];$data['next']=$list['next'];}
  access_service()->audit($actor,'stockcount.viewed','stock_count',$id?(string)$id:null,'succeeded',['page'=>$page]);return $data;
 });extract($data,EXTR_OVERWRITE);
}catch(Throwable $e){$error=access_error($e);$case=null;$rows=[];http_response_code($e instanceof \Tivoli\Security\PermissionDenied?403:($e instanceof PDOException?503:400));}
$notice=is_string($_SESSION['stockcount_notice']??null)?$_SESSION['stockcount_notice']:'';unset($_SESSION['stockcount_notice']);
function sc_url(array $q=[]):string{return access_url('stock-counts.php'.($q?'?'.http_build_query($q):''));}
function sc_fields(string $action,array $extra=[]):void{global $key;foreach(['csrf'=>access_csrf(),'operation_key'=>$key,'action'=>$action]+$extra as $n=>$v)echo '<input type="hidden" name="'.access_h($n).'" value="'.access_h($v).'">';}
function sc_value(string $name,string $default=''):string{global $values;return $values[$name]??$default;}
function sc_text(string $label,string $name,bool $required=false,string $value=''):void{echo '<label>'.access_h($label).($required?' *':' (Optional)').'<textarea name="'.access_h($name).'" rows="3" maxlength="2000"'.($required?' required':'').'>'.access_h(sc_value($name,$value)).'</textarea></label>';}
function sc_check(string $name,string $label):void{echo '<label class="sc-check"><input type="checkbox" name="'.access_h($name).'" value="1" required'.(sc_value($name)==='1'?' checked':'').'> <span>'.access_h($label).'</span></label>';}
function sc_upload():void{echo '<label>Evidence (Optional)<input name="evidence" type="file" accept=".pdf,.jpg,.jpeg,.png"><small>PDF, JPEG or PNG · Maximum 2 MB · Private evidence for this submission.</small></label>';}
function sc_status(string $status):string{return match($status){'counting'=>'Counting','ready'=>'Ready To Submit','submitted'=>'Awaiting Review','recount'=>'Recount Requested','superseded'=>'Superseded By Recount',default=>ucfirst($status)};}
function sc_qty(int $n):string{return \Tivoli\Domain\Stay\StayRules::decimal($n);}
function sc_link(string $label,array $q,bool $primary=false,string $anchor='stockcount-action'):void{echo '<a class="sc-button'.($primary?' sc-primary':'').'" href="'.access_h(sc_url($q)).'#'.access_h($anchor).'">'.access_h($label).'</a>';}
require dirname(__DIR__).'/templates/stock-counts/index.php';
