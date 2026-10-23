<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Security\Auth;
use App\Security\Csrf;

Auth::startSession();

// State-changing, so POST + CSRF rather than a link.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['_csrf'] ?? null)) {
    redirect('admin/');
}

Auth::logout();
redirect('admin/login.php?signed_out=1');
