<?php

declare(strict_types=1);

use App\Admin\PlatformForm;
use App\Config;
use App\Exception\AppException;
use App\Exception\ConfigurationException;
use App\Exception\DatabaseException;
use App\Repository\AdminRepository;
use App\Repository\PageRepository;
use App\Repository\PlatformRepository;
use App\Repository\ShareClickRepository;
use App\Security\Auth;
use App\Security\LoginThrottle;
use App\Share\RateLimitException;
use App\Stats\ShareStats;
use App\Stats\StatsFilter;
use App\Support\DemoSeeder;
use App\Support\SqlFile;
use App\Validation\ValidationException;
use App\View;

require_once __DIR__ . '/Support/FailingPdo.php';

/** A PDO whose every call fails, as if the database server went away. */
$failingPdo = static fn (): Tests\Support\FailingPdo => new Tests\Support\FailingPdo();

/** Asserts $fn throws DatabaseException that names the operation and keeps the driver error. */
$expectDatabaseError = static function (callable $fn): DatabaseException {
    $e = check_throws(DatabaseException::class, $fn);
    check(str_starts_with($e->getMessage(), 'Database error while trying to '), 'Unexpected message: ' . $e->getMessage());
    check($e->getPrevious() instanceof PDOException, 'The PDOException must be kept as the previous exception.');
    check(!str_contains($e->getMessage(), 'SELECT') && !str_contains($e->getMessage(), 'INSERT'), 'SQL must not leak into the message.');

    return $e;
};

$filter = new StatsFilter(new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-07'));

return [
    'every exception the app throws shares one base class' => function (): void {
        check(is_subclass_of(DatabaseException::class, AppException::class));
        check(is_subclass_of(ConfigurationException::class, AppException::class));
        check(is_subclass_of(ValidationException::class, AppException::class));
        check(is_subclass_of(RateLimitException::class, AppException::class));
    },
    'DatabaseException keeps the SQLSTATE for callers that need it' => function (): void {
        $e = DatabaseException::during('save', new PDOException('duplicate'));
        check_same('', $e->sqlState());
        // A real duplicate-key error from a driver.
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE t (slug TEXT UNIQUE)');
        $pdo->exec("INSERT INTO t VALUES ('x')");
        $duplicate = check_throws(PDOException::class, fn () => $pdo->exec("INSERT INTO t VALUES ('x')"));
        check_same('23000', DatabaseException::during('save', $duplicate)->sqlState());
    },
    'connection errors keep the reason but not the PDOException trace' => function (): void {
        $e = DatabaseException::connection('db.local', 3307, new PDOException('Connection refused'));
        check_same('Could not connect to the database at db.local:3307 (Connection refused).', $e->getMessage());
        check_same(null, $e->getPrevious());
    },
    'PageRepository wraps driver errors' => function () use ($failingPdo, $expectDatabaseError): void {
        $pages = new PageRepository($failingPdo());
        $expectDatabaseError(fn () => $pages->findOrCreate('https://example.com/', 'Title'));
        $expectDatabaseError(fn () => $pages->find(1));
        $expectDatabaseError(fn () => $pages->search('penang'));
    },
    'PlatformRepository wraps driver errors' => function () use ($failingPdo, $expectDatabaseError): void {
        $platforms = new PlatformRepository($failingPdo());
        $expectDatabaseError(fn () => $platforms->all());
        $expectDatabaseError(fn () => $platforms->active());
        $expectDatabaseError(fn () => $platforms->allWithClickCounts());
        $expectDatabaseError(fn () => $platforms->find(1));
        $expectDatabaseError(fn () => $platforms->findActiveBySlug('x'));
        $expectDatabaseError(fn () => $platforms->slugExists('x'));
        $expectDatabaseError(fn () => $platforms->create(['slug' => 'x', 'name' => 'X']));
        $expectDatabaseError(fn () => $platforms->update(1, ['name' => 'X']));
        $expectDatabaseError(fn () => $platforms->setActive(1, false));
    },
    'ShareClickRepository wraps driver errors' => function () use ($failingPdo, $expectDatabaseError): void {
        $clicks = new ShareClickRepository($failingPdo());
        $now = new DateTimeImmutable();
        $expectDatabaseError(fn () => $clicks->record(1, 1, 'hash', null, $now));
        $expectDatabaseError(fn () => $clicks->existsSince(1, 1, 'hash', $now));
        $expectDatabaseError(fn () => $clicks->countByVisitorSince('hash', $now));
        $expectDatabaseError(fn () => $clicks->countForPage(1));
        $expectDatabaseError(fn () => $clicks->countsForUrls(['https://example.com/']));
    },
    'AdminRepository and LoginThrottle wrap driver errors' => function () use ($failingPdo, $expectDatabaseError): void {
        $admins = new AdminRepository($failingPdo());
        $expectDatabaseError(fn () => $admins->findByUsername('admin'));
        $expectDatabaseError(fn () => $admins->upsert('admin', 'password123'));
        $expectDatabaseError(fn () => $admins->updatePasswordHash(1, 'hash'));
        $expectDatabaseError(fn () => $admins->touchLastLogin(1));
        $expectDatabaseError(fn () => $admins->count());

        $throttle = new LoginThrottle($failingPdo());
        $expectDatabaseError(fn () => $throttle->isLocked('hash'));
        $expectDatabaseError(fn () => $throttle->record('hash', 'admin', false));
    },
    'ShareStats wraps driver errors, including while streaming the export' => function () use ($failingPdo, $expectDatabaseError, $filter): void {
        $stats = new ShareStats($failingPdo());
        $expectDatabaseError(fn () => $stats->report($filter, []));
        $expectDatabaseError(fn () => $stats->pageBreakdown($filter, [], 2));
        $expectDatabaseError(fn () => iterator_to_array($stats->exportRows($filter)));
    },
    'a failed demo seed rolls back and reports a DatabaseException' => function () use ($failingPdo, $expectDatabaseError): void {
        $pdo = $failingPdo();
        $expectDatabaseError(fn () => (new DemoSeeder($pdo))->replace(3));
        check($pdo->rolledBack, 'The transaction must be rolled back.');
        check(!$pdo->inTransaction());
    },
    'SqlFile names the failing statement and file' => function () use ($failingPdo): void {
        $file = tempnam(sys_get_temp_dir(), 'sql');
        file_put_contents($file, "-- comment\nCREATE TABLE t (id INT);\nINSERT INTO t VALUES (1);\n");
        try {
            $e = check_throws(DatabaseException::class, fn () => SqlFile::run($failingPdo(), $file));
            check(str_contains($e->getMessage(), 'statement 1 of ' . basename($file)), $e->getMessage());
            check(str_contains($e->getMessage(), 'CREATE TABLE t'), $e->getMessage());
        } finally {
            unlink($file);
        }
        check_throws(ConfigurationException::class, fn () => SqlFile::run($failingPdo(), __DIR__ . '/does-not-exist.sql'));
    },
    'PlatformForm lets the database error through instead of reporting a bogus field error' => function () use ($failingPdo): void {
        $form = new PlatformForm(new PlatformRepository($failingPdo()));
        check_throws(DatabaseException::class, fn () => $form->validate(['slug' => 'reddit', 'name' => 'Reddit'], true));
    },
    'Config::loadFiles rejects broken and non-array config files' => function (): void {
        $dir = sys_get_temp_dir() . '/sst-config-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents("$dir/defaults.php", "<?php return ['app' => ['name' => 'Default', 'debug' => false]];");
        file_put_contents("$dir/broken.php", "<?php return [;");
        file_put_contents("$dir/scalar.php", "<?php return 'nope';");
        file_put_contents("$dir/local.php", "<?php return ['app' => ['name' => 'Local']];");
        $backup = (new ReflectionProperty(Config::class, 'items'));
        $backup->setAccessible(true);
        $saved = $backup->getValue();
        try {
            $e = check_throws(ConfigurationException::class, fn () => Config::loadFiles("$dir/defaults.php", "$dir/broken.php"));
            check(str_contains($e->getMessage(), 'broken.php'), $e->getMessage());
            check_throws(ConfigurationException::class, fn () => Config::loadFiles("$dir/defaults.php", "$dir/scalar.php"));

            Config::loadFiles("$dir/defaults.php", "$dir/local.php");
            check_same('Local', Config::get('app.name'));
            check_same(false, Config::get('app.debug'), 'Defaults the local file does not override must be kept.');
            Config::loadFiles("$dir/defaults.php", "$dir/missing.php");
            check_same('Default', Config::get('app.name'), 'A missing local file is optional.');
        } finally {
            $backup->setValue(null, $saved);
            array_map('unlink', glob("$dir/*.php"));
            rmdir($dir);
        }
    },
    'View refuses unknown or suspicious template names' => function (): void {
        check_throws(ConfigurationException::class, fn () => View::fetch('admin/does-not-exist'));
        check_throws(ConfigurationException::class, fn () => View::fetch('../config/config'));
        check_throws(ConfigurationException::class, fn () => View::fetch('admin/../site/home'));
    },
    'View discards partial output when a template fails' => function (): void {
        $level = ob_get_level();
        check_throws(Throwable::class, fn () => View::fetch('admin/dashboard', []));
        check_same($level, ob_get_level(), 'Output buffers must be cleaned up.');
    },
    'the public sign-in check never creates a session for anonymous visitors' => function (): void {
        unset($_COOKIE['sst_admin']);
        check_same(false, Auth::isAuthenticated());
        check_same(PHP_SESSION_NONE, session_status(), 'No session may be started without an admin cookie.');
    },
];
