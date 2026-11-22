<?php
if(PHP_SAPI!=='cli'||($argv[1] ?? '')!=='--confirmed-local')exit("Use --confirmed-local after verifying the local target.\n");
require dirname(__DIR__).'/bootstrap/hr.php';$dsn=access_config()['dsn'];if(!in_array($dsn,['mysql:host=127.0.0.1;port=3306;dbname=tivoli;charset=utf8mb4','mysql:host=127.0.0.1;port=3307;dbname=tivoli_auth_test;charset=utf8mb4'],true))throw new RuntimeException('Unapproved exit-processing target.');
if(getenv('TIVOLI_TEST_MODE')==='1'&&access_service()->repo->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')throw new RuntimeException('Unverified test target.');
echo hr_service()->processDueExits()." scheduled staff exits processed.\n";
