<?php
namespace Tivoli\Domain\Finance;
use RuntimeException;

/** Replays original movement amounts, mapping their funding to evidenced effective receipt identities. */
final class CashFundingGraph
{
    private array $pools=[];
    private array $transfers=[];
    private array $deposits=[];
    private static function add(array &$to,array $parts):void{foreach($parts as $id=>$n)if($n)$to[$id]=($to[$id]??0)+(int)$n;}
    private static function take(array &$from,int $amount):array{
        $parts=[];foreach($from as $id=>&$n){$use=min($amount,(int)$n);if($use){$parts[$id]=$use;$n-=$use;$amount-=$use;}if(!$amount)break;}unset($n);
        if($amount)throw new RuntimeException('Existing movements exceed the evidenced cash funding. Choose valid unused supporting cash or reconcile the source first.');return $parts;
    }
    private function takeActor(string $source,int $actor,int $amount):array{$this->pools[$source][$actor]??=[];return self::take($this->pools[$source][$actor],$amount);}
    private function putActor(string $source,int $actor,array $parts):void{$this->pools[$source][$actor]??=[];self::add($this->pools[$source][$actor],$parts);}
    private static function consume(array &$from,array &$to,string $source,int $amount,?int $onlyActor=null):array{
        $out=[];foreach($from[$source]??[] as $actor=>$ignored){if($onlyActor!==null&&(int)$actor!==$onlyActor)continue;$use=min($amount,array_sum($from[$source][$actor]));if(!$use)continue;$parts=self::take($from[$source][$actor],$use);$to[$source][$actor]??=[];self::add($to[$source][$actor],$parts);$out[$actor]=$parts;$amount-=$use;if(!$amount)break;}
        if($amount)throw new RuntimeException('Partial credit, return or acknowledgement funding is not supported by its original history.');return $out;
    }
    public function plan(array $rows,array $events,int $source,int $afterCash,int $origin,?int $support=null,int $shift=0):array{
        $this->pools=[];$this->transfers=[];$this->deposits=[];
        foreach($rows as $row){$id=(string)$row['id'];$actor=(int)($row['actor_id']??0);$this->pools[$id][$actor]=[$id=>(int)$row['minor']];}
        $this->pools[(string)$source][$origin]=[];if($afterCash)$this->pools[(string)$source][$origin][(string)$source]=$afterCash;
        if($support&&$shift){if($support===$source||$shift<1)throw new RuntimeException('Choose another evidenced cash receipt.');$available=&$this->pools[(string)$support][$origin];if(!is_array($available)||array_sum($available)<$shift)throw new RuntimeException('Supporting cash is unavailable under the evidenced collector.');self::take($available,$shift);$this->pools[(string)$source][$origin][(string)$support]=$shift;unset($available);}
        $views=[];$projected=[];
        foreach($events as $e){$d=$e['data'];$kind=$e['kind'];$id=(int)$e['id'];$next=$d;
            if($kind==='cash_transfer_sent'){
                $new=[];$funds=[];foreach($d['allocations'] as $old=>$amount){$parts=$this->takeActor((string)$old,(int)$e['actor_id'],(int)$amount);$funds[(string)$old][(int)$e['actor_id']]=$parts;self::add($new,$parts);}$this->transfers[$id]=['funds'=>$funds,'recipient'=>(int)$d['recipient_id'],'sender'=>(int)$e['actor_id']];$next['allocations']=$new;
            }elseif($kind==='cash_transfer_acknowledged'){
                $t=&$this->transfers[(int)$e['target_id']];if(!$t)throw new RuntimeException('Cash handover provenance is incomplete.');$total=0;foreach($t['funds'] as $old=>$actors)foreach($actors as $actor=>$parts){$total+=array_sum($parts);$this->putActor((string)$old,$t['recipient'],$parts);$t['funds'][$old][$actor]=[];}if($total!==(int)$e['amount_minor'])throw new RuntimeException('Full handover acknowledgement overlaps partial settlement.');unset($t);
            }elseif(in_array($kind,['cd_acknowledged','cd_cash_returned'],true)&&$e['target_type']==='handover'){
                $t=&$this->transfers[(int)$e['target_id']];if(!$t)throw new RuntimeException('Cash transfer evidence is incomplete.');$new=[];$discard=[];foreach($d['allocations'] as $old=>$amount){$taken=self::consume($t['funds'],$discard,(string)$old,(int)$amount);foreach($taken as $actor=>$parts){self::add($new,$parts);$this->putActor((string)$old,$kind==='cd_acknowledged'?$t['recipient']:$t['sender'],$parts);}}$next['allocations']=$new;unset($t);
            }elseif($kind==='deposit_recorded'){
                $new=[];$custody=[];$funds=[];foreach($d['allocations'] as $old=>$amount){
                    $actors=$d['custody_allocations'][(string)$old]??null;
                    if($actors===null){if((string)$old===(string)$source)throw new RuntimeException('Affected deposit custody evidence is missing.');$actors=[];$left=(int)$amount;foreach($this->pools[(string)$old]??[] as $actor=>$pool){$use=min($left,array_sum($pool));if($use)$actors[$actor]=$use;$left-=$use;if(!$left)break;}if($left)throw new RuntimeException('Historical deposit funding cannot be reconstructed safely.');}
                    foreach($actors as $actor=>$part){$parts=$this->takeActor((string)$old,(int)$actor,(int)$part);$funds[(string)$old][(int)$actor]=$parts;self::add($new,$parts);foreach($parts as $receipt=>$n)$custody[$receipt][$actor]=($custody[$receipt][$actor]??0)+$n;}
                }$this->deposits[$id]=['free'=>$funds,'verified'=>[]];$next['allocations']=$new;if(isset($d['custody_allocations']))$next['custody_allocations']=$custody;
            }elseif($kind==='deposit_confirmed'){
                $dep=&$this->deposits[(int)$e['target_id']];if(!$dep)throw new RuntimeException('Deposit verification provenance is unavailable.');foreach($dep['free'] as $old=>$actors)foreach($actors as $actor=>$parts){$dep['verified'][$old][$actor]??=[];self::add($dep['verified'][$old][$actor],$parts);$dep['free'][$old][$actor]=[];}unset($dep);
            }elseif(in_array($kind,['cd_credit','cd_reversed','cd_cash_returned'],true)&&$e['target_type']==='deposit'){
                $dep=&$this->deposits[(int)$e['target_id']];if(!$dep)throw new RuntimeException('Deposit resolution provenance is unavailable.');$new=[];$newCustody=[];
                if($kind==='cd_cash_returned'){
                    $discard=[];foreach($d['custody_parts'] as $old=>$actors)foreach($actors as $actor=>$amount){$taken=self::consume($dep['free'],$discard,(string)$old,(int)$amount,(int)$actor);foreach($taken as $who=>$parts){self::add($new,$parts);$this->putActor((string)$old,(int)$who,$parts);foreach($parts as $receipt=>$n)$newCustody[$receipt][$who]=($newCustody[$receipt][$who]??0)+$n;}}$next['custody_parts']=$newCustody;
                }else{foreach($d['allocations'] as $old=>$amount){if($kind==='cd_credit')$taken=self::consume($dep['free'],$dep['verified'],(string)$old,(int)$amount);else $taken=self::consume($dep['verified'],$dep['free'],(string)$old,(int)$amount);foreach($taken as $parts)self::add($new,$parts);}}
                $next['allocations']=$new;unset($dep);
            }
            if(isset($next['allocations'])&&in_array($kind,['cash_transfer_sent','deposit_recorded','cd_acknowledged','cd_credit','cd_reversed','cd_cash_returned'],true)&&array_sum($next['allocations'])!==(int)$e['amount_minor'])throw new RuntimeException('A funding view would change the actual movement amount.');
            if($next!==$d)$views[]=['event_id'=>$id,'kind'=>$kind,'amount_minor'=>(int)$e['amount_minor'],'before'=>array_intersect_key($d,array_flip(['allocations','custody_allocations','custody_parts'])),'data'=>array_intersect_key($next,array_flip(['allocations','custody_allocations','custody_parts']))];$e['data']=$next;$projected[]=$e;
        }
        return ['views'=>$views,'events'=>$projected];
    }
}
