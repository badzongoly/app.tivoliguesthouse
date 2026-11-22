<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/security.php';$r=access_service()->repo;
if($r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$tables=['payments'=>'id','expenses'=>'id','booking_extras'=>'id','stock_movements'=>'id','stay_refunds'=>'id','stay_departures'=>'booking_id','stay_receipts'=>'payment_id','finance_schema'=>'id','finance_events'=>'id','finance_operations'=>'actor_id,operation_key','finance_claims'=>'scope,identity_hash'];$before=[];
foreach($tables as $table=>$order)$before[$table]=hash('sha256',json_encode($r->run("SELECT * FROM `$table` ORDER BY $order")->fetchAll(),JSON_THROW_ON_ERROR));
$p=proc_open([PHP_BINARY,dirname(__DIR__,2).'/scripts/setup-finance.php','--confirmed-local'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0||$error)throw new RuntimeException('Installer rehearsal failed');
foreach($tables as $table=>$order){if(hash('sha256',json_encode($r->run("SELECT * FROM `$table` ORDER BY $order")->fetchAll(),JSON_THROW_ON_ERROR))!==$before[$table])throw new RuntimeException('Preservation failure '.$table);echo "PASS setup rerun preserves all fields and identities in $table\n";}
echo 'Verified test DB runtime: '.$r->run('SELECT VERSION()')->fetchColumn()."\nTOTAL 11 finance migration preservation checks passed.\n";
