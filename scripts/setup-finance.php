<?php
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--confirmed-local')exit("Verify the isolated local target; use --confirmed-local.\n");
require dirname(__DIR__).'/bootstrap/security.php';
$test=getenv('TIVOLI_TEST_MODE')==='1';$c=access_config();
if($c['dsn']!=='mysql:host=127.0.0.1;port='.($test?'3307;dbname=tivoli_auth_test':'3306;dbname=tivoli').';charset=utf8mb4')throw new RuntimeException('Unapproved finance target.');
$r=access_service()->repo;
if($test&&$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')throw new RuntimeException('Unverified test target.');
if(!$r->run('SELECT version FROM stay_schema WHERE version=2')->fetchColumn())throw new RuntimeException('Install occupancy first.');
foreach(['payments','expenses','security_audit','stay_receipts','stay_departures'] as $table)if($r->run('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$table])->fetchColumn()!=='InnoDB')throw new RuntimeException('Required transactional table missing.');
foreach(require dirname(__DIR__).'/database/migrations/009_finance.php' as $sql)$r->db->exec($sql);
foreach(['finance_schema','finance_events','finance_operations','finance_claims'] as $table)foreach(['UPDATE','DELETE'] as $op){$name='keep_'.$table.'_'.strtolower($op);if(!$r->run('SELECT 1 FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name=?',[$name])->fetchColumn())$r->db->exec("CREATE TRIGGER `$name` BEFORE $op ON `$table` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Finance evidence is immutable; append a linked correction'");}
access_service()->transaction(function()use($r){if(!$r->run('SELECT id FROM finance_schema WHERE id=1')->fetchColumn()){$r->run('INSERT INTO finance_schema VALUES(1,UTC_TIMESTAMP(),(SELECT COALESCE(MAX(id),0) FROM payments),(SELECT COALESCE(MAX(id),0) FROM expenses))');access_service()->audit(null,'finance.activated','finance',null,'succeeded',['policy'=>'full_cash_to_momo_next_calendar_day','historical_receipts_not_assumed_held'=>true]);}});
echo "Finance schema installed. Existing records preserved; no historical cash custody inferred.\n";
