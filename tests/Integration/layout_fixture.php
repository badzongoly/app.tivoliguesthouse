<?php
// Additive synthetic display fixtures. Never accepts a database target argument.
if(PHP_SAPI!=='cli'){exit(1);}
putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/security.php';
$r=access_service()->repo;
if($r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1'){throw new RuntimeException('Unverified test fixtures.');}
$ddl=[
"CREATE TABLE IF NOT EXISTS rooms(id INT PRIMARY KEY AUTO_INCREMENT,name VARCHAR(60) NOT NULL UNIQUE,type VARCHAR(60) NOT NULL,rate DECIMAL(12,2) NOT NULL DEFAULT 0,status ENUM('vacant','occupied','dirty','maintenance') NOT NULL DEFAULT 'vacant',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
"CREATE TABLE IF NOT EXISTS bookings(id INT PRIMARY KEY AUTO_INCREMENT,room_id INT NOT NULL,rate_per_night DECIMAL(12,2) NULL,guest_name VARCHAR(120) NOT NULL,gender ENUM('Male','Female','Other') NULL,nationality VARCHAR(80) NULL,contact VARCHAR(60) NULL,checkin_at DATETIME NOT NULL,checkout_at DATETIME NULL,status ENUM('active','checked_out','cancelled') NOT NULL DEFAULT 'active')",
"CREATE TABLE IF NOT EXISTS payments(id INT PRIMARY KEY AUTO_INCREMENT,booking_id INT NOT NULL,method ENUM('cash','momo','card') NOT NULL,amount DECIMAL(12,2) NOT NULL,note VARCHAR(255) NULL,created_at DATETIME NULL)",
"CREATE TABLE IF NOT EXISTS extras(id INT PRIMARY KEY AUTO_INCREMENT,name VARCHAR(120) NOT NULL,price DECIMAL(12,2) NOT NULL DEFAULT 0,active TINYINT NOT NULL DEFAULT 1,stock_qty DECIMAL(12,2) NOT NULL)",
"CREATE TABLE IF NOT EXISTS booking_extras(id INT PRIMARY KEY AUTO_INCREMENT,booking_id INT NOT NULL,extra_id INT NULL,description VARCHAR(160) NULL,qty DECIMAL(10,2) NOT NULL DEFAULT 1,unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
"CREATE TABLE IF NOT EXISTS stock_movements(id INT PRIMARY KEY AUTO_INCREMENT,extra_id INT NOT NULL,movement_type ENUM('in','out','adjustment') NOT NULL DEFAULT 'in',qty DECIMAL(12,2) NOT NULL,unit_cost DECIMAL(12,2) NULL,note VARCHAR(255) NULL,ref_type VARCHAR(50) NULL,ref_id INT NULL,created_by INT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)"
];
foreach($ddl as $sql){$r->db->exec($sql.' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');}
if(!(int)$r->run('SELECT COUNT(*) FROM rooms')->fetchColumn()){
 $r->run("INSERT INTO rooms(name,type,rate,status) VALUES('Synthetic Garden','Double','250.00','occupied'),('Synthetic Courtyard','Standard','180.00','vacant')");
 $r->run("INSERT INTO bookings(room_id,rate_per_night,guest_name,checkin_at) VALUES(1,'250.00','Synthetic Guest',UTC_TIMESTAMP())");
 $r->run("INSERT INTO payments(booking_id,method,amount,created_at) VALUES(1,'cash','50.00',UTC_TIMESTAMP())");
 $r->run("INSERT INTO extras(name,price,stock_qty) VALUES('Synthetic Water','5.00','20.00')");
}
echo "Synthetic layout fixtures ready; no financial workflow certified.\n";
