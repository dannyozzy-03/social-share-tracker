<?php

declare(strict_types=1);

use App\Share\ShareLinkBuilder;

$page = [
    'url' => 'https://example.com/article.php?slug=a&b=1',
    'title' => 'Nasi lemak & teh tarik: a guide',
    'description' => 'Line one',
];

return [
    'percent-encodes every placeholder' => function () use ($page): void {
        check_same(
            'https://x.com/intent/tweet?url=https%3A%2F%2Fexample.com%2Farticle.php%3Fslug%3Da%26b%3D1&text=Nasi%20lemak%20%26%20teh%20tarik%3A%20a%20guide',
            ShareLinkBuilder::build('https://x.com/intent/tweet?url={url}&text={title}', $page)
        );
    },
    'builds mailto links with subject and body' => function () use ($page): void {
        check_same(
            'mailto:?subject=Nasi%20lemak%20%26%20teh%20tarik%3A%20a%20guide&body=Line%20one%0A%0Ahttps%3A%2F%2Fexample.com%2Farticle.php%3Fslug%3Da%26b%3D1',
            ShareLinkBuilder::build('mailto:?subject={title}&body={description}%0A%0A{url}', $page)
        );
    },
    'treats missing metadata as empty' => function (): void {
        check_same('https://t.me/share/url?url=https%3A%2F%2Fa.b%2F&text=', ShareLinkBuilder::build('https://t.me/share/url?url={url}&text={title}', ['url' => 'https://a.b/']));
    },
    'opens web share pages in a new window but not email' => function (): void {
        check(ShareLinkBuilder::opensInNewWindow('https://www.facebook.com/sharer/sharer.php?u={url}'));
        check(!ShareLinkBuilder::opensInNewWindow('mailto:?body={url}'));
    },
];
