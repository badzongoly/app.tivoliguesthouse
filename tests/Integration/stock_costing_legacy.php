<?php
require __DIR__.'/stock_costing_purchases.php';
// Read-only projection of a synthetic legacy negative balance; always roll this fixture back.
$r->db->beginTransaction();try{$legacy=sx('-1');$v=$cost->item($owner,$legacy);sa(!$v['opening']&&!$v['supported'],'legacy negative quantity remains readable as unsupported, not a broken profit page');$b=$cost->period($owner,'2036-08-01','2036-08-31',0);sa(!$b['complete']&&$b['unstarted']>0,'negative legacy balance cannot create complete stock profit');}finally{$r->db->rollBack();}
$x=openingCost('0','0');$purchase=costPurchase($x,'10','30','2036-08-04 12:00:00');sa(cv($x)['error']!==null&&cv($x)['value']===null,'receipt physically predating opening count requires source reconciliation instead of duplicate supported opening value');
echo "TOTAL $n stock costing legacy-boundary assertions passed.\n";
