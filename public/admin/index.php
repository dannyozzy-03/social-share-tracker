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

// The page picker searches the server as the admin types (admin/api/pages.php), so only the
// selected page is loaded here, however many pages exist. An unknown ID falls back to all pages.
$selectedPage = $filter->pageId === null ? null : (new PageRepository($db))->find($filter->pageId);
if ($filter->pageId !== null && $selectedPage === null) {
    $filter = new StatsFilter($filter->from, $filter->to, $filter->granularity, $filter->platformIds);
}

View::render('admin/dashboard', [
    'title' => 'Dashboard',
    'active' => 'dashboard',
    'user' => $user,
    'filter' => $filter,
    'platforms' => $platforms,
    'selectedPage' => $selectedPage,
    'scripts' => [
        [
            'src' => 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            'integrity' => 'sha384-9nhczxUqK87bcKHh20fSQcTGD4qq5GhayNYSYWqwBkINBhOfQLg/P5HG5lF1urn4',
        ],
        asset('js/page-picker.js'),
        asset('js/dashboard.js'),
    ],
], 'admin/layout');
