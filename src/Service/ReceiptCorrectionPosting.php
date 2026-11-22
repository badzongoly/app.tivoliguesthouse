<?php
namespace Tivoli\Service;
use Tivoli\Repository\ReceiptAccounting as A;
use Tivoli\Domain\Stay\StayRules as R;
use RuntimeException;

trait ReceiptCorrectionPosting
{
    private function proposal(int $actor,int $payment,array $in,?array $file):array{
        $s=$this->source($actor,$payment);if(!hash_equals($s['token'],$in['source_token']??''))throw new RuntimeException('Receipt or guest position changed. Reload before requesting.');
        if(($in['confirmed']??'')!=='1')throw new RuntimeException('Confirm this corrects recorded facts without moving money.');
        $type=R::text($in['type']??'',20,true);if(!in_array($type,['amount','method','duplicate'],true))throw new RuntimeException('Choose a recording correction. Actual refunds use the separate guest workflow.');
        $before=$s['position'];$after=array_intersect_key($before,array_flip(['amount','method','reference']));$keeper=null;$keeperToken=null;
        if($type==='amount'){$after['amount']=R::minor(R::text($in['amount']??'',24,true));if($after['amount']===$before['amount'])throw new RuntimeException('Choose an amount different from the current effective receipt.');}
        if($type==='method'){
            $after['method']=R::text($in['method']??'',10,true);if(!in_array($after['method'],['cash','momo'],true))throw new RuntimeException('Choose Cash or MoMo.');
            $after['reference']=R::text($in['reference']??'',120,$after['method']==='momo');
            if($after['method']===$before['method']&&$after['reference']===$before['reference'])throw new RuntimeException('Change the recorded method or reference first.');
        }
        if($type==='duplicate'){
            $keeper=$this->id($in['keeper_id']??'');if($keeper===$payment)throw new RuntimeException('Choose a different retained receipt.');
            $k=A::position($this->access->repo,$keeper);
            if((int)$k['original']['booking_id']!==(int)$s['booking']['id']||$k['position']['amount']<1||$k['position']['amount']!==$before['amount']||$k['position']['method']!==$before['method'])throw new RuntimeException('The retained receipt must represent the same stay, amount and actual collection.');
            if($before['method']==='momo'&&(!$before['reference']||mb_strtolower(trim($before['reference']),'UTF-8')!==mb_strtolower(trim($k['position']['reference']),'UTF-8')))throw new RuntimeException('Verify identical external collection evidence before identifying a duplicate.');
            if($before['method']==='cash'&&(int)$k['original']['actor_id']!==(int)$s['original']['actor_id'])throw new RuntimeException('Different cash collectors require custody reconciliation, not an inferred duplicate.');
            if($this->active($keeper))throw new RuntimeException('Resolve the retained receipt correction before deduplicating.');
            $keeperToken=$this->hash($k);$after['amount']=0;
        }
        if($after['amount']<$s['refunded'])throw new RuntimeException('The corrected total cannot be below already recorded refunds.');
        $delta=$after['amount']-$before['amount'];$cashDelta=($after['method']==='cash'?$after['amount']:0)-($before['method']==='cash'?$before['amount']:0);$momoDelta=($after['method']==='momo'?$after['amount']:0)-($before['method']==='momo'?$before['amount']:0);
        $reconciliation=ReceiptReconciliation::plan($this->access,$actor,$s,$after,$in,$keeper,$this->now());
        return ['reconciliation'=>$reconciliation,'payment_id'=>$payment,'booking_id'=>(int)$s['booking']['id'],'type'=>$type,'before'=>$before,'after'=>$after,'delta'=>$delta,'cash_delta'=>$cashDelta,'momo_delta'=>$momoDelta,'guest_before'=>$s['bill']['balance'],'guest_after'=>$s['bill']['balance']-$delta,'keeper_id'=>$keeper,'keeper_token'=>$keeperToken,'source_token'=>$s['token'],'reason'=>R::text($in['reason']??'',2000,true),'evidence'=>$file?FinanceAttachment::meta($file):null,'source'=>['guest_name'=>$s['booking']['guest_name'],'room_name'=>$s['booking']['room_name'],'status'=>$s['booking']['status'],'original_date'=>$s['original_date'],'recorded_at'=>$s['original']['created_at'],'original_actor'=>$s['original']['actor_name']??'Historical Actor Unknown']];
    }
    private function guardNewReference(string $reference,int $payment):void{
        $r=$this->access->repo;$normalized=mb_strtolower(trim($reference),'UTF-8');A::guardAlias($r,$reference,$payment);\Tivoli\Repository\ExpenseAccounting::guardAlias($r,$reference);
        $claim=$r->run("SELECT f.* FROM finance_claims c JOIN finance_events f ON f.id=c.event_id WHERE c.scope='business_momo' AND c.identity_hash=?",[hash('sha256',$normalized)])->fetch();
        if($claim&&!(in_array($claim['kind'],['receipt_matched','cd_credit'],true)&&$claim['target_type']==='payment'&&A::collection($r,(int)$claim['target_id'])===A::collection($r,$payment)))throw new RuntimeException('This wallet reference belongs to another collection or outflow.');
        foreach($r->run('SELECT payment_id FROM stay_receipts WHERE payment_id<>? AND LOWER(TRIM(reference_no))=?',[$payment,$normalized])->fetchAll(\PDO::FETCH_COLUMN) as $other)if(A::collection($r,(int)$other)!==A::collection($r,$payment))throw new RuntimeException('This reference identifies another recorded collection.');
        if($r->run("SELECT 1 FROM finance_events WHERE kind='income_recorded' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.method'))='momo' AND LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.reference'))))=? LIMIT 1",[$normalized])->fetchColumn())throw new RuntimeException('This reference identifies activity income.');
    }
    private function alias(string $reference,int $event,int $owner):void{
        if(trim($reference)==='')return;$r=$this->access->repo;A::guardAlias($r,$reference,$owner);\Tivoli\Repository\ExpenseAccounting::guardAlias($r,$reference);$hash=hash('sha256',mb_strtolower(trim($reference),'UTF-8'));
        if(!$r->run("SELECT event_id FROM finance_claims WHERE scope='receipt_alias' AND identity_hash=?",[$hash])->fetchColumn())$r->run("INSERT INTO finance_claims(scope,identity_hash,event_id) VALUES('receipt_alias',?,?)",[$hash,$event]);
    }
    private function postCorrection(int $actor,int $id,array $c,array $in):array{
        if(($in['verified']??'')!=='1')throw new RuntimeException('Verify independent collection evidence and authorize all financial effects.');
        $reason=R::text($in['reason']??'',2000,true);$p=$c['proposal'];$s=$this->source($actor,(int)$p['payment_id']);
        if(!hash_equals($s['token'],$p['source_token']))throw new RuntimeException('The receipt, stay or dependencies changed. Return for revision before posting.');
        if($s['blockers'])throw new RuntimeException(implode(' ',$s['blockers']));
        if($s['dependencies']&&!isset($p['reconciliation']))throw new RuntimeException('Return this older request for linked-money reconciliation before approval.');
        $reconciliation=ReceiptReconciliation::plan($this->access,$actor,$s,$p['after'],$p['reconciliation']['inputs']??[],$p['keeper_id'],$this->now());
        if(!hash_equals($this->hash($reconciliation),$this->hash($p['reconciliation']??null)))throw new RuntimeException('Supporting money evidence changed. Return for a fresh review.');
        if($p['after']['method']==='cash'){ $cursor=(int)$this->access->repo->run('SELECT payment_cursor FROM finance_schema WHERE id=1')->fetchColumn();if(!$s['original']['actor_id']||$p['payment_id']<=$cursor)throw new RuntimeException('Historical cash custody is unknown; do not assign an invented custodian.');}
        if($p['keeper_id']){if(!hash_equals($p['keeper_token'],$this->hash(A::position($this->access->repo,$p['keeper_id'])))||$this->active($p['keeper_id']))throw new RuntimeException('The retained collection changed. Return for revision.');}
        if($p['after']['amount']<$s['refunded'])throw new RuntimeException('Recorded refunds exceed the proposed receipt.');
        FinanceService::guardSource($this->access,$this->today());
        if($p['after']['method']==='momo'&&($p['after']['reference']!==$p['before']['reference']||$p['before']['method']!=='momo'))$this->guardNewReference($p['after']['reference'],$p['payment_id']);
        $data=array_intersect_key($p,array_flip(['payment_id','booking_id','type','before','after','delta','cash_delta','momo_delta','keeper_id']))+['request_id'=>$id,'alias_owner_payment_id'=>$p['keeper_id']??$p['payment_id'],'original_activity_date'=>$p['source']['original_date'],'posting_date'=>$this->today(),'actual_money_moved'=>0];
        $r=$this->access->repo;$r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES('rc_posted','payment',?,?,?,?,?,?)",[(string)$p['payment_id'],$p['delta'],$this->today(),$actor,json_encode($data,JSON_THROW_ON_ERROR),$this->now()]);$posted=(int)$r->db->lastInsertId();
        foreach([$s['original']['method']==='momo'?($s['original']['reference_no']??''):'',$p['before']['method']==='momo'?$p['before']['reference']:'',$p['after']['method']==='momo'?$p['after']['reference']:''] as $ref)$this->alias($ref,$posted,$data['alias_owner_payment_id']);
        $allocationIds=$reconciliation?ReceiptReconciliation::post($this->access,$actor,$id,(int)$p['payment_id'],$reconciliation,$this->now()):[];
        $event=$this->event($actor,'rc_approved',['posted_id'=>$posted,'reason'=>$reason,'posting_date'=>$this->today(),'allocation_event_ids'=>$allocationIds],$id);return [$id,$event];
    }
}
