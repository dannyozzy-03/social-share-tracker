<?php

declare(strict_types=1);

use App\Content\ArticleRepository;
use App\Stats\ShareStats;
use App\Stats\StatsFilter;
use App\Support\Paginator;
use App\View;

/** $n fake stories, newest first, shaped like ArticleRepository::all(). */
$stories = static function (int $n): array {
    $list = [];
    for ($i = 1; $i <= $n; $i++) {
        $list[] = [
            'slug' => sprintf('story-%03d', $i),
            'title' => sprintf('Story %03d', $i),
            'summary' => 'Summary',
            'category' => ['Food', 'Travel'][$i % 2],
            'author' => 'Desk',
            'published' => (new DateTimeImmutable('2026-10-31'))->modify("-$i days")->format('Y-m-d'),
            'image_src' => '/img.jpg',
            'body' => ['Body'],
        ];
    }

    return $list;
};
$countOf = static fn (string $html, string $needle): int => substr_count($html, $needle);

return [
    'Paginator splits a list into pages and clamps the page' => function (): void {
        $pager = new Paginator(25, 10, 2);
        check_same([3, 10, 2, true, true], [$pager->pages(), $pager->offset(), $pager->page, $pager->hasPrevious(), $pager->hasNext()]);
        check_same([11, 20], $pager->range());
        check_same([21, 25], (new Paginator(25, 10, 3))->range());
        check_same(3, (new Paginator(25, 10, 99))->page, 'Too high a page lands on the last page.');
        check_same(1, (new Paginator(25, 10, -4))->page);
        check_same([1, 1, [0, 0]], [(new Paginator(0, 10))->pages(), (new Paginator(0, 10))->page, (new Paginator(0, 10))->range()]);
        check_same(['page' => 2, 'per_page' => 10, 'total' => 25, 'pages' => 3], $pager->toArray());
    },
    'Paginator shows first, last and nearby pages with gaps' => function (): void {
        check_same([1, 2, 3], (new Paginator(30, 10, 1))->links());
        check_same([1, null, 9, 10, 11, null, 20], (new Paginator(200, 10, 10))->links());
        check_same([1, 2, null, 20], (new Paginator(200, 10, 1))->links());
        check_same([1, 2, 3, 4, null, 20], (new Paginator(200, 10, 3))->links(), 'A one-page gap shows the page, not "…".');
        check_same([1, null, 19, 20], (new Paginator(200, 10, 20))->links());
        check_same([1], (new Paginator(5, 10))->links());
    },
    'Paginator::parsePage accepts only whole page numbers' => function (): void {
        check_same(1, Paginator::parsePage(null));
        check_same(1, Paginator::parsePage(''));
        check_same(7, Paginator::parsePage('7'));
        foreach (['0', '-1', '1.5', 'abc', ' 2', '2 ', '1e3', '9999999', '100001'] as $bad) {
            check_same(null, Paginator::parsePage($bad), "\"$bad\" must be rejected");
        }
        check_same(null, Paginator::parsePage(['2']));
    },
    'mostShared ranks by shares, keeps newest first on ties, and limits' => function () use ($stories): void {
        $list = $stories(30);
        $counts = [
            ArticleRepository::url($list[20]) => 50,
            ArticleRepository::url($list[5]) => 9,
            ArticleRepository::url($list[9]) => 9,
        ];
        $top = ArticleRepository::mostShared($list, $counts, 5);
        check_same(['Story 021', 'Story 006', 'Story 010', 'Story 001', 'Story 002'], array_column($top, 'title'));
        check_same([], ArticleRepository::mostShared($list, $counts, 0));
        check_same(30, count(ArticleRepository::mostShared($list, [], 100)));
    },
    'latest() returns the newest stories only' => function (): void {
        check_same(array_slice(ArticleRepository::all(), 0, 3), ArticleRepository::latest(3));
        check_same([], ArticleRepository::latest(-1));
    },
    'the front page shows 6 story cards per page, a pager and the top 5 trending' => function () use ($stories, $countOf): void {
        $list = $stories(40);
        $pager = new Paginator(39, 6, 1);
        $html = View::fetch('site/home', [
            'meta' => [], 'section' => null, 'homeUrl' => 'http://localhost/',
            'lead' => $list[0], 'stories' => array_slice($list, 1, 6), 'pager' => $pager,
            'pageUrl' => static fn (int $page): string => $page === 1 ? '/' : "/?page=$page",
            'allArticles' => $list, 'shareCounts' => [], 'platforms' => [],
        ]);
        check_same(6, $countOf($html, 'class="card-story"'), 'Only one page of cards is rendered.');
        check_same(5, $countOf($html, 'class="ranked__item'), 'Trending shows the top 5, not all 40.');
        check(str_contains($html, 'Showing 1–6 of 39'));
        check(str_contains($html, 'Page 1 of 7'));
        check(str_contains($html, 'href="/?page=2#latest" rel="next"'));
        check(str_contains($html, 'aria-current="page"'));
        check(str_contains($html, "Editor's picks"), 'The lead shows on page 1.');
    },
    'later pages drop the lead and link back' => function () use ($stories): void {
        $list = $stories(40);
        $pager = new Paginator(39, 6, 7);
        $html = View::fetch('site/home', [
            'meta' => [], 'section' => null, 'homeUrl' => 'http://localhost/',
            'lead' => null, 'stories' => array_slice($list, 1 + $pager->offset(), 6), 'pager' => $pager,
            'pageUrl' => static fn (int $page): string => $page === 1 ? '/' : "/?page=$page",
            'allArticles' => $list, 'shareCounts' => [], 'platforms' => [],
        ]);
        check(!str_contains($html, "Editor's picks"));
        check_same(3, substr_count($html, 'class="card-story"'), 'The last page holds the remaining 3 stories.');
        check(str_contains($html, 'Showing 37–39 of 39'));
        check(str_contains($html, 'href="/?page=6#latest" rel="prev"'));
        check(str_contains($html, '<span class="pager__link pager__link--step is-disabled" aria-hidden="true"><span>Next</span>'));
    },
    'a list that fits on one page has no pager' => function () use ($stories): void {
        $list = $stories(5);
        $html = View::fetch('site/home', [
            'meta' => [], 'section' => 'Food', 'homeUrl' => 'http://localhost/',
            'lead' => $list[0], 'stories' => array_slice($list, 1), 'pager' => new Paginator(4, 6),
            'pageUrl' => static fn (int $page): string => '/', 'allArticles' => $list, 'shareCounts' => [], 'platforms' => [],
        ]);
        check(!str_contains($html, 'class="pager"'));
    },
    'the article page ranks only the top 6 trending stories' => function () use ($stories, $countOf): void {
        $list = $stories(40);
        $html = View::fetch('site/article', [
            'meta' => ['url' => 'http://localhost/article.php?slug=story-001'],
            'article' => $list[0] + ['body' => ['One two', 'Three']],
            'all' => $list, 'previous' => null, 'next' => null,
            'shareCounts' => [], 'shareCount' => 0, 'platforms' => [],
        ]);
        check_same(6, $countOf($html, 'class="ranked__item'));
    },
    'the dashboard breakdown pages through every page, ranked' => function (): void {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE pages (id INTEGER PRIMARY KEY, url TEXT, title TEXT)');
        $pdo->exec('CREATE TABLE share_clicks (id INTEGER PRIMARY KEY, platform_id INT, page_id INT, created_at TEXT)');
        // 25 pages: page N gets N clicks, alternating platforms 1 and 2.
        for ($n = 1; $n <= 25; $n++) {
            $pdo->prepare('INSERT INTO pages (id, url, title) VALUES (?, ?, ?)')->execute([$n, "https://news.test/$n", "Page $n"]);
            for ($c = 0; $c < $n; $c++) {
                $pdo->prepare('INSERT INTO share_clicks (platform_id, page_id, created_at) VALUES (?, ?, ?)')->execute([1 + $c % 2, $n, '2026-10-05 12:00:00']);
            }
        }
        // A click outside the period must not count.
        $pdo->exec("INSERT INTO share_clicks (platform_id, page_id, created_at) VALUES (1, 1, '2026-09-01 12:00:00')");

        $stats = new ShareStats($pdo);
        $filter = new StatsFilter(new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-31'));
        $platforms = [['id' => 1, 'slug' => 'facebook'], ['id' => 2, 'slug' => 'x']];

        $first = $stats->pageBreakdown($filter, $platforms, 1);
        check_same(['page' => 1, 'per_page' => 10, 'total' => 25, 'pages' => 3], array_diff_key($first, ['rows' => 1]));
        check_same(['Page 25', 'Page 24'], array_column(array_slice($first['rows'], 0, 2), 'title'));
        check_same([1, 25, ['facebook' => 13, 'x' => 12]], [$first['rows'][0]['rank'], $first['rows'][0]['total'], $first['rows'][0]['counts']]);

        $last = $stats->pageBreakdown($filter, $platforms, 3);
        check_same(['Page 5', 'Page 4', 'Page 3', 'Page 2', 'Page 1'], array_column($last['rows'], 'title'));
        check_same([21, 25], [$last['rows'][0]['rank'], $last['rows'][4]['rank']]);
        check_same(1, $last['rows'][4]['total'], 'Clicks outside the period are excluded.');

        check_same(3, $stats->pageBreakdown($filter, $platforms, 50)['page'], 'A page past the end is clamped.');

        $seen = [];
        foreach ([1, 2, 3] as $p) {
            $seen = array_merge($seen, array_column($stats->pageBreakdown($filter, $platforms, $p)['rows'], 'id'));
        }
        check_same(range(25, 1), $seen, 'Every page appears exactly once, in rank order.');

        $empty = $stats->pageBreakdown(new StatsFilter(new DateTimeImmutable('2025-01-01'), new DateTimeImmutable('2025-01-31')), $platforms, 1);
        check_same([[], 0, 1], [$empty['rows'], $empty['total'], $empty['pages']]);
    },
];
