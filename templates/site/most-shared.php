<?php
/**
 * @var list<array<string, mixed>> $articles
 * @var array<string, int> $shareCounts keyed by page URL
 */

use App\Content\ArticleRepository;

$shares = static fn (int $n): string => number_format($n) . ($n === 1 ? ' share' : ' shares');
$ranked = $articles;
usort($ranked, static fn (array $a, array $b): int =>
    ($shareCounts[ArticleRepository::url($b)] ?? 0) <=> ($shareCounts[ArticleRepository::url($a)] ?? 0));
?>
<aside class="ranked" aria-labelledby="ranked-title">
    <div class="ranked__head">
        <h2 class="block-title" id="ranked-title">Most shared</h2>
        <span class="live-pill"><span class="live-pill__lamp" aria-hidden="true"></span> Live</span>
    </div>
    <ol class="ranked__list">
        <?php foreach (array_slice($ranked, 0, 5) as $article): ?>
            <?php $articleUrl = ArticleRepository::url($article); ?>
            <li class="ranked__item">
                <div class="ranked__body">
                    <a class="ranked__title stretched-link" href="<?= e($articleUrl) ?>"><?= e($article['title']) ?></a>
                    <p class="meta">
                        <span><?= e($article['category']) ?></span>
                        <span class="meta__shares" data-share-total data-url="<?= e($articleUrl) ?>"><span data-share-label><?= e($shares($shareCounts[$articleUrl] ?? 0)) ?></span></span>
                    </p>
                </div>
                <img class="ranked__thumb" src="<?= e($article['image_src']) ?>" alt="" width="1200" height="630" loading="lazy">
            </li>
        <?php endforeach; ?>
    </ol>
</aside>
