<?php
if (PHP_SAPI!=='cli') exit(1);
putenv('TIVOLI_TEST_MODE=1');
require dirname(__DIR__,2).'/bootstrap/security.php';

use Tivoli\Service\FinanceService;

$a=access_service();
$r=$a->repo;
if ($r->run('SELECT @@port')->fetchColumn()!=3307
    || $r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'
    || $r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1') exit(2);
$s=new FinanceService($a);
if (!$s->installed()) throw new RuntimeException('Finance fixture is not installed.');

$day='2099-02-09';
$week=(new DateTimeImmutable($day))->modify('monday this week')->format('Y-m-d');
$before=$r->run('SELECT COUNT(*) FROM finance_events')->fetchColumn();
$r->db->beginTransaction();
try {
    $r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at)
        VALUES('period_locked','period',?,0,?,1,'{}',?)",['week:'.$week,$day,$day.' 12:00:00']);
    if (!$s->periodLocked($day) || $s->dayClosed($day)) throw new RuntimeException('Invalid locked-week fixture.');
    try {
        FinanceService::guardSource($a,$day);
    } catch (RuntimeException $failure) {
        if (!str_contains($failure->getMessage(),'locked')) throw $failure;
        $blocked=true;
    }
    if (empty($blocked)) throw new RuntimeException('FAIL: ordinary receipts/refunds are still allowed on an open day in a locked week.');
    FinanceService::guardSource($a,'2099-02-16');
} finally {
    $r->db->rollBack();
}
if ($r->run('SELECT COUNT(*) FROM finance_events')->fetchColumn()!==$before) throw new RuntimeException('Fixture persisted.');
echo "PASS locked week blocks ordinary posting, adjacent open week allows posting, test lock rolled back.\n";
