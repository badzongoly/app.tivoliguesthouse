<?php
if(PHP_SAPI!=='cli' || !in_array($argv[1] ?? '',['--test','--confirmed-local'],true)){exit("Use --test for synthetic fixtures or --confirmed-local for local development.\n");}
putenv('TIVOLI_TEST_MODE='.(($argv[1]==='--test')?'1':'0'));require dirname(__DIR__).'/bootstrap/security.php';
$s=access_service();$r=$s->repo;$config=access_config();
if($argv[1]==='--test' && $r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1'){throw new RuntimeException('Unsafe test target.');}
if($argv[1]==='--confirmed-local' && $config['dsn']!=='mysql:host=127.0.0.1;port=3306;dbname=tivoli;charset=utf8mb4'){throw new RuntimeException('Unapproved local target.');}
$s->transaction(function() use($s,$r){
 foreach($r->run("SELECT id FROM security_mail_queue WHERE status='processing' AND locked_until<UTC_TIMESTAMP() AND attempts>=5 FOR UPDATE")->fetchAll() as $expired){
  $r->run("UPDATE security_mail_queue SET status='failed',payload='',claim_token=NULL,locked_until=NULL WHERE id=?",[$expired['id']]);
  $s->audit(null,'auth.reset_delivery','mail_queue',(string)$expired['id'],'failed',['reason'=>'final_worker_lease_expired']);
 }
});
$processed=0;
while($processed<50){
 $job=$s->transaction(function() use($r){
  $row=$r->run("SELECT id,payload,attempts FROM security_mail_queue WHERE ((status='pending' AND available_at<=UTC_TIMESTAMP()) OR (status='processing' AND locked_until<UTC_TIMESTAMP())) AND attempts<5 ORDER BY id LIMIT 1 FOR UPDATE")->fetch();if(!$row){return null;}
  $row['claim_token']=bin2hex(random_bytes(16));
  $r->run("UPDATE security_mail_queue SET status='processing',attempts=attempts+1,claim_token=?,locked_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 MINUTE) WHERE id=?",[$row['claim_token'],$row['id']]);return $row;
 });
 if(!$job){break;}$processed++;
 try {
  $delivery=\Tivoli\Infrastructure\ResetEnvelope::decode($job['payload'],$config['audit_key']);$eligible=null;
  if(is_string($delivery['email'] ?? null) && is_string($delivery['token'] ?? null)){
   $eligible=$r->run('SELECT r.user_id FROM security_password_resets r JOIN users u ON u.id=r.user_id JOIN security_accounts a ON a.user_id=u.id WHERE r.token_hash=? AND u.email=? AND a.active=1 AND r.consumed_at IS NULL AND r.expires_at>UTC_TIMESTAMP()',[hash('sha256',$delivery['token']),$delivery['email']])->fetchColumn();
  }
  if($eligible){access_deliver_reset($delivery);}
  $s->transaction(function() use($s,$r,$job,$eligible,$config){
   $changed=$r->run("UPDATE security_mail_queue SET status='done',payload='',locked_until=NULL,claim_token=NULL WHERE id=? AND claim_token=? AND status='processing' AND locked_until>=UTC_TIMESTAMP()",[$job['id'],$job['claim_token']])->rowCount();
   if($changed){$s->audit(null,'auth.reset_delivery','user',$eligible?(string)$eligible:null,$eligible?'succeeded':'skipped',['job_id'=>(int)$job['id'],'transport'=>$config['reset_delivery'] ?? 'unconfigured']);}
   else{$s->audit(null,'auth.reset_delivery_uncertain','mail_queue',(string)$job['id'],'recorded',['reason'=>'worker_lease_lost']);}
  });
 }catch(Throwable $error){
  $s->transaction(function() use($s,$r,$job){
   $changed=$r->run("UPDATE security_mail_queue SET status=?,available_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE),locked_until=NULL,claim_token=NULL WHERE id=? AND claim_token=? AND status='processing'",[((int)$job['attempts']+1>=5)?'failed':'pending',$job['id'],$job['claim_token']])->rowCount();
   if($changed){$s->audit(null,'auth.reset_delivery','mail_queue',(string)$job['id'],'failed',['reason'=>'transport_or_envelope_failure','attempt'=>(int)$job['attempts']+1]);}
   else{$s->audit(null,'auth.reset_delivery_uncertain','mail_queue',(string)$job['id'],'recorded',['reason'=>'worker_lease_lost']);}
  });
 }
}
echo 'Recovery queue jobs processed: '.$processed."\n";