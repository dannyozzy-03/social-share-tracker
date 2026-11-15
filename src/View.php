<?php

declare(strict_types=1);

namespace App;

use App\Exception\ConfigurationException;
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

    /**
     * @param array<string, mixed> $data
     * @throws ConfigurationException when the template does not exist
     */
    public static function fetch(string $template, array $data = []): string
    {
        // Template names come from code, never from requests; the check keeps it that way.
        if (preg_match('#^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$#', $template) !== 1) {
            throw new ConfigurationException(sprintf('Invalid template name "%s".', $template));
        }
        $__file = APP_ROOT . '/templates/' . $template . '.php';
        if (!is_file($__file)) {
            throw new ConfigurationException(sprintf('Template "%s" does not exist.', $template));
        }

        extract($data, EXTR_SKIP);
        $__level = ob_get_level();
        ob_start();
        try {
            require $__file;
        } catch (Throwable $e) {
            // Discard partial output, including buffers a failing nested template left open.
            while (ob_get_level() > $__level) {
                ob_end_clean();
            }
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
