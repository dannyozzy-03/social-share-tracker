<?php

declare(strict_types=1);

namespace App;

use App\Exception\DatabaseException;
use DateTimeImmutable;
use PDO;
use PDOException;

/**
 * The shared PDO connection to MySQL.
 *
 * Error handling: a failed connection becomes DatabaseException::connection(), which keeps the
 * driver's reason but not its stack trace (that can hold the password); a failed session setting
 * becomes DatabaseException::during().
 */
final class Database
{
    private static ?PDO $connection = null;

    /** @throws DatabaseException */
    public static function connection(): PDO
    {
        return self::$connection ??= self::connect();
    }

    /**
     * $withDatabase = false connects to the server only (installer, before the DB exists).
     * @throws DatabaseException
     */
    public static function connect(bool $withDatabase = true): PDO
    {
        $db = (array) config('db');
        $host = (string) ($db['host'] ?? '127.0.0.1');
        $port = (int) ($db['port'] ?? 3306);
        $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $db['charset'] ?? 'utf8mb4');
        if ($withDatabase) {
            $dsn .= ';dbname=' . ($db['name'] ?? '');
        }

        try {
            $pdo = new PDO($dsn, (string) ($db['user'] ?? ''), (string) ($db['pass'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw DatabaseException::connection($host, $port, $e);
        }

        try {
            // Keep NOW()/CURRENT_TIMESTAMP in the same timezone as PHP.
            $pdo->exec('SET time_zone = ' . $pdo->quote((new DateTimeImmutable())->format('P')));
        } catch (PDOException $e) {
            throw DatabaseException::during('set the connection time zone', $e);
        }

        return $pdo;
    }
}
