<?php
$stockCountSelected=is_array($_POST['items']??null)?array_map('strval',array_filter($_POST['items'],'is_scalar')):[];
if($_SERVER['REQUEST_METHOD']!=='POST'&&($_GET['reuse']??null)==='weekly')$stockCountSelected=array_map('strval',$lastWeeklyItems);
$stockCountPurpose=$values['purpose']??'Weekly Stock Count';
?>
<section class="card" id="stockcount-step" tabindex="-1"><h2>Start A Stock Count</h2>
<p>Choose the items, count them one at a time, then send the results to Eugene for approval.</p>
<?php if($lastWeeklyItems):?><div class="sc-row">
<?php if(($_GET['reuse']??null)==='weekly'):?><?php sc_link('Choose Items',['page'=>'new']);?><?php else:?><?php sc_link('Use Last Weekly Item List',['page'=>'new','reuse'=>'weekly']);?><?php endif?>
</div><?php endif?>
<form method="post" data-count-picker><?php sc_fields('start',['guided'=>'1']);?>
<label>Find Items<input type="search" data-count-search placeholder="Search by item name" autocomplete="off"></label>
<div class="sc-row"><button type="button" class="secondary" data-count-select>Select Shown</button><button type="button" class="secondary" data-count-clear>Clear Selection</button></div>
<p data-count-summary role="status" aria-live="polite">Select 1–50 items to count.</p>
<fieldset class="sc-picker-list"><legend>Items To Count</legend>
<?php foreach($options as $stockCountItem):?><label class="sc-check sc-picker-item" data-count-item><input type="checkbox" name="items[]" value="<?=(int)$stockCountItem['id']?>" <?=in_array((string)$stockCountItem['id'],$stockCountSelected,true)?'checked':''?>><span><?=access_h($stockCountItem['name'])?><?=$stockCountItem['active']?'':' · Inactive'?></span></label><?php endforeach?>
</fieldset><p data-count-empty hidden>No matching items. Try another name.</p>
<details <?=($stockCountPurpose!=='Weekly Stock Count'||!empty($values['comment']))?'open':''?>><summary>Count Details (Optional)</summary><label>Count Purpose<select name="purpose"><?php foreach(['Weekly Stock Count','Shift Check','Investigate A Discrepancy'] as $stockCountChoice):?><option value="<?=access_h($stockCountChoice)?>" <?=$stockCountPurpose===$stockCountChoice?'selected':''?>><?=access_h($stockCountChoice)?></option><?php endforeach?></select></label><?php sc_text('Comment','comment');?></details>
<p>Expected balances stay hidden until every selected item is counted. Stock changes only after owner approval.</p>
<div class="sc-row"><button type="submit" data-count-start>Start Counting</button><?php sc_link('Cancel',[]);?></div>
</form></section>
