<?php
namespace Tivoli\Domain\Stay;

final class ExtrasCatalogue
{
    public const PAGE_SIZE = 40;

    public static function filters(array $query): array
    {
        $search=is_string($query['q']??null)?mb_substr(trim($query['q']),0,120):'';
        $category=is_string($query['category']??null)?mb_substr(trim($query['category']),0,120):'';
        $status=$query['catalogue_status']??'active';
        $sort=$query['sort']??'name';
        $page=filter_var($query['catalogue_page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        return [
            'q'=>$search,'category'=>$category,
            'catalogue_status'=>in_array($status,['active','inactive','all'],true)?$status:'active',
            'sort'=>in_array($sort,['name','stock-low','stock-high'],true)?$sort:'name',
            'catalogue_page'=>$page?:1,
        ];
    }

    public static function select(array $items,array $filters): array
    {
        $categories=array_values(array_unique(array_column($items,'category')));
        natcasesort($categories);
        $matches=[];
        foreach($items as $item){
            $status=$filters['catalogue_status'];
            if($status!=='all'&&($status==='active')!==(bool)$item['active']) continue;
            if($filters['category']!==''&&$filters['category']!==$item['category']) continue;
            if($filters['q']!==''&&mb_stripos($item['name'],$filters['q'],0,'UTF-8')===false) continue;
            $item['available_units']=StayRules::quantity((string)$item['stock_qty'])-StayRules::quantity((string)$item['held_qty']);
            $item['stock_label']=match(true){
                !$item['tracked']=>'Service',
                $item['available_units']<=0=>'Out of Stock',
                $item['available_units']<=StayRules::quantity((string)$item['low_stock'])=>'Low Stock',
                default=>'In Stock',
            };
            $matches[]=$item;
        }
        $sort=$filters['sort'];
        usort($matches,static function(array $a,array $b)use($sort):int{
            if($sort!=='name'){
                $tracked=(int)$b['tracked']<=>(int)$a['tracked'];
                if($tracked) return $tracked;
                if($a['tracked']){
                    $stock=$a['available_units']<=>$b['available_units'];
                    if($stock) return $sort==='stock-low'?$stock:-$stock;
                }
            }
            return strnatcasecmp($a['name'],$b['name'])?:((int)$a['id']<=>(int)$b['id']);
        });
        $count=count($matches);
        $pages=max(1,(int)ceil($count/self::PAGE_SIZE));
        $page=min($filters['catalogue_page'],$pages);
        return [
            'items'=>array_slice($matches,($page-1)*self::PAGE_SIZE,self::PAGE_SIZE),
            'categories'=>$categories,'count'=>$count,'page'=>$page,'pages'=>$pages,
        ];
    }
}
