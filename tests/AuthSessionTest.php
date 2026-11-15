<?php

declare(strict_types=1);

use App\Security\Auth;

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
];
