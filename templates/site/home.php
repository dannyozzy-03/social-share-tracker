<?php
/**
 * @var array<string, mixed> $meta
 * @var string|null $section Section the page is narrowed to, or null for the full front page.
 * @var string $homeUrl
 * @var list<array<string, mixed>> $articles Stories in this view, newest first.
 * @var list<array<string, mixed>> $allArticles
 * @var array<string, int> $shareCounts keyed by page URL
 * @var list<array<string, mixed>> $platforms
 */

use App\Content\ArticleRepository;
use App\View;

$lead = $articles[0];
$leadUrl = ArticleRepository::url($lead);
$date = static fn (string $d, string $format): string => (new DateTimeImmutable($d))->format($format);
$shares = static fn (int $n): string => number_format($n) . ($n === 1 ? ' share' : ' shares');

?>
<div class="container home">
    <?php if ($section === null): ?>
        <h1 class="visually-hidden"><?= e(config('app.site_name')) ?>: latest stories</h1>
    <?php else: ?>
        <header class="section-head">
            <nav aria-label="Breadcrumb"><a href="<?= e(url()) ?>">Home</a> <i class="bi bi-chevron-right" aria-hidden="true"></i> <span><?= e($section) ?></span></nav>
            <h1 class="section-head__title"><?= e($section) ?></h1>
        </header>
    <?php endif; ?>

    <div class="home__top">
        <section aria-labelledby="picks-title">
            <h2 class="block-title" id="picks-title"><?= $section === null ? "Editor's picks" : 'Latest in ' . e($section) ?></h2>
            <article class="lead">
                <img class="lead__img" data-cover="<?= e($lead['slug']) ?>" src="<?= e($lead['image_src']) ?>" alt="" width="1200" height="630" fetchpriority="high">
                <div class="lead__panel">
                    <span class="chip"><?= e($lead['category']) ?></span>
                    <h3 class="lead__title headline"><a class="stretched-link" href="<?= e($leadUrl) ?>"><?= e($lead['title']) ?></a></h3>
                    <p class="lead__summary"><?= e($lead['summary']) ?></p>
                    <p class="meta">
                        <span>By <?= e($lead['author']) ?></span>
                        <time datetime="<?= e($lead['published']) ?>"><?= e($date($lead['published'], 'j M Y')) ?></time>
                        <span class="meta__shares" data-share-total data-url="<?= e($leadUrl) ?>"><i class="bi bi-share-fill" aria-hidden="true"></i> <span data-share-label><?= e($shares($shareCounts[$leadUrl] ?? 0)) ?></span></span>
                    </p>
                </div>
            </article>
        </section>

        <?= View::fetch('site/most-shared', ['articles' => $allArticles, 'shareCounts' => $shareCounts]) ?>
    </div>

    <?php if (count($articles) > 1): ?>
        <section class="latest" aria-labelledby="latest-title">
            <div class="block-head">
                <h2 class="block-title" id="latest-title"><?= $section === null ? 'Latest stories' : 'More in ' . e($section) ?></h2>
            </div>
            <div class="card-grid">
                <?php foreach (array_slice($articles, 1) as $article): ?>
                    <?= View::fetch('site/card', ['article' => $article, 'shares' => $shareCounts[ArticleRepository::url($article)] ?? 0]) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="pass" aria-labelledby="pass-it-on">
        <div class="pass__copy">
            <h2 class="pass__title" id="pass-it-on">Pass it on</h2>
            <p class="pass__text">Know someone who would enjoy today's stories? Send them the front page.</p>
        </div>
        <?= View::fetch('site/share-bar', [
            'platforms' => $platforms,
            'page' => ['url' => $homeUrl, 'title' => config('app.site_name') . ' — Latest stories', 'description' => 'Food, travel, money and lifestyle stories from around Malaysia.'],
            'count' => $shareCounts[$homeUrl] ?? 0,
            'label' => 'Share the front page',
            'variant' => 'day',
        ]) ?>
    </section>
</div>
