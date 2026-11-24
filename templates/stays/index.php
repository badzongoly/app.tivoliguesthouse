<?php
require dirname(__DIR__).'/layouts/start.php';
$catalogueQuery=$section==='extras'&&$tab!=='stock'?\Tivoli\Domain\Stay\ExtrasCatalogue::filters($_GET):[];
$cancelRendered=false;$guest=$view['booking'] ?? null;$bill=$view['bill'] ?? null;
$isolatedCheckin=$form==='checkin'&&$section==='board'&&!$bookingId&&$roomId>0;
$extraFeedback=$guest&&str_starts_with($notice,'Extra posted to the guest account.');
$url=static fn(array $q=[]):string=>access_h(stay_url($q));
$link=static function(string $label,array $q,string $style='secondary') use($url,$form,$bookingId,$roomId,$editId,$section):void {
    // Keep the open form as the action's single entry; other guests/rooms retain their own links.
    if(in_array($section,['board','folios','activity','departures'],true)&&!empty($q['form'])&&$q['form']===$form
        &&(int)($q['booking_id']??0)===$bookingId&&(int)($q['room_id']??0)===$roomId&&(int)($q['id']??0)===$editId)return;
    if(in_array($section,['rooms','areas'],true)){
        if(!empty($q['form'])&&$q['form']===$form&&(int)($q['booking_id']??0)===$bookingId&&(int)($q['room_id']??0)===$roomId&&(int)($q['id']??0)===$editId)return;
        if(in_array($q['form']??'',['room_audit','area_audit'],true)){
            $propertyEntry=['page'=>$q['form']==='room_audit'?'room':'area','form'=>'new'];
            if(!empty($q['room_id']))$propertyEntry['room_id']=(int)$q['room_id'];
            ?><a class="button <?=access_h($style)?>" href="<?=access_h(access_url('weekly-audits.php?'.http_build_query($propertyEntry)))?>#audit-action"><?=access_h(stay_label($label))?></a><?php return;
        }
    }
    ?><a class="button <?=access_h($style)?>" href="<?=$url($q)?>#stay-action"><?=access_h(stay_label($label))?></a><?php };
$summary=static function(array $b):void { ?><div class="stay-summary"><div><small>Charges</small><strong><?=$b['legacy_unknown']?'Unknown Historical Rate':stay_money($b['room']+$b['extras']+$b['incidental'])?></strong></div><div><small>Paid</small><small>Net of Refunds</small><strong><?=stay_money($b['paid'])?></strong></div><div><small><?=$b['balance']<0?'Overpayment':'Balance Due'?></small><strong><?=$b['legacy_unknown']?'Needs Reconciliation':stay_money(abs($b['balance']))?></strong></div></div><?php };
$target=null;foreach($view['rooms'] ?? [] as $r)if((int)$r['id']===$roomId)$target=$r;
$extra=null;foreach($view['extras'] ?? [] as $x)if((int)$x['id']===$editId)$extra=$x;
?>
<link rel="stylesheet" href="<?=access_h(workspace_asset_url('assets/css/stays.css'))?>"><script src="<?=access_h(workspace_asset_url('assets/js/stays.js'))?>" defer></script>
<?php $stayWorkspace=in_array($section,['board','folios','activity','departures'],true); ?>
<?php if(!$stayWorkspace):?><div class="app-page-heading-row">
    <div><h1><?=access_h($layoutTitle)?></h1>
    <?php if(in_array($section,['rooms','areas'],true)):?><p>Room details, maintenance and weekly property audits.</p><?php endif?></div>
</div><?php endif?>
<?php if($stayWorkspace&&!$isolatedCheckin)require __DIR__.'/workspace-nav.php';?>
<?php if(in_array($section,['rooms','areas'],true))require __DIR__.'/property-nav.php';?>
<?php if($section==='extras')require dirname(__DIR__).'/partials/extras-nav.php';?>
<div id="stay-workspace" tabindex="-1"></div>
<?php if($stayWorkspace):?>
<section class="card stay-payment-recovery" data-payment-recovery="<?=access_h(stay_url())?>" hidden>
<h2>Check Your Last Payment</h2>
<p data-payment-recovery-message role="status">There may be a payment awaiting confirmation. Check before entering it again.</p>
<div class="stay-actions"><button type="button" class="button secondary" data-payment-check>Check Payment Status</button>
<a data-payment-account hidden>Open Guest Stay Account</a>
<button type="button" class="button secondary" data-payment-dismiss hidden>Dismiss</button></div>
</section>
<?php endif?>
<?php if($error):?><p class="error" id="stay-result" role="alert" tabindex="-1"><?=access_h($error)?></p><?php endif?>
<?php if($notice):?><p class="notice<?=$extraFeedback?' stay-extra-feedback':''?>" <?=$extraFeedback?'data-extra-feedback':''?> id="stay-result" role="status" data-receipt-operation="<?=access_h($noticeOperation??'')?>" tabindex="-1"><?=access_h($notice)?>
<?php if($guest&&str_starts_with($notice,'Guest checked in.')):?> Room <?=access_h($guest['room_name'])?>. <a href="<?=$url(['booking_id'=>$bookingId,'tab'=>'bill'])?>#stay-workspace">Open Guest Stay Account</a><?php endif?>
<?php if($bill&&!$bill['legacy_unknown']&&str_contains($notice,' Receipt #')):?> <?=$bill['balance']<0?'Overpayment':'Balance Due'?> <?=stay_money(abs($bill['balance']))?>.<?php endif?></p><?php endif?>
<?php if($guest):?>
<section class="card stay-guest"><p class="app-eyebrow">ROOM <?=access_h($guest['room_name'])?> · STAY #<?=(int)$guest['id']?></p><h2><?=access_h($guest['guest_name'])?></h2><div class="stay-details"><?php foreach(['Stay Type'=>$guest['stay_type'],'Mobile'=>$guest['contact'],'Gender'=>$guest['gender'],'Nationality'=>$guest['nationality'],'ID type'=>$guest['id_type'] ?? '', 'Identity Verified'=>!empty($guest['identity_verified'])?'Yes':'Not Recorded','Checked In'=>display_date($guest['checkin_at']).' UTC','Departure'=>$guest['checkout_at']?display_date($guest['checkout_at']).' UTC':($guest['status']==='active'?'Still In House':'Unknown · Departure Reported')] as $label=>$value):?><div><small><?=access_h(stay_label($label))?></small><strong><?=access_h($value ?: 'Not Recorded')?></strong></div><?php endforeach?></div><?php if($guest['identity_available']):?><p><a href="<?=access_h(access_url('guest-id.php?booking_id='.(int)$guest['id']))?>">Download Private ID Document</a></p><?php endif?></section>
<?php endif?>
<?php if($guest&&$bill)require __DIR__.'/departure-case.php';?>
<?php if($confirmation):?>
<dialog open class="app-confirmation" id="stay-action" data-stay-confirmation aria-labelledby="stay-confirmation-title" aria-describedby="stay-confirmation-description">
<div class="app-confirmation-content">
<button type="button" class="app-confirmation-close secondary" data-confirmation-dismiss aria-label="Cancel Confirmation">&times;</button>
<p class="app-eyebrow">PLEASE CONFIRM</p>
<h2 id="stay-confirmation-title"><?=$confirmation['action']==='clear_balance'?'Record Full Payment?':'Confirm Guest Departure?'?></h2>
<p><strong><?=access_h($confirmation['guest'])?></strong> · Room <?=access_h($confirmation['room'])?></p>
<p id="stay-confirmation-description"><?=$confirmation['action']==='clear_balance'?'Record '.stay_money($confirmation['balance']).' as received via '.(($confirmation['input']['method'] ?? 'cash')==='momo'?'MoMo':'Cash').'?':'Record departure now and send the room for cleaning?'?></p>
<?php if($confirmation['action']==='checkout'&&$confirmation['balance']>0):?><p>Outstanding <?=stay_money($confirmation['balance'])?> will remain under Eugene’s authorization<?php if(!empty($bill['approval_record']['approved_at'])):?> recorded <?=access_h(display_date($bill['approval_record']['approved_at']))?> UTC<?php endif?>.</p><?php endif?>
<form method="post" data-confirmation-submit <?=$confirmation['action']==='clear_balance'?'data-payment-final':''?>>
<input type="hidden" name="csrf" value="<?=access_h(access_csrf())?>"><input type="hidden" name="action" value="confirm_action"><input type="hidden" name="operation_key" value="<?=access_h($confirmation['operation_key'])?>"><input type="hidden" name="confirmation_token" value="<?=access_h($confirmation['token'])?>">
<div class="stay-actions"><a class="button secondary" data-confirmation-cancel autofocus href="<?=$url(['booking_id'=>$bookingId,'form'=>$confirmation['action']==='checkout'?'checkout':'clear_balance'])?>#stay-action">Go Back</a><button class="button" type="submit"><?=$confirmation['action']==='clear_balance'?'Yes, Record Full Payment':'Yes, Confirm Departure'?></button></div>
</form>
</div>
</dialog>
<?php endif?>

<?php if($form):?>
<section class="card stay-action" id="stay-action" tabindex="-1"><h2><?=access_h(match($form){'clean'=>'Cleaning Complete','inspect'=>'Inspect & Release Room','checkin'=>'Check In','recover_receipt'=>'Record Late Payment','report_departure'=>'Guest Left Without Approval',default=>stay_label(str_replace('_',' ',$form))})?></h2>
<?php if($guest&&in_array($form,['report_departure','secure_departure_room','review_departure','departure_followup','collect_outstanding'],true)):require __DIR__.'/departure-forms.php';?>
<?php elseif($form==='checkin' && $target && !\Tivoli\Service\StayService::checkInReady($target)):?>
<p role="status">Room <?=access_h($target['name'])?> is unavailable for check-in. Return to the Room Board to choose a ready room.</p>
<?php $link('Choose a Ready Room',['preparation'=>'ready']);?>
<?php elseif($form==='checkin' && $target):?>
<p><strong>Room <?=access_h($target['name'])?></strong></p>
<?php
$arrivalTypes=[];
if (\Tivoli\Service\StayService::checkInReady($target,'overnight')) $arrivalTypes['overnight']='Overnight';
if (\Tivoli\Service\StayService::checkInReady($target,'short')) $arrivalTypes['short']='Short Stay · Maximum Two Hours';
$arrivalType=$formValues['stay_type']??($tab==='short'?'short':'overnight');
if (!isset($arrivalTypes[$arrivalType])) $arrivalType=array_key_first($arrivalTypes);
$arrivalRate=$arrivalType==='short'?$target['short_rate']:$target['rate'];
?>
<form method="post" enctype="multipart/form-data" class="stay-form" data-checkin-payment data-confirm="Confirm the guest, room and payment received before recording check-in."
 data-room-label="<?=access_h($target['name'])?>" data-overnight-rate="<?=access_h($target['rate'])?>" data-short-rate="<?=access_h($target['short_rate'])?>">
<p>Selected Stay Rate <strong data-arrival-summary><?=stay_decimal($arrivalRate)?></strong></p>
<?php stay_fields('checkin',0,['room_id'=>$roomId,'expected_rate'=>$arrivalRate]);?>
<div class="stay-grid"><?php
stay_input('Guest Name','guest_name',$formValues['guest_name']??'',required:true);
stay_select('Stay Type','stay_type',$arrivalTypes,$arrivalType,true);
stay_input('Mobile Number','phone',$formValues['phone']??'','tel',true);
stay_select('Gender','gender',[''=>'Choose Gender','Female'=>'Female','Male'=>'Male','Other'=>'Other','Prefer not to say'=>'Prefer Not to Say'],$formValues['gender']??'',true);
stay_select('Nationality','nationality',stay_countries(),$formValues['nationality']??'Ghana',true);
?></div>
<details class="stay-optional"><summary>Guest ID Details (Optional)</summary><div class="stay-grid"><?php
stay_select('ID Type (Optional)','id_type',[''=>'Not Recorded','Ghana Card'=>'Ghana Card','Passport'=>'Passport','Driving licence'=>'Driving Licence','Voter ID'=>'Voter ID','Other'=>'Other']);
stay_input('Upload ID (Optional, PDF/JPEG/PNG up to 5 MB)','identity_file','','file',false,'accept=".pdf,.jpg,.jpeg,.png"');
?></div></details>
<h3>Room Payment Received</h3>
<p data-arrival-coverage><?=$arrivalType==='short'?'Fixed Short-Stay Session · Maximum Two Hours':'First Overnight Charge · Through Next Day at 12:00:00 UTC'?></p>
<p>Minimum Room Payment <strong data-arrival-rate><?=stay_decimal($arrivalRate)?></strong></p>
<div class="stay-grid"><?php
stay_input('Amount Received (GHS)','amount',$formValues['amount']??$arrivalRate,'text',true,'inputmode="decimal" maxlength="13"');
stay_select('Payment Method','payment_type',[''=>'Choose Cash or MoMo','cash'=>'Cash','momo'=>'MoMo'],$formValues['payment_type']??'',true);
stay_input('Payment Reference (Optional)','reference',$formValues['reference']??'','text',false,'maxlength="120"');
?></div>
<ul class="stay-reminders">
<li>Guest Identity Verified</li>
<li>I Have Received Payment For The Room</li>
<li>I Have Checked The Room And It Is Ready Before Issuing The Key</li>
</ul>
<p class="stay-help">Check-in and receipt are recorded together using the actual check-in time. Cash enters deposit tracking. Recorded MoMo payments count as successful.</p>
<div class="stay-actions stay-form-actions"><button class="button" type="submit" name="attest" value="1">Confirm Check-In &amp; Record Payment</button><?php $link('Cancel',['preparation'=>'ready']);$cancelRendered=true;?></div></form>
<?php elseif($form==='recover_receipt'):?>
<?php if(!empty($view['can_recover_receipt'])):?>
<p>Record a payment already received for this checked-out stay. The receipt posts on today's open accounting date, <strong><?=access_h(display_date(gmdate('Y-m-d')))?> UTC</strong>, and stays linked to the original activity date you enter.</p>
<p>Outstanding balance <strong><?=stay_money($bill['balance'])?></strong></p>
<form method="post" class="stay-form">
<?php stay_fields('recover_receipt',$bookingId);
stay_input('Amount received (GHS)','amount','','text',true,'inputmode="decimal"');
stay_select('Payment method','method',['cash'=>'Cash','momo'=>'MoMo']);
stay_input('Original activity date','original_activity_date','','date',true);
stay_input('Receipt or transaction reference','reference','','text',true);
stay_textarea('Recovery reason','late_reason','',true);?>
<div class="stay-actions stay-form-actions"><button class="button" type="submit">Record Recovered Receipt</button>
<?php $link('Cancel',['booking_id'=>$bookingId]);$cancelRendered=true;?></div></form>
<?php else:?><p>Recovery requires the designated finance owner and a checked-out stay with a verified outstanding balance.</p><?php endif?>
<?php elseif($guest && in_array($form,['payment','clear_balance','extra','checkout','incidental','review_incidental','refund'],true)): $summary($bill);?>
<?php if($bill['legacy_unknown']):?><p class="error">Historical room rate is unknown. No current tariff will be substituted. An evidenced review is required before financial actions.</p>
<?php elseif($guest['status']!=='active'&&$form!=='refund'&&!($form==='review_incidental'&&$bill['provisional'])):?><p>This guest has checked out. Payments and purchases are closed.</p>
<?php elseif($form==='checkout'):?>
<?php if($bill['balance']>0):?><p><?=$bill['approval']?'Eugene authorized departure with this balance. The debt remains payable.':'Checkout is blocked while this balance is due. Clear the balance or ask Eugene to authorize departure.'?></p>
<?php if($canAuthorizeDeparture&&!$bill['approval']):?><form method="post" class="stay-form" data-confirm="Authorize departure with the displayed debt? This does not record a payment."><?php stay_fields('allow_checkout',$bookingId,['expected_balance'=>(string)$bill['balance']]);stay_textarea('Authorization Reason','reason','',true);?><button class="button secondary">Authorize Departure With Debt</button></form><?php endif;endif?>
<form method="post" class="stay-form"><?php stay_fields('checkout',$bookingId);?>
<ul class="stay-reminders"><li>Guest Bill, Extras And Payment Evidence Reviewed</li><li>Keys Recovered / Access Disabled</li></ul>
<?php stay_textarea('Departure / handover note (optional)','reason');?>
<div class="stay-actions stay-form-actions">
<?php if($bill['balance']<=0||$bill['approval']):?><button class="button" type="submit" name="attest" value="1">Confirm Departure</button><?php endif;
if($bill['balance']>0)$link('Clear Outstanding Balance',['booking_id'=>$bookingId,'form'=>'clear_balance'],'');
$link('Cancel',['booking_id'=>$bookingId]);$cancelRendered=true;?>
</div></form>
<?php else:?>
<form method="post" class="stay-form" <?=$form==='refund'?'data-confirm="Record this completed refund once? This records money actually returned; it does not send money."':''?><?php if($form==='payment'):?> data-confirm="Record this payment?" data-stay-payment data-room-label="<?=access_h($guest['room_name'])?>"<?php endif?>><?php stay_fields($form,$bookingId,['id'=>$editId,'incidental_id'=>$editId]);?>
<?php if(in_array($form,['payment','clear_balance'],true)):if($form==='payment'):stay_input('Amount received (GHS)','amount','','text',true,'inputmode="decimal"');else:?><p>Receive <?=stay_money($bill['balance'])?> to clear this balance. Checkout is a separate step.</p><?php endif;stay_select('Payment method','method',['cash'=>'Cash','momo'=>'MoMo']);stay_input('Payment reference (optional)','reference');?>
<?php elseif($form==='extra'): $opts=[];foreach($view['extras'] as $x)if($x['active'])$opts[$x['id']]=$x['name'].' · '.stay_decimal($x['price']).($x['tracked']?' · '.$x['available_qty'].' available':' · service');stay_select('Extra','extra_id',$opts,'',true);stay_input('Quantity','qty','1','number',true,'min="0.01" step="0.01"');stay_select('Settlement','settlement',['now'=>'Pay now','room'=>'Charge to room'],'now');stay_select('Payment method','method',['cash'=>'Cash','momo'=>'MoMo']);stay_input('Payment reference (optional)','reference');?>
<?php elseif($form==='refund'): $receipts=[];foreach($view['payments'] as $p)$receipts[$p['id']]='#'.$p['id'].' · '.stay_decimal($p['amount']).' · '.$p['method'];stay_select('Original receipt','payment_id',$receipts,'',true);stay_input('Refund amount (GHS)','amount','','text',true);stay_select('Actual Refund Method','method',['momo'=>'MoMo','cash'=>'Cash']);stay_input('Actual Refund Time (UTC)','actual_at',gmdate('Y-m-d\TH:i:s'),'datetime-local',true,'step="1"');stay_input('Actual Payer','actual_payer','','text',true);stay_input('Completed Refund Reference / Evidence','proof','','text',true);stay_check('I verified that this refund was actually completed. This does not send money.','completed',true);stay_textarea('Refund reason','reason','',true);?><p>Record an actual completed Cash or MoMo refund. Cash refunds remain separate from the full cash-sales deposit obligation. The original receipt and closed-period totals remain retained.</p><?php elseif($form==='incidental'):stay_input('Description','description','','text',true);stay_input('Amount (GHS)','amount','','text',true);stay_textarea('Evidence / reason','reason','',true);?><p>Not counted until a different administrator approves it.</p>
<?php else:stay_select('Decision','decision',['approved'=>'Approve','rejected'=>'Reject']);stay_textarea('Comment (Optional)','reason');endif?>
<?php if($form==='payment'&&(new \Tivoli\Service\FinanceService(access_service()))->canPostLate($actor)):?>
<details><summary>Record Late Activity</summary><p>Use the original date from the closed period. The entry posts today if today's accounting period is open. Include the payment or refund reference.</p>
<?php stay_input('Original activity date','original_activity_date','','date');stay_textarea('Late posting reason','late_reason');?>
</details>
<?php endif?>
<?php $cancelRendered=true;?><div class="stay-actions stay-form-actions"><button class="button" type="submit"><?=access_h(match($form){
    'clear_balance'=>'Record Full Payment','payment'=>'Record Payment','extra'=>'Post Extra','refund'=>'Record Refund',
    'incidental'=>'Submit Incidental Charge','review_incidental'=>'Save Charge Decision',
})?></button><?php $link('Cancel',['booking_id'=>$bookingId]);?></div></form>
<?php endif?>
<?php elseif($isAdmin && $form==='clean' && $target):?>
<p>Room <strong><?=access_h($target['name'])?></strong> · Complete all items, then submit for inspection. For conditional tasks, check the item after reviewing it and taking any required action.</p>
<p class="stay-help"><strong>Cleaning rule:</strong> Follow product labels and contact times. Never mix cleaning chemicals.</p>
<p>Report problems immediately. <a href="<?=$url(['section'=>'rooms','form'=>'fault','room_id'=>$roomId])?>#stay-action">Open Room Fault Report</a>. Do not mark an unresolved problem as completed.</p>
<form method="post" class="stay-form" data-cleaning-wizard>
<?php stay_fields('clean',0,['room_id'=>$roomId,'checklist_revision'=>$cleaningChecklist['revision']]);?>
<p data-cleaning-progress role="status" aria-live="polite">All checklist items are required. Notes are optional.</p>
<?php foreach($cleaningChecklist['sections'] as $section):if(!$section['items'])continue;?>
<fieldset class="cleaning-step" data-cleaning-step><legend><?=access_h($section['title'])?></legend>
<?php foreach($section['items'] as $item):?><label class="cleaning-item"><input type="checkbox" name="done_<?=access_h($item['id'])?>" value="1" required <?=($formValues['done_'.$item['id']] ?? '')==='1'?'checked':''?>><span><?=access_h($item['text'])?></span></label><?php endforeach?>
</fieldset><?php endforeach?>
<div data-cleaning-review><?php stay_textarea('Comment (Optional)','reason',$formValues['reason'] ?? '');?>
<button class="button" type="submit">Complete Cleaning & Submit For Inspection</button></div>
<div class="stay-actions" data-cleaning-controls hidden><button class="button secondary" type="button" data-cleaning-back>Back</button><button class="button" type="button" data-cleaning-next>Next</button></div>
</form>
<?php elseif($isAdmin && $form==='inspect' && $target):?>
<p>Room <strong><?=access_h($target['name'])?></strong></p>
<p class="stay-help">If any check fails, keep the room unavailable and report the issue. A room with an unresolved Critical fault cannot be released.</p>
<form method="post" class="stay-form" data-inspection-checklist>
<?php stay_fields('inspect',0,['room_id'=>$roomId,'checklist_revision'=>$inspectionChecklist['revision']]);?>
<?php foreach($inspectionChecklist['sections'] as $section):?><fieldset class="cleaning-step"><legend><?=access_h($section['title'])?></legend>
<?php foreach($section['items'] as $item):?><label class="cleaning-item"><input type="checkbox" name="done_<?=access_h($item['id'])?>" value="1" required <?=($formValues['done_'.$item['id']] ?? '')==='1'?'checked':''?>><span><?=access_h($item['text'])?></span></label><?php endforeach?>
</fieldset><?php endforeach?>
<?php stay_textarea('Comment (Optional)','reason',$formValues['reason'] ?? '');?>
<button class="button" type="submit">Sign Off & Release Room</button>
</form>
<?php elseif($isAdmin && in_array($form,['area_audit','area_followup'],true)):require __DIR__.'/area-form.php';?>
<?php elseif(($form==='extra_save'&&!empty($canEditExtras))||($isAdmin&&in_array($form,['room_save','fault','repair','release_fault','clean','inspect','room_audit'],true))):?>
<form method="post" enctype="multipart/form-data" class="stay-form"><?php stay_fields($form,0,['id'=>$editId,'room_id'=>$roomId?:$editId,'extra_id'=>$editId,'fault_id'=>$editId]);?>
<?php if($form==='extra_save'):?><div class="stay-grid"><?php stay_input('Name','name',$extra['name'] ?? '',required:true);$categories=[''=>'Choose a category'];foreach($extraCategories as $categoryName=>$active){if($active)$categories[$categoryName]=(string)$categoryName;}if($extra&&!isset($categories[$extra['category']]))$categories[$extra['category']]=$extra['category'].' (Inactive)';stay_select('Category','category',$categories,$extra['category'] ?? (isset($categories['General'])?'General':''),true);stay_input('Selling price (GHS)','price',$extra['price'] ?? '',required:true);stay_input('Low-stock alert level','low_stock',$extra['low_stock'] ?? '3','number',false,'min="0" step="0.01"');?></div><?php if(!$extra||!$extra['configured'])stay_check('Stocked product (leave unchecked for a service)','tracked',false,true);stay_check('Available for new sales','active',false,!$extra||$extra['active']);?>
<?php elseif(in_array($form,['stock_in','stock_count'],true)):stay_input($form==='stock_in'?'Quantity received':'Physical stock count','qty',$form==='stock_count'?($extra['stock_qty'] ?? '0'):'1','number',true,'min="0" step="0.01"');?>
<?php elseif($form==='room_save'): $room=null;foreach($view['rooms'] as $r)if((int)$r['id']===$editId)$room=$r;?><div class="stay-grid"><?php stay_input('Room name','name',$room['name'] ?? '',required:true);stay_select('Room Type','type',$service->roomTypes(),$room['type'] ?? 'Standard',true);stay_input('Standing overnight rate (GHS)','rate',$room['rate'] ?? '',required:true);stay_input('Standing short-stay rate (GHS)','short_rate',$room['short_rate'] ?? '0.00',required:true);?></div><?php stay_check('Offer short stays','enabled',false,!empty($room['short_enabled']));?><p>Rates apply every day until changed. Existing stays retain their captured rates.</p>
<?php elseif($form==='fault' || $form==='room_audit'): $opts=[];foreach($view['rooms'] as $r)$opts[$r['id']]=$r['name'];stay_select('Room','room_id',$opts,$roomId,true);stay_input($form==='room_audit'?'Action Owner / Due Date (Optional)':'Action Owner / Due Date','owner','',required:$form!=='room_audit');if($form==='fault'){stay_select('Priority','priority',['Routine'=>'Routine','Urgent'=>'Urgent','Critical'=>'Critical']);?><p class="stay-help">Critical blocks the room from check-in until the fault is resolved. Routine and Urgent faults leave room availability unchanged.</p><?php }else stay_select('Result','result',['pass'=>'Pass','corrective'=>'Corrective action','unsafe'=>'Safety / saleability failure']);endif;if(in_array($form,['inspect','release_fault'],true))stay_check('Cleanliness, Safety And Equipment Verified; No Unresolved Issue','inspection_verified',true);if(!in_array($form,['extra_save','stock_in'],true))stay_textarea($form==='fault'?'Describe Fault (Optional)':(in_array($form,['clean','inspect','room_save','repair','room_audit','stock_count'],true)?'Comment (Optional)':'Reason / Findings / Evidence'),'reason',$formValues['reason'] ?? '',!in_array($form,['clean','inspect','room_save','fault','repair','room_audit','stock_count'],true));if($form==='fault'):?><p class="stay-help">Only Critical priority blocks new check-ins. Use Critical for a safety or room-saleability issue.</p><?php endif;if(in_array($form,['fault','repair'],true))stay_input($form==='repair'?'Evidence (Optional, PDF/JPEG/PNG Up To 5 MB)':'Attachment (Optional, PDF/JPEG/PNG Up To 5 MB)','fault_file','','file',false,'accept=".pdf,.jpg,.jpeg,.png"');$cancelRendered=true;?><div class="stay-actions stay-form-actions"><button class="button" type="submit"><?=access_h(match($form){
    'extra_save'=>'Save Extra','stock_in'=>'Record Stock Receipt','stock_count'=>'Save Stock Count','room_save'=>'Save Room',
    'fault'=>'Report Fault','repair'=>'Record Repair','release_fault'=>'Release Room','clean'=>'Record Cleaning Complete',
    'inspect'=>'Confirm Inspection & Release Room','room_audit'=>'Record Room Audit',
})?></button><?php $link('Cancel',$guest?['booking_id'=>$bookingId]:(['section'=>$section]+($section==='rooms'?['tab'=>$tab]:[])+$catalogueQuery));?></div></form>
<?php else:?><p>Select a valid room or ask an administrator to perform this action.</p><?php endif?>
<?php if(!$cancelRendered):?><div class="stay-actions"><?php $link('Cancel',$guest?['booking_id'=>$bookingId]:(['section'=>$section]+($section==='rooms'?['tab'=>$tab]:[])));?></div><?php endif?></section>
<?php endif?>
<?php if($guest && $bill):?>
<section class="card"><h2><?=$bill['provisional']?'Provisional Stay Bill':($guest['status']==='checked_out'?'Final Stay Bill':'Stay Bill')?></h2><?php $summary($bill);?>
<?php if($guest['status']==='active'&&!$bill['legacy_unknown']):
if($guest['stay_type']==='overnight'):
$nextCharge=(new DateTimeImmutable($guest['checkin_at'],new DateTimeZone('UTC')))->modify('+'.$bill['nights'].' days')->setTime(12,0);?>
<p class="stay-help">Next Room Charge <?=stay_decimal($guest['rate_per_night'])?> After <?=access_h(display_date($nextCharge->format('Y-m-d H:i:s')))?> UTC</p>
<?php elseif($guest['due_at']):?><p class="stay-help">Short-Stay Limit <?=access_h(display_date($guest['due_at']))?> UTC<?=$guest['due_at']<gmdate('Y-m-d H:i:s')?' · Overrun Requires Review':''?></p><?php endif;endif?>
<?php if($guest['status']==='checked_out'):?><p>Departure <?=$guest['checkout_at']?access_h(display_date($guest['checkout_at'])).' UTC':'Unknown · Awaiting Owner Review'?><?php if($bill['checkout_balance']!==null):?> · Balance at Checkout <?=stay_money($bill['checkout_balance'])?><?php endif?></p><?php endif?>
<?php if($guest['status']==='checked_out'):?><section class="stay-preparation" aria-label="Room Preparation"><h2>Room Preparation</h2><p class="stay-help">Cleaning linked to this stay’s departure.</p><?php if(!empty($stayPreparation)):?><p><?=access_h(match($stayPreparation['status']){'cleaning'=>'Cleaning In Progress','inspection'=>'Awaiting Inspection','released'=>'Inspection Passed','returned'=>'Returned For Cleaning',default=>'Earlier Attempt'})?></p><a class="button secondary" href="<?=access_h(stay_url(['section'=>'housekeeping','id'=>$stayPreparation['id'],'from_stay'=>$bookingId]))?>#housekeeping-action">View Cleaning Record</a><?php else:?><p>No linked cleaning record yet. Use the Room Board for current preparation.</p><?php endif?></section><?php endif?>
<?php if($bill['recovered']>0):?><p>Balance at checkout <?=stay_money($bill['checkout_balance'])?> · Recovered after checkout <?=stay_money($bill['recovered'])?></p><?php endif?>
<div class="stay-actions"><?php if(!empty($view['can_recover_receipt']))$link('Record Late Payment',['booking_id'=>$bookingId,'form'=>'recover_receipt'],'');if($guest['status']==='active'&&!$bill['legacy_unknown']):$link('Add Extra',['booking_id'=>$bookingId,'form'=>'extra'],'');$link('Add Incidental',['booking_id'=>$bookingId,'form'=>'incidental']);$link('Record Payment',['booking_id'=>$bookingId,'form'=>'payment']);$link('Check Out',['booking_id'=>$bookingId,'form'=>'checkout']);endif;if($guest['status']==='active')$link('Report Unauthorised Departure',['booking_id'=>$bookingId,'form'=>'report_departure']);if($canAuthorizeDeparture&&$guest['status']==='checked_out'&&$bill['checkout_balance']!==null&&$bill['balance']>0&&!$bill['legacy_unknown'])$link('Recover Outstanding',['booking_id'=>$bookingId,'form'=>'collect_outstanding']);if($canAuthorizeDeparture&&$bill['balance']<0&&!$bill['legacy_unknown'])$link('Record Overpayment Refund',['booking_id'=>$bookingId,'form'=>'refund']);?><a class="button secondary" href="<?=access_h(access_url('financial-corrections.php?area=charges&booking_id='.$bookingId))?>#charge-action">Correct Charge / Review Refund</a></div><nav class="app-section-nav workspace-tab-strip" aria-label="Guest account sections"><?php foreach(['bill'=>'Bill','payments'=>'Payments','statement'=>'Statement Preview']+($isAdmin?['activity'=>'Stay Activity']:[]) as $key=>$label):?><a href="<?=$url(['booking_id'=>$bookingId,'tab'=>$key])?>#stay-bill" <?=($tab===$key||$key==='bill'&&!in_array($tab,['payments','statement','activity'],true))?'aria-current="page"':''?>><?=access_h(stay_label($label))?></a><?php endforeach?></nav><div id="stay-bill" tabindex="-1">
<?php if($tab==='payments'):?><div class="scroll"><table><thead><tr><th>Receipt / Time</th><th>Method / Reference</th><th>Received</th><th>Cashier</th></tr></thead><tbody><?php foreach($view['payments'] as $p):?><tr><td>#<?=(int)$p['id']?><br><?=access_h(display_date($p['created_at']))?>
<?php if(!empty($p['recovered_activity_date'])):?><br><small><?=($p['recovery_mode']??'')==='new_collection'?'Outstanding Collection · Received':'Recovered Receipt · Original Date'?> <?=access_h(display_date($p['recovered_activity_date']))?></small><?php endif?></td><td><?=access_h($p['method'])?><br><?=access_h($p['reference_no'] ?? $p['note'])?></td><td><?=stay_decimal($p['amount'])?><?php if($p['position']['last_id']):?><br><small>Current Effective <?=stay_money($p['position']['amount'])?> · <?=access_h(strtoupper($p['position']['method']).' / '.$p['position']['reference'])?></small><?php endif;if($canAuthorizeDeparture||(int)($p['actor_id']??0)===$actor):?><br><a href="<?=access_h(access_url('financial-corrections.php?payment_id='.(int)$p['id']))?>#correction-action">Request Correction</a><?php if(isset($view['finance_payment_cursor'])&&(int)$p['id']>$view['finance_payment_cursor']
    &&(int)($p['actor_id']??0)>0&&$p['position']['method']==='momo'&&$p['position']['amount']>0):?><br><a href="<?=access_h(access_url('finance.php?page=discrepancies&type=payment&source_id='.(int)$p['id']))?>#discrepancy-action">Report / Resolve Difference</a><?php endif;endif?></td><td><?=access_h($p['cashier_name'] ?? (isset($p['actor_id'])?'User #'.(int)$p['actor_id']:'Historical Actor Unknown'))?></td></tr><?php endforeach?></tbody></table></div><?php foreach($view['refunds'] ?? [] as $refund):$refundMeta=$refund['refund_metadata']?json_decode($refund['refund_metadata'],true,512,JSON_THROW_ON_ERROR):[];?><p>Refund #<?=(int)$refund['id']?> · <?=stay_money($refund['amount_minor'])?> · original receipt #<?=(int)$refund['payment_id']?> · <?=access_h($refund['proof'])?> · <?=access_h(isset($refundMeta['method'])?ucfirst($refundMeta['method']):'Historical Method Unknown')?> · Actual Time <?=access_h(!empty($refundMeta['actual_at'])?display_date($refundMeta['actual_at']).' UTC':'Not Recorded')?> · Actual Payer <?=access_h($refundMeta['actual_payer']??'Not Recorded')?> · Recorded By <?=access_h($refund['recorded_by_name']??'Historical Actor Unknown')?></p><?php endforeach?><?php foreach($view['charge_corrections']??[] as $correction):?><p>Charge Adjustment #<?=(int)$correction['id']?> · <?=access_h($correction['data']['label'])?> · <?=stay_money($correction['amount_minor'])?> · <?=access_h(display_date($correction['created_at']))?> UTC · No Money Moved</p><?php endforeach?><?php foreach($view['receipt_corrections']??[] as $correction):$cd=json_decode($correction['payload'],true);?><p>Receipt Adjustment ADJ-<?=(int)$correction['id']?> · Original Receipt #<?=(int)$cd['payment_id']?> · <?=stay_money($cd['delta'])?> · <?=access_h(display_date($correction['created_at']))?> UTC · No Money Moved</p><?php endforeach?><p class="stay-help">Recorded MoMo payments count as successful.</p>
<?php elseif($tab==='activity'&&$isAdmin):foreach($view['activity'] as $e):?><article class="stay-event"><strong><?=access_h($e['action'])?></strong><small><?=access_h(display_date($e['created_at']))?> UTC</small><pre><?=access_h($e['payload'])?></pre></article><?php endforeach?>
<?php else:if($tab==='statement'):?><h3><?=$bill['provisional']?'Provisional Guest Statement':($guest['status']==='checked_out'?'Final Guest Statement':'Statement Preview')?> · Stay #<?=$bookingId?></h3><p>Tivoli Guesthouse · <?=access_h($guest['guest_name'])?> · Room <?=access_h($guest['room_name'])?></p><p class="stay-help">Itemised statement, not a certified fiscal tax invoice.</p><?php endif?><div class="scroll"><table><thead><tr><th>Charge</th><th>Quantity / Basis</th><th>Amount</th></tr></thead><tbody><tr><td><?=$guest['stay_type']==='short'?'Short-stay session':'Overnight accommodation'?></td><td><?=$guest['stay_type']==='short'?'Maximum two hours':(int)$bill['nights'].' legacy billable nights'?></td><td><?=$bill['legacy_unknown']?'Unknown historical rate':stay_money($view['original_room_charge']??$bill['room'])?></td></tr><?php foreach($view['lines'] as $line):?><tr><td><?=access_h($line['description'])?></td><td><?=access_h($line['qty'])?> × <?=stay_decimal($line['unit_price'])?></td><td><?=stay_money(\Tivoli\Domain\Stay\StayRules::line(\Tivoli\Domain\Stay\StayRules::quantity($line['qty']),\Tivoli\Domain\Stay\StayRules::minor($line['unit_price'])))?></td></tr><?php endforeach;foreach($view['incidentals'] as $q):if($q['status']!=='approved')continue;?><tr><td><?=access_h($q['description'])?></td><td>Approved Incidental</td><td><?=stay_money($q['amount_minor'])?></td></tr><?php endforeach?></tbody></table></div><?php foreach($view['charge_corrections']??[] as $change):?><p>Charge Adjustment #<?=(int)$change['id']?> · <?=access_h($change['data']['label'])?> · <?=stay_money($change['amount_minor'])?> · <?=access_h(display_date($change['created_at']))?> UTC · Current-period adjustment; no money moved.</p><?php endforeach?><?php if($tab!=='statement'):foreach($view['incidentals'] as $q):if($q['status']!=='pending')continue;?><div class="stay-event"><?=access_h($q['description'])?> · <?=stay_money($q['amount_minor'])?> · Pending approval <?php if($isAdmin&&(int)$q['submitted_by']!==$actor)$link('Review',['booking_id'=>$bookingId,'form'=>'review_incidental','id'=>$q['id']]);?></div><?php endforeach;endif;endif?></div></section>
<?php elseif($section==='departures'):require __DIR__.'/departure-register.php';?>
<?php elseif($section==='folios'):$accountFilters=$folioDirectory['filters'];?>
<section class="card" id="stay-accounts" tabindex="-1"><h2>Guest Stay Account</h2>
<form method="get" action="<?=access_h(stay_url())?>#stay-accounts" class="stay-form"><input type="hidden" name="section" value="folios"><div class="stay-grid">
<?php stay_input('From','start',$accountFilters['start'] ?? '', 'date',true);stay_input('To','end',$accountFilters['end'] ?? '', 'date',true);?>
</div><div class="stay-actions"><button class="button" type="submit">Apply Filter</button><a class="button secondary" href="<?=$url(['section'=>'folios'])?>#stay-accounts">Current Week</a><a class="button secondary" href="<?=$url(['section'=>'folios','range'=>'all'])?>#stay-accounts">All Dates</a></div></form>
<p class="stay-help"><?=isset($accountFilters['range'])?'Showing all dates.':'Showing stays overlapping '.access_h(display_date($accountFilters['start'] ?? '')).' to '.access_h(display_date($accountFilters['end'] ?? '')).'.'?> Week runs Monday–Sunday. Dates and times are UTC.</p>
<div class="scroll"><table><thead><tr><th>Guest / Account</th><th>Room / Stay Type</th><th>Check-In (UTC)</th><th>Check-Out (UTC)</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach($folioDirectory['rows'] as $entry):?><tr><td><?=access_h($entry['guest_name'])?><br><small>Stay #<?=(int)$entry['id']?></small></td><td><?=access_h($entry['room_name'])?><br><?=access_h(stay_label($entry['stay_type']))?></td><td><?=access_h(display_date($entry['checkin_at']))?></td><td><?=access_h(display_date($entry['checkout_at']) ?: ($entry['status']==='active'?'Still In House':'Not Recorded'))?></td><td><?=access_h(stay_label(str_replace('_',' ',$entry['status'])))?></td><td><?php $link('Show Details',['section'=>'folios','booking_id'=>$entry['id']]);?></td></tr>
<?php endforeach;if(!$folioDirectory['rows']):?><tr><td colspan="6">No guest stays match these dates.</td></tr><?php endif?></tbody></table></div>
<?php if($folioDirectory['next_before']):?><a class="button secondary" href="<?=$url(['section'=>'folios','before'=>$folioDirectory['next_before']]+$accountFilters)?>#stay-accounts">Older Guest Stays</a><?php endif?></section>
<?php elseif($section==='activity'):?>
<section class="card"><h2>Operational Activity</h2><p class="stay-help"><?=$isAdmin?'Occupancy, payment, extras and room-control actions from all staff.':'Your own occupancy, payment, extras and room-control actions.'?> Read-access events remain recorded separately in the audit trail.</p><div class="scroll"><table><thead><tr><th>When (UTC)</th><th>Staff Member</th><th>Action</th><th>Record</th><th>Outcome</th></tr></thead><tbody><?php foreach($operationalActivity['rows'] as $event):?><tr><td><?=access_h(display_date($event['created_at']))?></td><td><?=access_h($event['actor_name'] ?? 'System / Unknown Historical Actor')?><?php if($event['actor_id']):?><br><small>User #<?=(int)$event['actor_id']?></small><?php endif?></td><td><?=access_h(stay_event_label($event['action']))?></td><td><?php if($event['target_type']==='booking'&&ctype_digit((string)$event['target_id'])):?><a href="<?=$url(['booking_id'=>$event['target_id'],'tab'=>$isAdmin?'activity':'bill'])?>#stay-bill">Stay #<?=(int)$event['target_id']?></a><?php else:$metadata=json_decode($event['payload'],true);$metadata=is_array($metadata)?$metadata:[];$record='Workspace';if(isset($metadata['room_id'])&&is_scalar($metadata['room_id'])){foreach($view['rooms'] as $room)if((int)$room['id']===(int)$metadata['room_id'])$record='Room '.$room['name'];}elseif(isset($metadata['extra_id'])&&is_scalar($metadata['extra_id'])){foreach($view['extras'] as $item)if((int)$item['id']===(int)$metadata['extra_id'])$record=$item['name'];}elseif($event['target_id']){$record=$event['target_id'];}?><?=access_h($record)?><?php endif?></td><td><?=access_h(stay_label($event['outcome']))?></td></tr><?php endforeach;if(!$operationalActivity['rows']):?><tr><td colspan="5">No operational events on this page.</td></tr><?php endif?></tbody></table></div><?php if($operationalActivity['next_before'])$link('Older Activity',['section'=>'activity','before'=>$operationalActivity['next_before']]);?></section>
<?php elseif($section==='areas'):if($isAdmin)require __DIR__.'/areas.php';?><?php if(!$isAdmin):?><p>Administrator access is required for area audits.</p><?php endif?>
<?php elseif($section==='extras'):?>
<section class="card"><?php if($tab==='stock'):?><h2>Stock Movements</h2><div class="scroll"><table><thead><tr><th>Item</th><th>Type / quantity</th><th>Actor / time</th><th>Reference / reason</th></tr></thead><tbody><?php foreach($view['movements'] ?? [] as $m):?><tr><td><?=access_h($m['extra_name'])?></td><td><?=access_h($m['movement_type'])?> · <?=access_h($m['qty'])?></td><td><?=isset($m['created_by'])?'User #'.(int)$m['created_by']:'Unknown historical actor'?><br><?=access_h(display_date($m['created_at']))?></td><td><?=access_h($m['ref_type'])?> #<?=access_h($m['ref_id'])?><br><?=access_h($m['note'])?></td></tr><?php endforeach?></tbody></table></div><?php else:require __DIR__.'/extras-catalogue.php';?><?php endif?></section>
<?php elseif($section==='rooms'):?>
<section class="card">
<?php if($tab==='faults'):?><div class="stay-actions"><h2>Out-Of-Order Log</h2><?php if($isAdmin)$link('Report Fault',['section'=>'rooms','form'=>'fault'],'');?></div><?php foreach($view['faults'] as $f):?><article class="stay-event"><h3>Room <?=access_h($f['room_name'])?> · <?=access_h(stay_label(str_replace('_',' ',$f['status'])))?> · <?=access_h($f['priority'])?></h3><p><?=access_h($f['reason'])?> · <?=access_h($f['owner'])?></p><?php if($isAdmin&&!empty($f['attachment_available'])):?><p><a href="<?=access_h(access_url('fault-attachment.php?fault_id='.(int)$f['id']))?>">Download Attachment</a></p><?php endif?><?php if($isAdmin&&!empty($f['repair_attachment_available'])):?><p><a href="<?=access_h(access_url('fault-attachment.php?fault_id='.(int)$f['id'].'&kind=repair'))?>">Download Repair Evidence</a></p><?php endif?><?php if($isAdmin&&$f['status']!=='closed')$link($f['status']==='open'?'Record Repair':'Complete Repair',['section'=>'rooms','form'=>'repair','id'=>$f['id']]);?></article><?php endforeach?>
<?php elseif($tab==='audits'):?><div class="stay-actions"><h2>Weekly Room Audit</h2><?php if($isAdmin)$link('Audit Room',['section'=>'rooms','form'=>'room_audit'],'');?></div><?php foreach($view['audits'] as $a):?><article class="stay-event"><strong>Room <?=access_h($a['room_name'])?> · <?=access_h($a['result'])?></strong><p><?=access_h($a['reason'])?> · <?=access_h($a['owner'])?></p><small><?=access_h(display_date($a['created_at']))?> UTC</small></article><?php endforeach?>
<?php else:?><div class="stay-actions"><h2>Room Master & Standing Rates</h2><?php if($isAdmin)$link('Add Room',['section'=>'rooms','form'=>'room_save'],'');?></div><div class="scroll"><table><thead><tr><th>Room</th><th>Overnight / Short Stay</th><th>Status / Readiness</th><th>Action</th></tr></thead><tbody><?php foreach($view['rooms'] as $r):?><tr><td><?=access_h($r['name'])?> · <?=access_h($r['type'])?></td><td><?=stay_decimal($r['rate'])?> / <?=stay_decimal($r['short_rate'])?></td><td><?=access_h(stay_label($r['status']))?> · <?=access_h(stay_label(str_replace('_',' ',$r['ready_state'])))?></td><td><?php if($isAdmin):$link('Edit Room',['section'=>'rooms','form'=>'room_save','id'=>$r['id']]);endif?></td></tr><?php endforeach?></tbody></table></div><?php endif?></section>
<?php elseif(!$isolatedCheckin):?>
<?php
$preparationFilter=$preparationFilter??'all';
$preparation=$preparation??[];
$boardRooms=$view['rooms']??[];
?>
<section class="stay-board-pane" aria-label="Room Board">
<?php require __DIR__.'/preparation-nav.php';?>
<form method="get" action="<?=$url()?>#stay-workspace" class="stay-board-search" data-room-search-panel>
<label data-room-search-field hidden>Find a Room or Guest <input type="search" data-room-search aria-controls="stay-room-grid" autocomplete="off"></label>
<label>Room Status<select name="preparation" data-room-filter aria-controls="stay-room-grid">
<?php foreach(['all'=>'All Rooms','ready'=>'Ready for Check-In','needs_cleaning'=>'Needs Cleaning','cleaning'=>'Cleaning In Progress','inspection'=>'Awaiting Inspection','blocked'=>'Blocked'] as $value=>$label):?>
<option value="<?=access_h($value)?>" <?=$preparationFilter===$value?'selected':''?>><?=access_h($label)?></option>
<?php endforeach?></select></label>
<noscript><button class="button secondary" type="submit">Apply Filter</button></noscript>
<p data-room-search-status role="status"></p>
</form>
<?php if($tab==='short'):?><p class="stay-help">Fixed short-stay rate. A room stays occupied until actual departure; two-hour overruns do not create an automatic fee.</p><?php endif?><div class="stay-rooms" id="stay-room-grid"><?php $visibleRoomCount=0;foreach($boardRooms as $r):$prep=$preparation[(int)$r['id']]??null;$ready=\Tivoli\Service\StayService::checkInReady($r);$state=$ready?'ready':($prep['state']??'');if($state==='ready'&&!$ready)$state='unavailable'; $matches=$preparationFilter==='all'||$state===$preparationFilter;if($matches)$visibleRoomCount++;?><article class="card stay-room" data-room-id="<?=(int)$r['id']?>" <?=$matches?'':'hidden'?> data-preparation="<?=access_h($state)?>"><div><h2><?=access_h($r['name'])?></h2><small><?=access_h($r['type'])?></small></div><span class="stay-badge"><?=access_h($prep?match($state){'ready'=>'Ready for Check-In','unavailable'=>'Unavailable','needs_cleaning'=>'Needs Cleaning','cleaning'=>'Cleaning In Progress','inspection'=>'Awaiting Inspection',default=>stay_label(str_replace('_',' ',$state))}:stay_label(str_replace('_',' ',$r['booking_id']?'occupied':$r['ready_state'])))?></span><p>Overnight <?=stay_decimal($r['rate'])?><br>Short stay <?=$r['short_enabled']?stay_decimal($r['short_rate']):'Not offered'?></p><?php if($r['booking_id']):?><h3><?=access_h($r['guest_name'])?></h3><p><?=access_h($r['stay_type'])?><?php if($r['due_at']):?> · Due <?=access_h(display_date($r['due_at']))?> UTC<?php endif?></p><?php $link('Show Details',['booking_id'=>$r['booking_id']],'');elseif($ready&&($tab!=='short'||\Tivoli\Service\StayService::checkInReady($r,'short'))):$link('Check In',['form'=>'checkin','room_id'=>$r['id'],'tab'=>$tab],'');else:
if(($prep['state']??'')==='needs_cleaning'):?>
<form method="post" action="<?=$url(['section'=>'housekeeping','room_id'=>(int)$r['id']])?>#housekeeping-action"
 data-start-cleaning="<?=$url(['section'=>'housekeeping','room_id'=>(int)$r['id'],'room_token'=>'1'])?>"
 data-start-confirm="Start physical cleaning for this unused, unblocked room?" hidden>
<?php $boardKey=bin2hex(random_bytes(16));foreach(['csrf'=>access_csrf(),'operation_key'=>$boardKey,'action'=>'start','room_id'=>(int)$r['id'],'room_token'=>''] as $name=>$value):?><input type="hidden" name="<?=access_h($name)?>" value="<?=access_h((string)$value)?>"><?php endforeach?>
<button type="submit" class="button secondary">Start Cleaning</button><p role="alert" data-cleaning-start-error hidden></p></form>
<noscript><a class="button secondary" href="<?=$url(['section'=>'housekeeping','room_id'=>(int)$r['id']])?>#housekeeping-action">Start Cleaning</a></noscript>
<?php elseif(!empty($prep['action'])):$prepQuery=['section'=>'housekeeping']+($prep['attempt_id']&&$prep['state']!=='needs_cleaning'?['id'=>$prep['attempt_id'],'form'=>$prep['form']]:['room_id'=>(int)$r['id']]);?><a class="button secondary" href="<?=access_h(stay_url($prepQuery))?>#housekeeping-action"><?=access_h($prep['action'])?></a><?php else:?><small>Room readiness requires inspection or maintenance clearance.</small><?php endif;endif?></article><?php endforeach?></div>
<p data-room-filter-empty <?=$visibleRoomCount?'hidden':''?>>No rooms match this view.</p>
</section>
<?php endif;?>

<?php require dirname(__DIR__).'/layouts/end.php';?>
