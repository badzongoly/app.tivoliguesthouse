<?php require_once __DIR__.'/context.php'; ?>
<link rel="stylesheet" href="<?=access_h(access_url('assets/css/app.css'))?>">
<?php if (($layoutPage ?? '') === 'owner-reports'): ?>
<link rel="stylesheet" href="<?=access_h(access_url('assets/css/owner-reports.css'))?>">
<?php endif ?>
<script src="<?=access_h(access_url('assets/js/app.js'))?>" defer></script>
<meta name="theme-color" content="#193e34">
