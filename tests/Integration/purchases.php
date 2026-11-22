<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';
use Tivoli\Service\PurchaseService;use Tivoli\Service\FinanceService;
$a=access_service();$r=$a->repo;if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$n=0;$tag=bin2hex(random_bytes(7));$clock='2046-06-05 20:00:00';$owner=(int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn();putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$owner);
function pa(bool $ok,string $label):void{global $n;if(!$ok)throw new RuntimeException($label);$n++;echo "PASS $label\n";}
function pd(callable $f,string $label):void{try{$f();}catch(Throwable $e){pa(true,$label);return;}throw new RuntimeException('Expected denial '.$label);}
function pk():string{return bin2hex(random_bytes(16));}
function pu(string $role='clerk'):int{global $r,$tag;$r->run('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',['Synthetic purchase '.$tag,pk().'@example.invalid',password_hash('Synthetic Purchase Passphrase 123!',PASSWORD_BCRYPT),$role]);$id=(int)$r->db->lastInsertId();$r->run('INSERT INTO security_accounts(user_id,must_change_password) VALUES(?,0)',[$id]);$r->run('INSERT INTO security_user_roles SELECT ?,id FROM security_roles WHERE name=?',[$id,$role]);return $id;}
$clerk=pu();$other=pu();$admin=pu('admin');$category=(int)$r->run('SELECT id FROM expense_categories WHERE active=1 ORDER BY id LIMIT 1')->fetchColumn();
pa(class_exists(PurchaseService::class),'Approved purchase workflow exists');
$p=new PurchaseService($a,$clock);$f=new FinanceService($a,$clock);
$base=$f->summary('2046-06-05','2046-06-05');$cash=$f->cash()['held'];$original=hash('sha256',json_encode($r->run('SELECT * FROM expenses ORDER BY id')->fetchAll()));$cursor=(int)$r->run('SELECT COALESCE(MAX(id),0) FROM expenses')->fetchColumn();
$request=['type'=>'expense','category_id'=>(string)$category,'title'=>'Synthetic cleaning '.$tag,'supplier'=>'Synthetic vendor '.$tag,'phone'=>'0000000000','purpose'=>'Room preparation','estimate'=>'180.00','method'=>'momo'];
$key=pk();$id=$p->execute($clerk,'request',$request,$key)['id'];pa($p->execute($clerk,'request',$request,$key)['id']===$id,'Request retry retains one source');
pa($f->summary('2046-06-05','2046-06-05')===$base,'Request creates no cost or payment');
pd(fn()=>$p->detail($other,$id),'Other staff cannot read private purchase');pd(fn()=>$p->detail($admin,$id),'Other administrator cannot read owner workflow');
function pc(string $action,array $in,int $actor):array{global $p;return $p->execute($actor,$action,$in,pk());}
function ver(int $id,int $actor):array{global $p;return ['id'=>(string)$id,'version'=>$p->detail($actor,$id)['version']];}
$review=ver($id,$clerk)+['decision'=>'approve','limit'=>'180.00','reason'=>'Estimate checked'];
pd(fn()=>pc('approve',$review,$clerk),'Staff cannot approve spending');pd(fn()=>pc('approve',$review,$admin),'Non-owner admin cannot approve spending');pc('approve',ver($id,$owner)+['decision'=>'approve','limit'=>'180.00','reason'=>'Estimate checked'],$owner);
pa($p->detail($clerk,$id)['limit']===18000,'Owner grants exact spending cap');
pd(fn()=>pc('commit',ver($id,$clerk)+['amount'=>'181.00','committed_at'=>'2046-06-05T20:00:00','confirmed'=>'1'],$clerk),'Over-limit commitment rejected');
pc('amend',ver($id,$clerk)+['limit'=>'210.00','reason'=>'New supplier quote','supplier'=>$request['supplier'],'scope'=>'Room preparation plus toiletries'],$clerk);
pd(fn()=>pc('commit',ver($id,$clerk)+['amount'=>'180.00','committed_at'=>'2046-06-05T20:00:00','confirmed'=>'1'],$clerk),'Pending revised approval blocks commitment');
pc('approve',ver($id,$owner)+['decision'=>'approve','limit'=>'210.00','reason'=>'Revised quote checked'],$owner);
pc('commit',ver($id,$clerk)+['amount'=>'210.00','committed_at'=>'2046-06-05T20:00:00','confirmed'=>'1'],$clerk);
$receive=['actual_cost'=>'230.00','delivered_at'=>'2046-06-05T20:00:00','description'=>'Complete goods received','missing_evidence'=>'Vendor has no formal receipt; goods and quote checked','method'=>'momo','payment_status'=>'unpaid','received'=>'1'];
pc('receive',ver($id,$clerk)+$receive,$clerk);pa($p->detail($clerk,$id)['status']==='exception','Actual overspend retained as after-the-fact exception');
pd(fn()=>pc('post',ver($id,$owner)+['verified'=>'1'],$owner),'Exception posting requires owner reason');
$key=pk();$in=ver($id,$owner)+['verified'=>'1','reason'=>'Accept evidenced actual cost; supplier change discussed'];$posted=$p->execute($owner,'post',$in,$key);$expense=$p->detail($owner,$id)['expense_id'];
pa($p->execute($owner,'post',$in,$key)['id']===$posted['id'],'Posting retry creates one expense');pa($f->summary('2046-06-05','2046-06-05')['cost']===$base['cost']+23000,'Cost recognised exactly once');pa($p->detail($owner,$id)['limit']===21000,'Exception acceptance does not rewrite approved cap');
pd(fn()=>pc('post',ver($id,$owner)+['verified'=>'1','reason'=>'Try another posting'],$owner),'Second fresh posting denied');
pd(fn()=>$f->execute($owner,'pay_cost',['type'=>'expense','id'=>(string)$expense,'amount'=>'230.00','method'=>'momo','reference'=>'DOUBLE-'.$tag,'destination'=>'Supplier ****1234','evidence'=>'Checked','verified'=>'1'],pk()),'Legacy payout cannot repay a linked purchase');
$payment=['amount'=>'100.00','method'=>'momo','payer_id'=>(string)$clerk,'paid_at'=>'2046-06-05T20:00:00','reference'=>'PART-'.$tag,'evidence'=>'Independent merchant debit checked','paid'=>'1'];
$key=pk();$in=ver($id,$owner)+$payment;$p->execute($owner,'pay',$in,$key);$p->execute($owner,'pay',$in,$key);
pa($p->detail($owner,$id)['paid']===10000,'Partial settlement retry records one payout');pa($f->summary('2046-06-05','2046-06-05')['cost']===$base['cost']+23000,'Settlement does not recognise another cost');
pd(fn()=>pc('pay',ver($id,$owner)+[...$payment,'amount'=>'131.00','reference'=>'OVER-'.$tag],$owner),'Overpayment denied');
pc('pay',ver($id,$owner)+[...$payment,'amount'=>'130.00','reference'=>'REST-'.$tag],$owner);pa($p->detail($owner,$id)['paid']===23000,'Remaining settlement is exact');
$r->run("INSERT INTO extras(name,price,stock_qty,active) VALUES(?,'10.00','0.00',1)",['Purchase stock '.$tag]);$extra=(int)$r->db->lastInsertId();$r->run("INSERT INTO stay_extra_settings(extra_id,category,tracked,low_stock) VALUES(?,'General',1,'0.00')",[$extra]);
$stock=pc('request',[...$request,'type'=>'stock','extra_id'=>(string)$extra,'qty'=>'24','estimate'=>'240.00'],$clerk)['id'];pc('approve',ver($stock,$owner)+['decision'=>'approve','limit'=>'240.00','reason'=>'Stock purchase checked'],$owner);pc('commit',ver($stock,$clerk)+['amount'=>'240.00','committed_at'=>'2046-06-05T20:00:00','confirmed'=>'1'],$clerk);pc('receive',ver($stock,$clerk)+[...$receive,'actual_cost'=>'240.00'],$clerk);pc('post',ver($stock,$owner)+['verified'=>'1','stock_verified'=>'1','reason'=>'Full quantity checked'],$owner);
pa($r->run('SELECT stock_qty FROM extras WHERE id=?',[$extra])->fetchColumn()==='24.00','Confirmed stock receipt updates exact quantity');pa((int)$r->run("SELECT COUNT(*) FROM stock_movements WHERE ref_type='purchase' AND ref_id=?",[$stock])->fetchColumn()===1,'Stock receipt has one stable purchase link');
$pending=pc('request',$request,$clerk)['id'];$state=ver($pending,$owner);pc('approve',$state+['decision'=>'reject','reason'=>'Not required'],$owner);pd(fn()=>pc('approve',$state+['decision'=>'approve','limit'=>'10.00','reason'=>'Stale review'],$owner),'Stale approval cannot replace rejection');
$before=(int)$r->run('SELECT COUNT(*) FROM finance_events')->fetchColumn();$r->db->exec("CREATE TRIGGER purchase_test_fail BEFORE INSERT ON security_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic purchase audit failure'");try{pd(fn()=>pc('request',$request,$clerk),'Required audit failure aborts request');}finally{$r->db->exec('DROP TRIGGER purchase_test_fail');}pa((int)$r->run('SELECT COUNT(*) FROM finance_events')->fetchColumn()===$before,'Audit rollback preserves event count');
pa(hash('sha256',json_encode($r->run('SELECT * FROM expenses WHERE id<=? ORDER BY id',[$cursor])->fetchAll()))===$original,'All earlier expense fields and IDs unchanged');pa($f->cash()['held']===$cash,'Costs and MoMo payouts do not reduce sales cash');

pd(fn()=>pc('pay',ver($stock,$owner)+['amount'=>'1.00','method'=>'momo','payer_id'=>(string)$clerk,'paid_at'=>'2046-06-05T19:00:00','reference'=>'BEFORE-APPROVAL-'.$tag,'evidence'=>'Past debit checked','paid'=>'1'],$owner),'A later-discovered pre-approval payment requires explicit exception evidence');

$beforeStock=$r->run('SELECT stock_qty FROM extras WHERE id=?',[$extra])->fetchColumn();
pd(fn()=>pc('report',[...$request,'type'=>'stock','extra_id'=>(string)$extra,'qty'=>'24','actual_cost'=>'10.00','estimate'=>'10.00','delivered_at'=>'2046-06-05T20:00:00','description'=>'Previously received stock','missing_evidence'=>'Legacy stock record preserved','exception_reason'=>'Missing cost only','payment_status'=>'unpaid','received'=>'1'],$clerk),'Unapproved earlier-stock cost cannot add inventory again');
pa($r->run('SELECT stock_qty FROM extras WHERE id=?',[$extra])->fetchColumn()===$beforeStock,'Earlier stock quantity is preserved');

function purchaseReady(int $who,int $owner,array $request,array $receive,?array $file=null):int{
 $id=pc('request',$request,$who)['id'];pc('approve',ver($id,$owner)+['decision'=>'approve','limit'=>$request['estimate'],'reason'=>'Synthetic prior approval'],$owner);
 pc('commit',ver($id,$who)+['amount'=>$request['estimate'],'committed_at'=>'2046-06-05T20:00:00','confirmed'=>'1'],$who);
 $in=ver($id,$who)+$receive;if($file)$in['_evidence']=$file;pc('receive',$in,$who);return $id;
}
$proof=\Tivoli\Service\FinanceAttachment::validate('%PDF-1.4'."\n".'Synthetic purchase evidence '.$tag,'proof.pdf');
$paidReceive=[...$receive,'actual_cost'=>'12.00','payment_status'=>'paid','payer_id'=>(string)$clerk,'paid_at'=>'2046-06-05T20:00:00','reference'=>'DECLARED-'.$tag];
$already=purchaseReady($clerk,$owner,[...$request,'estimate'=>'12.00'],$paidReceive,$proof);
pd(fn()=>pc('post',ver($already,$owner)+['verified'=>'1'],$owner),'Already-paid declaration needs independent owner payment check');
$beforePaid=$f->summary('2046-06-05','2046-06-05')['paid_costs'];pc('post',ver($already,$owner)+['verified'=>'1','payment_verified'=>'1'],$owner);
pa($p->detail($owner,$already)['paid']===1200&&$f->summary('2046-06-05','2046-06-05')['paid_costs']===$beforePaid+1200,'Verified prior payment posts once without another physical payment');
$receiptEvent=$p->detail($clerk,$already)['receipt']['event_id'];pa(\Tivoli\Service\FinanceAttachment::download($a,$clerk,'purchase',$receiptEvent)['bytes']===$proof['bytes'],'Private evidence retains original bytes for requester');
pd(fn()=>\Tivoli\Service\FinanceAttachment::download($a,$admin,'purchase',$receiptEvent),'Other administrators denied purchase evidence');
pd(fn()=>(new \Tivoli\Service\ExpenseService($a))->submit($clerk,['expense_date'=>'2046-06-05','category_id'=>(string)$category,'amount'=>'12.00','method'=>'momo','vendor'=>$request['supplier'],'reference_no'=>'','description'=>'Duplicate invoice','_evidence'=>$proof],pk()),'Shared evidence claim blocks a duplicate legacy expense');
$stockDup=purchaseReady($clerk,$owner,[...$request,'type'=>'stock','extra_id'=>(string)$extra,'qty'=>'2','estimate'=>'12.00'],$paidReceive);
$qtyBefore=$r->run('SELECT stock_qty FROM extras WHERE id=?',[$extra])->fetchColumn();$expenseBefore=(int)$r->run('SELECT COUNT(*) FROM expenses')->fetchColumn();$movementBefore=(int)$r->run('SELECT COUNT(*) FROM stock_movements')->fetchColumn();
pd(fn()=>pc('post',ver($stockDup,$owner)+['verified'=>'1','stock_verified'=>'1','payment_verified'=>'1'],$owner),'Duplicate payment reference aborts combined cost stock and payout');
pa($p->detail($owner,$stockDup)['expense_id']===null&&(int)$r->run('SELECT COUNT(*) FROM expenses')->fetchColumn()===$expenseBefore&&(int)$r->run('SELECT COUNT(*) FROM stock_movements')->fetchColumn()===$movementBefore&&$r->run('SELECT stock_qty FROM extras WHERE id=?',[$extra])->fetchColumn()===$qtyBefore,'Combined rollback leaves no cost stock or quantity residue');
$rollback=purchaseReady($clerk,$owner,[...$request,'type'=>'stock','extra_id'=>(string)$extra,'qty'=>'1','estimate'=>'5.00'],[...$receive,'actual_cost'=>'5.00']);
$expenseBefore=(int)$r->run('SELECT COUNT(*) FROM expenses')->fetchColumn();$movementBefore=(int)$r->run('SELECT COUNT(*) FROM stock_movements')->fetchColumn();
$r->db->exec("CREATE TRIGGER purchase_test_fail BEFORE INSERT ON security_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic purchase audit failure'");
try{pd(fn()=>pc('post',ver($rollback,$owner)+['verified'=>'1','stock_verified'=>'1'],$owner),'Audit failure aborts cost and stock posting');}finally{$r->db->exec('DROP TRIGGER purchase_test_fail');}
pa((int)$r->run('SELECT COUNT(*) FROM expenses')->fetchColumn()===$expenseBefore&&(int)$r->run('SELECT COUNT(*) FROM stock_movements')->fetchColumn()===$movementBefore&&$r->run('SELECT stock_qty FROM extras WHERE id=?',[$extra])->fetchColumn()===$qtyBefore,'Audit failure rolls back expense movement and stock balance');
$beforeEvents=(int)$r->run('SELECT COUNT(*) FROM finance_events')->fetchColumn();$beforeClaims=(int)$r->run('SELECT COUNT(*) FROM finance_claims')->fetchColumn();$beforeOps=(int)$r->run('SELECT COUNT(*) FROM finance_operations')->fetchColumn();
$r->db->exec("CREATE TRIGGER purchase_test_fail BEFORE INSERT ON security_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic purchase audit failure'");
try{pd(fn()=>pc('pay',ver($stock,$owner)+[...$payment,'amount'=>'1.00','reference'=>'ROLLPAY-'.$tag],$owner),'Audit failure aborts settlement');}finally{$r->db->exec('DROP TRIGGER purchase_test_fail');}
pa((int)$r->run('SELECT COUNT(*) FROM finance_events')->fetchColumn()===$beforeEvents&&(int)$r->run('SELECT COUNT(*) FROM finance_claims')->fetchColumn()===$beforeClaims&&(int)$r->run('SELECT COUNT(*) FROM finance_operations')->fetchColumn()===$beforeOps,'Settlement rollback leaves no payment claim or operation');
pc('pay',ver($stock,$owner)+['amount'=>'1.00','method'=>'momo','payer_id'=>(string)$clerk,'paid_at'=>'2046-06-05T19:00:00','reference'=>'AUTH-PRIOR-'.$tag,'evidence'=>'Past debit checked','paid'=>'1','exception_reason'=>'Payment made before approval; owner accepts evidenced exception'],$owner);
$last=$p->detail($owner,$stock)['payments'];pa(end($last)['data']['exception']===true&&str_contains(end($last)['data']['exception_reason'],'before approval'),'Later-discovered pre-approval settlement retains explicit exception evidence');
$old='2046-05-28';$week=(new DateTimeImmutable($old))->modify('monday this week')->format('Y-m-d');$r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at) VALUES('period_locked','period',?,0,?,?,?,?)",['week:'.$week,$old,$owner,json_encode(['synthetic_snapshot'=>$tag]),$old.' 21:00:00']);
$closedPayload=$r->run("SELECT payload FROM finance_events WHERE kind='period_locked' AND target_id=? ORDER BY id DESC LIMIT 1",['week:'.$week])->fetchColumn();$closedBefore=$f->summary($week,(new DateTimeImmutable($week))->modify('+6 days')->format('Y-m-d'));
$late=pc('report',[...$request,'estimate'=>'3.00','actual_cost'=>'3.00','delivered_at'=>$old.'T10:00:00','description'=>'Actual earlier cost','missing_evidence'=>'Delivery and supplier declaration','exception_reason'=>'No original prior approval','payment_status'=>'paid','method'=>'momo','payer_id'=>(string)$clerk,'paid_at'=>$old.'T11:00:00','reference'=>'LATEPUR-'.$tag,'received'=>'1'],$clerk)['id'];
pc('post',ver($late,$owner)+['verified'=>'1','payment_verified'=>'1','reason'=>'Original activity evidenced; recognise in open period'],$owner);
$lateCase=$p->detail($owner,$late);pa($r->run('SELECT expense_date FROM expenses WHERE id=?',[$lateCase['expense_id']])->fetchColumn()==='2046-06-05','Closed-period cost recognises in current open period');
pa($closedBefore===$f->summary($week,(new DateTimeImmutable($week))->modify('+6 days')->format('Y-m-d'))&&$r->run("SELECT payload FROM finance_events WHERE kind='period_locked' AND target_id=? ORDER BY id DESC LIMIT 1",['week:'.$week])->fetchColumn()===$closedPayload,'Closed monetary totals and original snapshot remain fixed');
pa($lateCase['payments'][0]['data']['original_activity_date']===$old,'Late payout retains original activity date');
pa(PurchaseService::pendingCosts($r,'2046-06-01','2046-06-30')>0,'Unreviewed received costs remain visible for period close');
pd(fn()=>$f->execute($owner,'period_lock',['period'=>'month','start'=>'2046-06-01','comment'=>'Synthetic closure','complete'=>'1'],pk()),'Unreviewed purchase costs prevent silent monthly closure');
echo "TOTAL $n purchase assertions passed.\n";



