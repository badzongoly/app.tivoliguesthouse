<?php
// Presentation only: each existing endpoint remains its own permission boundary.
$settingsPermissions=$workspace['permissions'];$settingsAdmin=in_array('admin',$workspace['roles'],true);
$settingsHR=in_array('hr.settings',$settingsPermissions,true)&&!$workspace['locked'];
$settingsRooms=$settingsHR&&$settingsAdmin&&in_array('rooms.manage',$settingsPermissions,true);
$settingsExtras=$settingsHR&&$settingsAdmin&&in_array('extras.manage',$settingsPermissions,true);
$settingsFinanceReady=$financeActive ?? (new \Tivoli\Service\FinanceService(access_service()))->installed();
$settingsCanEditOperations=$settingsAdmin&&$settingsHR&&in_array('bookings.manage',$settingsPermissions,true)&&(($kind ?? '')==='extras'?$settingsExtras:$settingsRooms);
$settingsTabs=[];
if($settingsHR)$settingsTabs+=['payments'=>['hr-settings.php','Payment Lists'],'documents'=>['hr-settings.php?section=documents','Document Types']];
if($settingsRooms)$settingsTabs+=['cleaning'=>['cleaning-settings.php','Cleaning Checklist'],'inspection'=>['cleaning-settings.php?type=inspection','Inspection Checklist'],'areas'=>['cleaning-settings.php?type=areas','Audit Areas']];
if($settingsExtras)$settingsTabs['extras']=['cleaning-settings.php?type=extras','Extra Categories'];
if($settingsFinanceReady&&!$workspace['locked'])$settingsTabs['notifications']=['reminders.php?page=settings','Notifications'];
$settingsFile=basename($_SERVER['SCRIPT_NAME']);
$settingsCurrent=match($settingsFile){'hr-settings.php'=>($section==='documents'?'documents':'payments'),'cleaning-settings.php'=>$kind,default=>'notifications'};
?>
<link rel="stylesheet" href="<?=access_h(workspace_asset_url('assets/css/settings-workspace.css'))?>">
<script src="<?=access_h(workspace_asset_url('assets/js/settings-workspace.js'))?>" defer></script>
<nav class="app-section-nav settings-navigation" aria-label="Settings"><?php foreach($settingsTabs as $settingsKey=>[$settingsPath,$settingsLabel]):?><a href="<?=access_h(access_url($settingsPath))?>" <?=$settingsKey===$settingsCurrent?'aria-current="page"':''?>><?=access_h($settingsLabel)?></a><?php endforeach?></nav>
