<?php

declare(strict_types=1);

namespace App\Validation;

use DateTimeImmutable;

final class Validator
{
    public const SHARE_PLACEHOLDERS = ['url', 'title', 'description'];

    public static function slug(string $value): bool
    {
        return preg_match('/^[a-z0-9](?:[a-z0-9_-]{0,30}[a-z0-9])?$/', $value) === 1;
    }

    public static function hexColor(string $value): bool
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1;
    }

    public static function iconClass(string $value): bool
    {
        return strlen($value) <= 64 && preg_match('/^bi-[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1;
    }

    public static function date(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    /** Returns an error message, or null when the template is valid. */
    public static function shareTemplateError(string $template): ?string
    {
        if ($template === '') {
            return 'Share URL template is required.';
        }
        if (strlen($template) > 512) {
            return 'Share URL template must be 512 characters or fewer.';
        }
        if (preg_match('/[\s"\'<>`]/', $template) === 1) {
            return 'Share URL template must not contain spaces, quotes or angle brackets. URL-encode them (e.g. %20).';
        }
        if (!str_contains($template, '{url}')) {
            return 'Share URL template must contain the {url} placeholder.';
        }

        preg_match_all('/\{([^}]*)\}/', $template, $matches);
        $unknown = array_diff($matches[1], self::SHARE_PLACEHOLDERS);
        if ($unknown !== []) {
            return 'Unknown placeholder {' . reset($unknown) . '}. Use {url}, {title} or {description}.';
        }

        $sample = str_replace(['{url}', '{title}', '{description}'], 'x', $template);
        if (stripos($sample, 'mailto:') === 0) {
            return null;
        }
        if (stripos($sample, 'https://') !== 0 || filter_var($sample, FILTER_VALIDATE_URL) === false) {
            return 'Share URL template must be a valid https:// or mailto: URL.';
        }

        return null;
    }

    public static function cleanText(mixed $value, int $maxLength): ?string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return null;
        }
        $value = strip_tags($value);
        $value = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }
}
