<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';$r=access_service()->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$j=json_decode(trim(fgets(STDIN)),true,512,JSON_THROW_ON_ERROR);putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$j['owner']);echo "READY\n";flush();if(trim(fgets(STDIN))!=='GO')exit(3);
try{$s=new \Tivoli\Service\WeeklyAuditService(access_service(),$j['clock']);$result=$s->execute($j['actor'],$j['action'],$j['input'],$j['key']);echo json_encode(['ok'=>true,'result'=>$result]);}catch(Throwable $e){echo json_encode(['ok'=>false,'reason'=>$e instanceof PDOException?'database failure':substr($e->getMessage(),0,160)]);}
