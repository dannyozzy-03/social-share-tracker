<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Database;
use App\Repository\PlatformRepository;
use App\Security\Auth;
use App\Security\Csrf;
use App\View;

$user = Auth::requireLogin();
send_security_headers();
header('Cache-Control: no-store');

$repository = new PlatformRepository(Database::connection());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = is_string($_POST['id'] ?? null) && ctype_digit($_POST['id']) ? (int) $_POST['id'] : 0;
    $platform = $id > 0 ? $repository->find($id) : null;

    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        flash('danger', 'Your form expired. Please try again.');
    } elseif ($platform === null) {
        flash('danger', 'That platform no longer exists.');
    } else {
        $active = ($_POST['active'] ?? '') === '1';
        $repository->setActive($id, $active);
        flash('success', sprintf(
            '%s is now %s.',
            $platform['name'],
            $active ? 'shown on the website' : 'hidden from the website (its history is kept)'
        ));
    }
    redirect('admin/platforms.php');
}

View::render('admin/platforms', [
    'title' => 'Platforms',
    'active' => 'platforms',
    'user' => $user,
    'platforms' => $repository->allWithClickCounts(),
], 'admin/layout');
