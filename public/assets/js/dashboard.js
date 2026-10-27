(() => {
  'use strict';

  const root = document.getElementById('dashboard');
  if (!root || !window.Chart) {
    return;
  }

  const form = document.getElementById('filters');
  const report = document.getElementById('report');
  const errorBox = document.getElementById('filter-errors');
  const exportLink = document.getElementById('export-link');
  const platformBoxes = Array.from(form.querySelectorAll('input[name="platforms[]"]'));

  const number = new Intl.NumberFormat('en-MY');
  const dayMonth = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short' });
  const monthYear = new Intl.DateTimeFormat('en-GB', { month: 'short', year: 'numeric' });
  const fullDate = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium', timeStyle: 'short' });
  const relative = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });
  const clock = new Intl.DateTimeFormat('en-GB', { timeStyle: 'medium' });
  const liveTime = document.getElementById('live-time');
  const LIVE_MS = 10000;

  const PALETTES = {
    dark: { ink: '#B4B9CC', faint: '#8A90A6', grid: 'rgba(180, 185, 204, .10)', tipBg: '#1D2133', tipInk: '#F2F3F8', tipFoot: '#8EA4FF', tipLine: '#363C52' },
    light: { ink: '#474D62', faint: '#676D82', grid: 'rgba(15, 18, 34, .07)', tipBg: '#FFFFFF', tipInk: '#0F1222', tipFoot: '#0A33FF', tipLine: '#CDD1DB' },
  };
  let colors = PALETTES.light;

  const charts = {};
  let timelineStyle = 'bar';
  let lastReport = null;
  let controller = null;
  let requestId = 0;
  let debounce = null;

  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.legend.labels.pointStyle = 'rectRounded';
  Chart.defaults.plugins.tooltip.borderWidth = 1;
  Chart.defaults.plugins.tooltip.cornerRadius = 8;
  Chart.defaults.plugins.tooltip.padding = 12;

  function isLight() {
    return document.documentElement.getAttribute('data-theme') === 'light';
  }

  function applyTheme() {
    colors = PALETTES[isLight() ? 'light' : 'dark'];
    Chart.defaults.color = colors.ink;
    Chart.defaults.borderColor = colors.grid;
    Chart.defaults.plugins.tooltip.backgroundColor = colors.tipBg;
    Chart.defaults.plugins.tooltip.titleColor = colors.tipInk;
    Chart.defaults.plugins.tooltip.bodyColor = colors.tipInk;
    Chart.defaults.plugins.tooltip.footerColor = colors.tipFoot;
    Chart.defaults.plugins.tooltip.borderColor = colors.tipLine;
  }

  // Brand colours as-is on light; lifted near-blacks (X) on dark.
  function gel(platform) {
    return isLight() ? platform.color : platform.stage_color;
  }

  applyTheme();
  document.addEventListener('themechange', () => {
    applyTheme();
    Object.keys(charts).forEach((key) => {
      charts[key].destroy();
      delete charts[key];
    });
    if (lastReport) {
      render(lastReport);
    }
  });

  function el(tag, props, ...children) {
    const node = document.createElement(tag);
    Object.entries(props || {}).forEach(([key, value]) => {
      if (key === 'class') {
        node.className = value;
      } else if (key === 'style') {
        Object.entries(value).forEach(([prop, val]) => node.style.setProperty(prop, val));
      } else if (key.startsWith('on')) {
        node.addEventListener(key.slice(2), value);
      } else {
        node.setAttribute(key, value);
      }
    });
    node.append(...children.filter((child) => child !== null && child !== undefined && child !== false));
    return node;
  }

  function icon(name) {
    return el('i', { class: `bi ${name}`, 'aria-hidden': 'true' });
  }

  function toISODate(date) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
  }

  function parseISODate(value) {
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d);
  }

  function formatPeriod(key, granularity) {
    const date = parseISODate(key);
    return granularity === 'month' ? monthYear.format(date) : dayMonth.format(date);
  }

  function hexToRgba(hex, alpha) {
    const n = parseInt(hex.slice(1), 16);
    return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
  }

  function truncate(text, max) {
    return text.length > max ? `${text.slice(0, max - 1)}…` : text;
  }

  function pageLabel(page) {
    return page.title || page.url.replace(/^https?:\/\//, '');
  }

  function timeAgo(iso) {
    const seconds = Math.round((new Date(iso) - Date.now()) / 1000);
    for (const [unit, size] of [['day', 86400], ['hour', 3600], ['minute', 60]]) {
      if (Math.abs(seconds) >= size) {
        return relative.format(Math.round(seconds / size), unit);
      }
    }
    return 'just now';
  }

  function safeHref(url) {
    try {
      const target = new URL(url);
      if (target.protocol !== 'http:' && target.protocol !== 'https:') {
        return '#';
      }

      const isLoopback = (hostname) => hostname === 'localhost'
        || hostname === '::1'
        || hostname === '[::1]'
        || /^127(?:\.\d{1,3}){3}$/.test(hostname);
      if (isLoopback(target.hostname) && isLoopback(window.location.hostname)) {
        target.host = window.location.host;
        target.protocol = window.location.protocol;
      }

      return target.href;
    } catch {
      return '#';
    }
  }

  function drawChart(key, canvasId, config) {
    const existing = charts[key];
    if (existing && existing.config.type === config.type) {
      existing.data = config.data;
      existing.options = config.options;
      existing.update();
      return;
    }
    if (existing) {
      existing.destroy();
    }
    charts[key] = new Chart(document.getElementById(canvasId), config);
  }

  function buildQuery() {
    const params = new URLSearchParams();
    ['from', 'to', 'granularity', 'page'].forEach((name) => {
      const value = form.elements[name].value;
      if (value) {
        params.set(name, value);
      }
    });
    // All platforms selected is the default, so only send the parameter for a subset.
    const checked = platformBoxes.filter((box) => box.checked);
    if (checked.length < platformBoxes.length) {
      checked.forEach((box) => params.append('platforms[]', box.value));
    }
    return params;
  }

  function highlightQuickRange() {
    const today = toISODate(new Date());
    form.querySelectorAll('[data-range]').forEach((button) => {
      const start = new Date();
      start.setDate(start.getDate() - (Number(button.dataset.range) - 1));
      const active = form.elements.to.value === today && form.elements.from.value === toISODate(start);
      button.classList.toggle('active', active);
      button.setAttribute('aria-pressed', String(active));
    });
  }

  // Clicking the only selected platform again brings all platforms back.
  function soloPlatform(id) {
    const soloed = platformBoxes.every((box) => box.checked === (Number(box.value) === id));
    platformBoxes.forEach((box) => { box.checked = soloed || Number(box.value) === id; });
    load();
  }

  function focusPage(page) {
    const select = form.elements.page;
    if (!select.querySelector(`option[value="${page.id}"]`)) {
      select.append(el('option', { value: String(page.id) }, truncate(pageLabel(page), 70)));
    }
    select.value = String(page.id);
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    load();
  }

  form.addEventListener('change', (event) => {
    if (event.target.name === 'platforms[]' && !platformBoxes.some((box) => box.checked)) {
      event.target.checked = true; // at least one platform must stay selected
      return;
    }
    highlightQuickRange();
    clearTimeout(debounce);
    debounce = setTimeout(load, 200);
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    load();
  });

  form.querySelectorAll('[data-range]').forEach((button) => {
    button.addEventListener('click', () => {
      const days = Number(button.dataset.range);
      const to = new Date();
      const from = new Date();
      from.setDate(to.getDate() - (days - 1));
      form.elements.from.value = toISODate(from);
      form.elements.to.value = toISODate(to);
      if (days > 120 && form.elements.granularity.value === 'day') {
        form.elements.granularity.value = 'week';
      }
      highlightQuickRange();
      load();
    });
  });

  document.getElementById('select-all-platforms').addEventListener('click', () => {
    platformBoxes.forEach((box) => { box.checked = true; });
    load();
  });

  document.querySelectorAll('input[name="timeline-style"]').forEach((radio) => {
    radio.addEventListener('change', () => {
      timelineStyle = radio.value;
      if (lastReport) {
        renderTimeline(lastReport);
      }
    });
  });

  function showErrors(errors) {
    ['from', 'to', 'granularity', 'page'].forEach((name) => {
      form.elements[name].classList.toggle('is-invalid', Boolean(errors && errors[name]));
    });
    errorBox.classList.toggle('d-none', !errors);
    errorBox.textContent = errors ? Object.values(errors).join(' ') : '';
  }

  // A silent load is the live refresh: no loading fade, and it never interrupts a filter change.
  async function load({ silent = false } = {}) {
    if (silent && controller) {
      return;
    }
    const query = buildQuery().toString();
    history.replaceState(null, '', query ? `?${query}` : window.location.pathname);
    exportLink.href = root.dataset.exportEndpoint + (query ? `?${query}` : '');

    if (controller) {
      controller.abort();
    }
    controller = new AbortController();
    const current = ++requestId;
    if (!silent) {
      report.classList.add('is-loading');
      report.setAttribute('aria-busy', 'true');
    }

    try {
      const response = await fetch(`${root.dataset.statsEndpoint}?${query}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: controller.signal,
      });
      if (response.status === 401) {
        window.location.href = `${root.dataset.loginUrl}?expired=1`;
        return;
      }
      const body = await response.json();
      if (!response.ok || !body.ok) {
        if (!silent) {
          showErrors(body.errors || { error: body.error || 'Could not load statistics.' });
        }
        return;
      }
      showErrors(null);
      lastReport = body;
      render(body);
      liveTime.textContent = clock.format(new Date());
      liveTime.setAttribute('datetime', new Date().toISOString());
    } catch (error) {
      if (error.name !== 'AbortError' && !silent) {
        showErrors({ error: 'Could not load statistics. Check your connection and try again.' });
      }
    } finally {
      if (current === requestId) {
        controller = null;
        report.classList.remove('is-loading');
        report.setAttribute('aria-busy', 'false');
      }
    }
  }

  function render(data) {
    report.classList.toggle('is-empty', data.summary.total === 0);
    renderReadout(data);
    renderMix(data);
    renderTimeline(data);
    renderTopPages(data);
    renderRecent(data);
    renderTable(data);
  }

  function renderReadout({ summary, filters }) {
    const from = dayMonth.format(parseISODate(filters.from));
    const to = dayMonth.format(parseISODate(filters.to));
    const decimal = new Intl.NumberFormat('en-MY', { maximumFractionDigits: 1 });

    document.getElementById('kpi-total').textContent = number.format(summary.total);
    document.getElementById('kpi-average').textContent = decimal.format(summary.daily_average);
    document.getElementById('kpi-pages').textContent = number.format(summary.pages);
    document.getElementById('kpi-period').textContent = `${from} – ${to}`;

    const change = document.getElementById('kpi-change');
    if (summary.change_pct === null) {
      change.className = 'kpi__change';
      change.replaceChildren();
    } else {
      const up = summary.change_pct >= 0;
      change.className = `kpi__change ${up ? 'is-up' : 'is-down'}`;
      change.title = `${number.format(summary.previous_total)} shares in the previous period`;
      change.replaceChildren(icon(up ? 'bi-arrow-up-right' : 'bi-arrow-down-right'), ` ${Math.abs(summary.change_pct)}%`);
    }

    const top = summary.top_platform;
    document.getElementById('kpi-top').replaceChildren(...(top
      ? [el('i', { class: `bi ${top.icon}`, style: { color: gel(top) }, 'aria-hidden': 'true' }), ` ${top.name}`]
      : ['–']));
    document.getElementById('kpi-top-share').textContent = top ? `${summary.top_platform_share}% of all shares` : 'No shares yet';

    const platforms = platformBoxes.filter((box) => box.checked).length;
    document.getElementById('filter-summary').textContent =
      `${from} – ${to} · ${platforms === platformBoxes.length ? 'all platforms' : `${platforms} of ${platformBoxes.length} platforms`}`;
  }

  // Ranked list: bar length is relative to the leader, the split bar above shows the true 100% mix.
  function renderMix({ by_platform: platforms, summary }) {
    const ranked = [...platforms].sort((a, b) => b.count - a.count);
    const max = Math.max(1, ...ranked.map((p) => p.count));
    const selected = platformBoxes.filter((box) => box.checked).map((box) => Number(box.value));
    const soloed = selected.length === 1 ? selected[0] : null;
    const share = (p) => (summary.total ? (p.count / summary.total) * 100 : 0);
    const active = ranked.filter((p) => p.count > 0).length;

    document.getElementById('mix-meta').textContent =
      `${number.format(summary.total)} shares · ${active} of ${ranked.length} active`;

    document.getElementById('mix-split').replaceChildren(...ranked.filter((p) => p.count > 0).map((p) =>
      el('span', { style: { '--gel': gel(p), 'flex-grow': String(p.count) }, title: `${p.name} ${share(p).toFixed(1)}%` })));

    document.getElementById('mix-rows').replaceChildren(...ranked.map((p) => {
      const pct = share(p);
      return el('button', {
        type: 'button',
        class: `mix__row${soloed === p.id ? ' is-solo' : ''}${p.count === 0 ? ' is-zero' : ''}`,
        style: { '--gel': gel(p), '--level': String(p.count / max) },
        'aria-pressed': String(soloed === p.id),
        'aria-label': `${p.name}: ${number.format(p.count)} shares, ${pct.toFixed(1)}%. ${soloed === p.id ? 'Show all platforms.' : `Show only ${p.name}.`}`,
        onclick: () => soloPlatform(p.id),
      },
      el('span', { class: 'mix__name', 'aria-hidden': 'true' }, icon(p.icon), el('span', null, p.name)),
      el('span', { class: 'mix__track', 'aria-hidden': 'true' }, el('span', { class: 'mix__fill' })),
      el('span', { class: 'mix__count', 'aria-hidden': 'true' }, number.format(p.count)),
      el('span', { class: 'mix__pct', 'aria-hidden': 'true' }, `${pct.toFixed(1)}%`));
    }));
  }

  function renderTimeline({ timeseries, filters }) {
    const stacked = timelineStyle === 'bar';
    const dense = timeseries.periods.length > 45;

    drawChart('timeline', 'chart-timeline', {
      type: stacked ? 'bar' : 'line',
      data: {
        labels: timeseries.periods.map((key) => formatPeriod(key, filters.granularity)),
        datasets: timeseries.series.map((series) => ({
          label: series.name,
          data: series.data,
          backgroundColor: stacked ? gel(series) : hexToRgba(gel(series), 0.15),
          borderColor: gel(series),
          borderWidth: stacked ? 0 : 2,
          borderRadius: stacked ? 1 : 0,
          maxBarThickness: 36,
          pointRadius: dense ? 0 : 2.5,
          pointHoverRadius: 5,
          tension: 0.3,
        })),
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
          x: { stacked, grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 16, color: colors.faint } },
          y: { stacked, beginAtZero: true, ticks: { precision: 0, color: colors.faint }, grid: { color: colors.grid }, border: { display: false } },
        },
        plugins: {
          legend: { position: 'bottom', labels: { color: colors.ink } },
          tooltip: {
            callbacks: {
              title: (items) => {
                const key = timeseries.periods[items[0].dataIndex];
                const prefix = filters.granularity === 'week' ? 'Week of ' : '';
                return prefix + (filters.granularity === 'month' ? monthYear : dayMonth).format(parseISODate(key));
              },
              footer: (items) => `Total: ${number.format(items.reduce((sum, item) => sum + item.parsed.y, 0))}`,
            },
          },
        },
      },
    });
  }

  function renderTopPages({ top_pages: pages, timeseries }) {
    document.getElementById('chart-pages-wrap').style.height = `${Math.max(240, pages.length * 40 + 70)}px`;

    drawChart('pages', 'chart-pages', {
      type: 'bar',
      data: {
        labels: pages.map((page) => truncate(pageLabel(page), window.innerWidth < 576 ? 22 : 34)),
        datasets: timeseries.series.map((series) => ({
          label: series.name,
          data: pages.map((page) => page.counts[series.slug] || 0),
          backgroundColor: gel(series),
          borderRadius: 1,
          maxBarThickness: 24,
        })),
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', axis: 'y', intersect: false },
        scales: {
          x: { stacked: true, beginAtZero: true, ticks: { precision: 0, color: colors.faint }, grid: { color: colors.grid }, border: { display: false } },
          y: { stacked: true, grid: { display: false }, ticks: { color: colors.ink } },
        },
        onClick: (event, elements) => {
          if (elements.length) {
            focusPage(pages[elements[0].index]);
          }
        },
        onHover: (event, elements) => {
          event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
        },
        plugins: {
          legend: { position: 'bottom', labels: { color: colors.ink } },
          tooltip: {
            callbacks: {
              title: (items) => pageLabel(pages[items[0].dataIndex]),
              footer: (items) => `Total: ${number.format(pages[items[0].dataIndex].total)}`,
            },
          },
        },
      },
    });
  }

  function renderRecent({ recent }) {
    const list = document.getElementById('recent-list');
    if (!recent.length) {
      list.replaceChildren(el('li', { class: 'cue-log__empty' }, 'No shares in this period.'));
      return;
    }
    list.replaceChildren(...recent.map((item) => el('li', {
      class: 'cue-log__item',
      style: { '--gel': item.platform ? gel(item.platform) : '#7D7AA3' },
    },
      el('span', { class: 'cue-log__lamp', title: item.platform ? item.platform.name : '' },
        icon(item.platform ? item.platform.icon : 'bi-share-fill')),
      el('div', { class: 'cue-log__body' },
        el('a', { href: safeHref(item.page.url), target: '_blank', rel: 'noopener', class: 'cue-log__title' },
          truncate(pageLabel(item.page), 64)),
        el('span', { class: 'cue-log__meta' },
          `${item.platform ? item.platform.name : 'Unknown'} · `,
          el('time', { datetime: item.created_at, title: fullDate.format(new Date(item.created_at)) }, timeAgo(item.created_at))),
      ),
    )));
  }

  function renderTable({ top_pages: pages, timeseries }) {
    const series = timeseries.series;
    document.getElementById('pages-table-head').replaceChildren(el('tr', null,
      el('th', { scope: 'col' }, 'Page'),
      ...series.map((s) => el('th', { scope: 'col', class: 'text-end' },
        el('span', { class: 'swatch', style: { 'background-color': gel(s) }, 'aria-hidden': 'true' }), ` ${s.name}`)),
      el('th', { scope: 'col', class: 'text-end' }, 'Total'),
      el('th', { scope: 'col' }, el('span', { class: 'visually-hidden' }, 'Actions')),
    ));

    const body = document.getElementById('pages-table-body');
    if (!pages.length) {
      body.replaceChildren(el('tr', null, el('td', { colspan: String(series.length + 3), class: 'text-body-secondary' }, 'No shares in this period.')));
      return;
    }
    const max = pages[0].total;
    body.replaceChildren(...pages.map((page) => el('tr', null,
      el('td', { class: 'breakdown__page' },
        el('a', { href: safeHref(page.url), target: '_blank', rel: 'noopener' }, truncate(pageLabel(page), 80)),
        el('span', { class: 'breakdown__url' }, page.url)),
      ...series.map((s) => el('td', { class: 'text-end' }, number.format(page.counts[s.slug] || 0))),
      el('td', { class: 'text-end breakdown__total' },
        el('strong', null, number.format(page.total)),
        el('span', { class: 'meter', 'aria-hidden': 'true' }, el('span', { style: { width: `${(page.total / max) * 100}%` } }))),
      el('td', { class: 'text-end' },
        el('button', { type: 'button', class: 'btn btn-sm btn-booth', onclick: () => focusPage(page) },
          icon('bi-funnel'), el('span', { class: 'visually-hidden' }, ` Show only ${pageLabel(page)}`))),
    )));
  }

  highlightQuickRange();
  load();
  setInterval(() => {
    if (!document.hidden) {
      load({ silent: true });
    }
  }, LIVE_MS);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      load({ silent: true });
    }
  });
})();
