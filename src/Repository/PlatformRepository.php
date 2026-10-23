<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class PlatformRepository
{
    private const COLUMNS = ['slug', 'name', 'share_url_template', 'icon', 'color', 'sort_order', 'is_active'];

    public function __construct(private PDO $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->query('SELECT * FROM platforms ORDER BY sort_order, name')->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function active(): array
    {
        return $this->db->query('SELECT * FROM platforms WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function allWithClickCounts(): array
    {
        return $this->db->query(
            'SELECT p.*, (SELECT COUNT(*) FROM share_clicks c WHERE c.platform_id = p.id) AS clicks
             FROM platforms p
             ORDER BY p.sort_order, p.name'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM platforms WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findActiveBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM platforms WHERE slug = ? AND is_active = 1');
        $stmt->execute([$slug]);

        return $stmt->fetch() ?: null;
    }

    public function slugExists(string $slug): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM platforms WHERE slug = ?');
        $stmt->execute([$slug]);

        return $stmt->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $data = array_intersect_key($data, array_flip(self::COLUMNS));
        $columns = array_keys($data);
        $stmt = $this->db->prepare(sprintf(
            'INSERT INTO platforms (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
        ));
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    /**
     * The slug is immutable: recorded clicks and front-end markup refer to it.
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $data = array_intersect_key($data, array_flip(array_diff(self::COLUMNS, ['slug'])));
        $assignments = array_map(static fn (string $c): string => $c . ' = :' . $c, array_keys($data));
        $stmt = $this->db->prepare('UPDATE platforms SET ' . implode(', ', $assignments) . ' WHERE id = :id');
        $stmt->execute($data + ['id' => $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db->prepare('UPDATE platforms SET is_active = ? WHERE id = ?')->execute([(int) $active, $id]);
    }
}
