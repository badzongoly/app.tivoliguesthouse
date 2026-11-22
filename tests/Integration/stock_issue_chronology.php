<?php
require __DIR__.'/stock_issues.php';
function itime(string $time):void{global $clock,$si,$svc,$a;$clock='2036-08-05 '.$time;$si=new \Tivoli\Service\StockIssueService($a,$clock);$svc=new \Tivoli\Service\StockCountService($a,$clock);}
function oldCount(int $x):int{global $staff;itime('10:00:00');$count=start($staff,[$x]);countLine($staff,$count,$x,'8.00');send($staff,$count);itime('12:00:00');approve($count);itime('14:00:00');return $count;}
$x=sx('10.00');$count=oldCount($x);
if(($argv[1]??'')!=='count-time')sd(fn()=>report($x,['kind'=>'missing','position'=>'gone','actual_at'=>'2036-08-05T09:00:00']),'late unlinked loss cannot deduct an approved-count shortage again');
sd(fn()=>report($x,['kind'=>'missing','position'=>'counted','count_id'=>(string)$count,'actual_at'=>'2036-08-05T11:00:00']),'a new loss after physical counting cannot reuse that old count');
$linked=report($x,['kind'=>'missing','position'=>'counted','count_id'=>(string)$count,'actual_at'=>'2036-08-05T09:00:00']);accept($linked);dispose($linked);sa(qty($x)==='8.00','evidenced pre-count loss can be disposed without a second deduction');
$x=sx('10.00');itime('09:00:00');$issue=report($x);itime('10:00:00');$count=start($staff,[$x]);countLine($staff,$count,$x,'8.00');send($staff,$count);itime('12:00:00');approve($count);itime('14:00:00');accept($issue);dispose($issue);sa(qty($x)==='8.00','a later clean count explicitly excluding the existing hold permits actual disposal');
echo "TOTAL $n chronology assertions passed.\n";
