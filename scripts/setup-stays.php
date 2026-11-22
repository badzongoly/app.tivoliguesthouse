<?php
if (PHP_SAPI!=='cli' || ($argv[1] ?? '')!=='--confirmed-local') { exit("Verify the isolated local target, then use --confirmed-local.\n"); }
require dirname(__DIR__).'/bootstrap/security.php';
$test=getenv('TIVOLI_TEST_MODE')==='1';$config=access_config();
$expected='mysql:host=127.0.0.1;port='.($test?'3307;dbname=tivoli_auth_test':'3306;dbname=tivoli').';charset=utf8mb4';
if ($config['dsn']!==$expected) { throw new RuntimeException('Unapproved occupancy setup target.'); }
$r=access_service()->repo;
if ($test && $r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1') { throw new RuntimeException('Unverified synthetic database.'); }
foreach (['rooms','bookings','payments','extras','booking_extras','stock_movements'] as $table) {
    if ($r->run('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$table])->fetchColumn()!=='InnoDB') { throw new RuntimeException('Expected existing InnoDB table: '.$table); }
}
require dirname(__DIR__).'/database/install-stays.php';
stay_install_schema($r);
echo "Occupancy schema installed on the verified local target. No historical backfill, rate guessing or financial row replacement.\n";
