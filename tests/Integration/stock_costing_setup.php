<?php
require __DIR__.'/stock_issue_helpers.php';
use Tivoli\Service\InventoryCostingService as S;
if(!class_exists(S::class))throw new RuntimeException('FAIL approved stock costing service is missing');
$clock='2036-08-05 14:00:00';$svc=new \Tivoli\Service\StockCountService($a,$clock);$cost=new S($a,$clock);$x=sx('20.00');$count=start($staff,[$x]);countLine($staff,$count,$x,'20');send($staff,$count);approve($count);
$before=$r->run('SELECT * FROM extras WHERE id=?',[$x])->fetch();$movements=$r->run('SELECT * FROM stock_movements WHERE extra_id=?',[$x])->fetchAll();
$in=['extra_id'=>(string)$x,'count_id'=>(string)$count,'total'=>'40.00','evidence'=>'Synthetic verified opening receipt','verified'=>'1'];$key=sk();
sd(fn()=>$cost->execute($staff,'start',$in,sk()),'clerk cannot set cost policy');sd(fn()=>$cost->execute($admin,'start',$in,sk()),'other administrator cannot set owner cost policy');
$result=$cost->execute($owner,'start',$in,$key);sa($cost->execute($owner,'start',$in,$key)['event_id']===$result['event_id'],'opening retry uses durable operation result');
sd(fn()=>$cost->execute($owner,'start',$in,sk()),'one opening valuation per item');$v=$cost->item($owner,$x);sa($v['state']['value']===4000&&$v['state']['qty']===2000,'supported count establishes exact opening pool');
sa($r->run('SELECT * FROM extras WHERE id=?',[$x])->fetch()===$before&&$r->run('SELECT * FROM stock_movements WHERE extra_id=?',[$x])->fetchAll()===$movements,'opening valuation does not change stock or create a receipt');
sd(fn()=>$cost->item($staff,$x),'cost details restricted to configured owner');
$y=sx('8.00');$c=start($staff,[$y]);countLine($staff,$c,$y,'8');send($staff,$c);approve($c);$cost->execute($owner,'start',['extra_id'=>(string)$y,'count_id'=>(string)$c,'total'=>'','evidence'=>'Opening source cost not available','verified'=>'1'],sk());sa($cost->item($owner,$y)['state']['value']===null,'unknown opening is explicit');
$v=$cost->item($owner,$y);$in=['extra_id'=>(string)$y,'version'=>$v['version'],'total'=>'24.00','evidence'=>'Synthetic original receipt independently reviewed','verified'=>'1'];$key=sk();$first=$cost->execute($owner,'evidence',$in,$key);sa($cost->execute($owner,'evidence',$in,$key)['event_id']===$first['event_id'],'later evidence replay is idempotent');sa($cost->item($owner,$y)['state']['value']===2400,'later evidence values existing stock without adding units');
$z=sx('4');$c=start($staff,[$z]);countLine($staff,$c,$z,'4');send($staff,$c);approve($c);move($z,'-1');sd(fn()=>$cost->execute($owner,'start',['extra_id'=>(string)$z,'count_id'=>(string)$c,'total'=>'4','evidence'=>'stale','verified'=>'1'],sk()),'movement after approved count requires a fresh opening count');
echo "TOTAL $n stock costing setup assertions passed.\n";
