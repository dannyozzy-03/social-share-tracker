<?php

declare(strict_types=1);

namespace App\Admin;

use App\Repository\PageRepository;
use App\Validation\ValidationException;
use App\Validation\Validator;

/**
 * The validated query of the dashboard's page picker (GET admin/api/pages.php).
 *
 * Error handling: pure validation with no I/O, so there is nothing to catch. Bad input throws
 * ValidationException listing every invalid field; admin/api/pages.php answers it with 422.
 */
final class PageSearch
{
    public const MAX_QUERY_LENGTH = 100;
    public const DEFAULT_LIMIT = 20;

    private function __construct(
        public readonly string $query,
        public readonly int $limit,
        public readonly int $offset,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @throws ValidationException
     */
    public static function fromQuery(array $input): self
    {
        $errors = [];

        $raw = $input['q'] ?? '';
        $query = '';
        if (!is_string($raw) || !mb_check_encoding($raw, 'UTF-8')) {
            $errors['q'] = 'Enter some text to search for.';
        } elseif (mb_strlen($raw) > self::MAX_QUERY_LENGTH) {
            $errors['q'] = sprintf('Search for %d characters or fewer.', self::MAX_QUERY_LENGTH);
        } else {
            $query = Validator::cleanText($raw, self::MAX_QUERY_LENGTH) ?? '';
        }

        $limit = self::integer($input, 'limit', self::DEFAULT_LIMIT, 1, PageRepository::SEARCH_MAX_LIMIT, $errors);
        $offset = self::integer($input, 'offset', 0, 0, PageRepository::SEARCH_MAX_OFFSET, $errors);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new self($query, $limit, $offset);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     */
    private static function integer(array $input, string $key, int $default, int $min, int $max, array &$errors): int
    {
        $value = $input[$key] ?? '';
        if ($value === '') {
            return $default;
        }
        if (!is_string($value) || !ctype_digit($value) || strlen($value) > 6 || (int) $value < $min || (int) $value > $max) {
            $errors[$key] = sprintf('%s must be a whole number from %d to %d.', ucfirst($key), $min, $max);

            return $default;
        }

        return (int) $value;
    }
}
