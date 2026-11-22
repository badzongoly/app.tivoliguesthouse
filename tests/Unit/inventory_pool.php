<?php
require dirname(__DIR__,2).'/bootstrap/security.php';
use Tivoli\Domain\Finance\InventoryPool as P;
$n=0;
function eq(mixed $actual,mixed $expected,string $label):void{global $n;if($actual!==$expected)throw new RuntimeException($label.' expected '.json_encode($expected).' got '.json_encode($actual));$n++;echo "PASS $label\n";}
if(!class_exists(P::class))throw new RuntimeException('FAIL approved exact stock-cost pool is missing');
$p=new P(2000,4000);$p->receive(1000,3000);eq($p->consume(600,'sales'),1400,'weighted cost of six sales');eq($p->consume(200,'loss','damage1'),467,'verified loss recognized before disposal');eq($p->consume(100,'complimentary'),233,'actual complimentary stock consumed');$p->dispose('damage1',200);eq($p->snapshot(),['qty'=>2100,'value'=>4900,'written_off'=>0,'costs'=>['sales'=>1400,'loss'=>467,'complimentary'=>233,'returns'=>0],'unknown'=>0],'stock and value reconcile after physical disposal');
$q=new P(800,null);eq($q->consume(200,'sales'),null,'unknown cost stays unknown');eq($q->snapshot()['unknown'],200,'unknown quantity disclosed');eq($q->snapshot()['value'],null,'unknown remaining value not invented');
$q=new P(800,2400);eq($q->consume(200,'sales'),600,'later opening evidence allocates actual consumed cost');eq($q->snapshot()['value'],1800,'later evidence allocates remaining stock');
$p=new P(2000,4000);$p->receive(1000,2800);$p->consume(600,'sales');$p->consume(200,'loss','damage1');$p->consume(100,'complimentary');$p->dispose('damage1',200);eq(array_sum($p->snapshot()['costs']),2040,'source cost correction splits total consumed delta');eq($p->snapshot()['value'],4760,'source correction leaves correct inventory value');
$p=new P(1000,5000);$p->consume(200,'loss','a');$p->restore('a');eq($p->snapshot()['value'],5000,'revoked damage approval restores carrying value');eq($p->snapshot()['costs']['loss'],0,'revoked loss posts exact offset');$p->consume(100,'loss','b');$p->dispose('b',100);eq($p->snapshot()['value'],4500,'reapproved reduced loss expensed once');
$p=new P(1000,5000);$p->returned(200,800);eq($p->snapshot()['costs']['returns'],200,'supplier return short credit recognizes carrying difference');eq($p->snapshot()['value'],4000,'supplier return removes carrying value once');
$p=new P(0,0);$p->receive(999999999999,999999999999);eq($p->consume(999999999998,'sales'),999999999998,'large exact multiplication avoids integer overflow');eq($p->snapshot()['value'],1,'large pool conserves final cent');
foreach([[1,1,2],[5,1,2],[7,2,3],[999999999999,500000000001,999999999999]] as [$a,$b,$d])eq(P::share($a,$b,$d),match([$a,$b,$d]){[1,1,2]=>1,[5,1,2]=>3,[7,2,3]=>5,default=>500000000001},'exact proportional rounding '.$a);
try{(new P(100,100))->consume(101,'sales');throw new LogicException('expected insufficient pool');}catch(RuntimeException $e){eq(true,true,'unsupported source quantity cannot fabricate stock');}
echo "TOTAL $n stock costing pool assertions passed.\n";
