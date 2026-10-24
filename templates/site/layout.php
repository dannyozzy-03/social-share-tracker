<?php
/**
 * @var string $content
 * @var array<string, mixed> $meta
 * @var string $bodyClass
 * @var string|null $section Section to mark as current in the nav.
 */

use App\Content\ArticleRepository;
use App\View;

$siteName = (string) config('app.site_name');
$pageTitle = str_starts_with($meta['title'], $siteName) ? $meta['title'] : $meta['title'] . ' | ' . $siteName;
$bodyClass ??= '';
$section ??= null;
$isHome = ($_SERVER['SCRIPT_NAME'] ?? '') !== '' && str_ends_with((string) $_SERVER['SCRIPT_NAME'], '/index.php');
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FFFFFF">
    <script src="<?= e(asset('js/theme.js')) ?>"></script>
    <script src="<?= e(asset('js/transitions.js')) ?>"></script>
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($meta['description']) ?>">
    <?php if (!empty($meta['noindex'])): ?>
        <meta name="robots" content="noindex">
    <?php else: ?>
        <link rel="canonical" href="<?= e($meta['url']) ?>">
    <?php endif; ?>

    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:type" content="<?= e($meta['type']) ?>">
    <meta property="og:title" content="<?= e($meta['title']) ?>">
    <meta property="og:description" content="<?= e($meta['description']) ?>">
    <meta property="og:url" content="<?= e($meta['url']) ?>">
    <meta property="og:locale" content="en_MY">
    <?php if (!empty($meta['image'])): ?>
        <meta property="og:image" content="<?= e($meta['image']) ?>">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
    <?php endif; ?>
    <?php if ($meta['type'] === 'article'): ?>
        <meta property="article:published_time" content="<?= e($meta['published']) ?>">
        <meta property="article:author" content="<?= e($meta['author']) ?>">
        <meta property="article:section" content="<?= e($meta['section']) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="<?= empty($meta['image']) ? 'summary' : 'summary_large_image' ?>">
    <meta name="twitter:title" content="<?= e($meta['title']) ?>">
    <meta name="twitter:description" content="<?= e($meta['description']) ?>">
    <?php if (!empty($meta['image'])): ?>
        <meta name="twitter:image" content="<?= e($meta['image']) ?>">
    <?php endif; ?>

    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preload" href="<?= e(url('assets/fonts/poppins-700.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(asset('css/theme.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
    <script type="speculationrules">
    {"prerender": [{"where": {"and": [{"href_matches": "/*"}, {"not": {"selector_matches": "[target=_blank], [data-platform], [download], #export-link, [href*='logout'], [href*='export']"}}]}, "eagerness": "moderate"}]}
    </script>
</head>
<body class="site <?= e($bodyClass) ?>"
      data-counts-endpoint="<?= e(url('api/counts.php')) ?>"
      data-admin-session-endpoint="<?= e(url('admin/session-status.php')) ?>">
<a class="visually-hidden-focusable skip-link" href="#main">Skip to content</a>

<div class="topbar">
    <div class="container topbar__inner">
        <time class="topbar__date" datetime="<?= e(date('Y-m-d')) ?>">
            <span class="d-none d-sm-inline"><?= e(date('l, j F Y')) ?></span><span class="d-sm-none"><?= e(date('j M Y')) ?></span>
        </time>
        <div class="topbar__tools">
            <a class="topbar__signin" href="<?= e(url('admin/')) ?>" data-admin-session-link aria-label="Admin sign in" aria-live="polite">
                <i class="bi bi-person-lock" aria-hidden="true" data-admin-session-icon></i>
                <span class="d-none d-sm-inline" data-admin-session-guest>Admin sign in</span><span class="d-sm-none" data-admin-session-guest>Sign in</span>
                <span class="d-none d-sm-inline" data-admin-session-user hidden>Admin dashboard</span><span class="d-sm-none" data-admin-session-user hidden>Dashboard</span>
            </a>
            <button class="theme-toggle theme-toggle--bar" type="button" data-theme-toggle aria-pressed="false" aria-label="Dark mode" title="Switch to dark mode">
                <i class="bi bi-sun theme-toggle__light" aria-hidden="true"></i><i class="bi bi-moon-stars theme-toggle__dark" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</div>

<header class="masthead">
    <div class="container">
        <a class="wordmark masthead__brand" href="<?= e(url()) ?>" aria-label="<?= e($siteName) ?> home">
            <?= wordmark() ?>
        </a>
        <p class="masthead__tagline">Stories worth passing on</p>
    </div>
</header>

<nav class="sections" aria-label="Sections">
    <div class="container">
        <ul class="sections__list">
            <li><a class="sections__link sections__link--home<?= $section === null && $isHome ? ' is-active' : '' ?>" href="<?= e(url()) ?>"<?= $section === null && $isHome ? ' aria-current="page"' : '' ?>><i class="bi bi-house-door-fill" aria-hidden="true"></i> Home</a></li>
            <?php foreach (ArticleRepository::sections() as $name): ?>
                <li><a class="sections__link<?= $section === $name ? ' is-active' : '' ?>" href="<?= e(ArticleRepository::sectionUrl($name)) ?>"<?= $section === $name && $isHome ? ' aria-current="page"' : '' ?>><?= e($name) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>

<?= View::fetch('site/story-ticker', [
    'articles' => ArticleRepository::all(),
    'currentSlug' => is_string($_GET['slug'] ?? null) ? $_GET['slug'] : null,
]) ?>

<main id="main">
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="site-footer__top">
            <div>
                <a class="wordmark site-footer__brand" href="<?= e(url()) ?>"><?= wordmark() ?></a>
                <p class="site-footer__tagline">Stories worth passing on.</p>
            </div>
            <ul class="site-footer__links" aria-label="Sections">
                <?php foreach (ArticleRepository::sections() as $name): ?>
                    <li><a href="<?= e(ArticleRepository::sectionUrl($name)) ?>"><?= e($name) ?></a></li>
                <?php endforeach; ?>
                <li><a href="<?= e(url('admin/')) ?>" data-admin-session-link aria-label="Admin sign in" aria-live="polite"><span data-admin-session-guest>Admin sign in</span><span data-admin-session-user hidden>Admin dashboard</span></a></li>
            </ul>
        </div>
        <p class="site-footer__note">&copy; <?= e(date('Y')) ?> <?= e($siteName) ?>. A demo publication built for a web developer practical test. Articles are placeholder content and share counts include generated demo data.</p>
    </div>
</footer>

<nav class="mobile-nav d-md-none" aria-label="Mobile navigation">
    <a class="mobile-nav__link<?= $section === null && $isHome ? ' is-active' : '' ?>" href="<?= e(url()) ?>"<?= $section === null && $isHome ? ' aria-current="page"' : '' ?>>
        <i class="bi bi-house-door-fill" aria-hidden="true"></i>
        <span>Home</span>
    </a>
    <?php foreach (ArticleRepository::sections() as $name): ?>
        <?php
        $sectionIcon = match ($name) {
            'Food' => 'bi-cup-hot-fill',
            'Lifestyle' => 'bi-stars',
            'Money' => 'bi-cash-coin',
            'Travel' => 'bi-airplane-fill',
            default => 'bi-grid-fill',
        };
        $sectionIsCurrent = $section === $name;
        ?>
        <a class="mobile-nav__link<?= $sectionIsCurrent ? ' is-active' : '' ?>" href="<?= e(ArticleRepository::sectionUrl($name)) ?>"<?= $sectionIsCurrent ? ' aria-current="page"' : '' ?>>
            <i class="bi <?= e($sectionIcon) ?>" aria-hidden="true"></i>
            <span><?= e($name) ?></span>
        </a>
    <?php endforeach; ?>
    <a class="mobile-nav__link mobile-nav__account" href="<?= e(url('admin/')) ?>" data-admin-session-link aria-label="Admin sign in" aria-live="polite">
        <i class="bi bi-person-lock" aria-hidden="true" data-admin-session-icon></i>
        <span data-admin-session-guest>Sign in</span>
        <span data-admin-session-user hidden>Dashboard</span>
    </a>
    <button class="mobile-nav__link mobile-nav__theme" type="button" data-theme-toggle aria-pressed="false" aria-label="Dark mode" title="Switch to dark mode">
        <i class="bi bi-sun theme-toggle__light" aria-hidden="true"></i>
        <i class="bi bi-moon-stars theme-toggle__dark" aria-hidden="true"></i>
        <span>Theme</span>
    </button>
</nav>

<script src="<?= e(asset('js/share.js')) ?>" defer></script>
<script src="<?= e(asset('js/admin-session.js')) ?>" defer></script>
<script src="<?= e(asset('js/story-ticker.js')) ?>" defer></script>
</body>
</html>
