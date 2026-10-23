/* Loaded in <head> without defer so the saved theme applies before first paint. Light is the default. */
(function () {
  'use strict';

  var root = document.documentElement;
  var KEY = 'ajaklah-theme';

  function saved() {
    try {
      return localStorage.getItem(KEY) === 'dark' ? 'dark' : 'light';
    } catch (e) {
      return 'light';
    }
  }

  function apply(theme) {
    root.setAttribute('data-theme', theme);
    if (root.hasAttribute('data-bs-theme')) {
      root.setAttribute('data-bs-theme', theme);
    }
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
      meta.setAttribute('content', theme === 'dark' ? '#0D0F17' : '#FFFFFF');
    }
    var buttons = document.querySelectorAll('[data-theme-toggle]');
    for (var i = 0; i < buttons.length; i++) {
      buttons[i].setAttribute('aria-pressed', String(theme === 'dark'));
      buttons[i].title = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
    }
  }

  apply(saved());

  document.addEventListener('DOMContentLoaded', function () {
    apply(root.getAttribute('data-theme'));
  });

  document.addEventListener('click', function (event) {
    var button = event.target.closest ? event.target.closest('[data-theme-toggle]') : null;
    if (!button) {
      return;
    }
    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    try {
      localStorage.setItem(KEY, next);
    } catch (e) {}
    apply(next);
    document.dispatchEvent(new CustomEvent('themechange', { detail: next }));
  });
})();
