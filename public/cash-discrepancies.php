<?php
if(!defined('TIVOLI_CASH_DISCREPANCIES')){http_response_code(403);exit;}
use Tivoli\Service\CashDiscrepancyService;
use Tivoli\Service\FinanceAttachment;
use Tivoli\Domain\Stay\StayRules as R;
$s=new CashDiscrepancyService(access_service());$actor=(int)$user['id'];$owner=$s->owner($actor);$page='discrepancies';
$id=max(0,filter_var($_GET['id']??0,FILTER_VALIDATE_INT)?:0);$sourceId=max(0,filter_var($_GET['source_id']??0,FILTER_VALIDATE_INT)?:0);$sourceType=is_string($_GET['type']??null)?$_GET['type']:'';$before=max(0,filter_var($_GET['before']??0,FILTER_VALIDATE_INT)?:0);
$values=$_SERVER['REQUEST_METHOD']==='POST'?array_filter($_POST,'is_string'):[];$operationKey=$_SERVER['REQUEST_METHOD']==='POST'?($values['operation_key']??''):bin2hex(random_bytes(16));$error='';$case=null;$source=null;$rows=[];$next=0;
try{
 $s->permit($actor);
 if($_SERVER['REQUEST_METHOD']==='POST'){
  access_check_csrf();$input=$_POST;if(isset($values['decision'])){$values['action']=$values['decision'];$input['action']=$values['decision'];unset($input['decision']);}if(isset($_FILES['evidence'])&&($_FILES['evidence']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$input['_evidence']=FinanceAttachment::uploaded($_FILES['evidence']);$out=$s->execute($actor,$values['action']??'',$input,$operationKey);$_SESSION['cd_notice']=$out['replayed']?'This action was already recorded. Its original result is shown.':'Action recorded. Original records and closed snapshots remain unchanged.';header('Location: '.cd_url(['id'=>$out['id']]).'#discrepancy-action');exit;
 }
 $data=access_service()->transaction(function()use($s,$actor,$id,$sourceId,$sourceType,$before){
  if($id){$case=$s->detail($actor,$id);$data=['case'=>$case,'source'=>$case['source']];}
  elseif($sourceId){$source=$s->source($actor,$sourceType,$sourceId);$existing=CashDiscrepancyService::caseId(access_service()->repo,$sourceType,$sourceId);$data=$existing?['case'=>$s->detail($actor,$existing),'source'=>$source]:['source'=>$source];}
  else $data=$s->register($actor,$before);
  access_service()->audit($actor,'cash_discrepancy.viewed','cash_discrepancy',$id?(string)$id:null,'succeeded');return $data;
 });extract($data,EXTR_OVERWRITE);
}catch(Throwable $e){$error=access_error($e);http_response_code($e instanceof \Tivoli\Security\PermissionDenied||http_response_code()===403?403:($e instanceof PDOException?503:400));if($_SERVER['REQUEST_METHOD']==='POST'&&http_response_code()===400){try{if($values['id']??''){$case=$s->detail($actor,(int)$values['id']);$source=$case['source'];}elseif($values['source_id']??'')$source=$s->source($actor,$values['type']??'',(int)$values['source_id']);}catch(Throwable $ignore){$case=null;$source=null;}}}
$notice=is_string($_SESSION['cd_notice']??null)?$_SESSION['cd_notice']:'';unset($_SESSION['cd_notice']);
function cd_url(array $q=[]):string{return access_url('finance.php?'.http_build_query(['page'=>'discrepancies']+$q));}
function cd_money(int $n):string{return 'GHS '.R::decimal($n);}
function cd_fields(string $action):void{global $case,$source,$operationKey;foreach(['csrf'=>access_csrf(),'operation_key'=>$operationKey,'action'=>$action]+($case?['id'=>(string)$case['root']['id'],'version'=>$case['version']]:['type'=>$source['type'],'source_id'=>(string)$source['id'],'source_token'=>$source['token']]) as $key=>$value)echo '<input type="hidden" name="'.access_h($key).'" value="'.access_h($value).'">';}
function cd_value(string $name,string $fallback=''):string{global $values;return $values[$name]??$fallback;}
function cd_reason(string $label='Reason / Evidence'):void{echo '<label>'.$label.' *<textarea name="reason" maxlength="2000" rows="3" required>'.access_h(cd_value('reason')).'</textarea></label>';}
function cd_source_label(string $type):string{return match($type){'payment'=>'Guest MoMo Receipt','income'=>'Activity MoMo Receipt','deposit'=>'Cash → MoMo Deposit','handover'=>'Cash Handover',default=>'Source'};}
function cd_event_label(string $kind):string{return match($kind){'ra_wallet_in'=>'Existing Wallet Proof Allocated Here','ra_wallet_out'=>'Existing Wallet Proof Allocated To Another Receipt','cd_reported'=>'Difference Reported','cd_credit'=>'Wallet Credit Verified','cd_reversed'=>'Credit Reversed / Disputed','cd_acknowledged'=>'Cash Receipt Acknowledged','cd_cash_returned'=>'Actual Cash Returned','cd_followup'=>'Follow-Up Recorded','cd_reopened'=>'Dispute Reopened','cd_evidence_confirmed'=>'Existing Evidence Verified',default=>'Recorded Action'};}
function cd_upload():void{echo '<label>Evidence File (Optional)<input type="file" name="evidence" accept=".pdf,.png,.jpg,.jpeg"><small>PDF, JPEG or PNG · Maximum 2 MB · Restricted Download</small></label>';}
require dirname(__DIR__).'/templates/finance/cash-discrepancies.php';
