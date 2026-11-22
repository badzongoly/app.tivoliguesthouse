<?php
namespace Tivoli\Repository;
use PDO;
final class AccessRepository
{
    public function __construct(public readonly PDO $db) {}
    public function run(string $sql, array $values = []): \PDOStatement
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($values);
        return $statement;
    }
    public function user(int $id): ?array
    {
        return $this->run('SELECT u.id,u.name,u.email,u.role,u.password_hash,a.active,a.session_version,a.must_change_password FROM users u JOIN security_accounts a ON a.user_id=u.id WHERE u.id=?', [$id])->fetch() ?: null;
    }
    public function privileges(int $id): array
    {
        return $this->run('SELECT DISTINCT p.code FROM security_user_roles ur JOIN security_role_privileges rp ON rp.role_id=ur.role_id JOIN security_privileges p ON p.id=rp.privilege_id WHERE ur.user_id=?', [$id])->fetchAll(PDO::FETCH_COLUMN);
    }
    public function isAdmin(int $id): bool
    {
        return (bool)$this->run("SELECT 1 FROM security_user_roles ur JOIN security_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.name='admin'", [$id])->fetchColumn();
    }
}