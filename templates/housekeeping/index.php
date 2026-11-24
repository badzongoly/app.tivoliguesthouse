<?php require dirname(__DIR__).'/layouts/start.php'; ?>
<link rel="stylesheet" href="<?=access_h(access_url('assets/css/housekeeping.css'))?>"><script src="<?=access_h(workspace_asset_url('assets/js/housekeeping.js'))?>" defer></script>
<div class="housekeeping">
<?php require dirname(__DIR__).'/stays/workspace-nav.php';?>
<div id="stay-workspace" tabindex="-1"></div>
<?php require dirname(__DIR__).'/stays/preparation-nav.php';?>
<?php if($notice):?><p class="notice" role="status" id="housekeeping-status" tabindex="-1"><?=access_h($notice)?></p><?php endif?>
<?php if($error):?><section class="card error" role="alert" id="housekeeping-error" tabindex="-1"><h2>Action Needs Attention</h2><p><?=access_h($error)?></p><a href="<?=access_h(h_url($id?['id'=>$id]:($roomId?['room_id'=>$roomId]:['page'=>$page])))?>">Reload Current Record</a>
<?php if(http_response_code()===503&&$_SERVER['REQUEST_METHOD']==='POST'):?><form method="post" enctype="multipart/form-data" data-confirm="Retry the same details? This action can commit only once."><?php foreach($values as $name=>$value):if($name==='csrf')continue;?><input type="hidden" name="<?=access_h($name)?>" value="<?=access_h($value)?>"><?php endforeach?><input type="hidden" name="csrf" value="<?=access_h(access_csrf())?>">
<?php if(isset($_POST['done'])&&is_array($_POST['done']))foreach($_POST['done'] as $value)if(is_string($value)):?><input type="hidden" name="done[]" value="<?=access_h($value)?>"><?php endif?>
<?php if(isset($_FILES['evidence'])&&($_FILES['evidence']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)h_upload();?><button type="submit">Retry Same Action</button></form><?php endif?></section><?php endif?>
<div id="housekeeping-action" tabindex="-1">
<?php if(!in_array(http_response_code(),[403,503],true)): ?>
<?php if($room):$c=$case;$f=$room['facts'];$d=$c['root']['data']??null;$extra=$c?['id'=>(string)$c['root']['id'],'version'=>$c['version']]:[];$cleaner=$c&&(int)$c['root']['actor_id']===$actor;$roomNumber=$f['id']??$d['room_id'];$roomName=$f['name']??$d['room_name'];$canStart=!$readOnly&&$f&&!$f['blocked']&&!$f['occupied']&&in_array([$f['status'],$f['ready_state']],[['dirty','cleaning'],['dirty','awaiting_inspection'],['vacant','unverified']],true)&&(!$c||!$c['valid']||in_array($c['status'],['returned','released','superseded'],true));?>
<?php if($form!=='clean'):?><section class="card"><div class="hk-row"><h2>Room <?=access_h($roomName)?></h2><span class="hk-badge"><?=access_h($readOnly?h_attempt_status($c['status']):h_status($room))?></span></div>
<?php if($c):?><p>Attempt #<?=(int)$c['root']['id']?> · Cleaner <?=access_h($c['root']['actor_name'])?> · <?=access_h(h_attempt_status($c['status']))?></p><p>Started <?=access_h(display_date($c['root']['created_at']))?> UTC</p>
<?php if(!$c['current']):?><p>This is an earlier attempt.</p><?php if(!$readOnly&&$room['latest'])h_link('Current Attempt',['id'=>$room['latest']]); ?>
<?php elseif(!$c['valid']&&!in_array($c['status'],['released','superseded'],true)):?><p class="error">Room occupancy or safety facts changed. This attempt cannot release the room. Original evidence is retained.</p><?php endif?>
<?php if(!$readOnly&&$d['previous_id'])h_link('Earlier Attempt #'.$d['previous_id'],['id'=>$d['previous_id']]);endif?>
<div class="hk-row">
<?php if($canStart):?><form method="post" data-confirm="Start physical cleaning for this unused, unblocked room?"><?php h_fields('start',['room_id'=>(string)$roomNumber,'room_token'=>$room['token']]);?><button type="submit">Start Cleaning</button></form><?php endif?>
<?php if(!$readOnly&&$c&&$c['valid']): ?>
<?php if($c['status']==='cleaning'&&$cleaner&&$form!=='clean')h_link('Continue Cleaning',['id'=>$c['root']['id'],'form'=>'clean'],true); ?>
<?php if($c['status']==='inspection'): ?>
<?php if(!$cleaner||($c['exception']['status']??'')==='approved'):if($form!=='inspect')h_link('Inspect & Release Room',['id'=>$c['root']['id'],'form'=>'inspect'],true); ?>
<?php else:?><p>You completed this cleaning. Another authorized staff member should inspect.</p>
<?php if(!in_array($c['exception']['status']??'',['pending','approved'],true)):if($form!=='exception')h_link($owner?'Authorize Owner Self-Inspection':'Request Self-Inspection Exception',['id'=>$c['root']['id'],'form'=>'exception']); ?>
<?php else:?><span class="hk-badge">Exception <?=access_h($c['exception']['status'])?></span><?php endif?>
<?php endif;endif;endif?>
<?php if(!$readOnly&&$owner&&$c&&$c['current']&&in_array($c['status'],['cleaning','inspection'],true)&&$form!=='restart')h_link('Restart Abandoned Attempt',['id'=>$c['root']['id'],'form'=>'restart']);?>
</div></section><?php endif?>
<?php if($readOnly):?><p>This is the cleaning record linked to Stay #<?=$fromStay?>. Current room work is managed from the Room Board.</p><?php if($stayNavCanViewStays):?><a class="hk-button" href="<?=access_h(stay_url(['booking_id'=>$fromStay]))?>#stay-bill">Back To Guest Stay Account</a><?php endif;endif?>
<?php if($form&&$c)require __DIR__.'/form.php';if($c&&$isAdmin&&$form!=='clean')require __DIR__.'/history.php'; ?>
<?php elseif($page==='owner'): ?>
<section class="card"><h2>Self-Inspection Requests</h2><?php foreach($attention['requests']??[] as $row):?><article class="hk-item"><h3>Room <?=access_h($row['root']['data']['room_name'])?> · <?=access_h($row['root']['actor_name'])?></h3><p><?=access_h($row['exception']['data']['reason'])?></p><?php h_link('Review Request',['id'=>$row['root']['id'],'form'=>'decision']);?></article><?php endforeach;if(empty($attention['requests'])):?><p>No pending exception requests.</p><?php endif?></section>
<section class="card"><h2>Follow-Up Reviews</h2><?php foreach($attention['followups']??[] as $row):?><article class="hk-item"><h3>Room <?=access_h($row['case']['root']['data']['room_name'])?> · Attempt #<?=(int)$row['case']['root']['id']?></h3><p><?=access_h($row['inspection']['actor_name'].' · '.display_date($row['inspection']['created_at']))?> UTC</p><?php h_link('Review Self-Inspection',['id'=>$row['case']['root']['id'],'form'=>'followup','inspection_id'=>$row['inspection']['id']]);?></article><?php endforeach;if(empty($attention['followups'])):?><p>No self-inspections awaiting review.</p><?php endif?></section>
<?php else: ?>
<section class="card"><h2><?=$page==='activity'?(access_service()->repo->isAdmin($actor)?'Housekeeping History':'My Housekeeping History'):'Room Readiness'?></h2><div class="hk-scroll"><table><thead><tr><th>Room</th><th>Status</th><th>Cleaner / Attempt</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $row):$attempt=$page==='activity'?$row:$row['attempt'];$v=$page==='activity'?$row['room']:$row;$f=$v['facts'];?><tr><th><?=access_h($f['name']??$attempt['root']['data']['room_name'])?></th><td><?=access_h($page==='activity'?h_attempt_status($attempt['status']):h_status($v))?></td><td><?=$attempt?access_h($attempt['root']['actor_name']).' · #'.(int)$attempt['root']['id']:'No Recorded Attempt'?></td><td><?php h_link('Show Details',$page==='activity'?['id'=>$attempt['root']['id']]:['room_id'=>$f['id']]);?></td></tr><?php endforeach?></tbody></table></div><?php if(!$rows):?><p>No rooms or attempts in this view.</p><?php endif;if($next)h_link('Older Records',['page'=>$page,'before'=>$next]);?></section>
<?php endif;endif?>
</div></div><?php require dirname(__DIR__).'/layouts/end.php'; ?>
