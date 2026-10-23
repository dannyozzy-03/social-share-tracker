<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Content\ArticleRepository;
use App\Database;
use App\Repository\PlatformRepository;
use App\Repository\ShareClickRepository;
use App\View;

send_security_headers();

$slug = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
$article = ArticleRepository::find($slug);

if ($article === null) {
    http_response_code(404);
    View::render('site/not-found', [
        'meta' => [
            'title' => 'Page not found',
            'description' => 'The story you were looking for could not be found.',
            'url' => app_url(),
            'image' => null,
            'type' => 'website',
            'noindex' => true,
        ],
    ], 'site/layout');
    exit;
}

$db = Database::connection();
$url = ArticleRepository::url($article);
$all = ArticleRepository::all();
$shareCounts = (new ShareClickRepository($db))->countsForUrls(array_map([ArticleRepository::class, 'url'], $all));

// Articles are newest first, so the next (newer) story sits before this one and the previous after it.
$index = (int) array_search($slug, array_column($all, 'slug'), true);

View::render('site/article', [
    'meta' => [
        'title' => $article['title'],
        'description' => $article['summary'],
        'url' => $url,
        'image' => $article['image'],
        'type' => 'article',
        'published' => $article['published'],
        'author' => $article['author'],
        'section' => $article['category'],
    ],
    'bodyClass' => 'is-story',
    'section' => $article['category'],
    'article' => $article,
    'all' => $all,
    'previous' => $all[$index + 1] ?? null,
    'next' => $all[$index - 1] ?? null,
    'shareCounts' => $shareCounts,
    'shareCount' => $shareCounts[$url],
    'platforms' => (new PlatformRepository($db))->active(),
], 'site/layout');
