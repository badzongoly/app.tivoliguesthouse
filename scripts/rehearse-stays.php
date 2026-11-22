<?php
/** Local backup and protected-copy rehearsal. Never migrates or restores over the source. */
if(PHP_SAPI!=='cli'||($argv[1] ?? '')!=='--confirmed-local-rehearsal')exit("Explicit local rehearsal flag required.\n");
if(getenv('TIVOLI_TEST_MODE')==='1')throw new RuntimeException('Rehearsal source must use the confirmed normal local configuration.');
require dirname(__DIR__).'/bootstrap/security.php';
$config=access_config();if($config['dsn']!=='mysql:host=127.0.0.1;port=3306;dbname=tivoli;charset=utf8mb4')throw new RuntimeException('Unexpected local source.');
$source=access_service()->repo;
if((int)$source->run('SELECT @@port')->fetchColumn()!==3306||$source->run('SELECT DATABASE()')->fetchColumn()!=='tivoli')throw new RuntimeException('Unexpected source server.');
$target=new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$data=str_replace('\\','/',(string)$target->query('SELECT @@datadir')->fetchColumn());$expected=str_replace('\\','/',dirname(__DIR__).'/.local-setup/auth-test-mysql/');
if((int)$target->query('SELECT @@port')->fetchColumn()!==3307||strcasecmp(rtrim($data,'/'),rtrim($expected,'/'))!==0)throw new RuntimeException('Unexpected protected restore instance.');
$stamp=gmdate('YmdHis');$name='tivoli_restore_stays_'.$stamp;$directory=dirname(__DIR__).'/.local-setup';$before=$directory.'/stays-before-'.$stamp.'.sql';$after=$directory.'/stays-rehearsal-after-'.$stamp.'.sql';
$dump='C:/xampp/mysql/bin/mysqldump.exe';$client='C:/xampp/mysql/bin/mysql.exe';
function command(array $args,?string $input,?string $output,array $env): void {
    $descriptors=[0=>$input?['file',$input,'r']:['pipe','r'],1=>$output?['file',$output,'w']:['pipe','w'],2=>['pipe','w']];$p=proc_open($args,$descriptors,$pipes,null,$env);if(!is_resource($p))throw new RuntimeException('Backup process did not start.');if(!$input)fclose($pipes[0]);if(!$output){stream_get_contents($pipes[1]);fclose($pipes[1]);}stream_get_contents($pipes[2]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('A protected backup/restore command failed. No source migration was attempted.');
}
$options=['--single-transaction','--skip-comments','--skip-extended-insert','--complete-insert','--order-by-primary','--hex-blob','--routines','--triggers'];
command([$dump,'--no-defaults','--host=127.0.0.1','--port=3306','--user='.$config['username'],...$options,'tivoli'],null,$before,array_merge(getenv(),['MYSQL_PWD'=>$config['password']]));
if(!is_file($before)||filesize($before)<1000)throw new RuntimeException('Protected snapshot is incomplete.');
$target->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
command([$client,'--no-defaults','--host=127.0.0.1','--port=3307','--user=root',$name],$before,null,array_merge(getenv(),['MYSQL_PWD'=>'']));
$copy=new PDO('mysql:host=127.0.0.1;port=3307;dbname='.$name.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$copy->exec("SET time_zone='+00:00'");$repo=new \Tivoli\Repository\AccessRepository($copy);
require dirname(__DIR__).'/database/install-stays.php';stay_install_schema($repo);
command([$dump,'--no-defaults','--host=127.0.0.1','--port=3307','--user=root',...$options,$name],null,$after,array_merge(getenv(),['MYSQL_PWD'=>'']));
$tables=['bookings','booking_extras','payments','expenses','expense_categories','rooms','extras','stock_movements','users'];
function rows(string $path,array $tables):array{$result=array_fill_keys($tables,[]);$handle=fopen($path,'r');while(($line=fgets($handle))!==false){if(preg_match('/^INSERT INTO `([^`]+)`/',$line,$m)&&array_key_exists($m[1],$result))$result[$m[1]][]=$line;}fclose($handle);return $result;}
$original=rows($before,$tables);$restored=rows($after,$tables);$counts=[];$total=0;
foreach($tables as $table){if($original[$table]!==$restored[$table])throw new RuntimeException('Protected original row mismatch in '.$table.'; source was not migrated.');$counts[$table]=count($original[$table]);$total+=$counts[$table];}
$key=dirname(__DIR__).'/config/hr.local.php';$keyBackup=$directory.'/stays-private-key-'.$stamp.'.php';if(is_file($key)){if(!copy($key,$keyBackup)||hash_file('sha256',$key)!==hash_file('sha256',$keyBackup))throw new RuntimeException('Private key backup failed.');}
$report=['source'=>'verified loopback:3306/tivoli','restore_database'=>$name,'snapshot'=>basename($before),'snapshot_sha256'=>hash_file('sha256',$before),'after_snapshot'=>basename($after),'original_rows'=>$counts,'total_original_rows_unchanged'=>$total,'copy_schema_version'=>(int)$repo->run('SELECT MAX(version) FROM stay_schema')->fetchColumn(),'source_migrated'=>false,'private_key_backup'=>is_file($keyBackup)?basename($keyBackup):null,'recorded_utc'=>gmdate('c')];
file_put_contents($directory.'/stays-rehearsal-evidence.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo "Protected backup restored and migration rehearsed in an isolated copy. $total original row statements match exactly. Ordinary localhost/tivoli was not migrated.\n";
