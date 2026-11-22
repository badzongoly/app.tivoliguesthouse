<?php
$financeNavActor=(int)$user['id'];$financeNavPermissions=access_service()->repo->privileges($financeNavActor);$financeNavService=new \Tivoli\Service\FinanceService(access_service());$financeNavAdmin=$financeNavService->isAdmin($financeNavActor);
$financeNavExpense=(bool)array_intersect(['expenses.submit','expenses.manage','expenses.approve'],$financeNavPermissions);
$financeNavPage=basename($_SERVER['SCRIPT_NAME']??'')==='expenses.php'?'expenses':(is_string($_GET['page']??null)?$_GET['page']:'overview');
if(basename($_SERVER['SCRIPT_NAME']??'')==='financial-corrections.php')$financeNavPage='corrections';
if(basename($_SERVER['SCRIPT_NAME']??'')==='purchases.php')$financeNavPage='purchases';
$financeNavItems=['purchases'=>'Purchase Requests','overview'=>'Money Overview','daily'=>'Daily Reconciliation','weekly'=>'Weekly Reconciliation','payments'=>'Payment Log','income'=>'Other Income','discrepancies'=>'Cash & MoMo Discrepancies','deposits'=>'Cash → MoMo Deposits','expenses'=>'Expenses','corrections'=>'Financial Corrections','payroll'=>'Payroll & Staff Cost','providers'=>'Provider Payments','revenue'=>'Room & Extras Revenue','profit'=>'Profit Review','activity'=>'Audit Trail'];
?>
<nav class="app-section-nav finance-navigation" aria-label="Finance sections">
<?php foreach($financeNavItems as $key=>$label):
 if(in_array($key,['daily','payroll','revenue','profit','activity'],true)&&!$financeNavAdmin)continue;
 if($key==='weekly'&&!$financeNavService->canPostLate($financeNavActor))continue;
 if($key==='payments'&&!in_array('payments.view',$financeNavPermissions,true))continue;
 if($key==='corrections'&&!in_array('bookings.manage',$financeNavPermissions,true)&&!$financeNavExpense)continue;
 if($key==='payroll'&&!in_array('hr.salary',$financeNavPermissions,true))continue;
 if($key==='expenses'&&!$financeNavExpense&&!$financeNavAdmin)continue;
 if(in_array($key,['income','deposits'],true)&&!$financeNavAdmin&&!in_array('bookings.manage',$financeNavPermissions,true))continue;
 if($key==='providers'&&!$financeNavAdmin&&!array_intersect(['bookings.manage','expenses.submit','expenses.manage'],$financeNavPermissions))continue;
 $financeNavPath=$key==='corrections'?('financial-corrections.php'.(!in_array('bookings.manage',$financeNavPermissions,true)?'?area=expenses':'')):($key==='payroll'?'payroll.php':($key==='purchases'?'purchases.php':($key==='expenses'&&$financeNavExpense?'expenses.php':'finance.php?page='.$key)));
?><a href="<?=access_h(access_url($financeNavPath))?>" <?=$key===$financeNavPage?'aria-current="page"':''?>><?=access_h($label)?></a>
<?php endforeach?>
</nav>
<?php if($financeNavPage==='expenses'):?>
<nav class="app-section-nav expense-navigation" aria-label="Expense Sections">
<?php if($financeNavExpense):?><a href="<?=access_h(access_url('expenses.php'))?>" <?=basename($_SERVER['SCRIPT_NAME']??'')==='expenses.php'?'aria-current="page"':''?>>Capture & Approval</a><?php endif?>
<?php if($financeNavAdmin):?><a href="<?=access_h(access_url('finance.php?page=expenses'))?>" <?=basename($_SERVER['SCRIPT_NAME']??'')==='finance.php'?'aria-current="page"':''?>>Expense Payments</a><?php endif?>
</nav>
<?php endif?>
