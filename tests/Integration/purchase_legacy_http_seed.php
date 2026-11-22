<?php
// Synthetic pre-existing records for compatibility checks after public creation moved to purchasing.
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/security.php';
$a=access_service();$r=$a->repo;if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$j=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$actor=(int)($j['actor']??0);if(!$actor)$actor=(int)$r->run('SELECT id FROM users WHERE email=?',[$j['email']??''])->fetchColumn();$in=$j['input'];if(isset($j['proof']))$in['_evidence']=\Tivoli\Service\FinanceAttachment::validate(base64_decode($j['proof'],true),'proof.pdf');
$result=$j['kind']==='provider'?(new \Tivoli\Service\FinanceService($a))->execute($actor,'provider_request',$in,bin2hex(random_bytes(16))):(new \Tivoli\Service\ExpenseService($a))->submit($actor,$in,bin2hex(random_bytes(16)));echo json_encode($result,JSON_THROW_ON_ERROR);
