<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Database;
use App\Repository\PlatformRepository;
use App\Security\Auth;
use App\Stats\ShareStats;
use App\Stats\StatsFilter;
use App\Validation\ValidationException;

Auth::requireLogin();
session_write_close();

$db = Database::connection();
$platformIds = array_map(static fn (array $p): int => (int) $p['id'], (new PlatformRepository($db))->all());

try {
    $filter = StatsFilter::fromQuery($_GET, $platformIds);
} catch (ValidationException $e) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo implode("\n", $e->errors());
    exit;
}

// Stream rows instead of buffering the whole result in memory.
$db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

$filename = sprintf('share-clicks_%s_to_%s.csv', $filter->from->format('Y-m-d'), $filter->to->format('Y-m-d'));
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// Neutralise values spreadsheets would run as formulas (CSV injection).
$safe = static fn (?string $value): string => preg_match('/^[=+\-@\t\r]/', (string) $value) ? "'" . $value : (string) $value;

$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel detects the encoding
fputcsv($out, ['clicked_at', 'platform', 'page_url', 'page_title'], ',', '"', '');
foreach ((new ShareStats($db))->exportRows($filter) as $row) {
    fputcsv($out, [$row['created_at'], $safe($row['platform']), $safe($row['url']), $safe($row['title'])], ',', '"', '');
}
fclose($out);
