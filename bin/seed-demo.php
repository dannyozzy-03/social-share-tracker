<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Database;
use App\Support\DemoSeeder;

$options = getopt('', ['days:']);
$days = (int) ($options['days'] ?? 90);
if ($days < 1 || $days > 730) {
    fwrite(STDERR, "--days must be between 1 and 730.\n");
    exit(1);
}

$seeder = new DemoSeeder(Database::connection());
$seeder->clear();
echo '[ok] Generated ' . number_format($seeder->run($days)) . " demo share clicks for the last $days days.\n";
