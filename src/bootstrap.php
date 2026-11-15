<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Social Share Tracker requires PHP 8.1 or newer. You are running ' . PHP_VERSION . '.');
}

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = APP_ROOT . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_ROOT . '/src/helpers.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Registered before the config is read so a broken config file is reported like any other error.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    // Deprecations are logged rather than thrown, so newer PHP versions can't break the app.
    if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
        error_log("Deprecated: $message in $file:$line");

        return true;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    $debug = (bool) config('app.debug');
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . ($debug ? (string) $e : $e->getMessage()) . PHP_EOL);
        exit(1);
    }

    error_log((string) $e);

    // A database outage is temporary: 503 tells clients and monitors to retry.
    $unavailable = $e instanceof App\Exception\DatabaseException;
    $status = $unavailable ? 503 : 500;
    $message = match (true) {
        $debug => $e->getMessage(),
        $unavailable => 'The service is temporarily unavailable. Please try again in a moment.',
        default => 'Something went wrong. Please try again later.',
    };

    // Drop anything a page had started to print so the error isn't appended to half a page.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($status);
        header('Cache-Control: no-store');
        if ($unavailable) {
            header('Retry-After: 30');
        }
    }
    if (wants_json()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false, 'error' => $message], JSON_INVALID_UTF8_SUBSTITUTE);

        return;
    }
    $title = $unavailable ? 'Temporarily unavailable' : 'Server error';
    echo '<!doctype html><meta charset="utf-8"><title>' . $title . '</title>'
        . '<div style="font-family:system-ui,sans-serif;max-width:40rem;margin:4rem auto;padding:0 1rem">'
        . '<h1>' . $title . '</h1><p>' . e($message) . '</p></div>';
});

App\Config::loadFiles(APP_ROOT . '/config/config.example.php', APP_ROOT . '/config/config.php');

date_default_timezone_set((string) config('app.timezone', 'UTC'));
mb_internal_encoding('UTF-8');
ini_set('display_errors', config('app.debug') ? '1' : '0');
