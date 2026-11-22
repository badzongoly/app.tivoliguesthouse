<?php
namespace Tivoli\Repository;
final class PayrollAccounting
{
    public static function period(AccessRepository $r,string $start,string $end,?string $asOf=null):array{
        $out=['cost'=>0,'paid'=>0,'count'=>0];if(!ReceiptAccounting::installed($r))return $out;$values=[$start,$end];if($asOf!==null)$values[]=$asOf;
        foreach($r->run("SELECT amount_minor,payload FROM finance_events WHERE kind='payroll_adjustment' AND target_type='request' AND effective_date BETWEEN ? AND ?".($asOf!==null?' AND created_at<?':''),$values)->fetchAll() as $row){$d=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);$out['cost']+=(int)$row['amount_minor'];$out['paid']+=(int)$d['paid_delta'];$out['count']++;}return $out;
    }
}
