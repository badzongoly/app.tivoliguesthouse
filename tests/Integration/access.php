<?php
if (PHP_SAPI!=='cli') { exit(1); }
putenv('TIVOLI_TEST_MODE=1');
require dirname(__DIR__,2).'/bootstrap/security.php';
$s=access_service();$r=$s->repo;
if ($r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test' || $r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1') { throw new RuntimeException('Refusing an unverified test target'); }
$r->run('DELETE FROM security_throttle');
$count=0;
function check(bool $condition,string $label): void { global $count;if(!$condition){throw new RuntimeException('FAIL '.$label);} $count++;echo "PASS $label\n"; }
function denied(callable $action,string $label): void {try{$action();}catch(Throwable $error){check(true,$label);return;}check(false,$label);}
$admin=1;$adminPass='Synthetic Admin Passphrase 123!';$suffix=bin2hex(random_bytes(5));$staffPassword='Synthetic Staff Passphrase 123!';
check($s->login('admin@example.invalid',$adminPass)['id']==1,'valid login and existing session fields');
check($s->login('missing@example.invalid','wrong')===null,'unknown account denied');
check($s->login('admin@example.invalid','incorrect')===null,'incorrect password denied');
$failed=$r->run("SELECT actor_id FROM security_audit WHERE action='auth.login' AND outcome='denied' ORDER BY id DESC LIMIT 1")->fetch();check($failed['actor_id']===null,'failed sign-in does not impersonate the target account');
$input=['name'=>'Synthetic Staff','email'=>'staff-'.$suffix.'@example.invalid','password'=>$staffPassword,'reason'=>'Synthetic account verification'];
$s->manage($admin,$adminPass,'save_user',$input);$staff=(int)$r->run('SELECT id FROM users WHERE email=?',[$input['email']])->fetchColumn();
check($staff>1,'staff created');check((int)$r->user($staff)['must_change_password']===1,'temporary password must change');
check($s->login($input['email'],$staffPassword)['id']==$staff,'new staff login');
denied(fn()=>$s->requirePrivilege($staff,'users.manage'),'clerk cannot manage users');
$rolesBefore=(int)$r->run('SELECT COUNT(*) FROM security_roles')->fetchColumn();
denied(fn()=>$s->manage($staff,$staffPassword,'create_role',['code'=>'forbidden-'.$suffix,'description'=>'Denied','reason'=>'Privilege denial test']),'server-side management permission enforced');
check((int)$r->run('SELECT COUNT(*) FROM security_roles')->fetchColumn()===$rolesBefore,'denial leaves roles unchanged');
denied(fn()=>$s->manage($admin,'wrong','create_role',['code'=>'wrong-'.$suffix,'description'=>'Denied','reason'=>'Reauthentication test']),'management requires current password');
$s->manage($admin,$adminPass,'create_role',['code'=>'auditor-'.$suffix,'description'=>'Synthetic audit reader','reason'=>'Test role creation']);
$role=(int)$r->run('SELECT id FROM security_roles WHERE name=?',['auditor-'.$suffix])->fetchColumn();
$auditPrivilege=(int)$r->run("SELECT id FROM security_privileges WHERE code='audit.view'")->fetchColumn();
$s->manage($admin,$adminPass,'assign_privileges',['id'=>$role,'ids'=>[(string)$auditPrivilege],'reason'=>'Grant audit reader capability']);
$s->manage($admin,$adminPass,'assign_roles',['id'=>$staff,'ids'=>[(string)$role],'reason'=>'Assign synthetic audit reader']);
check(in_array('audit.view',$r->privileges($staff),true),'role privilege inheritance');
check(!in_array('bookings.manage',$r->privileges($staff),true),'removed role grants removed');
check($r->user($staff)['role']==='clerk','custom role preserves legacy stored role');
$s->manage($admin,$adminPass,'create_privilege',['code'=>'custom.test.'.$suffix,'description'=>'Synthetic custom privilege','reason'=>'Test privilege catalogue']);
check(in_array('custom.test.'.$suffix,$r->privileges($admin),true),'custom privilege added and grantable by administrator');
denied(fn()=>$s->manage($admin,$adminPass,'create_privilege',['code'=>'users.superadmin','description'=>'Invalid reserved namespace','reason'=>'Test reserved namespace']),'built-in privilege namespace protected');
$accessOtherAdmins=$r->run("SELECT a.user_id,a.active FROM security_accounts a JOIN security_user_roles ur ON ur.user_id=a.user_id JOIN security_roles r ON r.id=ur.role_id WHERE r.name='admin' AND a.user_id<>?",[$admin])->fetchAll();
try{foreach($accessOtherAdmins as $account)$r->run('UPDATE security_accounts SET active=0 WHERE user_id=?',[$account['user_id']]);
denied(fn()=>$s->manage($admin,$adminPass,'assign_roles',['id'=>$admin,'ids'=>[],'reason'=>'Test last admin protection']),'last active administrator cannot lose role');
check($r->isAdmin($admin),'failed last-admin update rolled back');
}finally{foreach($accessOtherAdmins as $account)$r->run('UPDATE security_accounts SET active=? WHERE user_id=?',[$account['active'],$account['user_id']]);}
denied(fn()=>$s->manage($admin,$adminPass,'set_active',['id'=>$admin,'active'=>'0','reason'=>'Test self deactivation']),'self deactivation denied');
$version=(int)$r->user($staff)['session_version'];$nextPassword='Synthetic Changed Passphrase 456!';
denied(fn()=>$s->changePassword($staff,'wrong',$nextPassword),'password change verifies current password');
denied(fn()=>$s->changePassword($staff,$staffPassword,'short'),'short password rejected');
$s->changePassword($staff,$staffPassword,$nextPassword);
check((int)$r->user($staff)['session_version']>$version,'password change revokes sessions');check((int)$r->user($staff)['must_change_password']===0,'password change clears temporary flag');
check(!password_verify($staffPassword,$r->user($staff)['password_hash']),'old password stops working');
check(password_verify($nextPassword,$r->user($staff)['password_hash']),'new password works');
$reset=$s->requestReset($input['email']);check($reset!==null,'password reset request issued');
$tokenRow=$r->run('SELECT token_hash FROM security_password_resets WHERE user_id=? ORDER BY id DESC LIMIT 1',[$staff])->fetchColumn();
check($tokenRow===hash('sha256',$reset['token']),'reset token stored only as hash');
check($s->resetPassword($reset['token'],'Synthetic Recovered Passphrase 789!'),'valid reset accepted');
check(!$s->resetPassword($reset['token'],'Synthetic Recovered Passphrase 789!'),'reset token cannot be reused');
$expired=$s->requestReset($input['email']);$r->run('UPDATE security_password_resets SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE) WHERE token_hash=?',[hash('sha256',$expired['token'])]);
check(!$s->resetPassword($expired['token'],$nextPassword),'expired reset rejected');
check($s->requestReset('unknown-'.$suffix.'@example.invalid')===null,'unknown account yields no reset token');
$s->manage($admin,$adminPass,'set_active',['id'=>$staff,'active'=>'0','reason'=>'Test deactivation audit']);
check($s->login($input['email'],'Synthetic Recovered Passphrase 789!')===null,'inactive user cannot sign in');
check($s->requestReset($input['email'])===null,'inactive user cannot reset password');
$s->manage($admin,$adminPass,'set_active',['id'=>$staff,'active'=>'1','reason'=>'Test reactivation audit']);
$version=(int)$r->user($staff)['session_version'];$s->manage($admin,$adminPass,'revoke_sessions',['id'=>$staff,'reason'=>'Test session revocation']);check((int)$r->user($staff)['session_version']===$version+1,'explicit session revocation');
$throttled=true;for($i=0;$i<9;$i++){$result=$s->login('limited-'.$suffix.'@example.invalid','incorrect');$throttled=$throttled && $result===null;}
$last=$r->run("SELECT details FROM security_audit WHERE action='auth.login' ORDER BY id DESC LIMIT 1")->fetchColumn();check($throttled && str_contains($last,'throttled'),'persistent account login throttling');
$auditId=(int)$r->run('SELECT MIN(id) FROM security_audit')->fetchColumn();
denied(fn()=>$r->run('UPDATE security_audit SET outcome=? WHERE id=?',['changed',$auditId]),'audit updates blocked by database');
denied(fn()=>$r->run('DELETE FROM security_audit WHERE id=?',[$auditId]),'audit deletion blocked by database');
$r->db->exec("CREATE TRIGGER security_test_audit_failure BEFORE INSERT ON security_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'");
try { denied(fn()=>$s->manage($admin,$adminPass,'create_role',['code'=>'rollback-'.$suffix,'description'=>'Must roll back','reason'=>'Audit failure injection']),'required audit failure aborts operation'); }
finally{$r->db->exec('DROP TRIGGER security_test_audit_failure');}
check((int)$r->run('SELECT COUNT(*) FROM security_roles WHERE name=?',['rollback-'.$suffix])->fetchColumn()===0,'audit failure rolls back role creation');
$details=implode('\n',$r->run('SELECT details FROM security_audit')->fetchAll(PDO::FETCH_COLUMN));
foreach([$adminPass,$staffPassword,$nextPassword,$reset['token'],$expired['token']] as $secret){check(!str_contains($details,$secret),'audit excludes secret material');}
check(str_contains($details,'actor_roles'),'audit captures role snapshot');
check(str_contains($details,'before') && str_contains($details,'after'),'access changes retain before and after snapshots');
echo "TOTAL $count assertions passed on separate synthetic database.\n";