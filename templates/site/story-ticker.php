<?php
/**
 * @var list<array<string, mixed>> $articles
 * @var string|null $currentSlug
 */

use App\Content\ArticleRepository;

$renderStories = static function (bool $clone) use ($articles, $currentSlug): void {
    ?>
    <ol class="strip__list<?= $clone ? ' strip__list--clone' : '' ?>"<?= $clone ? ' aria-hidden="true"' : '' ?>>
        <?php foreach ($articles as $item): ?>
            <?php $current = !$clone && $item['slug'] === $currentSlug; ?>
            <li class="strip__item<?= $current ? ' is-current' : '' ?>">
                <div class="strip__text">
                    <span class="chip"><?= e($item['category']) ?></span>
                    <a class="strip__title" href="<?= e(ArticleRepository::url($item)) ?>"<?= $current ? ' aria-current="page"' : '' ?><?= $clone ? ' tabindex="-1"' : '' ?>><?= e($item['title']) ?></a>
                </div>
                <img class="strip__thumb" src="<?= e($item['image_src']) ?>" alt="" width="1200" height="630" loading="lazy">
            </li>
        <?php endforeach; ?>
    </ol>
    <?php
};
?>
<nav class="strip" aria-label="Latest stories">
    <div class="strip__inner" data-story-ticker>
        <div class="strip__viewport" data-story-viewport>
            <div class="strip__track">
                <?php $renderStories(true); ?>
                <?php $renderStories(false); ?>
            </div>
        </div>
    </div>
</nav>
