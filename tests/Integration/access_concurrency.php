<?php
if(PHP_SAPI!=='cli'){exit(1);}putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/security.php';
$s=access_service();$r=$s->repo;
if($r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1'){throw new RuntimeException('Unsafe test target');}
function race(array $first,array $second): array {
 $barrier=dirname(__DIR__,2).'/.local-setup/security-barrier-'.bin2hex(random_bytes(8));$jobs=[];
 foreach([$first,$second] as $index=>$data){$process=proc_open([PHP_BINARY,__DIR__.'/security_worker.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process)){throw new RuntimeException('Worker launch failed');}$data['barrier']=$barrier;$data['worker']=$index;fwrite($pipes[0],json_encode($data));fclose($pipes[0]);$jobs[]=[$process,$pipes];}
 $deadline=microtime(true)+10;while(!file_exists($barrier.'.ready0') || !file_exists($barrier.'.ready1')){if(microtime(true)>$deadline){throw new RuntimeException('Workers did not reach the deterministic barrier');}usleep(10000);}
 file_put_contents($barrier.'.go','go');$outcomes=[];
 foreach($jobs as [$process,$pipes]){$outcomes[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0 || $error!==''){throw new RuntimeException('Concurrency worker failed');}}
 foreach(['.ready0','.ready1','.go'] as $suffix){unlink($barrier.$suffix);}return $outcomes;
}
$suffix=bin2hex(random_bytes(5));$password='Synthetic Concurrent Admin Passphrase 123!';$adminPass='Synthetic Admin Passphrase 123!';$ids=[];
for($i=0;$i<2;$i++){$email='race-'.$suffix.'-'.$i.'@example.invalid';$s->manage(1,$adminPass,'save_user',['name'=>'Synthetic concurrent admin','email'=>$email,'password'=>$password,'reason'=>'Concurrency fixture setup']);$id=(int)$r->run('SELECT id FROM users WHERE email=?',[$email])->fetchColumn();$ids[]=$id;}
$email=$r->user($ids[0])['email'];$reset=$s->requestReset($email);$outcomes=race(['operation'=>'reset','token'=>$reset['token']],['operation'=>'reset','token'=>$reset['token']]);
if(count(array_filter($outcomes,fn($value)=>$value==='ok'))!==1){throw new RuntimeException('Concurrent reset was not single-use');}echo "PASS simultaneous reset-token consumption commits exactly once\n";
// Restore only synthetic fixture credentials before exercising competing administrators.
$r->run('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_BCRYPT,['cost'=>12]),$ids[0]]);
$role=(int)$r->run("SELECT id FROM security_roles WHERE name='admin'")->fetchColumn();
foreach($ids as $id){$s->manage(1,$adminPass,'assign_roles',['id'=>$id,'ids'=>[(string)$role],'reason'=>'Concurrent administrator setup']);}
$r->run('UPDATE security_accounts SET active=0 WHERE user_id=1');
try {
 $outcomes=race(['operation'=>'deactivate','actor'=>$ids[0],'target'=>$ids[1]],['operation'=>'deactivate','actor'=>$ids[1],'target'=>$ids[0]]);
 $active=(int)$r->run("SELECT COUNT(*) FROM security_accounts a JOIN security_user_roles ur ON ur.user_id=a.user_id JOIN security_roles r ON r.id=ur.role_id WHERE a.active=1 AND r.name='admin'")->fetchColumn();
 if(count(array_filter($outcomes,fn($value)=>$value==='ok'))!==1 || $active!==1){throw new RuntimeException('Concurrent administrators did not preserve a single active administrator');}
 echo "PASS competing administrator deactivations preserve an active administrator\n";
} finally {$r->run('UPDATE security_accounts SET active=1 WHERE user_id=1');foreach($ids as $id){$r->run('UPDATE security_accounts SET active=0 WHERE user_id=?',[$id]);}}
echo "TOTAL 2 concurrency scenarios passed using independent processes and deterministic barriers.\n";