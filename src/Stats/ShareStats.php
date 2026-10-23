<?php

declare(strict_types=1);

namespace App\Stats;

use DateTimeImmutable;
use Generator;
use PDO;

final class ShareStats
{
    /** Interpolated into SQL, so keys are whitelisted granularities. */
    private const BUCKETS = [
        'day' => 'DATE(c.created_at)',
        'week' => 'DATE(DATE_SUB(c.created_at, INTERVAL WEEKDAY(c.created_at) DAY))',
        'month' => "DATE_FORMAT(c.created_at, '%Y-%m-01')",
    ];

    public function __construct(private PDO $db)
    {
    }

    /**
     * @param list<array<string, mixed>> $platforms
     * @return array<string, mixed>
     */
    public function report(StatsFilter $filter, array $platforms): array
    {
        $counts = $this->countsByPlatform($filter);

        // Skip hidden platforms with no clicks in the period so retired networks don't clutter graphs.
        $shown = array_values(array_filter($platforms, static fn (array $p): bool =>
            ($filter->platformIds === [] || in_array((int) $p['id'], $filter->platformIds, true))
            && ((int) $p['is_active'] === 1 || ($counts[(int) $p['id']] ?? 0) > 0)));

        $byPlatform = array_map(static fn (array $p): array => self::platformInfo($p) + [
            'count' => $counts[(int) $p['id']] ?? 0,
        ], $shown);
        usort($byPlatform, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return [
            'filters' => $filter->toArray(),
            'summary' => $this->summary($filter, $byPlatform),
            'timeseries' => $this->timeseries($filter, $shown),
            'by_platform' => $byPlatform,
            'top_pages' => $this->topPages($filter, $shown),
            'recent' => $this->recent($filter, $platforms),
        ];
    }

    /** @return Generator<int, array{created_at: string, platform: string, url: string, title: ?string}> */
    public function exportRows(StatsFilter $filter): Generator
    {
        [$where, $params] = $this->where($filter);
        $stmt = $this->db->prepare(
            "SELECT c.created_at, pl.name AS platform, p.url, p.title
             FROM share_clicks c
             JOIN platforms pl ON pl.id = c.platform_id
             JOIN pages p ON p.id = c.page_id
             $where
             ORDER BY c.created_at, c.id"
        );
        $stmt->execute($params);
        while (($row = $stmt->fetch()) !== false) {
            yield $row;
        }
    }

    /** @return array<int, int> platform_id => clicks */
    private function countsByPlatform(StatsFilter $filter): array
    {
        [$where, $params] = $this->where($filter);
        $stmt = $this->db->prepare("SELECT c.platform_id, COUNT(*) FROM share_clicks c $where GROUP BY c.platform_id");
        $stmt->execute($params);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /**
     * @param list<array<string, mixed>> $byPlatform
     * @return array<string, mixed>
     */
    private function summary(StatsFilter $filter, array $byPlatform): array
    {
        [$where, $params] = $this->where($filter);
        $stmt = $this->db->prepare("SELECT COUNT(*) AS total, COUNT(DISTINCT c.page_id) AS pages FROM share_clicks c $where");
        $stmt->execute($params);
        $current = $stmt->fetch();

        [$where, $params] = $this->where($filter->previousPeriod());
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM share_clicks c $where");
        $stmt->execute($params);
        $previous = (int) $stmt->fetchColumn();

        $total = (int) $current['total'];
        $top = $byPlatform !== [] && $byPlatform[0]['count'] > 0 ? $byPlatform[0] : null;

        return [
            'total' => $total,
            'previous_total' => $previous,
            'change_pct' => $previous > 0 ? round(($total - $previous) / $previous * 100, 1) : null,
            'pages' => (int) $current['pages'],
            'daily_average' => round($total / $filter->days(), 1),
            'top_platform' => $top,
            'top_platform_share' => $top !== null && $total > 0 ? round($top['count'] / $total * 100, 1) : null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $platforms
     * @return array{periods: list<string>, series: list<array<string, mixed>>}
     */
    private function timeseries(StatsFilter $filter, array $platforms): array
    {
        [$where, $params] = $this->where($filter);
        $bucket = self::BUCKETS[$filter->granularity];
        $stmt = $this->db->prepare(
            "SELECT $bucket AS bucket, c.platform_id, COUNT(*) AS total
             FROM share_clicks c
             $where
             GROUP BY bucket, c.platform_id"
        );
        $stmt->execute($params);

        $matrix = [];
        foreach ($stmt->fetchAll() as $row) {
            $matrix[(string) $row['bucket']][(int) $row['platform_id']] = (int) $row['total'];
        }

        $periods = $filter->periods();
        $series = array_map(static fn (array $p): array => self::platformInfo($p) + [
            'data' => array_map(static fn (string $period): int => $matrix[$period][(int) $p['id']] ?? 0, $periods),
        ], $platforms);

        return ['periods' => $periods, 'series' => $series];
    }

    /**
     * @param list<array<string, mixed>> $platforms
     * @return list<array<string, mixed>>
     */
    private function topPages(StatsFilter $filter, array $platforms, int $limit = 10): array
    {
        [$where, $params] = $this->where($filter);
        $stmt = $this->db->prepare(
            "SELECT p.id, p.url, p.title, COUNT(*) AS total
             FROM share_clicks c
             JOIN pages p ON p.id = c.page_id
             $where
             GROUP BY p.id, p.url, p.title
             ORDER BY total DESC, p.id
             LIMIT $limit"
        );
        $stmt->execute($params);
        $pages = $stmt->fetchAll();
        if ($pages === []) {
            return [];
        }

        $placeholders = [];
        foreach ($pages as $i => $page) {
            $placeholders[] = ':pg' . $i;
            $params['pg' . $i] = (int) $page['id'];
        }
        $stmt = $this->db->prepare(
            "SELECT c.page_id, c.platform_id, COUNT(*) AS total
             FROM share_clicks c
             $where AND c.page_id IN (" . implode(', ', $placeholders) . ')
             GROUP BY c.page_id, c.platform_id'
        );
        $stmt->execute($params);
        $breakdown = [];
        foreach ($stmt->fetchAll() as $row) {
            $breakdown[(int) $row['page_id']][(int) $row['platform_id']] = (int) $row['total'];
        }

        return array_map(static function (array $page) use ($platforms, $breakdown): array {
            $counts = [];
            foreach ($platforms as $p) {
                $counts[$p['slug']] = $breakdown[(int) $page['id']][(int) $p['id']] ?? 0;
            }

            return [
                'id' => (int) $page['id'],
                'url' => $page['url'],
                'title' => $page['title'],
                'total' => (int) $page['total'],
                'counts' => $counts,
            ];
        }, $pages);
    }

    /**
     * @param list<array<string, mixed>> $platforms
     * @return list<array<string, mixed>>
     */
    private function recent(StatsFilter $filter, array $platforms, int $limit = 12): array
    {
        $info = [];
        foreach ($platforms as $p) {
            $info[(int) $p['id']] = self::platformInfo($p);
        }

        [$where, $params] = $this->where($filter);
        $stmt = $this->db->prepare(
            "SELECT c.created_at, c.platform_id, p.id AS page_id, p.url, p.title
             FROM share_clicks c
             JOIN pages p ON p.id = c.page_id
             $where
             ORDER BY c.created_at DESC, c.id DESC
             LIMIT $limit"
        );
        $stmt->execute($params);

        return array_map(static fn (array $row): array => [
            'created_at' => (new DateTimeImmutable($row['created_at']))->format(DATE_ATOM),
            'platform' => $info[(int) $row['platform_id']] ?? null,
            'page' => ['id' => (int) $row['page_id'], 'url' => $row['url'], 'title' => $row['title']],
        ], $stmt->fetchAll());
    }

    /** @return array{0: string, 1: array<string, int|string>} */
    private function where(StatsFilter $filter): array
    {
        $conditions = ['c.created_at >= :from', 'c.created_at < :until'];
        $params = [
            'from' => $filter->from->format('Y-m-d 00:00:00'),
            'until' => $filter->to->modify('+1 day')->format('Y-m-d 00:00:00'),
        ];

        if ($filter->platformIds !== []) {
            $placeholders = [];
            foreach ($filter->platformIds as $i => $id) {
                $placeholders[] = ':pl' . $i;
                $params['pl' . $i] = $id;
            }
            $conditions[] = 'c.platform_id IN (' . implode(', ', $placeholders) . ')';
        }

        if ($filter->pageId !== null) {
            $conditions[] = 'c.page_id = :page';
            $params['page'] = $filter->pageId;
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    /**
     * @param array<string, mixed> $platform
     * @return array{id: int, slug: string, name: string, color: string, stage_color: string, icon: string, is_active: bool}
     */
    private static function platformInfo(array $platform): array
    {
        return [
            'id' => (int) $platform['id'],
            'slug' => $platform['slug'],
            'name' => $platform['name'],
            'color' => $platform['color'],
            'stage_color' => on_stage($platform['color']),
            'icon' => $platform['icon'],
            'is_active' => (int) $platform['is_active'] === 1,
        ];
    }
}
