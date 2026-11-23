<?php require_once __DIR__.'/context.php'; ?>
<link rel="stylesheet" href="<?=access_h(workspace_asset_url('assets/css/app.css'))?>">
<?php if (($layoutPage ?? '') === 'owner-reports'): ?>
<link rel="stylesheet" href="<?=access_h(access_url('assets/css/owner-reports.css'))?>">
<?php endif ?>
<script src="<?=access_h(workspace_asset_url('assets/js/app.js'))?>" defer></script>
<meta name="theme-color" content="#193e34">

<link rel="stylesheet" href="<?=access_h(workspace_asset_url('assets/css/confirmations.css'))?>">
<script src="<?=access_h(workspace_asset_url('assets/js/confirmations.js'))?>" defer></script>

<?php
// Per-login navigation metadata only; no form values or business writes.
$formReturnActor=(int)($user['id'] ?? 0);
$formReturnScope='';
if ($formReturnActor>0 && session_status()===PHP_SESSION_ACTIVE) {
    $formReturnScope=$_SESSION['form_return_scope'] ??= bin2hex(random_bytes(16));
}
?>
<script src="<?=access_h(workspace_asset_url('assets/js/form-returns.js'))?>" data-return-base="<?=access_h(access_url(''))?>" data-return-actor="<?=$formReturnActor?>" data-return-scope="<?=access_h($formReturnScope)?>" defer></script>
