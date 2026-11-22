<?php
namespace Tivoli\Service;
use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Security\PermissionDenied;
use RuntimeException;

/** Linked compensation; original salary, transfer and issued statements stay immutable. */
trait PayrollCorrections
{
    private function affectsStatement(array $data):bool{
        if(($data['type']??'')!=='components')return true;
        foreach(['base','allowance','overtime','deductions'] as $field)if(!isset($data['before'][$field],$data['after'][$field])||$data['before'][$field]!==$data['after'][$field])return true;
        return trim($data['employee_note']??'')!=='';
    }
    private function projectPayroll(array $c):array{
        $c['original_approved']=$c['approved'];$c['original_payments']=$c['payments'];$map=[];$corrections=[];$followups=[];$publications=[];$cursor=0;
        foreach($c['payments'] as $p){$p['data']['original_amount']=(int)$p['data']['amount'];$p['data']['corrections']=[];$map[(int)$p['id']]=$p;}
        foreach($c['history'] as $e){$d=$e['data'];if(in_array($e['kind'],['payroll_approved','payroll_paid'],true)||$e['kind']==='payroll_corrected'&&$this->affectsStatement($d))$cursor=(int)$e['id'];
            if($e['kind']==='payroll_corrected'){
                if(!$c['approved'])throw new RuntimeException('Correction lacks an approved payroll source.');
                if($d['type']==='components')$c['approved']=$d['after'];
                elseif($d['type']==='payment'){$pid=(int)$d['payment_id'];if(!isset($map[$pid]))throw new RuntimeException('Corrected payroll transfer source is missing.');$map[$pid]['data']['amount']=(int)$d['after_amount'];$map[$pid]['data']['corrections'][]=['id'=>(int)$e['id'],'before'=>(int)$d['before_amount'],'after'=>(int)$d['after_amount'],'recorded_at'=>$e['created_at']];}
                else throw new RuntimeException('Unsupported payroll correction source.');$corrections[]=$e;
            }elseif($e['kind']==='payroll_recovery_note')$followups[]=$e;elseif($e['kind']==='payroll_published')$publications[]=$e;
        }
        $c['payments']=array_values($map);$c['paid']=array_sum(array_map(fn($p)=>(int)$p['data']['amount'],$c['payments']));$net=$c['approved']['net']??null;
        $c['due']=$net===null?null:max(0,$net-$c['paid']);$c['recovery_due']=$net===null?0:max(0,$c['paid']-$net);$c['corrections']=$corrections;$c['recovery_notes']=$followups;$c['publications']=$publications;$c['financial_cursor']=$cursor;
        $c['published_current']=$c['published']&&(int)$c['published']['id']>$cursor;$c['source_errors']=[];$public=[];
        if($c['original_approved']){
            $r=$this->access->repo;$id=(int)$c['root']['id'];$original=$c['original_approved'];$request=$r->run("SELECT * FROM finance_events WHERE id=? AND kind='payroll_requested'",[$c['request_id']])->fetch();
            $public=$r->run("SELECT * FROM finance_events WHERE target_type='request' AND target_id=? ORDER BY id",[(string)$c['request_id']])->fetchAll();$approvals=array_values(array_filter($public,fn($x)=>$x['kind']==='request_approved'));
            $req=$request?json_decode($request['payload'],true,512,JSON_THROW_ON_ERROR):[];$approval=count($approvals)===1?$approvals[0]:null;$ad=$approval?json_decode($approval['payload'],true,512,JSON_THROW_ON_ERROR):[];
            if((int)($original['staff_id']??0)!==(int)$c['root']['data']['staff_id']||($original['period']??'')!==$c['root']['data']['period']||($req['period']??'')!==$c['root']['data']['period']||(int)($req['staff_id']??0)!==(int)$c['root']['data']['staff_id']||!$request||$request['target_type']!=='staff'||(int)$request['target_id']!==(int)$c['root']['data']['staff_id']||(int)($req['payroll_id']??0)!==$id||(int)$request['amount_minor']!==$original['cost']||!$approval||(int)$approval['id']!==$original['approval_event_id']||(int)$approval['amount_minor']!==$original['cost']||($ad['request_kind']??'')!=='payroll_requested'||(int)($ad['payroll_id']??0)!==$id)$c['source_errors'][]='Original payroll cost and approval links need reconciliation.';
            $canonical=[];foreach($public as $p)if($p['kind']==='cost_paid')$canonical[(int)$p['id']]=$p;$seen=[];
            foreach($c['original_payments'] as $payment){$pd=$payment['data'];$cp=$canonical[(int)$pd['canonical_id']]??null;$cd=$cp?json_decode($cp['payload'],true,512,JSON_THROW_ON_ERROR):[];$claim=$r->run("SELECT event_id FROM finance_claims WHERE scope='business_momo' AND identity_hash=?",[hash('sha256',mb_strtolower(trim($pd['reference']),'UTF-8'))])->fetchColumn();
                if(!$cp||isset($seen[(int)$pd['canonical_id']])||(int)$cp['amount_minor']!==(int)$pd['amount']||(int)$cp['actor_id']!==(int)$payment['actor_id']||(int)($cd['payroll_id']??0)!==$id||($cd['method']??'')!=='momo'||(int)$claim!==(int)$pd['canonical_id']||($pd['wallet']??'')!==$original['wallet']||$pd['paid_at']<$original['approved_at']||$pd['paid_at']>$payment['created_at'])$c['source_errors'][]='Original payroll transfer, recorder, actual time or unique MoMo evidence does not reconcile.';
                $seen[(int)$pd['canonical_id']]=true;
            }
            if(count($seen)!==count($canonical))$c['source_errors'][]='A payroll payout is missing its private original transfer link; do not pay it again.';
            $adjustments=[];foreach($public as $e)if($e['kind']==='payroll_adjustment')$adjustments[(int)$e['id']]=$e;$seen=[];
            foreach($corrections as $e){$d=$e['data'];$pub=$adjustments[(int)$d['canonical_id']]??null;$meta=$pub?json_decode($pub['payload'],true,512,JSON_THROW_ON_ERROR):[];if(!$pub||isset($seen[(int)$d['canonical_id']])||($meta['type']??'')!==$d['type']||(int)($meta['cost_delta']??PHP_INT_MAX)!==(int)$d['cost_delta']||(int)$pub['amount_minor']!==(int)$d['cost_delta']||(int)($meta['paid_delta']??PHP_INT_MAX)!==(int)$d['paid_delta']||(int)($meta['payroll_id']??0)!==$id||(int)$pub['actor_id']!==(int)$e['actor_id'])$c['source_errors'][]='Payroll compensation and private correction do not reconcile.';$seen[(int)$d['canonical_id']]=true;}
            if(count($adjustments)!==count($seen)||count($corrections)!==count($seen))$c['source_errors'][]='A payroll accounting adjustment is missing its private correction.';
            $c['source_errors']=array_values(array_unique($c['source_errors']));
        }
        $c['version']=$this->hash([(int)$c['root']['id'],array_column($c['history'],'id'),$public]);return $c;
    }
    private function guardPayroll(array $c):void{if($c['source_errors'])throw new RuntimeException(implode(' ',$c['source_errors']));}
    private function correctPayroll(int $actor,int $id,array $c,array $in):array{
        if(!$c['approved'])throw new RuntimeException('Correct an approved payroll; return an unapproved draft for revision.');$this->guardPayroll($c);if(($in['verified']??'')!=='1')throw new RuntimeException('Independently verify the original source and exact correction effects.');FinanceService::guardSource($this->access,$this->today());
        $type=$in['type']??'';$before=$c['approved'];$after=$before;$paid=$c['paid'];$paymentId=null;$beforeAmount=null;$afterAmount=null;
        if($type==='components'){
            foreach(['base','allowance','overtime','deductions','employer'] as $name)$after[$name]=R::minor(R::text($in[$name]??'',24,true));$after['gross']=$after['base']+$after['allowance']+$after['overtime'];$after['net']=$after['gross']-$after['deductions'];$after['cost']=$after['gross']+$after['employer'];
            if($after['net']<0||$after['cost']>999999999999)throw new RuntimeException('Deductions cannot exceed gross pay; stay within supported payroll limits.');$changed=false;foreach(['base','allowance','overtime','deductions','employer'] as $n)if($before[$n]!==$after[$n])$changed=true;if(!$changed)throw new RuntimeException('No payroll component changed.');
        }elseif($type==='payment'){
            $paymentId=$this->id($in['payment_id']??'');$payment=null;foreach($c['payments'] as $p)if((int)$p['id']===$paymentId)$payment=$p;if(!$payment)throw new RuntimeException('Choose one original transfer from this payroll.');$beforeAmount=(int)$payment['data']['amount'];$afterAmount=R::minor(R::text($in['amount']??'',24,true));$paid+=$afterAmount-$beforeAmount;if($paid>999999999999||$afterAmount===$beforeAmount)throw new RuntimeException('Enter a changed evidenced amount within the ledger limit.');
            if(($in['recording_error']??'')!=='1')throw new RuntimeException('Confirm this corrects a recording error, not money returned or an actual employee overpayment.');
            if($this->access->repo->run("SELECT id FROM finance_events WHERE kind='cost_reconciled' AND ((target_type='request' AND target_id=?) OR (target_type='payout' AND target_id=?)) LIMIT 1",[(string)$c['request_id'],(string)$payment['data']['canonical_id']])->fetchColumn())throw new RuntimeException('This payout is independently matched; reconcile its allocation before changing the recorded amount.');
        }else throw new RuntimeException('Choose payroll components or one recorded transfer.');
        $reason=R::text($in['reason']??'',2000,true);$evidence=R::text($in['evidence_reference']??'',2000,true);$employeeNote=R::text($in['employee_note']??'',500);
        $costDelta=$after['cost']-$before['cost'];$paidDelta=$paid-$c['paid'];$meta=['payroll_id'=>$id,'type'=>$type,'cost_delta'=>$costDelta,'paid_delta'=>$paidDelta,'service_month'=>$c['root']['data']['period'],'original_cost_date'=>$c['original_approved']['posting_date'],'actual_money_moved'=>0];
        $canonical=$this->canonical($actor,'payroll_adjustment','request',(string)$c['request_id'],$costDelta,$this->today(),$meta);
        $data=['staff_id'=>$c['root']['data']['staff_id'],'period'=>$c['root']['data']['period'],'type'=>$type,'before'=>$before,'after'=>$after,'payment_id'=>$paymentId,'before_amount'=>$beforeAmount,'after_amount'=>$afterAmount,'before_paid'=>$c['paid'],'after_paid'=>$paid,'cost_delta'=>$costDelta,'paid_delta'=>$paidDelta,'before_due'=>$c['due'],'after_due'=>max(0,$after['net']-$paid),'before_recovery'=>$c['recovery_due'],'after_recovery'=>max(0,$paid-$after['net']),'reason'=>$reason,'evidence_reference'=>$evidence,'employee_note'=>$employeeNote,'canonical_id'=>$canonical];
        return [$id,$this->event($actor,'payroll_corrected',$data,$id)];
    }
    public function statementVersions(int $actor,int $id):array{
        $this->permit($actor);$owner=$this->owner($actor);$rows=$this->access->repo->run("SELECT * FROM finance_events WHERE target_type='payroll' AND target_id=? AND kind='payroll_published' ORDER BY id DESC",[(string)$id])->fetchAll();$out=[];
        foreach($rows as $e){if(!$owner){$safe=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);if((int)($safe['recipient_id']??0)!==$actor||!$this->access->repo->run('SELECT id FROM hr_staff WHERE id=? AND user_id=?',[(int)$safe['staff_id'],$actor])->fetchColumn())continue;}$out[]=['id'=>(int)$e['id'],'published_at'=>$e['created_at']];}return $out;
    }
}
