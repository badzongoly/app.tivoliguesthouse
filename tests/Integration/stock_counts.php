<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';
use Tivoli\Service\StockCountService;
$a=access_service();$r=$a->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
if(!class_exists(StockCountService::class))throw new RuntimeException('Approved stock count workflow is missing');

$n=0;$tag=bin2hex(random_bytes(6));$clock='2052-08-05 14:00:00';$owner=(int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn();putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$owner);
function sk():string{return bin2hex(random_bytes(16));}
function sa(bool $ok,string $label):void{global $n;if(!$ok)throw new RuntimeException($label);$n++;echo "PASS $label\n";}
function sd(callable $f,string $label):void{try{$f();}catch(Throwable $e){sa(true,$label);return;}throw new RuntimeException('Expected denial: '.$label);}
function su(string $role='clerk'):int{global $r;$r->run('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',['Synthetic stock counter',sk().'@example.invalid',password_hash('Synthetic Stock Passphrase 123!',PASSWORD_BCRYPT),$role]);$id=(int)$r->db->lastInsertId();$r->run('INSERT INTO security_accounts(user_id,must_change_password) VALUES(?,0)',[$id]);$r->run('INSERT INTO security_user_roles SELECT ?,id FROM security_roles WHERE name=?',[$id,$role]);return $id;}
$staff=su();$other=su();$admin=su('admin');$svc=new StockCountService($a,$clock);
function sx(string $stock='48.00'):int{global $r;$r->run("INSERT INTO extras(name,price,active,stock_qty) VALUES(?,1,1,?)",['Synthetic count '.sk(),$stock]);$id=(int)$r->db->lastInsertId();$r->run("INSERT INTO stay_extra_settings(extra_id,category,tracked,low_stock) VALUES(?,'General',1,0)",[$id]);return $id;}
function sc(int $who,string $action,array $in=[],?string $key=null):array{global $svc;return $svc->execute($who,$action,$in,$key??sk());}
function sv(int $id,int $actor):array{global $svc;return ['id'=>(string)$id,'version'=>$svc->detail($actor,$id)['version']];}
function start(int $actor,array $items,?int $parent=null):int{return sc($actor,'start',['items'=>json_encode($items),'purpose'=>'Weekly Stock Count','comment'=>'Synthetic full count']+($parent?['parent'=>(string)$parent]:[]))['id'];}
function countLine(int $actor,int $id,int $extra,string $qty):void{sc($actor,'begin',sv($id,$actor)+['extra_id'=>(string)$extra]);sc($actor,'finish',sv($id,$actor)+['extra_id'=>(string)$extra,'qty'=>$qty,'confirmed'=>'1','note'=>'Synthetic physical count']);}
function send(int $actor,int $id):void{global $svc;$c=$svc->detail($actor,$id);$reasons=[];foreach($c['lines'] as $key=>$line)$reasons[$key]=['reason'=>'Missing / Unexplained','note'=>'Checked both store and shelf'];sc($actor,'submit',sv($id,$actor)+['reasons'=>json_encode($reasons),'confirmed'=>'1']);}
function approve(int $id):array{global $svc,$owner;return sc($owner,'approve',sv($id,$owner)+['review_token'=>$svc->detail($owner,$id)['review_token'],'decision'=>'Physical count and records checked','verified'=>'1','authorized'=>'1']);}
function move(int $extra,string $qty,string $type='out'):void{global $a,$r,$clock;$a->transaction(function()use($r,$extra,$qty,$type,$clock){$r->run('UPDATE extras SET stock_qty=stock_qty+? WHERE id=?',[$qty,$extra]);$r->run('INSERT INTO stock_movements(extra_id,movement_type,qty,note,ref_type,ref_id,created_by,created_at) VALUES(?,?,?,?,?,?,?,?)',[$extra,$type,$type==='out'?ltrim($qty,'-'):$qty,'Synthetic intervening movement','synthetic_count',0,(int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn(),$clock]);});}
$original=hash('sha256',json_encode($r->run('SELECT * FROM stock_movements ORDER BY id')->fetchAll()));$cursor=(int)$r->run('SELECT COALESCE(MAX(id),0) FROM stock_movements')->fetchColumn();$money=hash('sha256',json_encode([$r->run('SELECT * FROM expenses ORDER BY id')->fetchAll(),$r->run('SELECT * FROM payments ORDER BY id')->fetchAll()]));
$x=sx();$y=sx('24.00');$id=start($staff,[$x,$y]);
sa($svc->detail($staff,$id)['status']==='counting','Starting a count changes no stock');
sd(fn()=>$svc->detail($other,$id),'Other staff cannot inspect private count');
sd(fn()=>$svc->detail($admin,$id),'Non-owner administrator cannot inspect private count');
sc($staff,'begin',sv($id,$staff)+['extra_id'=>(string)$x]);
sa(!isset($svc->detail($staff,$id)['lines'][$x]['window']['expected']),'Blind counting hides baseline');
move($x,'-1.00');
sd(fn()=>sc($staff,'finish',sv($id,$staff)+['extra_id'=>(string)$x,'qty'=>'46.00','confirmed'=>'1']),'Movement during count rejects submission');
countLine($staff,$id,$x,'46.00');
sa(!isset($svc->detail($staff,$id)['lines'][$x]['expected']),'Baseline remains hidden until every item counted');
sd(fn()=>send($staff,$id),'Incomplete scope cannot submit');
countLine($staff,$id,$y,'24.00');
sa($svc->detail($staff,$id)['lines'][$x]['difference']===-100,'Comparison uses stable window after intervening sale');
send($staff,$id);
sa((string)$r->run('SELECT stock_qty FROM extras WHERE id=?',[$x])->fetchColumn()==='47.00','Submission changes no quantity');
sd(fn()=>approve($id+999999),'Unknown count rejected');
sd(fn()=>sc($staff,'approve',sv($id,$staff)+['decision'=>'No authority','verified'=>'1','authorized'=>'1']),'Staff cannot approve');
sd(fn()=>sc($other,'begin',sv($id,$owner)+['extra_id'=>(string)$x]),'Other staff cannot change count');
$token=$svc->detail($owner,$id)['review_token'];move($x,'-3.00');
sd(fn()=>sc($owner,'approve',sv($id,$owner)+['review_token'=>$token,'decision'=>'Old preview','verified'=>'1','authorized'=>'1']),'Changed stock invalidates owner preview');
$c=$svc->detail($owner,$id);sa($c['lines'][$x]['current']===4400&&$c['lines'][$x]['projected']===4300,'Later sale retained in approval projection');
$input=sv($id,$owner)+['review_token'=>$c['review_token'],'decision'=>'Original count verified','verified'=>'1','authorized'=>'1'];$key=sk();$out=sc($owner,'approve',$input,$key);$again=sc($owner,'approve',$input,$key);
sa($again['replayed']&&(string)$r->run('SELECT stock_qty FROM extras WHERE id=?',[$x])->fetchColumn()==='43.00','Replay applies difference once');
sa((int)$r->run("SELECT COUNT(*) FROM stock_movements WHERE ref_type='stock_count' AND ref_id=?",[$id])->fetchColumn()===1,'No-variance item produces no movement');
sd(fn()=>sc($owner,'approve',$input,sk()),'Second approval with fresh key rejected');
sd(fn()=>sc($owner,'approve',[...$input,'decision'=>'Changed replay'],$key),'Changed payload with same key rejected');
$x=sx('10.25');$id=start($staff,[$x]);countLine($staff,$id,$x,'9.50');send($staff,$id);approve($id);sa((string)$r->run('SELECT stock_qty FROM extras WHERE id=?',[$x])->fetchColumn()==='9.50','Fractional legacy quantities remain exact');
$x=sx();$one=start($staff,[$x]);$two=start($other,[$x]);countLine($staff,$one,$x,'46.00');countLine($other,$two,$x,'46.00');send($staff,$one);send($other,$two);approve($one);sd(fn()=>approve($two),'Overlapping adjustments require recount');
sc($owner,'recount',sv($two,$owner)+['reason'=>'Another count adjusted the shared stock']);
$child=start($other,[$x],$two);sc($other,'cancel',sv($child,$other)+['reason'=>'Interrupted count']);
sa($svc->detail($owner,$two)['status']==='recount','Canceling draft recount leaves original open');
$child=start($other,[$x],$two);countLine($other,$child,$x,'46.00');send($other,$child);sa($svc->detail($owner,$two)['status']==='superseded','Submitting recount retains and supersedes original');approve($child);
sa((int)$r->run("SELECT COUNT(*) FROM stock_movements WHERE ref_type='stock_count' AND ref_id=?",[$child])->fetchColumn()===0,'Clean recount records review without adjustment');
$x=sx();$id=start($staff,[$x]);countLine($staff,$id,$x,'47.00');send($staff,$id);sc($owner,'dispute',sv($id,$owner)+['reason'=>'Explain one missing unit']);sd(fn()=>approve($id),'Disputed count cannot approve');
sc($staff,'clarify',sv($id,$staff)+['reason'=>'Additional stock location checked']);sa($svc->detail($staff,$id)['status']==='submitted','Clarification appends and returns for review');approve($id);
$x=sx();$id=start($owner,[$x]);countLine($owner,$id,$x,'48.00');send($owner,$id);sd(fn()=>approve($id),'Owner self-review needs explicit exception');
sc($owner,'approve',sv($id,$owner)+['review_token'=>$svc->detail($owner,$id)['review_token'],'decision'=>'Physical count verified','exception_reason'=>'Sole owner present; exception disclosed','verified'=>'1','authorized'=>'1']);
$x=sx();$id=start($staff,[$x]);countLine($staff,$id,$x,'1.00');send($staff,$id);move($x,'-2.00');sd(fn()=>approve($id),'Negative post-adjustment stock rejected');
$x=sx();$id=start($staff,[$x]);countLine($staff,$id,$x,'47.00');send($staff,$id);$r->run('UPDATE extras SET stock_qty=46 WHERE id=?',[$x]);sd(fn()=>approve($id),'Unexplained balance change rejected without guessing');
$x=sx();$id=start($staff,[$x]);countLine($staff,$id,$x,'47.00');send($staff,$id);$before=$svc->detail($owner,$id);$stock=$r->run('SELECT stock_qty FROM extras WHERE id=?',[$x])->fetchColumn();
$r->run("CREATE TRIGGER synthetic_stock_audit_fail BEFORE INSERT ON security_audit FOR EACH ROW BEGIN IF NEW.action='stockcount.approve' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'; END IF; END");
try{sd(fn()=>approve($id),'Required audit failure rolls back approval');}finally{$r->run('DROP TRIGGER synthetic_stock_audit_fail');}
sa($r->run('SELECT stock_qty FROM extras WHERE id=?',[$x])->fetchColumn()===$stock&&$svc->detail($owner,$id)['version']===$before['version'],'Audit rollback retains stock and original state');
approve($id);
$valid=\Tivoli\Service\FinanceAttachment::validate("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF",'proof.pdf');
$x=sx();$id=start($staff,[$x]);countLine($staff,$id,$x,'48.00');sc($staff,'submit',sv($id,$staff)+['reasons'=>'{}','confirmed'=>'1','_evidence'=>$valid]);$event=end($svc->detail($staff,$id)['history']);
sd(fn()=>\Tivoli\Service\FinanceAttachment::download($a,$admin,'stockcount',(int)$event['id']),'Private count evidence rejects other admins');
sa(\Tivoli\Service\FinanceAttachment::download($a,$staff,'stockcount',(int)$event['id'])['bytes']===$valid['bytes'],'Requester downloads original protected proof');

$x=sx();$y=sx();$partial=start($staff,[$x,$y]);countLine($staff,$partial,$x,'47.00');sc($staff,'cancel',sv($partial,$staff)+['reason'=>'Interrupted count']);
sa(!isset($svc->detail($staff,$partial)['lines'][$x]['expected'])&&!isset($svc->detail($staff,$partial)['lines'][$x]['window']),'Cancelled partial count retains blind baseline');
$r->run("INSERT INTO extras(name,price,active,stock_qty) VALUES(?,1,1,0)",['Synthetic unconfigured item '.sk()]);$x=(int)$r->db->lastInsertId();
$historic=start($staff,[$x]);countLine($staff,$historic,$x,'0.00');send($staff,$historic);approve($historic);
$pending=start($staff,[$x]);countLine($staff,$pending,$x,'0.00');send($staff,$pending);
(new \Tivoli\Service\StayService($a,$clock))->execute($owner,'extra_save',['id'=>(string)$x,'name'=>'Synthetic reclassified service '.sk(),'price'=>'1.00','category'=>'General','active'=>'1'],sk());
sa($svc->detail($staff,$historic)['status']==='approved','History remains readable after tracked item becomes service');
sa(isset($svc->register($staff)['rows'])&&isset($svc->attention($owner)['rows']),'Unusable current item cannot break registers or owner attention');
sa(isset((new \Tivoli\Service\HandoverService($a,$clock))->draft($staff)['snapshot']),'Historical reclassification cannot break handover');
sd(fn()=>approve($pending),'No adjustment allowed when item no longer tracked');

sa(hash('sha256',json_encode($r->run('SELECT * FROM stock_movements WHERE id<=? ORDER BY id',[$cursor])->fetchAll()))===$original,'Original stock movement rows preserved');
sa(hash('sha256',json_encode([$r->run('SELECT * FROM expenses ORDER BY id')->fetchAll(),$r->run('SELECT * FROM payments ORDER BY id')->fetchAll()]))===$money,'Counts and approvals create no money entries');
echo "TOTAL $n stock count assertions passed.\n";
