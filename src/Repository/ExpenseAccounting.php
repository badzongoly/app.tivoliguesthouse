<?php
namespace Tivoli\Repository;
use Tivoli\Domain\Stay\StayRules as R;
use RuntimeException;

/** Append-only ordinary expense positions and posting-date cost/classification legs. */
final class ExpenseAccounting
{
    public static function installed(AccessRepository $r):bool{return ReceiptAccounting::installed($r);}
    public static function position(AccessRepository $r,int $id):array{
        $e=$r->run('SELECT e.*,c.name category_name,u.name actor_name FROM expenses e JOIN expense_categories c ON c.id=e.category_id LEFT JOIN users u ON u.id=e.user_id WHERE e.id=?',[$id])->fetch();
        if(!$e)throw new RuntimeException('Original expense not found.');
        $p=['amount'=>R::minor((string)$e['amount']),'paid'=>null,'category_id'=>(int)$e['category_id'],'method'=>$e['method'],'reference'=>'','last_id'=>0];
        $settlements=[];
        if(ReceiptAccounting::installed($r)){
            $settlements=$r->run("SELECT * FROM finance_events WHERE kind='cost_paid' AND target_type='expense' AND target_id=? ORDER BY id",[(string)$id])->fetchAll();
            if(count($settlements)===1){$d=json_decode($settlements[0]['payload'],true,512,JSON_THROW_ON_ERROR);$p['paid']=(int)$settlements[0]['amount_minor'];$p['reference']=$d['reference']??'';}
            $f=$r->run("SELECT id,payload FROM finance_events WHERE kind='ec_posted' AND target_type='expense' AND target_id=? ORDER BY id DESC LIMIT 1",[(string)$id])->fetch();
            if($f){$p=json_decode($f['payload'],true,512,JSON_THROW_ON_ERROR)['after']+['last_id'=>(int)$f['id']];if($p['paid']===null&&count($settlements)===1){$p['paid']=(int)$settlements[0]['amount_minor'];$d=json_decode($settlements[0]['payload'],true,512,JSON_THROW_ON_ERROR);$p['reference']=$d['reference']??'';}}
        }
        $purchase=ReceiptAccounting::installed($r)?\Tivoli\Service\PurchaseService::forExpense($r,$id):0;if($purchase){$changes=PurchaseAccounting::entries($r,'purchase',$purchase,$id);$p['amount']=R::minor((string)$e['amount']);$p['paid']=array_sum(array_map(static fn($row)=>(int)$row['amount_minor'],$settlements));foreach($changes as $change){$p['amount']+=(int)$change['data']['cost_delta'];$p['paid']+=(int)$change['data']['paid_delta'];$p['last_id']=(int)$change['id'];}}
        return ['original'=>$e,'position'=>$p,'settlements'=>$settlements];
    }
    public static function collection(AccessRepository $r,int $id):int{
        $seen=[];if(!ReceiptAccounting::installed($r))return $id;
        while(true){if(isset($seen[$id]))throw new RuntimeException('Expense keeper links need reconciliation.');$seen[$id]=true;$p=$r->run("SELECT payload FROM finance_events WHERE kind='ec_posted' AND target_type='expense' AND target_id=? AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.type'))='duplicate' ORDER BY id DESC LIMIT 1",[(string)$id])->fetchColumn();if(!$p)return $id;$id=(int)json_decode($p,true,512,JSON_THROW_ON_ERROR)['keeper_id'];if($id<1)throw new RuntimeException('Retained expense unavailable.');}
    }
    public static function aliasOwner(AccessRepository $r,string $reference):?int{
        if(trim($reference)===''||!ReceiptAccounting::installed($r))return null;
        $p=$r->run("SELECT f.payload FROM finance_claims c JOIN finance_events f ON f.id=c.event_id WHERE c.scope='expense_alias' AND c.identity_hash=?",[hash('sha256',mb_strtolower(trim($reference),'UTF-8'))])->fetchColumn();
        return $p?self::collection($r,(int)json_decode($p,true,512,JSON_THROW_ON_ERROR)['alias_owner_expense_id']):null;
    }
    public static function guardAlias(AccessRepository $r,string $reference,?int $expense=null):void{
        $owner=self::aliasOwner($r,$reference);if($owner!==null&&($expense===null||$owner!==self::collection($r,$expense)))throw new RuntimeException('This reference is retained by an existing corrected expense payout.');
    }
    public static function costSql(bool $finance,?string $asOf=null):string{
        $cutoff='';if($asOf!==null){$dt=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$asOf);if(!$dt||$dt->format('Y-m-d H:i:s')!==$asOf)throw new RuntimeException('Invalid report cutoff.');$cutoff=' AND f.created_at<CAST(0x'.bin2hex($asOf).' AS DATETIME)';}
        $sql="SELECT e.id,e.expense_date,e.category_id,CONVERT(e.method USING utf8mb4) COLLATE utf8mb4_unicode_ci method,e.amount,CONVERT(e.vendor USING utf8mb4) COLLATE utf8mb4_unicode_ci vendor,CONVERT(e.reference_no USING utf8mb4) COLLATE utf8mb4_unicode_ci reference_no,CONVERT(e.description USING utf8mb4) COLLATE utf8mb4_unicode_ci description,e.user_id,'expense' source,e.id source_expense FROM expenses e";
        if(!$finance)return $sql;
        foreach(['before'=>-1,'after'=>1] as $key=>$sign){
            $j="JSON_UNQUOTE(JSON_EXTRACT(f.payload,'$.".$key; $sql.=" UNION ALL SELECT f.id,f.effective_date,CAST(".$j.".category_id')) AS UNSIGNED),CONVERT(".$j.".method')) USING utf8mb4) COLLATE utf8mb4_unicode_ci,CAST(".$sign."*CAST(".$j.".amount')) AS SIGNED)/100 AS DECIMAL(14,2)),CONVERT(COALESCE(base_expense.vendor,'Original Expense Unavailable') USING utf8mb4) COLLATE utf8mb4_unicode_ci,CONVERT(COALESCE(base_expense.reference_no,'') USING utf8mb4) COLLATE utf8mb4_unicode_ci,CONVERT(CONCAT(COALESCE(base_expense.description,''),' / Expense adjustment #',f.id,' / original expense #',f.target_id) USING utf8mb4) COLLATE utf8mb4_unicode_ci,f.actor_id,'expense_adjustment',CAST(f.target_id AS UNSIGNED) FROM finance_events f LEFT JOIN expenses base_expense ON base_expense.id=CAST(f.target_id AS UNSIGNED) WHERE f.kind='ec_posted' AND f.target_type='expense'".$cutoff;
        }
        return $sql.' UNION ALL '.PurchaseAccounting::expenseDeltaSql($asOf);
    }
    public static function period(AccessRepository $r,string $start,string $end,?string $asOf=null):array{
        $out=['cost'=>0,'paid'=>0,'cash'=>0,'momo'=>0,'count'=>0];if(!ReceiptAccounting::installed($r))return $out;
        $values=[$start,$end];if($asOf!==null)$values[]=$asOf;foreach($r->run("SELECT payload FROM finance_events WHERE kind='ec_posted' AND target_type='expense' AND effective_date BETWEEN ? AND ?".($asOf!==null?' AND created_at<?':''),$values)->fetchAll() as $e){$p=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);foreach(['cost','paid','cash','momo'] as $key)$out[$key]+=(int)$p[$key.'_delta'];$out['count']++;}return $out+['profit'=>-$out['cost']];
    }
}
