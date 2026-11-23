<?php
use Tivoli\Domain\Stay\ExtrasCatalogue;

$catalogue=ExtrasCatalogue::select($view['extras']??[],$catalogueQuery);
$catalogueBase=['section'=>'extras','tab'=>'catalogue'];
$catalogueList=$catalogueBase+$catalogueQuery;
$countStockAllowed=$requestPurchaseAllowed=false;
if(!empty($view['extras'])){
    try{
        (new \Tivoli\Service\StockIssueService(access_service()))->permit($actor);
        $countStockAllowed=true;
    }catch(RuntimeException $unavailable){}
    if(!empty($canEditExtras)){
        try{
            (new \Tivoli\Service\PurchaseService(access_service()))->permit($actor);
            $requestPurchaseAllowed=true;
        }catch(RuntimeException $unavailable){}
    }
}
?>
<link rel="stylesheet" href="<?=access_h(workspace_asset_url('assets/css/extras-catalogue.css'))?>">
<div class="extras-catalogue" id="extras-catalogue">
    <div class="stay-actions">
        <h2>Extras Catalogue</h2>
        <?php if(!empty($canEditExtras)&&$form!=='extra_save')$link('Add Extra',$catalogueList+['form'=>'extra_save'],'');?>
    </div>
    <?php if(empty($canEditExtras)):?><p class="stay-help">View Only · An administrator can change catalogue items.</p><?php endif?>
    <nav class="app-section-nav" aria-label="Catalogue Status">
        <?php foreach(['active'=>'Active','inactive'=>'Inactive','all'=>'All Items'] as $value=>$label):?>
        <a href="<?=$url(array_replace($catalogueList,['catalogue_status'=>$value,'catalogue_page'=>1]))?>#extras-catalogue"
           <?=$catalogueQuery['catalogue_status']===$value?'aria-current="page"':''?>><?=$label?></a>
        <?php endforeach?>
    </nav>
    <form class="extras-catalogue-filters" method="get" action="<?=access_h(stay_url())?>#extras-catalogue">
        <input type="hidden" name="section" value="extras">
        <input type="hidden" name="tab" value="catalogue">
        <label>Search Items<input type="search" name="q" maxlength="120" value="<?=access_h($catalogueQuery['q'])?>"></label>
        <label>Category<select name="category">
            <option value="">All Categories</option>
            <?php foreach($catalogue['categories'] as $category):?>
            <option value="<?=access_h($category)?>" <?=$catalogueQuery['category']===$category?'selected':''?>><?=access_h($category)?></option>
            <?php endforeach?>
        </select></label>
        <input type="hidden" name="catalogue_status" value="<?=access_h($catalogueQuery['catalogue_status'])?>">
        <label>Sort By<select name="sort">
            <?php foreach(['name'=>'Item Name','stock-low'=>'Available Stock: Low to High','stock-high'=>'Available Stock: High to Low'] as $value=>$label):?>
            <option value="<?=$value?>" <?=$catalogueQuery['sort']===$value?'selected':''?>><?=$label?></option>
            <?php endforeach?>
        </select></label>
        <div class="stay-actions"><button class="button" type="submit">Apply Filters</button>
        <a href="<?=$url($catalogueBase)?>#extras-catalogue">Reset</a></div>
    </form>
    <p><?=count($catalogue['items'])?> of <?=$catalogue['count']?> <?= $catalogue['count']===1?'item':'items'?></p>
    <?php if(!$catalogue['items']):?>
    <p>No items match these filters.</p>
    <?php else:?>
    <table class="extras-catalogue-table" role="table" aria-label="Extras Catalogue">
        <thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Item</th><th scope="col" role="columnheader">Category</th><th scope="col" role="columnheader">Price</th><th scope="col" role="columnheader">Available</th><th scope="col" role="columnheader">Status</th><th scope="col" role="columnheader">Details</th></tr></thead>
        <tbody role="rowgroup">
        <?php foreach($catalogue['items'] as $x):$id=(int)$x['id'];?>
        <tr role="row" data-extra-id="<?=$id?>">
            <th class="catalogue-name" scope="row" role="rowheader"><?=access_h($x['name'])?></th>
            <td class="catalogue-category" role="cell"><?=access_h($x['category'])?></td>
            <td class="catalogue-price" role="cell"><?=stay_decimal($x['price'])?></td>
            <td class="catalogue-available" role="cell"><?=$x['tracked']?access_h($x['available_qty']):'—'?></td>
            <td class="catalogue-status" role="cell">
                <?php if(!$x['active']):?><span class="stay-badge">Inactive</span><?php endif?>
                <span class="stay-badge"><?=access_h($x['stock_label'])?></span>
            </td>
            <td class="catalogue-details" role="cell">
                <details>
                    <summary aria-label="Details for <?=access_h($x['name'])?>">Details</summary>
                    <dl>
                        <div><dt>Category</dt><dd><?=access_h($x['category'])?></dd></div>
                        <div><dt>Item Status</dt><dd><?=$x['active']?'Active':'Inactive'?></dd></div>
                        <?php if($x['tracked']):?>
                        <div><dt>Held</dt><dd><?=access_h($x['held_qty'])?></dd></div>
                        <div><dt>Recorded</dt><dd><?=access_h($x['stock_qty'])?></dd></div>
                        <?php else:?><div><dt>Stock</dt><dd>No stock for services</dd></div><?php endif?>
                    </dl>
                    <?php if(!empty($canEditExtras)&&!($form==='extra_save'&&$editId===$id))$link('Edit',$catalogueList+['form'=>'extra_save','id'=>$id]);?>
                    <?php if($x['tracked']&&$x['active']&&$requestPurchaseAllowed):?>
                    <a class="button secondary" href="<?=access_h(access_url('purchases.php?page=new&type=stock&extra_id='.$id))?>#purchase-action">Request Purchase</a>
                    <?php endif?>
                    <?php if($x['tracked']&&$countStockAllowed):?>
                    <a class="button secondary" href="<?=access_h(access_url('stock-issues.php?page=new&extra_id='.$id))?>#stockissue-action">Report Stock Issue</a>
                    <?php endif?>
                </details>
            </td>
        </tr>
        <?php endforeach?>
        </tbody>
    </table>
    <?php endif?>
    <?php if($catalogue['pages']>1):?>
    <nav class="stay-actions" aria-label="Catalogue Pages">
        <?php if($catalogue['page']>1):?><a href="<?=$url(array_replace($catalogueList,['catalogue_page'=>$catalogue['page']-1]))?>#extras-catalogue">Previous</a><?php endif?>
        <span>Page <?=$catalogue['page']?> of <?=$catalogue['pages']?></span>
        <?php if($catalogue['page']<$catalogue['pages']):?><a href="<?=$url(array_replace($catalogueList,['catalogue_page'=>$catalogue['page']+1]))?>#extras-catalogue">Next</a><?php endif?>
    </nav>
    <?php endif?>
</div>
