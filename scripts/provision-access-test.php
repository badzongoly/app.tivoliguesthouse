<?php
if(PHP_SAPI!=='cli' || ($argv[1] ?? '')!=='--confirmed-test-instance'){exit(1);}
$root=dirname(__DIR__);$expected=strtolower(str_replace('\\','/',$root.'/.local-setup/auth-test-mysql'));
$db=new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$actual=strtolower(rtrim(str_replace('\\','/',$db->query('SELECT @@datadir')->fetchColumn()),'/'));
if($actual!==$expected){throw new RuntimeException('Port 3307 is not the dedicated project test instance.');}
$exists=(int)$db->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='tivoli_auth_test'")->fetchColumn();
if($exists){
 putenv('TIVOLI_TEST_MODE=1');require $root.'/bootstrap/security.php';
 if(access_service()->repo->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1'){throw new RuntimeException('Existing database is not a verified synthetic fixture database.');}
 echo "Existing synthetic database verified; no reset performed.\n";exit;
}
$password=bin2hex(random_bytes(24));$db->exec('CREATE DATABASE tivoli_auth_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db->exec("CREATE USER 'tivoli_auth_test'@'127.0.0.1' IDENTIFIED BY ".$db->quote($password));
$db->exec("GRANT ALL PRIVILEGES ON tivoli_auth_test.* TO 'tivoli_auth_test'@'127.0.0.1'");
$config=['dsn'=>'mysql:host=127.0.0.1;port=3307;dbname=tivoli_auth_test;charset=utf8mb4','username'=>'tivoli_auth_test','password'=>$password,'audit_key'=>bin2hex(random_bytes(32)),'base_url'=>'http://127.0.0.1:8099','reset_delivery'=>'local_preview'];
file_put_contents($root.'/config/access-test.local.php','<?php return '.var_export($config,true).';',LOCK_EX);
putenv('TIVOLI_TEST_MODE=1');require $root.'/bootstrap/security.php';$r=access_service()->repo;
$r->db->exec("CREATE TABLE users(id INT AUTO_INCREMENT PRIMARY KEY,email VARCHAR(190) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL,name VARCHAR(120) NOT NULL,role ENUM('admin','clerk') NOT NULL DEFAULT 'clerk',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
$r->db->exec('CREATE TABLE security_test_marker(marker VARCHAR(80) PRIMARY KEY) ENGINE=InnoDB');$r->run('INSERT INTO security_test_marker VALUES(?)',['synthetic-auth-only-v1']);
$r->run('INSERT INTO users(email,password_hash,name,role) VALUES(?,?,?,?)',['admin@example.invalid',password_hash('Synthetic Admin Passphrase 123!',PASSWORD_BCRYPT,['cost'=>12]),'Synthetic Administrator','admin']);
echo "Dedicated synthetic fixture schema provisioned.\n";