<?php
namespace Tivoli\Repository;
use Tivoli\Domain\Stay\StayRules as R;
use RuntimeException;

/** Derived receipt positions. Original payments and period snapshots are never rewritten. */
final class ReceiptAccounting
{
    public static function installed(AccessRepository $r):bool{return (bool)$r->run("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='finance_schema'")->fetchColumn()&&(bool)$r->run('SELECT id FROM finance_schema WHERE id=1')->fetchColumn();}
    public static function position(AccessRepository $r,int $id):array{
        $p=$r->run('SELECT p.*,s.actor_id,s.reference_no,s.recorded_at,u.name actor_name FROM payments p LEFT JOIN stay_receipts s ON s.payment_id=p.id LEFT JOIN users u ON u.id=s.actor_id WHERE p.id=?',[$id])->fetch();
        if(!$p)throw new RuntimeException('Original guest receipt not found.');
        $position=['amount'=>R::minor((string)$p['amount']),'method'=>$p['method'],'reference'=>$p['reference_no']??'','last_id'=>0];
        if(self::installed($r)){$e=$r->run("SELECT id,payload FROM finance_events WHERE kind='rc_posted' AND target_type='payment' AND target_id=? ORDER BY id DESC LIMIT 1",[(string)$id])->fetch();if($e){$d=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);$position=$d['after']+['last_id'=>(int)$e['id']];}}
        return ['original'=>$p,'position'=>$position];
    }
    public static function project(AccessRepository $r,array $rows):array{
        $positions=[];if(self::installed($r))foreach($r->run("SELECT id,target_id,payload FROM finance_events WHERE kind='rc_posted' AND target_type='payment' ORDER BY id")->fetchAll() as $e){$d=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);$positions[(int)$e['target_id']]=$d['after']+['last_id'=>(int)$e['id']];}
        foreach($rows as &$row)$row['position']=$positions[(int)$row['id']]??['amount'=>R::minor((string)$row['amount']),'method'=>$row['method'],'reference'=>$row['reference_no']??'','last_id'=>0];unset($row);return $rows;
    }
    public static function delta(AccessRepository $r,int $booking,int $after=0):int{
        if(!self::installed($r))return 0;
        return (int)$r->run("SELECT COALESCE(SUM(amount_minor),0) FROM finance_events WHERE kind='rc_posted' AND target_type='payment' AND id>? AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.booking_id'))=?",[$after,(string)$booking])->fetchColumn();
    }
    public static function cursor(AccessRepository $r,int $booking):int{
        if(!self::installed($r))return 0;
        return (int)$r->run("SELECT COALESCE(MAX(id),0) FROM finance_events WHERE kind='rc_posted' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.booking_id'))=?",[(string)$booking])->fetchColumn();
    }
    public static function frozenCursor(AccessRepository $r,int $booking):int{
        if(!self::installed($r))return 0;
        $p=$r->run("SELECT payload FROM finance_events WHERE kind='rc_snapshot' AND target_type='booking' AND target_id=? ORDER BY id DESC LIMIT 1",[(string)$booking])->fetchColumn();
        return $p?(int)json_decode($p,true,512,JSON_THROW_ON_ERROR)['cursor']:0;
    }
    public static function collection(AccessRepository $r,int $payment):int{
        if(!self::installed($r))return $payment;$seen=[];
        while(true){if(isset($seen[$payment]))throw new RuntimeException('Receipt collection links need owner reconciliation.');$seen[$payment]=true;$p=$r->run("SELECT payload FROM finance_events WHERE kind='rc_posted' AND target_type='payment' AND target_id=? AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.type'))='duplicate' ORDER BY id DESC LIMIT 1",[(string)$payment])->fetchColumn();if(!$p)return $payment;$d=json_decode($p,true,512,JSON_THROW_ON_ERROR);$payment=(int)$d['keeper_id'];if($payment<1)throw new RuntimeException('Retained receipt identity is unavailable.');}
    }
    public static function aliasOwner(AccessRepository $r,string $reference):?int{
        if(trim($reference)===''||!self::installed($r))return null;
        $p=$r->run("SELECT f.payload FROM finance_claims c JOIN finance_events f ON f.id=c.event_id WHERE c.scope='receipt_alias' AND c.identity_hash=?",[hash('sha256',mb_strtolower(trim($reference),'UTF-8'))])->fetchColumn();
        if(!$p)return null;$d=json_decode($p,true,512,JSON_THROW_ON_ERROR);return self::collection($r,(int)($d['alias_owner_payment_id']??$d['payment_id']));
    }
    public static function guardAlias(AccessRepository $r,string $reference,?int $matchingReceipt=null):void{
        $owner=self::aliasOwner($r,$reference);
        if($owner!==null&&($matchingReceipt===null||$owner!==self::collection($r,$matchingReceipt)))throw new RuntimeException('This reference is retained by an existing corrected collection.');
    }
    /** Posting-date channel legs; zero-reference annotations remain visible once. */
    public static function adjustmentsSql():string{
        $parts=[];
        foreach(['cash','momo'] as $method){
            $delta="CAST(JSON_UNQUOTE(JSON_EXTRACT(f.payload,'$.".$method."_delta')) AS SIGNED)";
            $parts[]="SELECT f.id,CAST(JSON_UNQUOTE(JSON_EXTRACT(f.payload,'$.booking_id')) AS UNSIGNED) booking_id,CONVERT('".$method."' USING utf8mb4) COLLATE utf8mb4_unicode_ci method,CAST((".$delta.")/100 AS DECIMAL(17,2)) amount,CONCAT('Recording adjustment #',f.id,' / original receipt #',f.target_id) note,f.created_at,'correction' source,CAST(f.target_id AS UNSIGNED) source_payment FROM finance_events f WHERE f.kind='rc_posted' AND f.target_type='payment' AND ".$delta."<>0";
        }
        $parts[]="SELECT f.id,CAST(JSON_UNQUOTE(JSON_EXTRACT(f.payload,'$.booking_id')) AS UNSIGNED),CONVERT(JSON_UNQUOTE(JSON_EXTRACT(f.payload,'$.after.method')) USING utf8mb4) COLLATE utf8mb4_unicode_ci,CAST(0 AS DECIMAL(17,2)),CONCAT('Reference correction #',f.id,' / original receipt #',f.target_id),f.created_at,'correction',CAST(f.target_id AS UNSIGNED) FROM finance_events f WHERE f.kind='rc_posted' AND f.target_type='payment' AND CAST(JSON_UNQUOTE(JSON_EXTRACT(f.payload,'$.cash_delta')) AS SIGNED)=0 AND CAST(JSON_UNQUOTE(JSON_EXTRACT(f.payload,'$.momo_delta')) AS SIGNED)=0";
        return implode(' UNION ALL ',$parts);
    }
    public static function paymentsSql(bool $finance):string{
        $base="SELECT p.id,p.booking_id,CONVERT(p.method USING utf8mb4) COLLATE utf8mb4_unicode_ci method,p.amount,CONVERT(p.note USING utf8mb4) COLLATE utf8mb4_unicode_ci note,p.created_at,'payment' source,p.id source_payment FROM payments p";
        return $finance?$base.' UNION ALL '.self::adjustmentsSql():$base;
    }
    public static function period(AccessRepository $r,string $start,string $end,?string $asOf=null):array{
        $values=[$start,$end];if($asOf!==null)$values[]=$asOf;$rows=self::installed($r)?$r->run("SELECT amount_minor,payload FROM finance_events WHERE kind='rc_posted' AND effective_date BETWEEN ? AND ?".($asOf!==null?' AND created_at<?':'').' ORDER BY id',$values)->fetchAll():[];
        $delta=0;$channels=['cash'=>0,'momo'=>0];
        foreach($rows as $e){$d=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);$delta+=(int)$e['amount_minor'];foreach($channels as $m=>$_)$channels[$m]+=(int)$d[$m.'_delta'];}
        return ['delta'=>$delta,'channels'=>$channels,'count'=>count($rows)];
    }
}
