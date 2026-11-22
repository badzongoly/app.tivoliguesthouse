<?php
if (PHP_SAPI!=='cli') exit(1);
putenv('TIVOLI_TEST_MODE=1');
require dirname(__DIR__,2).'/bootstrap/security.php';

use Tivoli\Service\ExpenseService;
use Tivoli\Service\FinanceService;
use Tivoli\Service\StayService;
use Tivoli\Security\PermissionDenied;

$a=access_service();
$r=$a->repo;
if ($r->run('SELECT @@port')->fetchColumn()!=3307
    || $r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'
    || $r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1') exit(2);

function check(bool $result,string $label):void {
    if (!$result) throw new RuntimeException($label);
    echo "PASS $label\n";
}
function keyForTest():string { return bin2hex(random_bytes(16)); }
function denied(callable $attempt,string $type,string $label):void {
    try { $attempt(); } catch (Throwable $e) {
        check($e instanceof $type,$label.': '.get_class($e));
        return;
    }
    throw new RuntimeException('Expected denial: '.$label);
}
$owner=(int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn();
$fixture=json_decode(file_get_contents(dirname(__DIR__,2).'/.local-setup/finance-fixture.json'),true,512,JSON_THROW_ON_ERROR);
$clerk=(int)$r->run('SELECT id FROM users WHERE email=?',[$fixture['clerk_email']])->fetchColumn();
$today=gmdate('Y-m-d');
$clock=$today.' 12:00:00';
$old='2004-03-03';
$start='2004-03-01';
$end='2004-03-07';
$tag=keyForTest();
$finance=new FinanceService($a,$clock);
if (!$owner || !$clerk || !$finance->installed() || $finance->periodLocked($today) || $finance->dayClosed($today)) {
    throw new RuntimeException('Expected active synthetic fixtures and an open posting day.');
}
putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$owner);
$stays=new StayService($a,$clock);
$expenses=new ExpenseService($a);
$baseline=$finance->summary($start,$end);
if (!$finance->periodLocked($old)) {
    $r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at)
        VALUES('period_locked','period',?,0,?,?,?,?)",['week:'.$start,$old,$owner,json_encode(['snapshot'=>$baseline],JSON_THROW_ON_ERROR),$clock]);
}
$snapshot=$r->run("SELECT payload FROM finance_events WHERE target_type='period' AND target_id=? ORDER BY id DESC LIMIT 1",['week:'.$start])->fetchColumn();
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard','100.00','occupied')",['Synthetic late '.$tag]);
$room=(int)$r->db->lastInsertId();
$r->run("INSERT INTO bookings(room_id,rate_per_night,guest_name,checkin_at,status) VALUES(?,'100.00','Synthetic late guest',?,'active')",[$room,$old.' 08:00:00']);
$booking=(int)$r->db->lastInsertId();
$r->run("INSERT INTO stay_details VALUES(?,'short','','','','',0,0,0,NULL,?,?)",[$booking,$owner,$old.' 08:00:00']);
$baseline=$finance->summary($start,$end);
$open=$finance->summary($today,$today);
$late=['original_activity_date'=>$old,'late_reason'=>'Synthetic evidence received after closure'];
$payment=$late+['booking_id'=>(string)$booking,'amount'=>'150.00','method'=>'cash','reference'=>'LATE-CASH-'.$tag];
denied(fn()=>$stays->execute($clerk,'payment',$payment,keyForTest()),PermissionDenied::class,'clerk cannot record late payment');
putenv('TIVOLI_FINANCE_OWNER_USER_ID');
denied(fn()=>$stays->execute($owner,'payment',$payment,keyForTest()),PermissionDenied::class,'unconfigured owner binding fails closed');
putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$owner);
$key=keyForTest();
$stays->execute($owner,'payment',$payment,$key);
$paymentId=(int)$r->run('SELECT payment_id FROM stay_receipts WHERE reference_no=?',[$payment['reference']])->fetchColumn();
$stays->execute($owner,'payment',$payment,$key);
check($paymentId>0 && (int)$r->run('SELECT COUNT(*) FROM stay_receipts WHERE reference_no=?',[$payment['reference']])->fetchColumn()===1,'identical receipt retry returns one entry');
$source=$r->run('SELECT * FROM payments WHERE id=?',[$paymentId])->fetch();
check($source['created_at']===$clock && $source['amount']==='150.00','late receipt posts on the open date');
$refund=$late+['method'=>'momo','actual_at'=>$old.' 18:00:00','actual_payer'=>'Synthetic actual owner','completed'=>'1','booking_id'=>(string)$booking,'payment_id'=>(string)$paymentId,'amount'=>'20.00','proof'=>'LATE-REFUND-'.$tag,'reason'=>'Synthetic partial refund'];
// A refund cannot be evidenced as predating the underlying receipt.
denied(fn()=>$stays->execute($owner,'refund',['original_activity_date'=>'2004-03-02']+$refund,keyForTest()),RuntimeException::class,'refund date before its receipt is rejected');
$stays->execute($owner,'refund',$refund,keyForTest());
$refundId=(int)$r->run('SELECT id FROM stay_refunds WHERE payment_id=? AND proof=?',[$paymentId,$refund['proof']])->fetchColumn();
check($r->run('SELECT created_at FROM stay_refunds WHERE id=?',[$refundId])->fetchColumn()===$clock,'late refund posts on the open date');
$category=(string)$r->run('SELECT id FROM expense_categories WHERE active=1 ORDER BY id LIMIT 1')->fetchColumn();
$expense=$late+['expense_date'=>$old,'category_id'=>$category,'method'=>'cash','amount'=>'17.25','vendor'=>'Synthetic Vendor','reference_no'=>'LATE-COST-'.$tag];
$cost=$expenses->submit($owner,$expense,keyForTest());
check($r->run('SELECT expense_date FROM expenses WHERE id=?',[$cost['id']])->fetchColumn()===$today,'late expense posts today');
denied(fn()=>$expenses->submit($owner,$expense,keyForTest()),RuntimeException::class,'new form cannot duplicate the late expense');
foreach (['payment'=>$paymentId,'refund'=>$refundId,'expense'=>$cost['id']] as $type=>$id) {
    $event=$r->run("SELECT * FROM finance_events WHERE kind='late_posting' AND target_type=? AND target_id=?",[$type,(string)$id])->fetch();
    $details=json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR);
    check($details['original_activity_date']===$old && $event['effective_date']===$today && (int)$event['actor_id']===$owner && (int)$event['amount_minor']===0,
        "$type evidence is linked once without posting a second amount");
}
$usedReference='SYN-USED-MOMO-'.$tag;
$originalEvent=(int)$r->run("SELECT id FROM finance_events WHERE kind='late_posting' AND target_type='payment' AND target_id=?",[(string)$paymentId])->fetchColumn();
$r->run("INSERT INTO finance_claims(scope,identity_hash,event_id) VALUES('business_momo',?,?)",[hash('sha256',strtolower($usedReference)),$originalEvent]);
denied(fn()=>$stays->execute($owner,'payment',['method'=>'momo','reference'=>$usedReference]+$payment,keyForTest()),RuntimeException::class,'late MoMo cannot reuse a reconciled reference');
$payout=$late+['id'=>(string)$cost['id'],'type'=>'expense','method'=>'cash','amount'=>'17.25','reference'=>'LATE-PAYOUT-'.$tag,'evidence'=>'Synthetic completed payout','destination'=>'Synthetic Vendor','verified'=>'1'];
$payoutId=(int)$finance->execute($owner,'pay_cost',$payout,keyForTest())['id'];
$paid=$r->run("SELECT effective_date,amount_minor FROM finance_events WHERE kind='cost_paid' AND id=?",[$payoutId])->fetch();
$paidLink=$r->run("SELECT payload FROM finance_events WHERE kind='late_posting' AND target_type='payout' AND target_id=?",[(string)$payoutId])->fetchColumn();
check($paid['effective_date']===$today && (int)$paid['amount_minor']===1725 && json_decode($paidLink,true)['original_activity_date']===$old,'late payout is separately linked to its activity date');
$activity=$late+['method'=>'cash','amount'=>'12.50','reference'=>'LATE-ACTIVITY-'.$tag,'payer'=>'Synthetic payer','activity'=>'Synthetic activity','treatment'=>'advance'];
$activityId=(int)$finance->execute($owner,'income_record',$activity,keyForTest())['id'];
$activityLink=$r->run("SELECT payload FROM finance_events WHERE kind='late_posting' AND target_type='income' AND target_id=?",[(string)$activityId])->fetchColumn();
check(json_decode($activityLink,true)['original_activity_date']===$old,'late non-guest receipt retains its original date');
$after=$finance->summary($today,$today);
check(($after['receipts']['cash']??0)-($open['receipts']['cash']??0)===16250 && $after['expenses']-$open['expenses']===1725 && $after['refunds']-$open['refunds']===2000 && $after['paid_costs']-$open['paid_costs']===1725,'open period reflects exact receipt and cost once');
check($finance->summary($start,$end)===$baseline
    && $snapshot===$r->run("SELECT payload FROM finance_events WHERE target_type='period' AND target_id=? ORDER BY id DESC LIMIT 1",['week:'.$start])->fetchColumn(),
    'closed totals and stored snapshot are unchanged');

// A departed folio is a separate path from ordinary payment capture.
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard','100.00','dirty')",['Synthetic departed '.$tag]);
$departedRoom=(int)$r->db->lastInsertId();
$r->run("INSERT INTO bookings(room_id,rate_per_night,guest_name,checkin_at,checkout_at,status) VALUES(?,'100.00','Synthetic departed guest',?,?,'checked_out')",[$departedRoom,$old.' 08:00:00',$old.' 17:00:00']);
$departed=(int)$r->db->lastInsertId();
$r->run('INSERT INTO stay_departures(booking_id,room_minor,extras_minor,incidental_minor,paid_minor,balance_minor,actor_id,departed_at,note) VALUES(?,10000,0,0,0,10000,?,?,?)',[$departed,$owner,$old.' 17:00:00','Synthetic checkout']);
$frozen=$r->run('SELECT * FROM stay_departures WHERE booking_id=?',[$departed])->fetch();
$beforeRecovery=$finance->summary($today,$today);
$logFilter=['start'=>$today,'end'=>$today,'direction'=>'incoming'];
$incomingBefore=(int)\Tivoli\Service\FinancePaymentLog::read($a,$owner,$logFilter)['totals']['incoming'];
$ownerReports=new \Tivoli\Repository\OwnerReports($r);
$ownerFilter=\Tivoli\Repository\OwnerReports::filters(['view'=>'daily','end'=>$today],new DateTimeImmutable($today.' 23:59:59',new DateTimeZone('UTC')));
$ownerBefore=(int)$ownerReports->read($ownerFilter)['payment']['minor'];
$closedBeforeRecovery=$finance->summary($start,$end);
$recovery=$late+['booking_id'=>(string)$departed,'amount'=>'35.00','method'=>'cash','reference'=>'RECOVER-'.$tag];
denied(fn()=>$stays->execute($clerk,'recover_receipt',$recovery,keyForTest()),PermissionDenied::class,'clerk cannot recover a departed receipt');
$key=keyForTest();
$stays->execute($owner,'recover_receipt',$recovery,$key);
$stays->execute($owner,'recover_receipt',$recovery,$key);
$folio=$stays->snapshot($owner,$departed);
check($folio['bill']['balance']===6500 && $folio['bill']['recovered']===3500 && count($folio['payments'])===1,'departure recovery reduces the receivable once');
check($r->run('SELECT * FROM stay_departures WHERE booking_id=?',[$departed])->fetch()===$frozen,'departure snapshot stays byte-for-byte intact');
check((int)\Tivoli\Service\FinancePaymentLog::read($a,$owner,$logFilter)['totals']['incoming']-$incomingBefore===3500
    && (int)$ownerReports->read($ownerFilter)['payment']['minor']-$ownerBefore===3500,
    'Payment Log and Owner Reports each count one recovered receipt');
$eventId=(int)$folio['payments'][0]['recovery_event_id'];
$sql='INSERT INTO payments(booking_id,method,amount,created_at,recovery_event_id) VALUES(?,?,?,?,?)';
function expectRecoverySqlState(callable $write,string $state,string $message,string $label):void {
    try { $write(); } catch (PDOException $error) {
        check(($error->errorInfo[0] ?? null)===$state && str_contains($error->getMessage(),$message),$label);
        return;
    }
    throw new RuntimeException('Unexpected database write: '.$label);
}
foreach ([
    [$departed,'cash','35.01','amount'],
    [$departed,'momo','35.00','method'],
    [$booking,'cash','35.00','stay'],
] as [$target,$method,$amount,$mismatch]) {
    expectRecoverySqlState(fn()=>$r->run($sql,[$target,$method,$amount,$clock,$eventId]),'45000',
        'Departed receipts require matching recovery authorization','trigger rejects a mismatched recovery '.$mismatch);
}
expectRecoverySqlState(fn()=>$r->run($sql,[$departed,'cash','35.00',$clock,$eventId]),'23000',
    'payment_recovery_once','unique constraint rejects reuse of matching authorization');
check($finance->summary($today,$today)['receipts']['cash']-($beforeRecovery['receipts']['cash']??0)===3500
    && $finance->summary($start,$end)===$closedBeforeRecovery,'the open period counts the receipt once and the original period stays fixed');
denied(fn()=>$stays->execute($owner,'payment',$recovery,keyForTest()),RuntimeException::class,'ordinary payment still rejects a departed stay');
denied(fn()=>$stays->execute($owner,'recover_receipt',['amount'=>'65.01','reference'=>'OVER-'.$tag]+$recovery,keyForTest()),RuntimeException::class,'recovery cannot exceed the remaining receivable');
denied(fn()=>$stays->execute($owner,'recover_receipt',$recovery,keyForTest()),RuntimeException::class,'duplicate recovery reference is denied');
denied(fn()=>$r->run("INSERT INTO payments(booking_id,method,amount,created_at) VALUES(?,'cash','1.00',?)",[$departed,$clock]),PDOException::class,'database rejects a departed receipt without its authorization link');

$unchanged=$r->run("SELECT status,checkout_at FROM bookings WHERE id=?",[$departed])->fetch();
check($unchanged['status']==='checked_out' && $unchanged['checkout_at']===$old.' 17:00:00'
    && $r->run('SELECT status FROM rooms WHERE id=?',[$departedRoom])->fetchColumn()==='dirty',
    'recovery leaves checkout and room state unchanged');
$activeBalance=$stays->snapshot($owner,$booking)['bill']['balance'];
denied(fn()=>$stays->execute($owner,'recover_receipt',['booking_id'=>(string)$booking]+$recovery,keyForTest()),RuntimeException::class,'recovery requires a departed stay');
check($stays->snapshot($owner,$booking)['bill']['balance']===$activeBalance,'denied recovery leaves the active folio unchanged');
$r->db->exec("CREATE TRIGGER stay_test_recovery_audit_failure BEFORE INSERT ON security_audit FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic recovery audit failure'");
$rollbackKey=keyForTest();
$rollbackReference='RECOVER-ROLLBACK-'.$tag;
try {
    denied(fn()=>$stays->execute($owner,'recover_receipt',['amount'=>'2.00','reference'=>$rollbackReference]+$recovery,$rollbackKey),
        PDOException::class,'audit failure rejects the recovery');
} finally {
    $r->db->exec('DROP TRIGGER stay_test_recovery_audit_failure');
}
check((int)$r->run('SELECT COUNT(*) FROM payments WHERE booking_id=?',[$departed])->fetchColumn()===1
    && (int)$r->run("SELECT COUNT(*) FROM finance_events WHERE kind='departed_receipt_authorized' AND target_id=?",[(string)$departed])->fetchColumn()===1
    && !(bool)$r->run('SELECT 1 FROM stay_operations WHERE actor_id=? AND operation_key=?',[$owner,$rollbackKey])->fetchColumn(),
    'audit failure rolls back authorization, payment and retry record');
$beforeReplay=$r->run('SELECT * FROM payments WHERE booking_id=? ORDER BY id',[$departed])->fetchAll();
(require dirname(__DIR__,2).'/database/migrations/010_departed_receipt_recovery.php')($r);
require_once dirname(__DIR__,2).'/database/install-stays.php';
stay_install_schema($r);
check(!(bool)$r->run("SELECT 1 FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='stay_payment_active'")->fetchColumn(),
    'ordinary setup replay retains the new guard without reinstating the old trigger');
check($r->run('SELECT * FROM payments WHERE booking_id=? ORDER BY id',[$departed])->fetchAll()===$beforeReplay
    && $r->run('SELECT * FROM stay_departures WHERE booking_id=?',[$departed])->fetch()===$frozen,
    'migration rerun preserves recovery payment and original checkout evidence');
