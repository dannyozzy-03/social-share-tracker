<?php

declare(strict_types=1);

namespace App\Support;

use App\Exception\ConfigurationException;
use App\Exception\DatabaseException;
use ErrorException;
use PDO;
use PDOException;

final class SqlFile
{
    /**
     * @throws ConfigurationException when the file cannot be read
     * @throws DatabaseException naming the file and statement that failed
     */
    public static function run(PDO $db, string $path): int
    {
        try {
            $sql = file_get_contents($path);
        } catch (ErrorException $e) {
            throw new ConfigurationException(sprintf('Cannot read %s.', basename($path)), 0, $e);
        }
        if ($sql === false) {
            throw new ConfigurationException(sprintf('Cannot read %s.', basename($path)));
        }

        $count = 0;
        foreach (self::statements($sql) as $statement) {
            try {
                $db->exec($statement);
            } catch (PDOException $e) {
                throw DatabaseException::during(sprintf(
                    'run statement %d of %s ("%s")',
                    $count + 1,
                    basename($path),
                    mb_strimwidth((string) preg_replace('/\s+/', ' ', $statement), 0, 60, '…')
                ), $e);
            }
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
