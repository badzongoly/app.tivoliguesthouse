<?php
namespace Tivoli\Service;
use Tivoli\Repository\AccessRepository;
use RuntimeException;
use Throwable;
final class AccessService
{
    public function __construct(public readonly AccessRepository $repo, private readonly string $requestId, private readonly string $ip, private readonly string $auditKey) {}
    public function audit(?int $actor, string $action, string $type, ?string $target, string $outcome, array $details = []): void
    {
        // Callers supply only explicit non-secret metadata, never raw requests or user rows.
        if ($actor !== null) { $details['actor_roles'] = $this->repo->run('SELECT r.name FROM security_roles r JOIN security_user_roles ur ON ur.role_id=r.id WHERE ur.user_id=? ORDER BY r.name', [$actor])->fetchAll(\PDO::FETCH_COLUMN); }
        $this->repo->run('INSERT INTO security_audit(actor_id,action,target_type,target_id,outcome,request_id,ip_hash,details) VALUES(?,?,?,?,?,?,?,?)', [$actor,$action,$type,$target,$outcome,$this->requestId,hash_hmac('sha256',$this->ip,$this->auditKey),json_encode($details, JSON_THROW_ON_ERROR)]);
    }
    public function transaction(callable $action): mixed
    {
        $db=$this->repo->db;
        $db->beginTransaction();
        try {
            // Serialises account/role/reset decisions, including last-admin checks.
            $this->repo->run('SELECT id FROM security_state WHERE id=1 FOR UPDATE');
            $result=$action();
            $db->commit();
            return $result;
        } catch (Throwable $error) {
            if ($db->inTransaction()) { $db->rollBack(); }
            throw $error;
        }
    }
    private function throttle(string $purpose, string $identity, int $limit): bool
    {
        $bucket=hash_hmac('sha256',$purpose.'|'.$identity,$this->auditKey);
        $row=$this->repo->run('SELECT attempts,started_at FROM security_throttle WHERE bucket=?',[$bucket])->fetch();
        if (!$row || strtotime($row['started_at'].' UTC') < time()-900) {
            $this->repo->run('INSERT INTO security_throttle(bucket,attempts,started_at) VALUES(?,1,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE attempts=1,started_at=UTC_TIMESTAMP()',[$bucket]);
            return true;
        }
        if ((int)$row['attempts'] >= $limit) { return false; }
        $this->repo->run('UPDATE security_throttle SET attempts=attempts+1 WHERE bucket=?',[$bucket]);
        return true;
    }
    public function login(string $email, string $password): ?array
    {
        return $this->transaction(function() use($email,$password) {
            $email=strtolower(trim($email));
            $byIp=$this->throttle('login.ip',$this->ip,30);
            $byEmail=$this->throttle('login.account',$email,8);
            if (!$byIp || !$byEmail) {
                $this->audit(null,'auth.login','user',null,'denied',['reason'=>'throttled']);
                return null;
            }
            $row=$this->repo->run('SELECT id FROM users WHERE email=?',[$email])->fetch();
            $user=$row ? $this->repo->user((int)$row['id']) : null;
            // Fixed valid dummy hash keeps unknown-account password verification work comparable.
            $dummy='$2y$12$yRomN1qLta0eQ1Lnc8MQB.JWXO0YmD8C5Wi/BmxaIZlSO9oI6P9SW';
            $verified=password_verify($password,$user['password_hash'] ?? $dummy);
            if (!$byIp || !$byEmail || !$verified || !$user || !$user['active']) {
                $this->audit(null,'auth.login','user',$user ? (string)$user['id'] : null,'denied',['reason'=>(!$byIp || !$byEmail) ? 'throttled' : 'invalid_credentials']);
                return null;
            }
            if (password_needs_rehash($user['password_hash'],PASSWORD_BCRYPT,['cost'=>12])) {
                $this->repo->run('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_BCRYPT,['cost'=>12]),$user['id']]);
            }
            $this->repo->run('DELETE FROM security_throttle WHERE bucket=?',[hash_hmac('sha256','login.account|'.$email,$this->auditKey)]);
            $this->repo->run('UPDATE security_throttle SET attempts=GREATEST(0,attempts-1) WHERE bucket=?',[hash_hmac('sha256','login.ip|'.$this->ip,$this->auditKey)]);
            $this->audit((int)$user['id'],'auth.login','user',(string)$user['id'],'succeeded');
            return $this->publicUser($user);
        });
    }
    public function publicUser(array $user): array
    {
        return array_intersect_key($user,array_flip(['id','name','email','role','session_version','must_change_password']));
    }
    public function requirePrivilege(int $actor, string $privilege): void
    {
        $user=$this->repo->user($actor);
        if (!$user || !$user['active'] || !in_array($privilege,$this->repo->privileges($actor),true)) {
            $this->audit($user ? $actor : null,'access.denied','privilege',$privilege,'denied');
            throw new RuntimeException('You do not have permission to perform this action.');
        }
    }
    public function reauthenticate(int $actor,string $password): void { $this->confirmPassword($actor,$password); }
    private function confirmPassword(int $actor, string $password): void
    {
        $accepted=$this->transaction(function() use($actor,$password) {
            $byAccount=$this->throttle('reauth.account',(string)$actor,8);
            $byIp=$this->throttle('reauth.ip',$this->ip,30);
            $user=$this->repo->user($actor);
            if (!$byAccount || !$byIp || !$user || !$user['active'] || !password_verify($password,$user['password_hash'])) {
                $this->audit($user?$actor:null,'auth.reauthentication','user',(string)$actor,'denied',['reason'=>(!$byAccount || !$byIp)?'throttled':'invalid_credentials']);
                return false;
            }
            // Only failed confirmations accumulate toward the persistent limit.
            $bucket=hash_hmac('sha256','reauth.account|'.$actor,$this->auditKey);
            $this->repo->run('DELETE FROM security_throttle WHERE bucket=?',[$bucket]);
            $this->repo->run('UPDATE security_throttle SET attempts=GREATEST(0,attempts-1) WHERE bucket=?',[hash_hmac('sha256','reauth.ip|'.$this->ip,$this->auditKey)]);
            $this->audit($actor,'auth.reauthentication','user',(string)$actor,'succeeded');
            return true;
        });
        if (!$accepted) { throw new RuntimeException('Password confirmation failed or is temporarily limited.'); }
    }
    public function assertTargetAuthority(int $actor,int $target): void { $this->protectTarget($actor,$target); }
    private function protectTarget(int $actor, int $target): void
    {
        if (!$this->repo->user($target)) { throw new RuntimeException('User not found.'); }
        if ($this->repo->isAdmin($actor)) { return; }
        if ($this->repo->isAdmin($target) || array_diff($this->repo->privileges($target),$this->repo->privileges($actor))) {
            throw new RuntimeException('You cannot change an account above your authority.');
        }
    }
    private function passwordPolicy(string $password): void
    {
        if (strlen($password)<12 || strlen($password)>72 || str_contains($password,"\0")) {
            throw new RuntimeException('Use a password between 12 and 72 bytes. Long unique passphrases are recommended.');
        }
    }
    public function changePassword(int $actor, string $current, string $next): void
    {
        $this->passwordPolicy($next);
        $this->confirmPassword($actor,$current);
        $this->transaction(function() use($actor,$current,$next) {
            $user=$this->repo->user($actor);
            if (!$user || !$user['active'] || !password_verify($current,$user['password_hash'])) {
                throw new RuntimeException('The current password is incorrect.');
            }
            if (password_verify($next,$user['password_hash'])) { throw new RuntimeException('Choose a different password.'); }
            $this->setPassword($actor,$next);
            $this->audit($actor,'auth.password_changed','user',(string)$actor,'succeeded',['sessions_revoked'=>true]);
        });
    }
    private function setPassword(int $id, string $password): void
    {
        $this->repo->run('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_BCRYPT,['cost'=>12]),$id]);
        $this->repo->run('UPDATE security_accounts SET session_version=session_version+1,must_change_password=0 WHERE user_id=?',[$id]);
        $this->repo->run('UPDATE security_password_resets SET consumed_at=UTC_TIMESTAMP() WHERE user_id=? AND consumed_at IS NULL',[$id]);
    }
    public function requestReset(string $email): ?array
    {
        return $this->transaction(function() use($email) {
            $email=strtolower(trim($email));
            $ipAllowed=$this->throttle('reset.ip',$this->ip,10);
            $emailAllowed=$this->throttle('reset.account',$email,3);
            $row=$this->repo->run('SELECT u.id,u.email FROM users u JOIN security_accounts a ON a.user_id=u.id WHERE u.email=? AND a.active=1',[$email])->fetch();
            $this->audit(null,'auth.reset_requested','user',null,'recorded',['accepted'=>$ipAllowed && $emailAllowed]);
            if (!$ipAllowed || !$emailAllowed) { return null; }
            if (!$row) {
                $this->repo->run('INSERT INTO security_mail_queue(payload) VALUES(?)',[\Tivoli\Infrastructure\ResetEnvelope::encode(['email'=>null],$this->auditKey)]);
                return null;
            }
            $token=bin2hex(random_bytes(32));
            $this->repo->run('UPDATE security_password_resets SET consumed_at=UTC_TIMESTAMP() WHERE user_id=? AND consumed_at IS NULL',[$row['id']]);
            $this->repo->run('INSERT INTO security_password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE))',[$row['id'],hash('sha256',$token)]);
            $delivery=['email'=>$row['email'],'token'=>$token];
            $this->repo->run('INSERT INTO security_mail_queue(payload) VALUES(?)',[\Tivoli\Infrastructure\ResetEnvelope::encode($delivery,$this->auditKey)]);
            $this->audit(null,'auth.reset_queued','user',null,'succeeded');
            return $delivery;
        });
    }
    public function resetPassword(string $token, string $password): bool
    {
        $this->passwordPolicy($password);
        return $this->transaction(function() use($token,$password) {
            $allowed=$this->throttle('reset.consume',$this->ip,20);
            $row=$this->repo->run('SELECT r.id,r.user_id FROM security_password_resets r JOIN security_accounts a ON a.user_id=r.user_id WHERE r.token_hash=? AND r.consumed_at IS NULL AND r.expires_at>UTC_TIMESTAMP() AND a.active=1 FOR UPDATE',[hash('sha256',$token)])->fetch();
            if (!$allowed || !$row || !preg_match('/^[a-f0-9]{64}$/D',$token)) {
                $this->audit(null,'auth.reset_completed','user',null,'denied',['reason'=>'invalid_expired_or_throttled']);
                return false;
            }
            $this->setPassword((int)$row['user_id'],$password);
            $this->audit(null,'auth.reset_completed','user',(string)$row['user_id'],'succeeded',['sessions_revoked'=>true]);
            return true;
        });
    }
    public function assertAdministratorAvailable(): void { $this->assertAdministratorRetained(); }
    private function assertAdministratorRetained(): void
    {
        $hasHr=(bool)$this->repo->run("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='hr_schema'")->fetchColumn();
        $sql="SELECT COUNT(*) FROM security_user_roles ur JOIN security_roles r ON r.id=ur.role_id JOIN security_accounts a ON a.user_id=ur.user_id".($hasHr?' LEFT JOIN hr_staff h ON h.user_id=a.user_id':'')." WHERE r.name='admin' AND a.active=1".($hasHr?" AND (h.id IS NULL OR h.status NOT IN ('exit_scheduled','exited','suspended'))":'');
        $count=$this->repo->run($sql)->fetchColumn();
        if ((int)$count<1) { throw new RuntimeException('At least one active administrator without a pending exit must remain.'); }
    }
    public function manage(int $actor, string $currentPassword, string $action, array $input): void
    {
        $permission=match($action) {
            'save_user','set_active','revoke_sessions'=>'users.manage',
            'create_role','assign_privileges'=>'roles.manage',
            'create_privilege'=>'privileges.manage',
            'assign_roles'=>'roles.assign',
            default=>throw new RuntimeException('Unknown action.')
        };
        try {
            $this->confirmPassword($actor,$currentPassword);
            $this->transaction(function() use($actor,$currentPassword,$action,$input,$permission) {
                $this->requirePrivilege($actor,$permission);
                $operator=$this->repo->user($actor);
                if (!$operator || !password_verify($currentPassword,$operator['password_hash'])) { throw new RuntimeException('Confirm your current password to make access changes.'); }
                $reason=trim((string)($input['reason'] ?? ''));
                if (strlen($reason)<5 || strlen($reason)>500) { throw new RuntimeException('Provide a reason between 5 and 500 characters.'); }
                $id=filter_var($input['id'] ?? 0,FILTER_VALIDATE_INT);
                if ($id===false || $id<0) { throw new RuntimeException('Invalid identifier.'); }
                if ($id>0 && in_array($action,['save_user','set_active','revoke_sessions','assign_roles'],true)) { $this->protectTarget($actor,$id); }
                $before=[]; $after=[]; $type='user';
                if ($action==='save_user') {
                    $name=trim((string)($input['name'] ?? '')); $email=strtolower(trim((string)($input['email'] ?? '')));
                    if ($name==='' || strlen($name)>120 || strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Enter a valid name and email.'); }
                    if ($id) {
                        $old=$this->repo->user($id); if (!$old) { throw new RuntimeException('User not found.'); }
                        $before=['name'=>$old['name'],'email'=>$old['email']];
                        $this->repo->run('UPDATE users SET name=?,email=? WHERE id=?',[$name,$email,$id]);
                        if ($old['email']!==$email) { $this->repo->run('UPDATE security_password_resets SET consumed_at=UTC_TIMESTAMP() WHERE user_id=? AND consumed_at IS NULL',[$id]); }
                        $this->repo->run('UPDATE security_accounts SET session_version=session_version+1 WHERE user_id=?',[$id]);
                    } else {
                        $clerkPrivileges=$this->repo->run("SELECT p.code FROM security_privileges p JOIN security_role_privileges rp ON rp.privilege_id=p.id JOIN security_roles r ON r.id=rp.role_id WHERE r.name='clerk'")->fetchAll(\PDO::FETCH_COLUMN);
                        if (array_diff($clerkPrivileges,$this->repo->privileges($actor))) { throw new RuntimeException('You cannot create a clerk with privileges you do not hold.'); }
                        $password=(string)($input['password'] ?? ''); $this->passwordPolicy($password);
                        $this->repo->run("INSERT INTO users(name,email,role,password_hash) VALUES(?,?,'clerk',?)",[$name,$email,password_hash($password,PASSWORD_BCRYPT,['cost'=>12])]);
                        $id=(int)$this->repo->db->lastInsertId();
                        $this->repo->run('INSERT INTO security_accounts(user_id,must_change_password) VALUES(?,1)',[$id]);
                        $this->repo->run("INSERT INTO security_user_roles(user_id,role_id) SELECT ?,id FROM security_roles WHERE name='clerk'",[$id]);
                    }
                    $after=['name'=>$name,'email'=>$email];
                } elseif ($action==='set_active' || $action==='revoke_sessions') {
                    $old=$this->repo->user($id); if (!$old) { throw new RuntimeException('User not found.'); }
                    if ($action==='set_active') {
                        if (!in_array($input['active'] ?? '',['0','1'],true)) { throw new RuntimeException('Invalid account state.'); }
                        if ($id===$actor && $input['active']==='0') { throw new RuntimeException('You cannot deactivate your own account.'); }
                        $before=['active'=>(int)$old['active']]; $after=['active'=>(int)$input['active']];
                        $this->repo->run('UPDATE security_accounts SET active=?,session_version=session_version+1 WHERE user_id=?',[$after['active'],$id]);
                        $this->repo->run('UPDATE security_password_resets SET consumed_at=UTC_TIMESTAMP() WHERE user_id=? AND consumed_at IS NULL',[$id]);
                    } else { $this->repo->run('UPDATE security_accounts SET session_version=session_version+1 WHERE user_id=?',[$id]); $after=['sessions_revoked'=>true]; }
                } elseif ($action==='create_role' || $action==='create_privilege') {
                    $name=trim((string)($input['code'] ?? '')); $description=trim((string)($input['description'] ?? ''));
                    if (!preg_match('/^[a-z][a-z0-9_.-]{2,59}$/D',$name) || $description==='' || strlen($description)>255) { throw new RuntimeException('Use a unique lowercase code and a description of up to 255 characters.'); }
                    $type=$action==='create_role'?'role':'privilege';
                    if ($type==='privilege' && !str_starts_with($name,'custom.')) { throw new RuntimeException('New privilege codes must start with custom.'); }
                    $sql=$type==='role'?'INSERT INTO security_roles(name,description) VALUES(?,?)':'INSERT INTO security_privileges(code,description) VALUES(?,?)';
                    $this->repo->run($sql,[$name,$description]); $id=(int)$this->repo->db->lastInsertId();
                    if ($type==='privilege') { $this->repo->run("INSERT INTO security_role_privileges(role_id,privilege_id) SELECT id,? FROM security_roles WHERE name='admin'",[$id]); }
                    $after=['code'=>$name,'description'=>$description];
                } elseif ($action==='assign_roles') {
                    if (!$this->repo->user($id)) { throw new RuntimeException('User not found.'); }
                    $ids=$this->validatedIds($input['ids'] ?? []);
                    $roles=$this->repo->run('SELECT id,name FROM security_roles')->fetchAll();
                    $known=array_map('intval',array_column($roles,'id'));
                    if (array_diff($ids,$known)) { throw new RuntimeException('Unknown role.'); }
                    $actorPrivileges=$this->repo->privileges($actor); $actorIsAdmin=$this->repo->isAdmin($actor);
                    $before=['role_ids'=>array_map('intval',$this->repo->run('SELECT role_id FROM security_user_roles WHERE user_id=?',[$id])->fetchAll(\PDO::FETCH_COLUMN))];
                    $this->repo->run('DELETE FROM security_user_roles WHERE user_id=?',[$id]);
                    foreach($ids as $roleId) {
                        $codes=$this->repo->run('SELECT p.code FROM security_role_privileges rp JOIN security_privileges p ON p.id=rp.privilege_id WHERE rp.role_id=?',[$roleId])->fetchAll(\PDO::FETCH_COLUMN);
                        if (array_diff($codes,$actorPrivileges) || (!$actorIsAdmin && in_array($roleId,array_map('intval',array_column(array_filter($roles,fn($r)=>$r['name']==='admin'),'id')),true))) { throw new RuntimeException('You cannot grant a role above your authority.'); }
                        $this->repo->run('INSERT INTO security_user_roles(user_id,role_id) VALUES(?,?)',[$id,$roleId]);
                    }
                    $this->repo->run('UPDATE users SET role=? WHERE id=?',[$this->repo->isAdmin($id)?'admin':'clerk',$id]);
                    $this->repo->run('UPDATE security_accounts SET session_version=session_version+1 WHERE user_id=?',[$id]);
                    $after=['role_ids'=>$ids];
                } elseif ($action==='assign_privileges') {
                    $type='role'; $role=$this->repo->run('SELECT name,system_role FROM security_roles WHERE id=?',[$id])->fetch();
                    if (!$role) { throw new RuntimeException('Role not found.'); }
                    if ($role['name']==='admin') { throw new RuntimeException('The administrator role retains all built-in privileges.'); }
                    $existingCodes=$this->repo->run('SELECT p.code FROM security_role_privileges rp JOIN security_privileges p ON p.id=rp.privilege_id WHERE rp.role_id=?',[$id])->fetchAll(\PDO::FETCH_COLUMN);
                    if (!$this->repo->isAdmin($actor) && array_diff($existingCodes,$this->repo->privileges($actor))) { throw new RuntimeException('You cannot change a role above your authority.'); }
                    $ids=$this->validatedIds($input['ids'] ?? []);
                    $all=$this->repo->run('SELECT id,code FROM security_privileges')->fetchAll();
                    $selected=array_filter($all,fn($p)=>in_array((int)$p['id'],$ids,true));
                    if (count($selected)!==count($ids) || array_diff(array_column($selected,'code'),$this->repo->privileges($actor))) { throw new RuntimeException('You cannot grant unknown privileges or privileges you do not hold.'); }
                    $before=['privilege_ids'=>array_map('intval',$this->repo->run('SELECT privilege_id FROM security_role_privileges WHERE role_id=?',[$id])->fetchAll(\PDO::FETCH_COLUMN))];
                    $this->repo->run('DELETE FROM security_role_privileges WHERE role_id=?',[$id]);
                    foreach($ids as $privilegeId){$this->repo->run('INSERT INTO security_role_privileges(role_id,privilege_id) VALUES(?,?)',[$id,$privilegeId]);}
                    $this->repo->run('UPDATE security_accounts SET session_version=session_version+1 WHERE user_id IN (SELECT user_id FROM security_user_roles WHERE role_id=?)',[$id]);
                    $after=['privilege_ids'=>$ids];
                }
                $this->assertAdministratorRetained();
                $this->audit($actor,'access.'.$action,$type,(string)$id,'succeeded',['reason'=>$reason,'before'=>$before,'after'=>$after]);
            });
        } catch (Throwable $error) {
            $this->audit($actor,'access.'.$action,'access_control',null,'failed',['reason'=>'validation_permission_or_storage_failure']);
            throw $error;
        }
    }
    private function validatedIds(mixed $values): array
    {
        if (!is_array($values) || count($values)>200) { throw new RuntimeException('Invalid selection.'); }
        $result=[];
        foreach($values as $value){$id=filter_var($value,FILTER_VALIDATE_INT);if($id===false || $id<1){throw new RuntimeException('Invalid selection.');}$result[]=$id;}
        return array_values(array_unique($result));
    }
}