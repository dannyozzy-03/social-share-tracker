/* ES5 on purpose so tracking also runs in older browsers. */
(function () {
  'use strict';

  if (!document.querySelectorAll || !window.JSON) {
    return;
  }

  function closest(el, selector) {
    while (el && el.nodeType === 1) {
      if ((el.matches || el.msMatchesSelector || el.webkitMatchesSelector).call(el, selector)) {
        return el;
      }
      el = el.parentNode;
    }
    return null;
  }

  // keepalive lets the request finish even if the page navigates away (e.g. mailto:).
  function track(endpoint, payload, onDone) {
    var body = JSON.stringify(payload);
    var done = function (response) {
      if (onDone && response && response.ok) {
        onDone(response);
      }
    };

    if (window.fetch) {
      window.fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: body,
        credentials: 'same-origin',
        keepalive: true
      }).then(function (res) {
        return res.json();
      }).then(done)['catch'](function () { /* tracking must never block sharing */ });
      return;
    }

    if (navigator.sendBeacon && window.Blob) {
      navigator.sendBeacon(endpoint, new Blob([body], { type: 'application/json' }));
      return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', endpoint, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.onreadystatechange = function () {
      if (xhr.readyState === 4) {
        try { done(JSON.parse(xhr.responseText)); } catch (e) { /* ignore */ }
      }
    };
    xhr.send(body);
  }

  function replayClass(el, name, duration) {
    var pattern = new RegExp('\\s*\\b' + name + '\\b', 'g');
    el.className = el.className.replace(pattern, '');
    void el.offsetWidth;
    el.className += ' ' + name;
    window.setTimeout(function () {
      el.className = el.className.replace(pattern, '');
    }, duration);
  }

  function formatNumber(n) {
    return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function updateCounts(url, total) {
    var bars = document.querySelectorAll('[data-share-bar]');
    for (var i = 0; i < bars.length; i++) {
      if (bars[i].getAttribute('data-url') !== url) {
        continue;
      }
      var count = bars[i].querySelector('[data-share-count]');
      var noun = bars[i].querySelector('[data-share-noun]');
      if (count && count.textContent !== formatNumber(total)) {
        count.textContent = formatNumber(total);
        replayClass(count, 'is-stepping', 450);
      }
      if (noun) { noun.textContent = total === 1 ? 'share' : 'shares'; }
    }
    var totals = document.querySelectorAll('[data-share-total]');
    for (var j = 0; j < totals.length; j++) {
      var label = totals[j].querySelector('[data-share-label]');
      var text = formatNumber(total) + (total === 1 ? ' share' : ' shares');
      if (totals[j].getAttribute('data-url') === url && label && label.textContent !== text) {
        label.textContent = text;
        replayClass(totals[j], 'is-stepping', 450);
      }
    }
  }

  // Poll so shares made by other readers appear without a reload.
  var POLL_MS = 10000;
  var countsEndpoint = document.body.getAttribute('data-counts-endpoint');

  function trackedUrls() {
    var nodes = document.querySelectorAll('[data-share-bar][data-url], [data-share-total][data-url]');
    var urls = [];
    for (var i = 0; i < nodes.length; i++) {
      var url = nodes[i].getAttribute('data-url');
      if (urls.indexOf(url) === -1) { urls.push(url); }
    }
    return urls;
  }

  function refreshCounts() {
    var urls = trackedUrls();
    if (!countsEndpoint || !urls.length || document.hidden) {
      return;
    }
    var query = [];
    for (var i = 0; i < urls.length; i++) { query.push('url[]=' + encodeURIComponent(urls[i])); }
    var xhr = new XMLHttpRequest();
    xhr.open('GET', countsEndpoint + '?' + query.join('&'), true);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.onload = function () {
      var body = null;
      try { body = JSON.parse(xhr.responseText); } catch (e) { return; }
      if (xhr.status !== 200 || !body || !body.ok) { return; }
      for (var url in body.counts) {
        if (Object.prototype.hasOwnProperty.call(body.counts, url)) { updateCounts(url, body.counts[url]); }
      }
    };
    xhr.send();
  }

  if (countsEndpoint) {
    window.setInterval(refreshCounts, POLL_MS);
    document.addEventListener('visibilitychange', refreshCounts);
  }

  function openShareWindow(href, platform) {
    // On small screens a new tab (or the native app) is a better experience than a popup.
    if (window.innerWidth < 768) {
      return window.open(href, '_blank');
    }
    var width = 600;
    var height = 560;
    var left = Math.max(0, (window.screenX || window.screenLeft || 0) + (window.outerWidth - width) / 2);
    var top = Math.max(0, (window.screenY || window.screenTop || 0) + (window.outerHeight - height) / 2);
    return window.open(href, 'share-' + platform,
      'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes');
  }

  document.addEventListener('click', function (event) {
    var button = closest(event.target, '[data-platform]');
    var bar = button && closest(button, '[data-share-bar]');
    if (!bar || event.defaultPrevented) {
      return;
    }
    // Ctrl/Cmd/Shift-clicks are still tracked, but the browser opens the link as the user asked.
    var modified = event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey;

    var platform = button.getAttribute('data-platform');
    var url = bar.getAttribute('data-url');

    track(bar.getAttribute('data-endpoint'), {
      platform: platform,
      url: url,
      title: bar.getAttribute('data-title') || document.title
    }, function (response) {
      updateCounts(url, response.total);
    });

    replayClass(button, 'is-cued', 900);

    if (!modified && button.hasAttribute('data-popup')) {
      // Open synchronously inside the click handler so pop-up blockers allow it.
      var win = openShareWindow(button.href, platform);
      if (win) {
        try { win.opener = null; } catch (e) { /* cross-origin */ }
        event.preventDefault();
      }
      // If the window was blocked, the link's default target="_blank" behaviour takes over.
    }
  });

  // The dock steps aside while either in-page share bar (under the headline, or after the story) is on screen.
  var dock = document.querySelector('.dock');
  var inlineBars = document.querySelectorAll('.story__head [data-share-bar], .story__share');
  if (dock && inlineBars.length && window.IntersectionObserver) {
    var visible = [];
    var observer = new window.IntersectionObserver(function (entries) {
      for (var i = 0; i < entries.length; i++) {
        var at = visible.indexOf(entries[i].target);
        if (entries[i].isIntersecting && at === -1) { visible.push(entries[i].target); }
        if (!entries[i].isIntersecting && at !== -1) { visible.splice(at, 1); }
      }
      dock.className = dock.className.replace(/\s*\bis-parked\b/g, '') + (visible.length ? ' is-parked' : '');
    });
    for (var b = 0; b < inlineBars.length; b++) { observer.observe(inlineBars[b]); }
  }
})();
