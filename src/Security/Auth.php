<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\AdminRepository;

final class Auth
{
    private const SESSION_KEY = 'admin';

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('sst_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => base_path() . '/admin/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function attempt(AdminRepository $admins, string $username, string $password): bool
    {
        $admin = $admins->findByUsername($username);

        // Dummy hash for unknown users so timing doesn't reveal valid usernames.
        $hash = $admin['password_hash'] ?? password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        if (!password_verify($password, $hash) || $admin === null) {
            return false;
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $admins->updatePasswordHash((int) $admin['id'], password_hash($password, PASSWORD_DEFAULT));
        }
        $admins->touchLastLogin((int) $admin['id']);

        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = [
            'id' => (int) $admin['id'],
            'username' => $admin['username'],
            'last_activity' => time(),
        ];

        return true;
    }

    /** @return array{id: int, username: string, last_activity: int}|null */
    public static function user(): ?array
    {
        $user = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($user)) {
            return null;
        }

        $idleSeconds = (int) config('admin.session_idle_minutes', 30) * 60;
        if (time() - (int) ($user['last_activity'] ?? 0) > $idleSeconds) {
            self::logout();

            return null;
        }
        $_SESSION[self::SESSION_KEY]['last_activity'] = time();

        return $user;
    }

    public static function isAuthenticated(): bool
    {
        self::startSession();
        $user = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($user)) {
            return false;
        }

        $idleSeconds = (int) config('admin.session_idle_minutes', 30) * 60;
        if (time() - (int) ($user['last_activity'] ?? 0) > $idleSeconds) {
            self::logout();

            return false;
        }

        return true;
    }

    /** @return array{id: int, username: string, last_activity: int} */
    public static function requireLogin(bool $json = false): array
    {
        self::startSession();
        $wasSignedIn = isset($_SESSION[self::SESSION_KEY]);
        $user = self::user();
        if ($user !== null) {
            return $user;
        }
        if ($json) {
            json_response(['ok' => false, 'error' => 'Please sign in to continue.'], 401);
        }
        redirect('admin/login.php' . ($wasSignedIn ? '?expired=1' : ''));
    }

    public static function logout(): void
    {
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
        session_destroy();
    }
}
