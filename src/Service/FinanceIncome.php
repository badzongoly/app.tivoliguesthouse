<?php
namespace Tivoli\Service;
use Tivoli\Domain\Stay\StayRules as R;
use RuntimeException;
use Tivoli\Security\PermissionDenied;

/** Non-guest receipts share deposit, audit and review controls, without invented guest bookings. */
trait FinanceIncome
{
    private function incomeCommand(int $actor,string $action,array $in):int{
        $this->access->requirePrivilege($actor,'bookings.manage');$today=$this->today();
        if($this->dayClosed($today))throw new RuntimeException('Reopen today’s reconciliation before recording activity income.');
        $this->openCostDate($today);
        if($action==='income_record'){
            $method=$in['method']??'';if(!in_array($method,['cash','momo'],true))throw new RuntimeException('Choose Cash or MoMo.');
            $treatment=$in['treatment']??'';if(!in_array($treatment,['earned','advance'],true))throw new RuntimeException('Choose completed service or advance payment.');
            if($treatment==='earned'&&($in['provided']??'')!=='1')throw new RuntimeException('Confirm that the activity or service has already been provided.');
            $phone=R::text($in['phone']??'',40);if($phone!==''&&!preg_match('/^[+0-9 ()-]{6,40}$/D',$phone))throw new RuntimeException('Enter a valid phone number.');
            $reference=R::text($in['reference']??'',120);\Tivoli\Repository\ReceiptAccounting::guardAlias($this->repo,$reference);\Tivoli\Repository\ExpenseAccounting::guardAlias($this->repo,$reference);
            $late=$this->lateDetails($actor,$in,$reference);
            if($late && $method==='momo')$this->guardLateMomoReference($reference);
            $id=$this->event('income_recorded','income','activity',$this->amount($in),$today,$actor,['payer'=>R::text($in['payer']??'',120,true),'phone'=>$phone,'activity'=>R::text($in['activity']??'',250,true),'method'=>$method,'reference'=>$reference,'treatment'=>$treatment]);
            if($late)$this->recordLate($actor,'income',$id,$late);
            return $id;
        }
        if(!preg_match('/^[1-9][0-9]{0,17}$/D',$in['id']??''))throw new RuntimeException('Choose a valid receipt.');$id=(int)$in['id'];$e=$this->find($id);
        if($e['kind']!=='income_recorded'||$e['data']['treatment']!=='advance'||$this->linked('income_completed','income',(string)$id))throw new RuntimeException('Choose an advance not already recognised as completed.');
        if((int)$e['actor_id']!==$actor&&!$this->isAdmin($actor))throw new PermissionDenied('Only the recorder or an administrator can complete this activity.');
        if(($in['provided']??'')!=='1')throw new RuntimeException('Confirm the service was provided.');
        return $this->event('income_completed','income',(string)$id,(int)$e['amount_minor'],$today,$actor,['comment'=>R::text($in['comment']??'',500),'original_receipt_id'=>$id]);
    }
    public static function claimCostEvidence(AccessService $a,int $actor,string $kind,int $id,array $file,int $amount,string $date):void{
        self::claimCostMetadata($a,$actor,$kind,$id,FinanceAttachment::meta($file),$amount,$date);
    }
    public static function claimCostMetadata(AccessService $a,int $actor,string $kind,int $id,array $meta,int $amount,string $date):void{
        $s=new self($a);if(!$s->installed())return;
        if(!$a->repo->db->inTransaction()||!preg_match('/^[a-f0-9]{64}$/D',$meta['sha']??''))throw new RuntimeException('Invalid cost evidence transaction.');
        $hash=hash('sha256',$meta['sha'].'|'.$amount.'|'.$date);
        $rows=$a->repo->run("SELECT target_type,target_id FROM security_audit WHERE action='finance.cost_evidence_claimed' AND outcome='succeeded' AND target_id=? ORDER BY id DESC",[$hash])->fetchAll();
        foreach($rows as $old){$data=$a->repo->run("SELECT details FROM security_audit WHERE action='finance.cost_evidence_claimed' AND target_type=? AND target_id=? AND outcome='succeeded' ORDER BY id DESC LIMIT 1",[$old['target_type'],$hash])->fetchColumn();$d=json_decode($data,true,512,JSON_THROW_ON_ERROR);$rejected=$d['kind']==='submission'?$a->repo->run("SELECT status FROM expense_submissions WHERE id=?",[$d['source_id']])->fetchColumn()==='rejected':($d['kind']==='provider'&&$s->linked('request_rejected','request',(string)$d['source_id']));if(!$rejected)throw new RuntimeException('This evidence, amount and cost date are already recorded. Review the existing cost instead.');}
        $a->audit($actor,'finance.cost_evidence_claimed','cost_'.$kind,$hash,'succeeded',['kind'=>$kind,'source_id'=>$id,'sha'=>$meta['sha'],'amount_minor'=>$amount,'date'=>$date]);
    }
}
