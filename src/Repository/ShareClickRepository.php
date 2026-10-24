<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Url;
use DateTimeInterface;
use PDO;

final class ShareClickRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function record(int $platformId, int $pageId, string $ipHash, ?string $userAgent, DateTimeInterface $at): int
    {
        $this->db->prepare(
            'INSERT INTO share_clicks (platform_id, page_id, ip_hash, user_agent, created_at) VALUES (?, ?, ?, ?, ?)'
        )->execute([$platformId, $pageId, $ipHash, $userAgent, $at->format('Y-m-d H:i:s')]);

        return (int) $this->db->lastInsertId();
    }

    public function existsSince(int $platformId, int $pageId, string $ipHash, DateTimeInterface $since): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM share_clicks
             WHERE ip_hash = ? AND created_at >= ? AND page_id = ? AND platform_id = ?
             LIMIT 1'
        );
        $stmt->execute([$ipHash, $since->format('Y-m-d H:i:s'), $pageId, $platformId]);

        return $stmt->fetchColumn() !== false;
    }

    public function countByVisitorSince(string $ipHash, DateTimeInterface $since): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM share_clicks WHERE ip_hash = ? AND created_at >= ?');
        $stmt->execute([$ipHash, $since->format('Y-m-d H:i:s')]);

        return (int) $stmt->fetchColumn();
    }

    public function countForPage(int $pageId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM share_clicks WHERE page_id = ?');
        $stmt->execute([$pageId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param list<string> $urls
     * @return array<string, int> keyed by the URLs as passed in
     */
    public function countsForUrls(array $urls): array
    {
        $keys = [];
        $hashes = [];
        foreach ($urls as $url) {
            $normalized = Url::normalizeForCounts($url) ?? $url;
            $keys[$url] = $normalized;
            $hashes[] = PageRepository::hash($normalized);
        }
        if ($hashes === []) {
            return [];
        }

        $localAliases = [];
        foreach (array_values(array_unique($keys)) as $key) {
            $host = strtolower((string) parse_url($key, PHP_URL_HOST));
            if ($host === 'localhost') {
                foreach (['localhost', '127.0.0.1', '[::1]'] as $alias) {
                    $localAliases[] = parse_url($key, PHP_URL_SCHEME) . '://' . $alias . ':%';
                }
            }
        }

        $where = sprintf('p.url_hash IN (%s)', implode(', ', array_fill(0, count($hashes), '?')));
        $params = $hashes;
        if ($localAliases !== []) {
            $where .= ' OR ' . implode(' OR ', array_fill(0, count($localAliases), 'p.url LIKE ?'));
            array_push($params, ...$localAliases);
        }

        $stmt = $this->db->prepare(sprintf(
            'SELECT p.url, COUNT(c.id) AS total
             FROM pages p
             JOIN share_clicks c ON c.page_id = p.id
             WHERE %s
             GROUP BY p.url, p.url_hash',
            $where
        ));
        $stmt->execute($params);
        $totals = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = Url::normalizeForCounts((string) $row['url']) ?? (string) $row['url'];
            $totals[$key] = ($totals[$key] ?? 0) + (int) $row['total'];
        }

        return array_map(static fn (string $key): int => $totals[$key] ?? 0, $keys);
    }
}
