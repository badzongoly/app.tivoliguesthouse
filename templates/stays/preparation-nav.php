<?php
$stayPrepInWork=($section??'')==='housekeeping';
$stayPrepOwner=$stayPrepInWork?($owner??false):($canAuthorizeDeparture??false);
$stayPrepPage=$stayPrepInWork?($page??'board'):'board';
?>
<nav class="app-section-nav workspace-tab-strip" aria-label="Room Preparation">
<a href="<?=access_h(stay_url(['section'=>'housekeeping','page'=>'activity']))?>#housekeeping-action" <?=$stayPrepPage==='activity'&&empty($id)&&empty($roomId)?'aria-current="page"':''?>>Cleaning History</a>
<?php if($stayPrepOwner):?><a href="<?=access_h(stay_url(['section'=>'housekeeping','page'=>'owner']))?>#housekeeping-action" <?=$stayPrepPage==='owner'&&empty($id)?'aria-current="page"':''?>>Owner Review</a><?php endif?>
</nav>
