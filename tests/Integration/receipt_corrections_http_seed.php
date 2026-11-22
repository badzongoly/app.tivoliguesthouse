<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';$r=access_service()->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$owner=(int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn();$tag=bin2hex(random_bytes(5));$users=[];
foreach(['staff'=>'clerk','other'=>'clerk','admin'=>'admin','outsider'=>'clerk'] as $key=>$role){
 $email='correction-'.$key.'-'.$tag.'@example.invalid';$r->run('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',['Synthetic correction '.$key,$email,password_hash('Synthetic Correction Passphrase 123!',PASSWORD_BCRYPT),$role]);$id=(int)$r->db->lastInsertId();$r->run('INSERT INTO security_accounts(user_id,must_change_password) VALUES(?,0)',[$id]);if($key!=='outsider')$r->run('INSERT INTO security_user_roles SELECT ?,id FROM security_roles WHERE name=?',[$id,$role]);$users[$key]=compact('id','email');
}
$receipts=[];$old=gmdate('Y-m-d',strtotime('-2 days'));$departure=gmdate('Y-m-d',strtotime('-1 day'));
foreach(['amount'=>'momo','method'=>'cash','deposit'=>'cash'] as $key=>$method){
 $r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard',500,'dirty')",['Synthetic corrections HTTP '.$key.' '.$tag]);$room=(int)$r->db->lastInsertId();$r->run("INSERT INTO bookings(room_id,rate_per_night,guest_name,checkin_at,status) VALUES(?,500,?,?,'active')",[$room,'Synthetic Correction Guest '.$key.' '.$tag,$old.' 14:00:00']);$booking=(int)$r->db->lastInsertId();$amount=$key==='method'?'300.00':'500.00';$ref=$method==='momo'?'HTTP-ORIGINAL-'.$tag.'-'.$key:'';
 $r->run('INSERT INTO payments(booking_id,method,amount,note,created_at) VALUES(?,?,?,?,?)',[$booking,$method,$amount,'Synthetic original receipt',$old.' 14:01:00']);$payment=(int)$r->db->lastInsertId();$r->run('INSERT INTO stay_receipts VALUES(?,?,?,?)',[$payment,$users['staff']['id'],$ref,$old.' 14:01:00']);
 $r->run("UPDATE bookings SET status='checked_out',checkout_at=? WHERE id=?",[$departure.' 10:00:00',$booking]);$paid=\Tivoli\Domain\Stay\StayRules::minor($amount);$r->run('INSERT INTO stay_departures(booking_id,room_minor,extras_minor,incidental_minor,paid_minor,balance_minor,actor_id,departed_at,note,refund_cursor) VALUES(?,50000,0,0,?,?,?,?,?,0)',[$booking,$paid,50000-$paid,$owner,$departure.' 10:00:00','Synthetic frozen checkout']);
 if($key==='deposit')$r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES('deposit_recorded','wallet','business',50000,?,?,?,?)",[gmdate('Y-m-d'),$users['staff']['id'],json_encode(['allocations'=>[(string)$payment=>50000],'fee'=>0,'reference'=>'HTTP-DEPOSIT-'.$tag]),gmdate('Y-m-d H:i:s')]);
 $receipts[$key]=compact('payment','booking','room','ref');
}
file_put_contents(dirname(__DIR__,2).'/.local-setup/receipt-corrections-http-fixture.json',json_encode(compact('users','receipts','tag')));
