<?php
namespace Tivoli\Service;
use Tivoli\Repository\ExpenseAccounting as A;
use Tivoli\Repository\ReceiptAccounting;
use Tivoli\Domain\Stay\StayRules as R;
use RuntimeException;

trait ExpenseCorrectionPosting
{
    private function proposal(int $actor,int $expense,array $in,?array $file):array{
        $s=$this->source($actor,$expense);if(!hash_equals($s['token'],$in['source_token']??''))throw new RuntimeException('Expense or settlement changed. Reload before requesting.');
        if(($in['confirmed']??'')!=='1')throw new RuntimeException('Confirm the actual cost and payout without moving money.');
        $type=R::text($in['type']??'',20,true);if(!in_array($type,['amount','category','method','duplicate'],true))throw new RuntimeException('Use a recording correction; actual supplier recovery needs a separate workflow.');
        $before=$s['position'];$after=array_intersect_key($before,array_flip(['amount','paid','category_id','method','reference']));$keeper=null;$keeperToken=null;
        if($type==='amount'){$after['amount']=R::minor(R::text($in['amount']??'',24,true));$after['paid']=R::minor(R::text($in['paid']??'',24,true));if($after['amount']<1||$after['amount']!==$after['paid'])throw new RuntimeException('Cost and actual payout must be positive equal totals for this paid-expense workflow. Supplier claims/payables require separate reconciliation.');}
        if($type==='category'){$after['category_id']=$this->id($in['category_id']??'');$this->category($after['category_id']);}
        if($type==='method'){$after['method']=R::text($in['method']??'',10,true);if(!in_array($after['method'],['cash','momo'],true))throw new RuntimeException('Choose Cash or MoMo.');$after['reference']=R::text($in['reference']??'',120,$after['method']==='momo');}
        if($type==='duplicate'){
            $keeper=$this->id($in['keeper_id']??'');if($keeper===$expense)throw new RuntimeException('Choose a different retained expense.');$k=$this->source($actor,$keeper);
            if($k['blockers']||$k['money_blockers']||A::collection($this->access->repo,$keeper)!==$keeper||$k['position']['amount']<1||$k['position']['amount']!==$before['amount']||$k['position']['paid']!==$before['paid']||$k['position']['method']!==$before['method']||$k['position']['category_id']!==$before['category_id']||mb_strtolower(trim($k['original']['vendor']),'UTF-8')!==mb_strtolower(trim($s['original']['vendor']),'UTF-8')||$k['payer_id']!==$s['payer_id']||$this->active($keeper))throw new RuntimeException('Verify the same ordinary cost, vendor, payer and one actual payout against an eligible keeper.');
            if($before['method']==='momo'&&(!$before['reference']||mb_strtolower(trim($before['reference']),'UTF-8')!==mb_strtolower(trim($k['position']['reference']),'UTF-8')))throw new RuntimeException('Verify identical external payout evidence.');
            $keeperToken=$k['token'];$after['amount']=0;$after['paid']=0;
        }
        if($after===array_intersect_key($before,$after))throw new RuntimeException('Change the recorded facts first.');
        $cash=($after['method']==='cash'?(int)$after['paid']:0)-($before['method']==='cash'?(int)$before['paid']:0);$momo=($after['method']==='momo'?(int)$after['paid']:0)-($before['method']==='momo'?(int)$before['paid']:0);
        return ['expense_id'=>$expense,'type'=>$type,'before'=>$before,'after'=>$after,'cost_delta'=>$after['amount']-$before['amount'],'paid_delta'=>$after['paid']===null||$before['paid']===null?0:$after['paid']-$before['paid'],'cash_delta'=>$cash,'momo_delta'=>$momo,'keeper_id'=>$keeper,'keeper_token'=>$keeperToken,'source_token'=>$s['token'],'reason'=>R::text($in['reason']??'',2000,true),'evidence'=>$file?FinanceAttachment::meta($file):null,'source'=>['vendor'=>$s['original']['vendor'],'original_date'=>$s['original_date'],'original_actor'=>$s['original']['actor_name']??'Historical Actor Unknown','payer_id'=>$s['payer_id']]];
    }
    private function category(int $id):void{
        $name=$this->access->repo->run('SELECT name FROM expense_categories WHERE id=? AND active=1',[$id])->fetchColumn();if(!$name||preg_match('/stock|inventory|capital|asset|payroll/i',$name))throw new RuntimeException('Choose an active ordinary operating expense category.');
    }
    private function alias(string $ref,int $event,int $owner):void{
        if(trim($ref)==='')return;$r=$this->access->repo;A::guardAlias($r,$ref,$owner);$hash=hash('sha256',mb_strtolower(trim($ref),'UTF-8'));if(!$r->run("SELECT event_id FROM finance_claims WHERE scope='expense_alias' AND identity_hash=?",[$hash])->fetchColumn())$r->run("INSERT INTO finance_claims(scope,identity_hash,event_id) VALUES('expense_alias',?,?)",[$hash,$event]);
    }
    private function guardReference(string $ref,array $s,?int $keeper=null):void{
        if(trim($ref)==='')return;$r=$this->access->repo;ReceiptAccounting::guardAlias($r,$ref);$allowed=[A::collection($r,(int)$s['original']['id'])];if($keeper)$allowed[]=A::collection($r,$keeper);$alias=A::aliasOwner($r,$ref);if($alias!==null&&!in_array($alias,$allowed,true))throw new RuntimeException('This reference is retained by another corrected expense.');$normalized=mb_strtolower(trim($ref),'UTF-8');
        $claims=$r->run("SELECT event_id FROM finance_claims WHERE scope='business_momo' AND identity_hash=?",[hash('sha256',$normalized)])->fetchAll(\PDO::FETCH_COLUMN);
        foreach($claims as $id){$e=$r->run('SELECT kind,target_type,target_id FROM finance_events WHERE id=?',[$id])->fetch();if(!$e||$e['kind']!=='cost_paid'||$e['target_type']!=='expense'||!in_array(A::collection($r,(int)$e['target_id']),$allowed,true))throw new RuntimeException('This reference belongs to another collection or payout.');}
        if($r->run('SELECT 1 FROM stay_receipts WHERE LOWER(TRIM(reference_no))=? LIMIT 1',[$normalized])->fetchColumn()||$r->run("SELECT 1 FROM finance_events WHERE kind IN ('income_recorded','deposit_recorded','refund_recorded') AND LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.reference'))))=? LIMIT 1",[$normalized])->fetchColumn())throw new RuntimeException('This reference identifies a different money movement.');
        foreach($r->run("SELECT target_type,target_id,payload FROM finance_events WHERE kind='cost_paid' AND LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.reference'))))=?",[$normalized])->fetchAll() as $e)if($e['target_type']!=='expense'||!in_array(A::collection($r,(int)$e['target_id']),$allowed,true))throw new RuntimeException('Another expense payout already retains this reference.');
    }
    private function postCorrection(int $actor,int $id,array $c,array $in):array{
        if(($in['verified']??'')!=='1')throw new RuntimeException('Independently verify ordinary cost, actual payer/funding, payout and all effects.');$reason=R::text($in['reason']??'',2000,true);$p=$c['proposal'];$s=$this->source($actor,(int)$p['expense_id']);
        if(!hash_equals($s['token'],$p['source_token']))throw new RuntimeException('Source or dependencies changed. Return for revision.');$blocks=$s['blockers'];if($p['type']!=='category')$blocks=array_merge($blocks,$s['money_blockers']);if($blocks)throw new RuntimeException(implode(' ',$blocks));$this->category($p['after']['category_id']);
        if($p['keeper_id']){$k=$this->source($actor,$p['keeper_id']);if(!hash_equals($p['keeper_token'],$k['token'])||$this->active($p['keeper_id']))throw new RuntimeException('Retained expense changed; return for revision.');}
        FinanceService::guardSource($this->access,$this->today());
        foreach([$p['before']['method']==='momo'?$p['before']['reference']:'',$p['after']['method']==='momo'?$p['after']['reference']:''] as $ref)$this->guardReference($ref,$s,$p['keeper_id']);
        $data=array_intersect_key($p,array_flip(['expense_id','type','before','after','cost_delta','paid_delta','cash_delta','momo_delta','keeper_id']))+['request_id'=>$id,'alias_owner_expense_id'=>$p['keeper_id']??$p['expense_id'],'original_activity_date'=>$s['original_date'],'posting_date'=>$this->today(),'actual_money_moved'=>0];
        $r=$this->access->repo;$r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES('ec_posted','expense',?,?,?,?,?,?)",[(string)$p['expense_id'],$p['cost_delta'],$this->today(),$actor,json_encode($data,JSON_THROW_ON_ERROR),$this->now()]);$posted=(int)$r->db->lastInsertId();
        foreach([$p['before']['method']==='momo'?$p['before']['reference']:'',$p['after']['method']==='momo'?$p['after']['reference']:''] as $ref)$this->alias($ref,$posted,$data['alias_owner_expense_id']);
        return [$id,$this->event($actor,'ec_approved',['posted_id'=>$posted,'reason'=>$reason,'posting_date'=>$this->today()],$id)];
    }
}
