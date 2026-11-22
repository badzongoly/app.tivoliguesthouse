<?php
if(PHP_SAPI!=='cli'||($argv[1] ?? '')!=='--confirmed-local')exit("Use --confirmed-local after verifying the local target.\n");
require dirname(__DIR__).'/bootstrap/security.php';$root=dirname(__DIR__);$testing=getenv('TIVOLI_TEST_MODE')==='1';$config=access_config();
$expected=$testing?'mysql:host=127.0.0.1;port=3307;dbname=tivoli_auth_test;charset=utf8mb4':'mysql:host=127.0.0.1;port=3306;dbname=tivoli;charset=utf8mb4';
if($config['dsn']!==$expected)throw new RuntimeException('Unapproved HR setup target.');
$a=access_service();$r=$a->repo;
if($testing){if($r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')throw new RuntimeException('Unverified synthetic database.');$actual=strtolower(rtrim(str_replace('\\','/',$r->run('SELECT @@datadir')->fetchColumn()),'/'));if($actual!==strtolower(str_replace('\\','/',$root.'/.local-setup/auth-test-mysql')))throw new RuntimeException('Unexpected synthetic engine directory.');}
$keyPath=$root.'/config/'.($testing?'hr-test.local.php':'hr.local.php');
if(!is_file($keyPath)){
    foreach(['hr_salary_versions','hr_documents'] as $table){
        if($r->run('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$table])->fetchColumn()&&(int)$r->run('SELECT COUNT(*) FROM '.$table)->fetchColumn()>0)throw new RuntimeException('Private HR data exists. Restore its original encryption key; setup will not replace it.');
    }
}
if(!is_file($keyPath)){if(file_put_contents($keyPath,'<?php return '.var_export(['hr_key'=>bin2hex(random_bytes(32))],true).';',LOCK_EX)===false)throw new RuntimeException('Could not save private HR encryption key.');}
$keyConfig=require $keyPath;if(!preg_match('/^[a-f0-9]{64}$/D',$keyConfig['hr_key'] ?? ''))throw new RuntimeException('Restore a valid original HR encryption key before setup.');
foreach(require $root.'/database/migrations/006_staff_hr.php' as $sql)$r->db->exec($sql);
if($r->run('SELECT version FROM hr_schema WHERE version=1')->fetchColumn())exit("HR schema already installed; assignments and keys preserved.\n");
$a->transaction(function()use($a,$r){
 foreach(['hr.view'=>'View all staff profiles','hr.self'=>'View own linked staff record and request leave','hr.manage'=>'Manage staff profiles and employment','hr.approve'=>'Approve onboarding and leave','hr.link'=>'Link staff and system accounts','hr.documents'=>'Manage private HR documents','hr.salary'=>'Manage encrypted salary and payment details','hr.settings'=>'Manage HR settings'] as $p=>$description)$r->run('INSERT IGNORE INTO security_privileges(code,description) VALUES(?,?)',[$p,$description]);
 $r->run("INSERT IGNORE INTO security_role_privileges(role_id,privilege_id) SELECT r.id,p.id FROM security_roles r CROSS JOIN security_privileges p WHERE r.name='admin' AND p.code LIKE 'hr.%'");
 $r->run("INSERT IGNORE INTO security_role_privileges(role_id,privilege_id) SELECT r.id,p.id FROM security_roles r CROSS JOIN security_privileges p WHERE r.name='clerk' AND p.code='hr.self'");
 foreach(['Signed employment contract','Identity document','Probation review','Qualification / certificate','Leave supporting document','Other HR document'] as $name)$r->run("INSERT IGNORE INTO hr_catalogue(group_name,name) VALUES('document',?)",[$name]);
 $a->audit(null,'system.hr_installed','schema','hr-1','succeeded',['existing_user_ids_unchanged'=>true,'financial_records_unchanged'=>true]);$r->run('INSERT INTO hr_schema(version) VALUES(1)');
});echo "HR schema installed. No staff-account inference or financial backfill performed.\n";
