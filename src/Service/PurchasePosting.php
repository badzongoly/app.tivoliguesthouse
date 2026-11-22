<?php
namespace Tivoli\Service;
use Tivoli\Domain\Stay\StayRules as R;
use RuntimeException;
use Tivoli\Security\PermissionDenied;

trait PurchasePosting
{
    private function postingDate(string $actual):string{
        $date=substr($actual,0,10);$f=$this->finance();
        if($f->dayClosed($date)||$f->periodLocked($date))$date=substr($this->now(),0,10);
        FinanceService::guardSource($this->access,$date);return $date;
    }
    private function postPurchase(int $actor,int $id,array $c,array $in):int{
        if(!$this->access->repo->db->inTransaction())throw new \LogicException('Purchase posting requires its transaction.');
        if(!(new ExpenseService($this->access))->canManage($actor))throw new PermissionDenied('Expense management authority is also required.');
        if(!in_array($c['status'],['declared','exception'],true)||$c['expense_id'])throw new RuntimeException('Only an unposted cost declaration can be recorded.');
        if(($in['verified']??'')!=='1')throw new RuntimeException('Verify complete delivery and actual cost first.');
        if($c['root']['kind']==='purchase_unapproved'&&$c['root']['data']['type']==='stock')throw new RuntimeException('Unapproved earlier stock requires a cost-only reconciliation without another stock movement.');
        $reason=R::text($in['reason']??'',2000,$c['status']==='exception');$d=$c['root']['data'];$receipt=$c['receipt'];$r=$this->access->repo;
        if($receipt['payment']&&($in['payment_verified']??'')!=='1')throw new RuntimeException('Independently check the declared payment before recording it. Do not pay it again.');
        $date=$this->postingDate($receipt['delivered_at']);$original=substr($receipt['delivered_at'],0,10);
        $r->run('INSERT INTO expenses(expense_date,category_id,method,amount,vendor,reference_no,description,user_id) VALUES(?,?,?,?,?,?,?,?)',[$date,$d['category_id'],$receipt['payment']['method']??$d['method'],R::decimal($receipt['cost']),$c['scope']['supplier'],'PURCHASE-'.$id,'Purchase #'.$id.': '.$d['title'],$c['root']['actor_id']]);
        $expense=(int)$r->db->lastInsertId();$movement=null;
        $proofs=[];if($receipt['proof'])$proofs[$receipt['proof']['sha']]=$receipt['proof'];foreach($c['clarifications'] as $clarification){$proof=$clarification['data']['proof']??null;if($proof)$proofs[$proof['sha']]=$proof;}
        foreach($proofs as $proof)FinanceService::claimCostMetadata($this->access,$actor,'expense',$expense,$proof,$receipt['cost'],$original);
        if($d['type']==='stock'){
            $this->access->requirePrivilege($actor,'stock.manage');if(($in['stock_verified']??'')!=='1')throw new RuntimeException('Verify the complete stock quantity before posting.');
            $extra=$r->run('SELECT e.*,COALESCE(s.tracked,1) tracked FROM extras e LEFT JOIN stay_extra_settings s ON s.extra_id=e.id WHERE e.id=? FOR UPDATE',[$d['extra_id']])->fetch();if(!$extra||!$extra['tracked'])throw new RuntimeException('The approved stock item is no longer tracked. Obtain an evidenced owner review.');
            $stock=R::quantity((string)$extra['stock_qty'])+(int)$d['qty'];if($stock>999999999999)throw new RuntimeException('Stock exceeds the supported quantity limit.');
            $r->run('UPDATE extras SET stock_qty=? WHERE id=?',[R::decimal($stock),$d['extra_id']]);
            $r->run("INSERT INTO stock_movements(extra_id,movement_type,qty,note,ref_type,ref_id,created_by,created_at) VALUES(?,'in',?,?,'purchase',?,?,?)",[$d['extra_id'],R::decimal($d['qty']),'Purchase #'.$id.' / Expense #'.$expense,$id,$actor,$this->now()]);$movement=(int)$r->db->lastInsertId();
        }
        $posted=$this->event('purchase_posted',$actor,['expense_id'=>$expense,'stock_movement_id'=>$movement,'actual_cost'=>$receipt['cost'],'reason'=>$reason,'exception'=>$c['status']==='exception','declaration_id'=>$receipt['event_id'],'original_activity_date'=>$original,'posting_date'=>$date,'approval_id'=>$c['approval']?(int)$c['approval']['id']:null],$id);
        (new InventoryCostingService($this->access,$this->now()))->purchase($actor,$id,$posted);
        if($date!==$original)$this->finance()->recordLate($actor,'expense',$expense,['original_activity_date'=>$original,'posting_date'=>$date,'reason'=>$reason?:'Owner-verified purchase cost posted in an open period','evidence_reference'=>'Purchase #'.$id.' / Declaration #'.$receipt['event_id']]);
        if($receipt['payment'])$this->payout($actor,$id,$expense,$receipt['payment']+['evidence'=>'Owner-verified previously declared payment','proof'=>null,'exception'=>!$c['approval']||$receipt['payment']['paid_at']<$c['approval']['created_at'],'exception_reason'=>$reason]);
        return $posted;
    }
    private function payout(int $actor,int $purchase,int $expense,array $data):int{
        $r=$this->access->repo;$f=$this->finance();$date=$this->postingDate($data['paid_at']);
        if($data['method']==='momo')$f->guardLateMomoReference($data['reference']);
        $event=$this->event('purchase_paid',$actor,$data+['expense_id'=>$expense,'original_activity_date'=>substr($data['paid_at'],0,10),'posting_date'=>$date],$purchase);
        if($data['method']==='momo')$r->run("INSERT INTO finance_claims(scope,identity_hash,event_id) VALUES('business_momo',?,?)",[hash('sha256',mb_strtolower(trim($data['reference']),'UTF-8')),$event]);
        $r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES('cost_paid','expense',?,?,?,?,?,?)",[(string)$expense,$data['amount'],$date,$actor,json_encode(['purchase_id'=>$purchase,'purchase_payment_id'=>$event,'method'=>$data['method'],'reference'=>$data['reference'],'paid_by'=>$data['payer_id'],'payer_name'=>$data['payer_name'],'actual_paid_at'=>$data['paid_at'],'cash_funding'=>$data['method']==='cash'?'separate_from_full_sales_deposit':null,'evidence'=>$data['evidence']??'Verified purchase payment'],JSON_THROW_ON_ERROR),$this->now()]);
        return $event;
    }
}
