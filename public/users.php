<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
$service=access_service(); $repo=$service->repo; $error=''; $notice='';
$permissions=$repo->privileges((int)$user['id']);
$can=static fn(string $code): bool=>in_array($code,$permissions,true);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        access_check_csrf();
        foreach($_POST as $key=>$value){if($key!=='ids' && !is_string($value)){throw new RuntimeException('Invalid form input.');}}
        $service->manage((int)$user['id'],$_POST['current_password'] ?? '',$_POST['action'] ?? '',$_POST);
        header('Location: users.php?saved=1'); exit;
    } catch(Throwable $exception){
        $error=access_error($exception);
        try{$service->audit((int)$user['id'],'access.form_rejected','access_control',null,'denied',['reason'=>'validation_permission_or_storage_failure']);}
        catch(Throwable $auditError){http_response_code(503);$error='Access management is temporarily unavailable.';}
        if($exception instanceof PDOException){http_response_code(503);}
    }
}
if (isset($_GET['delete'])) { $service->audit((int)$user['id'],'access.legacy_delete','user',null,'denied'); $error='Accounts are retained. Use the deactivation action instead.'; http_response_code(405); }
$notice=isset($_GET['saved'])?'Access changes saved. Affected sessions have been revoked where appropriate.':'';
$users=$repo->run('SELECT u.id,u.name,u.email,u.role,a.active,a.must_change_password FROM users u JOIN security_accounts a ON a.user_id=u.id ORDER BY u.name,u.id')->fetchAll();
$roles=$repo->run('SELECT id,name,description,system_role FROM security_roles ORDER BY name')->fetchAll();
$privileges=$repo->run('SELECT id,code,description FROM security_privileges ORDER BY code')->fetchAll();
$userRoles=[];foreach($repo->run('SELECT user_id,role_id FROM security_user_roles')->fetchAll() as $mapping){$userRoles[$mapping['user_id']][]=(int)$mapping['role_id'];}
$rolePrivileges=[];foreach($repo->run('SELECT role_id,privilege_id FROM security_role_privileges')->fetchAll() as $mapping){$rolePrivileges[$mapping['role_id']][]=(int)$mapping['privilege_id'];}
function access_management_fields(string $action, int $id=0): void {
    static $counter=0; $counter++;
    echo '<input type="hidden" name="csrf" value="'.access_h(access_csrf()).'"><input type="hidden" name="action" value="'.access_h($action).'"><input type="hidden" name="id" value="'.$id.'">';
    echo '<label for="reason'.$counter.'">Reason for this change</label><input id="reason'.$counter.'" name="reason" minlength="5" maxlength="500" required>';
    echo '<label for="operator'.$counter.'">Confirm your current password</label><div class="password"><input id="operator'.$counter.'" name="current_password" type="password" autocomplete="current-password" required><button type="button" class="secondary" data-password-toggle="operator'.$counter.'" aria-pressed="false" aria-label="Show password">Show</button></div>';
}
require dirname(__DIR__).'/templates/access/manage.php';