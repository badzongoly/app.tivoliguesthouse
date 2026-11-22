<?php require dirname(__DIR__).'/layouts/start.php';?>
<script src="<?=access_h(access_url('assets/js/stays.js'))?>" defer></script>
<link rel="stylesheet" href="<?=access_h(access_url('assets/css/stays.css'))?>">
<div class="app-page-heading-row"><div><p class="app-eyebrow">SETTINGS / HOUSEKEEPING</p><h1><?=access_h($checklistTitle)?></h1><p>Add or remove items for future checks. Previous completion records remain unchanged.</p></div></div>
<nav class="app-section-nav" aria-label="Settings"><a href="<?=access_h(access_url('hr-settings.php'))?>">Payment Lists</a><a href="<?=access_h(access_url('hr-settings.php?section=documents'))?>">Document Types</a><a href="<?=access_h(access_url('cleaning-settings.php'))?>" <?=$kind==='cleaning'?'aria-current="page"':''?>>Cleaning Checklist</a><a href="<?=access_h(access_url('cleaning-settings.php?type=inspection'))?>" <?=$kind==='inspection'?'aria-current="page"':''?>>Inspection Checklist</a><a href="<?=access_h(access_url('cleaning-settings.php?type=areas'))?>">Audit Areas</a><a href="<?=access_h(access_url('cleaning-settings.php?type=extras'))?>">Extra Categories</a></nav>
<div id="cleaning-settings" tabindex="-1"></div>
<?php if($error):?><p class="error" role="alert"><?=access_h($error)?></p><?php endif?>
<?php if($notice):?><p class="notice" role="status"><?=access_h($notice)?></p><?php endif?>
<?php if($list):?><?php if($kind==='cleaning'):?><p class="stay-help">Follow product labels and contact times. Never mix cleaning chemicals. This safety reminder remains visible regardless of checklist changes.</p><?php else:?><p class="stay-help">All active inspection checks are required before release. Unresolved faults continue to block availability.</p><?php endif?>
<?php foreach($list['sections'] as $section):?><section class="card"><h2><?=access_h($section['title'])?></h2>
<?php foreach($section['items'] as $item):?><div class="cleaning-setting-item"><p><?=access_h($item['text'])?></p><form method="post"><?php stay_fields($kind.'_checklist',0,['checklist_revision'=>$list['revision'],'change'=>'remove','item_id'=>$item['id']]);?><button class="button secondary" type="submit" aria-label="<?=access_h('Remove: '.$item['text'])?>">Remove Item</button></form></div><?php endforeach?>
<form method="post" class="stay-form"><?php stay_fields($kind.'_checklist',0,['checklist_revision'=>$list['revision'],'change'=>'add','section_id'=>$section['id']]);stay_input('New Checklist Item','item_text','','text',true,'maxlength="500"');?><p><button class="button" type="submit">Add Item</button></p></form>
</section><?php endforeach;endif?>
<?php require dirname(__DIR__).'/layouts/end.php';?>
