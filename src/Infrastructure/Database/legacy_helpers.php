<?php

if (!function_exists('db_table_exists')) {
  function db_table_exists(mysqli $mysqli, string $table): bool
  {
    $safe_table = $mysqli->real_escape_string($table);
    $result = $mysqli->query("SHOW TABLES LIKE '{$safe_table}'");
    return $result !== false && $result->num_rows > 0;
  }
}

if (!function_exists('require_db_table')) {
  function require_db_table(mysqli $mysqli, string $table): void
  {
    if (!db_table_exists($mysqli, $table)) {
      throw new RuntimeException("The '{$table}' table is missing. Import the database schema with an admin account or grant the app user access to the required tables.");
    }
  }
}

