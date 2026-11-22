<?php
require __DIR__.'/stock_issues.php';
$clock='2036-08-05 14:00:00';$si=new \Tivoli\Service\StockIssueService($a,$clock);$svc=new \Tivoli\Service\StockCountService($a,$clock);
function holdDenied(callable $f,string $label):void{try{$f();}catch(Throwable $e){if(!str_contains($e->getMessage(),'available stock'))throw $e;sa(true,$label);return;}throw new RuntimeException('Expected hold denial: '.$label);}
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard',100,'occupied')",['Synthetic issue writer '.sk()]);$room=(int)$r->db->lastInsertId();$r->run("INSERT INTO bookings(room_id,rate_per_night,guest_name,checkin_at,status) VALUES(?,100,'Synthetic issue writer guest',?,'active')",[$room,$clock]);$booking=(int)$r->db->lastInsertId();$stay=new \Tivoli\Service\StayService($a,$clock);
$x=sx('10.00');$id=report($x,['qty'=>'3.00']);
holdDenied(fn()=>$stay->execute($staff,'extra',['booking_id'=>(string)$booking,'extra_id'=>(string)$x,'qty'=>'8.00','settlement'=>'room'],sk()),'guest sale cannot consume reported held stock');
$stay->execute($staff,'extra',['booking_id'=>(string)$booking,'extra_id'=>(string)$x,'qty'=>'2.00','settlement'=>'room'],sk());sa(qty($x)==='8.00','sale of available quantity preserves held stock');
$count=start($staff,[$x]);countLine($staff,$count,$x,'5.00');send($staff,$count);$c=$svc->detail($owner,$count);sa($c['lines'][$x]['difference']===0&&$c['lines'][$x]['current']===800,'count compares saleable quantity separately from raw on hand');approve($count);sa(qty($x)==='8.00','clean count does not deduct held damage a second time');accept($id);dispose($id);sa(qty($x)==='5.00','later approved disposal removes the held quantity exactly once');
$x=sx('10.00');$count=start($staff,[$x]);countLine($staff,$count,$x,'10.00');report($x);sd(fn()=>send($staff,$count),'hold change after physical counting requires a recount');
$x=sx('10.00');$count=start($staff,[$x]);countLine($staff,$count,$x,'9.00');send($staff,$count);report($x);sd(fn()=>approve($count),'new hold after submission cannot become a duplicate variance approval');
echo "TOTAL $n cross-writer stock issue assertions passed.\n";
