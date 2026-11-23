<?php
$extrasNavPermissions=access_service()->repo->privileges((int)$user['id']);
$extrasNavPage=basename($_SERVER['SCRIPT_NAME']??'');
$extrasNavTab=match($extrasNavPage){
 'extra_sales.php'=>'sales','stock.php'=>'stock','stock-counts.php'=>'counts','stock-issues.php'=>'issues',
 default=>(($_GET['tab']??'')==='stock'?'stock':'catalogue')
};
$extrasNavItems=[
    'catalogue'=>['Manage Extras','extras.php',['extras.manage']],
    'sales'=>['Extra Sales','extra_sales.php',['sales.view']],
    'stock'=>['Stock Movements','stock.php',['stock.manage']],
    'counts'=>['Stock Counts','stock-counts.php',['bookings.manage','stock.manage']],
    'issues'=>['Non-Sale Issues','stock-issues.php',['bookings.manage','stock.manage']],
];
$extrasNavItems=array_filter($extrasNavItems,
    static fn(array $item):bool=>(bool)array_intersect($item[2],$extrasNavPermissions));
if((new \Tivoli\Service\InventoryCostingService(access_service()))->owner((int)$user['id'])){
    $extrasNavItems['costing']=['Stock Costing','finance.php?page=profit#stock-costing'];
}
?>
<nav class="extras-navigation workspace-tab-strip" aria-label="Extras & Stock">
        <?php foreach($extrasNavItems as $extrasNavKey=>$extrasNavItem):[$extrasNavLabel,$extrasNavPath]=$extrasNavItem;?>
        <a href="<?=access_h(access_url($extrasNavPath))?>" <?=$extrasNavKey===$extrasNavTab?'aria-current="page"':''?>><?=access_h($extrasNavLabel)?></a>
        <?php endforeach?>
</nav>
<?php if($extrasNavTab==='catalogue'||$extrasNavTab==='stock'||$extrasNavTab==='sales'):?>
<div class="extras-quick-actions" aria-label="Common Extras & Stock Actions" role="group">
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
