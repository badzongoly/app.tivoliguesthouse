<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/security.php';
use Tivoli\Repository\PurchaseAccounting;
$n=0;function opa(bool $yes,string $label):void{global $n;if(!$yes)throw new RuntimeException($label);$n++;echo "PASS $label\n";}
$payment=['id'=>71,'amount_minor'=>'20000','payload'=>'{"reference":"Synthetic"}'];
$entry=['data'=>['cost_delta'=>-5000,'received_delta'=>0,'return_qty'=>100,'payment_id'=>0,'paid_delta'=>0]];
$p=PurchaseAccounting::fold(35000,[$payment],500,[$entry]);opa($p['cost']===30000&&$p['paid']===20000&&$p['payable']===10000,'Partial purchase settlement retains exact remaining payable');opa($p['received_qty']===500&&$p['returned_qty']===100&&$p['net_qty']===400,'Source-linked return changes net quantity once');
$p=PurchaseAccounting::fold(10000,[$payment],0,[]);opa($p['supplier_due']===10000&&$p['payable']===0,'Real provider overpayment remains recovery due');
try{PurchaseAccounting::fold(10000,[],0,[['data'=>['cost_delta'=>0,'received_delta'=>0,'return_qty'=>0,'payment_id'=>71,'paid_delta'=>-100]]]);throw new LogicException('Expected missing payment denial');}catch(RuntimeException $e){opa(true,'Missing linked payment cannot silently become a settled source');}
echo "Owner purchase projection: $n assertions passed\n";
