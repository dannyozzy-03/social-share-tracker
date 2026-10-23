<?php

declare(strict_types=1);

namespace App\Support;

use App\Content\ArticleRepository;
use App\Repository\PageRepository;
use DateTimeImmutable;
use PDO;

final class DemoSeeder
{
    private const PLATFORM_WEIGHTS = ['whatsapp' => 36, 'facebook' => 27, 'telegram' => 14, 'x' => 12, 'email' => 8];
    private const OTHER_PLATFORM_WEIGHT = 4;
    private const HOUR_WEIGHTS = [1, 1, 1, 1, 1, 2, 4, 7, 8, 6, 5, 6, 8, 7, 5, 5, 5, 6, 7, 8, 9, 8, 5, 2];
    private const USER_AGENTS = [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15',
    ];

    public function __construct(private PDO $db)
    {
    }

    public function run(int $days = 90, int $seed = 2026): int
    {
        mt_srand($seed);
        $pages = new PageRepository($this->db);
        $platforms = $this->db->query('SELECT slug, id FROM platforms WHERE is_active = 1')->fetchAll(PDO::FETCH_KEY_PAIR);
        $weights = [];
        foreach ($platforms as $slug => $id) {
            $weights[(int) $id] = self::PLATFORM_WEIGHTS[$slug] ?? self::OTHER_PLATFORM_WEIGHT;
        }
        if ($weights === []) {
            return 0;
        }

        $now = new DateTimeImmutable();
        $windowStart = $now->setTime(0, 0)->modify(sprintf('-%d days', $days - 1));

        $targets = [[
            'url' => app_url(),
            'title' => config('app.site_name') . ' — Latest stories',
            'published' => $windowStart,
            'base' => 3.0,
            'decay' => false,
        ]];
        foreach (ArticleRepository::all() as $i => $article) {
            $targets[] = [
                'url' => ArticleRepository::url($article),
                'title' => $article['title'],
                'published' => new DateTimeImmutable($article['published']),
                'base' => [22, 30, 14, 18][$i % 4],
                'decay' => true,
            ];
        }

        $viralDay = $now->setTime(0, 0)->modify('-' . min(24, $days - 1) . ' days');
        $rows = [];
        $inserted = 0;

        foreach ($targets as $index => $target) {
            $pageId = $pages->findOrCreate((string) Url::normalize($target['url']), $target['title']);

            for ($day = $windowStart; $day <= $now; $day = $day->modify('+1 day')) {
                if ($day < $target['published']->setTime(0, 0)) {
                    continue;
                }
                $age = (int) $target['published']->diff($day)->days;
                $expected = $target['base'] * ($target['decay'] ? 0.12 + 1.4 * exp(-$age / 6) : 1.0);
                $expected *= (int) $day->format('N') >= 6 ? 1.3 : 1.0;
                if ($index === 2 && abs((int) $viralDay->diff($day)->days) <= 1) {
                    $expected *= 4; // the hiking article briefly goes viral
                }

                $clicks = (int) round($expected * (0.6 + mt_rand() / mt_getrandmax() * 0.8));
                for ($i = 0; $i < $clicks; $i++) {
                    $at = $day->setTime(self::weightedPick(self::HOUR_WEIGHTS), mt_rand(0, 59), mt_rand(0, 59));
                    if ($at > $now) {
                        continue;
                    }
                    array_push(
                        $rows,
                        self::weightedPick($weights),
                        $pageId,
                        ip_hash('203.0.113.' . mt_rand(1, 254)),
                        self::USER_AGENTS[mt_rand(0, count(self::USER_AGENTS) - 1)],
                        $at->format('Y-m-d H:i:s')
                    );
                    if (count($rows) >= 500 * 5) {
                        $inserted += $this->insert($rows);
                        $rows = [];
                    }
                }
            }
        }

        return $inserted + $this->insert($rows);
    }

    public function clear(): void
    {
        $this->db->exec('DELETE FROM share_clicks');
        $this->db->exec('DELETE FROM pages');
    }

    /** @param list<mixed> $values flat, five values per row */
    private function insert(array $values): int
    {
        $count = intdiv(count($values), 5);
        if ($count === 0) {
            return 0;
        }
        $this->db->prepare(
            'INSERT INTO share_clicks (platform_id, page_id, ip_hash, user_agent, created_at) VALUES '
            . implode(', ', array_fill(0, $count, '(?, ?, ?, ?, ?)'))
        )->execute($values);

        return $count;
    }

    /** @param array<int, int> $weights key => weight */
    private static function weightedPick(array $weights): int
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $key => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }
}
