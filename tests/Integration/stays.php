<?php
if(PHP_SAPI!=='cli')exit(1);
putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';
use Tivoli\Domain\Stay\StayRules as R;
$a=access_service();$r=$a->repo;if($r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')throw new RuntimeException('Unsafe fixture target.');
$admin=(int)$r->run("SELECT u.id FROM users u JOIN security_accounts a ON a.user_id=u.id WHERE u.email='admin@example.invalid' AND a.active=1")->fetchColumn();
$clerk=(int)$r->run("SELECT u.id FROM users u JOIN security_accounts a ON a.user_id=u.id WHERE u.email='clerk@example.invalid' AND a.active=1")->fetchColumn();
if(!$admin)throw new RuntimeException('Synthetic active administrator required.');
putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$admin);
$fixtureTag=bin2hex(random_bytes(5));
$r->run('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',['Synthetic occupancy cashier','stay-cashier-'.$fixtureTag.'@example.invalid',password_hash('Synthetic Cashier Passphrase 123!',PASSWORD_BCRYPT),'clerk']);$clerk=(int)$r->db->lastInsertId();$r->run('INSERT INTO security_accounts(user_id) VALUES(?)',[$clerk]);$r->run("INSERT INTO security_user_roles(user_id,role_id) SELECT ?,id FROM security_roles WHERE name='clerk'",[$clerk]);

$s=new \Tivoli\Service\StayService($a,'2026-11-06 14:00:00');$count=0;$tag=bin2hex(random_bytes(5));
function check(bool $ok,string $message):void{global $count;if(!$ok)throw new RuntimeException('FAIL '.$message);$count++;echo 'PASS '.$message."\n";}
function deny(callable $action,string $message):void{try{$action();}catch(Throwable $error){check(true,$message);return;}check(false,$message);}
function cleanData(int $room):array{global $r;$list=\Tivoli\Service\CleaningChecklist::current($r);$data=['room_id'=>(string)$room,'checklist_revision'=>$list['revision']];foreach($list['sections'] as $section)foreach($section['items'] as $item)$data['done_'.$item['id']]='1';return $data;}
function inspectionData(int $room):array{global $r;$list=\Tivoli\Service\CleaningChecklist::current($r,'inspection');$data=['room_id'=>(string)$room,'checklist_revision'=>$list['revision']];foreach($list['sections'] as $section)foreach($section['items'] as $item)$data['done_'.$item['id']]='1';return $data;}
function cmd(string $action,array $data=[],?int $actor=null,?string $key=null):array{
    global $s,$clerk,$r;
    if($action==='checkin'){
        $room=$r->run('SELECT r.rate,s.short_rate FROM rooms r JOIN stay_room_settings s ON s.room_id=r.id WHERE r.id=?',[$data['room_id']])->fetch();
        $rate=($data['stay_type']??'overnight')==='short'?$room['short_rate']:$room['rate'];
        $data+=['amount'=>$rate,'expected_rate'=>$rate,'payment_type'=>'cash','identity_verified'=>'1','payment_attested'=>'1','room_checked'=>'1'];
    }
    if($action==='refund')$data+=['method'=>'momo','actual_at'=>'2026-11-06 14:00:00','actual_payer'=>'Synthetic actual owner','completed'=>'1'];
    if($action==='allow_checkout')$data+=['expected_balance'=>(string)$s->snapshot($actor??$clerk,(int)$data['booking_id'])['bill']['balance'],'reason'=>'Synthetic unpaid departure authorization'];
    return $s->execute($actor??$clerk,$action,$data,$key??bin2hex(random_bytes(16)));
}
check(R::nights('2026-11-05 14:00:00','2026-11-06 12:00:00')===1,'noon equality remains one night');
check(R::nights('2026-11-05 14:00:00','2026-11-06 12:00:01')===2,'one second past noon adds night');
check(R::minor('0.01')===1&&R::decimal(-123)==='-1.23'&&R::line(125,1000)===1250,'exact money and fractional quantities');
deny(fn()=>R::minor('1e2'),'scientific money rejected');
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,?,?,'vacant')",['Synthetic stay '.$tag,'Standard','300.00']);$room=(int)$r->db->lastInsertId();
cmd('room_save',['id'=>(string)$room,'name'=>'Synthetic stay '.$tag,'type'=>'Standard','rate'=>'300.00','short_rate'=>'120.00','enabled'=>'1'],$admin);
check($r->run('SELECT type FROM rooms WHERE id=?',[$room])->fetchColumn()==='Standard','room setup accepts selected type without optional comment');
deny(fn()=>cmd('room_save',['id'=>(string)$room,'type'=>'Forged type '.$tag,'rate'=>'300.00','short_rate'=>'120.00'],$admin),'room type outside dropdown rejected server-side');
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,?,'100.00','vacant')",['Synthetic existing type '.$tag,'Legacy Annex']);
check(isset($s->roomTypes()['Legacy Annex'],$s->roomTypes()['Standard'],$s->roomTypes()['Suite']),'room type list preserves existing types and original choices');
deny(fn()=>cmd('room_save',['id'=>(string)$room,'reason'=>'Synthetic denied']),'clerk cannot change room master');
$guest=['room_id'=>(string)$room,'guest_name'=>'Synthetic guest '.$tag,'phone'=>'0000000000','gender'=>'Female','nationality'=>'Ghana','stay_type'=>'short'];
deny(fn()=>cmd('checkin',$guest),'uninspected vacancy is not saleable');

$inspectionOriginal=\Tivoli\Service\CleaningChecklist::current($r,'inspection');
check(count($inspectionOriginal['sections'][0]['items'])===6,'inspection defaults contain all six owner checks');
deny(fn()=>cmd('inspect',['room_id'=>(string)$room],$admin),'inspection without checklist rejected');
$oldInspection=inspectionData($room);
$inspectionConfig=['checklist_revision'=>$inspectionOriginal['revision'],'change'=>'add','section_id'=>'1','item_text'=>'Synthetic inspection check '.$tag];
deny(fn()=>cmd('inspection_checklist',$inspectionConfig,$clerk),'clerk cannot configure inspection checklist');
cmd('inspection_checklist',$inspectionConfig,$admin);
$inspectionChanged=\Tivoli\Service\CleaningChecklist::current($r,'inspection');$inspectionAdded=end($inspectionChanged['sections'][0]['items']);
check($inspectionChanged['revision']!==$inspectionOriginal['revision'],'inspection settings create an independent revision');
deny(fn()=>cmd('inspect',$oldInspection,$admin),'stale inspection form rejected');
$incomplete=inspectionData($room);unset($incomplete['done_'.$inspectionAdded['id']]);
deny(fn()=>cmd('inspect',$incomplete,$admin),'unchecked configured inspection item prevents release');
$r->db->exec("CREATE TRIGGER stay_test_inspection_audit_failure BEFORE INSERT ON security_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic inspection audit failure'");
try{deny(fn()=>cmd('inspect',inspectionData($room),$admin),'inspection audit failure rolls back release');}finally{$r->db->exec('DROP TRIGGER stay_test_inspection_audit_failure');}
check($r->run('SELECT ready_state FROM stay_room_settings WHERE room_id=?',[$room])->fetchColumn()==='unverified','audit failure preserves unreleased readiness');
$inspectionKey=bin2hex(random_bytes(16));$inspectionInput=inspectionData($room);cmd('inspect',$inspectionInput,$admin,$inspectionKey);cmd('inspect',$inspectionInput,$admin,$inspectionKey);
$inspectionEvidence=json_decode($r->run("SELECT details FROM security_audit WHERE action='stay.inspect' ORDER BY id DESC LIMIT 1")->fetchColumn(),true);
check($inspectionEvidence['reason']===''&&$inspectionEvidence['checklist']['sections']===$inspectionChanged['sections'],'inspection audit keeps exact completed checklist with optional notes');
cmd('inspection_checklist',['checklist_revision'=>$inspectionChanged['revision'],'change'=>'remove','item_id'=>$inspectionAdded['id']],$admin);
check(\Tivoli\Service\CleaningChecklist::current($r,'inspection')['sections']===$inspectionOriginal['sections']&&str_contains(json_encode($inspectionEvidence),$inspectionConfig['item_text']),'inspection removal preserves completed evidence');
deny(fn()=>cmd('inspect',inspectionData($room),$admin),'already released room cannot receive a second inspection completion');

$docBytes="%PDF-1.4\nSynthetic private guest identity";$vault=new \Tivoli\Infrastructure\HrVault(hr_config()['hr_key']);$guest['_identity']=['cipher'=>$vault->seal($docBytes,'guest-id:'.$clerk),'mime'=>'application/pdf','size'=>strlen($docBytes),'sha'=>hash('sha256',$docBytes),'owner'=>$clerk];
$key=bin2hex(random_bytes(16));$id=cmd('checkin',$guest,null,$key)['booking_id'];
check(cmd('checkin',$guest,null,$key)['booking_id']===$id,'retry returns original check-in');
deny(fn()=>cmd('checkin',[...$guest,'guest_name'=>'Changed'],null,$key),'same operation cannot be reused for different guest');
deny(fn()=>cmd('checkin',$guest),'occupied room cannot be checked in twice');
$snap=$s->snapshot($admin,$id);check($snap['booking']['due_at']==='2026-11-06 16:00:00'&&$snap['bill']['room']===12000,'short stay captures rate and exact two-hour limit');
check($snap['booking']['identity_available'] && !str_contains($r->run('SELECT cipher FROM stay_identity WHERE booking_id=?',[$id])->fetchColumn(),$docBytes),'optional identity stored encrypted');check(stay_download_identity($clerk,$id)['bytes']===$docBytes,'active guest ID downloaded through audited authorization');
check($snap['bill']['paid']===12000,'check-in records the actual first room payment');
cmd('extra_save',['name'=>'Synthetic extra '.$tag,'price'=>'10.00','category'=>'Drinks','tracked'=>'1','active'=>'1','low_stock'=>'3'],$admin);
$extra=(int)$r->run('SELECT id FROM extras WHERE name=?',['Synthetic extra '.$tag])->fetchColumn();
cmd('stock_in',['extra_id'=>(string)$extra,'qty'=>'5.00'],$admin);
deny(fn()=>cmd('stock_in',['extra_id'=>(string)$extra,'qty'=>'5','reason'=>'Denied']),'clerk stock management denied');
check($r->run('SELECT note FROM stock_movements WHERE extra_id=? ORDER BY id DESC LIMIT 1',[$extra])->fetchColumn()==='','stock receipt without reason retains movement with empty note');
cmd('stock_count',['extra_id'=>(string)$extra,'qty'=>'4.50'],$admin);
$adjustment=$r->run("SELECT qty,note FROM stock_movements WHERE extra_id=? AND movement_type='adjustment' ORDER BY id DESC LIMIT 1",[$extra])->fetch();
check($adjustment['qty']==='-0.50'&&$adjustment['note']==='','stock count without comment records the adjustment with an empty note');
cmd('stock_count',['extra_id'=>(string)$extra,'qty'=>'5','reason'=>'Synthetic recount'],$admin);
check($r->run('SELECT note FROM stock_movements WHERE extra_id=? ORDER BY id DESC LIMIT 1',[$extra])->fetchColumn()==='Synthetic recount','stock count preserves an optional comment');
cmd('extra',['booking_id'=>(string)$id,'extra_id'=>(string)$extra,'qty'=>'1.25','settlement'=>'now','method'=>'momo','reference'=>'']);
$b=$s->snapshot($admin,$id)['bill'];check($b['extras']===1250&&$b['paid']===13250&&$b['balance']===0,'pay-now extra records equal charge and final cashier payment');
check($r->run('SELECT stock_qty FROM extras WHERE id=?',[$extra])->fetchColumn()==='3.75','fractional stock deducts exactly');
$sale=(int)$r->run('SELECT MAX(id) FROM booking_extras WHERE booking_id=?',[$id])->fetchColumn();check((int)$r->run("SELECT ref_id FROM stock_movements WHERE extra_id=? AND movement_type='out' ORDER BY id DESC LIMIT 1",[$extra])->fetchColumn()===$sale,'stock movement links actual captured sale ID');
deny(fn()=>cmd('extra',['booking_id'=>(string)$id,'extra_id'=>(string)$extra,'qty'=>'9','settlement'=>'room']),'overselling rejected');
cmd('room_save',['id'=>(string)$room,'name'=>'Synthetic stay '.$tag,'type'=>'Standard','rate'=>'350.00','short_rate'=>'150.00','enabled'=>'1','reason'=>'Synthetic rate revision'],$admin);
check($s->snapshot($admin,$id)['bill']['room']===12000,'standing tariff change preserves active stay rate');
cmd('incidental',['booking_id'=>(string)$id,'amount'=>'20.00','description'=>'Synthetic laundry evidence','reason'=>'Synthetic requested service']);
$q=(int)$r->run('SELECT MAX(id) FROM stay_incidentals WHERE booking_id=?',[$id])->fetchColumn();check($s->snapshot($admin,$id)['bill']['incidental']===0,'pending incidental excluded');
deny(fn()=>cmd('review_incidental',['booking_id'=>(string)$id,'id'=>(string)$q,'decision'=>'approved','reason'=>'Denied']),'clerk incidental approval denied');
cmd('review_incidental',['booking_id'=>(string)$id,'id'=>(string)$q,'decision'=>'approved','reason'=>'Synthetic admin approval'],$admin);check($s->snapshot($admin,$id)['bill']['balance']===2000,'approved incidental counts once');
deny(fn()=>cmd('checkout',['booking_id'=>(string)$id,'expected_balance'=>'2000','extras_checked'=>'1','keys_returned'=>'1']),'unpaid checkout blocked');
deny(fn()=>cmd('allow_checkout',['booking_id'=>(string)$id]),'clerk cannot approve unpaid checkout');
cmd('allow_checkout',['booking_id'=>(string)$id],$admin);check($s->snapshot($clerk,$id)['bill']['approval'],'designated owner exception available to clerk');
cmd('extra',['booking_id'=>(string)$id,'extra_id'=>(string)$extra,'qty'=>'1','settlement'=>'room']);check(!$s->snapshot($clerk,$id)['bill']['approval'],'increased debt exceeds prior approval');
deny(fn()=>cmd('clear_balance',['booking_id'=>(string)$id,'expected_balance'=>'14000','method'=>'cash']),'stale confirmation balance rejected');
$balance=$s->snapshot($clerk,$id)['bill']['balance'];$payKey=bin2hex(random_bytes(16));$pay=['booking_id'=>(string)$id,'expected_balance'=>(string)$balance,'method'=>'momo','reference'=>''];cmd('clear_balance',$pay,null,$payKey);cmd('clear_balance',$pay,null,$payKey);
check($s->snapshot($clerk,$id)['bill']['balance']===0,'full settlement retries do not duplicate payment');
$out=['booking_id'=>(string)$id,'expected_balance'=>'0','extras_checked'=>'1','keys_returned'=>'1','reason'=>''];$outKey=bin2hex(random_bytes(16));cmd('checkout',$out,null,$outKey);cmd('checkout',$out,null,$outKey);
check($r->run('SELECT status FROM rooms WHERE id=?',[$room])->fetchColumn()==='dirty','checkout sends room to cleaning');
deny(fn()=>stay_download_identity($clerk,$id),'clerk cannot download departed guest ID');check(stay_download_identity($admin,$id)['bytes']===$docBytes,'administrator retains authorized departed ID access');
deny(fn()=>cmd('payment',['booking_id'=>(string)$id,'amount'=>'1.00','method'=>'cash']),'payment after checkout rejected');
$future=new \Tivoli\Service\StayService($a,'2027-01-01 10:00:00');check($future->snapshot($admin,$id)['bill']['balance']===0&&$future->snapshot($admin,$id)['bill']['room']===12000,'departed bill frozen at actual departure');

$checklist=\Tivoli\Service\CleaningChecklist::current($r);
$originalList=$checklist;
$clean=['room_id'=>(string)$room,'checklist_revision'=>$checklist['revision']];
deny(fn()=>cmd('clean',$clean,$admin),'incomplete cleaning checklist rejected server-side');
$config=['checklist_revision'=>$checklist['revision'],'change'=>'add','section_id'=>'1','item_text'=>'Synthetic extra cleaning check '.$tag];
deny(fn()=>cmd('cleaning_checklist',$config,$clerk),'clerk cannot change cleaning settings');
cmd('cleaning_checklist',$config,$admin);
$changed=\Tivoli\Service\CleaningChecklist::current($r);$newItem=end($changed['sections'][0]['items']);
check($changed['revision']!==$checklist['revision']&&$newItem['text']===$config['item_text'],'administrator adds a versioned cleaning item');
foreach($checklist['sections'] as $section)foreach($section['items'] as $item)$clean['done_'.$item['id']]='1';
deny(fn()=>cmd('clean',$clean,$admin),'stale cleaning checklist rejected after configuration change');
deny(fn()=>cmd('cleaning_checklist',$config,$admin),'stale settings update cannot overwrite another revision');
$clean['checklist_revision']=$changed['revision'];$clean['done_'.$newItem['id']]='1';
$r->db->exec("CREATE TRIGGER stay_test_clean_audit_failure BEFORE INSERT ON security_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic cleaning audit failure'");
try{deny(fn()=>cmd('clean',$clean,$admin),'failed cleaning audit rolls back completion');}finally{$r->db->exec('DROP TRIGGER stay_test_clean_audit_failure');}
check($r->run('SELECT ready_state FROM stay_room_settings WHERE room_id=?',[$room])->fetchColumn()==='cleaning','audit failure leaves room in cleaning');
$r->db->exec("CREATE TRIGGER stay_test_config_audit_failure BEFORE INSERT ON security_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic config audit failure'");
try{deny(fn()=>cmd('cleaning_checklist',[...$config,'checklist_revision'=>$changed['revision'],'item_text'=>'Synthetic rejected audit item'],$admin),'failed configuration audit rejects settings change');}finally{$r->db->exec('DROP TRIGGER stay_test_config_audit_failure');}
check(\Tivoli\Service\CleaningChecklist::current($r)['revision']===$changed['revision'],'failed settings save preserves authoritative revision');
$cleanKey=bin2hex(random_bytes(16));cmd('clean',$clean,$admin,$cleanKey);cmd('clean',$clean,$admin,$cleanKey);
$saved=json_decode($r->run("SELECT details FROM security_audit WHERE action='stay.clean' ORDER BY id DESC LIMIT 1")->fetchColumn(),true);
check($saved['reason']===''&&$saved['checklist']['revision']===$changed['revision'],'cleaning retains exact checklist revision and allows empty notes');
cmd('cleaning_checklist',['checklist_revision'=>$changed['revision'],'change'=>'remove','item_id'=>$newItem['id']],$admin);
$afterRemoval=\Tivoli\Service\CleaningChecklist::current($r);
check($afterRemoval['sections']===$originalList['sections'],'removing item affects future checklists only');
check(str_contains(json_encode($saved),$config['item_text']),'completed checklist keeps removed item evidence');
deny(fn()=>cmd('clean',[...$clean,'checklist_revision'=>$afterRemoval['revision']],$admin),'new cleaning submission cannot overwrite awaiting-inspection room');
check($r->run('SELECT ready_state FROM stay_room_settings WHERE room_id=?',[$room])->fetchColumn()==='awaiting_inspection','cleaning completion is not inspection');
cmd('inspect',inspectionData($room),$admin);
cmd('fault',['room_id'=>(string)$room,'priority'=>'Critical','owner'=>'Synthetic maintenance','reason'=>'Synthetic fault'],$admin);deny(fn()=>cmd('checkin',$guest),'fault block prevents resale');
$fault=(int)$r->run('SELECT MAX(id) FROM stay_faults WHERE room_id=?',[$room])->fetchColumn();cmd('repair',['id'=>(string)$fault,'reason'=>'Synthetic repair tested'],$admin);check($r->run('SELECT status FROM rooms WHERE id=?',[$room])->fetchColumn()==='vacant','repair immediately restores prior ready room');deny(fn()=>cmd('release_fault',['id'=>(string)$fault,'reason'=>'Removed step'],$admin),'old repair release handler rejected');
$legacyBefore=$r->run('SELECT * FROM payments WHERE booking_id=? ORDER BY id',[$id])->fetchAll();deny(fn()=>$r->run('DELETE FROM payments WHERE booking_id=?',[$id]),'payment deletion blocked at database boundary');check($legacyBefore===$r->run('SELECT * FROM payments WHERE booking_id=? ORDER BY id',[$id])->fetchAll(),'retained payment rows unchanged');
$r->db->exec("CREATE TRIGGER stay_test_audit_failure BEFORE INSERT ON security_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'");
try{deny(fn()=>cmd('checkin',$guest),'audit failure rolls back check-in');}finally{$r->db->exec('DROP TRIGGER stay_test_audit_failure');}
check((int)$r->run("SELECT COUNT(*) FROM bookings WHERE room_id=? AND status='active'",[$room])->fetchColumn()===0,'failed audit leaves no active booking');
check($r->run('SELECT status FROM rooms WHERE id=?',[$room])->fetchColumn()==='vacant','failed audit leaves room unchanged');
// Prospective refunds preserve source receipts and work for departed overpayments.
$over=cmd('checkin',[...$guest,'guest_name'=>'Synthetic overpayment']);$overId=$over['booking_id'];
cmd('payment',['booking_id'=>(string)$overId,'amount'=>'50.00','method'=>'cash','reference'=>'SYNTHETIC-OVER']);
$overBill=$s->snapshot($admin,$overId)['bill'];check($overBill['balance']===-5000,'overpayment remains visible');
$receipt=(int)$r->run('SELECT MAX(id) FROM payments WHERE booking_id=?',[$overId])->fetchColumn();
cmd('checkout',['booking_id'=>(string)$overId,'expected_balance'=>'-5000','extras_checked'=>'1','keys_returned'=>'1']);
deny(fn()=>cmd('refund',['booking_id'=>(string)$overId,'payment_id'=>(string)$receipt,'amount'=>'50.00','proof'=>'SYNTHETIC','reason'=>'Refund']),'clerk refund denied');
deny(fn()=>cmd('refund',['booking_id'=>(string)$overId,'payment_id'=>(string)$receipt,'amount'=>'51.00','proof'=>'SYNTHETIC','reason'=>'Refund'],$admin),'refund cannot exceed overpayment');
cmd('refund',['booking_id'=>(string)$overId,'payment_id'=>(string)$receipt,'amount'=>'50.00','proof'=>'SYNTHETIC-REFUND-'.$tag,'reason'=>'Synthetic executed return'],$admin);
check($s->snapshot($admin,$overId)['bill']['balance']===0&&$r->run('SELECT amount FROM payments WHERE id=?',[$receipt])->fetchColumn()==='50.00','post-departure refund retains original payment and clears credit');
cmd('clean',cleanData($room),$admin);cmd('inspect',inspectionData($room),$admin);
// New extra purchases must be exact pesewas; old fractional source values stay intact.
$next=cmd('checkin',[...$guest,'guest_name'=>'Synthetic exact line'])['booking_id'];
$r->run("INSERT INTO extras(name,price,stock_qty,active) VALUES(?,'0.10','10.00',1)",['Synthetic fractional cent '.$tag]);$tiny=(int)$r->db->lastInsertId();
deny(fn()=>cmd('extra',['booking_id'=>(string)$next,'extra_id'=>(string)$tiny,'qty'=>'0.15','settlement'=>'room']),'new fractional-pesewa sale rejected rather than inventing rounding');
check($s->bill($r->run('SELECT * FROM bookings WHERE id=?',[$next])->fetch(),'2026-11-07 12:00:01')['room']===15000,'short stay never converts automatically after overrun');
cmd('checkout',['booking_id'=>(string)$next,'expected_balance'=>'0','extras_checked'=>'1','keys_returned'=>'1']);
cmd('clean',cleanData($room),$admin);cmd('inspect',inspectionData($room),$admin);
file_put_contents(dirname(__DIR__,2).'/.local-setup/stays-http-fixture.json',json_encode(['room_id'=>$room,'booking_id'=>$id,'clerk_email'=>'stay-cashier-'.$fixtureTag.'@example.invalid','clerk_password'=>'Synthetic Cashier Passphrase 123!','extra_id'=>$extra],JSON_THROW_ON_ERROR));

$accounts=$s->folios($admin);
check($accounts['filters']===['start'=>'2026-11-02','end'=>'2026-11-08'],'guest account defaults to current Monday-Sunday UTC week');
check(in_array($id,array_column($accounts['rows'],'id')),'current-week account list includes overlapping departed stay');
check(!array_key_exists('bill',$accounts['rows'][0]),'account directory does not calculate or expose list balances');
$filtered=$s->folios($clerk,0,['start'=>'2026-11-06','end'=>'2026-11-06']);
check(in_array($id,array_column($filtered['rows'],'id')),'custom inclusive dates include exact arrival and departure day');
check(!in_array($id,array_column($s->folios($admin,0,['start'=>'2026-11-07','end'=>'2026-11-08'])['rows'],'id')),'departed stay outside selected period excluded');
deny(fn()=>$s->folios($admin,0,['start'=>'2026-02-30','end'=>'2026-03-01']),'impossible account filter date rejected');
deny(fn()=>$s->folios($admin,0,['start'=>'2026-11-08','end'=>'2026-11-02']),'reversed date filter rejected');
deny(fn()=>$s->folios($admin,0,['start'=>['2026-11-02'],'end'=>'2026-11-08']),'array date input rejected');
$pages=[];for($i=0;$i<52;$i++){$r->run("INSERT INTO bookings(room_id,guest_name,contact,checkin_at,checkout_at,status,rate_per_night) VALUES(?,?,?,'2001-01-03 12:00:00',NULL,'cancelled',NULL)",[$room,'Synthetic account pagination '.$tag.' '.$i,'0000']);$pages[]=(int)$r->db->lastInsertId();}
$page1=$s->folios($admin,0,['start'=>'2001-01-03','end'=>'2001-01-03']);$page2=$s->folios($admin,$page1['next_before'],$page1['filters']);
check(count($page1['rows'])===50&&count(array_intersect($pages,[...array_column($page1['rows'],'id'),...array_column($page2['rows'],'id')]))===52,'date-filtered pagination retains all 52 synthetic accounts');
check(!array_intersect(array_column($page1['rows'],'id'),array_column($page2['rows'],'id')),'account cursor pages do not repeat rows');
$historical=$s->folios($admin,0,['range'=>'all']);check($historical['filters']===['range'=>'all']&&in_array(end($pages),array_column($historical['rows'],'id')),'All Dates exposes historical accounts without guessed balances');

$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard','100.00','vacant')",['Synthetic fault attachment '.$tag]);$faultRoom=(int)$r->db->lastInsertId();
$faultInput=['room_id'=>(string)$faultRoom,'owner'=>'Synthetic maintenance','priority'=>'Routine'];
$emptyFault=cmd('fault',$faultInput,$admin)['fault_id'];
check($r->run('SELECT reason FROM stay_faults WHERE id=?',[$emptyFault])->fetchColumn()==='','fault description and attachment can both be omitted');
check($r->run('SELECT ready_state FROM stay_room_settings WHERE room_id=?',[$faultRoom])->fetchColumn()==='unverified','routine fault does not change room readiness');
$faultBytes="%PDF-1.4\nSynthetic room fault evidence\n%%EOF";
$faultFile=\Tivoli\Service\FaultAttachment::validate($faultBytes,'fault.pdf');
$faultKey=bin2hex(random_bytes(16));$faultUpload=[...$faultInput,'_fault_file'=>$faultFile];$attachmentFault=cmd('fault',$faultUpload,$admin,$faultKey)['fault_id'];
check(cmd('fault',$faultUpload,$admin,$faultKey)['fault_id']===$attachmentFault,'fault upload retry returns same fault');
check(\Tivoli\Service\FaultAttachment::download($a,$admin,$attachmentFault)['bytes']===$faultBytes,'administrator downloads exact protected fault attachment');
deny(fn()=>\Tivoli\Service\FaultAttachment::download($a,$clerk,$attachmentFault),'clerk cannot download private fault attachment');
deny(fn()=>\Tivoli\Service\FaultAttachment::validate('<html>not a PDF</html>','fake.pdf'),'mismatched fault attachment content rejected');
deny(fn()=>\Tivoli\Service\FaultAttachment::validate(str_repeat('x',5*1024*1024+1),'large.pdf'),'oversized fault attachment rejected');
$fileMeta=json_decode($r->run("SELECT details FROM security_audit WHERE action='stay.fault_attachment_saved' AND target_id=?",[(string)$attachmentFault])->fetchColumn(),true);
$filePath=\Tivoli\Service\FaultAttachment::directory().'/'.$fileMeta['storage_key'].'.enc';
check(!str_contains(file_get_contents($filePath),$faultBytes),'fault file is encrypted at rest');
check(!str_contains(json_encode($fileMeta),$faultBytes)&&!isset($fileMeta['bytes']),'fault audit records metadata without document bytes');
$beforeFaults=(int)$r->run('SELECT COUNT(*) FROM stay_faults WHERE room_id=?',[$faultRoom])->fetchColumn();
$r->db->exec("CREATE TRIGGER stay_test_fault_audit_failure BEFORE INSERT ON security_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic fault audit failure'");
try{deny(fn()=>cmd('fault',$faultUpload,$admin),'attachment registry audit failure rolls back fault');}finally{$r->db->exec('DROP TRIGGER stay_test_fault_audit_failure');}
check((int)$r->run('SELECT COUNT(*) FROM stay_faults WHERE room_id=?',[$faultRoom])->fetchColumn()===$beforeFaults,'failed upload audit creates no fault row');

cmd('repair',['id'=>(string)$emptyFault],$admin);
check($r->run('SELECT repair_reason FROM stay_faults WHERE id=?',[$emptyFault])->fetchColumn()==='','repair accepts empty optional Comment without file');
$repairBytes="%PDF-1.4\nSynthetic separate repair evidence\n%%EOF";$repairFile=\Tivoli\Service\FaultAttachment::validate($repairBytes,'repair.pdf');
$repairKey=bin2hex(random_bytes(16));$repairInput=['id'=>(string)$attachmentFault,'_fault_file'=>$repairFile];cmd('repair',$repairInput,$admin,$repairKey);cmd('repair',$repairInput,$admin,$repairKey);
check(\Tivoli\Service\FaultAttachment::download($a,$admin,$attachmentFault,'repair')['bytes']===$repairBytes,'repair evidence downloads exactly');
check(\Tivoli\Service\FaultAttachment::download($a,$admin,$attachmentFault)['bytes']===$faultBytes,'repair evidence preserves original fault attachment');
check($r->run('SELECT status FROM stay_faults WHERE id=?',[$attachmentFault])->fetchColumn()==='closed'&&$r->run('SELECT ready_state FROM stay_room_settings WHERE room_id=?',[$faultRoom])->fetchColumn()==='unverified','repair closes fault without inventing an inspection');
check((int)$r->run("SELECT COUNT(*) FROM security_audit WHERE action='stay.repair_attachment_saved' AND target_id=?",[(string)$attachmentFault])->fetchColumn()===1,'repair retry does not duplicate attachment registry');
deny(fn()=>\Tivoli\Service\FaultAttachment::download($a,$clerk,$attachmentFault,'repair'),'clerk cannot download repair evidence');
$failedRepair=cmd('fault',$faultInput,$admin)['fault_id'];
$r->db->exec("CREATE TRIGGER stay_test_repair_audit_failure BEFORE INSERT ON security_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic repair audit failure'");
try{deny(fn()=>cmd('repair',['id'=>(string)$failedRepair,'_fault_file'=>$repairFile],$admin),'repair evidence audit failure rolls back repair');}finally{$r->db->exec('DROP TRIGGER stay_test_repair_audit_failure');}
check($r->run('SELECT status FROM stay_faults WHERE id=?',[$failedRepair])->fetchColumn()==='open','audit failure retains open fault');
$httpFixture=json_decode(file_get_contents(dirname(__DIR__,2).'/.local-setup/stays-http-fixture.json'),true);$httpFixture['fault_id']=$failedRepair;file_put_contents(dirname(__DIR__,2).'/.local-setup/stays-http-fixture.json',json_encode($httpFixture,JSON_THROW_ON_ERROR));

// Critical-only faults preserve readiness and do not displace an occupant.
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard','100.00','vacant')",['Synthetic critical lifecycle '.$tag]);$lifecycleRoom=(int)$r->db->lastInsertId();cmd('inspect',inspectionData($lifecycleRoom),$admin);
$routine=cmd('fault',['room_id'=>(string)$lifecycleRoom,'priority'=>'Routine','owner'=>'Synthetic technician'],$admin)['fault_id'];
$urgent=cmd('fault',['room_id'=>(string)$lifecycleRoom,'priority'=>'Urgent','owner'=>'Synthetic technician'],$admin)['fault_id'];
check($r->run('SELECT ready_state FROM stay_room_settings WHERE room_id=?',[$lifecycleRoom])->fetchColumn()==='ready','routine and urgent faults keep ready room saleable');
deny(fn()=>cmd('fault',['room_id'=>(string)$lifecycleRoom,'priority'=>'critical','owner'=>'Synthetic technician'],$admin),'priority outside exact allowlist rejected');
$lifeGuest=cmd('checkin',[...$guest,'room_id'=>(string)$lifecycleRoom,'stay_type'=>'overnight'])['booking_id'];
$occupiedFault=cmd('fault',['room_id'=>(string)$lifecycleRoom,'priority'=>'Critical','owner'=>'Synthetic technician'],$admin)['fault_id'];
check($r->run('SELECT status FROM rooms WHERE id=?',[$lifecycleRoom])->fetchColumn()==='occupied','critical fault preserves occupied room and guest');
cmd('repair',['id'=>(string)$occupiedFault],$admin);
check($r->run('SELECT status FROM rooms WHERE id=?',[$lifecycleRoom])->fetchColumn()==='occupied'&&$r->run('SELECT ready_state FROM stay_room_settings WHERE room_id=?',[$lifecycleRoom])->fetchColumn()==='ready','repair clears critical block while preserving occupant');
cmd('checkout',['booking_id'=>(string)$lifeGuest,'expected_balance'=>'0','extras_checked'=>'1','keys_returned'=>'1']);
$critical1=cmd('fault',['room_id'=>(string)$lifecycleRoom,'priority'=>'Critical','owner'=>'Synthetic technician'],$admin)['fault_id'];
$critical2=cmd('fault',['room_id'=>(string)$lifecycleRoom,'priority'=>'Critical','owner'=>'Synthetic technician'],$admin)['fault_id'];
cmd('repair',['id'=>(string)$critical1],$admin);
check($r->run('SELECT ready_state FROM stay_room_settings WHERE room_id=?',[$lifecycleRoom])->fetchColumn()==='blocked','second critical fault retains block after first repair');
cmd('repair',['id'=>(string)$critical2],$admin);
check($r->run('SELECT ready_state FROM stay_room_settings WHERE room_id=?',[$lifecycleRoom])->fetchColumn()==='cleaning'&&$r->run('SELECT status FROM rooms WHERE id=?',[$lifecycleRoom])->fetchColumn()==='dirty','last critical repair restores cleaning instead of declaring dirty room ready');
cmd('clean',cleanData($lifecycleRoom),$admin);cmd('inspect',inspectionData($lifecycleRoom),$admin);
check($r->run('SELECT ready_state FROM stay_room_settings WHERE room_id=?',[$lifecycleRoom])->fetchColumn()==='ready','open routine faults do not block ordinary cleaning inspection');
cmd('room_audit',['room_id'=>(string)$lifecycleRoom,'result'=>'pass'],$admin);
$roomAudit=$r->run('SELECT owner,reason FROM stay_room_audits WHERE room_id=? ORDER BY id DESC LIMIT 1',[$lifecycleRoom])->fetch();check($roomAudit['owner']===''&&$roomAudit['reason']==='','room audit accepts optional owner and Comment');
cmd('room_audit',['room_id'=>(string)$lifecycleRoom,'result'=>'unsafe'],$admin);
check($r->run('SELECT ready_state FROM stay_room_settings WHERE room_id=?',[$lifecycleRoom])->fetchColumn()==='blocked','unsafe room audit still creates critical block');
$unsafeFault=(int)$r->run('SELECT MAX(id) FROM stay_faults WHERE room_id=?',[$lifecycleRoom])->fetchColumn();cmd('repair',['id'=>(string)$unsafeFault],$admin);
check($r->run('SELECT ready_state FROM stay_room_settings WHERE room_id=?',[$lifecycleRoom])->fetchColumn()==='ready','repair closes unsafe audit fault without extra release step');
$legacyFault=cmd('fault',['room_id'=>(string)$lifecycleRoom,'priority'=>'Critical','owner'=>'Synthetic technician'],$admin)['fault_id'];
$r->run("UPDATE stay_faults SET status='awaiting_inspection',repair_reason='Synthetic original repair',repair_by=?,repair_at='2026-11-06 12:00:00' WHERE id=?",[$admin,$legacyFault]);
cmd('repair',['id'=>(string)$legacyFault],$admin);$legacy=$r->run('SELECT * FROM stay_faults WHERE id=?',[$legacyFault])->fetch();
check($legacy['status']==='closed'&&$legacy['repair_reason']==='Synthetic original repair'&&$legacy['inspected_by']===null,'previous pending repair closes without rewriting repair evidence or inventing inspector');
// Non-room weekly audit and versioned follow-up.
$areas=\Tivoli\Service\AuditAreas::current($r);$chosen=$areas['entries'][0];
$areaInput=['area_id'=>$chosen['id'],'area_revision'=>$areas['revision'],'result'=>'corrective'];
deny(fn()=>cmd('area_audit',$areaInput,$clerk),'clerk cannot submit area audit');
deny(fn()=>cmd('area_audit',['area_revision'=>$areas['revision'],'result'=>'pass'],$admin),'area audit requires an active list entry');
deny(fn()=>cmd('area_audit',[...$areaInput,'result'=>'not_applicable'],$admin),'invalid overall area result rejected');
deny(fn()=>cmd('area_audit',[...$areaInput,'area_revision'=>'stale'],$admin),'stale area list rejected');
cmd('area_audit',[...$areaInput,'audit_date'=>'2099-01-01'],$admin);
$areaEvent=$r->run("SELECT id,target_id FROM security_audit WHERE action='stay.area_audit' ORDER BY id DESC LIMIT 1")->fetch();$areaKey=$areaEvent['target_id'];
$areaList=$s->areaAudits($admin,['range'=>'all','audit_key'=>$areaKey]);$areaRow=$areaList['rows'][0];
check($areaRow['data']['week']==='2026-11-02'&&$areaRow['data']['status']==='open'&&$areaRow['data']['owner']==='','simple area audit uses server week and opens corrective follow-up');
$weekly=new \Tivoli\Service\WeeklyAuditService($a,'2026-11-06 14:00:00');$task=$weekly->association('area',$areaKey);
deny(fn()=>cmd('area_followup',['audit_key'=>$areaKey,'version'=>(string)$areaEvent['id'],'status'=>'completed'],$admin),'old area writer cannot bypass verified follow-up');
$version=fn()=>['id'=>(string)$task,'version'=>$weekly->detail($admin,$task)['version']];
$weekly->execute($admin,'assign',$version()+['assignee'=>(string)$clerk,'due_date'=>'2026-11-07'],bin2hex(random_bytes(16)));
$done=$version();$doneKey=bin2hex(random_bytes(16));$weekly->execute($clerk,'done',$done,$doneKey);$weekly->execute($clerk,'done',$done,$doneKey);
check($weekly->detail($admin,$task)['status']==='review','assigned staff completion awaits owner verification');
deny(fn()=>$weekly->execute($admin,'close',$done+['verified'=>'1'],bin2hex(random_bytes(16))),'stale audit review rejected');
$weekly->execute($admin,'close',$version()+['verified'=>'1'],bin2hex(random_bytes(16)));
$history=$s->areaAudits($admin,['range'=>'all','history'=>$areaKey]);check(count($history['history'])===1&&$weekly->detail($admin,$task)['status']==='closed','original area audit retained alongside append-only verified follow-up');
deny(fn()=>$weekly->execute($admin,'assign',$version()+['assignee'=>(string)$clerk,'due_date'=>'2026-11-07'],bin2hex(random_bytes(16))),'completed audit cannot be silently reopened');
deny(fn()=>$s->areaAudits($clerk,['range'=>'all']),'clerk cannot view restricted area audit register');
$beforeAreas=(int)$r->run("SELECT COUNT(*) FROM security_audit WHERE action='stay.area_audit'")->fetchColumn();
$r->db->exec("CREATE TRIGGER stay_test_area_audit_failure BEFORE INSERT ON security_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic area audit failure'");
try{deny(fn()=>cmd('area_audit',$areaInput,$admin),'failed area audit storage rejects submission');}finally{$r->db->exec('DROP TRIGGER stay_test_area_audit_failure');}
check((int)$r->run("SELECT COUNT(*) FROM security_audit WHERE action='stay.area_audit'")->fetchColumn()===$beforeAreas,'failed area audit leaves no completion event');

$originalArea=$chosen['name'];$areaSnapshot=$areaRow['data']['area'];$catalogue=\Tivoli\Service\AuditAreas::current($r);
deny(fn()=>cmd('area_catalogue',['area_revision'=>$catalogue['revision'],'area_id'=>$chosen['id'],'name'=>'Denied','active'=>'1'],$clerk),'clerk cannot configure areas');
cmd('area_catalogue',['area_revision'=>$catalogue['revision'],'area_id'=>$chosen['id'],'name'=>'Synthetic Renamed Area','active'=>'1'],$admin);
$renamed=\Tivoli\Service\AuditAreas::current($r);check($renamed['entries'][0]['name']==='Synthetic Renamed Area','administrator renames catalogue area');
check($s->areaAudits($admin,['range'=>'all','audit_key'=>$areaKey])['rows'][0]['data']['area']===$areaSnapshot,'past audit preserves original area name');
deny(fn()=>cmd('area_catalogue',['area_revision'=>$catalogue['revision'],'area_id'=>$chosen['id'],'name'=>'Stale rename','active'=>'1'],$admin),'stale area settings change rejected');
cmd('area_catalogue',['area_revision'=>$renamed['revision'],'area_id'=>$chosen['id'],'name'=>$originalArea,'active'=>'0'],$admin);
$inactive=\Tivoli\Service\AuditAreas::current($r);
deny(fn()=>cmd('area_audit',[...$areaInput,'area_revision'=>$inactive['revision']],$admin),'inactive area cannot be selected for new audit');
cmd('area_catalogue',['area_revision'=>$inactive['revision'],'area_id'=>$chosen['id'],'name'=>$originalArea,'active'=>'1'],$admin);
check(\Tivoli\Service\AuditAreas::current($r)['entries'][0]['active'],'administrator reactivates area without replacing its identity');
echo "TOTAL $count occupancy integration assertions passed.\n";
