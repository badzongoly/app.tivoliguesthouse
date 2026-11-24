<?php
if (PHP_SAPI !== 'cli') exit(1);
putenv('TIVOLI_TEST_MODE=1');
require dirname(__DIR__, 2).'/bootstrap/security.php';
use Tivoli\Repository\OwnerReports;

if (!class_exists(OwnerReports::class)) {
    fwrite(STDERR, "Owner Reports repository is missing.\n");
    exit(1);
}
function check(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException('FAIL '.$label);
    echo "PASS $label\n";
}
$asOf = new DateTimeImmutable('2047-01-05 12:00:00', new DateTimeZone('UTC'));
$filters = OwnerReports::filters(['view'=>'extras', 'start'=>'2046-12-30', 'end'=>'2047-01-05'], $asOf);
check($filters['until']==='2047-01-05 12:00:00' && $filters['from']==='2046-12-30 00:00:00', 'half-open range crosses year and clips to report time');
foreach ([['start'=>['bad']], ['view'=>'extras','start'=>'2047-02-30'], ['view'=>'room','start'=>'2046-01-01'], ['view'=>'extras','item'=>'-1'], ['page'=>'99999999999']] as $bad) {
    try { OwnerReports::filters($bad, $asOf); } catch (InvalidArgumentException $e) { continue; }
    throw new RuntimeException('Invalid filters accepted');
}
check(true, 'malformed filters and oversized ranges rejected');

$r = access_service()->repo;
if ($r->run('SELECT @@port')->fetchColumn() != 3307 || $r->run('SELECT DATABASE()')->fetchColumn() !== 'tivoli_auth_test'
    || $r->run('SELECT marker FROM security_test_marker')->fetchColumn() !== 'synthetic-auth-only-v1') {
    throw new RuntimeException('Unsafe report test target.');
}
$repo = new OwnerReports($r);
$day = new DateTimeImmutable('2030-01-03',new DateTimeZone('UTC'));
while ($r->run('SELECT 1 FROM payments WHERE created_at>=? AND created_at<? LIMIT 1',
    [$day->format('Y-m-d'),$day->modify('+7 days')->format('Y-m-d')])->fetchColumn()) {
    $day = $day->modify('+7 days');
}
$start = $day->format('Y-m-d');
$end = $day->modify('+6 days')->format('Y-m-d');
$next = $day->modify('+7 days')->format('Y-m-d');
$asOf = $day->modify('+8 days')->setTime(12,0);
$tag = bin2hex(random_bytes(5));
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard','100.00','occupied')",['Synthetic owner report '.$tag]);
$room = (int)$r->db->lastInsertId();
$r->run("INSERT INTO bookings(room_id,rate_per_night,guest_name,checkin_at,status) VALUES(?,'100.00','Private synthetic guest',?,'active')",[$room,$start.' 00:00:00']);
$booking = (int)$r->db->lastInsertId();
$r->run("INSERT INTO stay_details VALUES(?,'overnight','','','', '',0,0,0,NULL,1,?)",[$booking,$start.' 00:00:00']);
$payments = [];
foreach ([['cash','420.00',$start.' 00:00:00'],['momo','480.00',$start.' 09:00:00'],['momo','120.00',$start.' 10:00:00'],['card','2.50',$end.' 23:59:59'],['cash','99.00',$next.' 00:00:00']] as [$method,$amount,$time]) {
    $r->run('INSERT INTO payments(booking_id,method,amount,created_at) VALUES(?,?,?,?)',[$booking,$method,$amount,$time]);
    $payments[] = (int)$r->db->lastInsertId();
}
$r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at)
 VALUES('receipt_matched','payment',?,48000,?,1,'{}',?)",[(string)$payments[1],$start,$start.' 11:00:00']);
$r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at)
 VALUES('income_recorded','activity','synthetic',5000,?,1,'{\"method\":\"momo\",\"treatment\":\"advance\",\"activity\":\"Synthetic advance\",\"payer\":\"Synthetic payer\",\"reference\":\"\",\"phone\":\"\"}',?)",[$end,$end.' 09:00:00']);
$advance = (int)$r->db->lastInsertId();
$r->run('INSERT INTO stay_receipts VALUES(?,1,?,?)',[$payments[0],'',$start.' 00:00:00']);
foreach ([10000,5000] as $amount) {
    $r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at)
        VALUES('deposit_recorded','wallet','business',?,?,1,?,?)",
        [$amount,$end,json_encode(['allocations'=>[$payments[0]=>$amount],'fee'=>0,'reference'=>'Synthetic '.$tag.'-'.$amount],JSON_THROW_ON_ERROR),$end.' 13:00:00']);
    if ($amount===10000) {
        $deposit = (string)$r->db->lastInsertId();
        $r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at)
            VALUES('deposit_confirmed','deposit',?,0,?,1,'{}',?)",[$deposit,$end,$end.' 15:00:00']);
    }
}
$r->run("INSERT INTO stay_refunds(booking_id,payment_id,amount_minor,actor_id,proof,reason,created_at) VALUES(?,?,300,1,?,?,?)",
    [$booking,$payments[1],'SYN-REPORT-'.$tag,'synthetic refund',$end.' 12:00:00']);
$r->run("INSERT INTO extras(name,price,stock_qty,active) VALUES(?,'999.99','0.00',0)",['Synthetic inactive item '.$tag]);
$item = (int)$r->db->lastInsertId();
$r->run("INSERT INTO stay_extra_settings VALUES(?,'General',1,0)",[$item]);
$r->db->beginTransaction();
for ($i=0;$i<501;$i++) {
    $r->run('INSERT INTO booking_extras(booking_id,extra_id,description,qty,unit_price,created_at) VALUES(?,?,?,?,?,?)',
        [$booking,$i===500?null:$item,$i===500?'<b>Snapshot</b>':'Original snapshot','0.50','1.01',$start.' 10:00:00']);
}
$r->db->commit();
$addStay = static function (int $roomId,string $status,string $type,string $arrive,?string $depart) use ($r): int {
    $r->run('INSERT INTO bookings(room_id,guest_name,checkin_at,checkout_at,status) VALUES(?,?,?,?,?)',
        [$roomId,'Private synthetic guest',$arrive,$depart,$status]);
    $id = (int)$r->db->lastInsertId();
    $r->run("INSERT INTO stay_details VALUES(?,?,'','','','',0,0,0,NULL,1,?)",[$id,$type,$arrive]);
    return $id;
};
$mid = $day->modify('+2 days')->format('Y-m-d');
$after = $day->modify('+3 days')->format('Y-m-d');
$ordinary = $addStay($room,'checked_out','overnight',$mid.' 12:00:00',$after.' 07:00:00');
$addStay($room,'checked_out','short',$mid.' 08:00:00',$mid.' 09:00:00');
$addStay($room,'cancelled','overnight',$mid.' 11:00:00',$end.' 08:00:00');
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Service','100.00','vacant')",['Synthetic service '.$tag]);
$serviceRoom = (int)$r->db->lastInsertId();
$service = $addStay($serviceRoom,'checked_out','overnight',$mid.' 12:00:00',$after.' 07:00:00');
foreach ([$ordinary=>90000,$service=>99000] as $id=>$minor) {
    $r->run('INSERT INTO stay_departures(booking_id,room_minor,extras_minor,incidental_minor,paid_minor,balance_minor,actor_id,departed_at,note) VALUES(?,?,0,0,0,?,1,?,?)',[$id,$minor,$minor,$after.' 07:00:00','Synthetic departure']);
}
$r->run('INSERT INTO expense_categories(name,active) VALUES(?,1)',['Synthetic report '.$tag]);
$category = (int)$r->db->lastInsertId();
$r->run("INSERT INTO expenses(expense_date,category_id,method,amount,user_id) VALUES(?,?,'momo','11.35',1)",[$end,$category]);
$hash = static function () use ($r,$booking,$advance): string {
    $rows = [];
    foreach (['bookings'=>'id','booking_extras'=>'booking_id','payments'=>'booking_id','stay_refunds'=>'booking_id'] as $table=>$column) {
        $rows[] = $r->run("SELECT * FROM $table WHERE $column=? ORDER BY id",[$booking])->fetchAll();
    }
    $rows[] = $r->run('SELECT * FROM finance_events WHERE id=?',[$advance])->fetchAll();
    return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
};
$before = $hash();
$read = static fn(string $view,array $filters=[])=>$repo->read(OwnerReports::filters($filters+['view'=>$view,'start'=>$start,'end'=>$end],$asOf));
$daily = $read('daily',['end'=>$start]);
check((int)$daily['payment']['minor']===102000 && (int)$daily['payment']['count']===3,'daily guest receipts count independently of 501 extra lines');
$default = OwnerReports::filters([], $asOf);
check($default['start']===$asOf->format('Y-m-d') && $default['end']===$default['start']
    && $default['until']===$asOf->format('Y-m-d H:i:s'), 'Daily Overview defaults to the property date and stops at report time');
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard','100.00','vacant')",['Synthetic historical occupancy '.$tag]);
$historicalRoom = (int)$r->db->lastInsertId();
$addStay($historicalRoom,'checked_out','overnight',$start.' 17:00:00',$day->modify('+1 day')->format('Y-m-d').' 00:00:00');
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard','100.00','occupied')",['Synthetic later occupancy '.$tag]);
$laterRoom = (int)$r->db->lastInsertId();
$addStay($laterRoom,'active','overnight',$next.' 08:00:00',null);
$historicalRooms = array_column($read('daily',['end'=>$start])['rooms'],null,'id');
check((int)$historicalRooms[$historicalRoom]['active']===1 && !isset($historicalRooms[$laterRoom]),
    'historical occupancy includes a stay departing at the next midnight and excludes a later arrival');
check(!isset(array_column($read('daily',['end'=>$mid])['rooms'],null,'id')[$historicalRoom]),
    'historical occupancy excludes the stay after its departure');
$todayRooms = array_column($repo->read($default)['rooms'],null,'id');
check($todayRooms[$laterRoom]['status']==='occupied' && (int)$todayRooms[$laterRoom]['active']===1,
    'today still shows current room readiness and occupancy');
$cash = $read('cash');
$detailRows=static fn(string $view,string $detail,array $filters=[])=>$read($view,['detail'=>$detail]+$filters)['detail']['rows'];
$detailRefunds=$detailRows('cash','refunds',['method'=>'card']);
check(array_sum(array_column($detailRefunds,'minor'))===(int)$cash['refunds'],'refund details reconcile and remain independent of receipt method');
$detailDeposits=[];
foreach ($detailRows('cash','deposits') as $part) {
    $detailDeposits[$part['state']]=($detailDeposits[$part['state']]??0)+(int)$part['minor'];
}
ksort($detailDeposits);
check($detailDeposits===array_map('intval',array_column($cash['deposits'],'minor','state')),'deposit detail reconciles by state');
$detailCustody=$detailRows('cash','custody');
foreach (['receipts','held','transit','confirmed'] as $column) {
    check(array_sum(array_column($detailCustody,$column))===array_sum(array_column($cash['custody']['rows'],$column)),
        'cash custody detail reconciles on '.$column);
}
$detailMoMo=[];
foreach ($detailRows('cash','momo') as $match) {
    $detailMoMo[$match['coverage']]=($detailMoMo[$match['coverage']]??0)+(int)$match['minor'];
}
ksort($detailMoMo);
check($detailMoMo===array_map('intval',array_column($cash['momo'],'minor','coverage')),'MoMo details reconcile by matching coverage');
check(count($detailRows('daily','momo',['end'=>$start]))===(int)$daily['pending'],'daily difference details match the daily exception count');
check(array_sum(array_column($detailRows('daily','room-charges',['end'=>$after]),'minor'))===90000,'departure detail reconciles and excludes service rooms');
check(count($detailRows('daily','arrivals',['end'=>$mid]))===(int)$read('daily',['end'=>$mid])['stayCounts']['arrivals'],'arrivals reconcile to daily count');
check(count($detailRows('daily','departures',['end'=>$after]))===(int)$read('daily',['end'=>$after])['stayCounts']['departures'],'departures reconcile to actual departure count');
$expenseMonth=$read('monthly',['end'=>$end]);
$expenses=$detailRows('monthly','expenses',['end'=>$end]);
check((int)$expenseMonth['months'][substr($end,0,7)]['expenses']===array_sum(array_column($expenses,'minor')),'expense detail uses selected month and matches recorded costs');
foreach ([['view'=>'cash','detail'=>'expenses'],['view'=>'daily','detail'=>['momo']]] as $bad) {
    try {OwnerReports::filters($bad,$asOf);} catch (InvalidArgumentException $e) {continue;}
    throw new RuntimeException('Invalid detail accepted');
}
check(true,'detail filter rejects malformed and cross-report values');
$filteredExpense=$detailRows('monthly','expenses',['end'=>$end,'detail_category'=>(string)$category]);
check(count($filteredExpense)===1&&(int)$filteredExpense[0]['minor']===1135,'expense category drill-down keeps the exact category and amount');

check(array_sum(array_column($cash['methods'],'minor'))===107250 && $cash['page']['count']===5,'cash report includes advance and historical card, excludes next midnight');
check((int)$cash['refunds']===300,'refund remains separate from incoming payments');
$custody = $cash['custody']['rows'][0];
check((int)$custody['receipts']===42000 && (int)$custody['held']===27000 && (int)$custody['transit']===5000
    && (int)$custody['confirmed']===10000,'cash cohort reconciles held, in transit and wallet confirmed without deducting refunds');
$coverage = array_map('intval',array_column($cash['momo'],'minor','coverage'));
check($coverage===['Matched by staff'=>48000,'Recorded as successful'=>17000],'MoMo buckets use source links and keep advances in recorded receipts');
check($read('cash',['method'=>'card'])['page']['count']===1,'receipt method filter changes detail and summary');
$roomData = $read('room',['room'=>(string)$room]);
check($roomData['occupied']===7 && count($roomData['byRoom'][$room])===7,'active overnight stay contributes only clipped elapsed nights');
check(count($roomData['overlaps'])===1 && (int)$roomData['overlaps'][0]['stays']===2,
    'overlaps count once; cancelled and short stays do not add room nights');
check((int)$read('daily',['end'=>$after])['roomCharges']['minor']===90000,
    'departure snapshots use room scope and stay separate from receipts');
check(!in_array($serviceRoom,array_map('intval',array_column($roomData['rooms'],'id')),true),'service rooms are excluded');
$extras = $read('extras',['page'=>'11']);
check((int)$extras['total']===25551 && $extras['page']['count']===501 && count($extras['lines'])===1,'501 fractional lines total exactly and page independently');
check(array_sum($extras['series'])===25551 && array_sum(array_column($extras['items'],'minor'))===25551,'daily series and item ranks reconcile to full charges');
check((int)$extras['unlinked']===500,'missing stock links are surfaced without changing charges');
check((int)$read('extras',['item'=>(string)$item])['total']===25500,'inactive item uses historical snapshot price with item filter');
$monthly = $read('monthly');
$month = substr($end,0,7);
$expenseTotal = array_sum(array_column($monthly['categories'],'minor'));
check(count($monthly['months'])===6 && $expenseTotal===(int)$monthly['months'][$month]['expenses'],
    'six-month comparison and selected category totals reconcile');
check((int)array_column($monthly['categories'],'minor','name')['Synthetic report '.$tag]===1135,'expense categories use exact business-date amounts');
$r->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at)
    VALUES('cd_reported','discrepancy','synthetic',0,?,1,?,?)",[$next,json_encode(['source_type'=>'payment','source_id'=>$payments[2]],JSON_THROW_ON_ERROR),$next.' 12:00:00']);
check((int)$read('daily',['end'=>$start])['pending']===0 && !$read('daily',['end'=>$start,'detail'=>'momo'])['detail']['rows'],
    'a MoMo discrepancy reported later does not change the historical day or its detail');
check($before===$hash(),'report reads preserve financial rows byte for byte');

