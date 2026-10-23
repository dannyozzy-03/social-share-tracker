<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Admin\PlatformForm;
use App\Database;
use App\Repository\PlatformRepository;
use App\Security\Auth;
use App\Security\Csrf;
use App\Validation\ValidationException;
use App\View;

$user = Auth::requireLogin();
send_security_headers();
header('Cache-Control: no-store');

$repository = new PlatformRepository(Database::connection());

$rawId = $_GET['id'] ?? '';
$platform = null;
if ($rawId !== '') {
    $platform = is_string($rawId) && ctype_digit($rawId) ? $repository->find((int) $rawId) : null;
    if ($platform === null) {
        flash('danger', 'That platform does not exist.');
        redirect('admin/platforms.php');
    }
}
$creating = $platform === null;

$values = $platform ?? [
    'slug' => '',
    'name' => '',
    'share_url_template' => 'https://',
    'icon' => 'bi-share-fill',
    'color' => '#6C757D',
    'sort_order' => 100,
    'is_active' => 1,
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = array_merge($values, array_map(
        static fn ($v) => is_string($v) ? trim($v) : '',
        array_intersect_key($_POST, $values)
    ));
    $values['is_active'] = isset($_POST['is_active']) ? 1 : 0;

    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        $errors['form'] = 'Your form expired. Please submit it again.';
    } else {
        try {
            $data = (new PlatformForm($repository))->validate($_POST, $creating);
            if ($creating) {
                $repository->create($data);
                flash('success', $data['name'] . ' was added. Its button now appears on every page.');
            } else {
                $repository->update((int) $platform['id'], $data);
                flash('success', $data['name'] . ' was updated.');
            }
            redirect('admin/platforms.php');
        } catch (ValidationException $e) {
            $errors = $e->errors();
        }
    }
    http_response_code(422);
}

View::render('admin/platform-form', [
    'title' => $creating ? 'Add platform' : 'Edit ' . $platform['name'],
    'active' => 'platforms',
    'user' => $user,
    'creating' => $creating,
    'platform' => $platform,
    'values' => $values,
    'errors' => $errors,
], 'admin/layout');
