<?php
if (is_file(dirname(__DIR__).'/vendor/autoload.php')) { require_once dirname(__DIR__).'/vendor/autoload.php'; }
spl_autoload_register(static function(string $class): void {
    $prefix='Tivoli\\';
    if (str_starts_with($class,$prefix)) {
        $path=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
        if (is_file($path)) { require_once $path; }
    }
});
function access_config(): array {
    static $config;
    if ($config) { return $config; }
    $testing=getenv('TIVOLI_TEST_MODE')==='1';
    $config=require dirname(__DIR__).($testing?'/config/access-test.local.php':'/config/access.local.php');
    if ($testing && ($config['dsn']!=='mysql:host=127.0.0.1;port=3307;dbname=tivoli_auth_test;charset=utf8mb4' || $config['username']!=='tivoli_auth_test')) { throw new RuntimeException('Unsafe test configuration.'); }
    return $config;
}
function access_service(): \Tivoli\Service\AccessService {
    static $service;
    if ($service) { return $service; }
    $config=access_config();
    $pdo=new PDO($config['dsn'],$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $service=new \Tivoli\Service\AccessService(new \Tivoli\Repository\AccessRepository($pdo),bin2hex(random_bytes(16)),(string)($_SERVER['REMOTE_ADDR'] ?? 'cli'),$config['audit_key']);
}
function access_session(): void {
    if (session_status()===PHP_SESSION_ACTIVE) { return; }
    ini_set('session.use_strict_mode','1');
    ini_set('session.use_only_cookies','1');
    session_set_cookie_params(['lifetime'=>0,'path'=>parse_url(access_config()['base_url'],PHP_URL_PATH) ?: '/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
    session_start();
    if (getenv('TIVOLI_TEST_MODE')==='1') { header('X-Tivoli-Test-Mode: synthetic'); }
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
}
function access_url(string $path=''): string { return rtrim(access_config()['base_url'],'/').'/'.ltrim($path,'/'); }
// Format UTC dates for display. Keep original strings for inputs, exports and storage.
function display_date(?string $value): string {
    if ($value === null || $value === '') return '';
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2}:\d{2})(?:\.\d{1,6})?)?$/D',$value,$parts)
        && checkdate((int)$parts[2],(int)$parts[3],(int)$parts[1])) {
        return $parts[3].'/'.$parts[2].'/'.$parts[1].(isset($parts[4])?' '.$parts[4]:'');
    }
    if (preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/D',$value)) {
        return (new DateTimeImmutable($value.'-01',new DateTimeZone('UTC')))->format('M Y');
    }
    return $value;
}
function access_h(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function access_csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function access_check_csrf(): void {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals(access_csrf(),$_POST['csrf'])) {
        access_service()->audit(isset($_SESSION['user']['id'])?(int)$_SESSION['user']['id']:null,'security.csrf','request',null,'denied');
        http_response_code(403);
        throw new RuntimeException('This form expired. Reload the page and try again.');
    }
}
function access_destroy_session(): void {
    $_SESSION=[];
    if (ini_get('session.use_cookies')) {
        $params=session_get_cookie_params();
        setcookie(session_name(),'', ['expires'=>time()-42000,'path'=>$params['path'],'secure'=>$params['secure'],'httponly'=>true,'samesite'=>'Lax']);
    }
    session_destroy();
}
function access_current(): ?array {
    if (empty($_SESSION['user']['id'])) { return null; }
    $stored=$_SESSION['user'];
    require_once __DIR__.'/hr.php';
    if(hr_due_for_user((int)$stored['id'])){
        try{access_service()->audit((int)$stored['id'],'auth.session_invalidated','user',(string)$stored['id'],'denied',['reason'=>'hr_employment_inactive_or_exit_due']);}
        finally{access_destroy_session();session_id('');access_session();}
        return null;
    }
    $user=access_service()->repo->user((int)$stored['id']);
    $expired=time()-(int)($_SESSION['last_seen'] ?? 0)>1800 || time()-(int)($_SESSION['authenticated_at'] ?? 0)>28800;
    if (!$user || !$user['active'] || !isset($stored['session_version']) || (int)$stored['session_version']!==(int)$user['session_version'] || $expired) {
        access_service()->audit($user?(int)$user['id']:null,'auth.session_invalidated','user',$user?(string)$user['id']:null,'denied',['reason'=>$expired?'expired':'revoked_or_inactive']);
        access_destroy_session();
        session_id(''); access_session();
        return null;
    }
    $_SESSION['last_seen']=time();
    return $_SESSION['user']=access_service()->publicUser($user);
}
function access_home_path(array $user): string {
    if ($user['must_change_password']) return 'auth/change-password.php';
    if (getenv('TIVOLI_OWNER_REPORTS') !== '0') {
        $repo = access_service()->repo;
        $id = (int)$user['id'];
        if ($repo->isAdmin($id) && in_array('reports.view',$repo->privileges($id),true)) {
            return 'owner_reports.php';
        }
    }
    return 'dashboard.php';
}
function access_error(Throwable $error): string {
    if ($error instanceof PDOException) { return 'The request could not be completed. Check for duplicate details or contact your administrator.'; }
    return $error instanceof RuntimeException ? $error->getMessage() : 'The request could not be completed. Contact your administrator.';
}
function access_deliver_reset(array $delivery): void {
    (new \Tivoli\Infrastructure\ResetMailer(access_config(),dirname(__DIR__)))->deliver($delivery);
}