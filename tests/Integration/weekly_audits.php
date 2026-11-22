<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';
use Tivoli\Service\WeeklyAuditService;
$a=access_service();$r=$a->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
if(!class_exists(WeeklyAuditService::class))throw new RuntimeException('Approved Weekly Audit Follow-Up service is missing');
$n=0;$clock='2036-11-17 14:00:00';$owner=(int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn();putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$owner);
function wk():string{return bin2hex(random_bytes(16));}
function wa(bool $ok,string $label):void{global $n;if(!$ok)throw new RuntimeException($label);$n++;echo "PASS $label\n";}
function wd(callable $f,string $label):void{try{$f();}catch(Throwable $e){wa(true,$label);return;}throw new RuntimeException('Expected denial: '.$label);}
function wu(string $role='clerk'):int{global $r;$r->run('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',['Synthetic audit staff',wk().'@example.invalid',password_hash('Synthetic Audit Passphrase 123!',PASSWORD_BCRYPT),$role]);$id=(int)$r->db->lastInsertId();$r->run('INSERT INTO security_accounts(user_id,must_change_password) VALUES(?,0)',[$id]);$r->run('INSERT INTO security_user_roles SELECT ?,id FROM security_roles WHERE name=?',[$id,$role]);return $id;}
$staff=wu();$other=wu();$admin=wu('admin');$staffPrivileges=$r->privileges($staff);$s=new WeeklyAuditService($a,$clock);$legacy=new \Tivoli\Service\StayService($a,$clock);
function wc(int $actor,string $action,array $in=[],?string $key=null):array{global $s;return $s->execute($actor,$action,$in,$key??wk());}
function wv(int $actor,int $id):array{global $s;return ['id'=>(string)$id,'version'=>$s->detail($actor,$id)['version']];}
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard',100,'vacant')",['Synthetic audit '.wk()]);$room=(int)$r->db->lastInsertId();
$financial=hash('sha256',json_encode([$r->run('SELECT * FROM payments ORDER BY id')->fetchAll(),$r->run('SELECT * FROM expenses ORDER BY id')->fetchAll(),$r->run('SELECT * FROM stock_movements ORDER BY id')->fetchAll()]));
wd(fn()=>$s->requireAuditor($staff),'Clerk has no implicit audit duty');
wd(fn()=>wc($admin,'duty',['user_id'=>(string)$staff,'enabled'=>'1','reason'=>'Cover Sunday']),'Non-owner admin cannot delegate audit duty');
$dutyKey=wk();$duty=['user_id'=>(string)$staff,'enabled'=>'1','reason'=>'Authorized Sunday audit'];$d=wc($owner,'duty',$duty,$dutyKey);wa(wc($owner,'duty',$duty,$dutyKey)['event_id']===$d['event_id']&&wc($owner,'duty',$duty,$dutyKey)['id']===$d['id'],'Audit duty retry posts once');$s->requireAuditor($staff);wa(!$r->isAdmin($staff)&&$r->privileges($staff)===$staffPrivileges,'Audit duty does not grant room administration');
$pass=$legacy->execute($staff,'room_audit',['room_id'=>(string)$room,'result'=>'pass'],wk());wa(empty($pass['followup_id']),'Passing audit creates no task');
$key=wk();$in=['room_id'=>(string)$room,'result'=>'corrective','reason'=>'Curtain needs attention'];$created=$legacy->execute($staff,'room_audit',$in,$key);$id=$created['followup_id'];wa($id>0,'Needs Attention creates source-linked action');wa($legacy->execute($staff,'room_audit',$in,$key)['followup_id']===$id,'Original audit replay returns same follow-up');
wd(fn()=>$s->detail($other,$id),'Unrelated staff cannot read task or source');
wd(fn()=>wc($admin,'assign',wv($admin,$id)+['assignee'=>(string)$staff,'due_date'=>'2036-11-18']),'Only configured owner assigns');
wc($owner,'assign',wv($owner,$id)+['assignee'=>(string)$staff,'due_date'=>'2036-11-18']);wa($s->detail($staff,$id)['status']==='assigned','Named staff and deadline assignment');
wd(fn()=>wc($other,'done',['id'=>(string)$id,'version'=>$s->detail($owner,$id)['version']]),'Only current assignee can mark done');
$prior=wv($staff,$id);$doneKey=wk();wc($staff,'done',$prior,$doneKey);wa($s->detail($owner,$id)['status']==='review','Done is awaiting verification');wc($staff,'done',$prior,$doneKey);wa(count(array_filter($s->detail($owner,$id)['history'],fn($e)=>$e['kind']==='wa_done'))===1,'Completion retry retains one claim');
wd(fn()=>wc($owner,'assign',wv($owner,$id)+['assignee'=>(string)$other,'due_date'=>'2036-11-19','reason'=>'Reassign']),'Must return work before reassignment during verification');
wd(fn()=>wc($owner,'return',wv($owner,$id)),'Rework requires explanation');wc($owner,'return',wv($owner,$id)+['comment'=>'Please also check the curtain rail']);wc($owner,'assign',wv($owner,$id)+['assignee'=>(string)$other,'due_date'=>'2036-11-19','reason'=>'Relief cover']);
wd(fn()=>wc($staff,'done',wv($owner,$id)),'Earlier assignee loses completion authority');wc($other,'done',wv($other,$id));
$state=$s->detail($owner,$id);$unsafe=$legacy->execute($staff,'room_audit',['room_id'=>(string)$room,'result'=>'unsafe','reason'=>'Loose curtain rail','followup_id'=>(string)$id,'followup_version'=>$state['version']],wk());wa($unsafe['followup_id']===$id&&$s->detail($owner,$id)['status']==='assigned','Explicit repeated finding reuses task and returns pending work');
wc($other,'done',wv($other,$id));wd(fn()=>wc($owner,'close',wv($owner,$id)+['verified'=>'1']),'Any later linked Critical fault blocks verification');
$fault=(int)$r->run("SELECT id FROM stay_faults WHERE room_id=? AND priority='Critical' ORDER BY id DESC LIMIT 1",[$room])->fetchColumn();$legacy->execute($owner,'repair',['fault_id'=>(string)$fault,'reason'=>'Secured rail'],wk());$roomBefore=$r->run('SELECT status FROM rooms WHERE id=?',[$room])->fetchColumn();wc($owner,'close',wv($owner,$id)+['verified'=>'1']);wa($s->detail($owner,$id)['status']==='closed','Owner verifies after canonical repair');wa($r->run('SELECT status FROM rooms WHERE id=?',[$room])->fetchColumn()===$roomBefore,'Follow-up closure never releases a room');
$legacy->execute($staff,'room_audit',['room_id'=>(string)$room,'result'=>'pass'],wk());wa($s->detail($owner,$id)['status']==='closed','Later Pass does not change original task');
$areas=\Tivoli\Service\AuditAreas::current($r);$entry=current(array_filter($areas['entries'],fn($x)=>$x['active']));$area=$legacy->execute($staff,'area_audit',['area_revision'=>$areas['revision'],'area_id'=>$entry['id'],'result'=>'unsafe'],wk());$aid=$area['followup_id'];wc($owner,'assign',wv($owner,$aid)+['assignee'=>(string)$staff,'due_date'=>'2036-11-19']);wc($staff,'done',wv($staff,$aid));wd(fn()=>wc($owner,'close',wv($owner,$aid)+['verified'=>'1']),'Unsafe area needs explicit safety attestation');wc($owner,'close',wv($owner,$aid)+['verified'=>'1','safe'=>'1']);
$areaKey=$s->detail($owner,$aid)['sources'][0]['source_id'];wd(fn()=>$legacy->execute($owner,'area_followup',['audit_key'=>$areaKey,'version'=>'0','status'=>'completed'],wk()),'Older area form cannot bypass named completion and owner verification');
wa(hash('sha256',json_encode([$r->run('SELECT * FROM payments ORDER BY id')->fetchAll(),$r->run('SELECT * FROM expenses ORDER BY id')->fetchAll(),$r->run('SELECT * FROM stock_movements ORDER BY id')->fetchAll()]))===$financial,'All original financial and stock rows unchanged');
echo "Weekly audits: $n assertions passed\n";
