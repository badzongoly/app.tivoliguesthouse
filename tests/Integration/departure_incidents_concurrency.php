<?php
if(PHP_SAPI!=='cli')exit(1);
putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';
$a=access_service();$r=$a->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$f=json_decode(file_get_contents(dirname(__DIR__,2).'/.local-setup/finance-fixture.json'),true,512,JSON_THROW_ON_ERROR);
$owner=1;$clerk=(int)$f['clerk'];putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$owner);
$s=new \Tivoli\Service\StayService($a,'2028-08-02 10:00:00');
function incidentRace(array $jobs):array{
 $base=dirname(__DIR__,2).'/.local-setup/stay-race-incident-'.bin2hex(random_bytes(10));$processes=[];
 foreach($jobs as $i=>$job){
  $payload=$job+['ready'=>$base.'-'.$i.'-ready','go'=>$base.'-go','clock'=>'2028-08-02 10:00:00','key'=>bin2hex(random_bytes(16))];
  $p=proc_open([PHP_BINARY,__DIR__.'/stays_worker.php',base64_encode(json_encode($payload,JSON_THROW_ON_ERROR))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  fclose($pipes[0]);$processes[]=[$p,$pipes,$payload['ready']];
 }
 $until=microtime(true)+10;
 while(!is_file($base.'-0-ready')||!is_file($base.'-1-ready')){if(microtime(true)>$until)throw new RuntimeException('Worker barrier failed');usleep(10000);}
 file_put_contents($base.'-go','go');$results=[];
 foreach($processes as [$p,$pipes,$ready]){
  $results[]=json_decode(stream_get_contents($pipes[1]),true,512,JSON_THROW_ON_ERROR);$err=stream_get_contents($pipes[2]);
  fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0||$err)throw new RuntimeException('Race worker failed');unlink($ready);
 }
 unlink($base.'-go');return $results;
}
function freshIncidentGuest():int{global $r,$s,$clerk;
 $r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard','100.00','vacant')",['Incident race '.bin2hex(random_bytes(10))]);$room=(int)$r->db->lastInsertId();
 $r->run("INSERT INTO stay_room_settings(room_id,short_rate,short_enabled,ready_state) VALUES(?,'40.00',1,'ready')",[$room]);
 return $s->execute($clerk,'checkin',['room_id'=>(string)$room,'guest_name'=>'Synthetic incident race','phone'=>'0000000000','gender'=>'Male','nationality'=>'Ghana','stay_type'=>'overnight','amount'=>'100.00','expected_rate'=>'100.00','payment_type'=>'cash','identity_verified'=>'1','payment_attested'=>'1','room_checked'=>'1'],bin2hex(random_bytes(16)))['booking_id'];
}
$id=freshIncidentGuest();
$report=['actor'=>$clerk,'action'=>'report_departure','input'=>['booking_id'=>(string)$id,'discovered_at'=>'2028-08-02T10:00:00','actual_departed_at'=>'2028-08-02T10:00:00','reason'=>'Synthetic simultaneous departure report','vacant'=>'1','secured'=>'1'],'key'=>bin2hex(random_bytes(16))];
$result=incidentRace([$report,$report]);
if(!$result[0]['ok']||!$result[1]['ok']||(int)$r->run("SELECT COUNT(*) FROM finance_events WHERE kind='departure_reported' AND target_id=?",[(string)$id])->fetchColumn()!==1)throw new RuntimeException('Duplicate report race failed');
echo "PASS simultaneous report retries retain one incident\n";
$id=freshIncidentGuest();$report['input']['booking_id']=(string)$id;unset($report['key']);
$checkout=['actor'=>$clerk,'action'=>'checkout','input'=>['booking_id'=>(string)$id,'expected_balance'=>'0','extras_checked'=>'1','keys_returned'=>'1']];
$result=incidentRace([$report,$checkout]);
if(count(array_filter($result,fn($item)=>$item['ok']))!==1)throw new RuntimeException('Checkout/report race failed');
echo "PASS normal checkout and incident report cannot both commit\n";
$id=freshIncidentGuest();
$r->run("INSERT INTO extras(name,price,stock_qty,active) VALUES(?,'20.00','2.00',1)",['Incident race cost '.bin2hex(random_bytes(5))]);$extra=(int)$r->db->lastInsertId();
$s->execute($clerk,'extra',['booking_id'=>(string)$id,'extra_id'=>(string)$extra,'qty'=>'1','settlement'=>'room'],bin2hex(random_bytes(16)));
$report['input']['booking_id']=(string)$id;$s->execute($clerk,'report_departure',$report['input'],bin2hex(random_bytes(16)));
$preview=$s->departurePreview($owner,$id,'2028-08-02T10:00:00');
$s->execute($owner,'review_departure',['booking_id'=>(string)$id,'decision'=>'confirm','actual_departed_at'=>$preview['actual_departed_at'],'review_token'=>$preview['token'],'reason'=>'Synthetic race owner review','owner_id'=>(string)$clerk,'due'=>'2028-08-03'],bin2hex(random_bytes(16)));
$job=['actor'=>$owner,'action'=>'collect_outstanding','input'=>['booking_id'=>(string)$id,'expected_balance'=>'2000','amount'=>'15.00','method'=>'cash','reference'=>'INCIDENT-RACE-'.bin2hex(random_bytes(8)),'reason'=>'Synthetic collection']];
$other=$job;$other['input']['reference'].='-OTHER';
$result=incidentRace([$job,$other]);
if(count(array_filter($result,fn($item)=>$item['ok']))!==1||$s->snapshot($owner,$id)['bill']['balance']!==500)throw new RuntimeException('Partial collection race failed');
echo "PASS competing partial collections cannot exceed the confirmed balance\n";
$job['input']['expected_balance']='500';$job['input']['amount']='5.00';$job['input']['reference'].='-LAST';$job['key']=bin2hex(random_bytes(16));
$result=incidentRace([$job,$job]);
if(!$result[0]['ok']||!$result[1]['ok']||$s->snapshot($owner,$id)['bill']['balance']!==0)throw new RuntimeException('Receipt replay race failed');
echo "PASS simultaneous final collection retries record one receipt\nTOTAL 4 departure incident concurrency scenarios passed.\n";
