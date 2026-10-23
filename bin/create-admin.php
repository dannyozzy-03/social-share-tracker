<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Database;
use App\Repository\AdminRepository;
use App\Validation\Validator;

[, $username, $password] = $argv + [null, '', ''];
$username = trim((string) $username);

if ($username === '' || $password === '') {
    fwrite(STDERR, "Usage: php bin/create-admin.php <username> <password>\n");
    exit(1);
}
if (Validator::cleanText($username, 64) !== $username || mb_strlen($username) > 64) {
    fwrite(STDERR, "Username must be 1-64 printable characters.\n");
    exit(1);
}
if (strlen($password) < 8 || strlen($password) > 72) {
    fwrite(STDERR, "Password must be 8-72 characters.\n");
    exit(1);
}

(new AdminRepository(Database::connection()))->upsert($username, $password);
echo "[ok] Admin '$username' saved.\n";
