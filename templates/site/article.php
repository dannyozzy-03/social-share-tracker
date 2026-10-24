<?php
/**
 * @var array<string, mixed> $meta
 * @var array<string, mixed> $article
 * @var list<array<string, mixed>> $all Every article, newest first.
 * @var array<string, mixed>|null $previous The older story, if any.
 * @var array<string, mixed>|null $next The newer story, if any.
 * @var array<string, int> $shareCounts keyed by page URL
 * @var int $shareCount
 * @var list<array<string, mixed>> $platforms
 */

use App\Content\ArticleRepository;
use App\View;

$sharePage = ['url' => $meta['url'], 'title' => $article['title'], 'description' => $article['summary']];
$bar = static fn (string $label, string $variant): string => View::fetch('site/share-bar', [
    'platforms' => $platforms,
    'page' => $sharePage,
    'count' => $shareCount,
    'label' => $label,
    'variant' => $variant,
]);
$date = static fn (string $d, string $format): string => (new DateTimeImmutable($d))->format($format);
$relatedArticles = array_slice(array_values(array_filter(
    $all,
    static fn (array $item): bool => $item['category'] === $article['category'] && $item['slug'] !== $article['slug'],
)), 0, 2);

// The opening word is set in bold capitals, as news sites do for the lead-in.
[$leadIn, $firstRest] = array_pad(explode(' ', $article['body'][0], 2), 2, '');
?>
<div class="container story-layout">
    <article class="story">
        <header class="story__head">
            <div class="story__headbox">
                <a class="chip chip--link" href="<?= e(ArticleRepository::sectionUrl($article['category'])) ?>"><?= e($article['category']) ?></a>
                <h1 class="story__title headline"><?= e($article['title']) ?></h1>
                <p class="story__dateline">
                    <time datetime="<?= e($article['published']) ?>"><?= e($date($article['published'], 'l, j F Y')) ?></time>
                    <span>By <?= e($article['author']) ?></span>
                    <span><?= e(ArticleRepository::readingMinutes($article)) ?> min read</span>
                </p>
                <?= $bar('Share', 'solid') ?>
            </div>
        </header>

        <figure class="story__figure">
            <img data-cover="<?= e($article['slug']) ?>" src="<?= e($article['image_src']) ?>" alt="" width="1200" height="630" fetchpriority="high">
            <?php if (!empty($article['caption'])): ?>
                <figcaption>
                    <i class="bi bi-image" aria-hidden="true"></i>
                    <?= e($article['caption']) ?>
                    <?php if (!empty($article['photo_credit'])): ?>
                        <span>Photo by <a href="<?= e($article['photo_credit']['source']) ?>" target="_blank" rel="noopener noreferrer"><?= e($article['photo_credit']['author']) ?></a> · <a href="<?= e($article['photo_credit']['license_url']) ?>" target="_blank" rel="license noopener noreferrer"><?= e($article['photo_credit']['license']) ?></a></span>
                    <?php elseif (!empty($article['credit'])): ?>
                        <span><?= e($article['credit']) ?></span>
                    <?php endif; ?>
                </figcaption>
            <?php endif; ?>
        </figure>

        <div class="story__body">
            <p class="story__standfirst"><?= e($article['summary']) ?></p>
            <p><strong class="story__lead-in"><?= e($leadIn) ?></strong> <?= e($firstRest) ?></p>
            <?php foreach (array_slice($article['body'], 1) as $index => $paragraph): ?>
                <p><?= e($paragraph) ?></p>
                <?php if (in_array($index, [0, 3], true) && isset($relatedArticles[$index === 0 ? 0 : 1])): ?>
                    <?php $related = $relatedArticles[$index === 0 ? 0 : 1]; ?>
                    <aside class="story__related">
                        <strong>Related story:</strong>
                        <a href="<?= e(ArticleRepository::url($related)) ?>"><?= e($related['title']) ?></a>
                    </aside>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <footer class="story__share">
            <h2 class="story__share-title">Found this useful? Pass it on.</h2>
            <?= $bar('Share this story', 'day') ?>
        </footer>

        <?php if ($previous !== null || $next !== null): ?>
            <nav class="adjacent" aria-label="More articles">
                <?php foreach ([['Previous article', $previous, 'bi-chevron-left', 'is-prev'], ['Next article', $next, 'bi-chevron-right', 'is-next']] as [$label, $item, $chevron, $side]): ?>
                    <?php if ($item === null): ?>
                        <div class="adjacent__slot" aria-hidden="true"></div>
                        <?php continue; ?>
                    <?php endif; ?>
                    <div class="adjacent__card <?= $side ?>">
                        <p class="adjacent__label"><i class="bi <?= $chevron ?>" aria-hidden="true"></i> <?= e($label) ?></p>
                        <div class="adjacent__row">
                            <div class="adjacent__text">
                                <span class="chip"><?= e($item['category']) ?></span>
                                <a class="adjacent__title stretched-link" href="<?= e(ArticleRepository::url($item)) ?>"><?= e($item['title']) ?></a>
                                <time class="adjacent__date" datetime="<?= e($item['published']) ?>"><?= e($date($item['published'], 'j F Y')) ?></time>
                            </div>
                            <img class="adjacent__thumb" src="<?= e($item['image_src']) ?>" alt="" width="1200" height="630" loading="lazy">
                        </div>
                    </div>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </article>

    <div class="story-layout__aside">
        <?= View::fetch('site/most-shared', ['articles' => $all, 'shareCounts' => $shareCounts, 'variant' => 'section']) ?>
    </div>
</div>

<div class="dock d-sm-none"><?= $bar('Share', 'dock') ?></div>
