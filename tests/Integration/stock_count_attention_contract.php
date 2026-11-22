<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';$a=access_service();$r=$a->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$owner=(int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn();putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$owner);$s=new \Tivoli\Service\StockCountService($a);$r->db->exec('START TRANSACTION READ ONLY');
try{
 $actors=[[$owner,false],[$owner,true]];
 foreach($r->run("SELECT DISTINCT actor_id FROM finance_events WHERE target_type='stock_count' AND kind='count_started' ORDER BY actor_id DESC LIMIT 20")->fetchAll(PDO::FETCH_COLUMN) as $actor){if((int)$actor===$owner)continue;try{$s->permit((int)$actor);$actors[]=[(int)$actor,true];break;}catch(\Tivoli\Security\PermissionDenied $e){}}
 foreach($actors as [$actor,$own]){
  $expected=[];$ids=$r->run("SELECT id FROM finance_events WHERE target_type='stock_count' AND kind='count_started'".($own?' AND actor_id=?':'').' ORDER BY id DESC',$own?[$actor]:[])->fetchAll(PDO::FETCH_COLUMN);
  foreach($ids as $id){$c=$s->detail($actor,(int)$id);if(in_array($c['status'],['submitted','disputed','recount'],true))$expected[]=$c;}
  $actual=$s->attention($actor,$own);if($actual['rows']!==$expected||$actual['count']!==count($expected))throw new RuntimeException('Stock attention differs from original full-history reader.');echo 'PASS stock attention preserves original complete ordered rows for '.($own?'own':'owner').' scope',"\n";
 }
}finally{$r->db->rollBack();}
