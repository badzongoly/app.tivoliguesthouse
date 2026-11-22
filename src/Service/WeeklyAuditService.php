<?php
namespace Tivoli\Service;
use RuntimeException;
use Throwable;
use Tivoli\Security\PermissionDenied;
use Tivoli\Domain\Stay\StayRules as R;

/** Follow-up controls around original room/area audits; no financial posting or room release. */
final class WeeklyAuditService
{
    public function __construct(private readonly AccessService $access,private readonly ?string $clock=null){}
    private function now():string{return $this->clock??gmdate('Y-m-d H:i:s');}
    private function hash(mixed $value):string{return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR));}
    public function owner(int $actor):bool{return (new FinanceService($this->access,$this->now()))->canPostLate($actor);}
    public function permit(int $actor):void{(new HousekeepingService($this->access,$this->now()))->permit($actor);}
    private function broadReader(int $actor):bool{return $this->owner($actor)||$this->access->repo->isAdmin($actor)&&in_array('rooms.manage',$this->access->repo->privileges($actor),true);}
    public function auditor(int $actor):bool{
        $this->permit($actor);$r=$this->access->repo;
        if($this->owner($actor)||$r->isAdmin($actor)&&in_array('rooms.manage',$r->privileges($actor),true))return true;
        $value=$r->run("SELECT payload FROM finance_events WHERE target_type='audit_duty' AND target_id=? AND kind='wa_duty' ORDER BY id DESC LIMIT 1",[(string)$actor])->fetchColumn();
        return $value&&(json_decode($value,true,512,JSON_THROW_ON_ERROR)['enabled']??false)===true;
    }
    public function requireAuditor(int $actor):void{if(!$this->auditor($actor))throw new PermissionDenied('Eugene must assign audit duty before you can record audits.');}
    public function staff(int $actor):array{
        $this->permit($actor);if(!$this->owner($actor))throw new PermissionDenied('Only Eugene chooses staff assignments.');$r=$this->access->repo;
        $hr=(bool)$r->run("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='hr_staff'")->fetchColumn();
        $rows=$r->run("SELECT DISTINCT u.id,u.name FROM users u JOIN security_accounts a ON a.user_id=u.id WHERE a.active=1 AND EXISTS (SELECT 1 FROM security_user_roles ur JOIN security_role_privileges rp ON rp.role_id=ur.role_id JOIN security_privileges p ON p.id=rp.privilege_id WHERE ur.user_id=u.id AND p.code IN ('bookings.manage','rooms.manage'))".($hr?" AND NOT EXISTS (SELECT 1 FROM hr_staff h WHERE h.user_id=u.id AND (h.status IN ('suspended','exited') OR (h.status='exit_scheduled' AND h.exit_at<=?)))":'')." ORDER BY u.name,u.id",$hr?[substr($this->now(),0,10)]:[])->fetchAll();
        $duties=[];foreach($r->run("SELECT e.target_id,e.payload FROM finance_events e JOIN (SELECT MAX(id) id FROM finance_events WHERE target_type='audit_duty' AND kind='wa_duty' GROUP BY target_id) latest ON latest.id=e.id")->fetchAll() as $d)$duties[(int)$d['target_id']]=json_decode($d['payload'],true,512,JSON_THROW_ON_ERROR)['enabled'];
        $admins=$r->run("SELECT DISTINCT ur.user_id FROM security_user_roles ur JOIN security_roles role ON role.id=ur.role_id AND role.name='admin' WHERE EXISTS (SELECT 1 FROM security_user_roles ur2 JOIN security_role_privileges rp ON rp.role_id=ur2.role_id JOIN security_privileges p ON p.id=rp.privilege_id WHERE ur2.user_id=ur.user_id AND p.code='rooms.manage')")->fetchAll(\PDO::FETCH_COLUMN);
        foreach($rows as &$u)$u['audit_duty']=(int)$u['id']===$actor||in_array((string)$u['id'],array_map('strval',$admins),true)||($duties[(int)$u['id']]??false);unset($u);return $rows;
    }
    public function dutyRoster(int $actor):array{
        $rows=$this->staff($actor);$known=array_column($rows,'id');$r=$this->access->repo;
        foreach($r->run("SELECT DISTINCT target_id FROM finance_events WHERE target_type='audit_duty' AND kind='wa_duty'")->fetchAll(\PDO::FETCH_COLUMN) as $id){if(in_array((int)$id,array_map('intval',$known),true))continue;$u=$r->user((int)$id);if(!$u)continue;$last=$r->run("SELECT payload FROM finance_events WHERE target_type='audit_duty' AND kind='wa_duty' AND target_id=? ORDER BY id DESC LIMIT 1",[(string)$id])->fetchColumn();$rows[]=['id'=>(int)$id,'name'=>$u['name'].' (No Longer Eligible)','audit_duty'=>json_decode($last,true,512,JSON_THROW_ON_ERROR)['enabled']];}return $rows;
    }
    private function id(mixed $value):int{if(!is_string($value)||!preg_match('/^[1-9][0-9]{0,17}$/D',$value))throw new RuntimeException('Choose a valid record.');return (int)$value;}
    private function event(int $actor,string $kind,array $data,int $id=0,string $type='weekly_audit'):int{
        $r=$this->access->repo;$r->run('INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES(?,?,?,?,?,?,?,?)',[$kind,$type,(string)$id,0,substr($this->now(),0,10),$actor,json_encode($data,JSON_THROW_ON_ERROR),$this->now()]);return (int)$r->db->lastInsertId();
    }
    public function association(string $kind,string $source):int{
        return (int)$this->access->repo->run("SELECT event_id FROM finance_claims WHERE scope='weekly_audit_source' AND identity_hash=?",[$this->hash([$kind,$source])])->fetchColumn();
    }
    /** Reconstruct only explicit source links; never infer an old fault or account from text. */
    public function source(int $actor,string $kind,string $id):array{
        $this->permit($actor);$r=$this->access->repo;
        if($kind==='room'){
            $row=$r->run('SELECT a.*,r.name location FROM stay_room_audits a JOIN rooms r ON r.id=a.room_id WHERE a.id=?',[$this->id($id)])->fetch();if(!$row)throw new RuntimeException('Room audit not found.');
            $meta=$r->run("SELECT details FROM security_audit WHERE action='stay.room_audit' AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.room_audit_id'))=? ORDER BY id DESC LIMIT 1",[$id])->fetchColumn();$d=$meta?json_decode($meta,true,512,JSON_THROW_ON_ERROR):[];
            $s=['kind'=>'room','source_id'=>$id,'location_id'=>(string)$row['room_id'],'location'=>$row['location'],'result'=>$row['result'],'comment'=>$row['reason'],'legacy_owner'=>$row['owner'],'actor_id'=>(int)$row['actor_id'],'created_at'=>$row['created_at'],'fault_id'=>(int)($d['fault_id']??0),'legacy_completed'=>false,'legacy_version'=>''];
        }elseif($kind==='area'){
            if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new RuntimeException('Invalid area audit.');
            $row=$r->run("SELECT * FROM security_audit WHERE action='stay.area_audit' AND target_type='area_audit' AND target_id=? ORDER BY id LIMIT 1",[$id])->fetch();if(!$row)throw new RuntimeException('Area audit not found.');$d=json_decode($row['details'],true,512,JSON_THROW_ON_ERROR);
            $last=$r->run("SELECT * FROM security_audit WHERE action='stay.area_followup' AND target_type='area_audit' AND target_id=? ORDER BY id DESC LIMIT 1",[$id])->fetch()?:$row;$current=json_decode($last['details'],true,512,JSON_THROW_ON_ERROR);
            $s=['kind'=>'area','source_id'=>$id,'location_id'=>$d['area_id'],'location'=>$d['area'],'result'=>$d['result'],'comment'=>$d['comment'],'legacy_owner'=>$current['owner']??$d['owner'],'legacy_due_date'=>$current['due_date']??'','legacy_followup'=>$current,'actor_id'=>(int)$row['actor_id'],'created_at'=>$row['occurred_at'],'fault_id'=>0,'legacy_completed'=>($current['status']??'')==='completed','legacy_version'=>(string)$last['id']];
        }else throw new RuntimeException('Choose Room Audit or Other Area Audit.');
        $s['followup_id']=$this->association($kind,$id);
        if(!$this->broadReader($actor)&&$s['actor_id']!==$actor){if(!$s['followup_id'])throw new PermissionDenied('You cannot view this audit.');$this->detail($actor,$s['followup_id']);}
        $s['token']=$this->hash($s);return $s;
    }
    /** Called by the original audit writer before commit, on the shared locked connection. */
    public function capture(int $actor,string $kind,string $source,array $in):int{
        if(!$this->access->repo->db->inTransaction())throw new RuntimeException('An audit transaction is required.');$this->requireAuditor($actor);
        $s=$this->source($actor,$kind,$source);if($s['result']==='pass')return 0;
        if($s['legacy_completed'])throw new RuntimeException('This historical area action was completed. Its history remains unchanged.');
        if($s['followup_id'])return $s['followup_id'];
        $id=isset($in['followup_id'])&&$in['followup_id']!==''?$this->id($in['followup_id']):0;
        if($id){$c=$this->detail($actor,$id);if(!hash_equals($c['version'],$in['followup_version']??''))throw new RuntimeException('The selected follow-up changed. Reload and review it.');if($c['status']==='closed'||$c['source']['kind']!==$kind||$c['source']['location_id']!==$s['location_id'])throw new RuntimeException('Choose an open action for this same location.');}
        else $id=$this->event($actor,'wa_created',['source'=>$s]);
        $this->access->repo->run('INSERT INTO finance_claims(scope,identity_hash,event_id) VALUES(?,?,?)',['weekly_audit_source',$this->hash([$kind,$source]),$id]);
        $event=$this->event($actor,'wa_source',['source'=>$s],$id);
        $this->access->audit($actor,'weekly_audit.source','weekly_audit',(string)$id,'succeeded',['event_id'=>$event,'kind'=>$kind,'source_id'=>$source]);return $id;
    }
    public function detail(int $actor,int $id):array{
        $this->permit($actor);$r=$this->access->repo;$root=$r->run("SELECT * FROM finance_events WHERE id=? AND target_type='weekly_audit' AND kind='wa_created'",[$id])->fetch();if(!$root)throw new RuntimeException('Follow-up not found.');$root['data']=json_decode($root['payload'],true,512,JSON_THROW_ON_ERROR);$source=$root['data']['source'];
        $history=$r->run("SELECT e.*,u.name actor_name FROM finance_events e LEFT JOIN users u ON u.id=e.actor_id WHERE target_type='weekly_audit' AND target_id=? ORDER BY e.id",[(string)$id])->fetchAll();$status='unassigned';$assignee=0;$due='';$sources=[];$bindings=[];$lastDone=null;
        foreach($history as &$e){$e['data']=json_decode($e['payload'],true,512,JSON_THROW_ON_ERROR);$d=$e['data'];switch($e['kind']){
            case 'wa_source':$sources[]=$d['source'];if($status==='review')$status=$assignee?'assigned':'unassigned';break;
            case 'wa_assigned':$assignee=$d['assignee'];$due=$d['due_date'];$status='assigned';break;
            case 'wa_done':$status='review';$lastDone=$e;break;
            case 'wa_returned':$status='assigned';break;
            case 'wa_closed':$status='closed';break;
            case 'wa_fault_linked':$bindings[$d['source_id']]=$d['fault_id'];break;
        }}unset($e);
        if(!$this->broadReader($actor)&&$assignee!==$actor&&!array_filter($sources,fn($s)=>$s['actor_id']===$actor))throw new PermissionDenied('This follow-up belongs to another staff member.');
        $faults=[];$unsafeArea=false;$unlinked=[];
        foreach($sources as &$s){if($s['kind']==='area'&&$s['result']==='unsafe')$unsafeArea=true;if($s['kind']!=='room'||$s['result']!=='unsafe')continue;$s['fault_id']=$bindings[$s['source_id']]??$s['fault_id'];if(!$s['fault_id']){$unlinked[]=$s['source_id'];continue;}$f=$r->run('SELECT id,room_id,priority,status FROM stay_faults WHERE id=?',[$s['fault_id']])->fetch();if(!$f||(string)$f['room_id']!==$s['location_id']||$f['priority']!=='Critical'){$unlinked[]=$s['source_id'];continue;}$faults[]=$f;}unset($s);
        $eligible=false;if($assignee)try{$this->permit($assignee);$eligible=true;}catch(PermissionDenied){}
        $version=$this->hash([$id,array_column($history,'id'),$faults,$unlinked,$eligible]);$name=$assignee?$r->run('SELECT name FROM users WHERE id=?',[$assignee])->fetchColumn():'';
        return compact('root','source','sources','history','status','assignee','due','faults','unlinked','unsafeArea','version','lastDone','eligible')+['assignee_name'=>$name,'overdue'=>$due!==''&&$due<substr($this->now(),0,10)&&$status!=='closed'];
    }
    public function listing(int $actor,bool $closed=false,int $before=0):array{
        $this->permit($actor);$rows=[];$next=0;$ids=$this->access->repo->run("SELECT id FROM finance_events WHERE kind='wa_created' AND target_type='weekly_audit'".($before?' AND id<?':'').' ORDER BY id DESC',$before?[$before]:[])->fetchAll(\PDO::FETCH_COLUMN);
        foreach($ids as $id){try{$c=$this->detail($actor,(int)$id);}catch(PermissionDenied){continue;}if(($c['status']==='closed')!==$closed)continue;if(count($rows)===50){$next=(int)end($rows)['root']['id'];break;}$rows[]=$c;}return compact('rows','next');
    }
    public function openChoices(int $actor,string $kind):array{
        $this->requireAuditor($actor);if(!in_array($kind,['room','area'],true))throw new RuntimeException('Invalid audit type.');$rows=[];
        $ids=$this->access->repo->run("SELECT e.id FROM finance_events e WHERE e.target_type='weekly_audit' AND e.kind='wa_created' AND JSON_UNQUOTE(JSON_EXTRACT(e.payload,'$.source.kind'))=? AND NOT EXISTS (SELECT 1 FROM finance_events closed WHERE closed.target_type='weekly_audit' AND CAST(closed.target_id AS BINARY)=CAST(e.id AS BINARY) AND closed.kind='wa_closed') ORDER BY e.id DESC",[$kind])->fetchAll(\PDO::FETCH_COLUMN);
        foreach($ids as $id){try{$c=$this->detail($actor,(int)$id);}catch(PermissionDenied){continue;}if($c['status']!=='closed')$rows[]=$c;}return $rows;
    }
    public function legacyOpen(int $actor,int $before=0):array{
        $this->permit($actor);if(!$this->owner($actor))throw new PermissionDenied('Only Eugene confirms older action assignments.');$rows=[];$next=0;
        foreach($this->access->repo->run("SELECT id,target_id FROM security_audit WHERE action='stay.area_audit' AND target_type='area_audit'".($before?' AND id<?':'').' ORDER BY id DESC',$before?[$before]:[])->fetchAll() as $row){$s=$this->source($actor,'area',$row['target_id']);if($s['followup_id']||$s['legacy_completed']||$s['result']==='pass')continue;if(count($rows)===50){$next=(int)end($rows)['cursor'];break;}$rows[]=$s+['cursor'=>(int)$row['id']];}return compact('rows','next');
    }
    public function register(int $actor,string $kind,int $before=0,bool $all=false):array{
        $this->requireAuditor($actor);$r=$this->access->repo;$values=[];$where='';if(!$this->broadReader($actor)){$where.=' AND actor_id=?';$values[]=$actor;}if($before){$where.=' AND id<?';$values[]=$before;}if(!$all){$where.=' AND '.($kind==='room'?'created_at':'occurred_at').'>=?';$values[]=WeeklyAreaAudit::week(substr($this->now(),0,10)).' 00:00:00';}
        if(!$all){$where.=' AND '.($kind==='room'?'created_at':'occurred_at').'<?';$values[]=(new \DateTimeImmutable(WeeklyAreaAudit::week(substr($this->now(),0,10)),new \DateTimeZone('UTC')))->modify('+7 days')->format('Y-m-d').' 00:00:00';}
        $sql=$kind==='room'?'SELECT id source_id,id FROM stay_room_audits WHERE 1=1':"SELECT target_id source_id,id FROM security_audit WHERE action='stay.area_audit' AND target_type='area_audit'";
        $rows=$r->run($sql.$where.' ORDER BY id DESC LIMIT 51',$values)->fetchAll();$more=count($rows)>50;if($more)array_pop($rows);return ['rows'=>array_map(fn($row)=>$this->source($actor,$kind,(string)$row['source_id']),$rows),'next'=>$more?(int)end($rows)['id']:0];
    }
    public function execute(int $actor,string $action,array $in,string $key):array{
        try{
            $this->permit($actor);if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new RuntimeException('Reload the form before submitting.');$file=$in['_evidence']??null;unset($in['_evidence'],$in['csrf'],$in['operation_key'],$in['action']);foreach($in as $v)if(!is_string($v))throw new RuntimeException('Invalid follow-up form.');
            if($file){if($action!=='done')throw new RuntimeException('Evidence belongs to completion.');$file=WeeklyAuditEvidence::checked($file);}ksort($in);$fingerprint=$this->hash(['weekly_audit.'.$action,$in,$file?array_intersect_key($file,array_flip(['mime','size','sha'])):null]);
            return $this->access->transaction(function()use($actor,$action,$in,$key,$fingerprint,$file){
                $this->permit($actor);$r=$this->access->repo;$prior=$r->run('SELECT * FROM finance_operations WHERE actor_id=? AND operation_key=?',[$actor,$key])->fetch();
                if($prior){if(!hash_equals($prior['fingerprint'],$fingerprint))throw new RuntimeException('This form was already used with different details.');$e=$r->run("SELECT * FROM finance_events WHERE id=? AND kind LIKE 'wa_%' AND target_type IN ('weekly_audit','audit_duty')",[$prior['event_id']])->fetch();if(!$e)throw new RuntimeException('This key belongs to another workflow.');return ['id'=>$e['target_type']==='audit_duty'?0:(int)$e['target_id'],'event_id'=>(int)$e['id'],'replayed'=>true];}
                [$id,$event]=$this->command($actor,$action,$in);if($file)WeeklyAuditEvidence::save($this->access,$actor,$event,$file);
                $r->run('INSERT INTO finance_operations VALUES(?,?,?,?)',[$actor,$key,$fingerprint,$event]);$this->access->audit($actor,'weekly_audit.'.$action,'weekly_audit',(string)$id,'succeeded',['event_id'=>$event]);return ['id'=>$id,'event_id'=>$event,'replayed'=>false];
            });
        }catch(Throwable $e){$this->access->audit($actor,'weekly_audit.failed','weekly_audit',null,'denied',['action'=>substr($action,0,32)]);throw $e;}
    }
    private function command(int $actor,string $action,array $in):array{
        $r=$this->access->repo;$owner=$this->owner($actor);
        if($action==='duty'){
            if(!$owner)throw new PermissionDenied('Only Eugene assigns audit duty.');$user=$this->id($in['user_id']??'');if(!in_array($in['enabled']??'',['0','1'],true))throw new RuntimeException('Choose Assign or Remove Audit Duty.');if(!$r->user($user))throw new RuntimeException('Staff account not found.');if($in['enabled']==='1')$this->permit($user);$previous=$r->run("SELECT payload FROM finance_events WHERE target_type='audit_duty' AND target_id=? AND kind='wa_duty' ORDER BY id DESC LIMIT 1",[(string)$user])->fetchColumn();$before=$previous?json_decode($previous,true,512,JSON_THROW_ON_ERROR)['enabled']:false;$event=$this->event($actor,'wa_duty',['enabled'=>$in['enabled']==='1','before'=>$before,'reason'=>R::text($in['reason']??'',2000,true)],$user,'audit_duty');return [0,$event];
        }
        if($action==='adopt'){
            if(!$owner)throw new PermissionDenied('Only Eugene confirms a historical finding.');$source=$this->source($actor,$in['kind']??'',$in['source_id']??'');if(!hash_equals($source['token'],$in['source_token']??''))throw new RuntimeException('Original audit changed. Reload.');$id=$this->capture($actor,$source['kind'],$source['source_id'],$in);if(!$id)throw new RuntimeException('A passing audit needs no follow-up.');return [$id,$this->event($actor,'wa_adopted',['comment'=>R::text($in['comment']??'',2000)],$id)];
        }
        $id=$this->id($in['id']??'');$c=$this->detail($actor,$id);if(!hash_equals($c['version'],$in['version']??''))throw new RuntimeException('This follow-up or its safety evidence changed. Reload before saving.');if($c['status']==='closed')throw new RuntimeException('This follow-up is already closed. Record a new audit for a new finding.');$comment=R::text($in['comment']??'',2000);
        if($action==='assign'){
            if(!$owner)throw new PermissionDenied('Only Eugene assigns or revises follow-ups.');if($c['status']==='review')throw new RuntimeException('Return the work before changing the assignment.');$assignee=$this->id($in['assignee']??'');$this->permit($assignee);$due=R::text($in['due_date']??'',10,true);WeeklyAreaAudit::week($due);if($due<substr($this->now(),0,10))throw new RuntimeException('Choose today or a later deadline.');$reason=R::text($in['reason']??'',2000,$c['assignee']!==0);
            return [$id,$this->event($actor,'wa_assigned',['assignee'=>$assignee,'due_date'=>$due,'before'=>['assignee'=>$c['assignee'],'due_date'=>$c['due']],'reason'=>$reason],$id)];
        }
        if($action==='done'){
            if($c['assignee']!==$actor)throw new PermissionDenied('Only the currently assigned staff member can mark this work done.');if($c['status']!=='assigned')throw new RuntimeException('This work is not awaiting completion.');return [$id,$this->event($actor,'wa_done',['comment'=>$comment],$id)];
        }
        if(!$owner)throw new PermissionDenied('Only Eugene verifies or returns follow-ups.');
        if($action==='link_fault'){
            $source=R::text($in['source_id']??'',32,true);if(!in_array($source,$c['unlinked'],true))throw new RuntimeException('Choose an unsafe room audit without an explicit fault link.');$s=current(array_filter($c['sources'],fn($s)=>$s['source_id']===$source&&$s['kind']==='room'));$fault=$this->id($in['fault_id']??'');$f=$r->run('SELECT room_id,priority FROM stay_faults WHERE id=?',[$fault])->fetch();if(!$f||(string)$f['room_id']!==$s['location_id']||$f['priority']!=='Critical')throw new RuntimeException('Choose the evidenced Critical fault for this room.');return [$id,$this->event($actor,'wa_fault_linked',['source_id'=>$source,'fault_id'=>$fault,'reason'=>R::text($in['reason']??'',2000,true)],$id)];
        }
        if(!in_array($action,['return','close'],true)||$c['status']!=='review')throw new RuntimeException('Only completed work awaiting verification can be reviewed.');
        if($action==='return')return [$id,$this->event($actor,'wa_returned',['comment'=>R::text($comment,2000,true)],$id)];
        if(($in['verified']??'')!=='1')throw new RuntimeException('Confirm that you checked the completed work.');if($c['unlinked']||array_filter($c['faults'],fn($f)=>$f['status']!=='closed'))throw new RuntimeException('Record Repair for every linked Critical fault before closing this action. Historic unsafe audits need an explicit fault link.');if($c['unsafeArea']&&($in['safe']??'')!=='1')throw new RuntimeException('Confirm the unsafe area is safe or blocked from use.');
        return [$id,$this->event($actor,'wa_closed',['comment'=>$comment,'verified'=>true,'unsafe_area_confirmed'=>$c['unsafeArea']],$id)];
    }
}
