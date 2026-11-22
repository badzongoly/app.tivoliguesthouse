<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/security.php';
if(access_service()->repo->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')throw new RuntimeException('Unsafe fixture target.');
// Separate suites share a test HTTP IP. Reset only synthetic throttle fixture state between suites.
access_service()->repo->run('DELETE FROM security_throttle');echo "Synthetic throttle fixture cleared.\n";
