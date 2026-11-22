<?php
require_once dirname(__DIR__).'/layouts/context.php';
$workspace=workspace_context($user);
$extrasMenu=in_array('extras.manage',$workspace['permissions'],true)?'extras.php':(in_array('sales.view',$workspace['permissions'],true)?'extra_sales.php':'stock.php');
$financeMenu=!array_intersect(['bookings.manage','reports.view','payments.view'],$workspace['permissions'])&&array_intersect(['expenses.submit','expenses.manage','expenses.approve'],$workspace['permissions'])?'expenses.php':'finance.php';
$ownerHome=getenv('TIVOLI_OWNER_REPORTS') !== '0'
    && in_array('admin',$workspace['roles'],true) && in_array('reports.view',$workspace['permissions'],true);
$home=$ownerHome?'owner_reports.php':'dashboard.php';
$groups=[
 'Overview'=>[$ownerHome?[$home,'Owner Reports','reports.view','report']:[$home,'Today','dashboard.view','today']],
 'Guest Operations'=>[['bookings.php','Check-In & Occupancy','bookings.manage','stays'],['handover.php','Shift Handover','bookings.manage','audit'],['housekeeping.php','Housekeeping','bookings.manage|rooms.manage','rooms'],['weekly-audits.php','Property & Audits','bookings.manage|rooms.manage','rooms'],[$extrasMenu,'Extras & Stock','extras.manage|sales.view|stock.manage','extras']],
 'Money & Review'=>[[$financeMenu,'Finance & Cash','bookings.manage|expenses.submit|expenses.manage|expenses.approve|reports.view|payments.view','money'],['reports.php','Reports','reports.view','report']],
 'Administration'=>[['payroll.php',((new \Tivoli\Service\FinanceService(access_service()))->canPostLate((int)$user['id'])&&in_array('hr.salary',$workspace['permissions'],true))?'Payroll & Payslips':'My Payslips','hr.salary|hr.self','money'],['staff.php','Staff & HR','hr.view|hr.self','users'],['hr-settings.php','Settings','hr.settings','audit'],['users.php','System Access','users.manage|roles.manage|roles.assign|privileges.manage','users'],['audit.php','Audit Trail','audit.view','audit'],['admin_recovery.php','Historical Recovery','recovery.manage','audit']],
];
$groups['Overview'][]=['reminders.php','Reminders','dashboard.view|bookings.manage|reports.view|hr.self|hr.view|users.manage','audit'];
if((new \Tivoli\Service\OwnerExceptionService(access_service()))->owner((int)$user['id']))$groups['Overview'][]=['owner-exceptions.php','Owner Exceptions','reports.view','audit'];
?>
<a class="app-skip" href="#workspace-main">Skip to content</a>
<aside class="app-sidebar" id="app-sidebar" aria-label="Main navigation" tabindex="-1">
 <a class="app-brand" aria-label="Tivoli Guesthouse workspace" href="<?=access_h(access_url($workspace['locked']?'auth/change-password.php':$home))?>"><img src="<?=access_h(access_url('assets/images/Tivoli.png'))?>" width="44" height="44" alt=""><span>Tivoli<small>GUESTHOUSE</small></span></a>
 <button class="app-drawer-close" type="button" id="app-drawer-close" aria-label="Close navigation">Close <span aria-hidden="true">×</span></button>
 <div class="app-sidebar-caption">STAFF WORKSPACE</div>
 <nav class="app-navigation" aria-label="Guesthouse">
 <?php foreach($groups as $group=>$items): $visible=array_filter($items,static fn($item)=>!$workspace['locked'] && array_intersect(explode('|',$item[2]),$workspace['permissions'])); if(!$visible)continue;?>
 <div class="app-nav-group"><p><?=access_h($group)?></p><?php foreach($visible as [$path,$label,$permission,$icon]):$staySection=$_GET['section'] ?? 'board';$staySelected=$workspace['page']==='occupancy.php' && (($path==='bookings.php' && !in_array($staySection,['extras','rooms','areas'],true)) || ($path===$extrasMenu && $staySection==='extras') || ($path==='weekly-audits.php' && in_array($staySection,['rooms','areas'],true)));$selected=$staySelected || ($path===$extrasMenu&&in_array($workspace['page'],['extras.php','extra_sales.php','stock.php','stock-counts.php','stock-issues.php'],true)) || ($path===$financeMenu&&in_array($workspace['page'],['finance.php','expenses.php','purchases.php','financial-corrections.php'],true)) || ($path==='weekly-audits.php'&&$workspace['page']==='rooms.php') || $workspace['page']===$path || ($path==='bookings.php' && in_array($workspace['page'],['checkin.php','checkout.php'],true));?>
 <a href="<?=access_h(access_url($path))?>" title="<?=access_h($label)?>" <?=$selected?'aria-current="page"':''?>><?=workspace_icon($icon)?><span><?=access_h($label)?></span><?php if($path===$financeMenu && $workspace['pending']):?><b class="app-count" aria-label="<?=(int)$workspace['pending']?> pending"><?=(int)$workspace['pending']?></b><?php endif?></a>
 <?php endforeach?></div><?php endforeach?>
 <?php if($workspace['locked']):?><p class="app-locked">Change your temporary password to open your workspace.</p><?php endif?>
 </nav>
 <div class="app-sidebar-foot"><span class="app-status-dot"></span><span>Made for a warmer welcome.</span></div>
</aside>
<button class="app-backdrop" id="app-backdrop" aria-label="Close navigation" tabindex="-1" hidden></button>
