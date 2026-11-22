<?php
$departureCase=$view['departure_case']??null;
$report=$departureCase['report']??null;
$pendingCase=$departureCase&&in_array($departureCase['status'],['pending','disputed'],true);
$nowUtc=gmdate('Y-m-d\TH:i:s');
?>
<?php if($form==='report_departure'&&$guest['status']==='active'):?>
<p>Record the guest’s actual departure or the time their absence was discovered. The bill stays provisional until Eugene reviews it.</p>
<form method="post" enctype="multipart/form-data" class="stay-form" data-confirm="Report this departure? Review vacancy and access security. No payment will be created.">
<?php stay_fields('report_departure',$bookingId);
stay_input('Discovered At (UTC)','discovered_at',$nowUtc,'datetime-local',true,'step="1"');
stay_input('Actual Departure At (UTC, If Known)','actual_departed_at','','datetime-local',false,'step="1"');
stay_textarea('What Happened','reason','',true);?>
<label class="stay-field">Supporting Evidence (Optional)<input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png"><small>PDF, JPEG or PNG · Maximum 2 MB · Private</small></label>
<?php stay_check('I Verified That the Room Is Vacant','vacant');stay_check('Keys Recovered or Access Secured','secured');?>
<p>Verified vacancy and secured access allow cleaning. Otherwise the room stays blocked until staff verify both.</p>
<div class="stay-actions"><button class="button">Report Departure</button><?php $link('Cancel',['booking_id'=>$bookingId]);?></div></form>
<?php elseif($form==='secure_departure_room'&&$departureCase&&!$departureCase['secured']&&$departureCase['status']!=='retracted'):?>
<form method="post" class="stay-form" data-confirm="Confirm the room is vacant and access is secured?">
<?php stay_fields('secure_departure_room',$bookingId);
stay_check('I Verified That the Room Is Vacant','vacant',true);stay_check('Keys Recovered or Access Secured','secured',true);
stay_textarea('Vacancy / Access Check','reason','',true);?>
<div class="stay-actions"><button class="button">Record Security Verification</button><?php $link('Cancel',['booking_id'=>$bookingId]);?></div></form>
<?php elseif($form==='review_departure'&&$pendingCase&&$canAuthorizeDeparture):?>
<?php if(!$departurePreview):?>
<form method="post" class="stay-form">
<?php stay_fields('preview_departure',$bookingId);
stay_input('Confirmed Actual Departure (UTC)','actual_departed_at',str_replace(' ','T',$report['data']['actual_departed_at']??''),'datetime-local',true,'step="1"');?>
<p>Unknown times require evidence before finalisation. Review charges at the confirmed departure time.</p>
<button class="button">Review Final Charges</button></form>
<?php else:$summary($departurePreview['bill']);?>
<p>Confirmed Departure <?=access_h(display_date($departurePreview['actual_departed_at']))?> UTC · Room revenue posts in today’s open period.</p>
<form method="post" class="stay-form" data-confirm="Finalize this departure and its displayed charges? The outstanding debt will remain payable.">
<?php stay_fields('review_departure',$bookingId,['decision'=>'confirm','actual_departed_at'=>$departurePreview['actual_departed_at'],'review_token'=>$departurePreview['token']]);
stay_textarea('Review Reason / Evidence Reference','reason','',true);
if($departurePreview['bill']['balance']!==0):?>
<label class="stay-field">Settlement Follow-Up Owner *<select name="owner_id" required><option value="">Choose Active Staff</option><?php foreach($incidentAssignees as $assignee):?><option value="<?=(int)$assignee['id']?>"><?=access_h($assignee['name'])?></option><?php endforeach?></select></label>
<?php stay_input('Follow-Up Due Date','due',gmdate('Y-m-d'),'date',true);endif;?>
<div class="stay-actions"><button class="button">Confirm Final Departure Bill</button><?php $link('Review Again',['booking_id'=>$bookingId,'form'=>'review_departure']);?></div></form>
<?php endif?>
<details><summary>Dispute or Correct the Report</summary><form method="post" class="stay-form" data-confirm="Record this owner decision? Original evidence is retained.">
<?php stay_fields('review_departure',$bookingId);stay_select('Decision','decision',['dispute'=>'Dispute · Investigate Further','retract'=>'Incorrect Report · Guest Still In House'],'dispute',true);stay_textarea('Decision Reason','reason','',true);?>
<p>Restoring the original occupancy requires an unfinalised case and no later room occupation.</p><button class="button">Record Owner Decision</button></form></details>
<?php elseif($form==='departure_followup'&&$departureCase&&$departureCase['status']!=='retracted'&&$canAuthorizeDeparture):?>
<form method="post" class="stay-form"><?php stay_fields('departure_followup',$bookingId);?>
<label class="stay-field">Follow-Up Owner *<select name="owner_id" required><option value="">Choose Active Staff</option><?php foreach($incidentAssignees as $assignee):?><option value="<?=(int)$assignee['id']?>" <?=(int)($departureCase['assignment']['owner_id']??0)===(int)$assignee['id']?'selected':''?>><?=access_h($assignee['name'])?></option><?php endforeach?></select></label>
<?php stay_input('Next Follow-Up Date','due',gmdate('Y-m-d'),'date',true);stay_textarea('Contact Attempt / Next Action','reason','',true);?>
<div class="stay-actions"><button class="button">Record Follow-Up</button><?php $link('Cancel',['booking_id'=>$bookingId]);?></div></form>
<?php elseif($form==='collect_outstanding'&&$canAuthorizeDeparture&&$guest['status']==='checked_out'&&$bill['checkout_balance']!==null&&$bill['balance']>0&&!$bill['legacy_unknown']):?>
<p>Outstanding <?=stay_money($bill['balance'])?>. Record money received now. A partial collection leaves the remaining balance visible.</p>
<form method="post" class="stay-form" data-confirm="Record this payment against the departed guest’s outstanding balance?">
<?php stay_fields('collect_outstanding',$bookingId,['expected_balance'=>(string)$bill['balance']]);
stay_input('Amount Received (GHS)','amount','','text',true,'inputmode="decimal"');
stay_select('Payment Method','method',['cash'=>'Cash','momo'=>'MoMo'],'cash',true);
stay_input('Receipt / Transaction Reference','reference','','text',true);
stay_textarea('Collection Note / Evidence','reason','',true);?>
<div class="stay-actions"><button class="button">Record Outstanding Collection</button><?php $link('Cancel',['booking_id'=>$bookingId]);?></div></form>
<?php else:?><p>This action requires an eligible stay and the appropriate authority.</p><?php endif?>
