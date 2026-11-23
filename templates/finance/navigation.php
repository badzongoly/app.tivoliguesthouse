<?php
$financeNavActor=(int)$user['id'];$financeNavPermissions=access_service()->repo->privileges($financeNavActor);$financeNavService=new \Tivoli\Service\FinanceService(access_service());$financeNavAdmin=$financeNavService->isAdmin($financeNavActor);
$financeNavExpense=(bool)array_intersect(['expenses.submit','expenses.manage','expenses.approve'],$financeNavPermissions);
$financeNavPage=basename($_SERVER['SCRIPT_NAME']??'')==='expenses.php'?'expenses':(is_string($_GET['page']??null)?$_GET['page']:'overview');
if(basename($_SERVER['SCRIPT_NAME']??'')==='financial-corrections.php')$financeNavPage='corrections';
if(basename($_SERVER['SCRIPT_NAME']??'')==='purchases.php'||$financeNavPage==='providers')$financeNavPage='purchases';
$financeNavItems=['overview'=>'Money Overview','purchases'=>'Purchase Requests','daily'=>'Daily Reconciliation','weekly'=>'Weekly Reconciliation','payments'=>'Payment Log','income'=>'Other Income','discrepancies'=>'Cash & MoMo Discrepancies','deposits'=>'Cash → MoMo Deposits','expenses'=>'Expenses','corrections'=>'Financial Corrections','revenue'=>'Room & Extras Revenue','profit'=>'Profit Review','activity'=>'Audit Trail'];
$financeNavCanPurchase=(bool)array_intersect(['bookings.manage','expenses.submit','expenses.manage','stock.manage'],$financeNavPermissions);
if(!$financeNavCanPurchase){
 unset($financeNavItems['purchases']);
 if($financeNavAdmin)$financeNavItems=['overview'=>'Money Overview','providers'=>'Legacy Provider Records']+$financeNavItems;
 if($financeNavPage==='purchases')$financeNavPage='providers';
}
?>
<nav class="app-section-nav finance-navigation" aria-label="Finance sections">
<?php foreach($financeNavItems as $financeNavKey=>$financeNavLabel):
 if(in_array($financeNavKey,['daily','payroll','revenue','profit','activity'],true)&&!$financeNavAdmin)continue;
 if($financeNavKey==='weekly'&&!$financeNavService->canPostLate($financeNavActor))continue;
 if($financeNavKey==='payments'&&!in_array('payments.view',$financeNavPermissions,true))continue;
 if($financeNavKey==='corrections'&&!in_array('bookings.manage',$financeNavPermissions,true)&&!$financeNavExpense)continue;
 if($financeNavKey==='payroll'&&!in_array('hr.salary',$financeNavPermissions,true))continue;
 if($financeNavKey==='expenses'&&!$financeNavExpense&&!$financeNavAdmin)continue;
 if(in_array($financeNavKey,['income','deposits'],true)&&!$financeNavAdmin&&!in_array('bookings.manage',$financeNavPermissions,true))continue;
 if($financeNavKey==='providers'&&!$financeNavAdmin&&!array_intersect(['bookings.manage','expenses.submit','expenses.manage'],$financeNavPermissions))continue;
 $financeNavPath=$financeNavKey==='corrections'?('financial-corrections.php'.(!in_array('bookings.manage',$financeNavPermissions,true)?'?area=expenses':'')):($financeNavKey==='payroll'?'payroll.php':($financeNavKey==='purchases'?'purchases.php':($financeNavKey==='expenses'&&$financeNavExpense?'expenses.php':'finance.php?page='.$financeNavKey)));
?><a href="<?=access_h(access_url($financeNavPath))?>" <?=$financeNavKey===$financeNavPage?'aria-current="page"':''?>><?=access_h($financeNavLabel)?></a>
<?php endforeach?>
</nav>
<?php if($financeNavPage==='expenses'&&$financeNavAdmin):?>
<nav class="app-section-nav expense-navigation" aria-label="Expense Sections">
<?php if($financeNavAdmin):?><a href="<?=access_h(access_url('finance.php?page=expenses'))?>" <?=basename($_SERVER['SCRIPT_NAME']??'')==='finance.php'?'aria-current="page"':''?>>Expense Payments</a><?php endif?>
</nav>
<?php endif?>
