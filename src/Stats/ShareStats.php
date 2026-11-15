<?php

declare(strict_types=1);

namespace App\Stats;

use App\Exception\DatabaseException;
use App\Support\Paginator;
use DateTimeImmutable;
use Generator;
use PDO;
use PDOException;

final class ShareStats
{
    /** Rows per page of the dashboard's "Page breakdown" table; also the size of the top-pages chart. */
    public const BREAKDOWN_PER_PAGE = 10;
    public const BREAKDOWN_MAX_PER_PAGE = 100;

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
     * @param int $breakdownPage which page of the "Page breakdown" table to include
     * @return array<string, mixed>
     * @throws DatabaseException
     */
    public function report(StatsFilter $filter, array $platforms, int $breakdownPage = 1): array
    {
        try {
            return $this->buildReport($filter, $platforms, $breakdownPage);
        } catch (PDOException $e) {
            throw DatabaseException::during('build the share report', $e);
        }
    }

    /**
     * Rows are fetched one at a time, so the caller can stream them.
     * @return Generator<int, array{created_at: string, platform: string, url: string, title: ?string}>
     * @throws DatabaseException
     */
    public function exportRows(StatsFilter $filter): Generator
    {
        [$where, $params] = $this->where($filter);
        try {
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
        } catch (PDOException $e) {
            throw DatabaseException::during('export the share clicks', $e);
        }
    }

    /**
     * @param list<array<string, mixed>> $platforms
     * @return array<string, mixed>
     */
    private function buildReport(StatsFilter $filter, array $platforms, int $breakdownPage): array
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

        $summary = $this->summary($filter, $byPlatform);
        // The chart shows the top 10; the table pages through all of them, reusing the top 10 for page 1.
        $topPages = $this->topPages($filter, $shown);

        return [
            'filters' => $filter->toArray(),
            'summary' => $summary,
            'timeseries' => $this->timeseries($filter, $shown),
            'by_platform' => $byPlatform,
            'top_pages' => $topPages,
            'breakdown' => $this->breakdown($filter, $shown, $breakdownPage, self::BREAKDOWN_PER_PAGE, $summary['pages'], $topPages),
            'recent' => $this->recent($filter, $platforms),
        ];
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
     * One page of the "Page breakdown" table: pages ranked by shares in the filter period,
     * with per-platform counts and their overall rank. The page number is clamped, so a page
     * that no longer exists (e.g. after narrowing the filter) returns the last one instead.
     *
     * @param list<array<string, mixed>> $platforms columns of the table
     * @return array{rows: list<array<string, mixed>>, page: int, per_page: int, total: int, pages: int}
     * @throws DatabaseException
     */
    public function pageBreakdown(StatsFilter $filter, array $platforms, int $page = 1, int $perPage = self::BREAKDOWN_PER_PAGE): array
    {
        try {
            return $this->breakdown($filter, $platforms, $page, $perPage, null, null);
        } catch (PDOException $e) {
            throw DatabaseException::during('load the page breakdown', $e);
        }
    }

    /**
     * @param list<array<string, mixed>> $platforms
     * @param int|null $total distinct pages in the period, when the caller already counted them
     * @param list<array<string, mixed>>|null $firstPage rows 1..perPage, when already loaded
     * @return array{rows: list<array<string, mixed>>, page: int, per_page: int, total: int, pages: int}
     */
    private function breakdown(StatsFilter $filter, array $platforms, int $page, int $perPage, ?int $total, ?array $firstPage): array
    {
        if ($total === null) {
            [$where, $params] = $this->where($filter);
            $stmt = $this->db->prepare("SELECT COUNT(DISTINCT c.page_id) FROM share_clicks c $where");
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();
        }

        $pager = new Paginator($total, min(max(1, $perPage), self::BREAKDOWN_MAX_PER_PAGE), $page);
        $rows = match (true) {
            $total === 0 => [],
            $pager->page === 1 && $firstPage !== null => $firstPage,
            default => $this->topPages($filter, $platforms, $pager->perPage, $pager->offset()),
        };
        foreach ($rows as $i => $row) {
            $rows[$i]['rank'] = $pager->offset() + $i + 1;
        }

        return ['rows' => $rows] + $pager->toArray();
    }

    /**
     * Pages ranked by shares in the period (ties by ID, so paging is stable), with per-platform counts.
     * @param list<array<string, mixed>> $platforms
     * @return list<array<string, mixed>>
     */
    private function topPages(StatsFilter $filter, array $platforms, int $limit = self::BREAKDOWN_PER_PAGE, int $offset = 0): array
    {
        [$where, $params] = $this->where($filter);
        // $limit and $offset are ints from the caller (clamped by Paginator), never request strings.
        $stmt = $this->db->prepare(sprintf(
            "SELECT p.id, p.url, p.title, COUNT(*) AS total
             FROM share_clicks c
             JOIN pages p ON p.id = c.page_id
             $where
             GROUP BY p.id, p.url, p.title
             ORDER BY total DESC, p.id
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
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
