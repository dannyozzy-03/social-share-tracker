<?php

declare(strict_types=1);

namespace App\Repository;

use App\Exception\DatabaseException;
use PDO;
use PDOException;

final class PlatformRepository
{
    private const COLUMNS = ['slug', 'name', 'share_url_template', 'icon', 'color', 'sort_order', 'is_active'];

    public function __construct(private PDO $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     * @throws DatabaseException
     */
    public function all(): array
    {
        try {
            return $this->db->query('SELECT * FROM platforms ORDER BY sort_order, name')->fetchAll();
        } catch (PDOException $e) {
            throw DatabaseException::during('load the platforms', $e);
        }
    }

    /**
     * @return list<array<string, mixed>>
     * @throws DatabaseException
     */
    public function active(): array
    {
        try {
            return $this->db->query('SELECT * FROM platforms WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
        } catch (PDOException $e) {
            throw DatabaseException::during('load the active platforms', $e);
        }
    }

    /**
     * @return list<array<string, mixed>>
     * @throws DatabaseException
     */
    public function allWithClickCounts(): array
    {
        try {
            return $this->db->query(
                'SELECT p.*, (SELECT COUNT(*) FROM share_clicks c WHERE c.platform_id = p.id) AS clicks
                 FROM platforms p
                 ORDER BY p.sort_order, p.name'
            )->fetchAll();
        } catch (PDOException $e) {
            throw DatabaseException::during('load the platforms with their click counts', $e);
        }
    }

    /**
     * @return array<string, mixed>|null
     * @throws DatabaseException
     */
    public function find(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM platforms WHERE id = ?');
            $stmt->execute([$id]);

            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            throw DatabaseException::during('load the platform', $e);
        }
    }

    /**
     * @return array<string, mixed>|null
     * @throws DatabaseException
     */
    public function findActiveBySlug(string $slug): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM platforms WHERE slug = ? AND is_active = 1');
            $stmt->execute([$slug]);

            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            throw DatabaseException::during('look up the platform', $e);
        }
    }

    /** @throws DatabaseException */
    public function slugExists(string $slug): bool
    {
        try {
            $stmt = $this->db->prepare('SELECT 1 FROM platforms WHERE slug = ?');
            $stmt->execute([$slug]);

            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            throw DatabaseException::during('check the platform slug', $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @throws DatabaseException with sqlState() "23000" when the slug was taken concurrently
     */
    public function create(array $data): int
    {
        // Column names come from the COLUMNS whitelist, never from the request.
        $data = array_intersect_key($data, array_flip(self::COLUMNS));
        $columns = array_keys($data);
        try {
            $stmt = $this->db->prepare(sprintf(
                'INSERT INTO platforms (%s) VALUES (%s)',
                implode(', ', $columns),
                implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
            ));
            $stmt->execute($data);

            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            throw DatabaseException::during('create the platform', $e);
        }
    }

    /**
     * The slug is immutable: recorded clicks and front-end markup refer to it.
     * @param array<string, mixed> $data
     * @throws DatabaseException
     */
    public function update(int $id, array $data): void
    {
        $data = array_intersect_key($data, array_flip(array_diff(self::COLUMNS, ['slug'])));
        if ($data === []) {
            return;
        }
        $assignments = array_map(static fn (string $c): string => $c . ' = :' . $c, array_keys($data));
        try {
            $stmt = $this->db->prepare('UPDATE platforms SET ' . implode(', ', $assignments) . ' WHERE id = :id');
            $stmt->execute($data + ['id' => $id]);
        } catch (PDOException $e) {
            throw DatabaseException::during('update the platform', $e);
        }
    }

    /** @throws DatabaseException */
    public function setActive(int $id, bool $active): void
    {
        try {
            $this->db->prepare('UPDATE platforms SET is_active = ? WHERE id = ?')->execute([(int) $active, $id]);
        } catch (PDOException $e) {
            throw DatabaseException::during('change the platform visibility', $e);
        }
    }
}
