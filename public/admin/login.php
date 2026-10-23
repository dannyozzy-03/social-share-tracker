<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Database;
use App\Repository\AdminRepository;
use App\Security\Auth;
use App\Security\Csrf;
use App\Security\LoginThrottle;
use App\View;

Auth::startSession();
send_security_headers();
header('Cache-Control: no-store');

if (Auth::user() !== null) {
    redirect('admin/');
}

$error = null;
$invalid = [];
$username = '';
$notice = match (true) {
    isset($_GET['expired']) => 'Your session expired. Please sign in again.',
    isset($_GET['signed_out']) => 'You have been signed out.',
    default => null,
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notice = null;
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    $db = Database::connection();
    $throttle = new LoginThrottle(
        $db,
        (int) config('admin.max_login_attempts', 5),
        (int) config('admin.lockout_minutes', 15)
    );
    $ipHash = ip_hash();

    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        $error = 'Your form expired. Please try again.';
    } elseif ($throttle->isLocked($ipHash)) {
        $error = sprintf('Too many failed attempts. Please wait %d minutes and try again.', $throttle->windowMinutes());
    } elseif ($username === '' || $password === '') {
        $invalid = array_keys(array_filter(['username' => $username === '', 'password' => $password === '']));
        $error = $invalid === ['password'] ? 'Enter your password.' : ($invalid === ['username'] ? 'Enter your username.' : 'Enter your username and password.');
    } elseif (mb_strlen($username) > 64 || strlen($password) > 72) {
        $invalid = ['username', 'password'];
        $error = "That username and password don't match. Check Caps Lock and try again.";
    } elseif (Auth::attempt(new AdminRepository($db), $username, $password)) {
        $throttle->record($ipHash, $username, true);
        redirect('admin/');
    } else {
        $throttle->record($ipHash, $username, false);
        $invalid = ['username', 'password'];
        $error = "That username and password don't match. Check Caps Lock and try again.";
    }
}

http_response_code($error === null ? 200 : 422);
View::render('admin/login', ['error' => $error, 'invalid' => $invalid, 'notice' => $notice, 'username' => $username]);
