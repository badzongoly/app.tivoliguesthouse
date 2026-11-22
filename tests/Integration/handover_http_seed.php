<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';
$r=access_service()->repo;if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$actor=(int)$argv[1];(new \Tivoli\Service\FinanceService(access_service()))->execute($actor,'income_record',['payer'=>'Synthetic handover HTTP cash','activity'=>'Synthetic courtyard','amount'=>'100.00','method'=>'cash','treatment'=>'earned','provided'=>'1'],bin2hex(random_bytes(16)));echo 'Synthetic source created';
