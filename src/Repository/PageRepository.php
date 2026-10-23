<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use PDOException;

final class PageRepository
{
    public function __construct(private PDO $db)
    {
    }

    public static function hash(string $normalizedUrl): string
    {
        return hash('sha256', $normalizedUrl);
    }

    public function findOrCreate(string $normalizedUrl, ?string $title): int
    {
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
    }

    /** @return list<array{id: int, url: string, title: ?string}> */
    public function allShared(): array
    {
        return $this->db->query(
            'SELECT p.id, p.url, p.title
             FROM pages p
             WHERE EXISTS (SELECT 1 FROM share_clicks c WHERE c.page_id = p.id)
             ORDER BY COALESCE(p.title, p.url)'
        )->fetchAll();
    }

    /** @return array{id: int, title: ?string}|null */
    private function findByHash(string $hash): ?array
    {
        $stmt = $this->db->prepare('SELECT id, title FROM pages WHERE url_hash = ?');
        $stmt->execute([$hash]);

        return $stmt->fetch() ?: null;
    }
}
