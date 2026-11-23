<?php require dirname(__DIR__).'/layouts/start.php';?>
<link rel="stylesheet" href="<?=access_h(workspace_asset_url('assets/css/hr.css'))?>"><script src="<?=access_h(workspace_asset_url('assets/js/hr.js'))?>" defer></script>
<div class="app-page-heading-row"><div><p class="app-eyebrow">PEOPLE / STAFF & HR</p><h1><?=$editing?($snapshot?'Edit staff record.':'Welcome someone new.'):($snapshot?access_h($snapshot['staff']['data']['name'] ?? 'Staff profile'):'Staff & HR')?></h1><p><?=$editing?'Prepare their staff record and first day.':($snapshot?'Employment, wellbeing and accountable system access.':'Your team, their employment records and onboarding.')?></p></div><div class="actions"><?php if(!$editing&&!$snapshot&&$can('hr.manage')):?><a class="button" href="<?=access_h(access_url('staff.php?new=1'))?>">New Staff Member</a><?php endif?><?php if($snapshot&&!$editing):?><a href="<?=access_h(access_url('staff.php'))?>">Staff Directory</a><?php endif?></div></div>
<?php if($error):?><p class="error" role="alert"><?=access_h($error)?></p><?php endif?><?php if($notice):?><p class="notice" role="status"><?=access_h($notice)?></p><?php endif?>
<?php if($editing&&(!$error||$restoreDraft)): $staff=$snapshot['staff'] ?? ['id'=>0,'version'=>0,'data'=>[]];$data=$staff['data'];require __DIR__.'/onboarding.php';?>
<?php elseif(!$error&&$snapshot):$staff=$snapshot['staff'];$data=$staff['data'];$version=(int)$staff['version'];require __DIR__.'/profile-context.php';?>
<div class="hr-profile-summary"><span class="hr-badge"><?=access_h($staff['staff_no'])?></span><span class="hr-badge"><?=access_h(str_replace('_',' ',$staff['status']))?></span><span><?=access_h($data['job'] ?? '')?> · <?=access_h($data['department'] ?? '')?></span><?php if($can('hr.manage')):?><a href="?id=<?=$id?>&edit=1">Edit Profile</a><?php endif?></div>
<nav class="app-section-nav workspace-tab-strip" aria-label="Staff profile sections"><?php foreach(['overview'=>'Overview','employment'=>'Employment','salary'=>'Salary & Payment','leave'=>'Leave','documents'=>'Documents','access'=>'System Access','activity'=>'Activity'] as $key=>$label):if(($key==='salary'&&!$can('hr.salary'))||($key==='documents'&&!$can('hr.documents'))||($key==='access'&&!$can('hr.link'))||($key==='activity'&&!$can('hr.view')))continue;?><a href="?id=<?=$id?>&tab=<?=$key?>" <?=$tab===$key?'aria-current="page"':''?>><?=access_h($label)?></a><?php endforeach?></nav>
<?php require __DIR__.'/'.$tab.'.php';?>
<?php elseif(!$error):?>
<?php if($can('hr.self')):foreach($directory as $ownProfile):
if((int)($ownProfile['user_id']??0)!==(int)$user['id'])continue;
$ownStaffLink=access_url('staff.php?id='.(int)$ownProfile['id']);?>
<nav class="app-section-nav" aria-label="My Staff Record">
<a href="<?=access_h($ownStaffLink.'&tab=leave')?>">My Leave</a>
<?php if($can('hr.documents')):?><a href="<?=access_h($ownStaffLink.'&tab=documents')?>">My Documents</a><?php endif?>
<a href="<?=access_h(access_url('payroll.php'))?>">My Payslips</a>
</nav>
<?php break;endforeach;endif?>
<section class="card"><h2>Staff Directory</h2><div class="scroll"><table><thead><tr><th>Staff member</th><th>Employment</th><th>Status</th><th>Account</th></tr></thead><tbody><?php foreach($directory as $row):?><tr><td><a href="?id=<?=(int)$row['id']?>"><?=access_h($row['data']['name'] ?? $row['staff_no'])?></a><br><small><?=access_h($row['staff_no'])?></small></td><td><?=access_h($row['data']['job'] ?? '')?><br><small><?=access_h($row['data']['department'] ?? '')?></small></td><td><?=access_h(str_replace('_',' ',$row['status']))?></td><td><?=$row['user_id']?'Linked user #'.(int)$row['user_id']:'No linked account'?></td></tr><?php endforeach?><?php if(!$directory):?><tr><td colspan="4">No staff records available.<?php if(!$can('hr.view')):?> Ask an administrator to link your account to your staff record.<?php endif?></td></tr><?php endif?></tbody></table></div></section>
<?php endif?><?php require dirname(__DIR__).'/layouts/end.php';?>
