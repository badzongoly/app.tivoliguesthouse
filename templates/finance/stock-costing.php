<?php
use Tivoli\Domain\Stay\StayRules as R;
// Cost controls are part of the owner's existing Profit Review, not a second stock entry form.
?>
<section class="card" id="stock-costing" tabindex="-1">
<p class="eyebrow">PURCHASE → STOCK → PROFIT</p><h2>Stock Costing &amp; Profit Accuracy</h2>
<?php if(!empty($costing['saved_missing'])):?><p>This period retains its original closed review. Stock costing was not captured in that snapshot; a historical stock profit has not been invented.</p><?php else:?>
<?php if(!empty($costing['retained'])):?><p class="notice">Retained Closed Snapshot · These figures and coverage have not been recomputed using later evidence.</p><?php endif?>
<p>Moving weighted average · Staff keep using Purchase Requests, Guest Stay Accounts, Stock Counts and Non-Sale Issues.</p>
<p class="notice"><strong><?=$costing['complete']?'Covered Stock Cost Result':'Incomplete Stock Cost Coverage'?></strong> · <?php if($costing['cutover']):?>Costing began <?=access_h(display_date($costing['cutover']))?> UTC. <?php endif?><?php if($costing['partial']):?>This range includes a partial cutover day. <?php endif?><?=(int)$costing['unstarted']?> items still need opening setup. <?php if($costing['unknown']):?>Some consumed stock costs or source records still need review.<?php endif?> Unknown costs are not zero.</p>
<div class="finance-scroll"><table><tbody>
<tr><th>Recorded-Cost Result</th><td><?=access_h(fm($costing['recorded_profit']))?></td></tr>
<tr><th>Add Back Linked Inventory Purchase Costs</th><td><?=access_h(fm($costing['acquisition']))?></td></tr>
<?php foreach(['sales'=>'Less Cost of Stock Sold','loss'=>'Less Verified Stock Losses','complimentary'=>'Less Actual Complimentary Stock','returns'=>'Less Supplier Return Differences'] as $key=>$label):?><tr><th><?=access_h($label)?></th><td><?=access_h(fm($costing['costs'][$key]))?></td></tr><?php endforeach?>
<tr><th><?=$costing['complete']?'Stock Cost Management Profit':'Result Before Missing Costs / Partial Coverage'?></th><td><?=access_h(fm($costing['before_unknown']))?></td></tr>
</tbody></table></div>
<p>Opening stock is carried forward, not bought again. Purchase costs are added back once and replaced with stock consumed. Buying stock and paying the supplier are separate actions. This remains a management estimate; the existing room-revenue and other-cost limits still apply.</p>
<p>Supported inventory value through <?=access_h(display_date($data['end']))?>: <strong><?=access_h(fm($costing['inventory']))?></strong> · <?=(int)$costing['inventory_unknown']?> items have unknown carrying value. Closed snapshots retain the figures and coverage recorded when reviewed.</p>
<?php endif?><h3>Current Item Setup</h3><p>The list below shows current stock cost setup, separate from any historical snapshot above.</p>
<form method="get" class="finance-inline"><input type="hidden" name="page" value="profit"><label>Find Stock Item<input name="costing_search" maxlength="120" value="<?=access_h(is_string($_GET['costing_search']??null)?$_GET['costing_search']:'')?>"></label><button>Find Item</button><small>Showing up to 50 items.</small></form>
<div class="finance-scroll"><table><thead><tr><th>Stock Item</th><th>Opening / Coverage</th><th>Carrying Value</th><th>Action</th></tr></thead><tbody>
<?php foreach($costingRows as $row):$state=$row['state'];?><tr><td><?=access_h($row['item']['name'])?></td><td><?=$row['opening']?($row['supported']&&$state['unknown']===0?'Supported Sources':'Needs Source Review'):'Opening Needed'?></td><td><?=!$state||$state['value']===null?'Unknown':access_h(fm($state['value']))?></td><td><a class="finance-action" href="<?=access_h(access_url('finance.php?page=profit&costing_id='.(int)$row['item']['id']))?>#costing-action"><?=$row['opening']?'Review Costs':'Set Opening Cost'?></a></td></tr><?php endforeach?>
</tbody></table></div>
<p><a href="<?=access_h(access_url('stock-counts.php'))?>">Open Existing Stock Counts</a> · <a href="<?=access_h(access_url('purchases.php'))?>">Purchase Requests</a> · <a href="<?=access_h(access_url('financial-corrections.php?area=purchases'))?>">Purchase Corrections</a> · <a href="<?=access_h(access_url('stock-issues.php'))?>">Non-Sale Issues</a></p>
</section>
<?php if($costingSelected):$selectedCost=$costingSelected;$opening=$selectedCost['opening'];$linked=(int)($selectedCost['basis']['purchase_id']??0);?>
<section class="card" id="costing-action" tabindex="-1"><h2><?=access_h($selectedCost['item']['name'])?> · <?=$opening?'Review Opening Evidence':'Set Opening Cost'?></h2>
<?php if($opening):?><p>Started <?=access_h(display_date($opening['created_at']))?> UTC from approved Stock Count #<?=(int)$opening['data']['count_id']?>. Opening quantity <?=access_h(R::decimal($opening['data']['qty']))?>. Current on hand <?=access_h($selectedCost['item']['stock_qty'])?>. Existing source records are retained.</p>
<p><strong><?=$selectedCost['supported']&&$selectedCost['state']['unknown']===0?'Supported Sources':'Incomplete Cost Coverage'?></strong> · Current Carrying Value <strong><?=$selectedCost['state']['value']===null?'Unknown':access_h(fm($selectedCost['state']['value']))?></strong> · Costed Quantity <?=access_h(R::decimal($selectedCost['state']['qty']))?> · Written Off, Awaiting Disposal <?=access_h(R::decimal($selectedCost['state']['written_off']))?>.</p>
<?php if(!$selectedCost['supported']):?><p class="notice">Cost coverage is incomplete. Review original stock movements and count evidence; a new opening total does not repair missing quantity links.</p><?php endif?>
<?php endif?>
<?php if($linked):?><p>Opening cost follows Purchase #<?=$linked?>. Correct the original source using <a href="<?=access_h(access_url('financial-corrections.php?area=purchases&source_type=purchase&source_id='.$linked))?>#purchase-correction-action">Purchase Corrections</a>.</p>
<?php else:?><form method="post" action="<?=access_h(access_url('finance.php?page=profit&costing_id='.(int)$selectedCost['item']['id']))?>" data-confirm="Record this reviewed opening cost? Stock quantity, original purchases and payments will remain retained.">
<?php ff($opening?'costing_evidence':'costing_start',['extra_id'=>(string)$selectedCost['item']['id'],'version'=>$selectedCost['version']]);?>
<div class="finance-form">
<?php if(!$opening):?><label>Approved Physical Stock Count *<select name="count_id" required><option value="">Choose a Fresh Approved Count</option><?php foreach($costingCounts as $count):?><option value="<?=(int)$count['target_id']?>">#<?=(int)$count['target_id']?> · <?=access_h(display_date($count['created_at']))?> UTC</option><?php endforeach?></select></label><?php endif?>
<label>Original Opening Total (GHS)<?=$opening?' *':''?><input type="text" inputmode="decimal" name="total" maxlength="24" <?=$opening?'required':''?> value="<?=$opening&&$selectedCost['basis']['total']!==null?access_h(R::decimal($selectedCost['basis']['total'])):''?>"><small>Use the total supported cost for the original opening quantity. <?=$opening?'Later evidence is split between stock already consumed and stock still held.':'Leave blank if unavailable; the app will clearly mark unknown costs.'?></small></label>
<label class="stay-wide">Opening Evidence / Comment *<textarea name="evidence" rows="3" maxlength="2000" required></textarea></label>
<details><summary>Link an Existing Accepted Purchase (Optional)</summary><label>Purchase Number<input type="number" name="purchase_id" min="1"><small>Only when this purchase supports the entire opening quantity and total. Do not guess a historical allocation.</small></label></details>
<label class="finance-check"><input type="checkbox" name="verified" value="1" required> I verified the approved count and the supported opening cost, or confirmed that its cost is unknown.</label>
</div><div class="finance-inline"><button><?=$opening?'Record Reviewed Evidence':'Start Costing for This Item'?></button><a class="finance-action" href="<?=access_h(access_url('finance.php?page=profit'))?>#stock-costing">Cancel</a></div>
</form><?php endif?>
<?php if($opening):?><details><summary>Cost History</summary><div class="finance-scroll"><table><thead><tr><th>Event</th><th>Recorded</th><th>Source / Evidence</th></tr></thead><tbody><?php foreach(array_reverse($selectedCost['history']) as $event):?><tr><td>#<?=(int)$event['id']?> · <?=access_h(ucwords(str_replace(['ic_','_'],['',' '],$event['kind'])))?></td><td><?=access_h(display_date($event['created_at']))?> UTC</td><td><?=access_h($event['data']['evidence']??$event['data']['identity']??'Retained Valuation Snapshot')?><?php if($event['kind']==='ic_valued'):?> · Consumed Cost Change <?=access_h(fm(array_sum($event['data']['cost_delta'])))?><?php endif?></td></tr><?php endforeach?></tbody></table></div></details><?php endif?>
</section><script>document.getElementById('costing-action')?.focus({preventScroll:true});document.getElementById('costing-action')?.scrollIntoView({block:'start'});</script>
<?php endif?>
