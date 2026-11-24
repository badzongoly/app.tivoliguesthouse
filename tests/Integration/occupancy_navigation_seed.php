<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';
$a=access_service();$r=$a->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$root=dirname(__DIR__,2);$old=json_decode(file_get_contents($root.'/.local-setup/housekeeping-merge-fixture.json'),true,512,JSON_THROW_ON_ERROR);$actor=(int)$old['actor'];
$rooms=[];foreach(['active'=>'ready','ready'=>'ready','clean'=>'cleaning','start'=>'cleaning'] as $name=>$state){$r->run('INSERT INTO rooms(name,type,rate,status) VALUES(?,?,?,?)',['Synthetic occupancy UI '.$name.' '.bin2hex(random_bytes(5)),'Standard','100.00',$state==='ready'?'vacant':'dirty']);$room=(int)$r->db->lastInsertId();$r->run('INSERT INTO stay_room_settings(room_id,short_rate,short_enabled,ready_state) VALUES(?,?,1,?)',[$room,'100.00',$state]);$rooms[$name]=$room;}
$s=new \Tivoli\Service\StayService($a);$h=new \Tivoli\Service\HousekeepingService($a);
$booking=$s->execute($actor,'checkin',['room_id'=>(string)$rooms['active'],'guest_name'=>'Synthetic occupancy navigation guest','phone'=>'0000000000','gender'=>'Female','nationality'=>'Ghana','stay_type'=>'short','amount'=>'100.00','expected_rate'=>'100.00','payment_type'=>'cash','identity_verified'=>'1','payment_attested'=>'1','room_checked'=>'1'],bin2hex(random_bytes(16)))['booking_id'];
$attempt=$h->execute($actor,'start',['room_id'=>(string)$rooms['clean'],'room_token'=>$h->room($actor,$rooms['clean'])['token']],bin2hex(random_bytes(16)))['id'];
$r->run("INSERT INTO extras(name,price,stock_qty,active) VALUES(?,5,10,1)",['Synthetic occupancy extra '.bin2hex(random_bytes(5))]);
$testExtra=(int)$r->db->lastInsertId();
$incident=json_decode(file_get_contents($root.'/.local-setup/departure-browser-fixture.json'),true,512,JSON_THROW_ON_ERROR);
file_put_contents($root.'/.local-setup/occupancy-ui-fixture.json',json_encode(['email'=>$old['email'],'booking'=>$booking,'ready_room'=>$rooms['ready'],'start_room'=>$rooms['start'],'extra'=>$testExtra,'attempt'=>$attempt,'source_booking'=>$old['booking'],'source_attempt'=>$old['attempt'],'incident_booking'=>$incident['booking'],'rooms_email'=>$old['rooms_email']],JSON_THROW_ON_ERROR));
echo "PASS Guarded synthetic occupancy navigation fixture created\n";
