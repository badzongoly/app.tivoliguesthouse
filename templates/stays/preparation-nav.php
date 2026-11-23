<?php
$stayPrepInWork=($section??'')==='housekeeping';
$stayPrepOwner=$stayPrepInWork?($owner??false):($canAuthorizeDeparture??false);
$stayPrepPage=$stayPrepInWork?($page??'board'):'board';
?>
<nav class="app-section-nav workspace-tab-strip" aria-label="<?=$stayPrepInWork?'Room Preparation':'Room Preparation Filters'?>">
<?php if(!$stayPrepInWork):foreach(['all'=>'All Rooms','ready'=>'Ready for Check-In','needs_cleaning'=>'Needs Cleaning','cleaning'=>'Cleaning In Progress','inspection'=>'Awaiting Inspection','blocked'=>'Blocked'] as $stayPrepValue=>$stayPrepLabel):?><a href="<?=access_h(stay_url(['preparation'=>$stayPrepValue]))?>#stay-workspace" <?=($preparationFilter??'all')===$stayPrepValue?'aria-current="page"':''?>><?=access_h($stayPrepLabel)?></a><?php endforeach;endif?>
<a href="<?=access_h(stay_url(['section'=>'housekeeping','page'=>'activity']))?>#housekeeping-action" <?=$stayPrepPage==='activity'&&empty($id)&&empty($roomId)?'aria-current="page"':''?>>Cleaning History</a>
<?php if($stayPrepOwner):?><a href="<?=access_h(stay_url(['section'=>'housekeeping','page'=>'owner']))?>#housekeeping-action" <?=$stayPrepPage==='owner'&&empty($id)?'aria-current="page"':''?>>Owner Review</a><?php endif?>
</nav>
