<?php
if (PHP_SAPI !== 'cli') exit(1);
putenv('TIVOLI_TEST_MODE=1');
require dirname(__DIR__, 2).'/bootstrap/security.php';

use Tivoli\Security\PermissionDenied;
use Tivoli\Service\FinanceService;

$a = access_service();
$r = $a->repo;
if ((int)$r->run('SELECT @@port')->fetchColumn() !== 3307
    || $r->run('SELECT DATABASE()')->fetchColumn() !== 'tivoli_auth_test'
    || $r->run('SELECT marker FROM security_test_marker')->fetchColumn() !== 'synthetic-auth-only-v1') exit(2);

function checkWeek(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS $message\n";
}

function rejectsWeek(callable $fn, string $class, string $message): void {
    try { $fn(); } catch (Throwable $e) {
        checkWeek($e instanceof $class && str_contains($e->getMessage(), $message), $message.' ('.get_class($e).')');
        return;
    }
    throw new RuntimeException('Expected rejection: '.$message);
}

$owner = (int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn();
$category = (int)$r->run('SELECT MIN(id) FROM expense_categories')->fetchColumn();
if (!$owner || !$category) throw new RuntimeException('Missing synthetic fixture');
$tag = bin2hex(random_bytes(8));
putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$owner);
$start = new DateTimeImmutable('2071-01-05', new DateTimeZone('UTC'));
while ((new FinanceService($a))->periodLocked($start->format('Y-m-d'))
    || $r->run("SELECT 1 FROM finance_events WHERE target_type='period' AND target_id=? LIMIT 1",['week:'.$start->format('Y-m-d')])->fetchColumn()) {
    $start = $start->modify('+7 days');
}
$monday = $start->format('Y-m-d');
$sunday = $start->modify('+6 days')->format('Y-m-d');
$reviewDay = $start->modify('+7 days')->format('Y-m-d');
$s = new FinanceService($a, $reviewDay.' 12:00:00');
$command = fn(string $action,array $input,?string $key=null) => $s->execute($owner,$action,$input,$key ?? bin2hex(random_bytes(16)));
$selection = ['start'=>$monday];

putenv('TIVOLI_FINANCE_OWNER_USER_ID='.($owner+1000000));
rejectsWeek(fn()=>$command('week_decide',$selection+['outcome'=>'open','comment'=>'Waiting for evidence']),PermissionDenied::class,'designated finance owner');
putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$owner);
rejectsWeek(fn()=>$command('week_decide',['start'=>$reviewDay,'outcome'=>'open','comment'=>'Waiting for evidence']),RuntimeException::class,'completed Monday–Sunday week');
rejectsWeek(fn()=>$command('period_lock',$selection+['period'=>'week','complete'=>'1','comment'=>'Old form']),RuntimeException::class,'Weekly Reconciliation');

$command('week_decide',$selection+['outcome'=>'open','comment'=>'Waiting for evidence']);
checkWeek(!$s->periodLocked($monday),'Keep open records a decision without locking');

$prior = new FinanceService($a,$sunday.' 23:59:59');
$momo = $prior->execute($owner,'income_record',[
    'payer'=>'Synthetic weekly payer','activity'=>'Synthetic activity','method'=>'momo','amount'=>'27.50',
    'treatment'=>'earned','provided'=>'1','reference'=>'RECORD-'.$tag,
],bin2hex(random_bytes(16)))['id'];
$lock = fn(string $outcome)=>$selection+['outcome'=>$outcome,'comment'=>'Synthetic review','evidence'=>'STATEMENT-'.$tag,
    'complete'=>'1','review_token'=>$s->weeklyReview($monday)['token']];
rejectsWeek(fn()=>$command('week_decide',$lock('carry')),RuntimeException::class,'Match all MoMo receipts');
$command('receipt_match',['type'=>'income','id'=>(string)$momo,'amount'=>'27.50','reference'=>'MATCH-'.$tag,
    'evidence'=>'Synthetic wallet evidence','verified'=>'1']);

$r->run("INSERT INTO expense_submissions(submitted_by,expense_date,category_id,method,amount,vendor,reference_no,description)
    VALUES(?,?,?,'momo','9.25','Synthetic vendor',?,'Awaiting receipt')",[$owner,$monday,$category,$tag]);
$case = (int)$r->db->lastInsertId();
rejectsWeek(fn()=>$command('week_decide',$lock('clean')),RuntimeException::class,'Resolve pending expenses');
rejectsWeek(fn()=>$command('week_decide',$lock('carry')),RuntimeException::class,'Assign every pending expense');
$assignment = ['owner_'.$case=>(string)$owner,'due_'.$case=>$reviewDay];
$input = $lock('carry')+$assignment;
$stale = $input;
$stale['review_token'] = str_repeat('0',64);
rejectsWeek(fn()=>$command('week_decide',$stale),RuntimeException::class,'changed since you opened');
$key = bin2hex(random_bytes(16));
$id = $command('week_decide',$input,$key)['id'];
checkWeek($command('week_decide',$input,$key)['replayed'],'Exact retry reuses the closed review');
checkWeek($s->periodLocked($monday)&&$s->periodLocked($sunday),'Week lock covers Monday through Sunday');
$stored = $r->run('SELECT payload FROM finance_events WHERE id=?',[$id])->fetchColumn();
$data = json_decode($stored,true,512,JSON_THROW_ON_ERROR);
checkWeek($data['snapshot']['receipts']['momo']===2750 && $data['snapshot']['expenses']===0
    && $data['carried'][0]['id']===$case && $data['carried'][0]['amount_minor']===925,'Snapshot keeps exact receipts, excludes pending cost and records the case');
rejectsWeek(fn()=>$command('period_reopen',$selection+['period'=>'week','comment'=>'Old reopen']),RuntimeException::class,'Weekly Reconciliation');
rejectsWeek(fn()=>FinanceService::guardSource($a,$monday),RuntimeException::class,'accounting period is locked');
rejectsWeek(fn()=>$command('week_followup',$selection+['case_id'=>(string)$case,'evidence'=>'PROOF-'.$tag]),RuntimeException::class,'Review the source expense');
$r->run("UPDATE expense_submissions SET status='rejected',reviewed_by=?,reviewed_at=UTC_TIMESTAMP(),review_reason='Synthetic source review' WHERE id=?",[(int)$r->run("SELECT MIN(id) FROM users WHERE id<>?",[$owner])->fetchColumn(),$case]);
$command('week_followup',$selection+['case_id'=>(string)$case,'evidence'=>'PROOF-'.$tag,'comment'=>'Source rejected']);
checkWeek($r->run('SELECT payload FROM finance_events WHERE id=?',[$id])->fetchColumn()===$stored,'Follow-up leaves the closure payload byte-for-byte intact');
checkWeek($s->weeklyReview($monday)['followups'][$case]['data']['evidence']==='PROOF-'.$tag,'Follow-up is linked and visible');
rejectsWeek(fn()=>$command('week_followup',$selection+['case_id'=>(string)$case,'evidence'=>'Duplicate','comment'=>'Second attempt']),RuntimeException::class,'already resolved');

$nextReview = new FinanceService($a,$start->modify('+14 days')->format('Y-m-d').' 12:00:00');
$cleanStart = $reviewDay;
$nextReview->execute($owner,'week_decide',['start'=>$cleanStart,'outcome'=>'clean','complete'=>'1','evidence'=>'CLEAN-'.$tag,
    'review_token'=>$nextReview->weeklyReview($cleanStart)['token']],bin2hex(random_bytes(16)));
checkWeek($nextReview->periodLocked($cleanStart),'A reconciled week can close without exceptions');

$clerk=(int)$r->run("SELECT u.id FROM users u JOIN security_accounts a ON a.user_id=u.id WHERE a.active=1 AND u.role='clerk' ORDER BY u.id LIMIT 1")->fetchColumn();
$oldStart=new DateTimeImmutable('2001-01-01',new DateTimeZone('UTC'));
while($r->run("SELECT 1 FROM finance_events WHERE target_type='period' AND target_id=? LIMIT 1",['week:'.$oldStart->format('Y-m-d')])->fetchColumn())$oldStart=$oldStart->modify('+7 days');
$oldMonday=$oldStart->format('Y-m-d');
$r->run("INSERT INTO expense_submissions(submitted_by,expense_date,category_id,method,amount,vendor,reference_no,description)
 VALUES(?,?,?,'momo','12.35','Synthetic carried cost',?,'Synthetic late evidence')",[$clerk,$oldMonday,$category,'CARRIED-'.$tag]);
$sourceId=(int)$r->db->lastInsertId();
$current=new FinanceService($a);
$closeInput=['start'=>$oldMonday,'outcome'=>'carry','complete'=>'1','evidence'=>'CLOSE-'.$tag,
 'comment'=>'Awaiting original receipt','owner_'.$sourceId=>(string)$owner,'due_'.$sourceId=>$current->today(),
 'review_token'=>$current->weeklyReview($oldMonday)['token']];
$closeId=$current->execute($owner,'week_decide',$closeInput,bin2hex(random_bytes(16)))['id'];
$closePayload=$r->run('SELECT payload FROM finance_events WHERE id=?',[$closeId])->fetchColumn();
$expenseService=new \Tivoli\Service\ExpenseService($a);
$lateInput=['original_activity_date'=>$oldMonday,'late_reason'=>'Evidence obtained after weekly close','late_reference'=>'COST-'.$tag];
rejectsWeek(fn()=>$expenseService->review($owner,$sourceId,'approved','Synthetic late approval'),RuntimeException::class,'accounting period is locked');
$before=$current->summary($current->today(),$current->today())['expenses'];
$r->db->exec("CREATE TRIGGER weekly_test_audit_fail BEFORE INSERT ON security_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic weekly audit failure'");
try {
 rejectsWeek(fn()=>$expenseService->review($owner,$sourceId,'approved','Synthetic late approval',$lateInput),Throwable::class,'Synthetic weekly audit failure');
} finally {$r->db->exec('DROP TRIGGER weekly_test_audit_fail');}
checkWeek($r->run('SELECT status FROM expense_submissions WHERE id=?',[$sourceId])->fetchColumn()==='pending'
 && $current->summary($current->today(),$current->today())['expenses']===$before,'Late approval audit failure leaves source pending and totals intact');
$result=$expenseService->review($owner,$sourceId,'approved','Synthetic late approval',$lateInput);
$expenseId=$result['expense_id'];
$expense=$r->run('SELECT * FROM expenses WHERE id=?',[$expenseId])->fetch();
checkWeek($expense['expense_date']===$current->today()&&$expense['amount']==='12.35'&&(int)$expense['user_id']===$clerk,'Late approval posts exact cost today and retains submitting clerk');
checkWeek($expenseService->review($owner,$sourceId,'approved','Synthetic late approval',$lateInput)['replayed']
 && $current->summary($current->today(),$current->today())['expenses']===$before+1235,'Late approval retry counts the expense once');
$latePayload=$r->run("SELECT payload FROM finance_events WHERE kind='late_posting' AND target_type='expense' AND target_id=?",[(string)$expenseId])->fetchColumn();
checkWeek(json_decode($latePayload,true,512,JSON_THROW_ON_ERROR)['original_activity_date']===$oldMonday,'Late approval retains original activity date');
$current->execute($owner,'week_followup',['start'=>$oldMonday,'case_id'=>(string)$sourceId,'evidence'=>'APPROVED-'.$tag],bin2hex(random_bytes(16)));
checkWeek($r->run('SELECT payload FROM finance_events WHERE id=?',[$closeId])->fetchColumn()===$closePayload
 && $current->summary($oldMonday,$oldStart->modify('+6 days')->format('Y-m-d'))['expenses']===0,'Approved follow-up preserves closed totals and immutable snapshot');
