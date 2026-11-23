<?php
$extrasNavPermissions=access_service()->repo->privileges((int)$user['id']);
$extrasNavPage=basename($_SERVER['SCRIPT_NAME']??'');
$extrasNavTab=match($extrasNavPage){
 'extra_sales.php'=>'sales','stock.php'=>'stock','stock-counts.php'=>'counts','stock-issues.php'=>'issues',
 default=>(($_GET['tab']??'')==='stock'?'stock':'catalogue')
};
?>
<?php if($extrasNavTab==='catalogue'||$extrasNavTab==='stock'||$extrasNavTab==='sales'):?>
<div class="app-section-nav" aria-label="Common Extras & Stock Actions" role="group">
<?php if(in_array('bookings.manage',$extrasNavPermissions,true)):?>
<a href="<?=access_h(access_url('occupancy.php'))?>#stay-workspace">Sell an Extra to a Guest</a>
<?php endif?>
<?php if(in_array('stock.manage',$extrasNavPermissions,true)):?>
<a href="<?=access_h(access_url('purchases.php?page=register'))?>#purchase-action">Find a Purchase to Receive</a>
<?php endif?>
<?php if(array_intersect(['bookings.manage','stock.manage'],$extrasNavPermissions)):?>
<a href="<?=access_h(access_url('stock-counts.php?page=new'))?>#stockcount-action">Count Stock</a>
<?php endif?>
</div>
<?php endif?>
<nav class="app-section-nav extras-navigation" aria-label="Extras & Stock">
<?php foreach(['catalogue'=>['Manage Extras','extras.php','extras.manage'],'sales'=>['Extra Sales','extra_sales.php','sales.view'],'stock'=>['Stock Movements','stock.php','stock.manage']] as $extrasNavKey=>[$extrasNavLabel,$extrasNavPath,$extrasNavPermission]):if(!in_array($extrasNavPermission,$extrasNavPermissions,true))continue;?>
<a href="<?=access_h(access_url($extrasNavPath))?>" <?=$extrasNavKey===$extrasNavTab?'aria-current="page"':''?>><?=access_h($extrasNavLabel)?></a>
<?php endforeach?>
<?php if(array_intersect(['bookings.manage','stock.manage'],$extrasNavPermissions)):?><a href="<?=access_h(access_url('stock-counts.php'))?>" <?=$extrasNavTab==='counts'?'aria-current="page"':''?>>Stock Counts</a><a href="<?=access_h(access_url('stock-issues.php'))?>" <?=$extrasNavPage==='stock-issues.php'?'aria-current="page"':''?>>Non-Sale Issues</a><?php endif?>
<?php if((new \Tivoli\Service\InventoryCostingService(access_service()))->owner((int)$user['id'])):?><a href="<?=access_h(access_url('finance.php?page=profit'))?>#stock-costing">Stock Costing</a><?php endif?>
</nav>
