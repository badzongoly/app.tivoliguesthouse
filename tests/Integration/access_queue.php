<?php
if(PHP_SAPI!=='cli'){exit(1);}putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/security.php';$s=access_service();$r=$s->repo;
if($r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1'){throw new RuntimeException('Unsafe test target');}
$r->run('DELETE FROM security_throttle');$config=access_config();
$s->login('spray-'.bin2hex(random_bytes(5)).'@example.invalid','wrong');$s->login('admin@example.invalid','Synthetic Admin Passphrase 123!');
$bucket=hash_hmac('sha256','login.ip|cli',$config['audit_key']);
if((int)$r->run('SELECT attempts FROM security_throttle WHERE bucket=?',[$bucket])->fetchColumn()!==1){throw new RuntimeException('Successful login erased failures against other accounts');}
echo "PASS successful login preserves prior IP failure history\n";
$r->run("INSERT INTO security_mail_queue(payload,status,attempts,locked_until,claim_token) VALUES(?,'processing',5,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE),?)",[\Tivoli\Infrastructure\ResetEnvelope::encode(['email'=>null],$config['audit_key']),bin2hex(random_bytes(16))]);$expired=(int)$r->db->lastInsertId();
$r->run('INSERT INTO security_mail_queue(payload) VALUES(?)',['deliberately-invalid-ciphertext']);$invalid=(int)$r->db->lastInsertId();
$process=proc_open([PHP_BINARY,dirname(__DIR__,2).'/scripts/send-reset-mail.php','--test'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0 || $error!==''){throw new RuntimeException('Queue worker failed');}
$row=$r->run('SELECT status,payload FROM security_mail_queue WHERE id=?',[$expired])->fetch();if($row['status']!=='failed' || $row['payload']!==''){throw new RuntimeException('Expired final lease was not safely retired');}echo "PASS crashed final-attempt lease is failed and sensitive payload cleared\n";
$row=$r->run('SELECT status,attempts,claim_token FROM security_mail_queue WHERE id=?',[$invalid])->fetch();if($row['status']!=='pending' || (int)$row['attempts']!==1 || $row['claim_token']!==null){throw new RuntimeException('Failed queue delivery retry state invalid');}echo "PASS invalid envelope failure is audited and queued for bounded retry\n";
$events=(int)$r->run("SELECT COUNT(*) FROM security_audit WHERE action='auth.reset_delivery' AND target_type='mail_queue' AND target_id IN (?,?)",[(string)$expired,(string)$invalid])->fetchColumn();if($events!==2){throw new RuntimeException('Queue failures lack audit evidence');}echo "PASS queue failures have durable audit records\n";
echo "TOTAL 4 queue/throttle regression assertions passed.\n";