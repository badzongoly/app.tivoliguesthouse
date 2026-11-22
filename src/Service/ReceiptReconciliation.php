<?php
namespace Tivoli\Service;
use Tivoli\Repository\ReceiptAccounting as A;
use Tivoli\Repository\ReceiptReallocation as V;
use Tivoli\Domain\Finance\CashFundingGraph;
use Tivoli\Domain\Finance\CashResolution as C;
use Tivoli\Domain\Finance\CashCustody;
use Tivoli\Security\PermissionDenied;
use RuntimeException;

/** Review and post a conserving dependency bundle inside ReceiptCorrectionService's transaction. */
final class ReceiptReconciliation
{
    public static function candidates(AccessService $a,int $actor,int $source,?string $clock=null):array{
        $r=$a->repo;$owner=(new FinanceService($a,$clock))->canPostLate($actor);$rows=$r->run('SELECT p.*,s.actor_id,s.reference_no FROM payments p JOIN stay_receipts s ON s.payment_id=p.id WHERE p.id<>?'.($owner?'':' AND s.actor_id=?').' ORDER BY p.id DESC LIMIT 100',$owner?[$source]:[$source,$actor])->fetchAll();$rows=A::project($r,$rows);return array_values(array_filter($rows,fn($p)=>$p['position']['amount']>0&&in_array($p['position']['method'],['cash','momo'],true)));
    }
    public static function dependencies(array $events,int $payment):array{
        $roots=[];$out=[];$cases=[];
        foreach($events as $e)if(in_array($e['kind'],['deposit_recorded','cash_transfer_sent'],true)&&!empty($e['data']['allocations'][(string)$payment])){$roots[$e['kind']==='deposit_recorded'?'deposit':'handover'][(string)$e['id']]=true;$out[(int)$e['id']]=$e;}
        foreach($events as $e){if(($e['target_type']==='payment'&&(int)$e['target_id']===$payment&&in_array($e['kind'],['receipt_matched','cd_credit','cd_reversed','ra_wallet_in','ra_wallet_out'],true))||isset($roots[$e['target_type']][(string)$e['target_id']]))$out[(int)$e['id']]=$e;
            if($e['kind']==='cd_reported'&&((($e['data']['source_type']??'')==='payment'&&(int)$e['data']['source_id']===$payment)||isset($roots[$e['data']['source_type']??''][(string)($e['data']['source_id']??0)]))){$cases[(int)$e['id']]=true;$out[(int)$e['id']]=$e;}}
        foreach($events as $e)if(isset($cases[(int)($e['data']['case_id']??0)])||($e['target_type']==='cash_discrepancy'&&isset($cases[(int)$e['target_id']])))$out[(int)$e['id']]=$e;ksort($out);return array_values($out);
    }
    private static function id(string $v):?int{if($v==='')return null;if(!preg_match('/^[1-9][0-9]{0,17}$/D',$v))throw new RuntimeException('Choose a valid supporting receipt.');return (int)$v;}
    private static function active(\Tivoli\Repository\AccessRepository $r,int $id):bool{return (bool)$r->run("SELECT root.id FROM finance_events root WHERE root.kind='rc_requested' AND JSON_UNQUOTE(JSON_EXTRACT(root.payload,'$.payment_id'))=? AND NOT EXISTS(SELECT 1 FROM finance_events decision WHERE decision.target_type='receipt_correction' AND decision.target_id=CAST(root.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AND decision.kind IN ('rc_approved','rc_rejected','rc_withdrawn')) LIMIT 1",[(string)$id])->fetchColumn();}
    private static function reviewedCase(AccessService $a,int $actor,int $payment,?string $clock):?string{
        $id=CashDiscrepancyService::caseId($a->repo,'payment',$payment);if(!$id)return null;$case=(new CashDiscrepancyService($a,$clock))->detail($actor,$id);if($case['status']==='review_required')throw new RuntimeException('Resolve the unreviewed or reopened wallet dispute before reallocating that proof.');return $case['version'];
    }
    private static function support(AccessService $a,int $actor,int $id,int $source):array{
        if($id===$source)throw new RuntimeException('A receipt cannot supply its own unexplained difference.');$s=A::position($a->repo,$id);if(!(new FinanceService($a))->canPostLate($actor)&&(int)$s['original']['actor_id']!==$actor)throw new PermissionDenied('Choose a supporting receipt you are authorized to review.');if(A::collection($a->repo,$id)!==$id||$s['position']['amount']<1||self::active($a->repo,$id))throw new RuntimeException('The supporting receipt is compensated, empty or has an unresolved correction.');return $s;
    }
    public static function plan(AccessService $a,int $actor,array $source,array $after,array $in,?int $keeper,?string $clock=null):?array{
        if(!$source['dependencies'])return null;if(($in['reconcile']??'')!=='1')throw new RuntimeException('Review and confirm the linked money evidence before submitting.');
        $f=new FinanceService($a,$clock);$r=$a->repo;$events=V::events($f->events());$id=(int)$source['original']['id'];$before=$source['position'];$beforeCash=$before['method']==='cash'?$before['amount']:0;$afterCash=$after['method']==='cash'?$after['amount']:0;
        $cashSupport=self::id($in['cash_support_id']??'');$walletSupport=self::id($in['wallet_support_id']??'');if($keeper&&$before['method']==='cash'){$cashSupport=$cashSupport??$keeper;if($cashSupport!==$keeper)throw new RuntimeException('A duplicate must retain its actual collection as the supporting receipt.');}
        if($keeper&&$before['method']==='momo'){$walletSupport=$walletSupport??$keeper;if($walletSupport!==$keeper)throw new RuntimeException('A duplicate must retain its actual collection as the matched receipt.');}
        $cashViews=[];$funding=[];$wallet=null;$cashChanges=[];
        if($cashSupport&&$afterCash>=$beforeCash)throw new RuntimeException('No replacement cash funding is needed for this correction.');
        if($beforeCash!==$afterCash){
            $origin=(int)$source['original']['actor_id'];$cursor=(int)$r->run('SELECT payment_cursor FROM finance_schema WHERE id=1')->fetchColumn();if(!$origin||$id<=$cursor)throw new RuntimeException('Actual original cash custody is unknown.');
            $cash=$f->cash();$rows=$cash['rows'];$shift=$cashSupport?$beforeCash-$afterCash:0;
            if($cashSupport){$support=self::support($a,$actor,$cashSupport,$id);if($support['position']['method']!=='cash'||(int)$support['original']['actor_id']!==$origin||$cashSupport<=$cursor||$support['position']['amount']<$shift)throw new RuntimeException('Supporting cash must be a real unused receipt from the same evidenced collector.');
                $earliest=null;foreach($events as $e){if(!empty($e['data']['allocations'][(string)$cashSupport]))throw new RuntimeException('Supporting cash already has movement evidence; do not consume it twice.');if(in_array($e['kind'],['deposit_recorded','cash_transfer_sent'],true)&&!empty($e['data']['allocations'][(string)$id]))$earliest=min($earliest??$e['created_at'],$e['created_at']);}
                if(!$support['original']['created_at']||($earliest&&$support['original']['created_at']>$earliest))throw new RuntimeException('Supporting cash did not exist when the original movement occurred.');$funding['cash']=['payment_id'=>$cashSupport,'amount'=>$shift,'token'=>hash('sha256',json_encode($support,JSON_THROW_ON_ERROR))];
            }
            $graph=(new CashFundingGraph())->plan($rows,$events,$id,$afterCash,$origin,$cashSupport,$shift);$cashViews=$graph['views'];$afterRows=[];$found=false;
            foreach($rows as $row){if((string)$row['id']===(string)$id){$row['minor']=$afterCash;$found=true;}$afterRows[]=$row;}
            if(!$found&&$afterCash)$afterRows[]=['id'=>$id,'actor_id'=>$origin,'minor'=>$afterCash,'created_at'=>$source['original']['created_at']];
            $deposits=[];$index=C::index($graph['events']);foreach($graph['events'] as $e)if($e['kind']==='deposit_recorded')$deposits[]=['event'=>$e,'position'=>C::position('deposit',(int)$e['id'],(int)$e['amount_minor'],$index['deposit'][(string)$e['id']]??[],$e['data']['allocations'])];
            foreach($afterRows as &$row){$reserved=0;foreach($deposits as $dep)$reserved+=(int)($dep['event']['data']['allocations'][(string)$row['id']]??0)-(int)($dep['position']['returns'][(string)$row['id']]??0);$row['held']=$row['minor']-$reserved;if($row['held']<0)throw new RuntimeException('The correction would leave unsupported cash allocation.');}unset($row);
            $afterCustody=CashCustody::apply(['rows'=>$afterRows],$graph['events']);$watched=[$id=>true];if($cashSupport)$watched[$cashSupport]=true;$beforeRows=array_column($rows,null,'id');foreach($afterCustody['rows'] as $row)if(isset($watched[(int)$row['id']]))$cashChanges[]=['payment_id'=>(int)$row['id'],'before'=>$beforeRows[$row['id']]['custody']??[],'after'=>$row['custody']];
        }
        if($before['method']==='momo'){
            if($after['method']==='momo'&&mb_strtolower(trim($after['reference']),'UTF-8')!==mb_strtolower(trim($before['reference']),'UTF-8')&&C::position('payment',$id,$before['amount'],$events)['verified']>0){
                $references=[];foreach($events as $e)if($e['target_type']==='payment'&&(int)$e['target_id']===$id&&in_array($e['kind'],['receipt_matched','cd_credit','ra_wallet_in'],true))$references[mb_strtolower(trim($e['data']['reference']??''),'UTF-8')]=true;
                $reference=mb_strtolower(trim($after['reference']),'UTF-8');if(count($references)!==1||!isset($references[$reference]))throw new RuntimeException('The corrected reference must identify this receipt\'s existing independently verified merchant proof. Reconcile different or ambiguous proof before retaining verification.');
            }
            $position=C::position('payment',$id,$before['amount'],$events);$afterMomo=$after['method']==='momo'?$after['amount']:0;$excess=max(0,$position['verified']-$afterMomo);
            if($excess){if(!$walletSupport)throw new RuntimeException('Existing verified wallet credit exceeds the corrected receipt. Select the real existing receipt that owns the difference.');$target=self::support($a,$actor,$walletSupport,$id);if($target['position']['method']!=='momo')throw new RuntimeException('Choose an existing MoMo receipt for the verified wallet difference.');$capacity=C::position('payment',$walletSupport,$target['position']['amount'],$events);if($capacity['remaining']<$excess)throw new RuntimeException('The supporting receipt has insufficient unmatched capacity.');
                $pools=[];foreach($events as $e)if($e['target_type']==='payment'&&(int)$e['target_id']===$id){if($e['kind']==='cd_reversed')throw new RuntimeException('Reversed wallet evidence needs a separately reconciled credit pool.');if(in_array($e['kind'],['receipt_matched','cd_credit','ra_wallet_in'],true)){$proof=(int)($e['data']['proof_event_id']??$e['id']);$pools[$proof]=$e['data']['reference']??'';}}
                if(count($pools)!==1)throw new RuntimeException('Multiple or unsupported wallet credits need explicit pool reconciliation.');$proof=(int)array_key_first($pools);$sourceCase=self::reviewedCase($a,$actor,$id,$clock);$targetCase=self::reviewedCase($a,$actor,$walletSupport,$clock);$proofRow=$r->run('SELECT * FROM finance_events WHERE id=?',[$proof])->fetch();if(!$proofRow||$proofRow['target_type']!=='payment')throw new RuntimeException('Original merchant proof provenance is unavailable.');$proofCase=self::reviewedCase($a,$actor,(int)$proofRow['target_id'],$clock);
                $targetEvents=self::dependencies($events,$walletSupport);$wallet=['from'=>$id,'to'=>$walletSupport,'amount'=>$excess,'proof_event_id'=>$proof,'reference'=>$pools[$proof],'proof_token'=>hash('sha256',json_encode([$proofRow,$proofCase,$sourceCase],JSON_THROW_ON_ERROR)),'target_token'=>hash('sha256',json_encode([$target,$capacity,$targetEvents,$targetCase],JSON_THROW_ON_ERROR))];$funding['wallet']=['payment_id'=>$walletSupport,'amount'=>$excess];
            }elseif($walletSupport)throw new RuntimeException('There is no excess verified wallet credit to reassign.');
        }elseif($walletSupport)throw new RuntimeException('This source has no verified MoMo amount to reassign.');
        return ['cash_views'=>$cashViews,'cash_changes'=>$cashChanges,'funding'=>$funding,'wallet'=>$wallet,'inputs'=>['reconcile'=>'1','cash_support_id'=>$cashSupport?(string)$cashSupport:'','wallet_support_id'=>$walletSupport?(string)$walletSupport:''],'actual_money_moved'=>0];
    }
    public static function post(AccessService $a,int $actor,int $request,int $payment,array $plan,string $now):array{
        if(!$a->repo->db->inTransaction()||!(new FinanceService($a,$now))->canPostLate($actor))throw new RuntimeException('Post reconciliation only inside the authorized owner correction transaction.');
        $r=$a->repo;$ids=[];$write=function(string $kind,string $type,int $target,int $amount,array $data)use($r,$actor,$request,$payment,$now,&$ids){$r->run('INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES(?,?,?,?,?,?,?,?)',[$kind,$type,(string)$target,$amount,substr($now,0,10),$actor,json_encode(['request_id'=>$request,'payment_id'=>$payment]+$data,JSON_THROW_ON_ERROR),$now]);$ids[]=(int)$r->db->lastInsertId();};
        foreach($plan['cash_views'] as $view)$write('ra_cash_view','cash_event',$view['event_id'],0,['data'=>$view['data']]);
        if($w=$plan['wallet']){foreach(['from'=>'ra_wallet_out','to'=>'ra_wallet_in'] as $side=>$kind){$target=(int)$w[$side];$data=$w+['case_id'=>CashDiscrepancyService::caseId($r,'payment',$target)];$write($kind,'payment',$target,(int)$w['amount'],$data);}}
        $finance=new FinanceService($a,$now);$finance->cash();if($w=$plan['wallet'])foreach([$w['from'],$w['to']] as $target){$pos=A::position($r,(int)$target)['position'];C::position('payment',(int)$target,$pos['method']==='momo'?$pos['amount']:0,$finance->events());}return $ids;
    }
}
