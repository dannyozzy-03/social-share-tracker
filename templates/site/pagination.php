<?php
/**
 * Numbered pager: « Previous  1 … 4 5 6 … 20  Next ». Plain links, so it works without JavaScript
 * and every page has its own address. Renders nothing when everything fits on one page.
 *
 * @var App\Support\Paginator $pager
 * @var callable(int): string $pageUrl
 * @var string $label What is being paged, for screen readers, e.g. "Latest stories".
 * @var string|null $anchor Element ID to jump to on the new page, e.g. "latest".
 */

if ($pager->pages() < 2) {
    return;
}
$anchor ??= null;
$href = static fn (int $page): string => $pageUrl($page) . ($anchor !== null ? '#' . $anchor : '');
[$first, $last] = $pager->range();
?>
<nav class="pager" aria-label="<?= e($label) ?> pages">
    <p class="pager__summary"><?= e(sprintf('Showing %s–%s of %s', number_format($first), number_format($last), number_format($pager->total))) ?></p>
    <ul class="pager__list">
        <li>
            <?php if ($pager->hasPrevious()): ?>
                <a class="pager__link pager__link--step" href="<?= e($href($pager->page - 1)) ?>" rel="prev"><i class="bi bi-chevron-left" aria-hidden="true"></i> <span>Previous</span></a>
            <?php else: ?>
                <span class="pager__link pager__link--step is-disabled" aria-hidden="true"><i class="bi bi-chevron-left"></i> <span>Previous</span></span>
            <?php endif; ?>
        </li>
        <?php foreach ($pager->links() as $number): ?>
            <li class="pager__number">
                <?php if ($number === null): ?>
                    <span class="pager__gap" aria-hidden="true">…</span>
                <?php elseif ($number === $pager->page): ?>
                    <a class="pager__link is-current" href="<?= e($href($number)) ?>" aria-current="page" aria-label="Page <?= e($number) ?>, current page"><?= e($number) ?></a>
                <?php else: ?>
                    <a class="pager__link" href="<?= e($href($number)) ?>" aria-label="Page <?= e($number) ?>"><?= e($number) ?></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        <li>
            <?php if ($pager->hasNext()): ?>
                <a class="pager__link pager__link--step" href="<?= e($href($pager->page + 1)) ?>" rel="next"><span>Next</span> <i class="bi bi-chevron-right" aria-hidden="true"></i></a>
            <?php else: ?>
                <span class="pager__link pager__link--step is-disabled" aria-hidden="true"><span>Next</span> <i class="bi bi-chevron-right"></i></span>
            <?php endif; ?>
        </li>
    </ul>
</nav>
