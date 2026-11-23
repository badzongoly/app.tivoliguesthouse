<?php
if(PHP_SAPI!=='cli')exit(1);
putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/hr.php';
$repo=access_service()->repo;$target=$repo->run('SELECT @@port AS port,DATABASE() AS db')->fetch();
if((int)$target['port']!==3307||$target['db']!=='tivoli_auth_test'||$repo->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')throw new RuntimeException('Unsafe HR test target.');
$path=dirname(__DIR__,2).'/.local-setup/hr-ux-preservation.json';
$tables=['hr_staff','hr_history','hr_leave','hr_items','hr_documents','hr_salary_versions','hr_catalogue','hr_operations','bookings','booking_extras','payments','expenses','expense_categories','rooms','extras','stock_movements'];
if(($argv[1]??'')==='before'){
 $snapshot=[];
 foreach($tables as $table){
  $exists=$repo->run('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$table])->fetchColumn();if(!$exists)throw new RuntimeException('Missing protected table '.$table);
  $rows=$repo->run('SELECT * FROM '.$table)->fetchAll();$snapshot[$table]=[];
  foreach($rows as $row){$hash=hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));$snapshot[$table][$hash]=($snapshot[$table][$hash]??0)+1;}
 }
 file_put_contents($path,json_encode($snapshot,JSON_THROW_ON_ERROR));echo "PASS exact synthetic target; protected row fingerprints recorded\n";
}elseif(($argv[1]??'')==='after'){
 $snapshot=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);$count=0;
 foreach($snapshot as $table=>$hashes){foreach($repo->run('SELECT * FROM '.$table)->fetchAll() as $row){$hash=hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));if(empty($hashes[$hash]))continue;if(--$hashes[$hash]===0)unset($hashes[$hash]);++$count;}if($hashes)throw new RuntimeException('Protected rows changed or missing in '.$table);}
 echo "PASS all $count pre-existing protected synthetic HR/guest/financial rows unchanged\n";
}else echo "PASS exact marked HR synthetic target verified\n";
