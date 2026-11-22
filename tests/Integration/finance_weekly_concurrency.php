<?php
if(PHP_SAPI!=='cli')exit(1);
putenv('TIVOLI_TEST_MODE=1');
require dirname(__DIR__,2).'/bootstrap/security.php';
$a=access_service();$r=$a->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$owner=(int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn();
putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$owner);
function weeklyRace(array $jobs):array {
 $barrier=dirname(__DIR__,2).'/.local-setup/finance-barrier-'.bin2hex(random_bytes(8));
 $processes=[];
 foreach($jobs as $i=>$job){
  $p=proc_open([PHP_BINARY,__DIR__.'/finance_worker.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  fwrite($pipes[0],json_encode($job+['barrier'=>$barrier,'worker'=>$i],JSON_THROW_ON_ERROR));fclose($pipes[0]);
  $processes[]=[$p,$pipes];
 }
 $deadline=microtime(true)+10;
 while(!file_exists($barrier.'.ready0')||!file_exists($barrier.'.ready1')){
  if(microtime(true)>$deadline)throw new RuntimeException('Worker readiness timed out');
  usleep(10000);
 }
 file_put_contents($barrier.'.go','go');
 $results=[];
 foreach($processes as [$p,$pipes]){
  $results[]=json_decode(stream_get_contents($pipes[1]),true,512,JSON_THROW_ON_ERROR);
  $errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
  if(proc_close($p)!==0||$errors)throw new RuntimeException('Worker failure');
 }
 foreach(['.ready0','.ready1','.go'] as $suffix)unlink($barrier.$suffix);
 return $results;
}
for($attempt=0;$attempt<3;$attempt++){
 $day=new DateTimeImmutable('2080-01-01');
 $day=$day->modify('monday this week');
 while($r->run("SELECT 1 FROM finance_events WHERE target_type='period' AND target_id=? LIMIT 1",['week:'.$day->format('Y-m-d')])->fetchColumn())$day=$day->modify('+7 days');
 $start=$day->format('Y-m-d');$sunday=$day->modify('+6 days')->format('Y-m-d');$monday=$day->modify('+7 days')->format('Y-m-d');
 $finance=new \Tivoli\Service\FinanceService($a,$monday.' 12:00:00');
 $close=['actor'=>$owner,'action'=>'week_decide','clock'=>$monday.' 12:00:00','input'=>[
  'start'=>$start,'outcome'=>'clean','complete'=>'1','evidence'=>'Synthetic race',
  'review_token'=>$finance->weeklyReview($start)['token']],'key'=>bin2hex(random_bytes(16))];
 $receipt=['actor'=>$owner,'action'=>'income_record','clock'=>$sunday.' 23:59:59','input'=>[
  'payer'=>'Synthetic race payer','activity'=>'Synthetic race service','amount'=>'3.25','method'=>'cash',
  'treatment'=>'earned','provided'=>'1'],'key'=>bin2hex(random_bytes(16))];
 $results=weeklyRace([$close,$receipt]);
 if(count(array_filter($results,fn($item)=>$item['ok']))!==1)throw new RuntimeException('Close/post race accepted both or neither');
 if($results[0]['ok']){
  if(!$finance->periodLocked($start)||array_sum($finance->summary($start,$sunday)['receipts'])!==0)throw new RuntimeException('Closed totals changed');
 }else{
  if($finance->periodLocked($start)||array_sum($finance->summary($start,$sunday)['receipts'])!==325)throw new RuntimeException('Open receipt lost');
  $finance->execute($owner,'week_decide',['start'=>$start,'outcome'=>'open','comment'=>'Synthetic race finished'],bin2hex(random_bytes(16)));
 }
 echo "PASS concurrent close and receipt preserve one consistent outcome\n";
}

