<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
require_once dirname(__DIR__).'/bootstrap/stays.php';
use Tivoli\Service\FinanceService;
use Tivoli\Domain\Stay\StayRules as R;
if(($_GET['page']??'')==='discrepancies'){define('TIVOLI_CASH_DISCREPANCIES',true);require __DIR__.'/cash-discrepancies.php';exit;}
$s=new FinanceService(access_service());$actor=(int)$user['id'];$admin=$s->isAdmin($actor);$salary=$admin&&in_array('hr.salary',access_service()->repo->privileges($actor),true);
$page=is_string($_GET['page']??null)?$_GET['page']:'overview';$pages=['overview'=>'Money Overview','daily'=>'Daily Reconciliation','weekly'=>'Weekly Reconciliation','payments'=>'Payment Log','income'=>'Other Income','deposits'=>'Cash → MoMo Deposits','expenses'=>'Expenses','payroll'=>'Payroll & Staff Cost','providers'=>'Provider Payments','revenue'=>'Room & Extras Revenue','profit'=>'Profit Review','activity'=>'Audit Trail'];
if($page==='corrections'&&in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){header('Location: '.access_url('financial-corrections.php'));exit;}
if(!isset($pages[$page]))$page='overview';$error='';$notice='';$data=[];$events=[];$cash=[];$unmatched=[];$staff=[];$expenses=[];$form=is_string($_GET['form']??null)?$_GET['form']:'';$id=max(0,(int)($_GET['id']??0));$type=is_string($_GET['type']??null)?$_GET['type']:'';
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&$form==='payroll_request'){header('Location: '.access_url('payroll.php').'#payroll-action');exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&$form==='provider_request'){header('Location: '.access_url('purchases.php?page=new&type=provider').'#purchase-action');exit;}
$today=$s->today();$period=is_string($_GET['period']??null)&&$_GET['period']==='month'?'month':'week';$date=is_string($_GET['date']??null)?$_GET['date']:$today;$start=is_string($_GET['start']??null)?$_GET['start']:(new DateTimeImmutable($today))->modify($period==='month'?'first day of this month':'monday this week')->format('Y-m-d');
$canLog=in_array('payments.view',access_service()->repo->privileges($actor),true);$paymentLog=[];$incomeRows=[];
$costing=[];$costingRows=[];$costingSelected=null;$costingCounts=[];$costingId=max(0,(int)($_GET['costing_id']??0));$ready=false;$weekly=[];$operationKey=bin2hex(random_bytes(16));
try{
 $s->permit($actor,in_array($page,['expenses','payroll','revenue','profit','activity'],true),$page==='payroll');
 if($page==='payments'&&!$canLog){access_service()->audit($actor,'finance.payment_log_denied','finance',null,'denied');throw new \Tivoli\Security\PermissionDenied('You cannot access the Payment Log.');}
 if($page==='weekly'&&!$s->canPostLate($actor)){access_service()->audit($actor,'finance.weekly_access_denied','finance',null,'denied');throw new \Tivoli\Security\PermissionDenied('Only the designated finance owner may view Weekly Reconciliation.');}
 if($_SERVER['REQUEST_METHOD']==='GET'&&$form==='pay_cost'&&$type==='request'&&\Tivoli\Repository\PurchaseAccounting::entries(access_service()->repo,'provider',$id)){(new \Tivoli\Service\PurchaseCorrectionService(access_service()))->source($actor,'provider',$id);header('Location: '.access_url('financial-corrections.php?area=purchases&source_type=provider&source_id='.$id).'#purchase-correction-action');exit;}
 $ready=$s->installed();if(!$ready)throw new RuntimeException('Finance is built but not activated in this database. An administrator must complete the reviewed setup before recording finance events.');
 if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&in_array($form,['deposit_confirm','receipt_match'],true)){$sourceKind=$form==='deposit_confirm'?'deposit':($type?:'payment');$dispute=\Tivoli\Service\CashDiscrepancyService::caseId(access_service()->repo,$sourceKind,$id);if($dispute){header('Location: '.access_url('finance.php?page=discrepancies&id='.$dispute).'#discrepancy-action');exit;}if(\Tivoli\Service\CashDiscrepancyService::hasWalletAllocation(access_service()->repo,$sourceKind,$id)||($form==='receipt_match'&&access_service()->repo->run("SELECT id FROM finance_events WHERE kind='receipt_matched' AND target_type=? AND target_id=? LIMIT 1",[$sourceKind,(string)$id])->fetchColumn())){header('Location: '.access_url('finance.php?page=discrepancies&type='.$sourceKind.'&source_id='.$id).'#discrepancy-action');exit;}}
 foreach([$date,$start] as $d){$p=DateTimeImmutable::createFromFormat('!Y-m-d',$d);if(!$p||$p->format('Y-m-d')!==$d||$d>$today)throw new RuntimeException('Choose valid dates no later than today.');}
 if($page==='weekly'){$period='week';$start=is_string($_GET['start']??null)?$start:$s->priorWeekStart();}
 else $start=(new DateTimeImmutable($start))->modify($period==='month'?'first day of this month':'monday this week')->format('Y-m-d');
 if($_SERVER['REQUEST_METHOD']==='POST'){
  access_check_csrf();if(($_POST['action']??'')==='payroll_request'){access_service()->audit($actor,'payroll.obsolete_form','finance',null,'denied');http_response_code(409);throw new RuntimeException('Prepare new payroll through Payroll & Staff Payments. Retained legacy records remain available.');}if(($_POST['action']??'')==='provider_request'){access_service()->audit($actor,'purchase.obsolete_provider_form','finance',null,'denied');http_response_code(409);throw new RuntimeException('Create new service requests through Purchase Requests. Existing provider records remain available.');}$input=$_POST;if(is_string($_POST['action']??null)&&str_starts_with($_POST['action'],'costing_')){if($page!=='profit')throw new RuntimeException('Use Stock Costing in Profit Review.');(new \Tivoli\Service\InventoryCostingService(access_service()))->execute($actor,substr($_POST['action'],8),$input,is_string($_POST['operation_key']??null)?$_POST['operation_key']:'');$_SESSION['finance_notice']='Stock cost evidence recorded. Existing quantities, purchases and payments are retained.';header('Location: '.access_url('finance.php?page=profit&costing_id='.(int)($_POST['extra_id']??0)).'#costing-action');exit;}if(isset($_FILES['evidence'])&&($_FILES['evidence']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$input['_evidence']=\Tivoli\Service\FinanceAttachment::uploaded($_FILES['evidence']);$s->execute($actor,is_string($_POST['action']??null)?$_POST['action']:'',$input,is_string($_POST['operation_key']??null)?$_POST['operation_key']:'');
  $_SESSION['finance_notice']='Action recorded. Your identity, time and source links are retained.';
  header('Location: '.access_url('finance.php?'.http_build_query(['page'=>$page,'date'=>$date,'period'=>$period,'start'=>$start])).'#finance-status');exit;
 }
 if($page==='profit'&&$costingId){$cs=new \Tivoli\Service\InventoryCostingService(access_service());$cs->permit($actor);$costingSelected=$cs->item($actor,$costingId);$costingCounts=access_service()->repo->run("SELECT id,target_id,created_at FROM finance_events WHERE kind='count_approved' AND target_type='stock_count' ORDER BY id DESC LIMIT 100")->fetchAll();}
 if($page==='profit'&&$s->canPostLate($actor))$costingRows=(new \Tivoli\Service\InventoryCostingService(access_service()))->register($actor,is_string($_GET['costing_search']??null)?$_GET['costing_search']:'');
 $notice=is_string($_SESSION['finance_notice']??null)?$_SESSION['finance_notice']:'';unset($_SESSION['finance_notice']);
 $snapshot=access_service()->transaction(function()use($s,$actor,$admin,$page,$start,$period,$date,$today,$salary){
  $end=min($today,(new DateTimeImmutable($start))->modify($period==='month'?'last day of this month':'+6 days')->format('Y-m-d'));
  $r=$s->repo;
  if($page==='weekly'){
   $review=$s->weeklyReview($start);
   $cases=(new \Tivoli\Service\StayService(access_service()))->departureCases($actor);
   $review['departure_incidents']=count(array_filter($cases,fn($row)=>!$row['resolved']));
   $review['assignees']=!$review['close']&&$review['pending']?$r->run('SELECT u.id,u.name FROM users u JOIN security_accounts a ON a.user_id=u.id WHERE a.active=1 ORDER BY u.name,u.id')->fetchAll():[];
   access_service()->audit($actor,'finance.viewed','finance',null,'succeeded',['page'=>$page,'week_start'=>$start]);
   return ['weekly'=>$review];
  }
  $cash=$s->cash();$cash['custodian_names']=$r->run('SELECT id,name FROM users ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);$events=array_values(array_filter($s->events(),fn($e)=>!str_starts_with($e['kind'],'cd_')&&!str_starts_with($e['kind'],'ra_')&&!str_starts_with($e['kind'],'gc_')&&!str_starts_with($e['kind'],'pc_')&&$e['kind']!=='payroll_adjustment'&&!in_array($e['target_type'],['payroll','receipt_correction','expense_correction'],true)));
  if(!$s->canPostLate($actor)){
   foreach($events as &$privateEvent)unset($privateEvent['data']['snapshot']['stock_costing']);unset($privateEvent);
   $events=array_values(array_filter($events,fn($event)=>!in_array($event['target_type'],['inventory_cost','handover','purchase','stock_count','housekeeping','receipt_correction'],true)));
   foreach($events as &$financeEvent)if($financeEvent['kind']==='cost_paid'&&(isset($financeEvent['data']['purchase_id'])||isset($financeEvent['data']['provider_source_id']))){
    $financeEvent['data']=array_intersect_key($financeEvent['data'],array_flip(['purchase_id','provider_source_id','method','cash_funding']));
    $financeEvent['data']['private_details']='Purchase / provider evidence is restricted to the requester and designated owner.';
   }unset($financeEvent);
  }
  if($admin&&!$s->canPostLate($actor))$events=array_values(array_filter($events,fn($e)=>($e['data']['workflow']??'')!=='weekly_reconciliation'&&$e['kind']!=='week_case_resolved'));
  if(!$salary){
   $privateRequests=[];$privatePayouts=[];
   foreach($events as $e)if($e['kind']==='payroll_requested')$privateRequests[(string)$e['id']]=true;
   foreach($events as $e)if($e['kind']==='cost_paid'&&$e['target_type']==='request'&&isset($privateRequests[$e['target_id']]))$privatePayouts[(string)$e['id']]=true;
   $events=array_values(array_filter($events,fn($e)=>$e['kind']!=='payroll_requested'
       && !($e['target_type']==='request'&&isset($privateRequests[$e['target_id']]))
       && !($e['target_type']==='payout'&&isset($privatePayouts[$e['target_id']]))));
  }
  if(!$admin){$cash['rows']=array_values(array_filter($cash['rows'],fn($r)=>(int)$r['actor_id']===$actor||array_key_exists($actor,$r['custody'])));foreach($cash['rows'] as &$cashRow)$cashRow['held']=$cashRow['custody'][$actor]??0;unset($cashRow);$cash['held']=$cash['custodians'][$actor]['held']??0;$owned=[];foreach($events as $e)if((int)$e['actor_id']===$actor&&in_array($e['kind'],['deposit_recorded','provider_requested','income_recorded'],true))$owned[(string)$e['id']]=true;$events=array_values(array_filter($events,fn($e)=>isset($owned[(string)$e['id']])||(in_array($e['target_type'],['deposit','request','income'],true)&&isset($owned[$e['target_id']])&&in_array($e['kind'],['deposit_confirmed','request_approved','request_rejected','cost_paid','income_completed','receipt_matched'],true))));}
  $staff=$salary?$r->run("SELECT id,staff_no,data FROM hr_staff WHERE status='active' ORDER BY id")->fetchAll():[];
  $expenses=$admin?$r->run('SELECT e.*,c.name category_name FROM expenses e JOIN expense_categories c ON c.id=e.category_id WHERE e.id>(SELECT expense_cursor FROM finance_schema WHERE id=1) ORDER BY e.id DESC')->fetchAll():[];
  access_service()->audit($actor,'finance.viewed','finance',null,'succeeded',['page'=>$page,'salary_authorized'=>$salary]);
  $periodSummary=$admin?$s->summary($page==='daily'?$date:$start,$page==='daily'?$date:$end):[];$costing=[];
  if($page==='profit'&&$s->canPostLate($actor)){
   $cs=new \Tivoli\Service\InventoryCostingService(access_service());$cs->permit($actor);$costing=$cs->period($actor,$start,$end,$periodSummary['profit']);
   $saved=$r->run("SELECT kind,payload FROM finance_events WHERE target_type='period' AND target_id=? AND kind IN ('period_locked','period_reopened') ORDER BY id DESC LIMIT 1",[$period.':'.$start])->fetch();if($saved&&$saved['kind']==='period_locked'){$d=json_decode($saved['payload'],true,512,JSON_THROW_ON_ERROR);$costing=isset($d['snapshot']['stock_costing'])?$d['snapshot']['stock_costing']+['retained'=>true]:['saved_missing'=>true];}
  }
  return ['costing'=>$costing,'payment_log'=>$page==='payments'?\Tivoli\Service\FinancePaymentLog::read(access_service(),$actor,$_GET):[],'income_rows'=>array_values(array_filter($events,fn($e)=>$e['kind']==='income_recorded')),'cash'=>$cash,'events'=>$events,'staff'=>$staff,'expenses'=>$expenses,'summary'=>$periodSummary,'unmatched'=>$admin?$s->unmatched($date):[],'activation'=>$r->run('SELECT * FROM finance_schema WHERE id=1')->fetch()];
 });
 if($page==='weekly')$weekly=$snapshot['weekly'];
 else{
 $costing=$snapshot['costing'];$paymentLog=$snapshot['payment_log'];$incomeRows=$snapshot['income_rows'];$cash=$snapshot['cash'];$events=$snapshot['events'];$staff=$snapshot['staff'];$expenses=$snapshot['expenses'];$data=$snapshot['summary'];$unmatched=$snapshot['unmatched'];
 }
}catch(Throwable $e){if($_SERVER['REQUEST_METHOD']==='POST'){try{access_service()->audit($actor,'finance.form_rejected','finance',null,'denied',['evidence_attempted'=>isset($_FILES['evidence'])]);}catch(Throwable $auditError){http_response_code(503);}}$error=access_error($e);if($e instanceof \Tivoli\Security\PermissionDenied)http_response_code(403);elseif($e instanceof PDOException)http_response_code(503);elseif(!in_array(http_response_code(),[403,409,503],true))http_response_code(400);if($_SERVER['REQUEST_METHOD']==='POST'&&is_string($_POST['operation_key']??null)&&preg_match('/^[a-f0-9]{32}$/D',$_POST['operation_key']))$operationKey=$_POST['operation_key'];}
function finance_url(array $q=[]):string{return access_url('finance.php?'.http_build_query($q));}
function fm(int|string $n):string{return '₵'.R::decimal((int)$n);}
function ff(string $action,array $fields=[]):void{global $operationKey;foreach(['csrf'=>access_csrf(),'operation_key'=>$operationKey,'action'=>$action]+$fields as $key=>$value)echo '<input type="hidden" name="'.access_h($key).'" value="'.access_h($value).'">';}
function fi(string $label,string $name,string $default='',string $type='text',bool $required=true):void{$value=($_SERVER['REQUEST_METHOD']==='POST'&&is_string($_POST[$name]??null))?$_POST[$name]:$default;echo '<label>'.access_h($label).($required?' *':'').'<input name="'.access_h($name).'" type="'.access_h($type).'" value="'.access_h($value).'" maxlength="500"'.($required?' required':'').'></label>';}
function fu(string $label,bool $required=false):void{echo '<label>'.access_h($label).($required?' *':'').'<input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png"'.($required?' required':'').'><small>PDF, JPEG or PNG · Maximum 2 MB. Private download only.</small></label>';}
function fc(string $text,string $name):void{echo '<label class="finance-check"><input type="checkbox" name="'.access_h($name).'" value="1" required> '.access_h($text).'</label>';}
function finance_action(string $label,string $form,int|string $id=0,string $type=''):void{global $page,$start;echo '<a class="finance-action" href="'.access_h(finance_url(['page'=>$page,'form'=>$form,'id'=>$id,'type'=>$type]+($page==='weekly'?['start'=>$start]:[]))).'#finance-action">'.access_h($label).'</a>';}
require dirname(__DIR__).'/templates/finance/index.php';
