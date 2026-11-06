<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
use PDO;
use RuntimeException;
final class AuthService
{
    private const WINDOW_MINUTES = 15;
    private const MAX_FAILURES = 5;

    public function login(string $username, string $password): bool
    {
        $db = Database::connection();
        $usernameKey = $this->key('u:'.strtolower(trim($username)));
        $ipKey = $this->key('ip:'.(string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if ($this->isRateLimited($db, $usernameKey, $ipKey)) {
            $this->audit(null, 'login_rate_limited', 'user', null, 'failure');
            return false;
        }
        $q = $db->prepare('SELECT u.id,u.username,u.role,u.pharmacy_id,u.password_hash,u.enabled,u.must_change_password,p.enabled AS pharmacy_enabled FROM users u LEFT JOIN pharmacies p ON p.id=u.pharmacy_id WHERE u.username=:u LIMIT 1');
        $q->execute(['u'=>$username]); $row = $q->fetch();
        $valid = $row && (int)$row['enabled'] === 1
            && ($row['role'] !== 'pharmacy' || (int)$row['pharmacy_enabled'] === 1)
            && password_verify($password, $row['password_hash']);
        $this->recordAttempt($db, $usernameKey, $ipKey, (bool)$valid);
        if (!$valid) {
            $this->audit($row['id'] ?? null, 'login', 'user', $row['id'] ?? null, 'failure');
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user'] = ['id'=>(int)$row['id'],'username'=>$row['username'],'role'=>$row['role'],
            'pharmacy_id'=>$row['pharmacy_id']?(int)$row['pharmacy_id']:null,
            'must_change_password'=>(bool)$row['must_change_password']];
        $this->audit((int)$row['id'], 'login', 'user', (int)$row['id'], 'success');
        return true;
    }

    public function changeOwnPassword(int $userId, string $currentPassword, string $newPassword): void
    {
        if (strlen($newPassword) < 14) throw new RuntimeException('La nueva contraseña debe tener al menos 14 caracteres.');
        if (strlen($newPassword) > 4096) throw new RuntimeException('La contraseña es demasiado larga.');
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $q = $db->prepare('SELECT password_hash,must_change_password FROM users WHERE id=? AND enabled=1 FOR UPDATE');
            $q->execute([$userId]); $user = $q->fetch();
            if (!$user) throw new RuntimeException('No se encontró una cuenta activa.');
            if (!(bool)$user['must_change_password'] && !password_verify($currentPassword, $user['password_hash'])) throw new RuntimeException('La contraseña actual no es correcta.');
            if (password_verify($newPassword, $user['password_hash'])) throw new RuntimeException('Elige una contraseña distinta de la actual.');
            $up = $db->prepare('UPDATE users SET password_hash=?,must_change_password=0 WHERE id=?');
            $up->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
            $db->prepare("INSERT INTO audit_log(user_id,action,entity_type,entity_id,result,ip_address) VALUES(?,'password_change','user',?,'success',?)")
                ->execute([$userId,(string)$userId,substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)]);
            $db->commit();
            $_SESSION['user']['must_change_password'] = false;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function logout(): void
    {
        $u = $_SESSION['user'] ?? null;
        if ($u) $this->audit((int)$u['id'], 'logout', 'user', (int)$u['id'], 'success');
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $p['path'], $p['domain']??'', (bool)$p['secure'], (bool)$p['httponly']);
        }
        session_destroy();
    }

    private function isRateLimited(PDO $db, string $usernameKey, string $ipKey): bool
    {
        $q = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE succeeded=0 AND attempted_at >= (NOW() - INTERVAL '.self::WINDOW_MINUTES.' MINUTE) AND (username_key=? OR ip_key=?)');
        $q->execute([$usernameKey,$ipKey]);
        return (int)$q->fetchColumn() >= self::MAX_FAILURES;
    }
    private function recordAttempt(PDO $db, string $usernameKey, string $ipKey, bool $success): void
    {
        $db->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
        $q=$db->prepare('INSERT INTO login_attempts(username_key,ip_key,succeeded) VALUES(?,?,?)');
        $q->execute([$usernameKey,$ipKey,$success?1:0]);
        if ($success) {
            $db->prepare('DELETE FROM login_attempts WHERE username_key=? AND ip_key=?')->execute([$usernameKey,$ipKey]);
        }
    }
    private function key(string $value): string
    {
        $secret = (string)(getenv('APP_KEY') ?: '');
        if (strlen($secret) < 32) throw new RuntimeException('APP_KEY debe tener al menos 32 caracteres para usar autenticación.');
        return hash_hmac('sha256', $value, $secret);
    }
    private function audit(?int $user, string $action, string $type, mixed $id, string $result): void
    {
        try {
            $s=Database::connection()->prepare('INSERT INTO audit_log(user_id,action,entity_type,entity_id,result,ip_address) VALUES(?,?,?,?,?,?)');
            $s->execute([$user,$action,$type,$id===null?null:(string)$id,$result,substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)]);
        } catch (\Throwable $e) { error_log('Audit write failed: '.$e->getMessage()); }
    }
}
