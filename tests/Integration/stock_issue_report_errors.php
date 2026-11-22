<?php
require __DIR__.'/stock_issues.php';
$x=sx('24.00');$id=report($x,['qty'=>'20']);$original=$si->detail($owner,$id)['root'];
ic($owner,'correct_report',iv($id)+['qty'=>'2','reason'=>'Verified only two damaged bottles; eighteen extra bottles are safe and saleable','verified'=>'1']);
sa(\Tivoli\Repository\StockAvailability::hold($r,$x)['held']===200&&qty($x)==='24.00','verified mistaken report releases only evidenced excess without a fake outflow');sa($si->detail($owner,$id)['qty']===200&&$si->detail($owner,$id)['root']===$original,'corrected quantity is derived while original report is retained');
sd(fn()=>ic($staff,'correct_report',iv($id)+['qty'=>'0','reason'=>'No authority','verified'=>'1']),'clerk cannot release a report hold');
sd(fn()=>ic($owner,'correct_report',iv($id)+['qty'=>'3','reason'=>'Increase','verified'=>'1']),'hold correction cannot silently increase scope');
sd(fn()=>ic($owner,'correct_report',iv($id)+['qty'=>'0','reason'=>'Unchecked','verified'=>'0']),'physical safety verification required for release');
accept($id);dispose($id);sa(qty($x)==='22.00','later disposal uses only effective corrected quantity');
$id=report($x);$in=iv($id)+['qty'=>'0','reason'=>'Physically verified a duplicate entry, every item is safe and saleable','verified'=>'1'];$key=sk();ic($owner,'correct_report',$in,$key);ic($owner,'correct_report',$in,$key);sa($si->detail($owner,$id)['status']==='cancelled'&&\Tivoli\Repository\StockAvailability::hold($r,$x)['held']===0&&qty($x)==='22.00','verified zero correction cancels a mistaken hold once without deleting history');
echo "TOTAL $n report-error assertions passed.\n";
