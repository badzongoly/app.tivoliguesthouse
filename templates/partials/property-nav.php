<?php
$propertyNavActor=(int)$user['id'];
$propertyNavPermissions=access_service()->repo->privileges($propertyNavActor);
$propertyNavWeekly=basename($_SERVER['SCRIPT_NAME']??'')==='weekly-audits.php';
$propertyNavAdmin=access_service()->repo->isAdmin($propertyNavActor)&&in_array('rooms.manage',$propertyNavPermissions,true)&&in_array('bookings.manage',$propertyNavPermissions,true);
$propertyNavOwner=$propertyNavWeekly?($owner??false):($canAuthorizeDeparture??false);
$propertyNavAuditor=false;
if($propertyNavWeekly)$propertyNavAuditor=$auditor??false;
else try{$propertyNavAuditor=(new \Tivoli\Service\WeeklyAuditService(access_service()))->auditor($propertyNavActor);}catch(\Throwable $propertyNavError){$propertyNavAuditor=false;}
$propertyNavActive=$propertyNavWeekly?match($page){'room'=>'room','area'=>'area','duty'=>'duty',default=>'followups'}:match(true){($section??'')==='areas'=>'area',($tab??'')==='faults'=>'faults',($tab??'')==='audits'=>'room',default=>'master'};
$propertyNavItems=[];
if($propertyNavAdmin)$propertyNavItems+=['master'=>['Room Master',stay_url(['section'=>'rooms','tab'=>'master']).'#stay-workspace'],'faults'=>['Out-Of-Order Log',stay_url(['section'=>'rooms','tab'=>'faults']).'#stay-workspace']];
if($propertyNavAuditor)$propertyNavItems+=['room'=>['Room Audits',access_url('weekly-audits.php?page=room').'#audit-action'],'area'=>['Other Area Audits',access_url('weekly-audits.php?page=area').'#audit-action']];
$propertyNavItems+=['followups'=>['Follow-Ups',access_url('weekly-audits.php').'#audit-action']];
if($propertyNavOwner)$propertyNavItems+=['duty'=>['Audit Duty',access_url('weekly-audits.php?page=duty').'#audit-action']];
?>
<link rel="stylesheet" href="<?=access_h(workspace_asset_url('assets/css/property-navigation.css'))?>">
<nav class="app-section-nav stay-property-nav property-navigation workspace-tab-strip" aria-label="Property &amp; Audits">
<?php foreach($propertyNavItems as $propertyNavKey=>[$propertyNavLabel,$propertyNavHref]):?><a href="<?=access_h($propertyNavHref)?>" <?=$propertyNavActive===$propertyNavKey?'aria-current="page"':''?>><?=access_h($propertyNavLabel)?></a><?php endforeach?>
</nav>
