<?php
namespace Tivoli\Domain\Finance;
use RuntimeException;

/** Read-only projection of immutable confirmations and their bounded resolutions. */
final class CashResolution
{
    public static function index(array $events):array{$out=[];foreach($events as $e)if(in_array($e['kind'],['deposit_confirmed','receipt_matched','cash_transfer_acknowledged','cd_credit','cd_acknowledged','cd_reversed','cd_cash_returned','ra_wallet_in','ra_wallet_out'],true))$out[$e['target_type']][(string)$e['target_id']][]=$e;return $out;}
    /** Expressions are internal SQL identifiers; cutoff is a PDO-quoted validated report timestamp. */
    public static function verifiedSql(string $type,string $id,string $amount,string $cutoff):string{
        return "(COALESCE((SELECT MAX(vr.amount_minor) FROM finance_events vr WHERE vr.kind IN ('receipt_matched','deposit_confirmed') AND vr.target_type=$type AND vr.target_id=CONVERT($id USING utf8mb4) COLLATE utf8mb4_unicode_ci AND vr.created_at<$cutoff),0) + COALESCE((SELECT SUM(CASE WHEN va.kind IN ('cd_credit','ra_wallet_in') THEN va.amount_minor WHEN va.kind IN ('cd_reversed','ra_wallet_out') THEN -va.amount_minor ELSE 0 END) FROM finance_events va WHERE va.kind IN ('cd_credit','cd_reversed','ra_wallet_in','ra_wallet_out') AND va.target_type=$type AND va.target_id=CONVERT($id USING utf8mb4) COLLATE utf8mb4_unicode_ci AND va.created_at<$cutoff),0))";
    }
    public static function returnedSql(string $id,string $cutoff):string{return "COALESCE((SELECT SUM(cr.amount_minor) FROM finance_events cr WHERE cr.kind='cd_cash_returned' AND cr.target_type='deposit' AND cr.target_id=CONVERT($id USING utf8mb4) COLLATE utf8mb4_unicode_ci AND cr.created_at<$cutoff),0)";}
    public static function pick(array $limits,int $amount):array{
        $out=[];foreach($limits as $source=>$limit){$n=min($amount,(int)$limit);if($n>0){$out[(string)$source]=$n;$amount-=$n;}if(!$amount)break;}
        if($amount!==0)throw new RuntimeException('Source allocations no longer support this amount.');return $out;
    }
    public static function position(string $type,int $id,int $amount,array $events,array $allocations=[]):array{
        $verified=0;$returned=0;$credits=[];$returns=[];$touched=false;
        foreach($events as $e){if($e['target_type']!==$type||(int)$e['target_id']!==$id)continue;
            if(($type==='deposit'&&$e['kind']==='deposit_confirmed')||(in_array($type,['payment','income'],true)&&$e['kind']==='receipt_matched')||($type==='handover'&&$e['kind']==='cash_transfer_acknowledged')){$verified=(int)$e['amount_minor'];$credits=$allocations;}
            if(!str_starts_with($e['kind'],'cd_')&&!in_array($e['kind'],['ra_wallet_in','ra_wallet_out'],true))continue;
            $n=(int)$e['amount_minor'];$d=$e['data'];
            if(in_array($e['kind'],['cd_credit','cd_acknowledged','ra_wallet_in'],true)){$verified+=$n;foreach($d['allocations']??[] as $source=>$part)$credits[$source]=($credits[$source]??0)+(int)$part;$touched=true;}
            if(in_array($e['kind'],['cd_reversed','ra_wallet_out'],true)){$verified-=$n;foreach($d['allocations']??[] as $source=>$part)$credits[$source]=($credits[$source]??0)-(int)$part;$touched=true;}
            if($e['kind']==='cd_cash_returned'){$returned+=$n;foreach($d['allocations']??[] as $source=>$part)$returns[$source]=($returns[$source]??0)+(int)$part;$touched=true;}

        }
        if($verified<0||$returned<0||$verified+$returned>$amount)throw new RuntimeException('Resolution history does not reconcile. Contact the owner.');
        $remaining=$amount-$verified-$returned;$available=[];
        foreach($allocations as $source=>$part){$available[$source]=(int)$part-($credits[$source]??0)-($returns[$source]??0);if($available[$source]<0||($credits[$source]??0)<0)throw new RuntimeException('Resolution allocations do not reconcile.');}
        return compact('verified','returned','remaining','credits','returns','available','touched');
    }
}
