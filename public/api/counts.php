<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Database;
use App\Repository\ShareClickRepository;
use App\Support\Url;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$requested = $_GET['url'] ?? [];
$requested = is_array($requested) ? $requested : [$requested];

$urls = [];
foreach (array_slice($requested, 0, 20) as $url) {
    $normalized = is_string($url) ? Url::normalize($url) : null;
    if ($normalized !== null && in_array(strtolower((string) parse_url($normalized, PHP_URL_HOST)), allowed_hosts(), true)) {
        $urls[] = $url;
    }
}

if ($urls === []) {
    json_response(['ok' => false, 'error' => 'Pass one or more tracked page URLs as url[].'], 422);
}

json_response(['ok' => true, 'counts' => (new ShareClickRepository(Database::connection()))->countsForUrls(array_values(array_unique($urls)))]);
