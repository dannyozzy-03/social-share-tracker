<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Content\ArticleRepository;
use App\Database;
use App\Repository\PlatformRepository;
use App\Repository\ShareClickRepository;
use App\View;

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

$urls = array_map([ArticleRepository::class, 'url'], $allArticles);
$shareCounts = (new ShareClickRepository($db))->countsForUrls([...$urls, $homeUrl]);

send_security_headers();
View::render('site/home', [
    'meta' => [
        'title' => $section === null ? config('app.site_name') . ' — Latest stories' : $section,
        'description' => $section === null
            ? 'Food, travel, money and lifestyle stories from around Malaysia.'
            : 'The latest ' . strtolower($section) . ' stories from ' . config('app.site_name') . '.',
        'url' => $section === null ? $homeUrl : app_url('?section=' . rawurlencode(strtolower($section))),
        'image' => ArticleRepository::coverUrl('front-page'),
        'type' => 'website',
    ],
    'section' => $section,
    'homeUrl' => $homeUrl,
    'articles' => $articles,
    'allArticles' => $allArticles,
    'shareCounts' => $shareCounts,
    'platforms' => (new PlatformRepository($db))->active(),
], 'site/layout');
