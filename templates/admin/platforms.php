<?php
/**
 * @var list<array<string, mixed>> $platforms
 */

use App\Security\Csrf;

?>
<header class="booth-header">
    <div>
        <h1 class="booth-title">Share platforms</h1>
        <p class="booth-subtitle">The share buttons are generated from this list. Add a network here and it appears on every page, in the filters and in every graph, with no code changes.</p>
    </div>
    <a class="btn btn-cue-primary" href="<?= e(url('admin/platform-edit.php')) ?>">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> Add platform
    </a>
</header>

<div class="booth-panel p-0">
    <div class="table-responsive">
        <table class="table align-middle mb-0 platforms-table">
            <thead>
            <tr>
                <th scope="col">Button</th>
                <th scope="col">Slug</th>
                <th scope="col">Share URL template</th>
                <th scope="col" class="text-end">Order</th>
                <th scope="col" class="text-end">Clicks</th>
                <th scope="col">Status</th>
                <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($platforms as $platform): ?>
                <?php $isActive = (int) $platform['is_active'] === 1; ?>
                <tr class="<?= $isActive ? '' : 'is-inactive' ?>">
                    <td>
                        <span class="gel-preview" style="--gel-day: <?= e($platform['color']) ?>; --gel-night: <?= e(on_stage($platform['color'])) ?>">
                            <i class="bi <?= e($platform['icon']) ?>" aria-hidden="true"></i> <?= e($platform['name']) ?>
                        </span>
                    </td>
                    <td><code><?= e($platform['slug']) ?></code></td>
                    <td class="template-cell"><code title="<?= e($platform['share_url_template']) ?>"><?= e($platform['share_url_template']) ?></code></td>
                    <td class="text-end"><?= e($platform['sort_order']) ?></td>
                    <td class="text-end"><?= e(number_format((int) $platform['clicks'])) ?></td>
                    <td>
                        <span class="state-tag <?= $isActive ? 'state-tag--on' : 'state-tag--off' ?>"><?= $isActive ? 'Shown' : 'Hidden' ?></span>
                    </td>
                    <td class="text-end text-nowrap">
                        <form method="post" action="<?= e(url('admin/platforms.php')) ?>" class="d-inline">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= e($platform['id']) ?>">
                            <input type="hidden" name="active" value="<?= $isActive ? '0' : '1' ?>">
                            <button type="submit" class="btn btn-sm btn-booth">
                                <?= $isActive ? 'Hide' : 'Show' ?><span class="visually-hidden"> <?= e($platform['name']) ?></span>
                            </button>
                        </form>
                        <a class="btn btn-sm btn-booth" href="<?= e(url('admin/platform-edit.php?id=' . $platform['id'])) ?>">
                            Edit<span class="visually-hidden"> <?= e($platform['name']) ?></span>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<p class="text-body-secondary small mt-3">
    Platforms are hidden rather than deleted so their past clicks stay in the reports.
</p>
