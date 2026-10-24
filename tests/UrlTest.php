<?php

declare(strict_types=1);

use App\Support\Url;

return [
    'keeps a clean URL unchanged' => function (): void {
        check_same('https://example.com/news/story', Url::normalize('https://example.com/news/story'));
    },
    'adds a root path' => function (): void {
        check_same('https://example.com/', Url::normalize('https://example.com'));
    },
    'lowercases scheme and host but not the path' => function (): void {
        check_same('https://example.com/News/Story', Url::normalize('HTTPS://Example.COM/News/Story'));
    },
    'drops fragments, credentials and default ports' => function (): void {
        check_same('https://example.com/a', Url::normalize('https://user:pass@example.com:443/a#comments'));
        check_same('http://example.com/a', Url::normalize('http://example.com:80/a'));
    },
    'keeps non-default ports' => function (): void {
        check_same('http://localhost:8000/article.php?slug=x', Url::normalize('http://localhost:8000/article.php?slug=x'));
    },
    'ignores ports and loopback aliases for local share counts' => function (): void {
        check_same(
            'http://localhost/article.php?slug=x',
            Url::normalizeForCounts('http://localhost:8111/article.php?slug=x')
        );
        check_same(
            Url::normalizeForCounts('http://localhost:8000/article.php?slug=x'),
            Url::normalizeForCounts('http://127.0.0.1:8111/article.php?slug=x')
        );
    },
    'preserves production hosts and ports for share counts' => function (): void {
        check_same(
            'https://example.com:8443/article.php?slug=x',
            Url::normalizeForCounts('https://example.com:8443/article.php?slug=x')
        );
    },
    'removes tracking parameters and sorts the rest' => function (): void {
        check_same(
            'https://example.com/a?id=7&page=2',
            Url::normalize('https://example.com/a?utm_source=fb&page=2&fbclid=abc&UTM_Medium=x&id=7')
        );
    },
    'removes the query string when only tracking parameters remain' => function (): void {
        check_same('https://example.com/a', Url::normalize('https://example.com/a?utm_campaign=launch&gclid=1'));
    },
    'rejects non-http schemes and junk' => function (): void {
        foreach (['javascript:alert(1)', 'ftp://example.com/', 'mailto:a@b.c', 'not a url', '', '//example.com/a'] as $value) {
            check(Url::normalize($value) === null, "Expected null for '$value'");
        }
    },
    'rejects URLs over 2048 characters' => function (): void {
        check(Url::normalize('https://example.com/?q=' . str_repeat('a', 2048)) === null);
    },
];
