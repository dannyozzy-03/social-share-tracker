<?php

declare(strict_types=1);

// Searches shared pages for the dashboard's page picker, one page of results at a time.
// GET ?q=penang&limit=20&offset=0 -> {"ok": true, "pages": [...], "has_more": bool, "next_offset": ?int}

require dirname(__DIR__, 3) . '/src/bootstrap.php';

use App\Admin\PageSearch;
use App\Database;
use App\Repository\PageRepository;
use App\Security\Auth;
use App\Validation\ValidationException;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

Auth::requireLogin(json: true);
session_write_close(); // release the session lock so typing doesn't queue behind other requests

try {
    $search = PageSearch::fromQuery($_GET);
} catch (ValidationException $e) {
    json_response(['ok' => false, 'errors' => $e->errors()], 422);
}

$result = (new PageRepository(Database::connection()))->search($search->query, $search->limit, $search->offset);

json_response([
    'ok' => true,
    'query' => $search->query,
    'pages' => $result['pages'],
    'has_more' => $result['has_more'],
    'next_offset' => $result['has_more'] ? $search->offset + count($result['pages']) : null,
]);
