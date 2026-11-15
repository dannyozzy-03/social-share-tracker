<?php

declare(strict_types=1);

namespace App\Repository;

use App\Exception\DatabaseException;
use PDO;
use PDOException;

final class AdminRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array{id: int, username: string, password_hash: string}|null
     * @throws DatabaseException
     */
    public function findByUsername(string $username): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT id, username, password_hash FROM admins WHERE username = ?');
            $stmt->execute([$username]);

            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            throw DatabaseException::during('look up the admin account', $e);
        }
    }

    /** @throws DatabaseException */
    public function upsert(string $username, #[\SensitiveParameter] string $password): void
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $this->db->prepare(
                'INSERT INTO admins (username, password_hash) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
            )->execute([$username, $hash]);
        } catch (PDOException $e) {
            throw DatabaseException::during('save the admin account', $e);
        }
    }

    /** @throws DatabaseException */
    public function updatePasswordHash(int $id, string $hash): void
    {
        try {
            $this->db->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
        } catch (PDOException $e) {
            throw DatabaseException::during('update the admin password', $e);
        }
    }

    /** @throws DatabaseException */
    public function touchLastLogin(int $id): void
    {
        try {
            $this->db->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')->execute([$id]);
        } catch (PDOException $e) {
            throw DatabaseException::during('record the sign-in time', $e);
        }
    }

    /** @throws DatabaseException */
    public function count(): int
    {
        try {
            return (int) $this->db->query('SELECT COUNT(*) FROM admins')->fetchColumn();
        } catch (PDOException $e) {
            throw DatabaseException::during('count the admin accounts', $e);
        }
    }
}
