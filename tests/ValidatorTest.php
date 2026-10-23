<?php

declare(strict_types=1);

use App\Validation\Validator;

return [
    'accepts valid slugs' => function (): void {
        foreach (['x', 'whatsapp', 'hacker-news', 'line_me', 'web3'] as $slug) {
            check(Validator::slug($slug), "Expected '$slug' to be valid");
        }
    },
    'rejects invalid slugs' => function (): void {
        foreach (['', 'WhatsApp', 'two words', '-lead', 'trail-', 'a/b', str_repeat('a', 33)] as $slug) {
            check(!Validator::slug($slug), "Expected '$slug' to be invalid");
        }
    },
    'validates hex colours' => function (): void {
        check(Validator::hexColor('#1877F2'));
        check(Validator::hexColor('#abcdef'));
        check(!Validator::hexColor('1877F2'));
        check(!Validator::hexColor('#FFF'));
        check(!Validator::hexColor('red'));
    },
    'validates Bootstrap icon classes' => function (): void {
        check(Validator::iconClass('bi-twitter-x'));
        check(!Validator::iconClass('fa-twitter'));
        check(!Validator::iconClass('bi-x" onmouseover="alert(1)'));
    },
    'parses strict dates only' => function (): void {
        check_same('2026-02-28', Validator::date('2026-02-28')?->format('Y-m-d'));
        check(Validator::date('2026-02-30') === null, 'Feb 30 must be rejected');
        check(Validator::date('22/10/2026') === null);
        check(Validator::date('2026-1-5') === null);
    },
    'accepts the default platform templates' => function (): void {
        $templates = [
            'https://www.facebook.com/sharer/sharer.php?u={url}',
            'https://x.com/intent/tweet?url={url}&text={title}',
            'https://api.whatsapp.com/send?text={title}%20{url}',
            'https://t.me/share/url?url={url}&text={title}',
            'mailto:?subject={title}&body={description}%0A%0A{url}',
        ];
        foreach ($templates as $template) {
            check_same(null, Validator::shareTemplateError($template), $template);
        }
    },
    'rejects unsafe or incomplete templates' => function (): void {
        $cases = [
            'https://example.com/share' => '{url}',
            'http://example.com/?u={url}' => 'https://',
            'javascript:alert({url})' => 'https://',
            'https://example.com/?u={url}&x={secret}' => 'Unknown placeholder {secret}',
            'https://example.com/?u={url}" onclick="x' => 'spaces, quotes',
        ];
        foreach ($cases as $template => $expected) {
            $error = Validator::shareTemplateError($template);
            check($error !== null && str_contains($error, $expected), "'$template' gave: " . var_export($error, true));
        }
    },
    'cleans free text' => function (): void {
        check_same('Hello world', Validator::cleanText("  <b>Hello</b>\n\t world  ", 50));
        check_same('Hell', Validator::cleanText('Hello', 4));
        check_same(null, Validator::cleanText('   ', 10));
        check_same(null, Validator::cleanText(['array'], 10));
        check_same(null, Validator::cleanText("\xB1\x31", 10), 'invalid UTF-8 is rejected');
        check_same('Café ☕', Validator::cleanText('Café ☕', 10));
    },
];
