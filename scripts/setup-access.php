<?php
if (PHP_SAPI!=='cli' || ($argv[1] ?? '')!=='--confirmed-local') { exit("Use CLI with --confirmed-local after verifying the local database.\n"); }
require dirname(__DIR__).'/bootstrap/security.php';
$config=access_config();
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=330[67];dbname=(tivoli|tivoli_auth_test);charset=utf8mb4$/D',$config['dsn'])) { throw new RuntimeException('Unapproved setup target.'); }
$service=access_service(); $repo=$service->repo;
foreach(array_merge(require dirname(__DIR__).'/database/migrations/001_access_control.php',require dirname(__DIR__).'/database/migrations/002_reset_delivery_queue.php') as $sql) { $repo->db->exec($sql); }
(require dirname(__DIR__).'/database/migrations/003_queue_claim_ownership.php')($repo->db);
$repo->db->exec('CREATE TABLE IF NOT EXISTS security_schema (version INT PRIMARY KEY) ENGINE=InnoDB');
if ($repo->run('SELECT version FROM security_schema WHERE version=3')->fetchColumn()) { exit("Access-control schema already installed; existing assignments preserved.\n"); }
if ($repo->run('SELECT version FROM security_schema WHERE version=2')->fetchColumn()) {
    $service->transaction(function() use($service,$repo){$service->audit(null,'system.access_upgraded','schema','3','succeeded');$repo->run('INSERT INTO security_schema(version) VALUES(3)');});
    exit("Recovery queue ownership protection installed.\n");
}
if ($repo->run('SELECT version FROM security_schema WHERE version=1')->fetchColumn()) {
    $service->transaction(function() use($service,$repo){$service->audit(null,'system.access_upgraded','schema','3','succeeded');$repo->run('INSERT INTO security_schema(version) VALUES(2),(3)');});
    exit("Recovery delivery queue installed. Existing assignments preserved.\n");
}
$service->transaction(function() use($service,$repo) {
    $privileges=[
        'dashboard.view'=>'View dashboard','bookings.manage'=>'Manage stays and guest extras',
        'rooms.manage'=>'Manage rooms','extras.manage'=>'Manage extra catalogue','stock.manage'=>'Manage stock',
        'sales.view'=>'View extra sales','reports.view'=>'View property reports','payments.view'=>'View payment history',
        'expenses.manage'=>'Manage expenses','recovery.manage'=>'Historical recovery',
        'users.manage'=>'Manage staff accounts','roles.manage'=>'Manage roles and role privileges',
        'roles.assign'=>'Assign staff roles','privileges.manage'=>'Create custom privilege definitions','audit.view'=>'View audit trail'
    ];
    foreach($privileges as $code=>$description){$repo->run('INSERT IGNORE INTO security_privileges(code,description) VALUES(?,?)',[$code,$description]);}
    foreach(['admin'=>'Administrator','clerk'=>'Reception clerk'] as $name=>$description){$repo->run('INSERT IGNORE INTO security_roles(name,description,system_role) VALUES(?,?,1)',[$name,$description]);}
    $repo->run("INSERT IGNORE INTO security_role_privileges(role_id,privilege_id) SELECT r.id,p.id FROM security_roles r CROSS JOIN security_privileges p WHERE r.name='admin'");
    foreach(['dashboard.view','bookings.manage','rooms.manage','extras.manage','stock.manage','sales.view'] as $code){$repo->run("INSERT IGNORE INTO security_role_privileges(role_id,privilege_id) SELECT r.id,p.id FROM security_roles r CROSS JOIN security_privileges p WHERE r.name='clerk' AND p.code=?",[$code]);}
    $repo->run('INSERT IGNORE INTO security_accounts(user_id) SELECT id FROM users');
    $repo->run('INSERT IGNORE INTO security_user_roles(user_id,role_id) SELECT u.id,r.id FROM users u JOIN security_roles r ON r.name=u.role');
    $service->audit(null,'system.access_installed','schema','1','succeeded',['legacy_ids_preserved'=>true]);
    $repo->run('INSERT INTO security_schema(version) VALUES(1),(2),(3)');
});
echo "Access-control schema installed. Existing users and financial records preserved.\n";