<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
if(is_string($_GET['area']??null)&&$_GET['area']==='payroll'){if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;}header('Location: '.access_url('payroll.php?page=corrections'));exit;}
if(is_string($_GET['area']??null)&&$_GET['area']==='purchases'){define('TIVOLI_PURCHASE_CORRECTIONS',true);require __DIR__.'/purchase-corrections.php';return;}
if(is_string($_GET['area']??null)&&$_GET['area']==='charges'){define('TIVOLI_GUEST_CHARGES',true);require __DIR__.'/guest-charge-corrections.php';return;}
if(is_string($_GET['area']??null)&&$_GET['area']==='expenses'){define('TIVOLI_EXPENSE_CORRECTIONS',true);require __DIR__.'/expense-corrections.php';return;}
require_once dirname(__DIR__).'/bootstrap/stays.php';
use Tivoli\Service\ReceiptCorrectionService;
use Tivoli\Service\FinanceAttachment;
use Tivoli\Domain\Stay\StayRules as R;
$s=new ReceiptCorrectionService(access_service());$actor=(int)$user['id'];$owner=$s->owner($actor);
$page=is_string($_GET['page']??null)&&in_array($_GET['page'],['receipts','requests','review','log','period','notice'],true)?$_GET['page']:'receipts';
$id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT)?:0;$paymentId=filter_var($_GET['payment_id']??0,FILTER_VALIDATE_INT)?:0;$before=max(0,filter_var($_GET['before']??0,FILTER_VALIDATE_INT)?:0);
$form=is_string($_GET['form']??null)?$_GET['form']:'';
$values=$_SERVER['REQUEST_METHOD']==='POST'?array_filter($_POST,'is_string'):[];$operationKey=$_SERVER['REQUEST_METHOD']==='POST'?($values['operation_key']??''):bin2hex(random_bytes(16));
$start=is_string($_GET['start']??null)?$_GET['start']:gmdate('Y-m-d',strtotime('monday this week'));$end=is_string($_GET['end']??null)?$_GET['end']:gmdate('Y-m-d');
$error='';$case=null;$source=null;$rows=[];$next=0;$files=[];$periodData=null;
try{
 $s->permit($actor);
 if($_SERVER['REQUEST_METHOD']==='POST'){
  try{access_check_csrf();$input=$values;if(isset($_FILES['evidence'])&&($_FILES['evidence']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$input['_evidence']=FinanceAttachment::uploaded($_FILES['evidence']);
   $action=$values['action']??'';if($action==='return'&&in_array($values['owner_decision']??'',['return','reject'],true))$action=$values['owner_decision'];$result=$s->execute($actor,$action,$input,$operationKey);$_SESSION['correction_notice']='Correction action recorded.';
   header('Location: '.access_url('financial-corrections.php?id='.$result['id']).'#correction-action');exit;
  }catch(Throwable $e){if($e instanceof \Tivoli\Security\PermissionDenied||$e instanceof PDOException||http_response_code()===403)throw $e;$error=access_error($e);http_response_code(400);}
 }
 $data=access_service()->transaction(function()use($s,$actor,$owner,$id,$paymentId,$page,$before,$form,$start,$end){
  $d=[];
  if($id){$d['case']=$s->detail($actor,$id);$d['source']=$d['case']['live'];if($page==='notice'&&$d['case']['status']!=='posted')throw new RuntimeException('Only a posted correction has an adjustment notice.');if($form==='revise'&&($d['case']['status']!=='returned'||(int)$d['case']['root']['actor_id']!==$actor))throw new \Tivoli\Security\PermissionDenied('Only the requester may revise a returned case.');$d['files']=[];foreach(array_merge([$d['case']['root']],$d['case']['history']) as $e)if(FinanceAttachment::available(access_service(),'correction',(int)$e['id']))$d['files'][(int)$e['id']]=true;}
  elseif($paymentId)$d['source']=$s->source($actor,$paymentId);
  elseif($page==='period'){if(!$owner)throw new \Tivoli\Security\PermissionDenied('Only the designated owner may review correction period effects.');foreach([$start,$end] as $date){$dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$dt||$dt->format('Y-m-d')!==$date||$date>gmdate('Y-m-d'))throw new RuntimeException('Choose valid report dates no later than today.');}if($start>$end)throw new RuntimeException('Start date must not follow end date.');$d['periodData']=\Tivoli\Repository\ReceiptAccounting::period(access_service()->repo,$start,$end);$d['rows']=access_service()->repo->run("SELECT id,target_id,amount_minor,effective_date,payload,created_at FROM finance_events WHERE kind='rc_posted' AND effective_date BETWEEN ? AND ? ORDER BY id DESC LIMIT 100",[$start,$end])->fetchAll();}
  elseif($page==='receipts')$d=$s->receipts($actor,$before);else $d=$s->register($actor,$page,$before);
  access_service()->audit($actor,'receipt_correction.viewed','receipt_correction',$id?(string)$id:null,'succeeded',['page'=>$page]);return $d;
 });extract($data,EXTR_OVERWRITE);
}catch(Throwable $e){$error=access_error($e);http_response_code($e instanceof \Tivoli\Security\PermissionDenied||http_response_code()===403?403:($e instanceof PDOException?503:400));}
if($source&&(!$case||$form==='revise'))$source['funding_candidates']=\Tivoli\Service\ReceiptReconciliation::candidates(access_service(),$actor,(int)$source['original']['id']);
$notice=is_string($_SESSION['correction_notice']??null)?$_SESSION['correction_notice']:'';unset($_SESSION['correction_notice']);
function cc_url(array $q=[]):string{return access_url('financial-corrections.php'.($q?'?'.http_build_query($q):''));}
function cc_money(int|string $n):string{return 'GHS '.R::decimal((int)$n);}
function cc_fields(string $action):void{global $operationKey,$case,$source;foreach(['csrf'=>access_csrf(),'operation_key'=>$operationKey,'action'=>$action]+($case?['id'=>(string)$case['root']['id'],'version'=>$case['version']]:[])+($source?['payment_id'=>(string)$source['original']['id'],'source_token'=>$source['token']]:[]) as $n=>$v)echo '<input type="hidden" name="'.access_h($n).'" value="'.access_h($v).'">';}
function cc_text(string $label,string $name='reason',bool $required=true,string $default=''):void{global $values;echo '<label>'.access_h($label).($required?' *':' (Optional)').'<textarea name="'.access_h($name).'" rows="3" maxlength="2000"'.($required?' required':'').'>'.access_h($values[$name]??$default).'</textarea></label>';}
function cc_check(string $name,string $label):void{echo '<label class="cc-check"><input type="checkbox" name="'.access_h($name).'" value="1" required> <span>'.access_h($label).'</span></label>';}
function cc_link(string $label,array $q):void{echo '<a class="cc-button" href="'.access_h(cc_url($q)).'#correction-action">'.access_h($label).'</a>';}
function cc_status(string $s):string{return match($s){'pending'=>'Awaiting Owner','returned'=>'Returned For Revision',default=>ucfirst($s)};}
function cc_person(int $id):string{static $names=[];if(!isset($names[$id]))$names[$id]=(string)(access_service()->repo->run('SELECT name FROM users WHERE id=?',[$id])->fetchColumn()?:('Historical Staff #'.$id));return $names[$id];}
function cc_movement_label(string $kind):string{return match($kind){'deposit_recorded'=>'Actual Cash → MoMo Deposit','cash_transfer_sent'=>'Actual Cash Handover','cd_credit','receipt_matched'=>'Verified Wallet Credit','cd_acknowledged'=>'Partial Cash Acknowledgement','cd_cash_returned'=>'Recorded Cash Return','cd_reversed'=>'Recorded Verification Reversal','ra_wallet_in'=>'Allocated Wallet Proof','ra_wallet_out'=>'Reallocated Wallet Proof',default=>'Linked Money Record'};}
function cc_type(string $s):string{return match($s){'amount'=>'Correct Recorded Amount','method'=>'Correct Method / Reference','duplicate'=>'Duplicate Recorded Receipt',default=>$s};}
require dirname(__DIR__).'/templates/finance/corrections/index.php';
