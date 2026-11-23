<?php require dirname(__DIR__).'/layouts/start.php';?>
<link rel="stylesheet" href="<?=access_h(workspace_asset_url('assets/css/stays.css'))?>"><script src="<?=access_h(workspace_asset_url('assets/js/stays.js'))?>" defer></script>
<div class="app-page-heading-row"><div><p class="app-eyebrow">SETTINGS</p><h1>Extra Categories</h1><p>Choose which categories are available when saving an extra.</p></div></div>
<?php require dirname(__DIR__).'/partials/settings-nav.php';?>
<div id="cleaning-settings" tabindex="-1"></div>
<?php if($error):?><p class="error" role="alert"><?=access_h($error)?></p><?php endif?><?php if($notice):?><p class="notice" role="status"><?=access_h($notice)?></p><?php endif?>

<?php if($list):?>
<?php if($settingsCanEditOperations):?>
<section class="card"><details class="settings-entry" data-settings-entry><summary>Add Category</summary>
<form method="post" class="stay-form" data-settings-editor>
    <?php stay_fields('extra_categories',0,['category_revision'=>$list['revision'],'category_mode'=>'add','active'=>'1']);
    stay_input('Category Name','name','','text',true,'maxlength="80"');?>
    <p class="settings-actions"><button class="button" type="submit">Add Category</button><button type="button" class="secondary" data-settings-cancel hidden>Cancel</button></p>
</form></details></section>
<?php else:?><p class="settings-help">You Can View These Settings But Cannot Change Them.</p><?php endif?>
<section class="card"><h2>Manage Categories</h2>
<p>Extras already assigned to an inactive category keep it until you change their category.</p>
<?php foreach($list['entries'] as $name=>$active):?>
    <details class="settings-entry" data-settings-entry>
        <summary><?=access_h($name)?> · <?=$active?'Active':'Inactive'?></summary>
        <?php if($settingsCanEditOperations):?><form method="post" class="stay-form" data-settings-editor>
            <?php stay_fields('extra_categories',0,['category_revision'=>$list['revision'],'category_mode'=>'status','name'=>(string)$name]);
            stay_select('Status','active',['1'=>'Active','0'=>'Inactive'],$active?'1':'0',true);?>
            <p class="settings-actions"><button class="button" type="submit">Save Status</button><button type="button" class="secondary" data-settings-cancel hidden>Cancel</button></p>
        </form><?php endif?>
    </details>
<?php endforeach?>
</section>
<?php endif?>
<?php require dirname(__DIR__).'/layouts/end.php'; ?>
