<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/src/bootstrap.php';

use App\Database;
use App\Repository\PlatformRepository;
use App\Security\Auth;
use App\Stats\ShareStats;
use App\Stats\StatsFilter;
use App\Support\Paginator;
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

// Page of the "Page breakdown" table; too high a number is clamped to the last page.
$breakdownPage = Paginator::parsePage($_GET['breakdown_page'] ?? null);
if ($breakdownPage === null) {
    json_response(['ok' => false, 'errors' => ['breakdown_page' => 'Invalid table page.']], 422);
}

json_response(['ok' => true] + (new ShareStats($db))->report($filter, $platforms, $breakdownPage));
