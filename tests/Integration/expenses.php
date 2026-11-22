<?php
if(PHP_SAPI!=='cli'){exit(1);}putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/security.php';
$a=access_service();$r=$a->repo;$s=new \Tivoli\Service\ExpenseService($a);
if($r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1' || $r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'){throw new RuntimeException('Unsafe expense test target');}
$count=0;function assertExpense(bool $ok,string $label):void{global $count;if(!$ok){throw new RuntimeException('FAIL '.$label);}echo "PASS $label\n";$count++;}
function rejectsExpense(callable $work,string $label):void{try{$work();}catch(Throwable $error){assertExpense(true,$label);return;}assertExpense(false,$label);}
$r->run('DELETE FROM security_throttle');$tag=bin2hex(random_bytes(5));$adminPass='Synthetic Admin Passphrase 123!';$ids=[];
for($i=0;$i<2;$i++){$email='expense-'.$tag.'-'.$i.'@example.invalid';$a->manage(1,$adminPass,'save_user',['name'=>'Synthetic expense clerk','email'=>$email,'password'=>'Synthetic Expense Passphrase 123!','reason'=>'Expense approval test fixture']);$ids[]=(int)$r->run('SELECT id FROM users WHERE email=?',[$email])->fetchColumn();$r->run('UPDATE security_accounts SET must_change_password=0 WHERE user_id=?',[$ids[$i]]);}
[$clerk,$other]=$ids;$s->addCategory(1,'Synthetic expense '.$tag);$category=(int)$r->db->lastInsertId(); // Audit inserts may alter the connection's lastInsertId; query source identity explicitly.
$category=(int)$r->run('SELECT id FROM expense_categories WHERE name=?',['Synthetic expense '.$tag])->fetchColumn();
$base=['expense_date'=>'2040-02-03','category_id'=>(string)$category,'method'=>'momo','amount'=>'0.10','vendor'=>'Synthetic vendor','reference_no'=>'=2+2','description'=>'Synthetic expense fixture','id'=>'0'];
$filters=['start'=>'2040-02-01','end'=>'2040-02-28','category_id'=>$category,'method'=>'all','q'=>''];
assertExpense($s->canSubmit($clerk),'clerk has submission privilege');assertExpense(!$s->canReview($clerk),'clerk cannot review');
$admin=$s->submit(1,[...$base,'amount'=>'2.20'],bin2hex(random_bytes(16)));assertExpense($admin['type']==='expense','admin expense posts immediately');
assertExpense($s->repo->approved($filters)['total']==='2.20','admin amount counted exactly');
$key=bin2hex(random_bytes(16));$submitted=$s->submit($clerk,[...$base,'status'=>'approved','user_id'=>'1'],$key);
assertExpense($submitted['type']==='submission','forged approval field cannot post clerk expense');
$row=$r->run('SELECT * FROM expense_submissions WHERE id=?',[$submitted['id']])->fetch();assertExpense($row['status']==='pending' && (int)$row['submitted_by']===$clerk,'server derives pending state and submitting actor');
assertExpense($s->repo->approved($filters)['total']==='2.20','pending excluded from expense totals');
$grouped=(string)$r->run('SELECT SUM(amount) FROM expenses WHERE category_id=? AND expense_date BETWEEN ? AND ?',[$category,'2040-02-01','2040-02-28'])->fetchColumn();assertExpense($grouped==='2.20','legacy report query naturally excludes pending');
$replay=$s->submit($clerk,$base,$key);assertExpense($replay['id']===$submitted['id'] && $replay['replayed'],'duplicate submission returns original identity');
rejectsExpense(fn()=>$s->submit($clerk,[...$base,'amount'=>'0.11'],$key),'same submission key with changed payload rejected');
assertExpense(count($s->repo->submissions($other,false))===0,'clerk cannot see another clerk submissions');
rejectsExpense(fn()=>$s->review($clerk,$submitted['id'],'approved','Forged clerk approval'),'clerk cannot approve own submission');
$privilege=(int)$r->run("SELECT id FROM security_privileges WHERE code='expenses.approve'")->fetchColumn();$clerkRole=(int)$r->run("SELECT id FROM security_roles WHERE name='clerk'")->fetchColumn();
$r->run('INSERT IGNORE INTO security_role_privileges(role_id,privilege_id) VALUES(?,?)',[$clerkRole,$privilege]);
try{rejectsExpense(fn()=>$s->review($other,$submitted['id'],'approved','Forged clerk reviewer'),'approval privilege alone cannot bypass admin requirement');}finally{$r->run('DELETE FROM security_role_privileges WHERE role_id=? AND privilege_id=?',[$clerkRole,$privilege]);}
$approved=$s->review(1,$submitted['id'],'approved','Reviewed synthetic receipt');assertExpense($approved['status']==='approved' && $approved['expense_id']>0,'administrator approves into counted ledger');
assertExpense($s->repo->approved($filters)['total']==='2.30','approved amount counted exactly once');
assertExpense((int)$r->run('SELECT user_id FROM expenses WHERE id=?',[$approved['expense_id']])->fetchColumn()===$clerk,'posted expense retains original clerk attribution');
$again=$s->review(1,$submitted['id'],'approved','Repeated browser request');assertExpense($again['expense_id']===$approved['expense_id'] && $again['replayed'],'repeat approval returns same expense');
assertExpense($s->repo->approved($filters)['total']==='2.30','repeat approval never double counts');
rejectsExpense(fn()=>$s->review(1,$submitted['id'],'rejected','Attempt changed review'),'approved decision cannot be overwritten');
$reject=$s->submit($clerk,[...$base,'amount'=>'8.50'],bin2hex(random_bytes(16)));$s->review(1,$reject['id'],'rejected','Missing supporting receipt');
assertExpense($s->repo->approved($filters)['total']==='2.30','rejected expenses excluded');
assertExpense($r->run('SELECT review_reason FROM expense_submissions WHERE id=?',[$reject['id']])->fetchColumn()==='Missing supporting receipt','rejection reason retained');
rejectsExpense(fn()=>$s->review(1,$reject['id'],'approved','Attempt approval after rejection'),'rejected decision cannot be overwritten');
foreach(['0','-1','1.001','1e3','NaN','99999999999.99','01.20'] as $amount){rejectsExpense(fn()=>$s->submit($clerk,[...$base,'amount'=>$amount],bin2hex(random_bytes(16))),'invalid money rejected: '.$amount);}
assertExpense(\Tivoli\Domain\Money\ExpenseAmount::parse('9999999999.99')==='9999999999.99','maximum DECIMAL boundary exact');
rejectsExpense(fn()=>$s->submit($clerk,[...$base,'vendor'=>'Emoji 🚕'],bin2hex(random_bytes(16))),'unsupported ledger charset rejected before creating submission');
rejectsExpense(fn()=>$s->submit($clerk,[...$base,'expense_date'=>'2040-02-30'],bin2hex(random_bytes(16))),'invalid expense date rejected');
rejectsExpense(fn()=>$s->submit($clerk,[...$base,'method'=>'invented'],bin2hex(random_bytes(16))),'unknown payment method rejected');
rejectsExpense(fn()=>$s->submit($clerk,[...$base,'id'=>(string)$admin['id']],bin2hex(random_bytes(16))),'posted edit attempt rejected');
rejectsExpense(fn()=>$s->addCategory($clerk,'Forbidden category '.$tag),'clerk cannot create categories');
$s->addCategory(1,'Inactive category '.$tag);$inactive=(int)$r->run('SELECT id FROM expense_categories WHERE name=?',['Inactive category '.$tag])->fetchColumn();$r->run('UPDATE expense_categories SET active=0 WHERE id=?',[$inactive]);
rejectsExpense(fn()=>$s->submit($clerk,[...$base,'category_id'=>(string)$inactive],bin2hex(random_bytes(16))),'inactive category rejected');
$r->run('UPDATE security_accounts SET active=0 WHERE user_id=?',[$other]);rejectsExpense(fn()=>$s->submit($other,$base,bin2hex(random_bytes(16))),'inactive account cannot submit');$r->run('UPDATE security_accounts SET active=1 WHERE user_id=?',[$other]);
rejectsExpense(fn()=>$r->run('UPDATE expenses SET amount=? WHERE id=?',['3.00',$admin['id']]),'database blocks posted expense edits');
rejectsExpense(fn()=>$r->run('DELETE FROM expenses WHERE id=?',[$admin['id']]),'database blocks posted expense deletion');
rejectsExpense(fn()=>$r->run('DELETE FROM expense_submissions WHERE id=?',[$submitted['id']]),'submission history cannot be deleted');
$pending=$s->submit($clerk,[...$base,'amount'=>'9.99'],bin2hex(random_bytes(16)));
rejectsExpense(fn()=>$r->run('UPDATE expense_submissions SET amount=? WHERE id=?',['1.00',$pending['id']]),'submitted amount cannot be silently changed');
$adminRole=(int)$r->run("SELECT id FROM security_roles WHERE name='admin'")->fetchColumn();$r->run('INSERT INTO security_user_roles(user_id,role_id) VALUES(?,?)',[$clerk,$adminRole]);
try{rejectsExpense(fn()=>$s->review($clerk,$pending['id'],'approved','Self approval after promotion'),'promoted requester still cannot approve own submission');}finally{$r->run('DELETE FROM security_user_roles WHERE user_id=? AND role_id=?',[$clerk,$adminRole]);}
$before=(int)$r->run('SELECT COUNT(*) FROM expense_submissions')->fetchColumn();$ops=(int)$r->run('SELECT COUNT(*) FROM expense_operations')->fetchColumn();$total=$s->repo->approved($filters)['total'];
$r->db->exec("CREATE TRIGGER expense_test_audit_failure BEFORE INSERT ON security_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic expense audit failure'");
try{
 rejectsExpense(fn()=>$s->submit($clerk,$base,bin2hex(random_bytes(16))),'audit failure aborts submission');
 rejectsExpense(fn()=>$s->review(1,$pending['id'],'approved','Audit rollback verification'),'audit failure aborts approval');
}finally{$r->db->exec('DROP TRIGGER expense_test_audit_failure');}
assertExpense((int)$r->run('SELECT COUNT(*) FROM expense_submissions')->fetchColumn()===$before,'failed submission left no partial row');
assertExpense((int)$r->run('SELECT COUNT(*) FROM expense_operations')->fetchColumn()===$ops,'failed submission left no operation identity');
assertExpense($s->repo->approved($filters)['total']===$total,'failed approval left no counted expense');
assertExpense($r->run('SELECT status FROM expense_submissions WHERE id=?',[$pending['id']])->fetchColumn()==='pending','failed approval retains pending state');
$state=$r->run("SELECT details,actor_id FROM security_audit WHERE action='expense.approved' AND target_id=? AND outcome='succeeded' ORDER BY id DESC LIMIT 1",[(string)$submitted['id']])->fetch();
assertExpense((int)$state['actor_id']===1 && str_contains($state['details'],'Reviewed synthetic receipt'),'approval audit contains real administrator and reason');
assertExpense(str_contains($state['details'],'submitted_by') && str_contains($state['details'],'expense_id'),'audit links submitter and posted expense');
// Verify the known 300-row list limit no longer truncates the full-period total.
for($i=0;$i<301;$i++){$r->run('INSERT INTO expenses(expense_date,category_id,method,amount,vendor,reference_no,description,user_id) VALUES(?,?,?,?,?,?,?,?)',['2040-02-03',$category,'cash','0.01','Synthetic cap fixture','','',1]);}
$listing=$s->repo->approved($filters);assertExpense(count($listing['rows'])===300 && $listing['count']===303,'300-row list limit explicitly separated from complete count');
assertExpense($listing['total']==='5.31','full filtered total includes every approved expense with exact cents');
echo "TOTAL $count expense integration assertions passed.\n";