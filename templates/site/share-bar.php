<?php
/**
 * @var list<array<string, mixed>> $platforms
 * @var array{url: string, title: string, description: string} $page
 * @var int $count
 * @var string $label
 * @var string $variant "night", "day", "solid" (filled icon squares) or "dock"
 */

use App\Share\ShareLinkBuilder;

$variant ??= 'night';
$labelId = 'share-label-' . substr(md5($page['url'] . $label . $variant), 0, 8);
?>
<div class="share-bar share-bar--<?= e($variant) ?>" data-share-bar
     data-endpoint="<?= e(url('api/track.php')) ?>"
     data-url="<?= e($page['url']) ?>"
     data-title="<?= e($page['title']) ?>"
     <?php if ($variant === 'dock'): ?>role="region" aria-label="Share this story"<?php endif; ?>>
    <p class="share-bar__label" id="<?= e($labelId) ?>"><?= e($label) ?></p>
    <ul class="share-bar__list" aria-labelledby="<?= e($labelId) ?>">
        <?php foreach ($platforms as $platform): ?>
            <?php $newWindow = ShareLinkBuilder::opensInNewWindow($platform['share_url_template']); ?>
            <li>
                <a class="gel"
                   href="<?= e(ShareLinkBuilder::build($platform['share_url_template'], $page)) ?>"
                   style="--gel: <?= e($platform['color']) ?>; --gel-night: <?= e(on_stage($platform['color'])) ?>"
                   data-platform="<?= e($platform['slug']) ?>"
                   <?php if ($newWindow): ?>target="_blank" rel="noopener noreferrer" data-popup<?php endif; ?>
                   title="Share on <?= e($platform['name']) ?>"
                   aria-label="Share on <?= e($platform['name']) ?>">
                    <i class="bi <?= e($platform['icon']) ?> gel__icon" aria-hidden="true"></i>
                    <span class="gel__name"><?= e($platform['name']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="share-bar__count" aria-live="polite">
        <strong class="share-bar__number" data-share-count><?= e(number_format($count)) ?></strong>
        <span data-share-noun><?= $count === 1 ? 'share' : 'shares' ?></span>
    </p>
</div>
