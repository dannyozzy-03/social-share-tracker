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

$config = require APP_ROOT . '/config/config.example.php';
if (is_file(APP_ROOT . '/config/config.php')) {
    $config = array_replace_recursive($config, require APP_ROOT . '/config/config.php');
}
App\Config::load($config);
unset($config);

date_default_timezone_set((string) config('app.timezone', 'UTC'));
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', config('app.debug') ? '1' : '0');
ini_set('log_errors', '1');

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
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . (config('app.debug') ? (string) $e : $e->getMessage()) . PHP_EOL);
        exit(1);
    }

    error_log((string) $e);
    $message = config('app.debug') ? $e->getMessage() : 'Something went wrong. Please try again later.';
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (wants_json()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false, 'error' => $message]);
        return;
    }
    echo '<!doctype html><meta charset="utf-8"><title>Server error</title>'
        . '<div style="font-family:system-ui,sans-serif;max-width:40rem;margin:4rem auto;padding:0 1rem">'
        . '<h1>Server error</h1><p>' . e($message) . '</p></div>';
});
