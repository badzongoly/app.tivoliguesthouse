<?php
if(PHP_SAPI!=='cli'){exit(1);}putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/security.php';
$s=access_service();$r=$s->repo;if($r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1'){throw new RuntimeException('Unsafe target');}
$r->run('DELETE FROM security_throttle');$suffix=bin2hex(random_bytes(5));$adminPass='Synthetic Admin Passphrase 123!';$managerPass='Synthetic Delegated Manager Passphrase 123!';$count=0;
function verify(bool $ok,string $label): void{global $count;if(!$ok){throw new RuntimeException('FAIL '.$label);}echo "PASS $label\n";$count++;}
function blocked(callable $action,string $label): void{try{$action();}catch(Throwable $error){verify(true,$label);return;}verify(false,$label);}
$email='delegated-'.$suffix.'@example.invalid';$s->manage(1,$adminPass,'save_user',['name'=>'Synthetic delegated manager','email'=>$email,'password'=>$managerPass,'reason'=>'Regression fixture setup']);$manager=(int)$r->run('SELECT id FROM users WHERE email=?',[$email])->fetchColumn();
$s->manage(1,$adminPass,'create_role',['code'=>'delegated-'.$suffix,'description'=>'Synthetic delegated account manager','reason'=>'Regression role setup']);$role=(int)$r->run('SELECT id FROM security_roles WHERE name=?',['delegated-'.$suffix])->fetchColumn();
$privileges=$r->run("SELECT id FROM security_privileges WHERE code IN ('users.manage','roles.assign')")->fetchAll(PDO::FETCH_COLUMN);
$s->manage(1,$adminPass,'assign_privileges',['id'=>$role,'ids'=>$privileges,'reason'=>'Regression permission setup']);$s->manage(1,$adminPass,'assign_roles',['id'=>$manager,'ids'=>[(string)$role],'reason'=>'Regression assignment setup']);
$original=$r->user(1)['email'];
blocked(fn()=>$s->manage($manager,$managerPass,'save_user',['id'=>1,'name'=>'Administrator','email'=>'takeover@example.invalid','reason'=>'Attempt administrator takeover']),'delegated manager cannot redirect administrator email');
verify($r->user(1)['email']===$original,'administrator email preserved');
blocked(fn()=>$s->manage($manager,$managerPass,'set_active',['id'=>1,'active'=>'0','reason'=>'Attempt administrator deactivation']),'delegated manager cannot deactivate administrator');
blocked(fn()=>$s->manage($manager,$managerPass,'revoke_sessions',['id'=>1,'reason'=>'Attempt administrator revocation']),'delegated manager cannot revoke administrator sessions');
blocked(fn()=>$s->manage($manager,$managerPass,'assign_roles',['id'=>1,'ids'=>[],'reason'=>'Attempt administrator demotion']),'delegated manager cannot demote administrator');
$token=$s->requestReset($email);
$s->manage(1,$adminPass,'save_user',['id'=>$manager,'name'=>'Synthetic delegated manager','email'=>'changed-'.$suffix.'@example.invalid','reason'=>'Verify reset invalidation on email change']);
verify(!$s->resetPassword($token['token'],'Synthetic New Passphrase 456!'),'email change invalidates outstanding recovery token');
for($i=0;$i<8;$i++){blocked(fn()=>$s->manage($manager,'wrong','save_user',['name'=>'Denied','email'=>'denied@example.invalid','reason'=>'Wrong password attempt']),'invalid reauthentication denied');}
blocked(fn()=>$s->manage($manager,$managerPass,'save_user',['id'=>$manager,'name'=>'Denied','email'=>'changed-'.$suffix.'@example.invalid','reason'=>'Throttle persistence check']),'correct password remains throttled after repeated failures');
$last=$r->run("SELECT details FROM security_audit WHERE action='auth.reauthentication' AND actor_id=? ORDER BY id DESC LIMIT 1",[$manager])->fetchColumn();verify(str_contains($last,'throttled'),'failed reauthentication counters survive rollback');
$envelope=\Tivoli\Infrastructure\ResetEnvelope::encode(['email'=>'synthetic@example.invalid','token'=>'synthetic-token'],access_config()['audit_key']);
verify(!str_contains($envelope,'synthetic-token'),'queued reset payload encrypted');
verify(\Tivoli\Infrastructure\ResetEnvelope::decode($envelope,access_config()['audit_key'])['token']==='synthetic-token','queued reset payload decrypts');
blocked(fn()=>\Tivoli\Infrastructure\ResetEnvelope::decode(substr($envelope,0,-4).'AAAA',access_config()['audit_key']),'tampered recovery envelope rejected');
echo "TOTAL $count security regression assertions passed.\n";