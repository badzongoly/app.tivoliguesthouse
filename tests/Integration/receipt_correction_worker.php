<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';$a=access_service();$r=$a->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$j=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);file_put_contents($j['barrier'].'.ready'.$j['worker'],'ready');$until=microtime(true)+15;while(!is_file($j['barrier'].'.go')){if(microtime(true)>$until)exit(3);usleep(10000);}
try{
 if(($j['service']??'')==='lock'){
  $result=$a->transaction(function()use($r,$j,$a){$r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES('period_locked','period','month:2036-04-01',0,'2036-04-15',?,'{}',?)",[$j['actor'],$j['clock']]);$id=(int)$r->db->lastInsertId();$a->audit($j['actor'],'synthetic_rc.lock','period','month:2036-04-01','recorded');return ['id'=>$id];});
 }else{$class=match($j['service']){'correction'=>\Tivoli\Service\ReceiptCorrectionService::class,'stay'=>\Tivoli\Service\StayService::class,'finance'=>\Tivoli\Service\FinanceService::class};$s=new $class($a,$j['clock']);$result=$s->execute($j['actor'],$j['action'],$j['input'],$j['key']);}
 echo json_encode(['ok'=>true,'result'=>$result]);
}catch(Throwable $e){echo json_encode(['ok'=>false,'reason'=>substr($e->getMessage(),0,220)]);}
