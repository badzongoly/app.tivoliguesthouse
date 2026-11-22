<?php
namespace Tivoli\Repository;
use Tivoli\Domain\Stay\StayRules as R;

/** Current recovery balances from immutable departures and their explicit later legs; no stay rebilling. */
final class OwnerGuestBalances
{
    public static function read(AccessRepository $r,array $events):array{
        $receiptCursors=[];$chargeCursors=[];$reports=[];$receiptChanges=[];$chargeChanges=[];
        foreach($events as $e){$d=$e['data'];$id=(int)$e['target_id'];
            if($e['kind']==='rc_snapshot'&&$e['target_type']==='booking')$receiptCursors[$id]=(int)$d['cursor'];
            if($e['kind']==='gc_snapshot'&&$e['target_type']==='booking')$chargeCursors[$id]=(int)$d['cursor'];
            if($e['kind']==='departure_reported'&&$e['target_type']==='booking')$reports[$id]=$e;
            if($e['kind']==='rc_posted'&&$e['target_type']==='payment')$receiptChanges[(int)$d['booking_id']][]=$e;
            if($e['kind']==='gc_posted'&&$e['target_type']==='booking')$chargeChanges[$id][]=$e;
        }
        $recovered=[];foreach($r->run('SELECT booking_id,amount FROM payments WHERE recovery_event_id IS NOT NULL')->fetchAll() as $p)$recovered[(int)$p['booking_id']]=($recovered[(int)$p['booking_id']]??0)+R::minor((string)$p['amount']);
        $refunds=[];foreach($r->run('SELECT id,booking_id,amount_minor FROM stay_refunds ORDER BY id')->fetchAll() as $f)$refunds[(int)$f['booking_id']][]=$f;
        $frozen=[];foreach($r->run('SELECT * FROM stay_departures')->fetchAll() as $d)$frozen[(int)$d['booking_id']]=$d;
        $ids=array_unique([...array_keys($frozen),...array_keys($reports)]);if(!$ids)return [];
        $marks=implode(',',array_fill(0,count($ids),'?'));$rows=$r->run("SELECT b.id,b.status,b.rate_per_night,room.name room_name FROM bookings b LEFT JOIN rooms room ON room.id=b.room_id WHERE b.id IN ($marks) ORDER BY b.id DESC",array_values($ids))->fetchAll();$out=[];
        $found=array_column($rows,'id');foreach(array_diff($ids,$found) as $missing)$out[]=['id'=>(int)$missing,'unavailable'=>true];
        foreach($rows as $b){$id=(int)$b['id'];if($b['room_name']===null){$out[]=['id'=>$id,'unavailable'=>true];continue;}$d=$frozen[$id]??null;$report=$reports[$id]??null;
            if(!$d){if($report)$out[]=$b+['frozen'=>null,'report'=>$report,'balance'=>null,'changed'=>false];continue;}
            $balance=(int)$d['balance_minor']-($recovered[$id]??0);$changed=isset($recovered[$id]);
            foreach($refunds[$id]??[] as $f)if((int)$f['id']>(int)$d['refund_cursor']){$balance+=(int)$f['amount_minor'];$changed=true;}
            foreach($receiptChanges[$id]??[] as $e)if((int)$e['id']>($receiptCursors[$id]??0)){$balance-=(int)$e['amount_minor'];$changed=true;}
            foreach($chargeChanges[$id]??[] as $e)if((int)$e['id']>($chargeCursors[$id]??0)){$balance+=(int)$e['amount_minor'];$changed=true;}
            if(!$report&&(int)$d['balance_minor']===0&&!$changed)continue;
            $out[]=$b+['frozen'=>$d,'report'=>$report,'balance'=>$balance,'changed'=>$changed];
        }
        return $out;
    }
}
