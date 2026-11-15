<?php

declare(strict_types=1);

namespace App\Share;

/**
 * Fills a platform's share URL template with a page's URL, title and description.
 *
 * Error handling: pure string work, so there is nothing to catch.
 */
final class ShareLinkBuilder
{
    /** @param array{url: string, title?: string|null, description?: string|null} $page */
    public static function build(string $template, array $page): string
    {
        return strtr($template, [
            '{url}' => rawurlencode($page['url']),
            '{title}' => rawurlencode((string) ($page['title'] ?? '')),
            '{description}' => rawurlencode((string) ($page['description'] ?? '')),
        ]);
    }

    public static function opensInNewWindow(string $template): bool
    {
        return stripos($template, 'mailto:') !== 0;
    }
}
