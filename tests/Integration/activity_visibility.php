<?php
if(PHP_SAPI!=='cli')exit(1);
putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';
$a=access_service();$r=$a->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')throw new RuntimeException('Unsafe fixture target');
$email='activity-'.bin2hex(random_bytes(5)).'@example.invalid';
$r->run("INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,'clerk')",['Synthetic activity reader',$email,password_hash('Synthetic Visibility Passphrase 123!',PASSWORD_BCRYPT)]);$clerk=(int)$r->db->lastInsertId();
$r->run('INSERT INTO security_accounts(user_id,must_change_password) VALUES(?,0)',[$clerk]);$r->run("INSERT INTO security_user_roles SELECT ?,id FROM security_roles WHERE name='clerk'",[$clerk]);
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard',100,'vacant')",['Synthetic activity guest '.bin2hex(random_bytes(4))]);$room=(int)$r->db->lastInsertId();$r->run("INSERT INTO stay_room_settings(room_id,short_rate,short_enabled,ready_state) VALUES(?,100,1,'ready')",[$room]);
$booking=(new Tivoli\Service\StayService($a))->execute($clerk,'checkin',['room_id'=>(string)$room,'guest_name'=>'Synthetic visibility guest','phone'=>'0000000000','gender'=>'Female','nationality'=>'Ghana','stay_type'=>'short','payment_type'=>'cash','amount'=>'100.00','expected_rate'=>'100.00','identity_verified'=>'1','payment_attested'=>'1','room_checked'=>'1'],bin2hex(random_bytes(16)))['booking_id'];
$admin=(int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn();
if(!$r->isAdmin($admin)||$r->isAdmin($clerk))throw new RuntimeException('Unexpected synthetic roles');
$tag='ACTIVITY-SCOPE-'.bin2hex(random_bytes(5));
$a->audit($clerk,'stay.activity_scope_probe','booking',(string)$booking,'succeeded',['scope_probe'=>$tag,'source'=>'own']);$own=(int)$r->db->lastInsertId();
$a->audit($admin,'stay.activity_scope_probe','booking',(string)$booking,'succeeded',['scope_probe'=>$tag,'source'=>'other']);$other=(int)$r->db->lastInsertId();
$a->audit(null,'stay.activity_scope_probe','booking',(string)$booking,'succeeded',['scope_probe'=>$tag,'source'=>'system']);$system=(int)$r->db->lastInsertId();
$s=new Tivoli\Service\StayService($a);$count=0;
function check(bool $ok,string $label):void{global $count;if(!$ok)throw new RuntimeException('FAIL '.$label);$count++;echo "PASS $label\n";}
$rows=$s->operationalActivity($clerk)['rows'];$ids=array_map('intval',array_column($rows,'id'));
check(in_array($own,$ids,true),'Non-admin operational activity retains own action');
check(!in_array($other,$ids,true)&&!in_array($system,$ids,true),'Non-admin operational activity excludes other/system actors');
check(count(array_filter($rows,fn($e)=>(int)$e['actor_id']!==$clerk))===0,'Every returned non-admin feed row belongs to actor');
$older=$s->operationalActivity($clerk,$other)['rows'];check(count(array_filter($older,fn($e)=>(int)$e['actor_id']!==$clerk||(int)$e['id']>=$other))===0,'Activity cursor cannot bypass actor scope');
$ids=array_map('intval',array_column($s->operationalActivity($admin)['rows'],'id'));check(in_array($own,$ids,true)&&in_array($other,$ids,true)&&in_array($system,$ids,true),'Admin retains all actors including system events');
$events=$s->snapshot($clerk,$booking)['activity'];$probe=array_values(array_filter($events,fn($e)=>str_contains($e['payload'],$tag)));
check(count($probe)===1&&json_decode($probe[0]['payload'],true)['source']==='own','Non-admin guest Activity retains only own action');
$probe=array_values(array_filter($s->snapshot($admin,$booking)['activity'],fn($e)=>str_contains($e['payload'],$tag)));check(count($probe)===3,'Admin guest Activity retains complete history');
check((int)$r->run('SELECT COUNT(*) FROM security_audit WHERE id IN (?,?,?)',[$own,$other,$system])->fetchColumn()===3,'Visibility filters preserve all underlying events');

// A custom audit reader remains a non-admin: an audit grant cannot widen scope.
$r->run('INSERT INTO security_roles(name,description) VALUES(?,?)',['scope-reader-'.bin2hex(random_bytes(4)),'Synthetic scope reader']);$role=(int)$r->db->lastInsertId();
$r->run("INSERT INTO security_role_privileges(role_id,privilege_id) SELECT ?,id FROM security_privileges WHERE code='audit.view'",[$role]);$r->run('INSERT INTO security_user_roles(user_id,role_id) VALUES(?,?)',[$clerk,$role]);
$h=new Tivoli\Service\HousekeepingService($a);$attempts=[];
foreach([$clerk,$admin] as $person){
 $r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard',100,'dirty')",['Synthetic activity '.bin2hex(random_bytes(4))]);$room=(int)$r->db->lastInsertId();
 $r->run("INSERT INTO stay_room_settings(room_id,short_rate,short_enabled,ready_state) VALUES(?,100,1,'cleaning')",[$room]);
 $attempts[]=$h->execute($person,'start',['room_id'=>(string)$room,'room_token'=>$h->room($person,$room)['token']],bin2hex(random_bytes(16)))['id'];
}
$rows=$h->history($clerk)['rows'];$ids=array_map(fn($row)=>(int)$row['root']['id'],$rows);
check(in_array($attempts[0],$ids,true)&&!in_array($attempts[1],$ids,true),'Non-admin housekeeping history excludes other cleaners');
check(count(array_filter($rows,fn($row)=>(int)$row['root']['actor_id']!==$clerk))===0,'Every non-admin housekeeping history row is their own attempt');
$rows=$h->history($admin)['rows'];$ids=array_map(fn($row)=>(int)$row['root']['id'],$rows);check(in_array($attempts[0],$ids,true)&&in_array($attempts[1],$ids,true),'Admin housekeeping history retains all cleaners');
$evidence=dirname(__DIR__,2).'/.local-setup/activity-visibility-fixture.json';file_put_contents($evidence,json_encode(compact('clerk','admin','booking','own','other','system','tag','attempts')+['email'=>$email]));
echo "TOTAL $count activity scope assertions passed\n";
