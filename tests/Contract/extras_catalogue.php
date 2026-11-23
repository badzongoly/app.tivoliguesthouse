<?php
use Tivoli\Domain\Stay\ExtrasCatalogue;
$root=dirname(__DIR__,2);
require $root.'/src/Domain/Stay/StayRules.php';
require $root.'/src/Domain/Stay/ExtrasCatalogue.php';
function check(bool $ok,string $label):void
{
    if(!$ok)throw new RuntimeException($label);
    echo "PASS $label\n";
}
function item(int $id,string $name,string $stock,array $overrides=[]):array
{
    return $overrides+['id'=>$id,'name'=>$name,'category'=>'Drinks','stock_qty'=>$stock,'held_qty'=>'0.00','low_stock'=>'2.00','active'=>1,'tracked'=>1];
}
$rows=[
    item(1,'Soda','10.00',['held_qty'=>'8.50']),
    item(2,'Water','0.25',['held_qty'=>'0.50']),
    item(3,'Laundry','0.00',['category'=>'Services','tracked'=>0]),
    item(4,'Old Juice','4.00',['active'=>0]),
    item(5,'Soda 2','4.00'),
];
$select=static fn(array $q=[])=>ExtrasCatalogue::select($rows,ExtrasCatalogue::filters($q));
$active=$select();
check(array_column($active['items'],'id')===[3,1,5,2],'default active and natural item order');
check(array_column($select(['catalogue_status'=>'inactive'])['items'],'id')===[4],'inactive items remain reachable');
check(array_column($select(['q'=>'SoDa','category'=>'Drinks'])['items'],'id')===[1,5],'case-insensitive name and category filter');
check($select(['q'=>'no matching product'])['items']===[],'empty search remains valid');
check(array_column($select(['sort'=>'stock-low'])['items'],'id')===[2,1,5,3],'low stock sorts by exact available amount and keeps services last');
check(array_column($select(['sort'=>'stock-high'])['items'],'id')===[5,1,2,3],'high stock sorts by exact available amount');
$byId=array_column($active['items'],null,'id');
check($byId[1]['available_units']===150&&$byId[1]['stock_label']==='Low Stock','held units reduce available stock');
check($byId[2]['available_units']===-25&&$byId[2]['stock_label']==='Out of Stock','negative availability stays visible and unsellable');
check($byId[3]['stock_label']==='Service','service is not reported as out of stock');
check(ExtrasCatalogue::filters(['q'=>['invalid'],'catalogue_status'=>['bad'],'catalogue_page'=>-1])===ExtrasCatalogue::filters([]),'malformed filters use safe defaults');
$many=array_map(static fn($id)=>item($id,'Item '.$id,'1.00'),range(1,41));
$page=ExtrasCatalogue::select($many,ExtrasCatalogue::filters(['catalogue_page'=>999]));
check($page['page']===2&&count($page['items'])===1&&$page['count']===41,'page clamps and retains exact total');
