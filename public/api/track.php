<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Database;
use App\Repository\PageRepository;
use App\Repository\PlatformRepository;
use App\Repository\ShareClickRepository;
use App\Share\RateLimitException;
use App\Share\ShareTracker;
use App\Validation\ValidationException;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

if (!request_from_allowed_origin()) {
    json_response(['ok' => false, 'error' => 'Requests from other sites are not accepted.'], 403);
}

$input = read_request_payload();
if ($input === null) {
    json_response(['ok' => false, 'error' => 'The request body must be a JSON object of 8 KB or less.'], 400);
}

$db = Database::connection();
$tracker = new ShareTracker(
    new PlatformRepository($db),
    new PageRepository($db),
    new ShareClickRepository($db),
    allowed_hosts(),
    (int) config('tracking.dedupe_seconds', 10),
    (int) config('tracking.max_per_minute', 30),
);

try {
    $result = $tracker->track($input, ip_hash(), $_SERVER['HTTP_USER_AGENT'] ?? null);
} catch (ValidationException $e) {
    json_response(['ok' => false, 'errors' => $e->errors()], 422);
} catch (RateLimitException $e) {
    header('Retry-After: 60');
    json_response(['ok' => false, 'error' => $e->getMessage()], 429);
}

json_response(['ok' => true] + $result, $result['recorded'] ? 201 : 200);
