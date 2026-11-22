<?php
namespace Tivoli\Service;
use RuntimeException;
use Throwable;
use Tivoli\Repository\NotificationJournal;
use Tivoli\Security\PermissionDenied;

final class ReminderService
{
    use ReminderGeneration,ReminderDelivery,ReminderReceipts,ReminderActivity;
    public readonly ReminderSources $sources;
    private readonly NotificationJournal $journal;
    public function __construct(private readonly AccessService $access,private readonly ?string $clock,private readonly array $config,?array $onlyKeys=null){$this->sources=new ReminderSources($access,$this->now(),$config,$onlyKeys);$this->journal=new NotificationJournal($access,(string)$config['audit_key'],$this->now());}
    private function now():string{return $this->clock??gmdate('Y-m-d H:i:s');}
    public function owner(int $actor):bool{return $actor>0&&$actor===$this->sources->owner();}
    public function permit(int $actor):void{if(!$this->sources->active($actor))throw new PermissionDenied('This account is unavailable.');if(!(new FinanceService($this->access))->installed())throw new RuntimeException('The existing operational setup is required for reminders.');}
    private function requireOwner(int $actor):void{$this->permit($actor);if(!$this->owner($actor))throw new PermissionDenied('Only the designated owner manages shared reminders.');}
    public function prefs(int $actor):array{
        $this->permit($actor);$u=$this->access->repo->user($actor);$e=$this->journal->latest('n_pref',(string)$actor);$p=($e['data']??[])+['email'=>false,'whatsapp'=>false,'consent'=>false,'phone'=>''];$p['email_address']=$u['email'];$p['version']=$e['id']??0;
        foreach(['email','whatsapp'] as $channel){$value=$channel==='email'?$u['email']:$p['phone'];$verified=$this->journal->latest('n_verified',$actor.':'.$channel);$p[$channel.'_verified']=$value!==''&&$verified&&hash_equals($verified['data']['fingerprint'],$this->contactFingerprint($actor,$channel,$value));}return $p;
    }
    private function contactFingerprint(int $actor,string $channel,string $value):string{return $this->journal->hash(['contact',$actor,$channel,$value]);}
    public function rules():array{$e=$this->journal->latest('n_rules','global');return ($e['data']??[])+['daily_time'=>'09:00','weekly_auditor'=>0,'version'=>$e['id']??0];}
    public function contacts(int $actor):array{
        $this->requireOwner($actor);$out=[];foreach($this->access->repo->run("SELECT DISTINCT target_id FROM finance_events WHERE target_type='n_pref' ORDER BY target_id")->fetchAll(\PDO::FETCH_COLUMN) as $id){if(!$this->sources->active((int)$id))continue;$p=$this->prefs((int)$id);if($p['email']||$p['whatsapp'])$out[]=['id'=>(int)$id,'name'=>$this->access->repo->user((int)$id)['name'],'prefs'=>$p];}return $out;
    }
    public function execute(int $actor,string $action,array $in,string $key):array{
        $this->permit($actor);foreach($in as $v)if(!is_string($v))throw new RuntimeException('Invalid reminder form.');unset($in['csrf'],$in['operation_key'],$in['action']);ksort($in);if(in_array($action,['rules','verify_contact','generate'],true))$this->requireOwner($actor);
        if($action==='generate')return $this->generate($actor,$key);
        return $this->journal->operation($actor,$action,$in,$key,function()use($actor,$action,$in){
            $this->permit($actor);
            if($action==='preferences'){
                if(array_diff(array_keys($in),['email','whatsapp','consent','phone','version']))throw new RuntimeException('Edit only your own delivery preferences.');
                $p=$this->prefs($actor);$next=['email'=>($in['email']??'')==='1','whatsapp'=>($in['whatsapp']??'')==='1','consent'=>($in['consent']??'')==='1','phone'=>trim($in['phone']??'')];
                if($next['phone']!==''&&!preg_match('/^\+[1-9][0-9]{7,14}$/D',$next['phone']))throw new RuntimeException('Enter your notification number with + and its country code.');if($next['whatsapp']&&(!$next['consent']||$next['phone']===''))throw new RuntimeException('WhatsApp requires your number and explicit consent.');
                if((string)$p['version']!==($in['version']??''))throw new RuntimeException('Your preferences changed. Reload before saving.');$current=array_intersect_key($p,$next);if($next===$current)return ['changed'=>false];$id=$this->journal->event($actor,'nt_preferences','n_pref',(string)$actor,$next);$this->cancelUnsent($actor);return ['changed'=>true,'id'=>$id];
            }
            if($action==='rules'){$this->requireOwner($actor);if(array_diff(array_keys($in),['daily_time','weekly_auditor','version']))throw new RuntimeException('Invalid reminder rules.');if(!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$in['daily_time']??''))throw new RuntimeException('Choose a valid daily UTC time.');$p=$this->rules();if((string)$p['version']!==($in['version']??''))throw new RuntimeException('Reminder rules changed. Reload.');$auditor=filter_var($in['weekly_auditor']??'0',FILTER_VALIDATE_INT);if($auditor===false||$auditor<0)throw new RuntimeException('Choose a valid authorized auditor.');if($auditor>0){if(!$this->sources->active($auditor))throw new RuntimeException('The auditor account is unavailable.');(new WeeklyAuditService($this->access,$this->now()))->requireAuditor($auditor);}$id=$this->journal->event($actor,'nt_rules','n_rules','global',['daily_time'=>$in['daily_time'],'weekly_auditor'=>$auditor]);return ['id'=>$id];}
            if($action==='verify_contact'){
                $this->requireOwner($actor);$id=filter_var($in['user_id']??'',FILTER_VALIDATE_INT);$ch=$in['channel']??'';if(!$id||!in_array($ch,['email','whatsapp'],true)||($in['verified']??'')!=='1'||mb_strlen(trim($in['reason']??''))<12||mb_strlen($in['reason'])>500)throw new RuntimeException('Confirm independently verified contact ownership and record its evidence.');
                $p=$this->prefs($id);$value=$ch==='email'?$p['email_address']:$p['phone'];if(!$p[$ch]||$value===''||($ch==='whatsapp'&&!$p['consent']))throw new RuntimeException('The recipient must enable this channel first.');$fingerprint=$this->contactFingerprint($id,$ch,$value);if(!hash_equals($fingerprint,$in['fingerprint']??''))throw new RuntimeException('The contact changed. Recheck its current ownership.');$event=$this->journal->event($actor,'nt_contact_verified','n_verified',$id.':'.$ch,['fingerprint'=>$fingerprint,'reason'=>trim($in['reason'])]);return ['id'=>$event];
            }
            if($action==='read'){
                $id=filter_var($in['id']??'',FILTER_VALIDATE_INT);if(!$id)throw new RuntimeException('Choose your reminder.');$e=$this->journal->find($id);if($e['kind']!=='nt_notice'||(int)$e['data']['recipient']!==$actor)throw new PermissionDenied('Only your own reminders are available.');$this->sources->reset();$this->sources->inspect($actor,$e['data']['source'],$this->access->repo->db->inTransaction());$ping=$this->journal->latest('n_ping',(string)$id);$seen=$this->journal->latest('n_read',(string)$id);$cursor=(int)($ping['id']??$id);if(($seen['data']['cursor']??0)>=$cursor)return ['id'=>$id,'changed'=>false];$this->journal->event($actor,'nt_read','n_read',(string)$id,['cursor'=>$cursor]);return ['id'=>$id,'changed'=>true];
            }
            throw new RuntimeException('Unknown reminder action.');
        });
    }
    public function contactToken(int $actor,int $user,string $channel):string{$this->requireOwner($actor);$p=$this->prefs($user);return $this->contactFingerprint($user,$channel,$channel==='email'?$p['email_address']:$p['phone']);}
    public function inbox(int $actor,bool $history=false):array{
        $this->permit($actor);$this->sources->reset();$rows=[];$unavailable=false;$seen=[];
        foreach(array_reverse($this->journal->events('n_notice')) as $e){$d=$e['data'];if((int)$d['recipient']!==$actor||!$this->sources->accepts($d['source']))continue;try{$row=$this->sources->inspect($actor,$d['source']);}catch(PermissionDenied){continue;}catch(Throwable){$row=['key'=>$d['source'],'title'=>'Original Source Unavailable','state'=>'Source Unavailable','resolved'=>false,'unavailable'=>true,'path'=>'','due'=>'','actionable'=>true];$unavailable=true;}
            if($row['unavailable'])$unavailable=true;
            $current=!$row['resolved']&&($row['actionable']??true)&&$this->sources->shouldReceive($row,$actor);if($this->owner($actor)&&!$row['unavailable']&&$d['episode']!==$this->sources->episode($row))$current=false;if($history===$current)continue;if(isset($seen[$d['source']])&&!$history)continue;$seen[$d['source']]=true;
            $ping=$this->journal->latest('n_ping',(string)$e['id']);$read=$this->journal->latest('n_read',(string)$e['id']);$rows[]=['id'=>$e['id'],'row'=>$row,'unread'=>(int)($read['data']['cursor']??0)<(int)($ping['id']??$e['id']),'created_at'=>$e['created_at'],'last_reminded_at'=>$ping['created_at']??$e['created_at']];
        }
        $heartbeat=$this->journal->latest('n_heartbeat','worker');$manual=$this->journal->latest('n_heartbeat','owner_check');$latest=($manual['id']??0)>($heartbeat['id']??0)?$manual:$heartbeat;$coverage=$latest['data']['coverage']??[];$remaining=$this->owner($actor)&&($latest['data']['remaining']??false);$limited=$this->owner($actor)&&in_array('restricted',$coverage,true);if($this->owner($actor)&&in_array('unavailable',$coverage,true))$unavailable=true;
        return ['rows'=>$rows,'unavailable'=>$unavailable,'remaining'=>$remaining,'limited'=>$limited,'heartbeat'=>$heartbeat];
    }
}
