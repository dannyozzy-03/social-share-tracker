<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use RuntimeException;

final class SqlFile
{
    public static function run(PDO $db, string $path): int
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException("Cannot read $path");
        }

        $count = 0;
        foreach (self::statements($sql) as $statement) {
            $db->exec($statement);
            $count++;
        }

        return $count;
    }

    /**
     * Naive split on ";": string literals in these scripts must not contain one.
     * @return list<string>
     */
    public static function statements(string $sql): array
    {
        $lines = array_filter(
            preg_split('/\R/', $sql) ?: [],
            static fn (string $line): bool => !str_starts_with(ltrim($line), '--')
        );

        return array_values(array_filter(
            array_map('trim', explode(';', implode("\n", $lines))),
            static fn (string $statement): bool => $statement !== ''
        ));
    }
}
