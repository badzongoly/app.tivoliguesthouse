<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/security.php';$r=access_service()->repo;
if($r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
if($r->run('SELECT email FROM users WHERE id=1')->fetchColumn()!=='admin@example.invalid')exit(3);
$r->run('UPDATE security_accounts SET active=1 WHERE user_id=1');echo "Restored only the known synthetic administrator after a fixture-assumption failure.\n";
