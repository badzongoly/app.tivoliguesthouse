<?php
namespace Tivoli\Service;

use RuntimeException;

/** Small versioned checklist: immutable configuration events are its authoritative revisions.
 * Uses the existing indexed, append-only audit store; no filesystem writes or schema cutover.
 */
final class CleaningChecklist
{
    public static function current(\Tivoli\Repository\AccessRepository $repo,string $kind='cleaning'): array
    {
        if(!in_array($kind,['cleaning','inspection'],true))throw new RuntimeException('Invalid checklist type.');
        $row=$repo->run("SELECT id,details FROM security_audit WHERE action=? AND outcome='succeeded' ORDER BY id DESC LIMIT 1",['stay.'.$kind.'_checklist'])->fetch();
        if($row){$data=json_decode($row['details'],true,512,JSON_THROW_ON_ERROR);return ['revision'=>(string)$row['id'],'sections'=>$data['sections']];}
        $bytes=file_get_contents(dirname(__DIR__,2).'/resources/'.$kind.'-checklist.json');
        return ['revision'=>'default-'.hash('sha256',$bytes),'sections'=>json_decode($bytes,true,512,JSON_THROW_ON_ERROR)];
    }
    public static function complete(\Tivoli\Repository\AccessRepository $repo,array $input,string $kind='cleaning'): array
    {
        $list=self::current($repo,$kind);
        if(($input['checklist_revision'] ?? '')!==$list['revision'])throw new RuntimeException('The '.$kind.' checklist changed. Reload and review the updated checklist before submitting.');
        foreach($list['sections'] as $section)foreach($section['items'] as $item){
            if(($input['done_'.$item['id']] ?? '')!=='1')throw new RuntimeException('Complete every '.$kind.' checklist item before submitting.');
        }
        return $list;
    }
    /** Called only inside the authorised, serialised StayService transaction. */
    public static function change(\Tivoli\Repository\AccessRepository $repo,array $input,string $kind='cleaning'): array
    {
        $list=self::current($repo,$kind);
        if(($input['checklist_revision'] ?? '')!==$list['revision'])throw new RuntimeException('These settings changed. Reload before saving.');
        $count=0;foreach($list['sections'] as $section)$count+=count($section['items']);
        if(($input['change'] ?? '')==='add'){
            $text=\Tivoli\Domain\Stay\StayRules::text($input['item_text'] ?? '',500,true);
            if($count>=75)throw new RuntimeException('Keep the checklist within 75 items.');
            $found=false;foreach($list['sections'] as &$section){if($section['id']===($input['section_id'] ?? '')){
                foreach($section['items'] as $item)if(mb_strtolower($item['text'])===mb_strtolower($text))throw new RuntimeException('This item already exists in this section.');
                $section['items'][]=['id'=>'i'.bin2hex(random_bytes(8)),'text'=>$text];$found=true;
            }}unset($section);if(!$found)throw new RuntimeException('Choose a valid checklist section.');
        }elseif(($input['change'] ?? '')==='remove'){
            if($count<=1)throw new RuntimeException('Retain at least one checklist item.');
            $found=false;foreach($list['sections'] as &$section){foreach($section['items'] as $key=>$item)if($item['id']===($input['item_id'] ?? '')){unset($section['items'][$key]);$found=true;}$section['items']=array_values($section['items']);}unset($section);
            if(!$found)throw new RuntimeException('Checklist item not found.');
        }else throw new RuntimeException('Choose Add or Remove.');
        return ['audit'=>['previous_revision'=>$list['revision'],'change'=>$input['change'],'sections'=>$list['sections']]];
    }
}
