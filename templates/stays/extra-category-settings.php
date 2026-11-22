<?php require dirname(__DIR__).'/layouts/start.php';?>
<link rel="stylesheet" href="<?=access_h(access_url('assets/css/stays.css'))?>"><script src="<?=access_h(access_url('assets/js/stays.js'))?>" defer></script>
<div class="app-page-heading-row"><div><p class="app-eyebrow">SETTINGS</p><h1>Extra Categories</h1><p>Choose which categories are available when saving an extra.</p></div></div>
<nav class="app-section-nav" aria-label="Settings"><a href="<?=access_h(access_url('hr-settings.php'))?>">Payment Lists</a><a href="<?=access_h(access_url('hr-settings.php?section=documents'))?>">Document Types</a><a href="<?=access_h(access_url('cleaning-settings.php'))?>">Cleaning Checklist</a><a href="<?=access_h(access_url('cleaning-settings.php?type=inspection'))?>">Inspection Checklist</a><a href="<?=access_h(access_url('cleaning-settings.php?type=areas'))?>">Audit Areas</a><a href="<?=access_h(access_url('cleaning-settings.php?type=extras'))?>" aria-current="page">Extra Categories</a></nav>
<div id="cleaning-settings" tabindex="-1"></div>
<?php if($error):?><p class="error" role="alert"><?=access_h($error)?></p><?php endif?><?php if($notice):?><p class="notice" role="status"><?=access_h($notice)?></p><?php endif?>

<?php if($list):?>
<section class="card"><h2>Add Category</h2>
<form method="post" class="stay-form">
    <?php stay_fields('extra_categories',0,['category_revision'=>$list['revision'],'category_mode'=>'add','active'=>'1']);
    stay_input('Category Name','name','','text',true,'maxlength="80"');?>
    <p><button class="button" type="submit">Add Category</button></p>
</form></section>
<section class="card"><h2>Manage Categories</h2>
<p>Extras already assigned to an inactive category keep it until you change their category.</p>
<?php foreach($list['entries'] as $name=>$active):?>
    <details>
        <summary><?=access_h($name)?> · <?=$active?'Active':'Inactive'?></summary>
        <form method="post" class="stay-form">
            <?php stay_fields('extra_categories',0,['category_revision'=>$list['revision'],'category_mode'=>'status','name'=>(string)$name]);
            stay_select('Status','active',['1'=>'Active','0'=>'Inactive'],$active?'1':'0',true);?>
            <p><button class="button" type="submit">Save Status</button></p>
        </form>
    </details>
<?php endforeach?>
</section>
<?php endif?>
<?php require dirname(__DIR__).'/layouts/end.php'; ?>
