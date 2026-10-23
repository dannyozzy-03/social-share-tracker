<?php

declare(strict_types=1);

namespace App\Security;

use DateTimeImmutable;
use PDO;

final class LoginThrottle
{
    public function __construct(
        private PDO $db,
        private int $maxAttempts = 5,
        private int $windowMinutes = 15,
    ) {
    }

    public function isLocked(string $ipHash): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND success = 0 AND attempted_at >= ?'
        );
        $stmt->execute([$ipHash, $this->windowStart()]);

        return (int) $stmt->fetchColumn() >= $this->maxAttempts;
    }

    public function record(string $ipHash, string $username, bool $success): void
    {
        $this->db->prepare(
            'INSERT INTO login_attempts (ip_hash, username, success, attempted_at) VALUES (?, ?, ?, NOW())'
        )->execute([$ipHash, mb_substr($username, 0, 64), (int) $success]);

        if ($success) {
            $this->db->prepare('DELETE FROM login_attempts WHERE ip_hash = ? AND success = 0')->execute([$ipHash]);
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
