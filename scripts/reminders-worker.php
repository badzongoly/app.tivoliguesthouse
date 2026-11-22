<?php
/** Dedicated application worker; never a financial source writer. */
if(PHP_SAPI!=='cli')exit(1);
$mode=$argv[1]??'';if(!in_array($mode,['--test','--local','--production'],true)){fwrite(STDERR,"Use --test for synthetic verification or the explicitly configured local/production worker mode.\n");exit(2);}
// Keep the expression explicit: an environment flag must never accidentally select real data.
putenv('TIVOLI_TEST_MODE='.($mode==='--test'?'1':'0'));
require dirname(__DIR__).'/bootstrap/security.php';
try{
 $c=access_config();
 if($mode==='--local'&&($c['dsn']!=='mysql:host=127.0.0.1;port=3306;dbname=tivoli_fresh_20261110140907;charset=utf8mb4'||!in_array(parse_url($c['base_url'],PHP_URL_HOST),['localhost','127.0.0.1'],true)))throw new RuntimeException('Unapproved local configuration.');
 if($mode==='--production'&&(($c['notifications']['production_worker_enabled']??false)!==true||!is_string($c['notifications']['approved_dsn_hash']??null)||!hash_equals($c['notifications']['approved_dsn_hash'],hash('sha256',$c['dsn']))))throw new RuntimeException('Unapproved production configuration.');
 $a=access_service();$r=$a->repo;$db=$r->run('SELECT DATABASE()')->fetchColumn();$port=(int)$r->run('SELECT @@port')->fetchColumn();
 if($mode==='--test'&&($port!==3307||$db!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1'))throw new RuntimeException('Unverified synthetic target.');
 if($mode==='--local'&&($port!==3306||$db!=='tivoli_fresh_20261110140907'||$c['dsn']!=='mysql:host=127.0.0.1;port=3306;dbname=tivoli_fresh_20261110140907;charset=utf8mb4'||!in_array(parse_url($c['base_url'],PHP_URL_HOST),['localhost','127.0.0.1'],true)))throw new RuntimeException('Unapproved local worker target.');
 if($mode==='--production'&&($c['notifications']['production_worker_enabled']??false)!==true)throw new RuntimeException('Production worker has not been explicitly configured.');
 $s=new \Tivoli\Service\ReminderService($a,null,$c);$owner=$s->sources->owner();if(!$owner)throw new RuntimeException('Configured owner is unavailable.');$result=$s->generate($owner,bin2hex(random_bytes(16)));$sent=$s->dispatch($owner);echo json_encode(['checked'=>true,'created'=>$result['added'],'repeat_pings'=>$result['pings'],'more_pending'=>$result['remaining'],'delivery_attempts'=>$sent['attempted']],JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,"Reminder worker requires review; no financial source command was run.\n");exit(1);}
