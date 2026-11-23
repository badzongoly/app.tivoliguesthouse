<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';
$a=access_service();$r=$a->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')throw new RuntimeException('Unverified synthetic target');
$dir=dirname(__DIR__,2).'/.local-setup';$mode=$argv[1]??'probe';$path=$dir.'/turnover-fixture.json';
$tables=['bookings'=>'id','payments'=>'id','expenses'=>'id','stock_movements'=>'id','stay_departures'=>'booking_id','stay_receipts'=>'payment_id','finance_events'=>'id'];
function hashes():array{global $r,$tables;$out=[];foreach($tables as $table=>$key){$out[$table]=[];foreach($r->run("SELECT * FROM $table ORDER BY $key")->fetchAll() as $row)$out[$table][(string)$row[$key]]=hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));}return $out;}
if($mode==='seed'){
 $tag=bin2hex(random_bytes(5));$users=[];
 foreach(['staff','inspector','outsider'] as $kind){$email='turnover-'.$kind.'-'.$tag.'@example.invalid';$r->run("INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,'clerk')",['Synthetic turnover '.$kind,$email,password_hash('Synthetic Turnover Passphrase 123!',PASSWORD_BCRYPT)]);$id=(int)$r->db->lastInsertId();$r->run('INSERT INTO security_accounts(user_id,must_change_password) VALUES(?,0)',[$id]);if($kind!=='outsider')$r->run("INSERT INTO security_user_roles SELECT ?,id FROM security_roles WHERE name='clerk'",[$id]);$users[$kind]=compact('id','email');}
 $r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard',100,'vacant')",['Synthetic full turnover '.$tag]);$room=(int)$r->db->lastInsertId();$r->run("INSERT INTO stay_room_settings(room_id,short_rate,short_enabled,ready_state) VALUES(?,100,1,'ready')",[$room]);
 $r->run('INSERT INTO extras(name,price,stock_qty,active) VALUES(?,10,10,1)',['Synthetic turnover extra '.$tag]);$extra=(int)$r->db->lastInsertId();
 file_put_contents($path,json_encode(compact('room','extra','users','tag'),JSON_THROW_ON_ERROR));file_put_contents($dir.'/turnover-baseline.json',json_encode(hashes(),JSON_THROW_ON_ERROR));echo "PASS Guarded synthetic room and accounts prepared\n";exit;
}
$f=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);$room=(int)$f['room'];
if($mode==='probe'){
 $bookings=$r->run('SELECT id,status FROM bookings WHERE room_id=? ORDER BY id',[$room])->fetchAll();$ids=array_column($bookings,'id');$totals=[];foreach($ids as $id)$totals[(string)$id]=['count'=>(int)$r->run('SELECT COUNT(*) FROM payments WHERE booking_id=?',[$id])->fetchColumn(),'paid'=>$r->run('SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id=?',[$id])->fetchColumn()];
 $state=$r->run('SELECT status,ready_state FROM rooms JOIN stay_room_settings ON room_id=rooms.id WHERE rooms.id=?',[$room])->fetch();
 $attempt=$r->run("SELECT id FROM finance_events WHERE kind='hk_started' AND target_type='housekeeping' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.room_id'))=? ORDER BY id DESC LIMIT 1",[(string)$room])->fetchColumn();
 $c=$attempt?(new Tivoli\Service\HousekeepingService($a))->detail((int)$f['users']['staff']['id'],(int)$attempt):null;
 echo json_encode(['state'=>$state,'bookings'=>$bookings,'payments'=>$totals,'departures'=>$ids?(int)$r->run('SELECT COUNT(*) FROM stay_departures WHERE booking_id IN ('.implode(',',array_fill(0,count($ids),'?')).')',$ids)->fetchColumn():0,'attempt'=>$c?['id'=>(int)$attempt,'status'=>$c['status'],'step'=>$c['step'],'version'=>$c['version'],'source_booking_id'=>$c['root']['data']['source_booking_id'],'completed'=>count(array_filter($c['history'],fn($e)=>$e['kind']==='hk_completed')),'inspections'=>count($c['inspections'])]:null],JSON_THROW_ON_ERROR);exit;
}
if($mode!=='verify')throw new RuntimeException('Unknown verification command');
$n=0;function check(bool $ok,string $label):void{global $n;if(!$ok)throw new RuntimeException('FAIL '.$label);++$n;echo "PASS $label\n";}
$baseline=json_decode(file_get_contents($dir.'/turnover-baseline.json'),true,512,JSON_THROW_ON_ERROR);$after=hashes();$retained=0;foreach($baseline as $table=>$rows)foreach($rows as $id=>$hash){if(($after[$table][$id]??null)!==$hash)throw new RuntimeException('Protected source changed in '.$table);$retained++;}check(true,'Every pre-existing protected row across seven tables remains unchanged');
$bookings=$r->run('SELECT * FROM bookings WHERE room_id=? ORDER BY id',[$room])->fetchAll();check(count($bookings)===2&&$bookings[0]['status']==='checked_out'&&$bookings[1]['status']==='active','Exactly one departed guest and one new active guest');
$first=(int)$bookings[0]['id'];$next=(int)$bookings[1]['id'];$departure=$r->run('SELECT * FROM stay_departures WHERE booking_id=?',[$first])->fetch();check($departure&&(int)$departure['room_minor']===10000&&(int)$departure['extras_minor']===1000&&(int)$departure['paid_minor']===11000&&(int)$departure['balance_minor']===0,'Original final bill remains exact: room100 plus extra10 paid110 with zero due');
$receipts=$r->run('SELECT p.*,s.actor_id FROM payments p JOIN stay_receipts s ON s.payment_id=p.id WHERE p.booking_id IN (?,?) ORDER BY p.id',[$first,$next])->fetchAll();check(count($receipts)===3&&array_sum(array_map(fn($x)=>Tivoli\Domain\Stay\StayRules::minor($x['amount']),$receipts))===21000,'Exactly three receipts total GHS210; no payment duplication');
check(count(array_filter($receipts,fn($e)=>(int)$e['actor_id']!==(int)$f['users']['staff']['id']))===0,'All receipts retain authenticated cashier identity');
$roots=$r->run("SELECT * FROM finance_events WHERE kind='hk_started' AND target_type='housekeeping' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.room_id'))=?",[(string)$room])->fetchAll();check(count($roots)===1,'One cleaning attempt for the departed guest');$id=(int)$roots[0]['id'];$c=(new Tivoli\Service\HousekeepingService($a))->detail((int)$f['users']['staff']['id'],$id);
check((int)$c['root']['data']['source_booking_id']===$first&&$c['status']==='released'&&!$c['valid'],'Original attempt retains source stay and released status');
check(count(array_filter($c['history'],fn($e)=>$e['kind']==='hk_completed'))===1&&count($c['inspections'])===1,'One cleaning completion and one successful final inspection');
check((int)$c['inspections'][0]['actor_id']===(int)$f['users']['inspector']['id']&&empty($c['inspections'][0]['data']['self']),'Independent inspector identity is retained');
check(count(array_filter($c['history'],fn($e)=>(int)$e['amount_minor']!==0))===0,'Cleaning and inspection create no revenue or cost amounts');
check((int)$r->run("SELECT COUNT(*) FROM security_audit WHERE action='stay.checkout' AND target_type='booking' AND target_id=? AND actor_id=? AND outcome='succeeded'",[(string)$first,$f['users']['staff']['id']])->fetchColumn()===1,'One durable checkout audit event');
check((int)$r->run("SELECT COUNT(*) FROM security_audit WHERE action='housekeeping.inspect' AND target_type='housekeeping' AND target_id=? AND actor_id=? AND outcome='succeeded'",[(string)$id,$f['users']['inspector']['id']])->fetchColumn()===1,'One durable independent inspection audit event');
$state=$r->run('SELECT status,ready_state FROM rooms JOIN stay_room_settings ON room_id=rooms.id WHERE rooms.id=?',[$room])->fetch();check($state['status']==='occupied','Old release replays cannot vacate the next guest room');
echo "TOTAL $n final turnover invariants passed; $retained original protected rows retained\n";
