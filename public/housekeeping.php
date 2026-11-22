<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
require_once dirname(__DIR__).'/bootstrap/stays.php';
use Tivoli\Service\HousekeepingService;
use Tivoli\Service\FinanceAttachment;
$s=new HousekeepingService(access_service());$actor=(int)$user['id'];$owner=$s->owner($actor);
$page=is_string($_GET['page']??null)&&in_array($_GET['page'],['board','owner','activity'],true)?$_GET['page']:'board';
$id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT)?:0;$roomId=filter_var($_GET['room_id']??0,FILTER_VALIDATE_INT)?:0;$before=max(0,filter_var($_GET['before']??0,FILTER_VALIDATE_INT)?:0);$reviewId=filter_var($_GET['inspection_id']??0,FILTER_VALIDATE_INT)?:0;
$form=is_string($_GET['form']??null)?$_GET['form']:'';$values=$_SERVER['REQUEST_METHOD']==='POST'?array_filter($_POST,'is_string'):[];$key=preg_match('/^[a-f0-9]{32}$/D',$values['operation_key']??'')?$values['operation_key']:bin2hex(random_bytes(16));
$error='';$case=null;$room=null;$rows=[];$next=0;$attention=[];$files=[];
try{
 $s->permit($actor);if($page==='owner'&&!$owner)throw new \Tivoli\Security\PermissionDenied('Only Eugene may open the owner review queue.');
 if($_SERVER['REQUEST_METHOD']==='POST'){
  access_check_csrf();$action=$values['action']??'';$input=$values;
  if($action==='progress'&&!isset($input['done']))$input['done']=json_encode(isset($_POST['done'])&&is_array($_POST['done'])?array_values($_POST['done']):[],JSON_THROW_ON_ERROR);
  if($action==='inspect'&&!isset($input['results'])){$results=[];foreach($values as $name=>$value)if(str_starts_with($name,'result_'))$results[substr($name,7)]=$value;$input['results']=json_encode($results,JSON_THROW_ON_ERROR);}
  if(($action==='inspect'||$action==='progress'&&($input['direction']??'')==='complete')&&isset($_FILES['evidence'])&&($_FILES['evidence']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$input['_evidence']=FinanceAttachment::uploaded($_FILES['evidence']);
  $result=$s->execute($actor,$action,$input,$key);$_SESSION['housekeeping_notice']='Action recorded. Room state, identities and original evidence are retained.';$query=['id'=>$result['id']];
  if(in_array($action,['start','restart'],true)||$action==='progress'&&!in_array($input['direction']??'',['complete','pause'],true))$query['form']='clean';
  header('Location: '.access_url('housekeeping.php?'.http_build_query($query)).'#housekeeping-action');exit;
 }
}catch(Throwable $e){$error=access_error($e);http_response_code($e instanceof \Tivoli\Security\PermissionDenied||http_response_code()===403?403:($e instanceof PDOException?503:400));}
if(http_response_code()!==403)try{
 $data=access_service()->transaction(function()use($s,$actor,$id,$roomId,$page,$before,$form,$owner,$reviewId){
  $data=[];
  if($id||$roomId){
   if($id){$c=$s->detail($actor,$id);$room=$c['room'];}else{$room=$s->room($actor,$roomId);$c=$room['latest']?$s->detail($actor,$room['latest']):null;}
   if($form){
    $cleaner=$c&&(int)$c['root']['actor_id']===$actor;
    $allowed=$c&&match($form){'clean'=>$cleaner&&$c['valid']&&$c['status']==='cleaning','inspect'=>$c['valid']&&$c['status']==='inspection'&&(!$cleaner||($c['exception']['status']??'')==='approved'),'exception'=>$cleaner&&$c['valid']&&$c['status']==='inspection'&&!in_array($c['exception']['status']??'',['approved','pending'],true),'decision'=>$owner&&$c['valid']&&($c['exception']['status']??'')==='pending','followup'=>$owner&&(bool)array_filter($c['followups'],fn($e)=>(int)$e['id']===$reviewId),'restart'=>$owner&&$c['current']&&in_array($c['status'],['cleaning','inspection'],true),default=>false};
    if(!$allowed)throw new \Tivoli\Security\PermissionDenied('This action is unavailable for your account or this attempt.');
   }
   $data['case']=$c;$data['room']=$room;$files=[];foreach($c['history']??[] as $ev)if(FinanceAttachment::available(access_service(),'housekeeping',(int)$ev['id']))$files[(int)$ev['id']]=true;$data['files']=$files;
  }elseif($page==='owner')$data['attention']=$s->attention($actor);else{$list=$page==='activity'?$s->history($actor,$before):$s->board($actor,$before);$data['rows']=$list['rows'];$data['next']=$list['next'];}
  access_service()->audit($actor,'housekeeping.viewed','housekeeping',$id?(string)$id:null,'succeeded',['page'=>$page]);return $data;
 });extract($data,EXTR_OVERWRITE);
}catch(Throwable $e){$error=access_error($e);$case=null;$room=null;$rows=[];http_response_code($e instanceof \Tivoli\Security\PermissionDenied?403:($e instanceof PDOException?503:400));}
$notice=is_string($_SESSION['housekeeping_notice']??null)?$_SESSION['housekeeping_notice']:'';unset($_SESSION['housekeeping_notice']);
function h_url(array $q=[]):string{return access_url('housekeeping.php'.($q?'?'.http_build_query($q):''));}
function h_fields(string $action,array $extra=[]):void{global $key;foreach(['csrf'=>access_csrf(),'operation_key'=>$key,'action'=>$action]+$extra as $name=>$value)echo '<input type="hidden" name="'.access_h($name).'" value="'.access_h($value).'">';}
function h_value(string $name,string $default=''):string{global $values;return $values[$name]??$default;}
function h_text(string $label,string $name='comment',bool $required=false,string $value=''):void{echo '<label>'.access_h($label).($required?' *':' (Optional)').'<textarea name="'.access_h($name).'" rows="3" maxlength="2000"'.($required?' required':'').'>'.access_h(h_value($name,$value)).'</textarea></label>';}
function h_check(string $name,string $label):void{echo '<label class="hk-check"><input name="'.access_h($name).'" type="checkbox" value="1" required'.(h_value($name)==='1'?' checked':'').'><span>'.access_h($label).'</span></label>';}
function h_upload():void{echo '<label>Photo / Evidence (Optional)<input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png"><small>PDF, JPEG or PNG · Up to 2 MB · Authorized housekeeping staff only.</small></label>';}
function h_link(string $label,array $query,bool $primary=false):void{echo '<a class="hk-button'.($primary?' hk-primary':'').'" href="'.access_h(h_url($query)).'#housekeeping-action">'.access_h($label).'</a>';}
function h_status(array $view):string{$f=$view['facts'];if(!$f)return 'Room No Longer Available';if($f['occupied'])return 'Occupied';if($f['blocked'])return 'Blocked';return match($f['ready_state']){'ready'=>'Ready / Available','awaiting_inspection'=>'Awaiting Inspection','cleaning'=>'Needs Cleaning',default=>'Needs Preparation'};}
function h_attempt_status(string $status):string{return match($status){'inspection'=>'Awaiting Inspection','released'=>'Released Ready / Available','returned'=>'Returned For Cleaning',default=>ucfirst($status)};}
require dirname(__DIR__).'/templates/housekeeping/index.php';
