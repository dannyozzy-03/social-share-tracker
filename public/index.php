<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Content\ArticleRepository;
use App\Database;
use App\Repository\PlatformRepository;
use App\Repository\ShareClickRepository;
use App\Support\Paginator;
use App\View;

/** Story cards per page under the lead; older stories continue on ?page=2, ?page=3, … */
const STORIES_PER_PAGE = 6;

$db = Database::connection();
$allArticles = ArticleRepository::all();
$homeUrl = app_url();

// ?section=food narrows the front page to one section; unknown sections fall back to everything.
$requested = is_string($_GET['section'] ?? null) ? strtolower($_GET['section']) : '';
$section = null;
foreach (ArticleRepository::sections() as $name) {
    if (strtolower($name) === $requested) {
        $section = $name;
    }
}
$articles = $section === null
    ? $allArticles
    : array_values(array_filter($allArticles, static fn (array $a): bool => $a['category'] === $section));

// The newest story leads page 1; the rest are paged so the front page stays short with any number of stories.
$requestedPage = Paginator::parsePage($_GET['page'] ?? null);
$pager = new Paginator(max(0, count($articles) - 1), STORIES_PER_PAGE, $requestedPage ?? 1);

/** Link to a page of this listing; page 1 has no ?page= so it keeps one canonical address. */
$listingPath = static function (int $page) use ($section): string {
    $query = http_build_query(array_filter([
        'section' => $section === null ? null : strtolower($section),
        'page' => $page > 1 ? $page : null,
    ]));

    return $query === '' ? '' : '?' . $query;
};

send_security_headers();

if ($requestedPage === null || $requestedPage > $pager->pages()) {
    http_response_code(404);
    View::render('site/not-found', [
        'meta' => [
            'title' => 'Page not found',
            'description' => 'That page of stories does not exist.',
            'url' => $homeUrl,
            'image' => null,
            'type' => 'website',
            'noindex' => true,
        ],
    ], 'site/layout');
    exit;
}

$urls = array_map([ArticleRepository::class, 'url'], $allArticles);
$shareCounts = (new ShareClickRepository($db))->countsForUrls([...$urls, $homeUrl]);

$title = $section === null ? config('app.site_name') . ' — Latest stories' : $section;
View::render('site/home', [
    'meta' => [
        'title' => $title . ($pager->page > 1 ? sprintf(' — Page %d of %d', $pager->page, $pager->pages()) : ''),
        'description' => $section === null
            ? 'Food, travel, money and lifestyle stories from around Malaysia.'
            : 'The latest ' . strtolower($section) . ' stories from ' . config('app.site_name') . '.',
        'url' => app_url($listingPath($pager->page)),
        'image' => ArticleRepository::coverUrl('front-page'),
        'type' => 'website',
        'prev' => $pager->hasPrevious() ? app_url($listingPath($pager->page - 1)) : null,
        'next' => $pager->hasNext() ? app_url($listingPath($pager->page + 1)) : null,
    ],
    'section' => $section,
    'homeUrl' => $homeUrl,
    'lead' => $pager->page === 1 ? ($articles[0] ?? null) : null,
    'stories' => array_slice($articles, 1 + $pager->offset(), $pager->perPage),
    'pager' => $pager,
    'pageUrl' => static fn (int $page): string => url($listingPath($page)),
    'allArticles' => $allArticles,
    'shareCounts' => $shareCounts,
    'platforms' => (new PlatformRepository($db))->active(),
], 'site/layout');
