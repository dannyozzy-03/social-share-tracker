<?php

declare(strict_types=1);

namespace App;

use Throwable;

/** Templates must escape all output with e(). */
final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = null): void
    {
        $content = self::fetch($template, $data);
        if ($layout !== null) {
            $content = self::fetch($layout, ['content' => $content] + $data);
        }
        echo $content;
    }

    /** @param array<string, mixed> $data */
    public static function fetch(string $template, array $data = []): string
    {
        $__file = APP_ROOT . '/templates/' . $template . '.php';
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $__file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
