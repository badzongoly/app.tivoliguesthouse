<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';
$a=access_service();$r=$a->repo;if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$f=json_decode(file_get_contents(dirname(__DIR__,2).'/.local-setup/activity-visibility-fixture.json'),true);$actor=(int)$f['clerk'];$inspector=(int)$f['admin'];$clock='2026-11-22 14:00:00';$h=new Tivoli\Service\HousekeepingService($a,$clock);$s=new Tivoli\Service\StayService($a,$clock);$n=0;
function check(bool $ok,string $label):void{global $n;if(!$ok)throw new RuntimeException('FAIL '.$label);$n++;echo "PASS $label\n";}
check(method_exists($h,'roomSummaries'),'Room Board has a shared housekeeping summary');
function command($service,int $actor,string $action,array $input):array{return $service->execute($actor,$action,$input,bin2hex(random_bytes(16)));}
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard',100,'vacant')",['Synthetic merged housekeeping '.bin2hex(random_bytes(4))]);$room=(int)$r->db->lastInsertId();$r->run("INSERT INTO stay_room_settings(room_id,short_rate,short_enabled,ready_state) VALUES(?,100,1,'ready')",[$room]);
$booking=command($s,$actor,'checkin',['room_id'=>(string)$room,'guest_name'=>'Synthetic merged stay','phone'=>'0000000000','gender'=>'Female','nationality'=>'Ghana','stay_type'=>'short','amount'=>'100.00','expected_rate'=>'100.00','payment_type'=>'cash','identity_verified'=>'1','payment_attested'=>'1','room_checked'=>'1'])['booking_id'];
command($s,$actor,'checkout',['booking_id'=>(string)$booking,'extras_checked'=>'1','keys_returned'=>'1','expected_balance'=>'0']);
$row=fn()=>array_values(array_filter($s->snapshot($actor)['rooms'],fn($x)=>(int)$x['id']===$room));
$summary=$h->roomSummaries($actor,$row())[$room];check($summary['state']==='needs_cleaning','Departure shows Needs Cleaning on Room Board');
$id=command($h,$actor,'start',['room_id'=>(string)$room,'room_token'=>$h->room($actor,$room)['token']])['id'];
check($h->detail($actor,$id)['root']['data']['source_booking_id']===$booking,'New attempt captures server-derived finalized departure');
check($h->roomSummaries($actor,$row())[$room]['state']==='cleaning','Assigned attempt shows Cleaning In Progress');
check((int)$h->preparationForStay($actor,$booking)['id']===$id,'Guest account links its original attempt');
$c=$h->detail($actor,$id);$done=array_merge(...array_map(fn($x)=>array_column($x['items'],'id'),$c['root']['data']['cleaning']['sections']));
foreach($c['root']['data']['cleaning']['sections'] as $i=>$section){command($h,$actor,'progress',['id'=>(string)$id,'version'=>$h->detail($actor,$id)['version'],'done'=>json_encode($done),'direction'=>$i===count($c['root']['data']['cleaning']['sections'])-1?'complete':'next','verified'=>'1']);}
check($h->roomSummaries($actor,$row())[$room]['state']==='inspection','Completed cleaning shows Awaiting Inspection');
$c=$h->detail($inspector,$id);$items=array_merge(...array_map(fn($x)=>array_column($x['items'],'id'),$c['root']['data']['inspection']['sections']));command($h,$inspector,'inspect',['id'=>(string)$id,'version'=>$c['version'],'results'=>json_encode(array_fill_keys($items,'pass')),'decision'=>'release','verified'=>'1']);
$s2=new Tivoli\Service\StayService($a,'2026-11-23 14:00:00');$h2=new Tivoli\Service\HousekeepingService($a,'2026-11-23 14:00:00');
$newBooking=command($s2,$actor,'checkin',['room_id'=>(string)$room,'guest_name'=>'Synthetic later guest','phone'=>'0000000000','gender'=>'Female','nationality'=>'Ghana','stay_type'=>'short','amount'=>'100.00','expected_rate'=>'100.00','payment_type'=>'cash','identity_verified'=>'1','payment_attested'=>'1','room_checked'=>'1'])['booking_id'];command($s2,$actor,'checkout',['booking_id'=>(string)$newBooking,'extras_checked'=>'1','keys_returned'=>'1','expected_balance'=>'0']);
$new=command($h2,$actor,'start',['room_id'=>(string)$room,'room_token'=>$h2->room($actor,$room)['token']])['id'];
check((int)$h2->preparationForStay($actor,$booking)['id']===$id,'Old stay never points at later guest cleaning');
check((int)$h2->preparationForStay($actor,$newBooking)['id']===$new,'Later stay points at its own cleaning attempt');
check(!$h2->detail($actor,$id)['current'],'Original attempt is historical after room reuse');
$fault=command($s2,$inspector,'fault',['room_id'=>(string)$room,'owner'=>'Synthetic repair owner','priority'=>'Critical'])['fault_id'];
command($s2,$inspector,'repair',['id'=>(string)$fault]);
$summary=$h2->roomSummaries($actor,$row())[$room];check($summary['state']==='needs_cleaning'&&$summary['action']==='Start Cleaning','Fault and repair invalidates old work and offers fresh cleaning');
$new=command($h2,$actor,'start',['room_id'=>(string)$room,'room_token'=>$h2->room($actor,$room)['token']])['id'];
check((int)$h2->preparationForStay($actor,$booking)['id']===$id,'Repair and restarted later work never changes original guest link');
$email='merged-rooms-'.bin2hex(random_bytes(5)).'@example.invalid';$r->run("INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,'clerk')",['Synthetic rooms-only cleaner',$email,password_hash('Synthetic Rooms Only Passphrase 123!',PASSWORD_BCRYPT)]);$roomsActor=(int)$r->db->lastInsertId();$r->run('INSERT INTO security_accounts(user_id,must_change_password) VALUES(?,0)',[$roomsActor]);
$r->run('INSERT INTO security_roles(name,description) VALUES(?,?)',['rooms-only-'.bin2hex(random_bytes(5)),'Synthetic rooms only']);$role=(int)$r->db->lastInsertId();$r->run("INSERT INTO security_role_privileges SELECT ?,id FROM security_privileges WHERE code='rooms.manage'",[$role]);$r->run('INSERT INTO security_user_roles VALUES(?,?)',[$roomsActor,$role]);
check(!in_array('bookings.manage',$r->privileges($roomsActor),true),'Room-only fixture has no booking grant');
file_put_contents(dirname(__DIR__,2).'/.local-setup/housekeeping-merge-fixture.json',json_encode(['actor'=>$actor,'email'=>$f['email'],'room'=>$room,'booking'=>$booking,'attempt'=>$id,'new_booking'=>$newBooking,'new_attempt'=>$new,'rooms_email'=>$email,'rooms_actor'=>$roomsActor]));
echo "TOTAL $n housekeeping merge assertions passed\n";
