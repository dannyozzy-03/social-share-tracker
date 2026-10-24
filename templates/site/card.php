<?php
/**
 * @var array<string, mixed> $article
 * @var int|null $shares Live share count, or null to leave it out.
 */

use App\Content\ArticleRepository;

$articleUrl = ArticleRepository::url($article);
?>
<article class="card-story">
    <img class="card-story__img" data-cover="<?= e($article['slug']) ?>" src="<?= e($article['image_src']) ?>" alt="" width="1200" height="630" loading="lazy">
    <div class="card-story__body">
        <span class="chip"><?= e($article['category']) ?></span>
        <h3 class="card-story__title"><a class="stretched-link" href="<?= e($articleUrl) ?>"><?= e($article['title']) ?></a></h3>
        <p class="card-story__summary"><?= e($article['summary']) ?></p>
        <p class="meta">
            <time datetime="<?= e($article['published']) ?>"><?= e((new DateTimeImmutable($article['published']))->format('j M Y')) ?></time>
            <?php if ($shares !== null): ?>
                <span class="meta__shares" data-share-total data-url="<?= e($articleUrl) ?>"><i class="bi bi-share-fill" aria-hidden="true"></i> <span data-share-label><?= e(number_format($shares) . ($shares === 1 ? ' share' : ' shares')) ?></span></span>
            <?php endif; ?>
        </p>
    </div>
</article>
