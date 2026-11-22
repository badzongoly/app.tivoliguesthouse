<?php
if(!defined('TIVOLI_GUEST_CHARGES')){http_response_code(403);exit;}
require_once dirname(__DIR__).'/bootstrap/stays.php';
use Tivoli\Service\GuestChargeCorrectionService;
use Tivoli\Service\FinanceAttachment;
use Tivoli\Domain\Stay\StayRules as R;
$s=new GuestChargeCorrectionService(access_service());$actor=(int)$user['id'];$owner=$s->owner($actor);$id=max(0,filter_var($_GET['id']??0,FILTER_VALIDATE_INT)?:0);$bookingId=max(0,filter_var($_GET['booking_id']??0,FILTER_VALIDATE_INT)?:0);$before=max(0,filter_var($_GET['before']??0,FILTER_VALIDATE_INT)?:0);$sourceId=max(0,filter_var($_GET['source_id']??0,FILTER_VALIDATE_INT)?:0);$chargeType=is_string($_GET['charge_type']??null)?$_GET['charge_type']:'';$form=is_string($_GET['form']??null)?$_GET['form']:'';$page=is_string($_GET['page']??null)?$_GET['page']:'stays';if(!in_array($page,['stays','requests','review','log','notice'],true))$page='stays';
$values=$_SERVER['REQUEST_METHOD']==='POST'?array_filter($_POST,'is_string'):[];$operationKey=$_SERVER['REQUEST_METHOD']==='POST'?($values['operation_key']??''):bin2hex(random_bytes(16));$error='';$case=null;$source=null;$charges=[];$rows=[];$next=0;$files=[];
try{
 $s->permit($actor);
 if($_SERVER['REQUEST_METHOD']==='POST'){
  access_check_csrf();$input=$values;if(isset($_FILES['evidence'])&&($_FILES['evidence']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$input['_evidence']=FinanceAttachment::uploaded($_FILES['evidence']);$action=$values['action']??'';if($action==='return'&&($values['owner_decision']??'')==='reject')$action='reject';$out=$s->execute($actor,$action,$input,$operationKey);$_SESSION['guest_charge_notice']='Guest charge action recorded.';header('Location: '.access_url('financial-corrections.php?area=charges&id='.$out['id']).'#charge-action');exit;
 }
 $data=access_service()->transaction(function()use($s,$actor,$owner,$id,$bookingId,$chargeType,$sourceId,$page,$before,$form){
  if($id){$c=$s->detail($actor,$id);if($page==='notice'&&$c['status']!=='posted')throw new RuntimeException('Only posted adjustments have notices.');if($form==='revise'&&($c['status']!=='returned'||(int)$c['root']['actor_id']!==$actor))throw new \Tivoli\Security\PermissionDenied('Only requester may revise this returned case.');$files=[];foreach(array_merge([$c['root']],$c['history']) as $e)if(FinanceAttachment::available(access_service(),'guestcharge',(int)$e['id']))$files[(int)$e['id']]=true;return ['case'=>$c,'source'=>$c['live'],'files'=>$files,'bookingId'=>$c['proposal']['booking_id']];}
  if($bookingId)return $chargeType?['source'=>$s->source($actor,$bookingId,$chargeType,$sourceId)]:['charges'=>$s->charges($actor,$bookingId)];
  if($page!=='stays')return $s->register($actor,$page,$before);
  $r=access_service()->repo;$rows=$r->run('SELECT b.id,b.guest_name,b.status,b.checkin_at,b.checkout_at,r.name room_name FROM bookings b JOIN rooms r ON r.id=b.room_id'.($before?' WHERE b.id<?':'').' ORDER BY b.id DESC LIMIT 51',$before?[$before]:[])->fetchAll();$more=count($rows)>50;if($more)array_pop($rows);return ['rows'=>$rows,'next'=>$more?(int)end($rows)['id']:0];
 });extract($data,EXTR_OVERWRITE);
}catch(Throwable $e){$error=access_error($e);http_response_code($e instanceof \Tivoli\Security\PermissionDenied||http_response_code()===403?403:($e instanceof PDOException?503:400));
 // Re-render only an authorized current source/case; keep the durable retry identity.
 if(!in_array(http_response_code(),[403,503],true))try{if($id){$case=$s->detail($actor,$id);$source=$case['live'];$bookingId=$case['proposal']['booking_id'];}elseif($bookingId&&$chargeType)$source=$s->source($actor,$bookingId,$chargeType,$sourceId);}catch(Throwable $ignored){}
}
$notice=$_SESSION['guest_charge_notice']??'';unset($_SESSION['guest_charge_notice']);
function gc_url(array $q=[]):string{return access_url('financial-corrections.php?'.http_build_query(['area'=>'charges']+$q));}
function gc_money(int $v):string{return ($v<0?'-':'').'GHS '.R::decimal(abs($v));}
function gc_fields(string $action):void{global $operationKey,$case,$source;foreach(['csrf'=>access_csrf(),'operation_key'=>$operationKey,'action'=>$action]+($case?['id'=>(string)$case['root']['id'],'version'=>$case['version']]:[])+($source?['booking_id'=>(string)$source['booking']['id'],'charge_type'=>$source['charge_type'],'source_id'=>(string)$source['source_id'],'source_token'=>$source['token']]:[]) as $key=>$value)echo '<input type="hidden" name="'.access_h($key).'" value="'.access_h($value).'">';}
function gc_reason(string $label='Reason',string $value=''):void{global $values;echo '<label>'.access_h($label).' *<textarea name="reason" required maxlength="2000" rows="4">'.access_h($values['reason']??$value).'</textarea></label>';}
$layoutTitle='Guest Charge Corrections & Refunds';require dirname(__DIR__).'/templates/finance/guest-charge-corrections.php';
