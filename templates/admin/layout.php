<?php
/**
 * @var string $content
 * @var string $title
 * @var string $active Current nav item.
 * @var array{username: string} $user
 * @var list<string> $scripts Extra scripts to load after Bootstrap.
 */

use App\Security\Csrf;

$scripts ??= [];
$nav = [
    'dashboard' => ['admin/', 'bi-bar-chart-line', 'Dashboard'],
    'platforms' => ['admin/platforms.php', 'bi-sliders', 'Platforms'],
];
?>
<!doctype html>
<html lang="en" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#FFFFFF">
    <script src="<?= e(asset('js/theme.js')) ?>"></script>
    <title><?= e($title) ?> · <?= e(config('app.site_name')) ?> share analytics</title>
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preload" href="<?= e(url('assets/fonts/poppins-700.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(asset('css/theme.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <script type="speculationrules">
    {"prerender": [{"where": {"and": [{"href_matches": "/*"}, {"not": {"selector_matches": "[target=_blank], [data-platform], [download], #export-link, [href*='logout'], [href*='export']"}}]}, "eagerness": "moderate"}]}
    </script>
</head>
<body class="admin">
<a class="visually-hidden-focusable skip-link" href="#main">Skip to content</a>
<nav class="navbar navbar-expand-md booth-nav" aria-label="Admin">
    <div class="container-xxl">
        <a class="navbar-brand booth-brand" href="<?= e(url('admin/')) ?>">
            <span class="wordmark"><?= wordmark() ?></span>
            <span class="booth-brand__desk">Analytics</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#admin-nav" aria-controls="admin-nav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="admin-nav">
            <ul class="navbar-nav me-auto">
                <?php foreach ($nav as $key => [$path, $icon, $label]): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $active === $key ? ' active' : '' ?>" href="<?= e(url($path)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
                            <i class="bi <?= e($icon) ?>" aria-hidden="true"></i> <?= e($label) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(url()) ?>" target="_blank" rel="noopener">
                        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> View website
                    </a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <button class="theme-toggle" type="button" data-theme-toggle aria-pressed="false" aria-label="Dark mode" title="Switch to dark mode">
                    <i class="bi bi-sun theme-toggle__light" aria-hidden="true"></i><i class="bi bi-moon-stars theme-toggle__dark" aria-hidden="true"></i>
                </button>
                <span class="booth-user"><span class="booth-user__avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['username'], 0, 1))) ?></span> <?= e($user['username']) ?></span>
                <form method="post" action="<?= e(url('admin/logout.php')) ?>" class="m-0">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn-sm btn-booth">Sign out</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<main class="container-xxl booth-main" id="main">
    <?php foreach (take_flashes() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>

    <?= $content ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<?php foreach ($scripts as $script): ?>
    <script src="<?= e($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
