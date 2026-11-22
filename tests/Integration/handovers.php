<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';
use Tivoli\Service\HandoverService;use Tivoli\Service\FinanceService;
$a=access_service();$r=$a->repo;if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$count=0;$tag=bin2hex(random_bytes(7));$clock='2044-04-04 20:00:00';$owner=(int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn();putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$owner);
function ha(bool $b,string $s):void{global $count;if(!$b)throw new RuntimeException($s);$count++;echo "PASS $s\n";}
function hd(callable $f,string $s):void{try{$f();}catch(Throwable $e){ha(true,$s);return;}throw new RuntimeException('Expected denial '.$s);}
function hk():string{return bin2hex(random_bytes(16));}
function hu(string $role='clerk'):int{global $r,$tag;$r->run('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',['Synthetic handover '.$tag,bin2hex(random_bytes(5)).'@example.invalid',password_hash('Synthetic Handover Passphrase 123!',PASSWORD_BCRYPT),$role]);$id=(int)$r->db->lastInsertId();$r->run('INSERT INTO security_accounts(user_id,must_change_password) VALUES(?,0)',[$id]);$r->run('INSERT INTO security_user_roles(user_id,role_id) SELECT ?,id FROM security_roles WHERE name=?',[$id,$role]);return $id;}
$sender=hu();$receiver=hu();$third=hu();$otherAdmin=hu('admin');
ha(class_exists(HandoverService::class),'Approved handover service exists');
$h=new HandoverService($a,$clock);$f=new FinanceService($a,$clock);$payments=hash('sha256',json_encode($r->run('SELECT * FROM payments ORDER BY id')->fetchAll()));
$income=['payer'=>'Synthetic payer','activity'=>'Synthetic courtyard','amount'=>'100.00','method'=>'cash','treatment'=>'earned','provided'=>'1'];
$receipt=$f->execute($sender,'income_record',$income,hk())['id'];$draft=$h->draft($sender);ha(count($draft['snapshot']['sections'])===4,'Four source review sections');
$in=['recipient_id'=>(string)$receiver,'duty_start'=>'2044-04-04T09:00:00','duty_end'=>'2044-04-04T20:00:00','notes'=>'Synthetic handover notes','review_token'=>$draft['token'],'review_guests'=>'1','review_cash'=>'1','review_rooms'=>'1','review_tasks'=>'1','confirmed'=>'1'];
hd(fn()=>$h->execute($sender,'submit',[...$in,'recipient_id'=>(string)$sender],hk()),'Self handover denied');hd(fn()=>$h->execute($sender,'submit',[...$in,'confirmed'=>''],hk()),'Explicit submission confirmation');hd(fn()=>$h->execute($sender,'submit',[...$in,'review_cash'=>''],hk()),'All source reviews required');
$f->execute($sender,'income_record',[...$income,'amount'=>'1.00'],hk());hd(fn()=>$h->execute($sender,'submit',$in,hk()),'Changed source invalidates preview');
$in['review_token']=$h->draft($sender)['token'];$key=hk();$id=$h->execute($sender,'submit',$in,$key)['id'];ha($h->execute($sender,'submit',$in,$key)['id']===$id,'Retry retains one submission');ha($h->detail($sender,$id)['status']==='pending','Submission precedes incoming acknowledgement');
hd(fn()=>$h->detail($third,$id),'Unrelated staff denied');hd(fn()=>$h->detail($otherAdmin,$id),'Non-owner administrator denied');$raw=$r->run('SELECT payload FROM finance_events WHERE id=?',[$id])->fetchColumn();
$f->execute($sender,'income_record',[...$income,'amount'=>'2.00'],hk());ha($r->run('SELECT payload FROM finance_events WHERE id=?',[$id])->fetchColumn()===$raw,'Original snapshot remains byte-for-byte');
$state=$h->detail($receiver,$id);$ack=['id'=>(string)$id,'version'=>$state['version'],'reviewed'=>'1','flag'=>'1','discrepancy'=>'Room not ready','comment'=>'Reviewed'];
hd(fn()=>$h->execute($sender,'ack',$ack,hk()),'Only incoming recipient acknowledges');hd(fn()=>$h->execute($receiver,'ack',[...$ack,'discrepancy'=>''],hk()),'Discrepancy needs description');
$h->execute($receiver,'ack',$ack,hk());ha($h->detail($owner,$id)['status']==='exception','Discrepancy remains open');$review=['id'=>(string)$id,'version'=>$h->detail($owner,$id)['version'],'comment'=>'Cleaning and inspection assigned'];hd(fn()=>$h->execute($otherAdmin,'review',$review,hk()),'Owner-only discrepancy review');$h->execute($owner,'review',$review,hk());ha($h->detail($owner,$id)['ack']['data']['discrepancy']==='Room not ready','Owner review preserves original discrepancy');
$moneyBefore=$f->summary('2044-04-04','2044-04-04');
$sent=$h->execute($sender,'cash_send',['recipient_id'=>(string)$receiver,'amount'=>'60.00','passed'=>'1'],hk())['id'];
$c=$f->cash();ha($c['custodians'][$sender]['held']===10300&&$c['custodians'][$sender]['available']===4300,'Pending cash reserved under sender');
hd(fn()=>$h->execute($sender,'cash_send',['recipient_id'=>(string)$third,'amount'=>'50.00','passed'=>'1'],hk()),'Pending cash cannot transfer twice');
$deposit=['amount'=>'50.00','fee'=>'0','date'=>'2044-04-04','reference'=>'HOVER-'.$tag];hd(fn()=>$f->execute($sender,'deposit_record',$deposit,hk()),'Deposit cannot consume pending transfer');
$state=$h->detail($receiver,$sent);$h->execute($receiver,'cash_dispute',['id'=>(string)$sent,'version'=>$state['version'],'comment'=>'Amount needs checking'],hk());ha($h->detail($owner,$sent)['status']==='disputed','Disputed cash remains open');
$h->execute($owner,'review',['id'=>(string)$sent,'version'=>$h->detail($owner,$sent)['version'],'comment'=>'Reconciliation requested'],hk());ha($f->cash()['custodians'][$sender]['available']===4300,'Owner review invents no custody movement');
$ackCash=['id'=>(string)$sent,'version'=>$h->detail($receiver,$sent)['version'],'received'=>'1'];hd(fn()=>$h->execute($receiver,'cash_ack',$ackCash,hk()),'Dispute acknowledgement requires explanation');
$key=hk();$ackCash['comment']='Full amount checked and received';$event=$h->execute($receiver,'cash_ack',$ackCash,$key)['id'];ha($h->execute($receiver,'cash_ack',$ackCash,$key)['id']===$event,'Cash acknowledgement replay posts once');
$c=$f->cash();ha($c['custodians'][$sender]['held']===4300&&$c['custodians'][$receiver]['held']===6000,'Acknowledged transfer splits custody exactly');$source=array_values(array_filter($c['rows'],fn($x)=>(string)$x['id']==='income:'.$receipt))[0];ha($source['due']==='2044-04-05'&&(int)$source['actor_id']===$sender,'Original cashier and deadline preserved');
$f->execute($receiver,'deposit_record',[...$deposit,'amount'=>'25.00','reference'=>'HREC-'.$tag],hk());ha($f->cash()['custodians'][$receiver]['held']===3500,'Receiver deposits received portion');
hd(fn()=>$f->execute($sender,'deposit_record',[...$deposit,'amount'=>'44.00','reference'=>'HFORGE-'.$tag],hk()),'Collector cannot spend receiver cash');
$t2=$h->execute($receiver,'cash_send',['recipient_id'=>(string)$third,'amount'=>'10.00','passed'=>'1'],hk())['id'];$h->execute($third,'cash_ack',['id'=>(string)$t2,'version'=>$h->detail($third,$t2)['version'],'received'=>'1'],hk());ha($f->cash()['custodians'][$third]['held']===1000,'Onward custody retains exact portions');
$before=(int)$r->run('SELECT COUNT(*) FROM finance_events')->fetchColumn();$ops=(int)$r->run('SELECT COUNT(*) FROM finance_operations')->fetchColumn();$r->db->exec("CREATE TRIGGER handover_test_fail BEFORE INSERT ON security_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'");
try{hd(fn()=>$h->execute($sender,'cash_send',['recipient_id'=>(string)$receiver,'amount'=>'1.00','passed'=>'1'],hk()),'Audit failure aborts transfer');}finally{$r->db->exec('DROP TRIGGER handover_test_fail');}
ha((int)$r->run('SELECT COUNT(*) FROM finance_events')->fetchColumn()===$before&&(int)$r->run('SELECT COUNT(*) FROM finance_operations')->fetchColumn()===$ops,'Audit rollback leaves no event or operation');
ha(hash('sha256',json_encode($r->run('SELECT * FROM payments ORDER BY id')->fetchAll()))===$payments,'Guest payments unchanged');
ha(count($h->register($owner)['rows'])>0,'Owner history register available');ha(isset($h->attention($owner)['cash']),'Owner attention available');

ha($f->summary('2044-04-04','2044-04-04')===$moneyBefore,'Custody transfers and zero-fee deposit leave revenue costs and receipts unchanged');
hd(fn()=>$h->execute($receiver,'cash_ack',[...$ackCash,'version'=>$h->detail($receiver,$sent)['version']],hk()),'Fresh operation cannot acknowledge cash twice');
$inactive=hu();$r->run('UPDATE security_accounts SET active=0 WHERE user_id=?',[$inactive]);
hd(fn()=>$h->execute($sender,'cash_send',['recipient_id'=>(string)$inactive,'amount'=>'1.00','passed'=>'1'],hk()),'Inactive recipients cannot receive new transfers');
hd(fn()=>$h->execute($sender,'cash_send',['recipient_id'=>(string)$receiver,'amount'=>'0','passed'=>'1'],hk()),'Zero transfer rejected');
hd(fn()=>$h->execute($sender,'cash_send',['recipient_id'=>(string)$receiver,'amount'=>'1.001','passed'=>'1'],hk()),'Fractional-pesewa transfer rejected');
hd(fn()=>$h->execute($receiver,'ack',$ack,hk()),'Stale acknowledgement cannot change recorded handover');

$findings=[];
$ageSender=hu();$ageReceiver=hu();(new FinanceService($a,'2044-04-01 12:00:00'))->execute($ageSender,'income_record',$income,hk());
$ageTransfer=$h->execute($ageSender,'cash_send',['recipient_id'=>(string)$ageReceiver,'amount'=>'50.00','passed'=>'1'],hk())['id'];
$h->execute($ageReceiver,'cash_ack',['id'=>(string)$ageTransfer,'version'=>$h->detail($ageReceiver,$ageTransfer)['version'],'received'=>'1'],hk());
$denied=false;try{$f->execute($ageReceiver,'deposit_record',['amount'=>'1.00','fee'=>'0','date'=>'2044-04-02','reference'=>'BEFORE-CUSTODY-'.$tag],hk());}catch(Throwable $e){$denied=true;}
if(!$denied)$findings[]='Deposit predating recipient custody was accepted';else ha(true,'Receiver cannot deposit before acquiring custody');
$exiting=hu();$r->run("INSERT INTO hr_staff(staff_no,status,user_id,exit_at,data,created_by) VALUES(?,'exit_scheduled',?,'2044-04-03',?,?)",['HO-'.$tag,$exiting,json_encode(['name'=>'Synthetic due exit']),$owner]);
if(in_array($exiting,array_map('intval',array_column($h->recipients($sender),'id'))))$findings[]='Due-exit recipient remains selectable';else ha(true,'Due-exit staff are excluded before login processes exit');
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard','100.00','vacant')",['Handover short '.$tag]);$room=(int)$r->db->lastInsertId();$r->run("INSERT INTO stay_room_settings(room_id,short_rate,short_enabled,ready_state) VALUES(?,'40.00',1,'ready')",[$room]);
$booking=(new \Tivoli\Service\StayService($a,$clock))->execute($sender,'checkin',['room_id'=>(string)$room,'guest_name'=>'Synthetic handover short','phone'=>'0000000000','gender'=>'Female','nationality'=>'Ghana','stay_type'=>'short','expected_rate'=>'40.00','amount'=>'40.00','payment_type'=>'cash','identity_verified'=>'1','payment_attested'=>'1','room_checked'=>'1'],hk())['booking_id'];
$items=$h->draft($sender)['snapshot']['sections']['guests']['items'];$guest=array_values(array_filter($items,fn($row)=>$row['link']==='occupancy.php?booking_id='.$booking))[0];
if(!str_contains($guest['detail'],'Short Stay')||!str_contains($guest['detail'],'04/04/2044 22:00:00'))$findings[]='Short stay type and due time missing from frozen summary';else ha(true,'Frozen short-stay summary includes type and departure deadline');
if($findings)throw new RuntimeException(implode('; ',$findings));
echo "TOTAL $count handover assertions passed.\n";


