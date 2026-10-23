<?php
/**
 * @var App\Stats\StatsFilter $filter
 * @var list<array<string, mixed>> $platforms
 * @var list<array{id: int, url: string, title: ?string}> $pages
 */

$allPlatformsSelected = $filter->platformIds === [];
$today = (new DateTimeImmutable())->format('Y-m-d');
?>
<div id="dashboard"
     data-stats-endpoint="<?= e(url('admin/api/stats.php')) ?>"
     data-export-endpoint="<?= e(url('admin/export.php')) ?>"
     data-login-url="<?= e(url('admin/login.php')) ?>">

    <header class="booth-header">
        <div>
            <h1 class="booth-title">Share analytics</h1>
            <p class="booth-subtitle">Which stories readers pass on, where they share them, and when.</p>
            <p class="live-tag"><span class="live-tag__lamp" aria-hidden="true"></span> Live · updates every 10 seconds · last <time id="live-time">–</time></p>
        </div>
        <a class="btn btn-booth" id="export-link" href="<?= e(url('admin/export.php') . '?' . http_build_query($_GET)) ?>">
            <i class="bi bi-download" aria-hidden="true"></i> Export CSV
        </a>
    </header>

    <form id="filters" class="cue-rail" method="get" action="<?= e(url('admin/')) ?>" novalidate aria-label="Filters">
        <button class="cue-rail__toggle d-sm-none" type="button" data-bs-toggle="collapse" data-bs-target="#filter-body" aria-expanded="false" aria-controls="filter-body">
            <span><i class="bi bi-sliders" aria-hidden="true"></i> Filters</span>
            <span class="cue-rail__summary" id="filter-summary"><?= e($filter->from->format('j M') . ' – ' . $filter->to->format('j M')) ?></span>
            <i class="bi bi-chevron-down cue-rail__chevron" aria-hidden="true"></i>
        </button>
        <div class="collapse d-sm-block" id="filter-body">
        <div class="row g-3 align-items-end">
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label" for="from">From</label>
                <input class="form-control" type="date" id="from" name="from" value="<?= e($filter->from->format('Y-m-d')) ?>" max="<?= e($today) ?>" required>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label" for="to">To</label>
                <input class="form-control" type="date" id="to" name="to" value="<?= e($filter->to->format('Y-m-d')) ?>" max="<?= e($today) ?>" required>
            </div>
            <div class="col-md-6 col-xl-3">
                <span class="form-label d-block" id="range-label">Quick range</span>
                <div class="btn-group w-100 range-group" role="group" aria-labelledby="range-label">
                    <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '1 year'] as $days => $label): ?>
                        <button type="button" class="btn" data-range="<?= $days ?>"><?= e($label) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-8 col-xl-3">
                <label class="form-label" for="page">Page</label>
                <select class="form-select" id="page" name="page">
                    <option value="">All pages</option>
                    <?php foreach ($pages as $page): ?>
                        <option value="<?= e($page['id']) ?>"<?= $filter->pageId === (int) $page['id'] ? ' selected' : '' ?>>
                            <?= e(mb_strimwidth($page['title'] ?? $page['url'], 0, 70, '…')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-xl-2">
                <label class="form-label" for="granularity">Group by</label>
                <select class="form-select" id="granularity" name="granularity">
                    <?php foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= $filter->granularity === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <fieldset class="col-12">
                <legend class="form-label">Platforms</legend>
                <div class="channels">
                    <?php foreach ($platforms as $platform): ?>
                        <?php $checked = $allPlatformsSelected || in_array((int) $platform['id'], $filter->platformIds, true); ?>
                        <input type="checkbox" class="btn-check" name="platforms[]" id="platform-<?= e($platform['id']) ?>"
                               value="<?= e($platform['id']) ?>" autocomplete="off"<?= $checked ? ' checked' : '' ?>>
                        <label class="channel" for="platform-<?= e($platform['id']) ?>" style="--gel-day: <?= e($platform['color']) ?>; --gel-night: <?= e(on_stage($platform['color'])) ?>">
                            <span class="channel__lamp" aria-hidden="true"></span>
                            <i class="bi <?= e($platform['icon']) ?>" aria-hidden="true"></i>
                            <?= e($platform['name']) ?>
                            <?php if (!(int) $platform['is_active']): ?><span class="channel__note">hidden</span><?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                    <button type="button" class="btn btn-link btn-sm" id="select-all-platforms">Select all</button>
                </div>
            </fieldset>
        </div>
        </div>

        <div id="filter-errors" class="alert alert-danger mt-3 mb-0 d-none" role="alert"></div>
        <noscript>
            <div class="alert alert-warning mt-3 mb-0">The graphs need JavaScript. Please enable it to use the dashboard.</div>
        </noscript>
    </form>

    <div id="report" class="report" aria-busy="true">
        <section class="kpis" aria-labelledby="kpi-title">
            <h2 class="visually-hidden" id="kpi-title">Summary</h2>
            <div class="kpi">
                <p class="kpi__label"><i class="bi bi-share" aria-hidden="true"></i> Total shares</p>
                <p class="kpi__value" id="kpi-total">–</p>
                <p class="kpi__note"><span class="kpi__change" id="kpi-change"></span> <span id="kpi-period">Loading…</span></p>
            </div>
            <div class="kpi">
                <p class="kpi__label"><i class="bi bi-calendar3" aria-hidden="true"></i> Daily average</p>
                <p class="kpi__value" id="kpi-average">–</p>
                <p class="kpi__note">shares per day in the period</p>
            </div>
            <div class="kpi">
                <p class="kpi__label"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Pages shared</p>
                <p class="kpi__value" id="kpi-pages">–</p>
                <p class="kpi__note">with at least one share</p>
            </div>
            <div class="kpi">
                <p class="kpi__label"><i class="bi bi-trophy" aria-hidden="true"></i> Top platform</p>
                <p class="kpi__value kpi__value--text" id="kpi-top">–</p>
                <p class="kpi__note" id="kpi-top-share">&nbsp;</p>
            </div>
        </section>

        <div class="booth-panel empty-state">
            <i class="bi bi-inbox" aria-hidden="true"></i>
            <h2 class="h5">No shares in this period</h2>
            <p class="mb-0">Try a wider date range, another page, or select more platforms.</p>
        </div>

        <div class="row g-3 report__detail">
            <section class="col-xl-8" aria-labelledby="timeline-title">
                <div class="booth-panel h-100">
                    <div class="booth-panel__header">
                        <h2 class="panel-label" id="timeline-title">Shares over time</h2>
                        <div class="btn-group btn-group-sm style-toggle" role="group" aria-label="Graph style">
                            <input type="radio" class="btn-check" name="timeline-style" id="timeline-bar" value="bar" checked>
                            <label class="btn" for="timeline-bar"><i class="bi bi-bar-chart-fill" aria-hidden="true"></i> Stacked</label>
                            <input type="radio" class="btn-check" name="timeline-style" id="timeline-line" value="line">
                            <label class="btn" for="timeline-line"><i class="bi bi-graph-up" aria-hidden="true"></i> Lines</label>
                        </div>
                    </div>
                    <div class="chart chart--tall"><canvas id="chart-timeline" role="img" aria-label="Shares over time by platform"></canvas></div>
                </div>
            </section>
            <section class="col-xl-4" aria-labelledby="mix-title">
                <div class="booth-panel h-100 mix">
                    <div class="mix__header">
                        <h2 class="panel-label" id="mix-title">Share by platform</h2>
                        <span class="mix__meta" id="mix-meta"></span>
                    </div>
                    <div class="mix__split" id="mix-split" aria-hidden="true"></div>
                    <div class="mix__head" aria-hidden="true">
                        <span>Platform</span><span></span><span class="text-end">Shares</span><span class="text-end">Share</span>
                    </div>
                    <div class="mix__rows" id="mix-rows" role="group" aria-label="Share of clicks by platform. Select a platform to show only it."></div>
                </div>
            </section>
        </div>

        <div class="row g-3 report__detail">
            <section class="col-xl-7" aria-labelledby="pages-title">
                <div class="booth-panel h-100">
                    <div class="booth-panel__header">
                        <h2 class="panel-label" id="pages-title">Most shared pages</h2>
                        <p class="panel-hint">Select a bar to focus on that page</p>
                    </div>
                    <div class="chart" id="chart-pages-wrap"><canvas id="chart-pages" role="img" aria-label="Most shared pages by platform"></canvas></div>
                </div>
            </section>
            <section class="col-xl-5" aria-labelledby="recent-title">
                <div class="booth-panel h-100">
                    <div class="booth-panel__header">
                        <h2 class="panel-label" id="recent-title">Latest shares</h2>
                    </div>
                    <ul class="cue-log" id="recent-list"></ul>
                </div>
            </section>
        </div>

        <section class="booth-panel report__detail" aria-labelledby="table-title">
            <div class="booth-panel__header">
                <h2 class="panel-label" id="table-title">Page breakdown</h2>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 breakdown">
                    <thead id="pages-table-head"></thead>
                    <tbody id="pages-table-body"></tbody>
                </table>
            </div>
        </section>
    </div>
</div>
