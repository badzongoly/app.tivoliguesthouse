<?php
namespace Tivoli\Repository;

/** Current allocation views never overwrite the original movement payload. */
final class ReceiptReallocation
{
    public static function events(array $events):array{$views=[];foreach($events as $e)if($e['kind']==='ra_cash_view')$views[(int)$e['target_id']]=['id'=>(int)$e['id'],'data'=>$e['data']['data']];foreach($events as &$e)if(isset($views[(int)$e['id']])){$e['original_data']=$e['data'];$e['data']=array_replace($e['data'],$views[(int)$e['id']]['data']);$e['allocation_revision']=$views[(int)$e['id']]['id'];}unset($e);return $events;}
    public static function event(AccessRepository $r,array $event):array{
        $row=$r->run("SELECT id,payload FROM finance_events WHERE kind='ra_cash_view' AND target_type='cash_event' AND target_id=? ORDER BY id DESC LIMIT 1",[(string)$event['id']])->fetch();if($row){$event['original_data']=$event['data'];$event['data']=array_replace($event['data'],json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR)['data']);$event['allocation_revision']=(int)$row['id'];}return $event;
    }
    /** alias and quoted cutoff are internal SQL expressions, never request parameters. */
    public static function payloadSql(string $alias,string $cutoff):string{return "COALESCE((SELECT JSON_EXTRACT(rv.payload,'$.data') FROM finance_events rv WHERE rv.kind='ra_cash_view' AND rv.target_type='cash_event' AND rv.target_id=CONVERT($alias.id USING utf8mb4) COLLATE utf8mb4_unicode_ci AND rv.created_at<$cutoff ORDER BY rv.id DESC LIMIT 1),$alias.payload)";}
}
