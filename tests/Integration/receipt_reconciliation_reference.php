<?php
require_once __DIR__.'/historical_momo_fixture.php';
define('RECONCILIATION_HELPERS_ONLY',true);require __DIR__.'/receipt_reconciliation.php';
$p=ap('500.00','momo');$proof='REAL-MERCHANT-'.ak();historicalMoMoMatch($r,$owner,['id'=>(string)$p,'type'=>'payment','amount'=>'500.00','reference'=>$proof,'evidence'=>'Synthetic actual merchant proof','verified'=>'1'],$clock);
an(fn()=>ar($p,['type'=>'method','method'=>'momo','reference'=>'UNPROVEN-'.ak()]),'reference correction cannot label unrelated reference as already verified');
$case=ar($p,['type'=>'method','method'=>'momo','reference'=>$proof])['id'];apost($case);aa(\Tivoli\Repository\ReceiptAccounting::position($r,$p)['position']['reference']===$proof,'reference may be corrected to its own real verified proof');
echo "TOTAL $n reconciliation reference assertions passed.\n";
