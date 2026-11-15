<?php

declare(strict_types=1);

use App\Stats\ShareStats;
use App\Stats\StatsFilter;
use App\Support\DemoSeeder;

/** SQLite copy of the tables the seeder writes, with the same ON DELETE CASCADE as schema.sql. */
$database = static function (): PDO {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE platforms (id INTEGER PRIMARY KEY, slug TEXT, is_active INT)');
    $pdo->exec('CREATE TABLE pages (id INTEGER PRIMARY KEY, url TEXT NOT NULL, url_hash TEXT NOT NULL UNIQUE, title TEXT)');
    $pdo->exec('CREATE TABLE share_clicks (id INTEGER PRIMARY KEY, platform_id INT NOT NULL REFERENCES platforms (id),
        page_id INT NOT NULL REFERENCES pages (id) ON DELETE CASCADE, ip_hash TEXT, user_agent TEXT, created_at TEXT NOT NULL)');
    $pdo->exec("INSERT INTO platforms (id, slug, is_active) VALUES (1, 'facebook', 1), (2, 'whatsapp', 1), (3, 'linkedin', 0)");
    // One real page with one click, which the archive must never touch.
    $pdo->exec("INSERT INTO pages (id, url, url_hash, title) VALUES (1, 'http://localhost:8000/', 'h', 'Front page')");
    $pdo->exec("INSERT INTO share_clicks (platform_id, page_id, created_at) VALUES (1, 1, '2026-10-01 10:00:00')");

    return $pdo;
};
$count = static fn (PDO $pdo, string $sql): int => (int) $pdo->query($sql)->fetchColumn();
$archiveWhere = "url LIKE 'https://" . DemoSeeder::ARCHIVE_HOST . "/%'";

return [
    'the archive adds many realistic pages and keeps existing data' => function () use ($database, $count, $archiveWhere): void {
        $pdo = $database();
        $result = (new DemoSeeder($pdo))->addArchive(300);

        check_same(300, $result['pages']);
        check_same(300, $count($pdo, "SELECT COUNT(*) FROM pages WHERE $archiveWhere"));
        check_same($result['clicks'], $count($pdo, 'SELECT COUNT(*) FROM share_clicks WHERE page_id <> 1'));
        check($result['clicks'] >= 300, 'Every archive page has at least one share.');
        check_same(0, $count($pdo, 'SELECT COUNT(*) FROM pages p WHERE NOT EXISTS (SELECT 1 FROM share_clicks c WHERE c.page_id = p.id)'));
        check_same(1, $count($pdo, "SELECT COUNT(*) FROM pages WHERE id = 1 AND title = 'Front page'"), 'The existing page is untouched.');
        check_same(1, $count($pdo, 'SELECT COUNT(*) FROM share_clicks WHERE page_id = 1'));
        check_same(300, $count($pdo, 'SELECT COUNT(DISTINCT title) FROM pages WHERE ' . $archiveWhere), 'Titles are distinct.');
        check_same(0, $count($pdo, 'SELECT COUNT(*) FROM share_clicks WHERE platform_id = 3'), 'Hidden platforms get no demo shares.');
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        check_same(0, $count($pdo, "SELECT COUNT(*) FROM share_clicks WHERE created_at > '$now'"), 'No shares in the future.');

        // A long tail: the most shared page has far more shares than the typical one.
        $top = $count($pdo, 'SELECT MAX(n) FROM (SELECT COUNT(*) AS n FROM share_clicks WHERE page_id <> 1 GROUP BY page_id)');
        $ones = $count($pdo, 'SELECT COUNT(*) FROM (SELECT COUNT(*) AS n FROM share_clicks WHERE page_id <> 1 GROUP BY page_id HAVING n = 1)');
        check($top >= 25, "Expected a popular page, got a top of $top shares.");
        check($ones > 100, "Expected many pages shared once, got $ones.");
    },
    'running it again replaces the archive instead of doubling it' => function () use ($database, $count, $archiveWhere): void {
        $pdo = $database();
        $seeder = new DemoSeeder($pdo);
        $first = $seeder->addArchive(120);
        $titles = $pdo->query("SELECT title FROM pages WHERE $archiveWhere ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $second = $seeder->addArchive(120);
        check_same(120, $count($pdo, "SELECT COUNT(*) FROM pages WHERE $archiveWhere"));
        check_same($first['clicks'], $second['clicks'], 'The same seed gives the same archive.');
        check_same($titles, $pdo->query("SELECT title FROM pages WHERE $archiveWhere ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
        check_same($first['clicks'] + 1, $count($pdo, 'SELECT COUNT(*) FROM share_clicks'), 'Old archive clicks were removed with their pages.');
    },
    'removeArchive takes out only the archive' => function () use ($database, $count): void {
        $pdo = $database();
        $seeder = new DemoSeeder($pdo);
        $seeder->addArchive(80);
        check_same(80, $seeder->removeArchive());
        check_same(1, $count($pdo, 'SELECT COUNT(*) FROM pages'));
        check_same(1, $count($pdo, 'SELECT COUNT(*) FROM share_clicks'));
        check_same(0, $seeder->removeArchive(), 'Removing twice is harmless.');
    },
    'more pages than headlines get numbered parts, so titles stay distinct' => function () use ($database, $count, $archiveWhere): void {
        $pdo = $database();
        (new DemoSeeder($pdo))->addArchive(2200);
        check_same(2200, $count($pdo, "SELECT COUNT(DISTINCT title) FROM pages WHERE $archiveWhere"));
        check($count($pdo, "SELECT COUNT(*) FROM pages WHERE title LIKE '% (part 2)'") > 0);
        check_same(2200, $count($pdo, "SELECT COUNT(DISTINCT url) FROM pages WHERE $archiveWhere"));
    },
    'the archive fills the paged Page breakdown' => function () use ($database): void {
        $pdo = $database();
        (new DemoSeeder($pdo))->addArchive(500);
        $today = new DateTimeImmutable('today');
        $breakdown = (new ShareStats($pdo))->pageBreakdown(
            new StatsFilter($today->modify('-89 days'), $today),
            [['id' => 1, 'slug' => 'facebook'], ['id' => 2, 'slug' => 'whatsapp']],
            2
        );
        check_same(2, $breakdown['page']);
        check($breakdown['total'] >= 400, 'Most archive pages were shared in the last 90 days, got ' . $breakdown['total']);
        check_same(10, count($breakdown['rows']));
        check_same(11, $breakdown['rows'][0]['rank']);
    },
];
