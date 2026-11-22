<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';$a=access_service();$r=$a->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$owner=(int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn();putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$owner);$h=new \Tivoli\Service\HousekeepingService($a,'2037-04-15 14:00:00');
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard',100,'vacant')",['Synthetic attention characterization '.bin2hex(random_bytes(5))]);$room=(int)$r->db->lastInsertId();$view=$h->room($owner,$room);$h->execute($owner,'start',['room_id'=>(string)$room,'room_token'=>$view['token']],bin2hex(random_bytes(16)));
function questions():int{global $r;return (int)$r->run("SHOW SESSION STATUS LIKE 'Questions'")->fetch()['Value'];}
$q=questions();$start=microtime(true);$h->permit($owner);if(!$h->owner($owner))exit(3);
$ids=$r->run("SELECT id FROM finance_events WHERE target_type='housekeeping' AND kind='hk_started' ORDER BY id DESC")->fetchAll(PDO::FETCH_COLUMN);$requests=[];$followups=[];
foreach($ids as $id){$c=$h->detail($owner,(int)$id);if($c['valid']&&$c['status']==='inspection'&&($c['exception']['status']??'')==='pending')$requests[]=$c;foreach($c['followups'] as $inspection)$followups[]=['case'=>$c,'inspection'=>$inspection];}
$reference=['requests'=>$requests,'followups'=>$followups,'count'=>count($requests)+count($followups)];$oldTime=microtime(true)-$start;$oldQueries=questions()-$q;
$q=questions();$start=microtime(true);$actual=$h->attention($owner);$newTime=microtime(true)-$start;$newQueries=questions()-$q;
if($actual!==$reference)throw new RuntimeException('Historical follow-ups or pending exceptions changed');echo "PASS exact owner attention records and ordering preserved\n";
if($newQueries>=$oldQueries)throw new RuntimeException('Owner attention still hydrates every non-actionable attempt');echo "PASS non-actionable history avoids repeated room/case queries\n";
file_put_contents(dirname(__DIR__,2).'/.local-setup/owner-attention-profile.json',json_encode(compact('oldTime','newTime','oldQueries','newQueries'),JSON_PRETTY_PRINT));echo json_encode(compact('oldTime','newTime','oldQueries','newQueries'))."\n";
