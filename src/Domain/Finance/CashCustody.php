<?php
namespace Tivoli\Domain\Finance;
use RuntimeException;

/** Rebuild custody without changing original receipts or their deposit deadlines. */
final class CashCustody
{
    public static function apply(array $cash,array $events): array
    {
        $positions=[];$sent=[];$done=[];$since=[];$settled=[];
        foreach($cash['rows'] as $row){$source=(string)$row['id'];$origin=(int)($row['actor_id']??0);$positions[$source]=[$origin=>(int)$row['minor']];$since[$source][$origin]=$row['created_at'];}
        $take=static function(string $source,int $actor,int $amount)use(&$positions):void{
            if($amount<0||($positions[$source][$actor]??0)<$amount)throw new RuntimeException('Cash custody does not reconcile. Contact the owner before moving cash.');
            $positions[$source][$actor]-=$amount;
        };
        foreach($events as $event){
            $data=$event['data'];
            if($event['kind']==='cash_transfer_sent')$sent[(string)$event['id']]=$event;
            if($event['kind']==='cash_transfer_acknowledged'){
                $original=$sent[$event['target_id']]??null;
                if(!$original||isset($done[$event['target_id']]))throw new RuntimeException('Cash transfer history requires owner review.');
                foreach($original['data']['allocations'] as $source=>$amount){
                    $take((string)$source,(int)$original['actor_id'],(int)$amount);
                    $to=(int)$original['data']['recipient_id'];$positions[(string)$source][$to]=($positions[(string)$source][$to]??0)+(int)$amount;
                    // Conservative bound when the same receipt returns to an existing custodian.
                    $since[(string)$source][$to]=max($since[(string)$source][$to]??'',$event['created_at']);
                }
                $done[$event['target_id']]=true;
            }
            if(in_array($event['kind'],['cd_acknowledged','cd_cash_returned'],true)&&$event['target_type']==='handover'){
                $original=$sent[$event['target_id']]??null;if(!$original||isset($done[$event['target_id']]))throw new RuntimeException('Resolved handover has inconsistent custody history.');
                foreach($data['allocations'] as $source=>$amount){
                    $settled[$event['target_id']][$source]=($settled[$event['target_id']][$source]??0)+(int)$amount;
                    if($settled[$event['target_id']][$source]>(int)($original['data']['allocations'][$source]??0))throw new RuntimeException('Cash handover resolution exceeds its original source.');
                    if($event['kind']==='cd_acknowledged'){$take((string)$source,(int)$original['actor_id'],(int)$amount);$to=(int)$original['data']['recipient_id'];$positions[(string)$source][$to]=($positions[(string)$source][$to]??0)+(int)$amount;$since[(string)$source][$to]=max($since[(string)$source][$to]??'',$event['created_at']);}
                }
            }
            if($event['kind']==='cd_cash_returned'&&$event['target_type']==='deposit'){
                foreach($data['custody_parts'] as $source=>$parts)foreach($parts as $actor=>$part){$positions[(string)$source][$actor]=($positions[(string)$source][$actor]??0)+(int)$part;$since[(string)$source][$actor]=max($since[(string)$source][$actor]??'',$event['created_at']);}
            }
            if($event['kind']==='deposit_recorded'){
                foreach($data['allocations'] as $source=>$amount){
                    $source=(string)$source;
                    if(isset($data['custody_allocations'][$source])){
                        $parts=$data['custody_allocations'][$source];
                        if(array_sum($parts)!==(int)$amount)throw new RuntimeException('Deposit custody allocation does not reconcile.');
                        foreach($parts as $actor=>$part)$take($source,(int)$actor,(int)$part);
                    }else{
                        // Before custody tracking, deposits were allocated from the original collector.
                        $left=(int)$amount;
                        foreach($positions[$source]??[] as $actor=>$part){$n=min($left,$part);$take($source,(int)$actor,$n);$left-=$n;if(!$left)break;}
                        if($left)throw new RuntimeException('Historical cash allocation requires owner reconciliation.');
                    }
                }
            }
        }
        $reserved=[];
        foreach($sent as $id=>$event)if(!isset($done[$id]))foreach($event['data']['allocations'] as $source=>$amount){
            $actor=(int)$event['actor_id'];$remaining=(int)$amount-($settled[$id][$source]??0);$reserved[(string)$source][$actor]=($reserved[(string)$source][$actor]??0)+$remaining;
        }
        $custodians=[];
        foreach($cash['rows'] as &$row){
            $id=(string)$row['id'];$row['custody']=$positions[$id]??[];$row['transfer_reserved']=$reserved[$id]??[];$row['available']=[];$row['custody_since']=$since[$id]??[];
            if(array_sum($row['custody'])!==(int)$row['held'])throw new RuntimeException('Cash totals and custody do not reconcile.');
            foreach($row['custody'] as $actor=>$held){
                $pending=$row['transfer_reserved'][$actor]??0;
                if($pending>$held)throw new RuntimeException('Pending transfers exceed recorded cash custody.');
                $row['available'][$actor]=$held-$pending;
                $custodians[$actor]??=['held'=>0,'reserved'=>0,'available'=>0];
                $custodians[$actor]['held']+=$held;$custodians[$actor]['reserved']+=$pending;$custodians[$actor]['available']+=$held-$pending;
            }
        }
        unset($row);$cash['custodians']=$custodians;return $cash;
    }
}
