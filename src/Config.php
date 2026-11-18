<?php

declare(strict_types=1);

namespace App;

use App\Exception\ConfigurationException;
use Throwable;

/**
 * Settings: config/config.example.php, with config/config.php merged over it.
 *
 * Error handling: a config file that cannot be read, does not parse or does not return an array
 * becomes ConfigurationException naming the file, with the original error as the previous exception.
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    /** @param array<string, mixed> $items */
    public static function load(array $items): void
    {
        self::$items = $items;
    }

    /**
     * Loads the defaults, then merges the optional local file over them.
     * @throws ConfigurationException when a file cannot be read or does not return an array
     */
    public static function loadFiles(string $defaultsFile, string $localFile): void
    {
        $items = self::read($defaultsFile);
        if (is_file($localFile)) {
            $items = array_replace_recursive($items, self::read($localFile));
        }
        self::load($items);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private static function read(string $file): array
    {
        $name = basename(dirname($file)) . '/' . basename($file);
        try {
            $items = require $file;
        } catch (Throwable $e) {
            // ParseError, or ErrorException from the bootstrap error handler (e.g. unreadable file).
            throw new ConfigurationException(sprintf('Could not load %s: %s', $name, $e->getMessage()), 0, $e);
        }
        if (!is_array($items)) {
            throw new ConfigurationException(sprintf('%s must return an array of settings.', $name));
        }

        return $items;
    }
}
