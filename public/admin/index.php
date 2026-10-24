<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Database;
use App\Repository\PageRepository;
use App\Repository\PlatformRepository;
use App\Security\Auth;
use App\Stats\StatsFilter;
use App\Validation\ValidationException;
use App\View;

$user = Auth::requireLogin();
send_security_headers();
header('Cache-Control: no-store');

$db = Database::connection();
$platforms = (new PlatformRepository($db))->active();

try {
    $filter = StatsFilter::fromQuery($_GET, array_map(static fn (array $p): int => (int) $p['id'], $platforms));
} catch (ValidationException) {
    $filter = StatsFilter::fromQuery([], []);
}

View::render('admin/dashboard', [
    'title' => 'Dashboard',
    'active' => 'dashboard',
    'user' => $user,
    'filter' => $filter,
    'platforms' => $platforms,
    'pages' => (new PageRepository($db))->allShared(),
    'scripts' => [
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
        asset('js/dashboard.js'),
    ],
], 'admin/layout');
