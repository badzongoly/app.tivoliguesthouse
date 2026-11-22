<?php
namespace Tivoli\Service;
use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Security\PermissionDenied;

/** Authorized current facts from existing writers; no independent approval/settlement state. */
trait OwnerExceptionFeeds
{
    private function collect():void{
        $f=new FinanceService($this->access,$this->now());
        $this->feed('journal','Money',true,function()use($f){$this->financeEvents=\Tivoli\Repository\ReceiptReallocation::events($f->events());$this->eventIndex=[];$this->sourceIndex=[];$this->caseEvents=[];foreach($this->financeEvents as $e){$this->eventIndex[$e['target_type']][(string)$e['target_id']][]=$e;$this->sourceIndex[(int)$e['id']]=$e;if(isset($e['data']['case_id'])&&(str_starts_with($e['kind'],'cd_')||in_array($e['kind'],['ra_wallet_in','ra_wallet_out'],true)))$this->caseEvents[(int)$e['data']['case_id']][(int)$e['id']]=$e;}$this->resolutionIndex=\Tivoli\Domain\Finance\CashResolution::index($this->financeEvents);});
        $this->feed('receipt_positions','Money',true,function(){
            $rows=$this->access->repo->run('SELECT p.id,p.amount,p.method,p.created_at,s.actor_id,s.reference_no FROM payments p LEFT JOIN stay_receipts s ON s.payment_id=p.id WHERE p.id>(SELECT payment_cursor FROM finance_schema WHERE id=1)')->fetchAll();$this->receipts=[];foreach(\Tivoli\Repository\ReceiptAccounting::project($this->access->repo,$rows) as $r)$this->receipts[(int)$r['id']]=$r;
        });
        $this->feed('cash','Money',true,function()use($f){$f->permit($this->actor);$this->cash=$f->cash();$this->deadlineIndex=[];foreach($this->cash['rows'] as $row)$this->deadlineIndex[(string)$row['id']]=$row['due'];});
        $this->feed('discrepancy','Money',true,fn()=>$this->discrepancyFeed());
        $this->feed('cash_sources','Money',$this->coverage['cash']==='available',fn()=>$this->cashFeed());
        $this->feed('weekly','Property',$this->has('bookings.manage','rooms.manage'),fn()=>$this->weeklyFeed());
        $this->otherFeeds();
    }
    private function deadline(array $allocations):string{
        $dues=[];foreach($allocations as $source=>$amount)if(isset($this->deadlineIndex[(string)$source]))$dues[]=$this->deadlineIndex[(string)$source];return $dues?min($dues):'';
    }
    private function discrepancyFeed():void{
        $this->journal();(new CashDiscrepancyService($this->access,$this->now()))->permit($this->actor);
        $this->roots('discrepancy','Money','cash_discrepancy',['cd_reported'],function($id,$root){
            $d=json_decode($root['payload'],true,512,JSON_THROW_ON_ERROR);$type=$d['source_type'];$sourceId=(int)$d['source_id'];$this->discrepancies[$type.':'.$sourceId]=$id;
            $source=$this->sourceBalance($type,$sourceId);$history=$this->caseEvents[$id]??[];foreach($this->history('cash_discrepancy',$id) as $e)$history[(int)$e['id']]=$e;ksort($history);$history=array_values($history);$confirmed=false;$assignee=null;$due=$type==='deposit'||$type==='handover'?$this->deadline($source['allocations']):'';
            foreach($history as $e){if($e['kind']==='cd_reopened')$confirmed=false;if(in_array($e['kind'],['cd_credit','cd_reversed','cd_acknowledged','cd_cash_returned','cd_evidence_confirmed'],true))$confirmed=true;if(isset($e['data']['assignee_id']))$assignee=(int)$e['data']['assignee_id'];if(isset($e['data']['due']))$due=$e['data']['due'];}
            $status=!$confirmed?'review_required':($source['remaining']===0?'resolved':($source['verified']>0?'partial':'open'));
            $facts=['Original Source'=>ucfirst($type).' #'.$sourceId,'Effective Source Amount'=>$this->money($source['amount']),'Verified Amount'=>$this->money($source['verified']),'Cash Returned'=>$this->money($source['returned']),'Remaining Unexplained'=>$this->money($source['remaining'])];
            return $this->row('discrepancy:'.$id,'Money','Cash & MoMo Difference','Case #'.$id.' · '.ucfirst($type).' #'.$sourceId,$this->fallbackPath('discrepancy',$id),match($status){'review_required'=>'Owner Review Required','partial'=>'Partly Verified','resolved'=>'Resolved At Source',default=>'Unresolved'},$status==='resolved',true,$assignee,$due,$source['remaining'],$facts,[$root,...$history]);
        });
    }
    private function sourceBalance(string $type,int $id):array{
        $this->journal();$allocations=[];
        if($type==='payment'){$row=$this->receipts[$id]??throw new \RuntimeException('Original activated receipt unavailable.');$amount=$row['position']['method']==='momo'?(int)$row['position']['amount']:0;$origin=(int)($row['actor_id']??0);}
        else{$row=$this->sourceIndex[$id]??throw new \RuntimeException('Original cash source unavailable.');$origin=(int)$row['actor_id'];$amount=(int)$row['amount_minor'];
            if($type==='deposit'&&$row['kind']==='deposit_recorded'||$type==='handover'&&$row['kind']==='cash_transfer_sent')$allocations=$row['data']['allocations'];
            elseif($type!=='income'||$row['kind']!=='income_recorded'||($row['data']['method']??'')!=='momo')throw new \RuntimeException('Unsupported original source.');}
        if(!$origin)throw new \RuntimeException('Original source actor requires evidence.');
        return \Tivoli\Domain\Finance\CashResolution::position($type,$id,$amount,$this->resolutionIndex[$type][(string)$id]??[],$allocations)+compact('row','amount','origin','allocations');
    }
    private function cashFeed():void{
        foreach($this->cash['rows'] as $source){
            $id=(string)$source['id'];$free=(int)array_sum($source['available']);$settled=(int)$source['outstanding']===0;
            if($free<=0&&!$settled)continue;
            $custodians=array_keys(array_filter($source['available'],fn($n)=>$n>0));$actor=count($custodians)===1?(int)$custodians[0]:null;
            $facts=['Gross Cash Receipt'=>$this->money($source['minor']),'Still Due For Full Deposit'=>$this->money($source['outstanding']),'Free Cash Still Held'=>$this->money($free),'Cash Custody'=>implode(', ',array_map(fn($a)=>$this->name((int)$a),$custodians))?:'No Free Cash Held','Deposit Rule'=>'Cash receipts are deposited in full; cash costs use separate funding.'];
            $this->add($this->row('cash:'.str_replace(':','-',$id),'Money','Cash Awaiting Full Deposit','Cash Source #'.$id,$this->path('finance.php',['page'=>'deposits'],'finance-action'),$settled?'Full Cash Deposit Confirmed':'Free Cash Still Held',$settled,false,$actor,$source['due'],$free,$facts));
        }
        foreach($this->cash['deposits'] as $id=>$root){
            if(isset($this->discrepancies['deposit:'.$id]))continue;$p=$this->cash['deposit_positions'][$id];$history=$this->history('deposit',$id);
            $this->add($this->row('deposit:'.$id,'Money','Deposit Awaiting Wallet Confirmation','Deposit #'.$id,$this->path('finance.php',['page'=>'deposits','form'=>'deposit_confirm','id'=>$id],'finance-action'),$p['remaining']===0?'Deposit Control Resolved':'Wallet Evidence Required',$p['remaining']===0,true,$this->actor,$this->deadline($root['data']['allocations']),$p['remaining'],['Original Deposit'=>$this->money($root['amount_minor']),'Verified Credit'=>$this->money($p['verified']),'Cash Returned'=>$this->money($p['returned'])],[$root,...$history]));
        }
    }
    private function weeklyFeed():void{
        $s=new WeeklyAuditService($this->access,$this->now());$s->permit($this->actor);
        if(($this->coverage['journal']??'')!=='available')throw new \RuntimeException('Audit journal unavailable.');
        $faults=[];foreach($this->access->repo->run('SELECT id,room_id,priority,status FROM stay_faults')->fetchAll() as $f)$faults[(int)$f['id']]=$f;
        $this->roots('weekly','Property','weekly_audit',['wa_created'],function($id,$root)use($faults){
            $d=json_decode($root['payload'],true,512,JSON_THROW_ON_ERROR);$source=$d['source'];$history=$this->history('weekly_audit',$id);$status='unassigned';$assignee=null;$due='';$sources=[];$bindings=[];
            // Read the same explicit state events as WeeklyAuditService::detail; all writes stay there.
            foreach($history as $e){$p=$e['data'];switch($e['kind']){case 'wa_source':$sources[]=$p['source'];if($status==='review')$status=$assignee?'assigned':'unassigned';break;case 'wa_assigned':$assignee=(int)$p['assignee'];$due=$p['due_date'];$status='assigned';break;case 'wa_done':$status='review';break;case 'wa_returned':$status='assigned';break;case 'wa_closed':$status='closed';break;case 'wa_fault_linked':$bindings[$p['source_id']]=(int)$p['fault_id'];break;}}
            $critical=false;$links=[];
            foreach($sources as $a){$links[]=['label'=>'Original '.ucfirst($a['kind']).' Audit #'.$a['source_id'],'path'=>$this->path('weekly-audits.php',['page'=>$a['kind'],'source_id'=>$a['source_id']],'audit-action')];if($a['result']!=='unsafe')continue;if($a['kind']==='area'){$critical=true;continue;}$fid=$bindings[$a['source_id']]??(int)$a['fault_id'];$f=$faults[$fid]??null;if(!$f||(string)$f['room_id']!==$a['location_id']||$f['priority']!=='Critical'||$f['status']!=='closed')$critical=true;if($f&&$f['status']!=='closed'&&$this->has('rooms.manage'))$links[]=['label'=>'Record Repair · Fault #'.$fid,'path'=>$this->path('occupancy.php',['section'=>'rooms','form'=>'repair','id'=>$fid],'stay-action')];}
            $facts=['Location'=>$source['location'],'Original Finding'=>$source['comment']?:'No Comment Recorded','Safety'=>$critical?'Linked Unsafe Finding Requires Source Review':'No Open Linked Critical Fault','Room Release'=>'Follow-up closure never changes room readiness.'];
            return $this->row('weekly:'.$id,'Property','Audit Follow-Up · '.$source['location'],'Follow-Up #'.$id,$this->fallbackPath('weekly',$id),match($status){'unassigned'=>'Needs Assignment','assigned'=>'In Progress','review'=>'Awaiting Owner Verification','closed'=>'Verified & Closed'},$status==='closed',in_array($status,['unassigned','review'],true),$assignee,$due,null,$facts,[$root,...$history],$links,$critical);
        });
    }
    private function otherFeeds():void{$this->operationalFeeds();}
}
