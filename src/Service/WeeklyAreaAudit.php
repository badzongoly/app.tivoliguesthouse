<?php
namespace Tivoli\Service;
use RuntimeException;
use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Repository\AccessRepository;

/** Immutable weekly area checks and version-checked corrective follow-ups. */
final class WeeklyAreaAudit
{
    public const CHECKS=['cleanliness'=>'Cleanliness & Waste','access'=>'Walkways & Exits','lighting'=>'Lighting & Visible Electrical Condition','security'=>'Doors, Locks & Security','water'=>'Water, Leaks & Drainage','equipment'=>'Equipment & Supplies'];
    public static function week(mixed $date): string
    {
        $parsed=is_string($date)?\DateTimeImmutable::createFromFormat('!Y-m-d',$date,new \DateTimeZone('UTC')):false;
        if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new RuntimeException('Choose a valid audit date.');
        return $parsed->modify('-'.((int)$parsed->format('N')-1).' days')->format('Y-m-d');
    }
    public static function record(AccessRepository $repo,array $in,string $now): array
    {
        $list=AuditAreas::current($repo);
        if(($in['area_revision'] ?? '')!==$list['revision'])throw new RuntimeException('The area list changed. Reload and choose an area again.');
        $area=null;foreach($list['entries'] as $entry)if($entry['id']===($in['area_id'] ?? '')&&$entry['active'])$area=$entry;
        if(!$area)throw new RuntimeException('Choose an active area from the list.');
        $result=$in['result'] ?? '';if(!in_array($result,['pass','corrective','unsafe'],true))throw new RuntimeException('Choose a valid audit result.');
        $date=substr($now,0,10);$week=self::week($date);
        return ['audit'=>['audit_key'=>bin2hex(random_bytes(16)),'area_id'=>$area['id'],'area_revision'=>$list['revision'],'area'=>$area['name'],'audit_date'=>$date,'week'=>$week,'checks'=>[],'result'=>$result,'status'=>$result==='pass'?'completed':'open','comment'=>R::text($in['reason'] ?? '',500),'owner'=>R::text($in['owner'] ?? '',120),'due_date'=>'']];
    }
    public static function followup(AccessRepository $repo,array $in): array
    {
        // Existing operation-key replays are resolved by StayService before this retired writer.
        // Historical completed evidence remains readable; every new decision uses named work and owner verification.
        throw new RuntimeException('Open Follow-Ups. Eugene must confirm the original finding, assign staff and verify completion. This older form cannot record new decisions.');
    }
    public static function listing(AccessRepository $repo,array $filters,string $now): array
    {
        $week=($filters['range'] ?? '')==='all'?null:self::week($filters['week'] ?? substr($now,0,10));
        $before=filter_var($filters['before'] ?? '0',FILTER_VALIDATE_INT);if($before===false||$before<0)throw new RuntimeException('Invalid area audit cursor.');
        $where="a.action='stay.area_audit' AND a.target_type='area_audit'";$values=[];
        if($week!==null){$where.=" AND JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.week'))=?";$values[]=$week;}
        if(isset($filters['audit_key'])){if(!is_string($filters['audit_key'])||!preg_match('/^[a-f0-9]{32}$/D',$filters['audit_key']))throw new RuntimeException('Invalid area audit.');$where.=' AND a.target_id=?';$values[]=$filters['audit_key'];}
        if($before){$where.=' AND a.id<?';$values[]=$before;}
        $rows=$repo->run('SELECT a.id,a.target_id,a.details,a.actor_id,a.occurred_at,u.name actor_name FROM security_audit a LEFT JOIN users u ON u.id=a.actor_id WHERE '.$where.' ORDER BY a.id DESC LIMIT 51',$values)->fetchAll();
        $more=count($rows)>50;if($more)array_pop($rows);$keys=array_column($rows,'target_id');$latest=[];
        if($keys){$marks=implode(',',array_fill(0,count($keys),'?'));foreach($repo->run("SELECT a.id,a.target_id,a.details,a.occurred_at,u.name actor_name FROM security_audit a JOIN (SELECT MAX(id) id FROM security_audit WHERE action='stay.area_followup' AND target_type='area_audit' AND target_id IN ($marks) GROUP BY target_id) v ON v.id=a.id LEFT JOIN users u ON u.id=a.actor_id",$keys)->fetchAll() as $item)$latest[$item['target_id']]=$item;}
        foreach($rows as &$row){$row['data']=json_decode($row['details'],true,512,JSON_THROW_ON_ERROR);$row['version']=(string)$row['id'];$row['followup']=null;if(isset($latest[$row['target_id']])){$last=$latest[$row['target_id']];$row['followup']=json_decode($last['details'],true,512,JSON_THROW_ON_ERROR)+['actor_name'=>$last['actor_name'],'occurred_at'=>$last['occurred_at']];$row['data']['status']=$row['followup']['status'];$row['data']['owner']=$row['followup']['owner'] ?? $row['data']['owner'];$row['data']['due_date']=$row['followup']['due_date'] ?? $row['data']['due_date'];$row['version']=(string)$last['id'];}unset($row['details']);}unset($row);
        $history=[];$historyNext=null;$historyKey=$filters['history'] ?? '';
        if($historyKey!==''){
            if(!is_string($historyKey)||!preg_match('/^[a-f0-9]{32}$/D',$historyKey))throw new RuntimeException('Invalid area audit history.');
            $historyBefore=filter_var($filters['history_before'] ?? '0',FILTER_VALIDATE_INT);if($historyBefore===false||$historyBefore<0)throw new RuntimeException('Invalid history cursor.');
            $params=[$historyKey];if($historyBefore)$params[]=$historyBefore;
            $history=$repo->run("SELECT a.id,a.action,a.details,a.occurred_at,u.name actor_name FROM security_audit a LEFT JOIN users u ON u.id=a.actor_id WHERE a.target_type='area_audit' AND a.target_id=? AND a.action IN ('stay.area_audit','stay.area_followup')".($historyBefore?' AND a.id<?':'').' ORDER BY a.id DESC LIMIT 51',$params)->fetchAll();
            if(count($history)>50){array_pop($history);$historyNext=(int)end($history)['id'];}
            foreach($history as &$event){$event['data']=json_decode($event['details'],true,512,JSON_THROW_ON_ERROR);unset($event['details']);}unset($event);
        }
        return ['rows'=>$rows,'week'=>$week,'next_before'=>$more?(int)end($rows)['id']:null,'history'=>$history,'history_next'=>$historyNext];
    }
}
