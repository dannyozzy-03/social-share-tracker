<?php

declare(strict_types=1);

namespace App\Exception;

use PDOException;
use Throwable;

/**
 * A database operation failed. The message names the operation, never the SQL or credentials.
 *
 * Error handling: thrown by every class that runs SQL. Entry points let it reach the handler in
 * src/bootstrap.php, which logs it and answers 503 with Retry-After. Two catch it on purpose:
 * admin/platform-edit.php turns a duplicate slug (sqlState() "23000") into a form error, and
 * admin/export.php ends a CSV that fails mid-download with an "EXPORT INCOMPLETE" line.
 */
final class DatabaseException extends AppException
{
    private string $sqlState = '';

    public static function during(string $operation, Throwable $previous): self
    {
        $exception = new self(sprintf('Database error while trying to %s.', $operation), 0, $previous);
        if ($previous instanceof PDOException) {
            $exception->sqlState = self::stateOf($previous);
        }

        return $exception;
    }

    /**
     * Connection failures keep the driver's reason (e.g. "Access denied", "Connection refused")
     * but deliberately do not chain the PDOException: on PHP < 8.2 its stack trace can contain
     * the password passed to new PDO(), and chained traces end up in the error log.
     */
    public static function connection(string $host, int $port, PDOException $error): self
    {
        $exception = new self(sprintf('Could not connect to the database at %s:%d (%s).', $host, $port, $error->getMessage()));
        $exception->sqlState = self::stateOf($error);

        return $exception;
    }

    /** The SQLSTATE of the underlying driver error, e.g. "23000" for a duplicate key; "" when unknown. */
    public function sqlState(): string
    {
        return $this->sqlState;
    }

    /** Driver errors carry a 5-character SQLSTATE string; a hand-made PDOException has int 0. */
    private static function stateOf(PDOException $e): string
    {
        $code = $e->getCode();

        return is_string($code) && preg_match('/^[0-9A-Z]{5}$/', $code) === 1 ? $code : '';
    }
}
