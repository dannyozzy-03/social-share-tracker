<?php

declare(strict_types=1);

use App\Admin\PageSearch;
use App\Repository\PageRepository;
use App\Validation\ValidationException;
use App\View;

/**
 * An in-memory SQLite copy of the two tables search() reads. The SQL in search() is
 * standard (LIKE … ESCAPE, COALESCE, LIMIT/OFFSET), so it runs unchanged on MySQL and SQLite.
 * @param list<array{0: string, 1: ?string, 2: int}> $pages url, title, clicks
 */
$database = static function (array $pages): PDO {
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE TABLE pages (id INTEGER PRIMARY KEY, url TEXT NOT NULL, url_hash TEXT, title TEXT NULL)');
    $pdo->exec('CREATE TABLE share_clicks (id INTEGER PRIMARY KEY, page_id INTEGER NOT NULL)');
    $page = $pdo->prepare('INSERT INTO pages (url, url_hash, title) VALUES (?, ?, ?)');
    $click = $pdo->prepare('INSERT INTO share_clicks (page_id) VALUES (?)');
    foreach ($pages as [$url, $title, $clicks]) {
        $page->execute([$url, hash('sha256', $url), $title]);
        $id = (int) $pdo->lastInsertId();
        for ($i = 0; $i < $clicks; $i++) {
            $click->execute([$id]);
        }
    }

    return $pdo;
};

$sample = [
    ['https://news.test/article.php?slug=penang-hawker', "A first-timer's guide to Penang's hawker centres", 4],
    ['https://news.test/article.php?slug=penang-walk', 'A relaxed evening food walk through Penang', 2],
    ['https://news.test/article.php?slug=kl-trails', 'Five easy hiking trails', 1],
    ['https://news.test/article.php?slug=discount', '100% free museum days', 1],
    ['https://news.test/article.php?slug=thousand', '1000 steps to the summit', 1],
    ['https://news.test/article.php?slug=never-shared', 'Penang never shared', 0],
    ['https://news.test/untitled_page', null, 3],
];

$titles = static fn (array $result): array => array_map(static fn (array $p): ?string => $p['title'], $result['pages']);

return [
    'an empty search lists shared pages newest first' => function () use ($database, $sample, $titles): void {
        $result = (new PageRepository($database($sample)))->search('');
        check_same([null, '1000 steps to the summit', '100% free museum days', 'Five easy hiking trails',
            'A relaxed evening food walk through Penang', "A first-timer's guide to Penang's hawker centres"], $titles($result));
        check_same(false, $result['has_more']);
    },
    'pages that were never shared are left out' => function () use ($database, $sample, $titles): void {
        check(!in_array('Penang never shared', $titles((new PageRepository($database($sample)))->search('never')), true));
    },
    'every word must match the title or the URL, in any case' => function () use ($database, $sample, $titles): void {
        $repo = new PageRepository($database($sample));
        check_same(["A first-timer's guide to Penang's hawker centres"], $titles($repo->search('PENANG hawker')));
        check_same(['A relaxed evening food walk through Penang'], $titles($repo->search('penang slug=penang-walk')));
        check_same([null], $titles($repo->search('untitled')), 'URL-only pages must be searchable.');
    },
    'titles that start with the query rank first' => function () use ($database, $titles): void {
        $repo = new PageRepository($database([
            ['https://news.test/a', 'Guide to Penang food', 1],
            ['https://news.test/b', 'Penang after dark', 1],
        ]));
        check_same(['Penang after dark', 'Guide to Penang food'], $titles($repo->search('penang')));
    },
    'LIKE wildcards in the query match literally' => function () use ($database, $sample, $titles): void {
        $repo = new PageRepository($database($sample));
        check_same(['100% free museum days'], $titles($repo->search('100%')), '"%" must not act as a wildcard.');
        check_same([null], $titles($repo->search('untitled_page')));
        check_same([], $titles($repo->search('untitled_xage')), '"_" must not match any character.');
        check_same([], $titles($repo->search('!')), 'The escape character itself must be escaped.');
    },
    'results are paged with a has_more flag and share totals' => function () use ($database): void {
        $pages = [];
        for ($i = 1; $i <= 45; $i++) {
            $pages[] = [sprintf('https://news.test/story-%02d', $i), sprintf('Story %02d', $i), $i % 3 + 1];
        }
        $repo = new PageRepository($database($pages));

        $first = $repo->search('story', 20, 0);
        check_same(20, count($first['pages']));
        check_same(true, $first['has_more']);
        check_same('Story 01', $first['pages'][0]['title']);
        check_same(2, $first['pages'][0]['shares']);

        $last = $repo->search('story', 20, 40);
        check_same(['Story 41', 'Story 42', 'Story 43', 'Story 44', 'Story 45'], array_column($last['pages'], 'title'));
        check_same(false, $last['has_more']);

        $ids = array_merge(...array_map(
            static fn (int $offset): array => array_column($repo->search('story', 20, $offset)['pages'], 'id'),
            [0, 20, 40]
        ));
        check_same(45, count(array_unique($ids)), 'Pages must not repeat or go missing across result pages.');
    },
    'limit and offset are clamped' => function () use ($database, $sample): void {
        $repo = new PageRepository($database($sample));
        check_same(1, count($repo->search('', 0)['pages']));
        check_same(6, count($repo->search('', 1000)['pages']));
        check_same(0, count($repo->search('', 20, PHP_INT_MAX)['pages']));
    },
    'find returns one page or null' => function () use ($database, $sample): void {
        $repo = new PageRepository($database($sample));
        check_same(['id' => 3, 'url' => 'https://news.test/article.php?slug=kl-trails', 'title' => 'Five easy hiking trails'], $repo->find(3));
        check_same(null, $repo->find(999));
    },
    'search terms are split, de-duplicated and capped' => function (): void {
        check_same(['Penang', 'food'], PageRepository::searchTerms("  Penang \t food penang  "));
        check_same([], PageRepository::searchTerms('   '));
        check_same(PageRepository::SEARCH_MAX_TERMS, count(PageRepository::searchTerms('a b c d e f g')));
        check_same('100!% !_x!!', PageRepository::escapeLike('100% _x!'));
    },
    'PageSearch validates the query string' => function (): void {
        $search = PageSearch::fromQuery(['q' => "  Penang\x07 hawker  ", 'limit' => '10', 'offset' => '40']);
        check_same('Penang hawker', $search->query);
        check_same(10, $search->limit);
        check_same(40, $search->offset);

        $defaults = PageSearch::fromQuery([]);
        check_same(['', 20, 0], [$defaults->query, $defaults->limit, $defaults->offset]);

        $e = check_throws(ValidationException::class, fn () => PageSearch::fromQuery([
            'q' => str_repeat('x', 101),
            'limit' => '0',
            'offset' => '-5',
        ]));
        check_same(['q', 'limit', 'offset'], array_keys($e->errors()));
        check_throws(ValidationException::class, fn () => PageSearch::fromQuery(['q' => ['x']]));
        check_throws(ValidationException::class, fn () => PageSearch::fromQuery(['q' => "\xC3\x28"]));
        check_throws(ValidationException::class, fn () => PageSearch::fromQuery(['limit' => '51']));
        check_throws(ValidationException::class, fn () => PageSearch::fromQuery(['offset' => '10001']));
        check_throws(ValidationException::class, fn () => PageSearch::fromQuery(['limit' => '1e3']));
    },
    'the dashboard renders a search picker, not a list of every page' => function (): void {
        $filter = new App\Stats\StatsFilter(new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-07'), 'day', [], 7);
        $html = View::fetch('admin/dashboard', [
            'filter' => $filter,
            'platforms' => [],
            'selectedPage' => ['id' => 7, 'url' => 'https://news.test/x', 'title' => '<b>Penang</b> & "friends"'],
        ]);
        check(!str_contains($html, '<select class="form-select" id="page"'), 'The page <select> must be gone.');
        check(str_contains($html, 'role="combobox"'), 'The picker must be an ARIA combobox.');
        check(str_contains($html, 'name="page" id="page" value="7"'), 'The selected page ID must be kept in the form.');
        check(str_contains($html, 'value="&lt;b&gt;Penang&lt;/b&gt; &amp; &quot;friends&quot;"'), 'The page title must be escaped.');
        check(!str_contains($html, '<b>Penang</b>'));
        check(str_contains($html, 'page=7'), 'The export link must carry the page filter.');
    },
];
