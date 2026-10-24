<?php

declare(strict_types=1);

use App\Content\ArticleRepository;
use App\View;

return [
    'has at least twelve stories with unique slugs and existing covers' => function (): void {
        $articles = ArticleRepository::all();
        $slugs = array_column($articles, 'slug');
        $images = array_column($articles, 'image_src');

        check(count($articles) >= 12, 'Expected at least twelve stories.');
        check(count($slugs) === count(array_unique($slugs)), 'Story slugs must be unique.');
        check(count($images) === count(array_unique($images)), 'Each story must have a different cover image.');
        foreach ($articles as $article) {
            check(isset($article['summary']) && trim($article['summary']) !== '', 'Missing description for ' . $article['slug']);
            check(is_file(APP_ROOT . '/public/assets/img/covers/' . basename(parse_url($article['image_src'], PHP_URL_PATH))), 'Missing cover for ' . $article['slug']);
        }
    },
    'every story has long-form copy and related stories in its section' => function (): void {
        $articles = ArticleRepository::all();

        foreach ($articles as $article) {
            $body = $article['body'];
            $sameSectionStories = array_filter(
                $articles,
                static fn (array $candidate): bool => $candidate['category'] === $article['category'] && $candidate['slug'] !== $article['slug'],
            );

            check(count($body) >= 7, 'Expected at least seven article paragraphs for ' . $article['slug'] . '.');
            check(count($sameSectionStories) >= 2, 'Expected at least two related stories for ' . $article['slug'] . '.');
        }
    },
    'trending list includes every story and uses its updated title' => function (): void {
        $articles = ArticleRepository::all();
        $shareCounts = [];
        foreach ($articles as $article) {
            $shareCounts[ArticleRepository::url($article)] = 0;
        }
        $lastStoryUrl = ArticleRepository::url($articles[array_key_last($articles)]);
        $shareCounts[$lastStoryUrl] = 1;

        $html = View::fetch('site/most-shared', ['articles' => $articles, 'shareCounts' => $shareCounts]);

        check(str_contains($html, 'Trending (Most Shared)'), 'Expected updated trending heading.');
        check(substr_count($html, 'class="ranked__item"') === count($articles), 'Every story must appear in trending.');
        check(str_contains($html, e($articles[array_key_last($articles)]['title'])), 'The last story must appear in trending.');
        foreach ($articles as $article) {
            check(str_contains($html, e($article['summary'])), 'Trending must show the description for ' . $article['slug']);
        }
    },
    'story cards display each story description' => function (): void {
        foreach (ArticleRepository::all() as $article) {
            $html = View::fetch('site/card', ['article' => $article, 'shares' => 0]);

            check(str_contains($html, 'class="card-story__summary">' . e($article['summary'])), 'Story card must show description for ' . $article['slug']);
        }
    },
    'visible ticker clones remain clickable without entering keyboard navigation' => function (): void {
        $html = View::fetch('site/story-ticker', ['articles' => ArticleRepository::all(), 'currentSlug' => null]);

        check(!str_contains($html, 'strip__list--clone" aria-hidden="true" inert'), 'Visible ticker clones must not be inert.');
        check(str_contains($html, 'strip__list--clone" aria-hidden="true">'), 'Ticker clones must remain hidden from assistive technology.');
        check(str_contains($html, 'tabindex="-1"'), 'Ticker clone links must not be added to keyboard tab order.');
    },
    'category page places its stories directly after the featured story' => function (): void {
        $allArticles = ArticleRepository::all();
        $articles = array_values(array_filter($allArticles, static fn (array $article): bool => $article['category'] === 'Food'));
        $shareCounts = [];
        foreach ($allArticles as $article) {
            $shareCounts[ArticleRepository::url($article)] = 0;
        }
        $pageData = [
            'meta' => [],
            'section' => 'Food',
            'homeUrl' => url(),
            'articles' => $articles,
            'allArticles' => $allArticles,
            'shareCounts' => $shareCounts,
            'platforms' => [],
        ];

        $html = View::fetch('site/home', $pageData);

        check(str_contains($html, 'home--section'), 'Category pages must use their dedicated layout.');
        check(str_contains($html, 'More in Food'), 'Category pages must show the remaining stories in their category.');
        $moreInFoodPosition = strpos($html, 'More in Food');
        $trendingPosition = strpos($html, 'Trending (Most Shared)');
        check($trendingPosition !== false && $trendingPosition > $moreInFoodPosition, 'Trending must appear after the category stories.');
        check(str_contains($html, 'class="ranked ranked--section"'), 'Category trending must use the section list layout.');
        check(str_contains($html, 'class="ranked__list ranked__list--section"'), 'Category trending stories must use the responsive list grid.');
        check(str_contains($html, 'class="ranked__item ranked__item--section"'), 'Category trending entries must use the horizontal story row.');
    },
    'homepage latest stories start below the feature alongside Trending' => function (): void {
        $articles = ArticleRepository::all();
        $shareCounts = [];
        foreach ($articles as $article) {
            $shareCounts[ArticleRepository::url($article)] = 0;
        }

        $html = View::fetch('site/home', [
            'meta' => [],
            'section' => null,
            'homeUrl' => url(),
            'articles' => $articles,
            'allArticles' => $articles,
            'shareCounts' => $shareCounts,
            'platforms' => [],
        ]);
        $leadPosition = strpos($html, 'class="lead"');
        $latestPosition = strpos($html, 'id="latest-title"');
        $trendingPosition = strpos($html, 'id="ranked-title"');

        check($leadPosition !== false && $latestPosition > $leadPosition, 'Homepage latest stories must follow the feature.');
        check($trendingPosition !== false && $latestPosition < $trendingPosition, 'Homepage latest stories must appear before the full Trending column.');
    },
    'article page places section-style trending after previous and next stories' => function (): void {
        $articles = ArticleRepository::all();
        $shareCounts = [];
        foreach ($articles as $article) {
            $shareCounts[ArticleRepository::url($article)] = 0;
        }

        $html = View::fetch('site/article', [
            'meta' => ['url' => ArticleRepository::url($articles[1])],
            'article' => $articles[1],
            'all' => $articles,
            'previous' => $articles[2],
            'next' => $articles[0],
            'shareCounts' => $shareCounts,
            'shareCount' => 0,
            'platforms' => [],
        ]);
        $adjacentPosition = strpos($html, 'class="adjacent"');
        $trendingPosition = strpos($html, 'class="ranked ranked--section"');

        check($adjacentPosition !== false, 'Article page must show previous and next story links.');
        check($trendingPosition !== false && $trendingPosition > $adjacentPosition, 'Article Trending must appear after previous and next links.');
        check(str_contains($html, 'class="ranked__item ranked__item--section"'), 'Article Trending must use the compact story list.');
    },
    'mobile navigation includes home, every section, and admin access' => function (): void {
        $html = View::fetch('site/layout', [
            'content' => '<p>Page content</p>',
            'meta' => [
                'title' => 'Food',
                'description' => 'Food stories',
                'url' => 'http://localhost/',
                'type' => 'website',
            ],
            'bodyClass' => '',
            'section' => 'Food',
        ]);

        check(str_contains($html, '<nav class="mobile-nav d-md-none" aria-label="Mobile navigation">'), 'Expected the mobile floating navigation.');
        check(str_contains($html, 'mobile-nav__link is-active" href="') && str_contains($html, 'aria-current="page"'), 'Expected the active category in the mobile navigation.');
        foreach (ArticleRepository::sections() as $section) {
            check(str_contains($html, '>' . e($section) . '</span>'), 'Mobile navigation must include ' . $section . '.');
        }
        check(str_contains($html, 'data-admin-session-link'), 'Mobile navigation must include admin sign in/dashboard.');
        check(str_contains($html, 'class="mobile-nav__link mobile-nav__theme" type="button" data-theme-toggle'), 'The mobile navigation must retain the theme toggle.');
    },
    'admin layout provides floating mobile navigation with active state and sign out' => function (): void {
        $html = View::fetch('admin/layout', [
            'content' => '<p>Dashboard content</p>',
            'title' => 'Dashboard',
            'active' => 'dashboard',
            'user' => ['username' => 'admin'],
        ]);

        check(str_contains($html, '<nav class="admin-mobile-nav d-md-none" aria-label="Admin mobile navigation">'), 'Expected the admin floating navigation.');
        check(str_contains($html, 'admin-mobile-nav__link is-active" href="') && str_contains($html, 'aria-current="page"'), 'Expected the current admin page to be highlighted.');
        foreach (['Dashboard', 'Platforms', 'Website', 'Theme', 'Sign out'] as $label) {
            check(str_contains($html, '>' . $label . '</span>'), 'Admin mobile navigation must include ' . $label . '.');
        }
        check(str_contains($html, 'name="_csrf"'), 'Mobile sign out must include CSRF protection.');
    },
];
