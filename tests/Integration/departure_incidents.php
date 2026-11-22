<?php
if(PHP_SAPI!=='cli')exit(1);
putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';
use Tivoli\Service\StayService;use Tivoli\Service\FinanceService;use Tivoli\Service\FinanceAttachment;
$a=access_service();$r=$a->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$f=json_decode(file_get_contents(dirname(__DIR__,2).'/.local-setup/finance-fixture.json'),true,512,JSON_THROW_ON_ERROR);
$owner=(int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn();$clerk=(int)$f['clerk'];$other=(int)$f['reviewer'];
putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$owner);
$tag=bin2hex(random_bytes(7));$count=0;
function ci(bool $ok,string $label):void{global $count;if(!$ok)throw new RuntimeException($label);$count++;echo "PASS $label\n";}
function di(callable $f,string $label):void{try{$f();}catch(Throwable $e){ci(true,$label);return;}throw new RuntimeException('Expected denial '.$label);}
function ik():string{return bin2hex(random_bytes(16));}
function incidentGuest(string $at):array{global $a,$r,$clerk,$tag;
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard','100.00','vacant')",['Incident '.$tag.' '.substr(ik(),0,8)]);$room=(int)$r->db->lastInsertId();
$r->run("INSERT INTO stay_room_settings(room_id,short_rate,short_enabled,ready_state) VALUES(?,'40.00',1,'ready')",[$room]);
$s=new StayService($a,$at);$id=$s->execute($clerk,'checkin',['room_id'=>(string)$room,'guest_name'=>'Synthetic incident guest','phone'=>'0000000000','gender'=>'Female','nationality'=>'Ghana','stay_type'=>'overnight','payment_type'=>'cash','amount'=>'100.00','expected_rate'=>'100.00','identity_verified'=>'1','payment_attested'=>'1','room_checked'=>'1'],ik())['booking_id'];
$r->run("INSERT INTO extras(name,price,stock_qty,active) VALUES(?,'20.00','10.00',1)",['Synthetic incident extra '.ik()]);$extra=(int)$r->db->lastInsertId();
$s->execute($clerk,'extra',['booking_id'=>(string)$id,'extra_id'=>(string)$extra,'qty'=>'1','settlement'=>'room'],ik());
return [$id,$room];
}
if((new FinanceService($a))->periodLocked('2027-05-03')){
$r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES('period_reopened','period','week:2027-05-03',0,'2027-05-03',?,'{}','2027-05-03 09:00:00')",[$owner]);
}
[$id,$room]=incidentGuest('2027-05-03 10:00:00');$s=new StayService($a,'2027-05-05 10:00:00');
$report=['booking_id'=>(string)$id,'discovered_at'=>'2027-05-04T13:00:00','actual_departed_at'=>'2027-05-04T11:30:00','reason'=>'Guest left while reception was unattended','vacant'=>'1','secured'=>'1'];
$key=ik();$result=$s->execute($clerk,'report_departure',$report,$key);
ci($s->execute($clerk,'report_departure',$report,$key)['booking_id']===$id,'Report retry retains one incident');
$case=$s->departureCase($clerk,$id);ci($case['status']==='pending','Reported departure awaits owner review');
ci($r->run('SELECT status FROM rooms WHERE id=?',[$room])->fetchColumn()==='dirty','Verified vacant secured room enters cleaning');
ci($s->snapshot($clerk,$id)['booking']['status']==='checked_out','Physical departure ends occupancy without payment');
ci((int)$r->run('SELECT COUNT(*) FROM payments WHERE booking_id=?',[$id])->fetchColumn()===1,'Departure report fabricates no receipt');
ci((new StayService($a,'2028-01-01 12:00:00'))->snapshot($clerk,$id)['bill']['room']===10000,'Pending room charges stop accruing');
di(fn()=>$s->execute($clerk,'payment',['booking_id'=>(string)$id,'amount'=>'20.00','method'=>'cash'],ik()),'Ordinary post-departure payment remains denied');
di(fn()=>$s->departurePreview($other,$id,'2027-05-04T11:30:00'),'Other administrator cannot finalize incident');
$outside=$s->folios($clerk,0,['start'=>'2027-05-10','end'=>'2027-05-16']);
ci(!in_array($id,array_column($outside['rows'],'id')),'Reported departure is excluded from unrelated future account ranges');
foreach(['clean'=>'cleaning','inspect'=>'inspection'] as $action=>$kind){
 $list=\Tivoli\Service\CleaningChecklist::current($r,$kind);
 $checks=['room_id'=>(string)$room,'checklist_revision'=>$list['revision']];
 foreach($list['sections'] as $section)foreach($section['items'] as $item)$checks['done_'.$item['id']]='1';
 $s->execute($owner,$action,$checks,ik());
}
$newGuest=$s->execute($clerk,'checkin',['room_id'=>(string)$room,'guest_name'=>'Synthetic room reuse','phone'=>'0000000000','gender'=>'Male','nationality'=>'Ghana','stay_type'=>'overnight','amount'=>'100.00','expected_rate'=>'100.00','payment_type'=>'cash','identity_verified'=>'1','payment_attested'=>'1','room_checked'=>'1'],ik())['booking_id'];
ci($newGuest!==$id,'Secured room can be inspected and reused before debt review');
$s->execute($clerk,'report_departure',['booking_id'=>(string)$newGuest,'discovered_at'=>'2027-05-05T10:00:00','actual_departed_at'=>'2027-05-05T10:00:00','reason'=>'Second guest departure during prior incident review','vacant'=>'1','secured'=>'1'],ik());
ci($s->departureCase($clerk,$newGuest)['status']==='pending','Reused room can report another departure without false occupancy conflict');
di(fn()=>$s->execute($owner,'review_departure',['booking_id'=>(string)$id,'decision'=>'retract','reason'=>'Invalid reactivation after room reuse'],ik()),'Owner cannot restore old occupancy after room reuse');

$finance=new FinanceService($a,'2027-05-05 10:00:00');$old=$finance->summary('2027-05-03','2027-05-04');
$r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES('period_locked','period','week:2027-05-03',0,'2027-05-05',?,?,'2027-05-05 10:00:00')",[$owner,json_encode(['snapshot'=>$old],JSON_THROW_ON_ERROR)]);
// Post review in a separate open week.
$reviewer=new StayService($a,'2027-05-10 10:00:00');
$reviewRoomBefore=(new FinanceService($a,'2027-05-10 10:00:00'))->summary('2027-05-10','2027-05-10')['room'];
$preview=$reviewer->departurePreview($owner,$id,'2027-05-04T11:30:00');
$review=['booking_id'=>(string)$id,'decision'=>'confirm','actual_departed_at'=>$preview['actual_departed_at'],'review_token'=>$preview['token'],'reason'=>'Departure evidence reviewed','owner_id'=>(string)$clerk,'due'=>'2027-05-11'];
di(fn()=>$reviewer->execute($clerk,'review_departure',$review,ik()),'Clerk cannot finalize departure incident');
di(fn()=>$reviewer->execute($owner,'review_departure',['review_token'=>str_repeat('0',64)]+$review,ik()),'Stale review is rejected');
$reviewer->execute($owner,'review_departure',$review,ik());
ci($finance->summary('2027-05-03','2027-05-04')===$old,'Closed financial period totals remain unchanged');
ci((new FinanceService($a,'2027-05-10 10:00:00'))->summary('2027-05-10','2027-05-10')['room']===$reviewRoomBefore+10000,'Room revenue posts once in open review period');
$bill=$reviewer->snapshot($clerk,$id)['bill'];ci($bill['balance']===2000&&$bill['checkout_balance']===2000,'Final departure retains unpaid receivable');
$stored=$r->run('SELECT * FROM stay_departures WHERE booking_id=?',[$id])->fetch();
$pay=['booking_id'=>(string)$id,'amount'=>'5.00','expected_balance'=>'2000','method'=>'momo','reference'=>'INC-COLLECT-'.$tag,'reason'=>'Guest paid part of outstanding balance'];
di(fn()=>$reviewer->execute($clerk,'collect_outstanding',$pay,ik()),'Clerk cannot collect on departed account');
$paymentKey=ik();$reviewer->execute($owner,'collect_outstanding',$pay,$paymentKey);$reviewer->execute($owner,'collect_outstanding',$pay,$paymentKey);
ci($reviewer->snapshot($owner,$id)['bill']['balance']===1500,'Partial recovery and retry count once');
ci($r->run('SELECT * FROM stay_departures WHERE booking_id=?',[$id])->fetch()===$stored,'Collections preserve the final departure snapshot');
di(fn()=>$reviewer->execute($owner,'collect_outstanding',$pay,ik()),'Changed balance prevents stale collection');
[$unknown,$heldRoom]=incidentGuest('2027-06-01 10:00:00');$u=new StayService($a,'2027-06-02 13:00:00');
$u->execute($clerk,'report_departure',['booking_id'=>(string)$unknown,'discovered_at'=>'2027-06-02T12:30:00','reason'=>'Guest absent; actual departure time unknown'],ik());
ci($r->run('SELECT checkout_at FROM bookings WHERE id=?',[$unknown])->fetchColumn()===null,'Unknown departure time stays unknown');
ci($r->run('SELECT ready_state FROM stay_room_settings WHERE room_id=?',[$heldRoom])->fetchColumn()==='blocked','Unsecured room retains a hold');
di(fn()=>$u->execute($owner,'inspect',['room_id'=>(string)$heldRoom],ik()),'Inspection cannot bypass security hold');
$u->execute($clerk,'secure_departure_room',['booking_id'=>(string)$unknown,'vacant'=>'1','secured'=>'1','reason'=>'Room inspected and access secured'],ik());
ci($r->run('SELECT ready_state FROM stay_room_settings WHERE room_id=?',[$heldRoom])->fetchColumn()==='cleaning','Later security verification releases only into cleaning');
$u->execute($owner,'review_departure',['booking_id'=>(string)$unknown,'decision'=>'dispute','reason'=>'Investigating actual departure time'],ik());
ci($u->departureCase($clerk,$unknown)['status']==='disputed','Disputed report remains visible');
$u->execute($owner,'review_departure',['booking_id'=>(string)$unknown,'decision'=>'retract','reason'=>'Guest is still occupying original room'],ik());
ci($r->run('SELECT status FROM bookings WHERE id=?',[$unknown])->fetchColumn()==='active','Owner can retract before room reuse with original report preserved');
ci($u->departureCase($owner,$unknown)['status']==='retracted','Retraction preserves report and review history');
[$failure,$failureRoom]=incidentGuest('2027-07-01 10:00:00');
$failureService=new StayService($a,'2027-07-02 13:00:00');
$failureInput=['booking_id'=>(string)$failure,'discovered_at'=>'2027-07-02T12:30:00','reason'=>'Synthetic report with unknown departure'];
$r->db->exec("CREATE TRIGGER incident_test_audit_failure BEFORE INSERT ON security_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic incident audit failure'");
try{di(fn()=>$failureService->execute($clerk,'report_departure',$failureInput,ik()),'Audit failure rejects departure report');}finally{$r->db->exec('DROP TRIGGER incident_test_audit_failure');}
ci($failureService->departureCase($clerk,$failure)===null&&$r->run('SELECT status FROM rooms WHERE id=?',[$failureRoom])->fetchColumn()==='occupied','Failed incident leaves no report or room transition');
di(fn()=>$failureService->execute($clerk,'report_departure',['actual_departed_at'=>'2027-06-30T10:00:00']+$failureInput,ik()),'Departure before check-in is rejected');
$r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES('day_closed','day','2027-07-02',0,'2027-07-02',?,'{}','2027-07-02 12:00:00')",[$owner]);
try{
 $failureService->execute($clerk,'report_departure',$failureInput,ik());
 ci($failureService->departureCase($clerk,$failure)['status']==='pending','Closed accounting day still permits factual departure report');
 $p=$failureService->departurePreview($owner,$failure,'2027-07-02T11:00:00');
 $confirmation=['booking_id'=>(string)$failure,'decision'=>'confirm','actual_departed_at'=>$p['actual_departed_at'],'review_token'=>$p['token'],'reason'=>'Evidence confirms earlier departure','owner_id'=>(string)$clerk,'due'=>'2027-07-03'];
 di(fn()=>$failureService->execute($owner,'review_departure',$confirmation,ik()),'Closed posting day blocks financial finalization');
 ci(!(bool)$r->run('SELECT 1 FROM stay_departures WHERE booking_id=?',[$failure])->fetchColumn(),'Failed financial review leaves no final snapshot');
}finally{
 $r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES('day_reopened','day','2027-07-02',0,'2027-07-02',?,'{}','2027-07-02 13:00:00')",[$owner]);
}
$failureService->execute($owner,'review_departure',$confirmation,ik());
ci($failureService->snapshot($owner,$failure)['bill']['room']===10000,'Owner evidence can finalize an earlier time without altering original report');
$case=$failureService->departureCase($owner,$failure);
ci($case['report']['data']['snapshot']['room']===20000&&$case['report']['data']['actual_departed_at']===null,'Original unknown-time provisional bill is retained');

$retractedRows=array_values(array_filter($u->departureCases($owner),fn($row)=>(int)$row['id']===$unknown));
ci(count($retractedRows)===1&&$retractedRows[0]['resolved'],'Retracted report remains in resolved incident history');
[$creditId,$creditRoom]=incidentGuest('2027-08-01 10:00:00');
$creditService=new StayService($a,'2027-08-01 10:00:00');
$creditService->execute($clerk,'payment',['booking_id'=>(string)$creditId,'amount'=>'50.00','method'=>'cash','reference'=>'CREDIT-'.$tag],ik());
$creditService->execute($clerk,'report_departure',['booking_id'=>(string)$creditId,'discovered_at'=>'2027-08-01T10:00:00','actual_departed_at'=>'2027-08-01T10:00:00','reason'=>'Departed guest has an overpayment','vacant'=>'1','secured'=>'1'],ik());
$creditPreview=$creditService->departurePreview($owner,$creditId,'2027-08-01T10:00:00');
$creditReview=['booking_id'=>(string)$creditId,'decision'=>'confirm','actual_departed_at'=>$creditPreview['actual_departed_at'],'review_token'=>$creditPreview['token'],'reason'=>'Confirm guest credit and arrange settlement','owner_id'=>(string)$clerk,'due'=>'2027-08-02'];
$creditService->execute($owner,'review_departure',$creditReview,ik());
$creditRows=array_values(array_filter($creditService->departureCases($owner),fn($row)=>(int)$row['id']===$creditId));
ci(count($creditRows)===1&&!$creditRows[0]['resolved']&&$creditRows[0]['bill']['balance']===-3000,'Guest credit remains an open settlement item');
$creditPayment=(int)$r->run('SELECT MIN(id) FROM payments WHERE booking_id=?',[$creditId])->fetchColumn();
$refund=['method'=>'momo','actual_at'=>'2027-08-01 10:00:00','actual_payer'=>'Synthetic actual owner','completed'=>'1','booking_id'=>(string)$creditId,'payment_id'=>(string)$creditPayment,'amount'=>'30.00','proof'=>'CREDIT-RETURN-'.$tag,'reason'=>'Actual MoMo return of guest credit'];
di(fn()=>$creditService->execute($other,'refund',$refund,ik()),'Another administrator cannot refund an incident guest credit');
$creditService->execute($owner,'refund',$refund,ik());
$creditRows=array_values(array_filter($creditService->departureCases($owner),fn($row)=>(int)$row['id']===$creditId));
ci($creditRows[0]['resolved']&&$creditRows[0]['bill']['balance']===0,'Owner refund settles guest credit exactly once');

$firstReport=$u->departureCase($owner,$unknown)['report'];
$u->execute($clerk,'report_departure',['booking_id'=>(string)$unknown,'discovered_at'=>'2027-06-02T13:00:00','reason'=>'A later departure after the corrected first report','vacant'=>'1','secured'=>'1'],ik());
$caseView=$u->snapshot($owner,$unknown);
ci(count($caseView['prior_departure_cases'])===1
 && $caseView['prior_departure_cases'][0]['status']==='retracted'
 && $caseView['prior_departure_cases'][0]['report']['payload']===$firstReport['payload'],'Re-reporting retains earlier report facts and retraction history');
$listed=array_values(array_filter($u->departureCases($owner),fn($row)=>(int)$row['id']===$unknown));
ci((int)$listed[0]['report_count']===2,'Incident register exposes earlier reports after re-reporting');

echo "TOTAL $count departure incident assertions passed.\n";
