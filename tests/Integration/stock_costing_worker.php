<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';
$job=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$a=access_service();$r=$a->repo;if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);putenv('TIVOLI_FINANCE_OWNER_USER_ID='.(int)$job['owner']);
file_put_contents($job['barrier'].'.ready'.$job['worker'],'ready');$until=microtime(true)+15;while(!is_file($job['barrier'].'.go')){if(microtime(true)>$until)exit(3);usleep(10000);}
$class=match($job['service']){'cost'=>\Tivoli\Service\InventoryCostingService::class,'issue'=>\Tivoli\Service\StockIssueService::class,'stay'=>\Tivoli\Service\StayService::class};
try{$result=(new $class($a,$job['clock']))->execute($job['actor'],$job['action'],$job['input'],$job['key']);echo json_encode(['ok'=>true,'result'=>$result],JSON_THROW_ON_ERROR);}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>get_class($e)],JSON_THROW_ON_ERROR);}
