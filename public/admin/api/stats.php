<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/src/bootstrap.php';

use App\Database;
use App\Repository\PlatformRepository;
use App\Security\Auth;
use App\Stats\ShareStats;
use App\Stats\StatsFilter;
use App\Validation\ValidationException;

Auth::requireLogin(json: true);
session_write_close(); // release the session lock so parallel requests don't queue

$db = Database::connection();
$platforms = (new PlatformRepository($db))->all();

try {
    $filter = StatsFilter::fromQuery($_GET, array_map(static fn (array $p): int => (int) $p['id'], $platforms));
} catch (ValidationException $e) {
    json_response(['ok' => false, 'errors' => $e->errors()], 422);
}

json_response(['ok' => true] + (new ShareStats($db))->report($filter, $platforms));
