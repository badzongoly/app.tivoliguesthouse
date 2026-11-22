<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';
$r=access_service()->repo;if($r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')throw new RuntimeException('Unsafe worker target.');
$payload=json_decode(base64_decode($argv[1]),true,512,JSON_THROW_ON_ERROR);
file_put_contents($payload['ready'],'ready');$deadline=microtime(true)+15;while(!is_file($payload['go'])){if(microtime(true)>$deadline)exit(2);usleep(10000);}
try{$result=(new \Tivoli\Service\StayService(access_service(),$payload['clock']??'2026-11-06 14:00:00'))->execute($payload['actor'],$payload['action'],$payload['input'],$payload['key']);echo json_encode(['ok'=>true,'result'=>$result]);}catch(Throwable $e){echo json_encode(['ok'=>false,'reason'=>'rejected']);}
