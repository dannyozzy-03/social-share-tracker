<?php
/**
 * @var string|null $error
 * @var list<string> $invalid Fields to mark as invalid.
 * @var string|null $notice
 * @var string $username
 */

use App\Security\Csrf;

$invalid ??= [];
$isInvalid = static fn (string $field): bool => in_array($field, $invalid, true);
$focus = $username !== '' ? 'password' : 'username';
?>
<!doctype html>
<html lang="en" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#FFFFFF">
    <script src="<?= e(asset('js/theme.js')) ?>"></script>
    <title>Sign in · <?= e(config('app.site_name')) ?> share analytics</title>
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(asset('css/theme.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <script type="speculationrules">
    {"prerender": [{"where": {"and": [{"href_matches": "/*"}, {"not": {"selector_matches": "[target=_blank], [data-platform], [download], #export-link, [href*='logout'], [href*='export']"}}]}, "eagerness": "moderate"}]}
    </script>
</head>
<body class="admin admin-login">
<div class="login">
<aside class="login-stage">
    <a class="login-stage__brand wordmark" href="<?= e(url()) ?>"><?= wordmark() ?></a>
    <div class="login-stage__copy">
        <p class="login-stage__kicker">Share analytics</p>
        <p class="login-stage__line">What got shared, where, and when.</p>
    </div>
    <p class="login-stage__credit">Kuala Lumpur at dusk. Photo: James Kerwin, CC BY 2.0</p>
</aside>
<main class="login-pane">
<button class="theme-toggle login-theme" type="button" data-theme-toggle aria-pressed="false" aria-label="Dark mode" title="Switch to dark mode">
    <i class="bi bi-sun theme-toggle__light" aria-hidden="true"></i><i class="bi bi-moon-stars theme-toggle__dark" aria-hidden="true"></i>
</button>
<div class="login-card">
    <h1 class="login-card__title">Sign in</h1>
    <p class="login-card__lede">See which stories readers share, and where.</p>

    <?php if ($notice !== null): ?>
        <div class="alert alert-info login-alert" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i> <?= e($notice) ?></div>
    <?php endif; ?>
    <?php if ($error !== null): ?>
        <div class="alert alert-danger login-alert" role="alert" id="login-error"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('admin/login.php')) ?>" id="login-form" novalidate>
        <?= Csrf::field() ?>
        <div class="mb-3">
            <label class="form-label" for="username">Username</label>
            <input class="form-control form-control-lg<?= $isInvalid('username') ? ' is-invalid' : '' ?>" id="username" name="username" type="text"
                   value="<?= e($username) ?>" autocomplete="username" autocapitalize="none" spellcheck="false"
                   maxlength="64" required<?= $focus === 'username' ? ' autofocus' : '' ?>
                   <?= $isInvalid('username') ? 'aria-invalid="true" aria-describedby="login-error"' : '' ?>>
        </div>
        <div class="mb-4">
            <label class="form-label" for="password">Password</label>
            <div class="input-group input-group-lg">
                <input class="form-control<?= $isInvalid('password') ? ' is-invalid' : '' ?>" id="password" name="password" type="password"
                       autocomplete="current-password" maxlength="72" required<?= $focus === 'password' ? ' autofocus' : '' ?>
                       aria-describedby="caps-warning<?= $isInvalid('password') ? ' login-error' : '' ?>"<?= $isInvalid('password') ? ' aria-invalid="true"' : '' ?>>
                <button class="btn btn-booth login-reveal" type="button" id="toggle-password" aria-controls="password" aria-pressed="false" hidden>
                    <i class="bi bi-eye" aria-hidden="true"></i><span class="login-reveal__text">Show</span>
                </button>
            </div>
            <p class="login-caps" id="caps-warning" aria-live="polite" hidden><i class="bi bi-capslock-fill" aria-hidden="true"></i> Caps Lock is on</p>
        </div>
        <button class="btn btn-cue-primary btn-lg w-100" type="submit" id="login-submit">
            <span class="login-submit__label">Sign in</span>
            <span class="login-submit__busy" hidden><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Signing in…</span>
        </button>
    </form>

    <p class="login-hint"><i class="bi bi-terminal" aria-hidden="true"></i><span>The admin account is created when you run <code>php bin/install.php</code>. See the README for the default login.</span></p>
</div>
<p class="login-pane__foot"><a class="login-card__back" href="<?= e(url()) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to the website</a></p>
</main>
</div>
<script src="<?= e(asset('js/login.js')) ?>" defer></script>
</body>
</html>
