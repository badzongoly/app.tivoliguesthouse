<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/security.php';
$r=access_service()->repo;if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$job=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);file_put_contents($job['barrier'].'.ready'.$job['worker'],'ready');$limit=microtime(true)+15;while(!is_file($job['barrier'].'.go')){if(microtime(true)>$limit)exit(3);usleep(10000);}
try{$service=$job['service']==='finance'?new \Tivoli\Service\FinanceService(access_service(),$job['clock']):new \Tivoli\Service\HandoverService(access_service(),$job['clock']);$result=$service->execute($job['actor'],$job['action'],$job['input'],$job['key']);echo json_encode(['ok'=>true,'result'=>$result]);}catch(Throwable $e){echo json_encode(['ok'=>false,'reason'=>'rejected']);}
