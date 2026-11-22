<?php
// Router for the isolated legacy route contract. The temporary table lasts one request.
if (PHP_SAPI !== 'cli-server' || getenv('TIVOLI_TEST_MODE') !== '1'
    || ($_SERVER['SERVER_PORT'] ?? '') !== getenv('TIVOLI_TEST_ROUTE_PORT')) {
    http_response_code(404);
    exit;
}
if (($_GET['_test_no_stay_marker'] ?? '') === '1') {
    require_once dirname(__DIR__,2).'/bootstrap/security.php';
    $repo = access_service()->repo;
    if ($repo->run('SELECT marker FROM security_test_marker')->fetchColumn() !== 'synthetic-auth-only-v1') {
        http_response_code(503);
        exit;
    }
    $repo->db->exec('CREATE TEMPORARY TABLE stay_schema (version INT PRIMARY KEY)');
}
return false;

