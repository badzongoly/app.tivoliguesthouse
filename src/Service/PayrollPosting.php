<?php
namespace Tivoli\Service;
use Tivoli\Domain\Stay\StayRules as R;
use RuntimeException;

trait PayrollPosting
{
    private function canonical(int $actor,string $kind,string $type,string $target,int $amount,string $date,array $payload):int{
        $r=$this->access->repo;$r->run('INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES(?,?,?,?,?,?,?,?)',[$kind,$type,$target,$amount,$date,$actor,json_encode($payload,JSON_THROW_ON_ERROR),$this->now()]);return (int)$r->db->lastInsertId();
    }
    private function approvePayroll(int $actor,int $id,array $c,array $in):array{
        if($c['status']!=='review'||$c['approved']||($in['authorized']??'')!=='1')throw new RuntimeException('Review and authorize an unapproved payroll.');
        $d=$c['root']['data'];$p=$c['prepared'];$salary=$this->salary((int)$d['staff_id'],$d['period']);if(!hash_equals($p['token'],$salary['token']))throw new RuntimeException('Salary, staff or recipient details changed. Return for revision and prepare again.');
        $reason=R::text($in['reason']??'',2000,true);FinanceService::guardSource($this->access,$this->today());
        $request=$this->canonical($actor,'payroll_requested','staff',(string)$d['staff_id'],$p['cost'],$this->today(),['staff_id'=>$d['staff_id'],'period'=>$d['period'],'payroll_id'=>$id]);
        $approval=$this->canonical($actor,'request_approved','request',(string)$request,$p['cost'],$this->today(),['request_kind'=>'payroll_requested','payroll_id'=>$id]);
        $p+=['posting_date'=>$this->today(),'approved_at'=>$this->now(),'approved_by'=>$this->access->repo->user($actor)['name'],'approval_event_id'=>$approval];
        $event=$this->event($actor,'payroll_approved',['staff_id'=>$d['staff_id'],'period'=>$d['period'],'request_id'=>$request,'snapshot'=>$p,'reason'=>$reason],$id);
        return [$id,$event];
    }
    private function payPayroll(int $actor,int $id,array $c,array $in):array{
        $this->guardPayroll($c);if(!$c['approved']||$c['due']<1)throw new RuntimeException('No approved employee balance remains.');
        if(($in['verified']??'')!=='1')throw new RuntimeException('Verify the completed transfer, recipient and reference.');
        $amount=R::minor(R::text($in['amount']??'',24,true));if($amount<1||$amount>$c['due'])throw new RuntimeException('Record a positive payment no greater than the employee balance.');
        $reference=R::text($in['reference']??'',120,true);$at=str_replace('T',' ',R::text($in['paid_at']??'',19,true));if(strlen($at)===16)$at.=':00';$dt=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$at,new \DateTimeZone('UTC'));
        if(!$dt||$dt->format('Y-m-d H:i:s')!==$at||$at>$this->now()||$at<$c['approved']['approved_at'])throw new RuntimeException('Enter an actual UTC payment time after approval and no later than now.');
        $finance=new FinanceService($this->access,$this->now());FinanceService::guardSource($this->access,$this->today());$finance->guardLateMomoReference($reference);
        $canonical=$this->canonical($actor,'cost_paid','request',(string)$c['request_id'],$amount,$this->today(),['method'=>'momo','payroll_id'=>$id,'reference'=>'Restricted Payroll Reference','destination'=>'Private Payroll','evidence'=>'Owner-verified completed payroll settlement']);
        $this->access->repo->run("INSERT INTO finance_claims(scope,identity_hash,event_id) VALUES('business_momo',?,?)",[hash('sha256',mb_strtolower(trim($reference),'UTF-8')),$canonical]);
        $d=$c['root']['data'];$event=$this->event($actor,'payroll_paid',['staff_id'=>$d['staff_id'],'period'=>$d['period'],'amount'=>$amount,'reference'=>$reference,'paid_at'=>$at,'posting_date'=>$this->today(),'canonical_id'=>$canonical,'wallet'=>$c['approved']['wallet'],'comment'=>R::text($in['comment']??'',2000)],$id);
        return [$id,$event];
    }
}
