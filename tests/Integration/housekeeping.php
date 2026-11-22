<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';
use Tivoli\Service\HousekeepingService;
$a=access_service();$r=$a->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
if(!class_exists(HousekeepingService::class))throw new RuntimeException('Approved housekeeping service missing');

$n=0;$tag=bin2hex(random_bytes(6));$clock='2034-11-14 14:00:00';$owner=(int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn();putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$owner);
function hk():string{return bin2hex(random_bytes(16));}
function ha(bool $v,string $label):void{global $n;if(!$v)throw new RuntimeException($label);$n++;echo "PASS $label\n";}
function hd(callable $f,string $label):void{try{$f();}catch(Throwable $e){ha(true,$label);return;}throw new RuntimeException('Expected denial '.$label);}
function hu(string $role='clerk'):int{global $r;$r->run('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',['Synthetic housekeeper',hk().'@example.invalid',password_hash('Synthetic Housekeeping Passphrase 123!',PASSWORD_BCRYPT),$role]);$id=(int)$r->db->lastInsertId();$r->run('INSERT INTO security_accounts(user_id,must_change_password) VALUES(?,0)',[$id]);$r->run('INSERT INTO security_user_roles SELECT ?,id FROM security_roles WHERE name=?',[$id,$role]);return $id;}
$staff=hu();$other=hu();$admin=hu('admin');$s=new HousekeepingService($a,$clock);$legacy=new \Tivoli\Service\StayService($a,$clock);
function hrm():int{global $r;$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard',100,'dirty')",['Synthetic housekeeping '.hk()]);$id=(int)$r->db->lastInsertId();$r->run("INSERT INTO stay_room_settings(room_id,ready_state) VALUES(?,'cleaning')",[$id]);return $id;}
function hc(int $actor,string $action,array $in=[],?string $key=null):array{global $s;return $s->execute($actor,$action,$in,$key??hk());}
function hv(int $actor,int $id):array{global $s;return ['id'=>(string)$id,'version'=>$s->detail($actor,$id)['version']];}
function hs(int $actor,int $room):int{global $s;return hc($actor,'start',['room_id'=>(string)$room,'room_token'=>$s->room($actor,$room)['token']])['id'];}
function hdone(int $id,int $actor):array{global $s;$c=$s->detail($actor,$id);return array_merge(...array_map(fn($section)=>array_column($section['items'],'id'),$c['root']['data']['cleaning']['sections']));}
function hcomplete(int $actor,int $id):void{global $s;$sections=$s->detail($actor,$id)['root']['data']['cleaning']['sections'];foreach($sections as $i=>$section)hc($actor,'progress',hv($actor,$id)+['direction'=>$i===count($sections)-1?'complete':'next','done'=>json_encode(hdone($id,$actor)),'verified'=>'1','comment'=>'Synthetic completed checklist']);}
function hinspect(int $actor,int $id,string $decision='release',?array $results=null):array{global $s;$c=$s->detail($actor,$id);$all=array_merge(...array_map(fn($section)=>array_column($section['items'],'id'),$c['root']['data']['inspection']['sections']));$results??=array_fill_keys($all,'pass');return hc($actor,'inspect',hv($actor,$id)+['results'=>json_encode($results),'decision'=>$decision,'verified'=>'1','comment'=>'Synthetic inspection']);}
$financial=hash('sha256',json_encode([$r->run('SELECT * FROM payments ORDER BY id')->fetchAll(),$r->run('SELECT * FROM expenses ORDER BY id')->fetchAll(),$r->run('SELECT * FROM stock_movements ORDER BY id')->fetchAll()]));
$room=hrm();$id=hs($staff,$room);ha($s->detail($staff,$id)['status']==='cleaning','Authorized staff starts recorded cleaning attempt');
$warnings=[];set_error_handler(function($number,$message)use(&$warnings){$warnings[]=$message;return true;});
try{hd(fn()=>hc($staff,'progress',hv($staff,$id)+['direction'=>'pause','done'=>'[{}]']),'Nested checklist values rejected');}finally{restore_error_handler();}
ha(!$warnings,'Nested checklist values rejected without PHP warnings');
hd(fn()=>hs($other,$room),'Another cleaner cannot take over current attempt');
hd(fn()=>hc($other,'progress',hv($other,$id)+['direction'=>'next','done'=>json_encode(hdone($id,$staff))]),'Only assigned cleaner records progress');
hd(fn()=>hc($staff,'progress',hv($staff,$id)+['direction'=>'next','done'=>'[]']),'Incomplete section cannot advance');
hd(fn()=>hc($staff,'progress',hv($staff,$id)+['direction'=>'complete','done'=>json_encode(hdone($id,$staff)),'verified'=>'1']),'Final completion cannot skip wizard steps');
hcomplete($staff,$id);ha($s->detail($staff,$id)['status']==='inspection'&&$r->run('SELECT ready_state FROM stay_room_settings WHERE room_id=?',[$room])->fetchColumn()==='awaiting_inspection','Cleaning completion does not release room');
hd(fn()=>hinspect($staff,$id),'Cleaner cannot inspect without owner authority');
hc($staff,'request_exception',hv($staff,$id)+['reason'=>'Independent inspector unavailable']);
hd(fn()=>hinspect($staff,$id),'Exception request alone grants no release');
hd(fn()=>hc($admin,'decide_exception',hv($admin,$id)+['decision'=>'approve','reason'=>'Non-owner admin']),'Other admin cannot authorize self-inspection');
hc($owner,'decide_exception',hv($owner,$id)+['decision'=>'approve','reason'=>'One attempt authorized; follow-up required']);
$before=$s->detail($staff,$id);ha($before['status']==='inspection','Grant alone does not release room');
$keys=array_merge(...array_map(fn($x)=>array_column($x['items'],'id'),$before['root']['data']['inspection']['sections']));$failed=array_fill_keys($keys,'pass');$failed[$keys[0]]='fail';
hd(fn()=>hinspect($staff,$id,'release',$failed),'Failed item prevents release even with Select All elsewhere');
hinspect($staff,$id,'rework',$failed);ha($s->detail($staff,$id)['status']==='returned','Inspection returns room for cleaning');
hd(fn()=>hinspect($staff,$id),'Used grant cannot be reused');
$review=$s->detail($owner,$id)['followups'][0];hc($owner,'followup',hv($owner,$id)+['inspection_id'=>(string)$review['id'],'reason'=>'Rework finding reviewed']);
ha(!$s->detail($owner,$id)['followups'],'Owner follow-up records exact inspection once');
$second=hs($other,$room);hcomplete($other,$second);hinspect($staff,$second);
ha($r->run('SELECT status FROM rooms WHERE id=?',[$room])->fetchColumn()==='vacant'&&$r->run('SELECT ready_state FROM stay_room_settings WHERE room_id=?',[$room])->fetchColumn()==='ready','Different inspector releases ready room');
ha($s->detail($owner,$id)['status']==='returned'&&!$s->detail($owner,$id)['current'],'Original failed attempt remains readable');
hd(fn()=>hinspect($staff,$second),'Final release cannot repeat');
hd(fn()=>$legacy->execute($owner,'inspect',['room_id'=>(string)$room],hk()),'Legacy service cannot bypass modern attempt controls');
$room=hrm();$id=hs($owner,$room);hcomplete($owner,$id);hd(fn()=>hinspect($owner,$id),'Owner self-inspection needs explicit exception');
hc($owner,'request_exception',hv($owner,$id)+['reason'=>'Owner alone on duty; explicit self-review exception']);hinspect($owner,$id);
ha(count($s->detail($owner,$id)['followups'])===1,'Owner self-inspection remains due for follow-up');
$room=hrm();$id=hs($staff,$room);hcomplete($staff,$id);hc($staff,'request_exception',hv($staff,$id)+['reason'=>'No cover']);hc($owner,'decide_exception',hv($owner,$id)+['decision'=>'reject','reason'=>'Wait for other staff']);hd(fn()=>hinspect($staff,$id),'Rejected exception cannot permit inspection');hinspect($other,$id);
$room=hrm();$id=hs($staff,$room);$oldRevision=$s->detail($staff,$id)['root']['data']['cleaning']['revision'];$list=\Tivoli\Service\CleaningChecklist::current($r);$section=$list['sections'][0]['id'];$legacy->execute($owner,'cleaning_checklist',['checklist_revision'=>$list['revision'],'change'=>'add','section_id'=>$section,'item_text'=>'Synthetic housekeeping revision '.hk()],hk());hcomplete($staff,$id);ha($s->detail($staff,$id)['root']['data']['cleaning']['revision']===$oldRevision,'Started attempt retains captured checklist after settings change');
$legacy->execute($owner,'fault',['room_id'=>(string)$room,'priority'=>'Critical','owner'=>'Synthetic maintenance','reason'=>'Exposed wiring'],hk());
hd(fn()=>hinspect($other,$id),'Critical fault blocks release');
$fault=(int)$r->run('SELECT MAX(id) FROM stay_faults WHERE room_id=?',[$room])->fetchColumn();$legacy->execute($owner,'repair',['fault_id'=>(string)$fault,'reason'=>'Safe repair evidence'],hk());
hd(fn()=>hinspect($other,$id),'Fault then repair invalidates old attempt grant and checklist');
$fresh=hs($other,$room);hcomplete($other,$fresh);hinspect($staff,$fresh);ha($s->detail($owner,$id)['status']==='superseded','Fresh attempt retains invalidated original');
$room=hrm();$id=hs($staff,$room);hcomplete($staff,$id);$r->run("INSERT INTO bookings(room_id,rate_per_night,guest_name,checkin_at,status) VALUES(?,100,'Synthetic occupancy guard',?,'active')",[$room,$clock]);hd(fn()=>hinspect($other,$id),'Occupied room cannot release');
$room=hrm();$id=hs($staff,$room);$version=hv($staff,$id);hc($staff,'progress',$version+['direction'=>'pause','done'=>json_encode(hdone($id,$staff))]);hd(fn()=>hc($staff,'progress',$version+['direction'=>'pause','done'=>'[]']),'Stale progress version denied');
hd(fn()=>hc($staff,'restart',hv($staff,$id)+['reason'=>'Not owner']),'Staff cannot restart another assignment');
$new=hc($owner,'restart',hv($owner,$id)+['reason'=>'Assigned staff unavailable; owner restarts physical cleaning'])['id'];ha($new!==$id&&$s->detail($owner,$new)['root']['actor_id']==$owner,'Owner restart creates a new factual cleaning attempt');
$room=hrm();$id=hs($staff,$room);hcomplete($staff,$id);$input=hv($other,$id)+['results'=>json_encode(array_fill_keys(array_merge(...array_map(fn($x)=>array_column($x['items'],'id'),$s->detail($other,$id)['root']['data']['inspection']['sections'])),'pass')),'decision'=>'release','verified'=>'1'];$key=hk();
$r->run("CREATE TRIGGER synthetic_hk_audit_fail BEFORE INSERT ON security_audit FOR EACH ROW BEGIN IF NEW.action='housekeeping.inspect' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'; END IF; END");
try{hd(fn()=>hc($other,'inspect',$input,$key),'Required audit failure rolls back release');}finally{$r->run('DROP TRIGGER synthetic_hk_audit_fail');}
ha($s->detail($other,$id)['status']==='inspection'&&$r->run('SELECT ready_state FROM stay_room_settings WHERE room_id=?',[$room])->fetchColumn()==='awaiting_inspection','Failed audit retains previous room readiness');
hc($other,'inspect',$input,$key);$out=hc($other,'inspect',$input,$key);ha($out['replayed'],'Same operation retries replay without second release');
$proof=\Tivoli\Service\FinanceAttachment::validate("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF",'room.pdf');$room=hrm();$id=hs($staff,$room);$sections=$s->detail($staff,$id)['root']['data']['cleaning']['sections'];foreach($sections as $i=>$sec)$out=hc($staff,'progress',hv($staff,$id)+['direction'=>$i===count($sections)-1?'complete':'next','done'=>json_encode(hdone($id,$staff)),'verified'=>'1']+($i===count($sections)-1?['_evidence'=>$proof]:[]));
ha(\Tivoli\Service\FinanceAttachment::download($a,$other,'housekeeping',$out['event_id'])['bytes']===$proof['bytes'],'Authorized housekeeping staff downloads protected room evidence');
ha(hash('sha256',json_encode([$r->run('SELECT * FROM payments ORDER BY id')->fetchAll(),$r->run('SELECT * FROM expenses ORDER BY id')->fetchAll(),$r->run('SELECT * FROM stock_movements ORDER BY id')->fetchAll()]))===$financial,'Housekeeping creates no money or stock movements');
echo "TOTAL $n housekeeping assertions passed.\n";
