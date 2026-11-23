<?php $departureCase=$view['departure_case']??null;
if($departureCase):
$report=$departureCase['report'];$assignment=$departureCase['assignment'];
$status=match($departureCase['status']){'pending'=>'Departure Reported — Awaiting Eugene','disputed'=>'Departure Disputed — Investigation Open','retracted'=>'Departure Report Retracted',default=>$bill['balance']>0?'Departed — Outstanding Balance':($bill['balance']<0?'Departed — Guest Credit':'Departed — Settled')};
?>
<section class="card stay-incident">
<h2><?=access_h($status)?></h2>
<p>Reported by <?=access_h($report['actor_name'])?> · <?=access_h(display_date($report['created_at']))?> UTC</p>
<p>Discovered <?=access_h(display_date($report['data']['discovered_at']))?> UTC · Reported Departure <?=$report['data']['actual_departed_at']?access_h(display_date($report['data']['actual_departed_at'])).' UTC':'Unknown'?></p>
<p><?=access_h($report['data']['description'])?></p>
<?php if($bill['provisional']):?><p class="notice">Provisional Bill · Automatic room charges stopped at the reported departure or discovery time. Eugene must confirm the actual time and final charges.</p><?php endif?>
<?php if(!$departureCase['secured']&&$departureCase['status']!=='retracted'):?><p class="error">Room Blocked · Vacancy and access security require verification.</p><?php $link('Verify Vacancy & Secure Access',['booking_id'=>$bookingId,'form'=>'secure_departure_room']);endif?>
<?php if($assignment&&$assignment['owner_id']&&$departureCase['status']!=='retracted'):?><p>Follow-Up <?=access_h($assignment['owner_name'])?> · Due <?=access_h(display_date($assignment['due']))?><?=$assignment['due']<gmdate('Y-m-d')?' · Overdue':''?></p><?php endif?>
<div class="stay-actions">
<?php if($canAuthorizeDeparture):
if(in_array($departureCase['status'],['pending','disputed'],true))$link('Review Departure',['booking_id'=>$bookingId,'form'=>'review_departure'],'');
if($departureCase['status']!=='retracted')$link('Record Follow-Up',['booking_id'=>$bookingId,'form'=>'departure_followup']);

if(!empty($report['data']['attachment'])):?><a class="button secondary" href="<?=access_h(access_url('finance-evidence.php?kind=incident&id='.(int)$report['id']))?>">Download Private Evidence</a><?php endif;endif?>
</div>
<details><summary>Incident and Follow-Up History</summary>
<?php foreach($departureCase['history'] as $event):?>
<article class="stay-event"><strong><?=access_h(stay_label(str_replace('_',' ',$event['kind'])))?></strong><small><?=access_h($event['actor_name'].' · '.display_date($event['created_at']))?> UTC</small>
<p><?=access_h($event['data']['reason']??'')?></p><?php if(!empty($event['data']['owner_name'])):?><p><?=access_h($event['data']['owner_name'].' · Due '.display_date($event['data']['due']))?></p><?php endif?></article>
<?php endforeach?></details>
<?php if(!empty($view['prior_departure_cases'])):?>
<details><summary>Earlier Departure Reports (<?=count($view['prior_departure_cases'])?>)</summary>
<?php foreach($view['prior_departure_cases'] as $priorCase):$oldReport=$priorCase['report'];?>
<article class="stay-event"><h3>Report #<?=(int)$oldReport['id']?> · <?=access_h(stay_label($priorCase['status']))?></h3>
<p><?=access_h($oldReport['actor_name'].' · '.display_date($oldReport['created_at']))?> UTC</p>
<p><?=access_h($oldReport['data']['description'])?></p>
<p>Original Departure <?=$oldReport['data']['actual_departed_at']?access_h(display_date($oldReport['data']['actual_departed_at'])).' UTC':'Unknown'?> · Discovered <?=access_h(display_date($oldReport['data']['discovered_at']))?> UTC</p>
<p><?=$oldReport['data']['snapshot']['balance']<0?'Original Provisional Guest Credit':'Original Provisional Balance'?> <?=$oldReport['data']['snapshot']['legacy_unknown']?'Unknown Historical Rate':stay_money(abs($oldReport['data']['snapshot']['balance']))?></p>
<?php if($canAuthorizeDeparture&&!empty($oldReport['data']['attachment'])):?><a href="<?=access_h(access_url('finance-evidence.php?kind=incident&id='.(int)$oldReport['id']))?>">Download Original Private Evidence</a><?php endif?>
<?php foreach($priorCase['history'] as $oldEvent):?><p><?=access_h(stay_label(str_replace('_',' ',$oldEvent['kind'])).' · '.$oldEvent['actor_name'].' · '.display_date($oldEvent['created_at']))?> UTC<br><?=access_h($oldEvent['data']['reason']??'')?></p><?php endforeach?>
</article>
<?php endforeach?></details>
<?php endif?>
</section>
<?php endif?>
