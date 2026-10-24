<?php
/**
 * @var list<array<string, mixed>> $articles
 * @var array<string, int> $shareCounts keyed by page URL
 * @var string|null $variant
 */

use App\Content\ArticleRepository;

$shares = static fn (int $n): string => number_format($n) . ($n === 1 ? ' share' : ' shares');
$variant ??= 'default';
$ranked = $articles;
usort($ranked, static fn (array $a, array $b): int =>
    ($shareCounts[ArticleRepository::url($b)] ?? 0) <=> ($shareCounts[ArticleRepository::url($a)] ?? 0));
?>
<aside class="ranked<?= $variant === 'section' ? ' ranked--section' : '' ?>" aria-labelledby="ranked-title">
    <div class="ranked__head">
        <h2 class="block-title" id="ranked-title">Trending (Most Shared)</h2>
        <span class="live-pill"><span class="live-pill__lamp" aria-hidden="true"></span> Live</span>
    </div>
    <ol class="ranked__list<?= $variant === 'section' ? ' ranked__list--section' : '' ?>">
        <?php foreach ($ranked as $article): ?>
            <?php $articleUrl = ArticleRepository::url($article); ?>
            <li class="ranked__item<?= $variant === 'section' ? ' ranked__item--section' : '' ?>">
                <div class="ranked__body">
                    <?php if ($variant === 'section'): ?>
                        <span class="chip"><?= e($article['category']) ?></span>
                    <?php endif; ?>
                    <a class="ranked__title stretched-link" href="<?= e($articleUrl) ?>"><?= e($article['title']) ?></a>
                    <p class="ranked__summary"><?= e($article['summary']) ?></p>
                    <p class="meta">
                        <?php if ($variant === 'section'): ?>
                            <time datetime="<?= e($article['published']) ?>"><?= e((new DateTimeImmutable($article['published']))->format('j M Y')) ?></time>
                        <?php else: ?>
                            <span><?= e($article['category']) ?></span>
                        <?php endif; ?>
                        <span class="meta__shares" data-share-total data-url="<?= e($articleUrl) ?>"><span data-share-label><?= e($shares($shareCounts[$articleUrl] ?? 0)) ?></span></span>
                    </p>
                </div>
                <img class="ranked__thumb" src="<?= e($article['image_src']) ?>" alt="" width="1200" height="630" loading="lazy">
            </li>
        <?php endforeach; ?>
    </ol>
</aside>
