<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';require_once dirname(__DIR__,2).'/bootstrap/hr.php';$a=access_service();$r=$a->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$j=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);file_put_contents($j['barrier'].'.ready'.$j['worker'],'ready');$until=microtime(true)+15;while(!is_file($j['barrier'].'.go')){if(microtime(true)>$until)exit(3);usleep(10000);}
try{
 if(($j['service']??'')==='relink'){$a->transaction(fn()=>$r->run('UPDATE hr_staff SET user_id=? WHERE id=?',[$j['input']['user_id'],$j['input']['staff_id']]));$result=[];}
 elseif(($j['service']??'')==='salary'){$result=$a->transaction(function()use($j,$r){$v=new \Tivoli\Infrastructure\HrVault(hr_config()['hr_key']);$old=$r->run('SELECT * FROM hr_salary_versions WHERE staff_id=? ORDER BY id DESC LIMIT 1',[$j['input']['staff_id']])->fetch();$data=json_decode($v->open($old['payload'],'salary:'.$old['staff_id']),true);$data['amount']='1100.00';$r->run('INSERT INTO hr_salary_versions(staff_id,preferred,momo_id,effective_date,payload,reason,created_by) VALUES(?,?,?,?,?,?,?)',[$old['staff_id'],$old['preferred'],$old['momo_id'],$old['effective_date'],$v->seal(json_encode($data),'salary:'.$old['staff_id']),'Synthetic race terms',$j['actor']]);return [];});}
 else{$s=($j['service']??'')==='finance'?new \Tivoli\Service\FinanceService($a,$j['clock']):new \Tivoli\Service\PayrollService($a,$j['clock']);$result=$s->execute($j['actor'],$j['action'],$j['input'],$j['key']);}
 echo json_encode(['ok'=>true,'result'=>$result]);
}catch(Throwable $e){echo json_encode(['ok'=>false,'reason'=>substr($e->getMessage(),0,160)]);}
