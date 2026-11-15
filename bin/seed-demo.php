<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Database;
use App\Support\DemoSeeder;

$options = getopt('', ['days:', 'archive:', 'remove-archive', 'help']);

if (isset($options['help'])) {
    echo <<<TXT
    Usage:
      php bin/seed-demo.php [--days=90]      Replace ALL share data with fresh demo data (default 90 days).
      php bin/seed-demo.php --archive=2000   Add 2,000 extra shared pages to try the dashboard with a big
                                             site. Keeps existing data; running it again replaces the archive.
      php bin/seed-demo.php --remove-archive Remove those extra pages again (nothing else is touched).

    TXT;
    exit(0);
}

$seeder = new DemoSeeder(Database::connection());

if (isset($options['remove-archive'])) {
    $removed = $seeder->removeArchive();
    echo '[ok] Removed ' . number_format($removed) . " archive pages and their share clicks.\n";
    exit(0);
}

if (isset($options['archive'])) {
    $count = filter_var($options['archive'], FILTER_VALIDATE_INT);
    if ($count === false || $count < 1 || $count > DemoSeeder::ARCHIVE_MAX) {
        fwrite(STDERR, sprintf("--archive must be a number of pages from 1 to %d.\n", DemoSeeder::ARCHIVE_MAX));
        exit(1);
    }
    $result = $seeder->addArchive($count);
    echo sprintf(
        "[ok] Added %s archive pages with %s share clicks (under https://%s/).\n"
        . "     Open the admin dashboard and pick \"90 days\" to see them all.\n"
        . "     Remove them again with: php bin/seed-demo.php --remove-archive\n",
        number_format($result['pages']),
        number_format($result['clicks']),
        DemoSeeder::ARCHIVE_HOST
    );
    exit(0);
}

$days = filter_var($options['days'] ?? '90', FILTER_VALIDATE_INT);
if ($days === false || $days < 1 || $days > 730) {
    fwrite(STDERR, "--days must be between 1 and 730.\n");
    exit(1);
}

echo '[ok] Generated ' . number_format($seeder->replace($days)) . " demo share clicks for the last $days days.\n";
