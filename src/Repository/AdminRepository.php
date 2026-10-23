<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class AdminRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array{id: int, username: string, password_hash: string}|null */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT id, username, password_hash FROM admins WHERE username = ?');
        $stmt->execute([$username]);

        return $stmt->fetch() ?: null;
    }

    public function upsert(string $username, string $password): void
    {
        $this->db->prepare(
            'INSERT INTO admins (username, password_hash) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
        )->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $this->db->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
    }

    public function touchLastLogin(int $id): void
    {
        $this->db->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')->execute([$id]);
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    }
}
