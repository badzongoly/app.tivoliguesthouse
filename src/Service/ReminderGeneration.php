<?php
namespace Tivoli\Service;
use RuntimeException;
use Throwable;
use Tivoli\Security\PermissionDenied;

trait ReminderGeneration
{
    public function generate(int $actor,string $key):array{
        $this->requireOwner($actor);
        return $this->journal->operation($actor,'generate',[],$key,function()use($actor){
            $this->requireOwner($actor);$this->sources->reset();$this->sources->ensureRoutines($actor);$snapshot=$this->sources->all($actor,true);$rows=$snapshot['rows'];$added=0;$pings=0;$members=[];$more=false;$today=substr($this->now(),0,10);$daily=substr($this->now(),11,5)>=$this->rules()['daily_time'];
            foreach($rows as $row){if($row['resolved'])continue;$recipients=[];$responsible=(int)($row['responsible_id']??0);if($row['decision']||$row['critical'])$recipients[]=$actor;if(str_starts_with($row['key'],'cash:'))$recipients=array_merge($recipients,$this->sources->cashRecipients($actor,$row['key']));elseif($responsible>0)$recipients[]=$responsible;if($this->sources->shouldReceive($row,$actor))$recipients[]=$actor;if($row['due']!==''&&$row['due']<$today)$recipients[]=$actor;
                foreach(array_unique($recipients) as $recipient){if(!$this->sources->active($recipient))continue;try{$current=$recipient===$actor?$row:$this->sources->inspect($recipient,$row['key'],true);if($current['resolved']||!($current['actionable']??true))continue;}catch(PermissionDenied){continue;}catch(Throwable){if($recipient!==$actor)continue;$current=$row;$current['unavailable']=true;}
                    $episode=$this->sources->episode($row);$made=$this->journal->unique('n_notice',[$row['key'],$episode,$recipient],fn($hash)=>$this->journal->event($actor,'nt_notice','n_notice',$hash,['recipient'=>$recipient,'source'=>$row['key'],'episode'=>$episode,'critical'=>$row['critical'],'category'=>$row['category']]));$added+=(int)$made['created'];
                    if($made['created']||$daily){$ping=$this->journal->unique('n_ping',[$made['id'],$today],fn($hash)=>$this->journal->event($actor,'nt_ping','n_ping',(string)$made['id'],['slot'=>$today]));$pings+=(int)$ping['created'];}
                    if(!$current['unavailable'])$members[$recipient][]=['notice'=>$made['id'],'source'=>$row['key'],'episode'=>$episode,'critical'=>$row['critical']];
                    if($added+$pings>=1000){$more=true;break 2;}
                }
            }
            $this->queueDeliveries($actor,$members,$daily);$id=$this->journal->event($actor,'nt_checked','n_heartbeat',PHP_SAPI==='cli'?'worker':'owner_check',['added'=>$added,'pings'=>$pings,'remaining'=>$more,'coverage'=>$snapshot['coverage'],'mode'=>PHP_SAPI==='cli'?'worker':'owner_check']);
            return ['id'=>$id,'added'=>$added,'pings'=>$pings,'remaining'=>$more];
        });
    }
}
