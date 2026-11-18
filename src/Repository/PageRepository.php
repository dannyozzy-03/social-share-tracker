<?php

declare(strict_types=1);

namespace App\Repository;

use App\Exception\DatabaseException;
use PDO;
use PDOException;

/**
 * Shared pages, found by a hash of their cleaned-up URL, and the dashboard's page search.
 *
 * Error handling: every public method catches PDOException and rethrows it as DatabaseException
 * naming the operation, with the driver error as the previous exception.
 */
final class PageRepository
{
    public const SEARCH_MAX_LIMIT = 50;
    public const SEARCH_MAX_OFFSET = 10000;
    public const SEARCH_MAX_TERMS = 5;

    public function __construct(private PDO $db)
    {
    }

    public static function hash(string $normalizedUrl): string
    {
        return hash('sha256', $normalizedUrl);
    }

    /** @throws DatabaseException */
    public function findOrCreate(string $normalizedUrl, ?string $title): int
    {
        try {
            $hash = self::hash($normalizedUrl);
            $page = $this->findByHash($hash);

            if ($page === null) {
                try {
                    $this->db->prepare('INSERT INTO pages (url, url_hash, title) VALUES (?, ?, ?)')
                        ->execute([$normalizedUrl, $hash, $title]);

                    return (int) $this->db->lastInsertId();
                } catch (PDOException $e) {
                    // Another request inserted the same page between SELECT and INSERT.
                    if ($e->getCode() !== '23000' || ($page = $this->findByHash($hash)) === null) {
                        throw $e;
                    }
                }
            }

            if ($title !== null && $title !== $page['title']) {
                $this->db->prepare('UPDATE pages SET title = ? WHERE id = ?')->execute([$title, $page['id']]);
            }

            return (int) $page['id'];
        } catch (PDOException $e) {
            throw DatabaseException::during('save the shared page', $e);
        }
    }

    /**
     * @return array{id: int, url: string, title: ?string}|null
     * @throws DatabaseException
     */
    public function find(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT id, url, title FROM pages WHERE id = ?');
            $stmt->execute([$id]);
            $page = $stmt->fetch();
        } catch (PDOException $e) {
            throw DatabaseException::during('load the page', $e);
        }

        return $page === false ? null : ['id' => (int) $page['id'], 'url' => (string) $page['url'], 'title' => $page['title']];
    }

    /**
     * One page of shared pages for the dashboard's page picker, so the dashboard never has to
     * load every page at once. Every word in $query must appear in the title or the URL.
     * An empty query lists the newest pages first; otherwise titles starting with the query
     * come first, then the rest alphabetically.
     *
     * @return array{pages: list<array{id: int, url: string, title: ?string, shares: int}>, has_more: bool}
     * @throws DatabaseException
     */
    public function search(string $query, int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min(self::SEARCH_MAX_LIMIT, $limit));
        $offset = max(0, min(self::SEARCH_MAX_OFFSET, $offset));
        $terms = self::searchTerms($query);

        // Native prepared statements can't reuse a named placeholder, so each use gets its own.
        $conditions = ['EXISTS (SELECT 1 FROM share_clicks c WHERE c.page_id = p.id)'];
        $params = [];
        foreach ($terms as $i => $term) {
            $conditions[] = "(p.title LIKE :title$i ESCAPE '!' OR p.url LIKE :url$i ESCAPE '!')";
            $params["title$i"] = $params["url$i"] = '%' . self::escapeLike($term) . '%';
        }
        if ($terms === []) {
            $order = 'p.id DESC';
        } else {
            $order = "(p.title LIKE :prefix ESCAPE '!') DESC, COALESCE(p.title, p.url), p.id";
            $params['prefix'] = self::escapeLike(implode(' ', $terms)) . '%';
        }

        try {
            // $limit and $offset are clamped integers, so interpolating them is safe;
            // one extra row tells us whether another page of results exists.
            $stmt = $this->db->prepare(sprintf(
                'SELECT p.id, p.url, p.title FROM pages p WHERE %s ORDER BY %s LIMIT %d OFFSET %d',
                implode(' AND ', $conditions),
                $order,
                $limit + 1,
                $offset
            ));
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            // Stop offering more once the next offset would pass the cap the endpoint accepts.
            $hasMore = count($rows) > $limit && $offset + $limit <= self::SEARCH_MAX_OFFSET;
            $rows = array_slice($rows, 0, $limit);
            $shares = $this->shareTotals(array_map(static fn (array $row): int => (int) $row['id'], $rows));
        } catch (PDOException $e) {
            throw DatabaseException::during('search the shared pages', $e);
        }

        return [
            'pages' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'url' => (string) $row['url'],
                'title' => $row['title'],
                'shares' => $shares[(int) $row['id']] ?? 0,
            ], $rows),
            'has_more' => $hasMore,
        ];
    }

    /**
     * Splits a search query into distinct, case-insensitive words.
     * @return list<string>
     */
    public static function searchTerms(string $query): array
    {
        $words = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $unique = [];
        foreach ($words as $word) {
            $unique[mb_strtolower($word)] ??= $word;
        }

        return array_slice(array_values($unique), 0, self::SEARCH_MAX_TERMS);
    }

    /** Escapes LIKE wildcards so "100%" or "my_page" match literally; pair with ESCAPE '!'. */
    public static function escapeLike(string $value): string
    {
        return strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']);
    }

    /**
     * @param list<int> $pageIds
     * @return array<int, int> page_id => all-time shares
     */
    private function shareTotals(array $pageIds): array
    {
        if ($pageIds === []) {
            return [];
        }
        $stmt = $this->db->prepare(sprintf(
            'SELECT page_id, COUNT(*) FROM share_clicks WHERE page_id IN (%s) GROUP BY page_id',
            implode(', ', array_fill(0, count($pageIds), '?'))
        ));
        $stmt->execute($pageIds);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** @return array{id: int, title: ?string}|null */
    private function findByHash(string $hash): ?array
    {
        $stmt = $this->db->prepare('SELECT id, title FROM pages WHERE url_hash = ?');
        $stmt->execute([$hash]);

        return $stmt->fetch() ?: null;
    }
}
