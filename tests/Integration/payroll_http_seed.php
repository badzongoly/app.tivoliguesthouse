<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';require_once dirname(__DIR__,2).'/bootstrap/hr.php';
$r=access_service()->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$owner=(int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn();$tag=bin2hex(random_bytes(5));$users=[];
foreach(['employee'=>'clerk','other'=>'clerk','admin'=>'admin'] as $k=>$role){
 $email='payroll-'.$k.'-'.$tag.'@example.invalid';$r->run('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',['Synthetic Payroll '.$k,$email,password_hash('Synthetic Payroll Passphrase 123!',PASSWORD_BCRYPT),$role]);$id=(int)$r->db->lastInsertId();
 $r->run('INSERT INTO security_accounts(user_id,must_change_password) VALUES(?,0)',[$id]);$r->run('INSERT INTO security_user_roles SELECT ?,id FROM security_roles WHERE name=?',[$id,$role]);$users[$k]=compact('id','email');
}
$provider=(int)$r->run("SELECT id FROM hr_catalogue WHERE group_name='momo' AND active=1 LIMIT 1")->fetchColumn();
if(!$provider){$r->run("INSERT INTO hr_catalogue(group_name,name,active) VALUES('momo','Synthetic Payroll MoMo',1)");$provider=(int)$r->db->lastInsertId();}
$r->run("INSERT INTO hr_staff(staff_no,user_id,status,data,created_by) VALUES(?,?,'active',?,?)",['PH-'.$tag,$users['employee']['id'],json_encode(['name'=>'Synthetic Payroll Employee '.$tag,'start'=>gmdate('Y-m-01')]),$owner]);$staff=(int)$r->db->lastInsertId();
$v=new \Tivoli\Infrastructure\HrVault(hr_config()['hr_key']);$salary=['amount'=>'1000.00','frequency'=>'monthly','preferred'=>'momo','momo_id'=>$provider,'momo_name'=>'Synthetic Payroll Recipient','momo_number'=>'0000000174'];
$r->run("INSERT INTO hr_salary_versions(staff_id,preferred,momo_id,effective_date,payload,reason,created_by) VALUES(?,'momo',?,?,?,'Synthetic HTTP fixture',?)",[$staff,$provider,gmdate('Y-m-01'),$v->seal(json_encode($salary),'salary:'.$staff),$owner]);
file_put_contents(dirname(__DIR__,2).'/.local-setup/payroll-http-fixture.json',json_encode(compact('users','staff','tag')));
