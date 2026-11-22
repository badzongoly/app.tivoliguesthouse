<?php
namespace Tivoli\Repository;
use Tivoli\Domain\Stay\StayRules as R;

/** Current linked cost, specific payout and stock positions; originals remain immutable. */
final class PurchaseAccounting
{
    public static function entries(AccessRepository $r,string $type,int $source,?int $expense=null):array{
        if(!ReceiptAccounting::installed($r))return [];if($type==='purchase'&&$expense===null){$posted=$r->run("SELECT payload FROM finance_events WHERE target_type='purchase' AND target_id=? AND kind='purchase_posted' ORDER BY id DESC LIMIT 1",[(string)$source])->fetchColumn();if(!$posted)return [];$expense=(int)json_decode($posted,true,512,JSON_THROW_ON_ERROR)['expense_id'];}$rows=$r->run("SELECT * FROM finance_events WHERE target_type=? AND target_id=? AND kind='pc_posted' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.source_type'))=? AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.source_id'))=? ORDER BY id",[$type==='purchase'?'expense':'request',(string)($type==='purchase'?$expense:$source),$type,(string)$source])->fetchAll();foreach($rows as &$e)$e['data']=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);return $rows;
    }
    public static function paymentRows(AccessRepository $r,string $type,int $source,?int $expense=null):array{return $r->run("SELECT * FROM finance_events WHERE kind='cost_paid' AND target_type=? AND target_id=? ORDER BY id",[$type==='purchase'?'expense':'request',(string)($type==='purchase'?$expense:$source)])->fetchAll();}
    public static function payoutBlockers(array $markers,array $rows,int $source):array{
        $map=[];foreach($markers as $m)$map[(int)$m['id']]=$m['data'];$seen=[];
        foreach($rows as $row){$d=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);$id=(int)($d['purchase_payment_id']??0);$m=$map[$id]??null;if(!$m||isset($seen[$id])||(int)($m['expense_id']??0)!==(int)$row['target_id']||(int)($d['purchase_id']??0)!==$source||(int)$row['amount_minor']!==(int)$m['amount']||($d['method']??'')!==$m['method']||($d['reference']??'')!==$m['reference']||(int)($d['paid_by']??0)!==(int)$m['payer_id']||($d['actual_paid_at']??'')!==$m['paid_at'])return ['Original purchase payout and cost-payment mirrors need reconciliation.'];$seen[$id]=true;}
        return count($seen)===count($map)?[]:['Original purchase payout mirror is missing; do not treat it as unpaid.'];
    }
    public static function position(AccessRepository $r,string $type,int $source,int $cost,array $payments,int $quantity=0,?int $expense=null):array{
        return self::fold($cost,$payments,$quantity,self::entries($r,$type,$source,$expense));
    }
    /** Same exact source calculation, reusable by batched read-only owner views. */
    public static function fold(int $cost,array $payments,int $quantity,array $entries):array{
        $received=$quantity;$returned=0;$map=[];foreach($payments as $p){$p['data']=json_decode($p['payload'],true,512,JSON_THROW_ON_ERROR);$p['original_amount']=(int)$p['amount_minor'];$p['amount_minor']=(int)$p['amount_minor'];$map[(int)$p['id']]=$p;}
        foreach($entries as $e){$d=$e['data'];$cost+=(int)$d['cost_delta'];$received+=(int)$d['received_delta'];$returned+=(int)$d['return_qty'];if($d['payment_id']){if(!isset($map[(int)$d['payment_id']]))throw new \RuntimeException('Linked payment history needs reconciliation.');$map[(int)$d['payment_id']]['amount_minor']+=(int)$d['paid_delta'];}}
        $paid=0;foreach($map as $p){if($p['amount_minor']<0)throw new \RuntimeException('Corrected payout cannot be negative.');$paid+=$p['amount_minor'];}if($cost<0||$received<$returned||$returned<0)throw new \RuntimeException('Corrected purchase positions do not reconcile.');return ['cost'=>$cost,'paid'=>$paid,'payable'=>max(0,$cost-$paid),'supplier_due'=>max(0,$paid-$cost),'received_qty'=>$received,'returned_qty'=>$returned,'net_qty'=>$received-$returned,'payments'=>array_values($map),'corrections'=>$entries];
        }
    public static function period(AccessRepository $r,string $start,string $end,?string $asOf=null):array{
        $out=['cost'=>0,'expense_cost'=>0,'provider_cost'=>0,'paid'=>0,'cash'=>0,'momo'=>0,'count'=>0];if(!ReceiptAccounting::installed($r))return $out;$values=[$start,$end];if($asOf!==null)$values[]=$asOf;foreach($r->run("SELECT payload FROM finance_events WHERE kind='pc_posted' AND effective_date BETWEEN ? AND ?".($asOf!==null?' AND created_at<?':''),$values)->fetchAll() as $e){$d=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);$out['cost']+=(int)$d['cost_delta'];$out[$d['source_type']==='purchase'?'expense_cost':'provider_cost']+=(int)$d['cost_delta'];$out['paid']+=(int)$d['paid_delta'];if($d['paid_delta'])$out[$d['payment_method']]+=(int)$d['paid_delta'];$out['count']++;}return $out;
    }
    public static function expenseDeltaSql(?string $asOf=null):string{
        $cutoff='';if($asOf!==null){$dt=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$asOf);if(!$dt||$dt->format('Y-m-d H:i:s')!==$asOf)throw new \RuntimeException('Invalid cutoff.');$cutoff=' AND f.created_at<CAST(0x'.bin2hex($asOf).' AS DATETIME)';}
        return "SELECT f.id,f.effective_date,e.category_id,CONVERT(e.method USING utf8mb4) COLLATE utf8mb4_unicode_ci,CAST(f.amount_minor/100 AS DECIMAL(14,2)),CONVERT(e.vendor USING utf8mb4) COLLATE utf8mb4_unicode_ci,CONVERT(e.reference_no USING utf8mb4) COLLATE utf8mb4_unicode_ci,CONVERT(CONCAT(e.description,' / Purchase adjustment #',f.id,' / original expense #',e.id) USING utf8mb4) COLLATE utf8mb4_unicode_ci,f.actor_id,'purchase_adjustment',e.id FROM finance_events f JOIN expenses e ON e.id=CAST(JSON_UNQUOTE(JSON_EXTRACT(f.payload,'$.expense_id')) AS UNSIGNED) WHERE f.kind='pc_posted' AND JSON_UNQUOTE(JSON_EXTRACT(f.payload,'$.source_type'))='purchase' AND f.amount_minor<>0".$cutoff;
    }
}
