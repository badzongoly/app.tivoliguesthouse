<?php
// Preserve the legacy mysqli, charset and UTC setup during extraction.
if (getenv('TIVOLI_TEST_MODE')==='1') {
    // Synthetic rendering only: reject all writes before any legacy controller runs.
    require_once __DIR__.'/security.php';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET')!=='GET' || array_intersect(['delete','edit'],array_keys($_GET))) {
        http_response_code(405);exit('Legacy writes are disabled in layout tests.');
    }
    $testConfig=access_config(); // Enforces the exact loopback:3307 test DSN/account.
    if(access_service()->repo->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1') {
        throw new RuntimeException('Unverified synthetic layout database.');
    }
    $mysqli=new mysqli('127.0.0.1',$testConfig['username'],$testConfig['password'],'tivoli_auth_test',3307);
    $mysqli->set_charset('utf8mb4');
    $mysqli->query("SET time_zone = '+00:00'");
    $mysqli->query('SET SESSION TRANSACTION READ ONLY');
    date_default_timezone_set('UTC');
    return;
}
require_once dirname(__DIR__) . '/config/database.local.php';