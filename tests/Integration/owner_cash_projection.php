<?php
require __DIR__.'/cash_discrepancies.php';
$queue=new \Tivoli\Service\OwnerExceptionService($a,$clock);
$staff=cu();$fresh=cp('momo',10000);$case=cr('payment',$fresh)['id'];$s->execute($owner,'credit',ci($case,['amount'=>'80.00']),ck());
$view=$queue->read($owner,['key'=>'discrepancy:'.$case]);ca($view['selected']['amount_minor']===2000&&!$view['selected']['resolved'],'Partial wallet credit leaves exact twenty cedis open in same linked case');
ca(!isset(array_column($view['rows'],null,'key')['momo:payment-'.$fresh]),'Explicit discrepancy replaces duplicate standalone wallet row');
$s->execute($owner,'credit',ci($case,['amount'=>'20.00']),ck());$view=$queue->read($owner,['key'=>'discrepancy:'.$case]);ca($view['selected']['resolved']&&$view['selected']['amount_minor']===0,'Full source verification resolves queue without a new money entry');
$s->execute($staff,'reopen',ci($case,['reason'=>'Synthetic later evidence requires original case review']),ck());$view=$queue->read($owner,['key'=>'discrepancy:'.$case]);ca(!$view['selected']['resolved']&&$view['selected']['amount_minor']===0&&$view['selected']['facts']['Verified Amount']==='GHS 100.00','Reopened original case preserves verified amount and requires owner review');
$cashReceipt=cp('cash',2500);$dep=$f->execute($staff,'deposit_record',['amount'=>'25.00','fee'=>'0.00','date'=>'2044-07-19','reference'=>'QUEUE-'.ck()],ck())['id'];$dc=cr('deposit',$dep)['id'];$s->execute($owner,'credit',ci($dc,['amount'=>'20.00']),ck());$view=$queue->read($owner,['key'=>'discrepancy:'.$dc]);ca($view['selected']['amount_minor']===500,'Deposit short credit retains exact five cedis unexplained');
$cash=array_values(array_filter($f->cash()['rows'],fn($v)=>(string)$v['id']===(string)$cashReceipt))[0];ca($cash['minor']===2500&&$cash['outstanding']===500,'Full gross cash-deposit obligation survives partial verification');
echo "Owner cash projection: $n assertions passed\n";
