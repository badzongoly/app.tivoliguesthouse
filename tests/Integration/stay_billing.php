<?php
if(PHP_SAPI!=='cli')exit(1);
putenv('TIVOLI_TEST_MODE=1');
require dirname(__DIR__,2).'/bootstrap/stays.php';
use Tivoli\Service\StayService;
use Tivoli\Service\FinanceService;
use Tivoli\Security\PermissionDenied;
$a=access_service();$r=$a->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307
 ||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'
 ||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$owner=(int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn();
$f=json_decode(file_get_contents(dirname(__DIR__,2).'/.local-setup/finance-fixture.json'),true,512,JSON_THROW_ON_ERROR);
$clerk=(int)$f['clerk'];$other=(int)$f['reviewer'];
putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$owner);
$s=new StayService($a,'2029-04-03 10:00:00');$tag=bin2hex(random_bytes(7));$count=0;
function cb(bool $ok,string $label):void{global $count;if(!$ok)throw new RuntimeException($label);$count++;echo "PASS $label\n";}
function denyBilling(callable $fn,string $label):void{try{$fn();}catch(Throwable $e){cb(true,$label);return;}throw new RuntimeException('Expected denial '.$label);}
function billingCmd(string $action,array $in,int $actor,?string $key=null):array{global $s;return $s->execute($actor,$action,$in,$key??bin2hex(random_bytes(16)));}
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard','300.00','vacant')",['Synthetic billing '.$tag]);$room=(int)$r->db->lastInsertId();
$r->run("INSERT INTO stay_room_settings(room_id,short_rate,short_enabled,ready_state) VALUES(?,'120.00',1,'ready')",[$room]);
$guest=['room_id'=>(string)$room,'guest_name'=>'Synthetic arrival','phone'=>'0000000000','gender'=>'Female','nationality'=>'Ghana','stay_type'=>'overnight','payment_type'=>'cash','amount'=>'300.00','expected_rate'=>'300.00','payment_attested'=>'1','identity_verified'=>'1','room_checked'=>'1','reference'=>'ARR-'.$tag];
$before=$r->run('SELECT COUNT(*) FROM bookings')->fetchColumn();
denyBilling(fn()=>billingCmd('checkin',array_diff_key($guest,['amount'=>1]),$clerk),'Check-in without received amount denied');
denyBilling(fn()=>billingCmd('checkin',['amount'=>'299.99']+$guest,$clerk),'Underpaid overnight check-in denied');
denyBilling(fn()=>billingCmd('checkin',['payment_type'=>'']+$guest,$clerk),'Missing payment method denied');
denyBilling(fn()=>billingCmd('checkin',['expected_rate'=>'250.00']+$guest,$clerk),'Changed displayed rate denied');
denyBilling(fn()=>billingCmd('checkin',array_diff_key($guest,['payment_attested'=>1]),$clerk),'Missing payment attestation denied');
cb($r->run('SELECT COUNT(*) FROM bookings')->fetchColumn()===$before,'Rejected check-ins create no booking');
$eventCounts=fn()=>[(int)$r->run('SELECT COUNT(*) FROM payments')->fetchColumn(),(int)$r->run('SELECT COUNT(*) FROM stay_receipts')->fetchColumn(),(int)$r->run('SELECT COUNT(*) FROM stay_operations')->fetchColumn()];
$original=$eventCounts();
$r->db->exec("CREATE TRIGGER billing_test_audit_failure BEFORE INSERT ON security_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic billing audit failure'");
try{denyBilling(fn()=>billingCmd('checkin',$guest,$clerk),'Audit failure rejects combined check-in');}finally{$r->db->exec('DROP TRIGGER billing_test_audit_failure');}
cb($eventCounts()===$original&&(int)$r->run("SELECT COUNT(*) FROM bookings WHERE room_id=?",[$room])->fetchColumn()===0
 &&$r->run('SELECT status FROM rooms WHERE id=?',[$room])->fetchColumn()==='vacant','Failed check-in leaves no payment, receipt, operation or occupancy');
$key=bin2hex(random_bytes(16));$id=billingCmd('checkin',$guest,$clerk,$key)['booking_id'];
cb(billingCmd('checkin',$guest,$clerk,$key)['booking_id']===$id,'Combined check-in retry returns same stay');
$payment=$r->run('SELECT p.*,s.actor_id,s.reference_no FROM payments p JOIN stay_receipts s ON s.payment_id=p.id WHERE booking_id=?',[$id])->fetchAll();
cb(count($payment)===1&&$payment[0]['amount']==='300.00'&&(int)$payment[0]['actor_id']===$clerk
 &&$payment[0]['reference_no']===$guest['reference']&&$payment[0]['created_at']==='2029-04-03 10:00:00','One exact receipt retains actor, reference and check-in timestamp');
cb($s->snapshot($clerk,$id)['bill']['balance']===0,'Initial accommodation is paid');
denyBilling(fn()=>billingCmd('checkin',['amount'=>'310.00']+$guest,$clerk,$key),'Changed check-in retry denied');
$r->run("INSERT INTO extras(name,price,stock_qty,active) VALUES(?,'30.00','10.00',1)",['Synthetic billing extra '.$tag]);$extra=(int)$r->db->lastInsertId();
billingCmd('extra',['booking_id'=>(string)$id,'extra_id'=>(string)$extra,'qty'=>'1','settlement'=>'room'],$clerk);
$approval=['booking_id'=>(string)$id,'expected_balance'=>'3000','reason'=>'Synthetic authorized unpaid departure'];
denyBilling(fn()=>billingCmd('allow_checkout',$approval,$clerk),'Clerk cannot authorize debt');
denyBilling(fn()=>billingCmd('allow_checkout',$approval,$other),'Other administrator cannot authorize debt');
denyBilling(fn()=>billingCmd('allow_checkout',array_diff_key($approval,['reason'=>1]),$owner),'Owner approval requires reason');
denyBilling(fn()=>billingCmd('allow_checkout',['expected_balance'=>'2000']+$approval,$owner),'Owner approval rejects stale balance');
putenv('TIVOLI_FINANCE_OWNER_USER_ID');
denyBilling(fn()=>billingCmd('allow_checkout',$approval,$owner),'Absent owner binding denies authorization');
putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$owner);
$r->run('INSERT INTO stay_checkout_approvals(booking_id,amount_minor,approved_by,approved_at) VALUES(?,?,?,?)',[$id,3000,$other,'2029-04-03 09:00:00']);
$legacyApproval=$r->run('SELECT * FROM stay_checkout_approvals WHERE booking_id=? ORDER BY id DESC LIMIT 1',[$id])->fetch();
cb(!$s->snapshot($clerk,$id)['bill']['approval'],'Historical approval by another administrator requires owner authorization');
billingCmd('allow_checkout',$approval,$owner);
cb($r->run('SELECT * FROM stay_checkout_approvals WHERE id=?',[$legacyApproval['id']])->fetch()===$legacyApproval,'Historical authorization record remains intact');
cb((bool)$s->snapshot($clerk,$id)['bill']['approval'],'Eugene approval is usable by clerk');
$e=json_decode($r->run("SELECT details FROM security_audit WHERE action='stay.allow_checkout' AND target_id=? ORDER BY id DESC LIMIT 1",[(string)$id])->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
cb($e['reason']===$approval['reason']&&$e['approved_balance']===3000&&$e['approval_id']>0,'Approval audit retains reason, amount and approval identity');
billingCmd('extra',['booking_id'=>(string)$id,'extra_id'=>(string)$extra,'qty'=>'1','settlement'=>'room'],$clerk);
cb(!$s->snapshot($clerk,$id)['bill']['approval'],'Debt increase invalidates earlier authorization');
denyBilling(fn()=>billingCmd('checkout',['booking_id'=>(string)$id,'expected_balance'=>'6000','extras_checked'=>'1','keys_returned'=>'1'],$clerk),'Clerk checkout above approved debt denied');
billingCmd('allow_checkout',['expected_balance'=>'6000']+$approval,$owner);
billingCmd('checkout',['booking_id'=>(string)$id,'expected_balance'=>'6000','extras_checked'=>'1','keys_returned'=>'1'],$clerk);
$final=$s->snapshot($clerk,$id)['bill'];
cb($final['balance']===6000&&$final['paid']===30000,'Authorized departure retains real debt and creates no receipt');
$future=new StayService($a,'2030-04-03 10:00:00');
cb($future->snapshot($clerk,$id)['bill']['room']===30000&&$future->snapshot($clerk,$id)['bill']['balance']===6000,'Departure room charges and debt remain frozen');
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard','300.00','vacant')",['Synthetic short billing '.$tag]);$shortRoom=(int)$r->db->lastInsertId();
$r->run("INSERT INTO stay_room_settings(room_id,short_rate,short_enabled,ready_state) VALUES(?,'120.00',1,'ready')",[$shortRoom]);
$short=['room_id'=>(string)$shortRoom,'stay_type'=>'short','amount'=>'120.00','expected_rate'=>'120.00','payment_type'=>'momo','reference'=>'SHORT-'.$tag]+$guest;
$shortId=billingCmd('checkin',$short,$clerk)['booking_id'];$shortBill=$s->snapshot($clerk,$shortId)['bill'];
cb($shortBill['paid']===12000&&$shortBill['balance']===0,'Short-stay check-in records exact session payment');
cb($future->snapshot($clerk,$shortId)['bill']['room']===12000,'Short overrun preserves fixed session rate');
$finance=new FinanceService($a,'2029-04-03 10:00:00');
cb(!(bool)array_filter($finance->openMoMoDiscrepancies('2029-04-03','2029-04-03'),fn($p)=>(int)$p['id']===(int)$r->run('SELECT id FROM payments WHERE booking_id=?',[$shortId])->fetchColumn()),'Arrival MoMo receipt is recorded without a separate match');
cb((bool)array_filter($finance->cash()['rows'],fn($p)=>(int)$p['id']===(int)$payment[0]['id']),'Arrival cash enters full-deposit tracking');
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard','300.00','vacant')",['Synthetic closed arrival '.$tag]);$closedRoom=(int)$r->db->lastInsertId();
$r->run("INSERT INTO stay_room_settings(room_id,short_rate,short_enabled,ready_state) VALUES(?,'120.00',1,'ready')",[$closedRoom]);
$r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES('day_closed','day','2029-04-03',0,'2029-04-03',?,'{}','2029-04-03 10:00:00')",[$owner]);
try {
 denyBilling(fn()=>billingCmd('checkin',['room_id'=>(string)$closedRoom]+$guest,$clerk),'Closed accounting day blocks paid check-in');
 cb((int)$r->run('SELECT COUNT(*) FROM bookings WHERE room_id=?',[$closedRoom])->fetchColumn()===0,'Closed-day denial leaves room without a stay or receipt');
} finally {
 $r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES('day_reopened','day','2029-04-03',0,'2029-04-03',?,'{}','2029-04-03 10:00:01')",[$owner]);
}
echo "TOTAL $count billing assertions passed.\n";
