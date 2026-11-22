<?php
namespace Tivoli\Service;
use Tivoli\Domain\Stay\StayRules as R;
use RuntimeException;
use Throwable;

/** Source-specific read adapters. Monetary positions use the existing accounting projections. */
trait OwnerExceptionOperationalFeeds
{
    private function operationalFeeds():void{
        $this->feed('momo','Money',true,fn()=>$this->momoFeed());
        $this->feed('purchase','Approvals',$this->has('bookings.manage','expenses.submit','expenses.manage','stock.manage'),fn()=>$this->purchaseFeed());
        $this->feed('corrections','Corrections',true,fn()=>$this->correctionFeeds());
        $this->feed('payroll','Payroll',$this->has('hr.salary'),fn()=>$this->payrollFeed());
        $this->feed('stock_issue','Stock',$this->has('stock.manage'),fn()=>$this->stockFeed());
        $this->feed('recovery','Money',$this->has('bookings.manage'),fn()=>$this->recoveryFeed());
        $this->feed('legacy_audits','Property',$this->has('bookings.manage','rooms.manage'),fn()=>$this->legacyAuditFeed());
        $this->feed('handover','Money',$this->has('bookings.manage'),fn()=>$this->handoverFeed());
        $this->feed('housekeeping','Property',$this->has('bookings.manage','rooms.manage'),fn()=>$this->housekeepingFeed());
        $this->feed('expenses','Approvals',(new ExpenseService($this->access))->canReview($this->actor),fn()=>$this->expenseFeed());
        $this->feed('providers','Approvals',true,fn()=>$this->providerFeed());
    }
    private function journal():void{if(($this->coverage['journal']??'')!=='available')throw new RuntimeException('Original source journal unavailable.');}
    private function stateEvents(array $history,array $transitions,string $initial):string{
        foreach($history as $e)if(isset($transitions[$e['kind']]))$initial=$transitions[$e['kind']];return $initial;
    }
    private function momoFeed():void{
        $this->journal();$keys=[];
        foreach($this->receipts as $id=>$r)if($r['position']['method']==='momo'&&$r['position']['amount']>0)$keys['payment:'.$id]=['payment',$id];
        foreach($this->financeEvents as $e){if($e['kind']==='income_recorded'&&($e['data']['method']??'')==='momo')$keys['income:'.$e['id']]=['income',(int)$e['id']];if($e['kind']==='receipt_matched'&&$e['target_type']==='payment'&&isset($this->receipts[(int)$e['target_id']]))$keys['payment:'.$e['target_id']]=['payment',(int)$e['target_id']];}
        foreach($keys as $identity=>[$type,$id]){
            if(isset($this->discrepancies[$identity]))continue;
            try{$c=$this->sourceBalance($type,$id);$resolved=$c['remaining']===0;$path=$this->path('finance.php',['page'=>'daily','form'=>'receipt_match','type'=>$type,'id'=>$id,'date'=>substr($c['row']['created_at'],0,10)],'finance-action');
                $this->add($this->row('momo:'.$type.'-'.$id,'Money','MoMo Receipt Evidence',ucfirst($type).' #'.$id,$path,$resolved?($c['amount']===0?'Receipt Reclassified At Source':'Wallet Evidence Verified'):'Awaiting Independent Wallet Evidence',$resolved,true,$this->actor,'',$c['remaining'],['Effective Source Amount'=>$this->money($c['amount']),'Verified Amount'=>$this->money($c['verified'])],$this->history($type,$id)));
            }catch(Throwable){$this->coverage['momo']='unavailable';$this->unavailable('momo:'.$type.'-'.$id,'Money','MoMo Source #'.$id.' · Source Unavailable',$this->path('finance.php',['page'=>'daily']));}
        }
    }
    private function purchaseFeed():void{
        (new PurchaseService($this->access,$this->now()))->permit($this->actor);$this->journal();$costs=[];
        foreach($this->access->repo->run("SELECT id,amount FROM expenses WHERE id IN (SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.expense_id')) AS UNSIGNED) FROM finance_events WHERE kind='purchase_posted' AND target_type='purchase')")->fetchAll() as $e)$costs[(int)$e['id']]=R::minor((string)$e['amount']);
        $this->roots('purchase','Approvals','purchase',['purchase_requested','purchase_unapproved'],function($id,$root)use($costs){
            $d=json_decode($root['payload'],true,512,JSON_THROW_ON_ERROR);$h=$this->history('purchase',$id);$status=$root['kind']==='purchase_unapproved'?'exception':'pending';$receipt=$d['receipt']??null;$expense=0;$paid=0;$markers=[];
            foreach($h as $e){$p=$e['data'];switch($e['kind']){case 'purchase_approved':$status='approved';break;case 'purchase_rejected':$status='rejected';break;case 'purchase_change':$status='change';break;case 'purchase_change_rejected':$status='approved';break;case 'purchase_committed':$status='committed';break;case 'purchase_received':$receipt=$p;$status=$p['exception']?'exception':'declared';break;case 'purchase_disputed':$status='disputed';break;case 'purchase_clarified':$status=($receipt['exception']??true)?'exception':'declared';break;case 'purchase_posted':$expense=(int)$p['expense_id'];$status='posted';break;case 'purchase_paid':$paid+=(int)$p['amount'];$markers[]=$e;break;case 'purchase_cancelled':$status='cancelled';break;}}
            $blockers=[];if($status==='posted'){
                if(!$expense||!isset($costs[$expense]))throw new RuntimeException('Original purchase cost unavailable.');$rows=[];$entries=[];
                foreach($this->history('expense',$expense) as $e){if($e['kind']==='cost_paid')$rows[]=$e;if($e['kind']==='pc_posted'&&($e['data']['source_type']??'')==='purchase'&&(int)$e['data']['source_id']===$id)$entries[]=$e;}
                $position=\Tivoli\Repository\PurchaseAccounting::fold($costs[$expense],$rows,(int)($d['qty']??0),$entries);$blockers=\Tivoli\Repository\PurchaseAccounting::payoutBlockers($markers,$rows,$id);$paid=$blockers?$paid+array_sum(array_column(array_column($entries,'data'),'paid_delta')):$position['paid'];$receipt['cost']=$position['cost'];
            }
            $closed=in_array($status,['rejected','cancelled'],true)||($status==='posted'&&!$blockers&&$paid===$receipt['cost']);$ownerStep=in_array($status,['pending','change','declared','exception','disputed','posted'],true);$amount=$receipt?(int)$receipt['cost']-$paid:(isset($d['estimate'])?(int)$d['estimate']:null);
            $label=match($status){'pending'=>'Spending Approval Required','change'=>'Revised Approval Required','declared','exception','disputed'=>'Delivery / Cost / Payment Evidence Review','posted'=>$blockers?'Settlement Source Requires Review':($paid>$receipt['cost']?'Supplier Recovery Due':($closed?'Settled At Source':'Approved Cost · Settlement Still Due')),'approved'=>'Approved · Awaiting Staff Commitment','committed'=>'Awaiting Delivery',default=>ucfirst($status)};
            return $this->row('purchase:'.$id,'Approvals','Purchase / Provider Request #'.$id,'Request #'.$id,$this->fallbackPath('purchase',$id),$label,$closed,$ownerStep,$ownerStep?$this->actor:(int)$root['actor_id'],'',$amount,['Request'=>$d['title']??'Original Request','Cost Approval'=>'Approval and actual payment verification remain separate source steps.'],[$root,...$h]);
        });
    }
    private function correctionFeeds():void{
        $definitions=[
            ['receipt_correction','rc_requested',ReceiptCorrectionService::class,$this->has('bookings.manage'),'Receipt Correction'],
            ['expense_correction','ec_requested',ExpenseCorrectionService::class,(new ExpenseService($this->access))->canManage($this->actor),'Expense Correction'],
            ['purchase_correction','pc_requested',PurchaseCorrectionService::class,(new ExpenseService($this->access))->canManage($this->actor),'Purchase / Provider Correction'],
            ['guest_charge','gc_requested',GuestChargeCorrectionService::class,$this->has('bookings.manage'),'Guest Charge Correction']
        ];
        foreach($definitions as [$type,$kind,$class,$allowed,$label])$this->feed($type,'Corrections',$allowed,function()use($type,$kind,$class,$label){
            $s=new $class($this->access,$this->now());$s->permit($this->actor);$this->journal();$prefix=substr($kind,0,2);
            $this->roots($type,'Corrections',$type,[$kind],function($id,$root)use($type,$label,$prefix,$s){
                $d=json_decode($root['payload'],true,512,JSON_THROW_ON_ERROR);$history=$this->history($type,$id);$status=$this->stateEvents($history,[$prefix.'_revised'=>'pending',$prefix.'_returned'=>'returned',$prefix.'_approved'=>'posted',$prefix.'_rejected'=>'rejected',$prefix.'_withdrawn'=>'withdrawn'],'pending');$proposal=$d['proposal']??throw new RuntimeException('Original correction proposal unavailable.');foreach($history as $e)if($e['kind']===$prefix.'_revised')$proposal=$e['data']['proposal'];
                $closed=in_array($status,['posted','rejected','withdrawn'],true);$stale=false;
                {
                    $exists=match($type){
                        'receipt_correction'=>$this->access->repo->run('SELECT p.id FROM payments p JOIN bookings b ON b.id=p.booking_id JOIN rooms r ON r.id=b.room_id WHERE p.id=?',[$proposal['payment_id']??0])->fetchColumn(),
                        'expense_correction'=>$this->access->repo->run('SELECT id FROM expenses WHERE id=?',[$proposal['expense_id']??0])->fetchColumn(),
                        'purchase_correction'=>isset($this->sourceIndex[(int)($proposal['source_id']??0)])&&in_array($this->sourceIndex[(int)$proposal['source_id']]['kind'],['purchase_requested','purchase_unapproved','provider_requested'],true),
                        'guest_charge'=>$this->access->repo->run('SELECT b.id FROM bookings b JOIN rooms r ON r.id=b.room_id WHERE b.id=?',[$proposal['booking_id']??0])->fetchColumn(),default=>false};
                    if(!$exists)throw new RuntimeException('Original correction source unavailable.');
                    if($this->selectedKey===$type.':'.$id){$live=$s->detail($this->actor,$id);$stale=!$closed&&$live['stale'];}
                }
                $amount=$proposal['delta']??$proposal['cost_delta']??null;if(!is_int($amount))$amount=null;
                return $this->row($type.':'.$id,'Corrections',$label.' #'.$id,'Original Correction #'.$id,$this->fallbackPath($type,$id),$stale?'Source Changed · Return For Revision':match($status){'pending'=>'Owner Decision Required · Source Recheck Required','returned'=>'Requester Clarification Required','posted'=>'Correction Posted',default=>ucfirst($status)},$closed,$status==='pending',$status==='returned'?(int)$root['actor_id']:$this->actor,'',$amount,['Source Check'=>'Open the original workflow to recheck current source evidence before any decision.','Effect'=>'A recording correction is separate from actual money returned or paid.'],[$root,...$history]);
            });
        });
    }
    private function payrollFeed():void{
        $s=new PayrollService($this->access,$this->now());if(!$s->owner($this->actor))throw new \Tivoli\Security\PermissionDenied('Private payroll authority is required.');
        $this->roots('payroll','Payroll','payroll',['payroll_draft'],function($id)use($s){
            $c=$s->detail($this->actor,$id);$recovery=(int)$c['recovery_due'];$due=$c['due'];$closed=$c['published_current']&&$recovery===0&&!$c['source_errors'];$state=$recovery>0?'Private Recovery Due · Actual Recovery Workflow Required':($c['source_errors']?'Private Source Review Required':match($c['status']){'draft'=>'Awaiting Preparation','revision'=>'Returned For Revision','review'=>'Payroll Review Required','approved'=>($due>0?'Approved · Staff Payment Due':'Publish Current Statement'),default=>ucwords($c['status'])});
            return $this->row('payroll:'.$id,'Payroll','Private Payroll #'.$id,'Payroll #'.$id.' · '.$c['root']['data']['period'],$this->fallbackPath('payroll',$id),$closed?'Current Statement Issued':$state,$closed,true,$this->actor,'',$recovery>0?$recovery:($due===null?null:(int)$due),['Privacy'=>'Private owner payroll authority required.','Recovery'=>'A private note cannot record recovered money or authorize a salary deduction.'],[$c['root'],...$c['history']]);
        });
    }
    private function stockFeed():void{
        $s=new StockIssueService($this->access,$this->now());$s->permit($this->actor);if(!$s->owner($this->actor))throw new \Tivoli\Security\PermissionDenied('Owner stock authority is required.');$this->journal();
        $this->roots('stock_issue','Stock','stock_issue',['si_reported'],function($id,$root){
            $d=json_decode($root['payload'],true,512,JSON_THROW_ON_ERROR);$history=$this->history('stock_issue',$id);$status='pending';$qty=(int)$d['qty'];
            foreach($history as $e){$p=$e['data'];switch($e['kind']){case 'si_corrected':$qty=(int)$p['qty_after'];$status=$qty?'pending':'cancelled';break;case 'si_returned':$status='returned';break;case 'si_clarified':$status='pending';break;case 'si_approved':$status='approved';break;case 'si_completed':$status='completed';break;}}
            $closed=in_array($status,['completed','cancelled'],true);$row=$this->row('stock_issue:'.$id,'Stock',StockIssueService::KINDS[$d['kind']].' · Stock Issue #'.$id,'Original Issue #'.$id,$this->fallbackPath('stock_issue',$id),match($status){'pending'=>'Owner Stock Decision Required','returned'=>'Reporter Clarification Required','approved'=>'Approved · Actual Disposition Still Required','completed'=>'Disposition Confirmed',default=>'Cancelled At Source'},$closed,$status!=='returned',$status==='returned'?(int)$root['actor_id']:$this->actor,'',null,['Quantity'=>R::decimal($qty).' Units','Physical Control'=>'Approval does not itself confirm actual disposal or supply.'],[$root,...$history]);$row['quantity']=R::decimal($qty).' Units';return $row;
        });
        $count=new StockCountService($this->access,$this->now());$count->permit($this->actor);
        $this->roots('stock_count','Stock','stock_count',['count_started'],function($id,$root){$h=$this->history('stock_count',$id);$status=$this->stateEvents($h,['count_submitted'=>'submitted','count_disputed'=>'disputed','count_clarified'=>'submitted','count_recount'=>'recount','count_superseded'=>'superseded','count_approved'=>'approved','count_cancelled'=>'cancelled'],'counting');$closed=in_array($status,['approved','superseded','cancelled'],true);return $this->row('stock_count:'.$id,'Stock','Stock Count #'.$id,'Original Count #'.$id,$this->fallbackPath('stock_count',$id),$closed?ucfirst($status).' At Source':ucfirst($status),$closed,in_array($status,['submitted','disputed'],true),in_array($status,['submitted','disputed'],true)?$this->actor:(int)$root['actor_id'],'',null,[],[$root,...$h]);});
    }
    private function recoveryFeed():void{
        $this->journal();(new StayService($this->access,$this->now()))->installed();
        foreach(\Tivoli\Repository\OwnerGuestBalances::read($this->access->repo,$this->financeEvents) as $b){
            $id=(int)$b['id'];if(!empty($b['unavailable'])){$this->coverage['recovery']='unavailable';$this->unavailable('recovery:'.$id,'Money','Original Stay #'.$id.' · Source Unavailable',$this->path('occupancy.php',['booking_id'=>$id],'stay-workspace'));continue;}$report=$b['report'];$status='confirmed';$secured=true;$assignment=[];$h=$this->history('booking',$id);foreach($h as $prior)if($prior['kind']==='departure_reported'&&(!$report||(int)$prior['id']!==(int)$report['id']))foreach($this->history('departure_incident',(int)$prior['id']) as $event)$h[]=$event;
            if($report){$status='pending';$secured=(bool)$report['data']['room_cleared'];foreach($this->history('departure_incident',(int)$report['id']) as $e){$h[]=$e;switch($e['kind']){case 'departure_confirmed':$status='confirmed';$assignment=$e['data'];break;case 'departure_disputed':$status='disputed';break;case 'departure_retracted':$status='retracted';break;case 'departure_secured':$secured=true;break;case 'departure_followup':$assignment=$e['data'];break;}}}
            $unknown=$b['rate_per_night']===null;$provisional=in_array($status,['pending','disputed'],true);$balance=$b['balance'];$closed=$status==='retracted'||(!$unknown&&!$provisional&&$b['frozen']&&$balance===0&&$secured);
            $state=$unknown?'Original Bill Requires Evidence':($provisional?'Departure Awaits Owner Review':(!$b['frozen']?'Departure Snapshot Requires Evidence':($balance>0?'Guest Recovery Due':($balance<0?'Guest Credit / Refund Review':(!$secured?'Room Security Handover Required':'Source Balance Settled')))));
            $facts=['Original Stay'=>'Stay #'.$id,'Room'=>$b['room_name'],'Balance Basis'=>'Original frozen departure plus explicit later receipt, refund and charge-correction legs.','Recovery'=>'Use the existing departed-stay recovery and authorized posting-period controls.'];
            $this->add($this->row('recovery:'.$id,'Money','Departure / Guest Recovery · Room '.$b['room_name'],'Stay #'.$id,$this->path('occupancy.php',['booking_id'=>$id],'stay-workspace'),$state,$closed,true,isset($assignment['owner_id'])?(int)$assignment['owner_id']:$this->actor,$assignment['due']??'',$provisional?null:$balance,$facts,$h));
        }
    }
    private function legacyAuditFeed():void{
        $s=new WeeklyAuditService($this->access,$this->now());$s->requireAuditor($this->actor);$r=$this->access->repo;
        $claimed=array_flip($r->run("SELECT identity_hash FROM finance_claims WHERE scope='weekly_audit_source'")->fetchAll(\PDO::FETCH_COLUMN));$sources=[];foreach($r->run("SELECT id FROM stay_room_audits WHERE result<>'pass' ORDER BY id DESC")->fetchAll(\PDO::FETCH_COLUMN) as $id)$sources[]=['room',(string)$id];
        foreach($r->run("SELECT target_id FROM security_audit WHERE action='stay.area_audit' AND target_type='area_audit' AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.result'))<>'pass' ORDER BY id DESC")->fetchAll(\PDO::FETCH_COLUMN) as $id)$sources[]=['area',$id];
        foreach($sources as [$kind,$id]){if(isset($claimed[hash('sha256',json_encode([$kind,$id],JSON_THROW_ON_ERROR))]))continue;$key='legacy_'.$kind.':'.$id;$path=$this->path('weekly-audits.php',['page'=>$kind,'source_id'=>$id],'audit-action');try{$c=$s->source($this->actor,$kind,$id);$this->add($this->row($key,'Property','Original Audit Finding · '.$c['location'],'Original '.ucfirst($kind).' Audit #'.$id,$path,$c['legacy_completed']?'Historical Completion Retained':'Confirm Original Finding For Follow-Up',$c['legacy_completed'],true,null,$c['legacy_due_date']??'',null,['Original Comment'=>$c['comment']?:'Not Recorded','Original Owner Note'=>$c['legacy_owner']?:'Not Recorded','Identity'=>'No named assignment or owner verification has been inferred.'],[],[],$c['result']==='unsafe'));}catch(Throwable){$this->coverage['legacy_audits']='unavailable';$this->unavailable($key,'Property','Original Audit · Source Unavailable',$path);}}
    }
    private function handoverFeed():void{
        (new HandoverService($this->access,$this->now()))->permit($this->actor);$this->journal();
        $this->roots('handover','Money','handover',['handover_submitted','cash_transfer_sent'],function($id,$root){
            if($root['kind']==='cash_transfer_sent'&&isset($this->discrepancies['handover:'.$id]))return null;$d=json_decode($root['payload'],true,512,JSON_THROW_ON_ERROR);$h=$this->history('handover',$id);$cash=$root['kind']==='cash_transfer_sent';$status='pending';
            foreach($h as $e){if($e['kind']==='handover_acknowledged')$status=!empty($e['data']['discrepancy'])?'exception':'acknowledged';if($e['kind']==='handover_reviewed')$status='acknowledged';if($e['kind']==='cash_transfer_disputed')$status='disputed';if($e['kind']==='cash_transfer_acknowledged')$status='acknowledged';}
            $amount=null;$due='';if($cash){$p=$this->sourceBalance('handover',$id);$amount=$p['remaining'];$due=$this->deadline($p['allocations']);if($p['touched'])$status=$p['remaining']===0?($p['returned']?'resolved_returned':'acknowledged'):'partially_acknowledged';}
            $closed=in_array($status,['acknowledged','resolved_returned'],true);$decision=in_array($status,['exception','disputed'],true);return $this->row('handover:'.$id,'Money',($cash?'Cash Transfer':'Shift Handover').' #'.$id,'Original Handover #'.$id,$this->fallbackPath('handover',$id),$closed?'Acknowledged / Settled At Source':ucwords(str_replace('_',' ',$status)),$closed,$decision,$decision?$this->actor:(int)$d['recipient_id'],$due,$amount,['Custody'=>'Acknowledged custody still retains its original full cash-deposit deadline. Owner follow-up is not acknowledgement or settlement.'],[$root,...$h]);
        });
    }
    private function housekeepingFeed():void{
        (new HousekeepingService($this->access,$this->now()))->permit($this->actor);$this->journal();
        $this->roots('housekeeping','Property','housekeeping',['hk_started'],function($id,$root){$d=json_decode($root['payload'],true,512,JSON_THROW_ON_ERROR);$h=$this->history('housekeeping',$id);$seen=false;$pending=false;$status='cleaning';$inspections=[];$reviews=[];
            foreach($h as $e){$p=$e['data'];switch($e['kind']){case 'hk_exception_requested':$seen=true;$pending=!$p['owner_authorized'];break;case 'hk_exception_decided':$pending=false;break;case 'hk_completed':$status='inspection';break;case 'hk_inspected':$status=$p['decision']==='release'?'released':'returned';$pending=false;if($p['self']){$seen=true;$inspections[(int)$e['id']]=true;}break;case 'hk_followup':$reviews[(int)$p['inspection_id']]=true;break;case 'hk_superseded':$status='superseded';$pending=false;break;}}
            if(!$seen)return null;$pending=$pending&&$status==='inspection';$review=(bool)array_diff_key($inspections,$reviews);$closed=!$pending&&!$review&&in_array($status,['released','superseded'],true);return $this->row('housekeeping:'.$id,'Property','Housekeeping Owner Review #'.$id,'Original Attempt #'.$id,$this->fallbackPath('housekeeping',$id),$pending?'Owner Exception Decision Required':($review?'Owner Follow-Up Review Required':ucfirst($status)),$closed,$pending||$review,$pending||$review?$this->actor:(int)$root['actor_id'],'',null,['Room'=>$d['room_name'],'Safety'=>'Room-cycle, occupancy and independent-inspection conditions are checked again by the existing source workflow.'],[$root,...$h]);
        });
    }
    private function expenseFeed():void{
        foreach($this->access->repo->run('SELECT s.*,u.name actor_name FROM expense_submissions s LEFT JOIN users u ON u.id=s.submitted_by ORDER BY s.id DESC')->fetchAll() as $c){$id=(int)$c['id'];$closed=$c['status']!=='pending';$own=(int)$c['submitted_by']===$this->actor;$h=[['id'=>$id,'kind'=>'expense_submitted','actor_id'=>$c['submitted_by'],'actor_name'=>$c['actor_name'],'created_at'=>$c['submitted_at']]];if($c['reviewed_at'])$h[]=['id'=>$id,'kind'=>'expense_'.$c['status'],'actor_id'=>$c['reviewed_by'],'created_at'=>$c['reviewed_at'],'data'=>['reason'=>$c['review_reason']]];$this->add($this->row('expense:'.$id,'Approvals','Expense Submission #'.$id,'Original Submission #'.$id,$this->path('expenses.php',['status'=>$c['status'],'before'=>(string)($id+1)],'submission-queue'),$closed?ucfirst($c['status']).' At Source':($own?'Different Authorized Reviewer Required':'Expense Approval Required'),$closed,!$own,$own?null:$this->actor,'',R::minor((string)$c['amount']),['Submitted Amount'=>$this->money(R::minor((string)$c['amount'])),'Review Rule'=>'A requester cannot approve their own submission.'],$h));}
    }
    private function providerFeed():void{
        $this->journal();$s=new PurchaseCorrectionService($this->access,$this->now());
        $this->roots('provider','Approvals','provider',['provider_requested'],function($id,$root)use($s){
            $d=json_decode($root['payload'],true,512,JSON_THROW_ON_ERROR);$h=$this->history('request',$id);$state=$this->stateEvents($h,['request_approved'=>'approved','request_rejected'=>'rejected'],'pending');$amount=null;$settled=false;$path=$this->path('finance.php',['page'=>'providers'],'finance-action');
            if($state==='approved'){
                if(!$s->owner($this->actor))throw new \Tivoli\Security\PermissionDenied('Provider settlement source authority is required.');$c=$s->source($this->actor,'provider',$id);$amount=(int)$c['cost']-(int)$c['paid'];$settled=$amount===0&&!$c['money_blockers'];$path=$this->path('financial-corrections.php',['area'=>'purchases','source_type'=>'provider','source_id'=>$id],'purchase-correction-action');$h=[...$h,...$c['followups']];$state=$c['money_blockers']?'Source Review Required':($amount<0?'Provider Recovery Due':($settled?'Settled At Source':'Provider Payment Due'));
            }
            $own=(int)$root['actor_id']===$this->actor;return $this->row('provider:'.$id,'Approvals','Provider Request #'.$id,'Original Provider Request #'.$id,$path,$state==='pending'&&$own?'Different Authorized Reviewer Required':ucwords($state),$state==='rejected'||$settled,!$own||$state!=='pending',$own&&$state==='pending'?null:$this->actor,'',$amount,['Provider'=>$d['payee']??'Original Provider','Money Control'=>'Payment and recovery stay in the existing provider source workflow.'],[$root,...$h]);
        });
    }
}
