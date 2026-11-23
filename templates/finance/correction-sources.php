<?php
$correctionSourcePermissions=access_service()->repo->privileges((int)$actor);
$correctionSourceArea=is_string($_GET['area']??null)?$_GET['area']:'';
$correctionSourceItems=[];
if(in_array('bookings.manage',$correctionSourcePermissions,true))$correctionSourceItems+=[''=>'Guest Receipts','charges'=>'Guest Charges & Refunds'];
if(array_intersect(['expenses.submit','expenses.manage','expenses.approve'],$correctionSourcePermissions))$correctionSourceItems+=['expenses'=>'Expenses'];
if(array_intersect(['bookings.manage','expenses.submit','expenses.manage','stock.manage'],$correctionSourcePermissions))$correctionSourceItems+=['purchases'=>'Purchases & Providers'];
?>
<h2>Financial Corrections</h2><nav class="app-section-nav workspace-tab-strip" aria-label="Correction Sources">
<?php foreach($correctionSourceItems as $correctionSourceKey=>$correctionSourceLabel):?><a href="<?=access_h(access_url('financial-corrections.php'.($correctionSourceKey!==''?'?area='.$correctionSourceKey:'')))?>" <?=$correctionSourceKey===$correctionSourceArea?'aria-current="page"':''?>><?=access_h($correctionSourceLabel)?></a><?php endforeach?>
<?php if((new \Tivoli\Service\PayrollService(access_service()))->owner((int)$actor)):?><a href="<?=access_h(access_url('payroll.php?page=corrections'))?>">Payroll</a><?php endif?>
</nav>
