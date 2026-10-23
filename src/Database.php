<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        return self::$connection ??= self::connect();
    }

    /** $withDatabase = false connects to the server only (installer, before the DB exists). */
    public static function connect(bool $withDatabase = true): PDO
    {
        $db = (array) config('db');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            $db['host'] ?? '127.0.0.1',
            (int) ($db['port'] ?? 3306),
            $db['charset'] ?? 'utf8mb4'
        );
        if ($withDatabase) {
            $dsn .= ';dbname=' . $db['name'];
        }

        $pdo = new PDO($dsn, (string) ($db['user'] ?? ''), (string) ($db['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Keep NOW()/CURRENT_TIMESTAMP in the same timezone as PHP.
        $pdo->exec('SET time_zone = ' . $pdo->quote((new DateTimeImmutable())->format('P')));

        return $pdo;
    }
}
