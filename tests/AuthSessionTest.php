<?php

declare(strict_types=1);

use App\Exception\AppException;
use App\Repository\AdminRepository;
use App\Security\Auth;

/** An in-memory admins table holding "admin" / "right-password" (a cheap hash keeps the test fast). */
$admins = static function (): AdminRepository {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('CREATE TABLE admins (id INTEGER PRIMARY KEY, username TEXT UNIQUE, password_hash TEXT, last_login_at TEXT)');
    $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')
        ->execute(['admin', password_hash('right-password', PASSWORD_BCRYPT, ['cost' => 4])]);
    $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);

    return new AdminRepository($pdo);
};

/** Runs $fn with $_SESSION set to $session, then restores whatever was there. */
$withSession = static function (array $session, callable $fn): mixed {
    $saved = $_SESSION ?? null;
    $_SESSION = $session;
    try {
        return $fn();
    } finally {
        $_SESSION = $saved;
    }
};
$admin = static fn (int $idleSeconds): array => ['admin' => ['id' => 1, 'username' => 'admin', 'last_activity' => time() - $idleSeconds]];

return [
    'an active admin stays signed in and the idle timer restarts' => function () use ($withSession, $admin): void {
        $withSession($admin(60), function (): void {
            check_same('admin', Auth::user()['username'] ?? null);
            check($_SESSION['admin']['last_activity'] >= time() - 1, 'Activity must extend the idle timeout.');
        });
    },
    'an idle session is ended' => function () use ($withSession, $admin): void {
        $withSession($admin(31 * 60), function (): void {
            check_same(null, Auth::user());
            check_same([], $_SESSION, 'The expired session must be cleared.');
        });
    },
    'a malformed session counts as expired and is cleared, never a PHP warning' => function () use ($withSession): void {
        foreach ([
            'missing last_activity' => ['admin' => ['id' => 1, 'username' => 'admin']],
            'missing username' => ['admin' => ['id' => 1, 'last_activity' => time()]],
            'not an array' => ['admin' => 'tampered'],
        ] as $case => $session) {
            $withSession($session, function () use ($case): void {
                check_same(null, Auth::user(), "$case must not sign anyone in");
                check_same([], $_SESSION, "$case must be cleared");
            });
        }
    },
    'no session means nobody is signed in' => function () use ($withSession): void {
        $withSession([], fn () => check_same(null, Auth::user()));
    },
    'signing out after output has started does not crash' => function () use ($withSession, $admin): void {
        // The test runner has already printed, so headers are sent: logout() must skip the cookie, not throw.
        check(headers_sent() || PHP_SAPI !== 'cli', 'Expected output to have started.');
        $withSession($admin(60), function (): void {
            Auth::logout();
            check_same([], $_SESSION);
        });
    },
    'unknown usernames are checked against a hash as costly as a real one, which nothing matches' => function (): void {
        // Same algorithm and cost as a new password hash, so a wrong username takes as long as a
        // wrong password; generating a hash per attempt (the old way) took twice as long.
        $dummy = password_get_info(Auth::unknownUserHash());
        $real = password_get_info(password_hash('any password', PASSWORD_DEFAULT));
        check_same($real['algo'], $dummy['algo']);
        check_same($real['options']['cost'] ?? null, $dummy['options']['cost'] ?? null);
        check_same(false, password_needs_rehash(Auth::unknownUserHash(), PASSWORD_DEFAULT));
        foreach (['', 'password', 'Admin@12345', str_repeat('.', 53)] as $password) {
            check_same(false, password_verify($password, Auth::unknownUserHash()), "\"$password\" must not match the dummy hash.");
        }
    },
    'a wrong username or password signs nobody in' => function () use ($withSession, $admins): void {
        $withSession([], function () use ($admins): void {
            $repository = $admins();
            check_same(false, Auth::attempt($repository, 'nobody', 'right-password'));
            check_same(false, Auth::attempt($repository, 'admin', 'wrong-password'));
            check_same([], $_SESSION, 'A failed attempt must not touch the session.');
        });
    },
    'sign-in fails closed when the session ID cannot be renewed' => function () use ($withSession, $admins): void {
        // There is no active session in the test runner, so PHP refuses to regenerate the ID.
        $withSession([], function () use ($admins): void {
            $e = check_throws(AppException::class, fn () => Auth::attempt($admins(), 'admin', 'right-password'));
            check_same('Could not renew the admin session.', $e->getMessage());
            check($e->getPrevious() instanceof ErrorException, "PHP's warning must be kept as the previous exception.");
            check(!isset($_SESSION['admin']), 'Nobody may be signed in on the old session ID.');
        });
    },
];
