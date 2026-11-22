<?php
if(!defined('TIVOLI_EXPENSE_CORRECTIONS')){http_response_code(403);exit;}
require_once dirname(__DIR__).'/bootstrap/stays.php';
use Tivoli\Service\ExpenseCorrectionService;
use Tivoli\Service\FinanceAttachment;
use Tivoli\Domain\Stay\StayRules as R;
$s=new ExpenseCorrectionService(access_service());$actor=(int)$user['id'];$owner=$s->owner($actor);
$page=is_string($_GET['page']??null)&&in_array($_GET['page'],['expenses','requests','review','log','period','notice'],true)?$_GET['page']:'expenses';
$id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT)?:0;$expenseId=filter_var($_GET['expense_id']??0,FILTER_VALIDATE_INT)?:0;$before=max(0,filter_var($_GET['before']??0,FILTER_VALIDATE_INT)?:0);
$form=is_string($_GET['form']??null)?$_GET['form']:'';
$values=$_SERVER['REQUEST_METHOD']==='POST'?array_filter($_POST,'is_string'):[];$operationKey=$_SERVER['REQUEST_METHOD']==='POST'?($values['operation_key']??''):bin2hex(random_bytes(16));
$start=is_string($_GET['start']??null)?$_GET['start']:gmdate('Y-m-d',strtotime('monday this week'));$end=is_string($_GET['end']??null)?$_GET['end']:gmdate('Y-m-d');
$error='';$case=null;$source=null;$rows=[];$next=0;$files=[];$periodData=null;
try{
 $s->permit($actor);
 if($_SERVER['REQUEST_METHOD']==='GET'&&$expenseId&&!$id){$linked=\Tivoli\Service\PurchaseService::forExpense(access_service()->repo,$expenseId);if($linked){(new \Tivoli\Service\PurchaseCorrectionService(access_service()))->source($actor,'purchase',$linked);header('Location: '.access_url('financial-corrections.php?area=purchases&source_type=purchase&source_id='.$linked).'#purchase-correction-action');exit;}}
 if($_SERVER['REQUEST_METHOD']==='POST'){
  try{access_check_csrf();$input=$values;if(isset($_FILES['evidence'])&&($_FILES['evidence']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$input['_evidence']=FinanceAttachment::uploaded($_FILES['evidence']);
   $action=$values['action']??'';if($action==='return'&&in_array($values['owner_decision']??'',['return','reject'],true))$action=$values['owner_decision'];$result=$s->execute($actor,$action,$input,$operationKey);$_SESSION['correction_notice']='Correction action recorded.';
   header('Location: '.access_url('financial-corrections.php?area=expenses&id='.$result['id']).'#correction-action');exit;
  }catch(Throwable $e){if($e instanceof \Tivoli\Security\PermissionDenied||$e instanceof PDOException||http_response_code()===403)throw $e;$error=access_error($e);http_response_code(400);}
 }
 $data=access_service()->transaction(function()use($s,$actor,$owner,$id,$expenseId,$page,$before,$form,$start,$end){
  $d=[];
  if($id){$d['case']=$s->detail($actor,$id);$d['source']=$d['case']['live'];if($page==='notice'&&$d['case']['status']!=='posted')throw new RuntimeException('Only a posted correction has an adjustment notice.');if($form==='revise'&&($d['case']['status']!=='returned'||(int)$d['case']['root']['actor_id']!==$actor))throw new \Tivoli\Security\PermissionDenied('Only the requester may revise a returned case.');$d['files']=[];foreach(array_merge([$d['case']['root']],$d['case']['history']) as $e)if(FinanceAttachment::available(access_service(),'expensecorrection',(int)$e['id']))$d['files'][(int)$e['id']]=true;}
  elseif($expenseId)$d['source']=$s->source($actor,$expenseId);
  elseif($page==='period'){if(!$owner)throw new \Tivoli\Security\PermissionDenied('Only the designated owner may review correction period effects.');foreach([$start,$end] as $date){$dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$dt||$dt->format('Y-m-d')!==$date||$date>gmdate('Y-m-d'))throw new RuntimeException('Choose valid report dates no later than today.');}if($start>$end)throw new RuntimeException('Start date must not follow end date.');$d['periodData']=\Tivoli\Repository\ExpenseAccounting::period(access_service()->repo,$start,$end);$d['rows']=access_service()->repo->run("SELECT id,target_id,amount_minor,effective_date,payload,created_at FROM finance_events WHERE kind='ec_posted' AND effective_date BETWEEN ? AND ? ORDER BY id DESC LIMIT 100",[$start,$end])->fetchAll();}
  elseif($page==='expenses')$d=$s->expenses($actor,$before);else $d=$s->register($actor,$page,$before);
  access_service()->audit($actor,'expense_correction.viewed','expense_correction',$id?(string)$id:null,'succeeded',['page'=>$page]);return $d;
 });extract($data,EXTR_OVERWRITE);
}catch(Throwable $e){$error=access_error($e);http_response_code($e instanceof \Tivoli\Security\PermissionDenied||http_response_code()===403?403:($e instanceof PDOException?503:400));}
$categories=(new \Tivoli\Repository\ExpenseRepository(access_service()->repo))->categories();
$notice=is_string($_SESSION['correction_notice']??null)?$_SESSION['correction_notice']:'';unset($_SESSION['correction_notice']);
function ec_url(array $q=[]):string{return access_url('financial-corrections.php?'.http_build_query(['area'=>'expenses']+$q));}
function ec_money(int|string|null $n):string{return $n===null?'Settlement Unknown':'GHS '.R::decimal((int)$n);}
function ec_fields(string $action):void{global $operationKey,$case,$source;foreach(['csrf'=>access_csrf(),'operation_key'=>$operationKey,'action'=>$action]+($case?['id'=>(string)$case['root']['id'],'version'=>$case['version']]:[])+($source?['expense_id'=>(string)$source['original']['id'],'source_token'=>$source['token']]:[]) as $n=>$v)echo '<input type="hidden" name="'.access_h($n).'" value="'.access_h($v).'">';}
function ec_text(string $label,string $name='reason',bool $required=true,string $default=''):void{global $values;echo '<label>'.access_h($label).($required?' *':' (Optional)').'<textarea name="'.access_h($name).'" rows="3" maxlength="2000"'.($required?' required':'').'>'.access_h($values[$name]??$default).'</textarea></label>';}
function ec_check(string $name,string $label):void{echo '<label class="cc-check"><input type="checkbox" name="'.access_h($name).'" value="1" required> <span>'.access_h($label).'</span></label>';}
function ec_link(string $label,array $q):void{echo '<a class="cc-button" href="'.access_h(ec_url($q)).'#correction-action">'.access_h($label).'</a>';}
function ec_status(string $s):string{return match($s){'pending'=>'Awaiting Owner','returned'=>'Returned For Revision',default=>ucfirst($s)};}
function ec_type(string $s):string{return match($s){'amount'=>'Correct Recorded Amount','method'=>'Correct Method / Reference','duplicate'=>'Duplicate Recorded Receipt',default=>$s};}
function ec_effect(array $p):void{
 echo '<div class="cc-scroll"><table><thead><tr><th>Measure</th><th>Before</th><th>After</th></tr></thead><tbody>';
 foreach(['amount'=>'Recorded Cost','paid'=>'Recorded Payout'] as $key=>$label)echo '<tr><td>'.$label.'</td><td>'.access_h(ec_money($p['before'][$key])).'</td><td>'.access_h(ec_money($p['after'][$key])).'</td></tr>';
 echo '<tr><td>Method / Reference</td><td>'.access_h(strtoupper($p['before']['method']).' · '.$p['before']['reference']).'</td><td>'.access_h(strtoupper($p['after']['method']).' · '.$p['after']['reference']).'</td></tr>';
 foreach(['before','after'] as $side)$names[$side]=access_service()->repo->run('SELECT name FROM expense_categories WHERE id=?',[$p[$side]['category_id']])->fetchColumn();
 echo '<tr><td>Category</td><td>'.access_h($names['before']).'</td><td>'.access_h($names['after']).'</td></tr></tbody></table></div><p>Expense Adjustment '.access_h(ec_money($p['cost_delta'])).' · Management Profit Effect '.access_h(ec_money(-$p['cost_delta'])).' · Recorded Payout Adjustment '.access_h(ec_money($p['paid_delta'])).'</p><p>Cash '.access_h(ec_money($p['cash_delta'])).' / MoMo '.access_h(ec_money($p['momo_delta'])).' · Actual Money Moved GHS 0.00.</p>';
}
require dirname(__DIR__).'/templates/finance/expense-corrections.php';
