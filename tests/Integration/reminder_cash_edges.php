<?php
require __DIR__.'/cash_discrepancies.php';
$bad=0;
function cashEdge(callable $f,string $label):void{global $bad,$n;try{$ok=(bool)$f();}catch(Throwable){$ok=false;}if(!$ok)$bad++;else$n++;echo ($ok?'PASS ':'FAIL ').$label."\n";}
function cashQueue(array $keys):\Tivoli\Service\ReminderService{global $a;return new \Tivoli\Service\ReminderService($a,'2044-07-21 14:00:00',access_config(),$keys);}
$staff=cu();$receipt=cp('cash',10000);$cashKey='cash:'.$receipt;$q=cashQueue([$cashKey]);$q->generate($owner,ck());$dep=$f->execute($staff,'deposit_record',['amount'=>'100.00','fee'=>'0.00','date'=>'2044-07-19','reference'=>'REMINDER-'.ck()],ck())['id'];$q=cashQueue([$cashKey,'deposit:'.$dep]);
cashEdge(function()use($q,$owner,$cashKey){$row=$q->sources->inspect($owner,$cashKey);return !$row['unavailable']&&!$row['resolved']&&!$row['actionable'];},'Dispatched cash reminder retires through factual handoff without claiming financial settlement');
$q->generate($owner,ck());$dc=cr('deposit',$dep)['id'];$q=cashQueue(['deposit:'.$dep,'discrepancy:'.$dc]);
cashEdge(function()use($q,$owner,$dep,$dc){$row=$q->sources->inspect($owner,'deposit:'.$dep);return !$row['unavailable']&&!$row['actionable']&&str_contains($row['path'],(string)$dc);},'Deposit reminder points to explicit discrepancy successor instead of false unavailability');
$m=cp('momo',10000);$key='momo:payment-'.$m;$q=cashQueue([$key]);
cashEdge(fn()=>$q->sources->inspect($owner,$key)['resolved'],'A recorded MoMo receipt requires no legacy matching reminder');
$mc=cr('payment',$m)['id'];$q=cashQueue([$key,'discrepancy:'.$mc]);
cashEdge(function()use($q,$owner,$key,$mc){$row=$q->sources->inspect($owner,$key);return !$row['unavailable']&&!$row['actionable']&&str_contains($row['path'],(string)$mc);},'Legacy wallet reminder links to an actual reported discrepancy');
$staff=cu();$recipient=cu();$p=cp('cash',10000);$h=new \Tivoli\Service\HandoverService($a,$clock);$sent=$h->execute($staff,'cash_send',['recipient_id'=>(string)$recipient,'amount'=>'40.00','passed'=>'1'],ck())['id'];$h->execute($recipient,'cash_ack',['id'=>(string)$sent,'version'=>$h->detail($recipient,$sent)['version'],'received'=>'1'],ck());$q=cashQueue(['cash:'.$p]);$q->generate($owner,ck());
cashEdge(fn()=>count($q->inbox($staff)['rows'])===1&&count($q->inbox($recipient)['rows'])===1,'Every positive current cash custodian receives their own portion reminder');
cashEdge(function()use($q,$owner){try{$q->sources->inspect($owner,'weekly:2147483647');}catch(RuntimeException){return true;}return false;},'A genuinely missing original source remains unavailable');
echo "Reminder cash edges: $n passed assertions including source setup; $bad failures\n";exit($bad?1:0);
