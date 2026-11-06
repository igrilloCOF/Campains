<?php
declare(strict_types=1);
namespace App\Core;
final class Security
{
    public static function boot(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('diabetes_session');
            session_set_cookie_params(['httponly'=>true,'secure'=>(getenv('SESSION_SECURE')?:'true')==='true','samesite'=>getenv('SESSION_SAMESITE')?:'Lax','path'=>'/']);
            session_start();
        }
    }
    public static function csrf(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
    public static function verifyCsrf(): void { $t=(string)($_POST['_csrf']??''); if(!hash_equals(self::csrf(),$t)){http_response_code(403);exit('Solicitud no válida. Recarga la página e inténtalo de nuevo.');} }
    public static function e(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    public static function user(): ?array { return $_SESSION['user']??null; }
    public static function requireRole(string $role): array { $u=self::requireLogin(); if($u['role']!==$role){http_response_code(403);exit('No tienes permiso para acceder a esta página.');} return $u; }
    public static function requireLogin(): array
    {
        $u=self::user(); if(!$u){header('Location: ?r=login');exit;}
        $q=Database::connection()->prepare('SELECT u.enabled,u.role,u.pharmacy_id,u.must_change_password,p.enabled AS pharmacy_enabled FROM users u LEFT JOIN pharmacies p ON p.id=u.pharmacy_id WHERE u.id=?');
        $q->execute([(int)$u['id']]);$fresh=$q->fetch();
        if(!$fresh || (int)$fresh['enabled']!==1 || $fresh['role']!==$u['role'] || ($u['role']==='pharmacy' && ((int)$fresh['pharmacy_enabled']!==1 || (int)$fresh['pharmacy_id']!==(int)$u['pharmacy_id']))) {
            $_SESSION=[];session_regenerate_id(true);header('Location: ?r=login&disabled=1');exit;
        }
        $_SESSION['user']['must_change_password']=(bool)$fresh['must_change_password'];
        return $_SESSION['user'];
    }
    public static function rotate(): void { session_regenerate_id(true); }
}
