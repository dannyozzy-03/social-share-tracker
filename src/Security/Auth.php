<?php

declare(strict_types=1);

namespace App\Security;

use App\Exception\AppException;
use App\Exception\DatabaseException;
use App\Repository\AdminRepository;
use ErrorException;

/**
 * Admin sign-in, the admin session and its idle timeout.
 *
 * Error handling: every session call is wrapped. When PHP cannot start, renew or destroy the
 * session, it throws AppException with PHP's warning as the previous exception, so sign-in and
 * sign-out fail closed instead of carrying on with a stale session. Database faults arrive as
 * DatabaseException from AdminRepository.
 */
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

    /**
     * @throws DatabaseException
     * @throws AppException when the session ID cannot be renewed (sign-in is refused)
     */
    public static function attempt(AdminRepository $admins, string $username, #[\SensitiveParameter] string $password): bool
    {
        $admin = $admins->findByUsername($username);

        // An unknown username is checked against a dummy hash of the same cost, so it takes as
        // long as a wrong password and response times don't reveal which usernames exist.
        $hash = $admin['password_hash'] ?? self::unknownUserHash();
        if (!password_verify($password, $hash) || $admin === null) {
            return false;
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $admins->updatePasswordHash((int) $admin['id'], password_hash($password, PASSWORD_DEFAULT));
        }
        $admins->touchLastLogin((int) $admin['id']);

        // New session ID and new CSRF token: nothing issued before sign-in stays valid after it.
        self::renewSessionId();
        Csrf::rotate();
        $_SESSION[self::SESSION_KEY] = [
            'id' => (int) $admin['id'],
            'username' => $admin['username'],
            'last_activity' => time(),
        ];

        return true;
    }

    /**
     * The hash checked for a username that does not exist: a well-formed bcrypt hash at today's
     * default cost that no password matches. Checking it takes as long as checking a real
     * password, and building it costs nothing. (Hashing a random password here instead made every
     * unknown username take twice as long as a wrong password, which gave valid usernames away.)
     */
    public static function unknownUserHash(): string
    {
        return sprintf('$2y$%02d$%s', PASSWORD_BCRYPT_DEFAULT_COST, str_repeat('.', 53));
    }

    /**
     * The signed-in admin, or null. Counts as activity, so it extends the idle timeout.
     * @return array{id: int, username: string, last_activity: int}|null
     */
    public static function user(): ?array
    {
        // sessionUser() also ends a malformed session, e.g. one missing last_activity.
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

    /** @throws AppException when the session cannot be destroyed, so a failed sign-out is never reported as done */
    public static function logout(): void
    {
        $_SESSION = [];
        // Once a page has started printing, the cookie can no longer be cleared; the session data
        // is still destroyed below, so the old cookie then points at nothing.
        if (!headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        try {
            $destroyed = session_destroy();
        } catch (ErrorException $e) {
            throw new AppException('Could not end the admin session.', 0, $e);
        }
        if (!$destroyed) {
            throw new AppException('Could not end the admin session.');
        }
    }

    /**
     * Gives the session a new ID and deletes the old one, so an ID planted before sign-in is useless.
     * @throws AppException when PHP cannot do it: sign-in then fails instead of keeping the old ID
     */
    private static function renewSessionId(): void
    {
        try {
            $renewed = session_regenerate_id(true);
        } catch (ErrorException $e) {
            throw new AppException('Could not renew the admin session.', 0, $e);
        }
        if (!$renewed) {
            throw new AppException('Could not renew the admin session.');
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
        if ($user === null) {
            return null;
        }
        // A malformed entry (e.g. missing last_activity) counts as expired: end that session.
        if (!is_array($user) || !isset($user['id'], $user['username'], $user['last_activity'])) {
            self::logout();

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
