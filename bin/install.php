<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$options = getopt('', [
    'db-host:', 'db-port:', 'db-name:', 'db-user:', 'db-pass:', 'app-url:',
    'admin-user:', 'admin-pass:', 'demo', 'fresh', 'help',
]);

if (isset($options['help'])) {
    echo "Usage: php bin/install.php [--db-user=root] [--db-pass=] [--admin-user=admin] [--admin-pass=...] [--demo] [--fresh]\n";
    exit(0);
}

$configFile = "$root/config/config.php";
if (!is_file($configFile)) {
    $local = [
        'app' => [
            'url' => $options['app-url'] ?? 'http://localhost:8000',
            'secret' => bin2hex(random_bytes(32)),
        ],
        'db' => [
            'host' => $options['db-host'] ?? '127.0.0.1',
            'port' => (int) ($options['db-port'] ?? 3306),
            'name' => $options['db-name'] ?? 'social_share_tracker',
            'user' => $options['db-user'] ?? 'root',
            'pass' => $options['db-pass'] ?? '',
        ],
    ];
    file_put_contents(
        $configFile,
        "<?php\n\n// Local settings, merged over config/config.example.php. Not committed to git.\nreturn "
        . var_export($local, true) . ";\n"
    );
    echo "[ok] Created config/config.php\n";
} elseif (array_intersect_key($options, array_flip(['db-host', 'db-port', 'db-name', 'db-user', 'db-pass', 'app-url']))) {
    echo "[!!] config/config.php already exists, so --db-* and --app-url options were ignored. Edit that file instead.\n";
}

require "$root/src/bootstrap.php";

use App\Database;
use App\Repository\AdminRepository;
use App\Support\DemoSeeder;
use App\Support\SqlFile;

$dbName = (string) config('db.name');
if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbName)) {
    fwrite(STDERR, "Database name may only contain letters, numbers and underscores.\n");
    exit(1);
}
try {
    $server = Database::connect(false);
} catch (PDOException $e) {
    fwrite(STDERR, "Could not connect to MySQL at " . config('db.host') . ':' . config('db.port')
        . " as '" . config('db.user') . "': " . $e->getMessage() . "\nCheck the db settings in config/config.php.\n");
    exit(1);
}
$server->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "[ok] Database `$dbName` is ready\n";

$db = Database::connection();

if (isset($options['fresh'])) {
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['share_clicks', 'pages', 'platforms', 'login_attempts', 'admins'] as $table) {
        $db->exec("DROP TABLE IF EXISTS `$table`");
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "[ok] Dropped existing tables (--fresh)\n";
}
SqlFile::run($db, "$root/database/schema.sql");
SqlFile::run($db, "$root/database/seed.sql");
echo "[ok] Tables created and default platforms seeded\n";

$admins = new AdminRepository($db);
$username = trim((string) ($options['admin-user'] ?? 'admin'));
$password = $options['admin-pass'] ?? null;

if ($password === null && $admins->findByUsername($username) !== null) {
    echo "[ok] Admin '$username' already exists (use --admin-pass to reset the password)\n";
} else {
    $generated = false;
    if ($password === null && stream_isatty(STDIN)) {
        echo "Choose a password for admin '$username' (8+ characters, leave empty to generate one): ";
        $password = trim((string) fgets(STDIN));
    }
    if ($password === null || $password === '') {
        $password = rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'Kq'), '=');
        $generated = true;
    }
    if (strlen($password) < 8) {
        fwrite(STDERR, "The admin password must be at least 8 characters.\n");
        exit(1);
    }
    $admins->upsert($username, $password);
    echo "[ok] Admin account saved: username '$username'" . ($generated ? ", password '$password'" : '') . "\n";
}

if (isset($options['demo'])) {
    $seeder = new DemoSeeder($db);
    $seeder->clear();
    echo '[ok] Generated ' . number_format($seeder->run(90)) . " demo share clicks for the last 90 days\n";
}

echo "\nDone. Start the site with:\n  php -S localhost:8000 -t public\n"
    . "You can replace 8000 with any available port, then open http://localhost:<port>/.\n";
