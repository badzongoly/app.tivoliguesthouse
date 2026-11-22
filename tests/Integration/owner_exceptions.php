<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require_once dirname(__DIR__,2).'/bootstrap/security.php';
use Tivoli\Service\OwnerExceptionService;
$guard=access_service()->repo;if((int)$guard->run('SELECT @@port')->fetchColumn()!==3307||$guard->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$guard->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
if(!class_exists(OwnerExceptionService::class))throw new RuntimeException('Approved Owner Exception Register is missing');
require __DIR__.'/weekly_audits.php';
$oe=new OwnerExceptionService($a,$clock);
wd(fn()=>$oe->read($staff),'Clerk cannot open owner register');wd(fn()=>$oe->read($admin),'Administrator role alone cannot open owner register');
$made=$legacy->execute($staff,'room_audit',['room_id'=>(string)$room,'result'=>'corrective','reason'=>'Synthetic owner queue action'],wk());$tid=$made['followup_id'];
function oe_row(string $key):array{global $oe,$owner;return $oe->read($owner,['key'=>$key])['selected'];}
$first=oe_row('weekly:'.$tid);wa(!$first['resolved']&&$first['decision']&&$first['responsible_id']===null,'Original unassigned audit appears without invented staff identity');
wc($owner,'assign',wv($owner,$tid)+['assignee'=>(string)$staff,'due_date'=>'2036-11-18']);$view=oe_row('weekly:'.$tid);wa($view['responsible_id']===$staff&&$view['due']==='2036-11-18'&&!$view['resolved'],'Source assignment and deadline flow into same queue key');
wc($staff,'done',wv($staff,$tid));$view=oe_row('weekly:'.$tid);wa(!$view['resolved']&&$view['decision'],'Staff completion stays open for owner verification');
wc($owner,'close',wv($owner,$tid)+['verified'=>'1']);$view=oe_row('weekly:'.$tid);wa($view['resolved']&&$view['key']===$first['key'],'Owner source verification resolves original queue identity');
$before=$r->run('SELECT COUNT(*) FROM finance_events')->fetchColumn();$ops=$r->run('SELECT COUNT(*) FROM finance_operations')->fetchColumn();$sourceRows=$r->run('SELECT COUNT(*) FROM stay_room_audits')->fetchColumn();$financial=hash('sha256',json_encode([$r->run('SELECT * FROM payments ORDER BY id')->fetchAll(),$r->run('SELECT * FROM expenses ORDER BY id')->fetchAll(),$r->run('SELECT * FROM stock_movements ORDER BY id')->fetchAll()]));
$oe->read($owner);$oe->read($owner,['view'=>'resolved']);$oe->read($owner,['view'=>'activity']);
wa($r->run('SELECT COUNT(*) FROM finance_events')->fetchColumn()===$before&&$r->run('SELECT COUNT(*) FROM finance_operations')->fetchColumn()===$ops&&$r->run('SELECT COUNT(*) FROM stay_room_audits')->fetchColumn()===$sourceRows,'Repeated queue reads create no business events operations or source records');
wa(hash('sha256',json_encode([$r->run('SELECT * FROM payments ORDER BY id')->fetchAll(),$r->run('SELECT * FROM expenses ORDER BY id')->fetchAll(),$r->run('SELECT * FROM stock_movements ORDER BY id')->fetchAll()]))===$financial,'Owner register preserves exact original financial and stock rows');
wd(fn()=>$oe->read($owner,['key'=>'weekly:0<script>']),'Malformed source identity rejected');wd(fn()=>$oe->read($owner,['page'=>['1']]),'Nested filter values rejected');
echo "Owner exceptions: $n assertions passed\n";
