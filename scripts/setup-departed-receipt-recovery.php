<?php
if (PHP_SAPI !== 'cli' || count($argv)!==3 || $argv[1]!=='--confirmed-local') {
    fwrite(STDERR,"Specify --confirmed-local and the exact approved database name.\n");
    exit(1);
}
require dirname(__DIR__).'/bootstrap/security.php';
$db=$argv[2];
$test=getenv('TIVOLI_TEST_MODE')==='1';
$port=$test?3307:3306;
$allowed=$test?$db==='tivoli_auth_test':preg_match('/^tivoli(?:_fresh_[0-9]{14})?$/D',$db);
if (!$allowed || access_config()['dsn']!=="mysql:host=127.0.0.1;port=$port;dbname=$db;charset=utf8mb4") {
    throw new RuntimeException('Database target does not match the local configuration.');
}
$r=access_service()->repo;
$dir=rtrim(str_replace('\\','/',$r->run('SELECT @@datadir')->fetchColumn()),'/');
$expected=$test?str_replace('\\','/',dirname(__DIR__).'/.local-setup/auth-test-mysql'):'C:/xampp/mysql/data';
if (strcasecmp($dir,$expected)!==0 || (int)$r->run('SELECT @@port')->fetchColumn()!==$port
    || $r->run('SELECT DATABASE()')->fetchColumn()!==$db
    || ($test && $r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')) {
    throw new RuntimeException('Database instance verification failed.');
}
(require dirname(__DIR__).'/database/migrations/010_departed_receipt_recovery.php')($r);
echo "Departed receipt recovery schema verified on the named local database.\n";
