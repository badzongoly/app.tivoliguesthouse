<?php
$extrasNavPermissions=access_service()->repo->privileges((int)$user['id']);
$extrasNavPage=basename($_SERVER['SCRIPT_NAME']??'');$extrasNavTab=$extrasNavPage==='extra_sales.php'?'sales':(($_GET['tab']??'')==='stock'||$extrasNavPage==='stock.php'?'stock':'catalogue');
$extrasNavTab=$extrasNavPage==='stock-issues.php'?'issues':$extrasNavTab;
?>
<nav class="app-section-nav extras-navigation" aria-label="Extras & Stock">
<?php foreach(['catalogue'=>['Manage Extras','extras.php','extras.manage'],'sales'=>['Extra Sales','extra_sales.php','sales.view'],'stock'=>['Stock','stock.php','stock.manage']] as $key=>[$label,$path,$permission]):if(!in_array($permission,$extrasNavPermissions,true))continue;?>
<a href="<?=access_h(access_url($path))?>" <?=$key===$extrasNavTab?'aria-current="page"':''?>><?=access_h($label)?></a>
<?php endforeach?>
<?php if(array_intersect(['bookings.manage','stock.manage'],$extrasNavPermissions)):?><a href="<?=access_h(access_url('stock-issues.php'))?>" <?=$extrasNavPage==='stock-issues.php'?'aria-current="page"':''?>>Non-Sale Issues</a><?php endif?>
<?php if((new \Tivoli\Service\InventoryCostingService(access_service()))->owner((int)$user['id'])):?><a href="<?=access_h(access_url('finance.php?page=profit'))?>#stock-costing">Stock Costing</a><?php endif?>
</nav>
