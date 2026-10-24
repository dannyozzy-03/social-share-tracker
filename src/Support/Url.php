<?php

declare(strict_types=1);

namespace App\Support;

final class Url
{
    public const MAX_LENGTH = 2048;

    private const TRACKING_PARAMS = ['fbclid', 'gclid', 'dclid', 'msclkid', 'igshid', 'mc_cid', 'mc_eid', 'ref_src'];

    /** Canonicalise so the same page is counted once; null if not a valid http(s) URL. */
    public static function normalize(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > self::MAX_LENGTH || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        $port = $parts['port'] ?? null;
        $isDefaultPort = $port === null || ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);

        $query = '';
        if (isset($parts['query'])) {
            parse_str($parts['query'], $params);
            $params = array_filter(
                $params,
                static fn ($key): bool => !self::isTrackingParam((string) $key),
                ARRAY_FILTER_USE_KEY
            );
            ksort($params);
            $query = $params === [] ? '' : '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        return $scheme . '://' . strtolower($parts['host'])
            . ($isDefaultPort ? '' : ':' . $port)
            . (($parts['path'] ?? '') === '' ? '/' : $parts['path'])
            . $query;
    }

    /** Local development ports identify the same page for share-count display. */
    public static function normalizeForCounts(string $url): ?string
    {
        $normalized = self::normalize($url);
        if ($normalized === null) {
            return null;
        }

        $parts = parse_url($normalized);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)) {
            return $normalized;
        }

        return $parts['scheme'] . '://localhost' . ($parts['path'] ?? '/')
            . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    private static function isTrackingParam(string $key): bool
    {
        return str_starts_with(strtolower($key), 'utm_') || in_array(strtolower($key), self::TRACKING_PARAMS, true);
    }
}
