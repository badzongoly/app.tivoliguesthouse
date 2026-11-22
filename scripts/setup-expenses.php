<?php
if(PHP_SAPI!=='cli' || ($argv[1] ?? '')!=='--confirmed-local'){exit("Use --confirmed-local only after verifying the local target.\n");}
require dirname(__DIR__).'/bootstrap/security.php';
$config=access_config();$testing=getenv('TIVOLI_TEST_MODE')==='1';
$expected=$testing?'mysql:host=127.0.0.1;port=3307;dbname=tivoli_auth_test;charset=utf8mb4':'mysql:host=127.0.0.1;port=3306;dbname=tivoli;charset=utf8mb4';
if($config['dsn']!==$expected){throw new RuntimeException('Unapproved expense setup target.');}
$service=access_service();$r=$service->repo;
if($testing){if($r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1'){throw new RuntimeException('Unverified synthetic database.');}foreach(require dirname(__DIR__).'/tests/Fixtures/expense_schema.php' as $sql){$r->db->exec($sql);}}
foreach(['expenses','expense_categories'] as $table){$statement=$r->run('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$table]);if($statement->fetchColumn()!=='InnoDB'){throw new RuntimeException('Expected existing InnoDB expense tables.');}}
foreach(array_merge(require dirname(__DIR__).'/database/migrations/004_expense_approvals.php',require dirname(__DIR__).'/database/migrations/005_expense_submission_identity.php') as $sql){$r->db->exec($sql);}
if($r->run('SELECT version FROM expense_schema WHERE version=2')->fetchColumn()){exit("Expense approvals already installed; assignments retained.\n");}
if($r->run('SELECT version FROM expense_schema WHERE version=1')->fetchColumn()){
 $service->transaction(function() use($service,$r){$r->run('INSERT INTO expense_schema(version) VALUES(2)');$service->audit(null,'system.expense_approvals_upgraded','schema','expense-2','succeeded');});
 exit("Expense identity protection installed.\n");
}
$service->transaction(function() use($service,$r){
 foreach(['expenses.submit'=>'Submit expenses for administrator approval','expenses.approve'=>'Approve or reject clerk expense submissions'] as $code=>$description){$r->run('INSERT IGNORE INTO security_privileges(code,description) VALUES(?,?)',[$code,$description]);}
 $r->run("INSERT IGNORE INTO security_role_privileges(role_id,privilege_id) SELECT r.id,p.id FROM security_roles r CROSS JOIN security_privileges p WHERE r.name='admin' AND p.code IN ('expenses.submit','expenses.approve')");
 $r->run("INSERT IGNORE INTO security_role_privileges(role_id,privilege_id) SELECT r.id,p.id FROM security_roles r CROSS JOIN security_privileges p WHERE r.name='clerk' AND p.code='expenses.submit'");
 $service->audit(null,'system.expense_approvals_installed','schema','expense-1','succeeded',['historical_expenses_unchanged'=>true,'clerk_requires_approval'=>true]);
 $r->run('INSERT INTO expense_schema(version) VALUES(1),(2)');
});
echo "Expense approvals installed. Existing expense rows unchanged.\n";