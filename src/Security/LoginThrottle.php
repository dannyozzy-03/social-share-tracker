<?php

declare(strict_types=1);

namespace App\Security;

use App\Exception\DatabaseException;
use DateTimeImmutable;
use PDO;
use PDOException;

final class LoginThrottle
{
    /** Attempts older than this are deleted; they no longer affect any lockout. */
    private const RETENTION_DAYS = 7;

    public function __construct(
        private PDO $db,
        private int $maxAttempts = 5,
        private int $windowMinutes = 15,
    ) {
    }

    /** @throws DatabaseException */
    public function isLocked(string $ipHash): bool
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND success = 0 AND attempted_at >= ?'
            );
            $stmt->execute([$ipHash, $this->windowStart()]);

            return (int) $stmt->fetchColumn() >= $this->maxAttempts;
        } catch (PDOException $e) {
            throw DatabaseException::during('check the sign-in lockout', $e);
        }
    }

    /** @throws DatabaseException */
    public function record(string $ipHash, string $username, bool $success): void
    {
        try {
            $this->db->prepare(
                'INSERT INTO login_attempts (ip_hash, username, success, attempted_at) VALUES (?, ?, ?, NOW())'
            )->execute([$ipHash, mb_substr($username, 0, 64), (int) $success]);

            if ($success) {
                $this->db->prepare('DELETE FROM login_attempts WHERE ip_hash = ? AND success = 0')->execute([$ipHash]);
            }

            // Keep the table small: without this it grows by one row per sign-in attempt forever.
            $this->db->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([
                (new DateTimeImmutable(sprintf('-%d days', max(self::RETENTION_DAYS, (int) ceil($this->windowMinutes / 1440)))))
                    ->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $e) {
            throw DatabaseException::during('record the sign-in attempt', $e);
        }
    }

    public function windowMinutes(): int
    {
        return $this->windowMinutes;
    }

    private function windowStart(): string
    {
        return (new DateTimeImmutable(sprintf('-%d minutes', $this->windowMinutes)))->format('Y-m-d H:i:s');
    }
}
