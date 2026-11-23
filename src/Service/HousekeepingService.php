<?php
namespace Tivoli\Service;
use Tivoli\Domain\Stay\StayRules as R;
use Tivoli\Security\PermissionDenied;
use RuntimeException;
use Throwable;

/** Named cleaning attempts, independent inspections and bounded owner exceptions. */
final class HousekeepingService
{
    public function __construct(private readonly AccessService $access,private readonly ?string $clock=null){}
    private function now():string{return $this->clock??gmdate('Y-m-d H:i:s');}
    private function hash(mixed $v):string{return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));}
    public function owner(int $actor):bool{return (new FinanceService($this->access,$this->now()))->canPostLate($actor);}
    public function permit(int $actor):void{
        $r=$this->access->repo;$u=$r->user($actor);
        if(!$u||!$u['active']||!array_intersect(['bookings.manage','rooms.manage'],$r->privileges($actor)))throw new PermissionDenied('You cannot access housekeeping.');
        if(!(new FinanceService($this->access))->installed())throw new RuntimeException('The existing finance setup must be active.');
        if($r->run("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='hr_staff'")->fetchColumn()
            &&$r->run("SELECT id FROM hr_staff WHERE user_id=? AND (status IN ('suspended','exited') OR (status='exit_scheduled' AND exit_at<=?)) LIMIT 1",[$actor,substr($this->now(),0,10)])->fetchColumn())throw new PermissionDenied('This staff account is not eligible for housekeeping.');
    }
    private function id(mixed $v):int{if(!is_string($v)||!preg_match('/^[1-9][0-9]{0,17}$/D',$v))throw new RuntimeException('Choose a valid room or attempt.');return (int)$v;}
    public static function hasAttempts(\Tivoli\Repository\AccessRepository $r,int $room):bool{
        if(!$r->run("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='finance_events'")->fetchColumn())return false;
        return (bool)$r->run("SELECT id FROM finance_events WHERE target_type='housekeeping' AND kind='hk_started' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.room_id'))=? LIMIT 1",[(string)$room])->fetchColumn();
    }
    private function latest(int $room):int{return (int)$this->access->repo->run("SELECT id FROM finance_events WHERE target_type='housekeeping' AND kind='hk_started' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.room_id'))=? ORDER BY id DESC LIMIT 1",[(string)$room])->fetchColumn();}
    public function room(int $actor,int $room):array{
        $this->permit($actor);$facts=(new StayService($this->access,$this->now()))->housekeepingRoom($room);$latest=$this->latest($room);
        return ['facts'=>$facts,'latest'=>$latest,'token'=>$this->hash([$facts,$latest])];
    }
    private function event(int $actor,string $kind,array $data,int $id=0):int{
        $this->access->repo->run('INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES(?,?,?,?,?,?,?,?)',[$kind,'housekeeping',(string)$id,0,substr($this->now(),0,10),$actor,json_encode($data,JSON_THROW_ON_ERROR),$this->now()]);
        return (int)$this->access->repo->db->lastInsertId();
    }
    private function load(int $actor,int $id):array{
        $this->permit($actor);$r=$this->access->repo;$root=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.id=? AND e.kind='hk_started' AND e.target_type='housekeeping'",[$id])->fetch();
        if(!$root)throw new RuntimeException('Housekeeping attempt not found.');$root['data']=json_decode($root['payload'],true,512,JSON_THROW_ON_ERROR);
        $history=$r->run("SELECT e.*,u.name actor_name FROM finance_events e JOIN users u ON u.id=e.actor_id WHERE e.target_type='housekeeping' AND e.target_id=? ORDER BY e.id",[(string)$id])->fetchAll();
        $status='cleaning';$step=0;$done=[];$comment='';$exception=null;$inspections=[];$reviews=[];
        foreach($history as &$e){$e['data']=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);$d=$e['data'];
            switch($e['kind']){
                case 'hk_progress':$step=$d['step'];$done=$d['done'];$comment=$d['comment'];break;
                case 'hk_completed':$done=$d['done'];$comment=$d['comment'];$status='inspection';break;
                case 'hk_exception_requested':$exception=$e;$exception['status']=$d['owner_authorized']?'approved':'pending';break;
                case 'hk_exception_decided':$exception['status']=$d['decision']==='approve'?'approved':'rejected';$exception['approval']=$e;break;
                case 'hk_inspected':$status=$d['decision']==='release'?'released':'returned';$inspections[]=$e;if($exception)$exception['status']=$d['self']?'used':'closed-unused';break;
                case 'hk_followup':$reviews[(int)$d['inspection_id']]=$e;break;
                case 'hk_superseded':$status='superseded';break;
            }
        }unset($e);
        $followups=array_values(array_filter($inspections,fn($e)=>$e['data']['self']&&!isset($reviews[(int)$e['id']])));
        return compact('root','history','status','step','done','comment','exception','inspections','reviews','followups')+['version'=>$this->hash([$id,array_column($history,'id')])];
    }
    public function detail(int $actor,int $id):array{
        $c=$this->load($actor,$id);$d=$c['root']['data'];
        try{$view=$this->room($actor,(int)$d['room_id']);$f=$view['facts'];$current=$view['latest']===$id;
            $expected=match($c['status']){'cleaning','returned'=>['dirty','cleaning'],'inspection'=>['dirty','awaiting_inspection'],'released'=>['vacant','ready'],default=>null};
            $valid=$current&&$expected&&hash_equals($d['cycle'],$f['cycle'])&&!$f['occupied']&&!$f['blocked']&&$f['status']===$expected[0]&&$f['ready_state']===$expected[1];
        }catch(RuntimeException $e){if($e instanceof \PDOException||$e instanceof PermissionDenied)throw $e;$view=['facts'=>null,'latest'=>0,'token'=>''];$current=false;$valid=false;}
        return $c+['room'=>$view,'current'=>$current,'valid'=>(bool)$valid];
    }

    /** Read-only Room Board projection; all writes still use the guarded service. */
    public function roomSummaries(int $actor,array $rooms):array{
        $this->permit($actor);$r=$this->access->repo;$latest=[];
        $roots=$r->run("SELECT e.id,e.actor_id,e.payload FROM finance_events e JOIN (SELECT MAX(id) id FROM finance_events WHERE target_type='housekeeping' AND kind='hk_started' GROUP BY JSON_UNQUOTE(JSON_EXTRACT(payload,'$.room_id'))) last_attempt ON last_attempt.id=e.id")->fetchAll();
        $ids=[];foreach($roots as $row){$d=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);$latest[(int)$d['room_id']]=['id'=>(int)$row['id'],'actor'=>(int)$row['actor_id'],'cycle'=>$d['cycle'],'status'=>'cleaning'];$ids[]=(string)$row['id'];}
        $states=[];
        if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));foreach($r->run("SELECT target_id,kind,payload FROM finance_events WHERE target_type='housekeeping' AND target_id IN ($marks) AND kind IN ('hk_completed','hk_inspected','hk_superseded') ORDER BY id",$ids)->fetchAll() as $row){$d=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);$states[$row['target_id']]=match($row['kind']){'hk_completed'=>'inspection','hk_inspected'=>$d['decision']==='release'?'released':'returned',default=>'superseded'};}}
        $out=[];foreach($rooms as $room){$id=(int)$room['id'];$a=$latest[$id]??null;if($a)$a['status']=$states[(string)$a['id']]??'cleaning';
            $blocked=!empty($room['blocked'])||$room['status']==='maintenance'||$room['ready_state']==='blocked';
            $state=!empty($room['booking_id'])?'occupied':($blocked?'blocked':($room['ready_state']==='ready'?'ready':($room['ready_state']==='awaiting_inspection'?'inspection':($a&&$a['status']==='cleaning'?'cleaning':'needs_cleaning'))));
            if($a&&in_array($state,['cleaning','inspection'],true)&&!hash_equals($a['cycle'],(new StayService($this->access,$this->now()))->housekeepingRoom($id)['cycle']))$state='needs_cleaning';
            $action=null;$form='';
            if($state==='needs_cleaning')$action='Start Cleaning';
            elseif($state==='cleaning'){$action=$a&&$a['actor']===$actor?'Continue Cleaning':'View Cleaning';$form=$a&&$a['actor']===$actor?'clean':'';}
            elseif($state==='inspection'){$action=$a&&$a['actor']!==$actor?'Inspect & Release Room':'Review Inspection';$form=$a&&$a['actor']!==$actor?'inspect':'';}
            $out[$id]=['state'=>$state,'attempt_id'=>$a['id']??0,'action'=>$action,'form'=>$form];
        }return $out;
    }
    /** Only explicit new links are used. Never substitute the room's latest guest. */
    public function preparationForStay(int $actor,int $booking):?array{
        $this->access->requirePrivilege($actor,'bookings.manage');
        $row=$this->access->repo->run("SELECT id FROM finance_events WHERE kind='hk_started' AND target_type='housekeeping' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.source_booking_id'))=? ORDER BY id DESC LIMIT 1",[(string)$booking])->fetchColumn();
        if(!$row)return null;$c=$this->load($actor,(int)$row);
        return ['id'=>(int)$row,'status'=>$c['status'],'room_id'=>(int)$c['root']['data']['room_id']];
    }
    private function sourceBooking(int $room,?array $previous):?int{
        $b=$this->access->repo->run('SELECT b.id,b.status,d.departed_at FROM bookings b LEFT JOIN stay_departures d ON d.booking_id=b.id WHERE b.room_id=? ORDER BY b.checkin_at DESC,b.id DESC LIMIT 1',[$room])->fetch();
        if(!$b||$b['status']!=='checked_out'||!$b['departed_at'])return null;
        if($previous){$linked=$previous['root']['data']['source_booking_id']??null;
            if((int)$linked===(int)$b['id'])return $previous['status']==='released'?null:(int)$b['id'];
            if(!$linked&&$previous['root']['created_at']>=$b['departed_at'])return null;
        }return (int)$b['id'];
    }

    public function board(int $actor,int $before=0):array{
        $this->permit($actor);$ids=$this->access->repo->run('SELECT id FROM rooms'.($before?' WHERE id<?':'').' ORDER BY id DESC LIMIT 51',$before?[$before]:[])->fetchAll(\PDO::FETCH_COLUMN);$more=count($ids)>50;if($more)array_pop($ids);$rows=[];
        foreach($ids as $id){$room=$this->room($actor,(int)$id);$room['attempt']=$room['latest']?$this->detail($actor,$room['latest']):null;$rows[]=$room;}
        return ['rows'=>$rows,'next'=>$more?(int)end($ids):0];
    }
    public function history(int $actor,int $before=0):array{
        $this->permit($actor);$allActors=$this->access->repo->isAdmin($actor);$scope=$allActors?'':' AND actor_id=?';$params=$allActors?[]:[$actor];if($before)$params[]=$before;
        $ids=$this->access->repo->run("SELECT id FROM finance_events WHERE target_type='housekeeping' AND kind='hk_started'".$scope.($before?' AND id<?':'').' ORDER BY id DESC LIMIT 51',$params)->fetchAll(\PDO::FETCH_COLUMN);$more=count($ids)>50;if($more)array_pop($ids);
        return ['rows'=>array_map(fn($id)=>$this->detail($actor,(int)$id),$ids),'next'=>$more?(int)end($ids):0];
    }
    public function attention(int $actor):array{
        $this->permit($actor);if(!$this->owner($actor))throw new PermissionDenied('Only Eugene can review housekeeping exceptions.');
        // Attention can only arise from an undecided exception or an unreviewed self-inspection.
        // Read these small linked events first; ordinary completed history need not load room facts.
        $metadata=[];
        foreach($this->access->repo->run("SELECT id,target_id,kind,payload FROM finance_events WHERE target_type='housekeeping' AND kind IN ('hk_exception_requested','hk_exception_decided','hk_inspected','hk_followup','hk_superseded') ORDER BY id")->fetchAll() as $event){
            $key=(string)$event['target_id'];if(!isset($metadata[$key]))$metadata[$key]=['pending'=>false,'self'=>[],'reviewed'=>[]];$state=&$metadata[$key];$data=json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR);
            switch($event['kind']){
                case 'hk_exception_requested':$state['pending']=empty($data['owner_authorized']);break;
                case 'hk_exception_decided':case 'hk_superseded':$state['pending']=false;break;
                case 'hk_inspected':$state['pending']=false;if(!empty($data['self']))$state['self'][(int)$event['id']]=true;break;
                case 'hk_followup':$state['reviewed'][(int)$data['inspection_id']]=true;break;
            }unset($state);
        }
        $ids=$this->access->repo->run("SELECT id FROM finance_events WHERE target_type='housekeeping' AND kind='hk_started' ORDER BY id DESC")->fetchAll(\PDO::FETCH_COLUMN);
        $ids=array_values(array_filter($ids,function($id)use($metadata){$state=$metadata[(string)$id]??null;return $state&&($state['pending']||array_diff_key($state['self'],$state['reviewed']));}));$requests=[];$followups=[];
        foreach($ids as $id){$c=$this->detail($actor,(int)$id);if($c['valid']&&$c['status']==='inspection'&&($c['exception']['status']??'')==='pending')$requests[]=$c;foreach($c['followups'] as $inspection)$followups[]=['case'=>$c,'inspection'=>$inspection];}
        return ['requests'=>$requests,'followups'=>$followups,'count'=>count($requests)+count($followups)];
    }
    public function tasks(int $actor):array{
        $this->permit($actor);$ids=$this->access->repo->run("SELECT id FROM finance_events WHERE target_type='housekeeping' AND kind='hk_started' AND actor_id=? ORDER BY id",[$actor])->fetchAll(\PDO::FETCH_COLUMN);$rows=[];
        foreach($ids as $id){$c=$this->detail($actor,(int)$id);if($c['current']&&in_array($c['status'],['cleaning','inspection','returned'],true))$rows[]=$c;}return $rows;
    }
    private function ensureRoom(array $c):void{if(!$c['valid'])throw new RuntimeException('Room occupancy, safety or attempt state changed. Review the current room and start a fresh cleaning attempt if needed.');}
    private function identifiers(array $list):array{return array_merge([], ...array_map(fn($s)=>array_column($s['items'],'id'),$list['sections']));}
    public function execute(int $actor,string $action,array $in,string $key):array{
        try{
            $this->permit($actor);if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new RuntimeException('Reload the form before submitting.');
            $file=null;if(isset($in['_evidence'])){if($action!=='inspect'&&!($action==='progress'&&($in['direction']??'')==='complete'))throw new RuntimeException('Evidence belongs to completed cleaning or inspection.');$file=FinanceAttachment::checked($in['_evidence']);unset($in['_evidence']);}
            foreach($in as $v)if(!is_string($v))throw new RuntimeException('Invalid housekeeping form.');unset($in['csrf'],$in['operation_key'],$in['action']);ksort($in);$fingerprint=$this->hash(['housekeeping.'.$action,$in,$file?FinanceAttachment::meta($file):null]);
            return $this->access->transaction(function()use($actor,$action,$in,$key,$file,$fingerprint){
                $this->permit($actor);$r=$this->access->repo;$prior=$r->run('SELECT * FROM finance_operations WHERE actor_id=? AND operation_key=?',[$actor,$key])->fetch();
                if($prior){if(!hash_equals($prior['fingerprint'],$fingerprint))throw new RuntimeException('This form was already used with different details.');$e=$r->run("SELECT id,kind,target_id FROM finance_events WHERE id=? AND target_type='housekeeping'",[$prior['event_id']])->fetch();if(!$e)throw new RuntimeException('This operation belongs to another workflow.');$id=$e['kind']==='hk_started'?(int)$e['id']:(int)$e['target_id'];$this->access->audit($actor,'housekeeping.replayed','housekeeping',(string)$id,'recorded');return ['id'=>$id,'event_id'=>(int)$e['id'],'replayed'=>true];}
                [$id,$event]=$this->command($actor,$action,$in);
                if($file)FinanceAttachment::save($this->access,$actor,'housekeeping',$event,$file);
                $r->run('INSERT INTO finance_operations VALUES(?,?,?,?)',[$actor,$key,$fingerprint,$event]);$this->access->audit($actor,'housekeeping.'.($action==='progress'&&($in['direction']??'')==='complete'?'completed':$action),'housekeeping',(string)$id,'succeeded',['event_id'=>$event]);return ['id'=>$id,'event_id'=>$event,'replayed'=>false];
            });
        }catch(Throwable $e){$this->access->audit($actor,'housekeeping.failed','housekeeping',null,'denied',['action'=>substr($action,0,32)]);throw $e;}
    }
    private function start(int $actor,int $room,array $in,bool $restart=false):array{
        $view=$this->room($actor,$room);$f=$view['facts'];$previous=$view['latest']?$this->detail($actor,$view['latest']):null;
        if($f['occupied']||$f['blocked']||!in_array([$f['status'],$f['ready_state']],[['dirty','cleaning'],['dirty','awaiting_inspection'],['vacant','unverified']],true))throw new RuntimeException('Only an unused, unblocked room needing preparation can start cleaning.');
        if(!$restart&&!hash_equals($view['token'],$in['room_token']??''))throw new RuntimeException('The room changed. Reload before starting.');
        if(!$restart&&$previous&&$previous['valid']&&in_array($previous['status'],['cleaning','inspection'],true))throw new RuntimeException('This room already has a current cleaning assignment or completed cleaning.');
        if($previous&&in_array($previous['status'],['cleaning','inspection'],true))$this->event($actor,'hk_superseded',['reason'=>$restart?R::text($in['reason']??'',2000,true):'Room cycle or safety facts changed'],(int)$previous['root']['id']);
        $this->access->repo->run("INSERT IGNORE INTO stay_room_settings(room_id) VALUES(?)",[$room]);
        $this->access->repo->run("UPDATE rooms SET status='dirty' WHERE id=?",[$room]);$this->access->repo->run("UPDATE stay_room_settings SET ready_state='cleaning' WHERE room_id=?",[$room]);
        $id=$this->event($actor,'hk_started',['room_id'=>$room,'room_name'=>$f['name'],'source_booking_id'=>$this->sourceBooking($room,$previous),'cycle'=>$f['cycle'],'cleaning'=>CleaningChecklist::current($this->access->repo),'inspection'=>CleaningChecklist::current($this->access->repo,'inspection'),'previous_id'=>$view['latest']?:null,'restart_reason'=>$restart?R::text($in['reason']??'',2000,true):'']);return [$id,$id];
    }
    private function command(int $actor,string $action,array $in):array{
        if($action==='start')return $this->start($actor,$this->id($in['room_id']??''),$in);
        $id=$this->id($in['id']??'');$c=$this->detail($actor,$id);$d=$c['root']['data'];$cleaner=(int)$c['root']['actor_id']===$actor;
        if(!hash_equals($c['version'],$in['version']??''))throw new RuntimeException('This attempt changed. Reload its current history.');
        $r=$this->access->repo;
        if($action==='followup'){
            if(!$this->owner($actor))throw new PermissionDenied('Only Eugene records self-inspection follow-up.');
            $inspection=$this->id($in['inspection_id']??'');if(!array_filter($c['followups'],fn($e)=>(int)$e['id']===$inspection))throw new RuntimeException('This self-inspection is already reviewed or does not belong here.');
            return [$id,$this->event($actor,'hk_followup',['inspection_id'=>$inspection,'reason'=>R::text($in['reason']??'',2000,true)],$id)];
        }
        if($action==='restart'){
            if(!$this->owner($actor))throw new PermissionDenied('Only Eugene may restart an abandoned assignment.');
            if(!$c['current']||!in_array($c['status'],['cleaning','inspection'],true))throw new RuntimeException('Only the latest unfinished attempt may be restarted.');
            R::text($in['reason']??'',2000,true);return $this->start($actor,(int)$d['room_id'],$in,true);
        }
        $this->ensureRoom($c);
        if($action==='progress'){
            if(!$cleaner||$c['status']!=='cleaning')throw new PermissionDenied('Only the assigned cleaner may complete this attempt.');
            $done=json_decode($in['done']??'',true,128,JSON_THROW_ON_ERROR);$ids=$this->identifiers($d['cleaning']);
            if(!is_array($done)||!array_is_list($done)||count(array_filter($done,'is_string'))!==count($done)||count($done)!==count(array_unique($done))||array_diff($done,$ids))throw new RuntimeException('Invalid checklist selection.');
            $direction=$in['direction']??'';if(!in_array($direction,['next','previous','pause','complete'],true))throw new RuntimeException('Choose the next checklist action.');
            $step=$c['step'];$current=$d['cleaning']['sections'][$step];if(in_array($direction,['next','complete'],true)&&array_diff(array_column($current['items'],'id'),$done))throw new RuntimeException('Complete every item in this step.');
            $comment=R::text($in['comment']??$c['comment'],2000);
            if($direction==='complete'){
                if($step!==count($d['cleaning']['sections'])-1||array_diff($ids,$done)||($in['verified']??'')!=='1')throw new RuntimeException('Finish every cleaning step and confirm actual completion.');
                $r->run("UPDATE stay_room_settings SET ready_state='awaiting_inspection' WHERE room_id=?",[$d['room_id']]);
                return [$id,$this->event($actor,'hk_completed',compact('done','comment'),$id)];
            }
            if($direction==='next')$step=min($step+1,count($d['cleaning']['sections'])-1);if($direction==='previous')$step=max(0,$step-1);
            return [$id,$this->event($actor,'hk_progress',compact('step','done','comment'),$id)];
        }
        if($action==='request_exception'){
            if(!$cleaner||$c['status']!=='inspection'||in_array($c['exception']['status']??'', ['pending','approved'],true))throw new RuntimeException('Only this completed attempt’s cleaner may request a current exception.');
            return [$id,$this->event($actor,'hk_exception_requested',['reason'=>R::text($in['reason']??'',2000,true),'owner_authorized'=>$this->owner($actor)],$id)];
        }
        if($action==='decide_exception'){
            if(!$this->owner($actor))throw new PermissionDenied('Only Eugene authorizes self-inspection.');
            if($c['status']!=='inspection'||($c['exception']['status']??'')!=='pending')throw new RuntimeException('This request is no longer pending.');
            $decision=$in['decision']??'';if(!in_array($decision,['approve','reject'],true))throw new RuntimeException('Approve or decline this exception.');
            return [$id,$this->event($actor,'hk_exception_decided',['decision'=>$decision,'reason'=>R::text($in['reason']??'',2000,true),'request_id'=>(int)$c['exception']['id']],$id)];
        }
        if($action==='inspect'){
            if($c['status']!=='inspection'||($in['verified']??'')!=='1')throw new RuntimeException('Complete cleaning before recording the actual inspection.');
            if($cleaner&&($c['exception']['status']??'')!=='approved')throw new PermissionDenied('A different inspector or Eugene’s one-attempt authorization is required.');
            $results=json_decode($in['results']??'',true,128,JSON_THROW_ON_ERROR);$ids=$this->identifiers($d['inspection']);if(!is_array($results)||count(array_filter($results,'is_string'))!==count($results))throw new RuntimeException('Record the inspection checklist.');
            $got=array_keys($results);sort($got);$expected=$ids;sort($expected);if($got!==$expected||array_diff(array_values($results),['pass','fail']))throw new RuntimeException('Record Pass or Needs Attention for every inspection item.');
            $decision=$in['decision']??'';if(!in_array($decision,['release','rework'],true))throw new RuntimeException('Release or return this room for cleaning.');
            $failed=array_keys(array_filter($results,fn($v)=>$v==='fail'));if(($decision==='release'&&$failed)||($decision==='rework'&&!$failed))throw new RuntimeException('Failed items require rework; a return must identify an item needing attention.');
            $self=$cleaner;$data=['results'=>$results,'decision'=>$decision,'failed'=>$failed,'comment'=>R::text($in['comment']??'',2000),'self'=>$self,'exception_request_id'=>$self?(int)$c['exception']['id']:null,'exception_decision_id'=>$self?(int)($c['exception']['approval']['id']??$c['exception']['id']):null];
            $r->run('UPDATE rooms SET status=? WHERE id=?',[$decision==='release'?'vacant':'dirty',$d['room_id']]);$r->run('UPDATE stay_room_settings SET ready_state=? WHERE room_id=?',[$decision==='release'?'ready':'cleaning',$d['room_id']]);
            return [$id,$this->event($actor,'hk_inspected',$data,$id)];
        }
        throw new RuntimeException('Unknown housekeeping action.');
    }
}
