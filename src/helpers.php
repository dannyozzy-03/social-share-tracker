<?php

declare(strict_types=1);

use App\Config;

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/** "" under `php -S -t public`, or e.g. "/social-share-tracker/public" under XAMPP htdocs. */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $normalise = static fn (string $path): string => str_replace('\\', '/', $path);
    $public = $normalise((string) realpath(APP_ROOT . '/public'));
    $script = $normalise((string) realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $scriptName = $normalise($_SERVER['SCRIPT_NAME'] ?? '');

    $base = '';
    if ($public !== '' && $script !== '' && stripos($script, $public) === 0) {
        $relative = substr($script, strlen($public));
        if ($relative !== '' && str_ends_with($scriptName, $relative)) {
            $base = substr($scriptName, 0, -strlen($relative));
        }
    }

    return $base = rtrim($base, '/');
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = APP_ROOT . '/public/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';

    return url('assets/' . ltrim($path, '/')) . '?v=' . $version;
}

function app_url(string $path = ''): string
{
    $root = rtrim((string) config('app.url', ''), '/');
    if ($root === '') {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
            $host = 'localhost';
        }
        $root = (is_https() ? 'https' : 'http') . '://' . $host . base_path();
    }

    return $root . '/' . ltrim($path, '/');
}

/** @return list<string> */
function allowed_hosts(): array
{
    $hosts = array_map('strtolower', (array) config('tracking.allowed_hosts', []));
    $hosts[] = strtolower((string) parse_url(app_url(), PHP_URL_HOST));

    return array_values(array_unique(array_filter($hosts)));
}

/** The site name as wordmark spans: the last word is set in brand blue ("Ajaklah" + "News"). */
function wordmark(): string
{
    $words = preg_split('/\s+/', trim((string) config('app.site_name'))) ?: [''];
    $desk = count($words) > 1 ? (string) array_pop($words) : '';

    return '<span class="wordmark__name">' . e(implode(' ', $words)) . '</span>'
        . ($desk === '' ? '' : '<span class="wordmark__desk">' . e($desk) . '</span>');
}

/** Near-black brand colours (X is #000000) are lightened so they stay visible on dark backgrounds. */
function on_stage(string $hex): string
{
    if (!preg_match('/^#([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/', $hex, $m)) {
        return '#8A8FB0';
    }
    $linear = static function (string $channel): float {
        $c = hexdec($channel) / 255;

        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    };
    $luminance = 0.2126 * $linear($m[1]) + 0.7152 * $linear($m[2]) + 0.0722 * $linear($m[3]);

    return $luminance < 0.03 ? '#E9E7F5' : strtoupper($hex);
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function ip_hash(?string $ip = null): string
{
    return hash_hmac('sha256', $ip ?? client_ip(), (string) config('app.secret'));
}

function wants_json(): bool
{
    return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/api/');
}

/** @param array<string, mixed> $data */
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'inline-speculation-rules' https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
        . "font-src 'self' https://cdn.jsdelivr.net; "
        . "img-src 'self' data:; "
        . "connect-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'self'"
    );
}

/** Requests with neither Origin nor Referer are allowed so older browsers keep working. */
function request_from_allowed_origin(): bool
{
    $source = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? null;
    if ($source === null || $source === '') {
        return true;
    }
    $host = strtolower((string) parse_url((string) $source, PHP_URL_HOST));
    $requestHost = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));

    return $host !== '' && ($host === $requestHost || in_array($host, allowed_hosts(), true));
}

/** @return array<string, mixed>|null null when the body is too large or malformed */
function read_request_payload(int $maxBytes = 8192): ?array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (!str_contains($contentType, 'application/json')) {
        return $_POST;
    }
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if ($raw === false || strlen($raw) > $maxBytes) {
        return null;
    }
    $data = json_decode($raw, true);

    return is_array($data) ? $data : null;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type: string, message: string}> */
function take_flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);

    return $messages;
}
