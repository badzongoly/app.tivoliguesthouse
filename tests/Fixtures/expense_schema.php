<?php
// Synthetic schema only. The provisioning script verifies the isolated instance marker first.
return [
"CREATE TABLE IF NOT EXISTS expense_categories (id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(80) NOT NULL UNIQUE,active TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
"CREATE TABLE IF NOT EXISTS expenses(id INT AUTO_INCREMENT PRIMARY KEY,expense_date DATE NOT NULL,category_id INT NOT NULL,method ENUM('cash','momo','card','bank','other') NOT NULL DEFAULT 'cash',amount DECIMAL(12,2) NOT NULL,vendor VARCHAR(120) NULL,reference_no VARCHAR(80) NULL,description VARCHAR(255) NULL,user_id INT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(category_id) REFERENCES expense_categories(id) ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci"
];