<?php

declare(strict_types=1);

use App\Stats\ShareStats;
use App\Stats\StatsFilter;

/**
 * The ranking behind "Most shared pages" and the paged "Page breakdown" table, run on an in-memory
 * SQLite copy of the two tables it reads. (report() also groups by MySQL date functions for the
 * timeline, so the whole report is covered by the CI smoke test against MySQL instead.)
 */
$database = static function (): PDO {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('CREATE TABLE pages (id INTEGER PRIMARY KEY, url TEXT NOT NULL, title TEXT)');
    $pdo->exec('CREATE TABLE share_clicks (id INTEGER PRIMARY KEY, platform_id INT NOT NULL, page_id INT NOT NULL, created_at TEXT NOT NULL)');

    // Page ID => [Facebook clicks, WhatsApp clicks] inside the period. Totals: 2, 5, 2, 2, 1, 4.
    $clicks = [1 => [1, 1], 2 => [3, 2], 3 => [0, 2], 4 => [2, 0], 5 => [1, 0], 6 => [0, 4]];
    $page = $pdo->prepare('INSERT INTO pages (id, url, title) VALUES (?, ?, ?)');
    $click = $pdo->prepare('INSERT INTO share_clicks (platform_id, page_id, created_at) VALUES (?, ?, ?)');
    foreach ($clicks as $id => [$facebook, $whatsapp]) {
        $page->execute([$id, "https://news.test/story-$id", "Story $id"]);
        foreach ([1 => $facebook, 2 => $whatsapp] as $platformId => $count) {
            for ($i = 0; $i < $count; $i++) {
                $click->execute([$platformId, $id, '2026-10-03 12:00:00']);
            }
        }
    }
    // Shares outside the period must not count: without the date filter, page 5 would lead.
    for ($i = 0; $i < 10; $i++) {
        $click->execute([1, 5, '2026-09-01 12:00:00']);
    }

    return $pdo;
};

$platforms = [['id' => 1, 'slug' => 'facebook'], ['id' => 2, 'slug' => 'whatsapp']];
$october = static fn (array $platformIds = [], ?int $pageId = null): StatsFilter =>
    new StatsFilter(new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-07'), 'day', $platformIds, $pageId);
$summary = static fn (array $breakdown): array => array_map(
    static fn (array $row): array => [$row['rank'], $row['id'], $row['total']],
    $breakdown['rows']
);

return [
    'pages are ranked by shares in the period, ties by page ID, with ranks across table pages' => function () use ($database, $platforms, $october, $summary): void {
        $stats = new ShareStats($database());
        check_same([[1, 2, 5], [2, 6, 4]], $summary($stats->pageBreakdown($october(), $platforms, 1, 2)));
        check_same([[3, 1, 2], [4, 3, 2]], $summary($stats->pageBreakdown($october(), $platforms, 2, 2)));

        $last = $stats->pageBreakdown($october(), $platforms, 3, 2);
        check_same([[5, 4, 2], [6, 5, 1]], $summary($last));
        check_same(['page' => 3, 'per_page' => 2, 'total' => 6, 'pages' => 3], array_diff_key($last, ['rows' => 0]));
    },
    'each row carries its title, URL and per-platform counts' => function () use ($database, $platforms, $october): void {
        $row = (new ShareStats($database()))->pageBreakdown($october(), $platforms, 1, 1)['rows'][0];
        check_same(['id' => 2, 'url' => 'https://news.test/story-2', 'title' => 'Story 2', 'total' => 5,
            'counts' => ['facebook' => 3, 'whatsapp' => 2], 'rank' => 1], $row);
    },
    'the platform and page filters narrow the ranking' => function () use ($database, $platforms, $october, $summary): void {
        $stats = new ShareStats($database());
        $whatsapp = $stats->pageBreakdown($october([2]), $platforms, 1, 10);
        check_same([[1, 6, 4], [2, 2, 2], [3, 3, 2], [4, 1, 1]], $summary($whatsapp));
        check_same(0, $whatsapp['rows'][0]['counts']['facebook'], 'Filtered-out platforms count zero.');

        check_same([[1, 6, 4]], $summary($stats->pageBreakdown($october([], 6), $platforms, 1, 10)));
    },
    'a table page past the end shows the last page, and an empty period has no rows' => function () use ($database, $platforms, $october, $summary): void {
        $stats = new ShareStats($database());
        check_same([[5, 4, 2], [6, 5, 1]], $summary($stats->pageBreakdown($october(), $platforms, 99, 2)));

        $empty = $stats->pageBreakdown(
            new StatsFilter(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-07')),
            $platforms
        );
        check_same([], $empty['rows']);
        check_same(0, $empty['total']);
    },
];
