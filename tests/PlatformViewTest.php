<?php

declare(strict_types=1);

use App\View;

return [
    'platform rows expose labels for responsive card layouts' => function (): void {
        $html = View::fetch('admin/platforms', [
            'platforms' => [[
                'id' => 1,
                'name' => 'Facebook',
                'slug' => 'facebook',
                'icon' => 'bi-facebook',
                'color' => '#1877F2',
                'share_url_template' => 'https://www.facebook.com/sharer/sharer.php?u={url}',
                'sort_order' => 1,
                'clicks' => 1250,
                'is_active' => 1,
            ]],
        ]);

        foreach (['Button', 'Slug', 'Share URL template', 'Order', 'Clicks', 'Status', 'Actions'] as $label) {
            check(str_contains($html, 'data-label="' . $label . '"'), 'Platform rows must include the ' . $label . ' responsive label.');
        }
        check(str_contains($html, 'https://www.facebook.com/sharer/sharer.php?u={url}'), 'The full share URL template must remain available.');
    },
];
