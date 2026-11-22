<?php
$tail=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
return [
"CREATE TABLE IF NOT EXISTS finance_schema(id INT PRIMARY KEY,activated_at DATETIME NOT NULL,payment_cursor INT NOT NULL,expense_cursor INT NOT NULL)".$tail,
"CREATE TABLE IF NOT EXISTS finance_events(id BIGINT AUTO_INCREMENT PRIMARY KEY,kind VARCHAR(32) NOT NULL,target_type VARCHAR(24) NOT NULL,target_id VARCHAR(80) NOT NULL,amount_minor BIGINT NOT NULL DEFAULT 0,effective_date DATE NOT NULL,actor_id INT NOT NULL,payload LONGTEXT NOT NULL,created_at DATETIME NOT NULL,INDEX target_events(target_type,target_id,id),INDEX period_events(effective_date,kind),FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE RESTRICT)".$tail,
"CREATE TABLE IF NOT EXISTS finance_operations(actor_id INT NOT NULL,operation_key CHAR(32) NOT NULL,fingerprint CHAR(64) NOT NULL,event_id BIGINT NOT NULL,PRIMARY KEY(actor_id,operation_key),FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE RESTRICT,FOREIGN KEY(event_id) REFERENCES finance_events(id) ON DELETE RESTRICT)".$tail,
"CREATE TABLE IF NOT EXISTS finance_claims(scope VARCHAR(24) NOT NULL,identity_hash CHAR(64) NOT NULL,event_id BIGINT NOT NULL,PRIMARY KEY(scope,identity_hash),FOREIGN KEY(event_id) REFERENCES finance_events(id) ON DELETE RESTRICT)".$tail
];
