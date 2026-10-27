<?php

declare(strict_types=1);

use App\Repository\PageRepository;
use App\Repository\PlatformRepository;
use App\Repository\ShareClickRepository;
use App\Share\ShareTracker;

return [
    'local shares append to totals recorded under another localhost port' => function (): void {
        $db = new \PDO('sqlite::memory:', options: [\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $db->exec('CREATE TABLE platforms (id INTEGER PRIMARY KEY, slug TEXT, is_active INTEGER)');
        $db->exec('CREATE TABLE pages (id INTEGER PRIMARY KEY AUTOINCREMENT, url TEXT, url_hash TEXT UNIQUE, title TEXT)');
        $db->exec('CREATE TABLE share_clicks (id INTEGER PRIMARY KEY AUTOINCREMENT, platform_id INTEGER, page_id INTEGER, ip_hash TEXT, user_agent TEXT, created_at TEXT)');
        $db->exec("INSERT INTO platforms (id, slug, is_active) VALUES (1, 'facebook', 1)");

        $legacyUrl = 'http://localhost:8088/article.php?slug=story';
        $legacyHash = PageRepository::hash($legacyUrl);
        $db->prepare('INSERT INTO pages (id, url, url_hash, title) VALUES (1, ?, ?, ?)')
            ->execute([$legacyUrl, $legacyHash, 'Story']);
        $insertClick = $db->prepare(
            'INSERT INTO share_clicks (platform_id, page_id, ip_hash, user_agent, created_at) VALUES (1, 1, ?, ?, ?)'
        );
        for ($i = 0; $i < 428; $i++) {
            $insertClick->execute(['previous-visitor-' . $i, 'test', '2026-10-27 11:00:00']);
        }

        $clicks = new ShareClickRepository($db);
        $url = 'http://localhost:8000/article.php?slug=story';
        check_same(428, $clicks->countsForUrls([$url])[$url]);

        $tracker = new ShareTracker(
            new PlatformRepository($db),
            new PageRepository($db),
            $clicks,
            ['localhost']
        );
        $now = new \DateTimeImmutable('2026-10-27 12:00:00');
        $result = $tracker->track(['platform' => 'facebook', 'url' => $url, 'title' => 'Story'], 'visitor', 'test', $now);

        check_same(['recorded' => true, 'total' => 429], $result);
        check_same(429, $clicks->countsForUrls([$url])[$url]);

        $duplicate = $tracker->track(['platform' => 'facebook', 'url' => $url, 'title' => 'Story'], 'visitor', 'test', $now->modify('+5 seconds'));
        check_same(['recorded' => false, 'total' => 429], $duplicate);
    },
];
