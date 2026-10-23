<?php
/**
 * @var bool $creating
 * @var array<string, mixed>|null $platform
 * @var array<string, mixed> $values
 * @var array<string, string> $errors
 * @var string $title
 */

use App\Security\Csrf;

$invalid = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = static fn (string $field): string => isset($errors[$field])
    ? '<div class="invalid-feedback" id="' . e($field) . '-error">' . e($errors[$field]) . '</div>'
    : '';
$describedBy = static function (string $field, string $help) use ($errors): string {
    $ids = trim($help . (isset($errors[$field]) ? " $field-error" : ''));

    return $ids === '' ? '' : 'aria-describedby="' . e($ids) . '"';
};
$color = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $values['color']) ? (string) $values['color'] : '#6C757D';
?>
<header class="booth-header">
    <div>
        <nav aria-label="Breadcrumb">
            <ol class="breadcrumb mb-2 small">
                <li class="breadcrumb-item"><a href="<?= e(url('admin/platforms.php')) ?>">Platforms</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= $creating ? 'Add' : 'Edit' ?></li>
            </ol>
        </nav>
        <h1 class="booth-title"><?= e($title) ?></h1>
    </div>
</header>

<div class="row g-3">
    <div class="col-lg-7">
        <form method="post" class="booth-panel" id="platform-form" novalidate>
            <?= Csrf::field() ?>

            <?php if (isset($errors['form'])): ?>
                <div class="alert alert-danger" role="alert"><?= e($errors['form']) ?></div>
            <?php elseif ($errors !== []): ?>
                <div class="alert alert-danger" role="alert">Please fix the highlighted fields.</div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control<?= $invalid('name') ?>" id="name" name="name" maxlength="64" required
                           value="<?= e($values['name']) ?>" <?= $describedBy('name', '') ?>>
                    <?= $feedback('name') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="slug">Slug</label>
                    <?php if ($creating): ?>
                        <input class="form-control<?= $invalid('slug') ?>" id="slug" name="slug" maxlength="32" required
                               pattern="[a-z0-9][a-z0-9_\-]*" value="<?= e($values['slug']) ?>" <?= $describedBy('slug', 'slug-help') ?>>
                        <div class="form-text" id="slug-help">Lowercase ID sent by the share button, e.g. <code>linkedin</code>. Cannot be changed later.</div>
                        <?= $feedback('slug') ?>
                    <?php else: ?>
                        <input class="form-control" id="slug" value="<?= e($platform['slug']) ?>" disabled aria-describedby="slug-help">
                        <div class="form-text" id="slug-help">Slugs are permanent because recorded clicks refer to them.</div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="form-label" for="share_url_template">Share URL template</label>
                    <input class="form-control font-monospace<?= $invalid('share_url_template') ?>" id="share_url_template"
                           name="share_url_template" maxlength="512" required
                           value="<?= e($values['share_url_template']) ?>" <?= $describedBy('share_url_template', 'template-help') ?>>
                    <div class="form-text" id="template-help">
                        An <code>https://</code> or <code>mailto:</code> URL. Placeholders are URL-encoded automatically:
                        <code>{url}</code> (required), <code>{title}</code>, <code>{description}</code>.
                    </div>
                    <?= $feedback('share_url_template') ?>
                </div>

                <div class="col-md-5">
                    <label class="form-label" for="icon">Icon</label>
                    <input class="form-control<?= $invalid('icon') ?>" id="icon" name="icon" maxlength="64"
                           value="<?= e($values['icon']) ?>" <?= $describedBy('icon', 'icon-help') ?>>
                    <div class="form-text" id="icon-help">A <a href="https://icons.getbootstrap.com/" target="_blank" rel="noopener">Bootstrap Icons</a> class, e.g. <code>bi-linkedin</code>.</div>
                    <?= $feedback('icon') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="color">Brand colour</label>
                    <div class="input-group has-validation">
                        <input type="color" class="form-control form-control-color" id="color-picker"
                               value="<?= e($color) ?>" aria-label="Pick brand colour">
                        <input class="form-control font-monospace<?= $invalid('color') ?>" id="color" name="color" maxlength="7" required
                               value="<?= e($values['color']) ?>" <?= $describedBy('color', '') ?>>
                        <?= $feedback('color') ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="sort_order">Order</label>
                    <input class="form-control<?= $invalid('sort_order') ?>" id="sort_order" name="sort_order" type="number" min="0" max="9999"
                           value="<?= e($values['sort_order']) ?>" <?= $describedBy('sort_order', '') ?>>
                    <?= $feedback('sort_order') ?>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= (int) $values['is_active'] === 1 ? ' checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Show this button on the website</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-cue-primary"><?= $creating ? 'Add platform' : 'Save changes' ?></button>
                <a class="btn btn-booth" href="<?= e(url('admin/platforms.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>

    <aside class="col-lg-5">
        <div class="booth-panel">
            <h2 class="panel-label mb-3">Preview</h2>
            <span class="gel-preview gel-preview--lg" id="preview" style="--gel-day: <?= e($color) ?>; --gel-night: <?= e(on_stage($color)) ?>">
                <i class="bi <?= e($values['icon']) ?>" id="preview-icon" aria-hidden="true"></i>
                <span id="preview-name"><?= e($values['name'] !== '' ? $values['name'] : 'Platform') ?></span>
            </span>
            <p class="text-body-secondary small mt-3 mb-1">Example share link for the front page:</p>
            <code class="d-block text-break small" id="preview-link"></code>
        </div>
        <div class="booth-panel mt-3">
            <h2 class="panel-label mb-2">Template examples</h2>
            <ul class="small text-body-secondary mb-0 ps-3 template-examples">
                <li>LinkedIn: <code>https://www.linkedin.com/sharing/share-offsite/?url={url}</code></li>
                <li>Reddit: <code>https://www.reddit.com/submit?url={url}&amp;title={title}</code></li>
                <li>Threads: <code>https://www.threads.net/intent/post?text={title}%20{url}</code></li>
            </ul>
        </div>
    </aside>
</div>
<script src="<?= e(asset('js/platform-form.js')) ?>" data-sample-url="<?= e(app_url()) ?>" data-sample-title="<?= e(config('app.site_name')) ?>" id="platform-form-script" defer></script>
