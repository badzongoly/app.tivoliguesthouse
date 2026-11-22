<?php
if(PHP_SAPI!=='cli'){exit(1);}putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/security.php';
$s=access_service();if($s->repo->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1'){exit(2);}
$data=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$prefix=dirname(__DIR__,2).'/.local-setup/security-barrier-';
if(!str_starts_with($data['barrier'],$prefix)){exit(3);}
file_put_contents($data['barrier'].'.ready'.$data['worker'],'ready');
$deadline=microtime(true)+10;while(!file_exists($data['barrier'].'.go')){if(microtime(true)>$deadline){exit(4);}usleep(10000);}
try {
 if($data['operation']==='reset'){$ok=$s->resetPassword($data['token'],'Synthetic Concurrent Reset Passphrase 456!');}
 else{$s->manage((int)$data['actor'],'Synthetic Concurrent Admin Passphrase 123!','set_active',['id'=>(int)$data['target'],'active'=>'0','reason'=>'Concurrent last-administrator verification']);$ok=true;}
 echo $ok?'ok':'denied';
}catch(Throwable $error){echo 'denied';}