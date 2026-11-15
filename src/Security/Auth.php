<?php

declare(strict_types=1);

namespace App\Security;

use App\Exception\AppException;
use App\Exception\DatabaseException;
use App\Repository\AdminRepository;
use ErrorException;

final class Auth
{
    private const SESSION_KEY = 'admin';
    private const SESSION_NAME = 'sst_admin';

    /** @throws AppException when PHP cannot start the session (e.g. unwritable session directory) */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        try {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_trans_sid', '0');
            session_name(self::SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => base_path() . '/admin/',
                'secure' => is_https(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $started = session_start();
        } catch (ErrorException $e) {
            // The bootstrap error handler turns session warnings into ErrorException.
            throw new AppException('Could not start the admin session.', 0, $e);
        }
        if (!$started) {
            throw new AppException('Could not start the admin session.');
        }
    }

    /** @throws DatabaseException */
    public static function attempt(AdminRepository $admins, string $username, #[\SensitiveParameter] string $password): bool
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

        // New session ID and new CSRF token: nothing issued before sign-in stays valid after it.
        session_regenerate_id(true);
        Csrf::rotate();
        $_SESSION[self::SESSION_KEY] = [
            'id' => (int) $admin['id'],
            'username' => $admin['username'],
            'last_activity' => time(),
        ];

        return true;
    }

    /**
     * The signed-in admin, or null. Counts as activity, so it extends the idle timeout.
     * @return array{id: int, username: string, last_activity: int}|null
     */
    public static function user(): ?array
    {
        $user = self::sessionUser();
        if ($user === null) {
            return null;
        }
        $_SESSION[self::SESSION_KEY]['last_activity'] = time();

        return $user;
    }

    /**
     * A read-only check for the public site's sign-in indicator: it does not extend the idle
     * timeout, and it never creates a session for visitors who have no admin session cookie.
     */
    public static function isAuthenticated(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[self::SESSION_NAME])) {
            return false;
        }
        self::startSession();

        return self::sessionUser() !== null;
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
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * The admin stored in the session, or null when there is none, it is malformed or it has
     * been idle too long (in which case the session is ended).
     * @return array{id: int, username: string, last_activity: int}|null
     */
    private static function sessionUser(): ?array
    {
        $user = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($user) || !isset($user['id'], $user['username'], $user['last_activity'])) {
            return null;
        }

        $idleSeconds = max(1, (int) config('admin.session_idle_minutes', 30)) * 60;
        if (time() - (int) $user['last_activity'] > $idleSeconds) {
            self::logout();

            return null;
        }

        return $user;
    }
}
