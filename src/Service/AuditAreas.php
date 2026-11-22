<?php
namespace Tivoli\Service;
use RuntimeException;
use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Repository\AccessRepository;
final class AuditAreas
{
    public static function current(AccessRepository $repo): array
    {
        $row=$repo->run("SELECT id,details FROM security_audit WHERE action='stay.area_catalogue' AND outcome='succeeded' ORDER BY id DESC LIMIT 1")->fetch();
        if($row){$data=json_decode($row['details'],true,512,JSON_THROW_ON_ERROR);return ['revision'=>(string)$row['id'],'entries'=>$data['entries']];}
        $bytes=file_get_contents(dirname(__DIR__,2).'/resources/audit-areas.json');return ['revision'=>'default-'.hash('sha256',$bytes),'entries'=>json_decode($bytes,true,512,JSON_THROW_ON_ERROR)];
    }
    /** Authorised and serialised by StayService. Removal is deactivation, never history deletion. */
    public static function change(AccessRepository $repo,array $in): array
    {
        $list=self::current($repo);if(($in['area_revision'] ?? '')!==$list['revision'])throw new RuntimeException('The area list changed. Reload before saving.');
        $name=R::text($in['name'] ?? '',120,true);$id=R::text($in['area_id'] ?? '',40);$active=$in['active'] ?? '1';if(!in_array($active,['0','1'],true))throw new RuntimeException('Choose Active or Inactive.');
        foreach($list['entries'] as $entry)if($entry['id']!==$id&&mb_strtolower($entry['name'])===mb_strtolower($name))throw new RuntimeException('An area with this name already exists. Reactivate that entry instead.');
        $before=null;
        if($id!==''){$found=false;foreach($list['entries'] as &$entry)if($entry['id']===$id){$before=$entry;$entry['name']=$name;$entry['active']=$active==='1';$found=true;}unset($entry);if(!$found)throw new RuntimeException('Area not found.');}
        else{if(count($list['entries'])>=100)throw new RuntimeException('Keep the area list within 100 entries.');$id='a'.bin2hex(random_bytes(8));$list['entries'][]=['id'=>$id,'name'=>$name,'active'=>$active==='1'];}
        return ['audit'=>['area_id'=>$id,'previous_revision'=>$list['revision'],'before'=>$before,'entries'=>$list['entries']]];
    }
}
