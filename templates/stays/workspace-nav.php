<?php
$stayNavCanViewStays=in_array('bookings.manage',access_service()->repo->privileges((int)$user['id']),true);
$stayNavInPreparation=($section??'')==='housekeeping'||basename($_SERVER['SCRIPT_NAME']??'')==='housekeeping.php';
$stayNavGuestContext=!empty($guest)||($stayNavInPreparation&&!empty($fromStay));
$stayNavBoardCurrent=($stayNavInPreparation&&!$stayNavGuestContext)||(($section??'')==='board'&&!$stayNavGuestContext);
?>
<link rel="stylesheet" href="<?=access_h(workspace_asset_url('assets/css/occupancy-navigation.css'))?>">
<nav class="app-section-nav" aria-label="Stays">
<a href="<?=access_h(stay_url($stayNavCanViewStays?[]:['section'=>'housekeeping']))?>#stay-workspace" <?=$stayNavBoardCurrent||!$stayNavCanViewStays?'aria-current="page"':''?>>Room Board</a>
<?php if($stayNavCanViewStays):?>
<a href="<?=access_h(stay_url(['section'=>'folios']))?>#stay-workspace" <?=$stayNavGuestContext||($section??'')==='folios'?'aria-current="page"':''?>>Guest Stay Account</a>
<a href="<?=access_h(stay_url(['section'=>'departures']))?>#stay-workspace" <?=!$stayNavGuestContext&&($section??'')==='departures'?'aria-current="page"':''?>>Departure Incidents</a>
<a href="<?=access_h(stay_url(['section'=>'activity']))?>#stay-workspace" <?=!$stayNavGuestContext&&($section??'')==='activity'?'aria-current="page"':''?>>Activity</a>
<?php endif?></nav>
