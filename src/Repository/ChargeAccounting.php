<?php
namespace Tivoli\Repository;

/** Append-only charge effects, separate from receipts, refunds and inventory. */
final class ChargeAccounting
{
    public static function entries(AccessRepository $r,int $booking,int $after=0):array{
        if(!ReceiptAccounting::installed($r))return [];
        $rows=$r->run("SELECT id,amount_minor,payload,created_at,effective_date FROM finance_events WHERE kind='gc_posted' AND target_type='booking' AND target_id=? AND id>? ORDER BY id",[(string)$booking,$after])->fetchAll();foreach($rows as &$e){$e['data']=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);$e['amount_minor']=(int)$e['amount_minor'];}return $rows;
    }
    public static function totals(AccessRepository $r,int $booking,int $after=0):array{$out=['room'=>0,'extras'=>0,'incidental'=>0];foreach(self::entries($r,$booking,$after) as $e)$out[$e['data']['charge_type']==='extra'?'extras':$e['data']['charge_type']]+=$e['amount_minor'];return $out;}
    public static function line(AccessRepository $r,int $booking,string $type,int $source,int $base):int{foreach(self::entries($r,$booking) as $e)if($e['data']['charge_type']===$type&&(int)$e['data']['source_id']===$source)$base+=$e['amount_minor'];return $base;}
    public static function frozenCursor(AccessRepository $r,int $booking):int{if(!ReceiptAccounting::installed($r))return 0;$p=$r->run("SELECT payload FROM finance_events WHERE kind='gc_snapshot' AND target_type='booking' AND target_id=? ORDER BY id DESC LIMIT 1",[(string)$booking])->fetchColumn();return $p?(int)json_decode($p,true,512,JSON_THROW_ON_ERROR)['cursor']:0;}
    public static function snapshot(\Tivoli\Service\AccessService $a,int $actor,int $booking,string $at):void{
        $rows=self::entries($a->repo,$booking);if(!$rows)return;if(!$a->repo->db->inTransaction())throw new \RuntimeException('Charge cursor requires the departure transaction.');$last=end($rows);$a->repo->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES('gc_snapshot','booking',?,0,?,?,?,?)",[(string)$booking,substr($at,0,10),$actor,json_encode(['cursor'=>(int)$last['id']],JSON_THROW_ON_ERROR),$at]);$a->audit($actor,'guest_charge.snapshot','booking',(string)$booking,'recorded');
    }
    public static function period(AccessRepository $r,string $start,string $end,?string $asOf=null,int $item=0,int $room=0):array{
        $out=['room'=>0,'extras'=>0,'incidental'=>0,'total'=>0,'count'=>0,'rows'=>[]];if(!ReceiptAccounting::installed($r))return $out;$values=[$start,$end];$where='';if($asOf!==null){$where.=' AND e.created_at<?';$values[]=$asOf;}if($item){$where.=" AND JSON_UNQUOTE(JSON_EXTRACT(e.payload,'$.charge_type'))='extra' AND JSON_UNQUOTE(JSON_EXTRACT(e.payload,'$.extra_id'))=?";$values[]=(string)$item;}if($room){$where.=' AND b.room_id=?';$values[]=$room;}
        $rows=$r->run("SELECT e.id,e.target_id booking_id,e.amount_minor,e.payload,e.created_at,e.effective_date FROM finance_events e JOIN bookings b ON b.id=CAST(e.target_id AS UNSIGNED) WHERE e.kind='gc_posted' AND e.target_type='booking' AND e.effective_date BETWEEN ? AND ?".$where.' ORDER BY e.id',$values)->fetchAll();foreach($rows as $e){$d=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);$key=$d['charge_type']==='extra'?'extras':$d['charge_type'];$out[$key]+=(int)$e['amount_minor'];$out['total']+=(int)$e['amount_minor'];$out['count']++;$e['data']=$d;$out['rows'][]=$e;}return $out;
    }
}
