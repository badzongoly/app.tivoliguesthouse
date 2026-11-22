<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';$r=access_service()->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
if(($argv[1]??'')==='off'){$r->run('DROP TRIGGER IF EXISTS synthetic_stock_issue_http_fail');exit;}
if(($argv[1]??'')!=='on')exit(3);
$fixture=json_decode(file_get_contents(dirname(__DIR__,2).'/.local-setup/stock-issue-http-fixture.json'),true,512,JSON_THROW_ON_ERROR);$actor=(int)$fixture['users']['staff']['id'];if($actor<1)exit(4);
$r->run("CREATE TRIGGER synthetic_stock_issue_http_fail BEFORE INSERT ON security_audit FOR EACH ROW BEGIN IF NEW.action='stockissue.report' AND NEW.actor_id=".$actor." THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic required audit failure'; END IF; END");
