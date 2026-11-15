<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PDOException;
use PDOStatement;

/**
 * A PDO whose every query fails, as if the database server went away mid-request.
 * Transactions "work" so code paths that begin, roll back and commit can be tested.
 */
final class FailingPdo extends PDO
{
    public bool $rolledBack = false;
    private bool $open = false;

    public function __construct()
    {
        // Deliberately no parent::__construct(): no real connection is made.
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        throw self::goneAway();
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        throw self::goneAway();
    }

    public function exec(string $statement): int|false
    {
        throw self::goneAway();
    }

    public function beginTransaction(): bool
    {
        return $this->open = true;
    }

    public function inTransaction(): bool
    {
        return $this->open;
    }

    public function rollBack(): bool
    {
        $this->open = false;

        return $this->rolledBack = true;
    }

    public function commit(): bool
    {
        $this->open = false;

        return true;
    }

    private static function goneAway(): PDOException
    {
        return new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
    }
}
