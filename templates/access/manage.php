<?php $layoutTitle='System Access';$layoutPage='users';require __DIR__.'/context.php';require dirname(__DIR__).'/layouts/start.php'; ?>
<link rel="stylesheet" href="<?=access_h(workspace_asset_url('assets/css/access-workspace.css'))?>">
<script src="<?=access_h(workspace_asset_url('assets/js/access-workspace.js'))?>" defer></script>
<div class="access-workspace" data-access-workspace data-access-account="<?=$accessSelectedAccount?>" data-access-default="<?=access_h($accessDefaultSection)?>" data-access-error-action="<?=access_h($accessFailedAction)?>" data-access-error-id="<?=$accessFailedId?>">
<p class="app-eyebrow">ADMINISTRATION</p><h1>System Access</h1><p>Manage sign-in accounts, roles and privileges. Changes require your password and a reason, and are recorded in the audit trail.</p>
<nav class="app-section-nav" aria-label="System Access Sections"><?php foreach(['staff'=>'Users','roles'=>'Roles','privileges'=>'Privileges'] as $accessSection=>$accessLabel):?><a href="#<?=$accessSection?>" data-access-section="<?=$accessSection?>"><?=$accessLabel?></a><?php endforeach?></nav>
<?php if($error):?><p class="error" role="alert"><?=access_h($error)?></p><?php endif?><?php if($notice):?><p class="notice" role="status"><?=access_h($notice)?></p><?php endif?>
<?php require __DIR__.'/users.php';require __DIR__.'/roles.php';require __DIR__.'/privileges.php';?>
</div><?php require dirname(__DIR__).'/layouts/end.php'; ?>
