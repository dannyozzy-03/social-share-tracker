(() => {
  'use strict';

  const eventKey = 'sst-admin-session-event';
  const statusEndpoint = document.body.dataset.adminSessionEndpoint;

  function updateLinks(signedIn) {
    document.querySelectorAll('[data-admin-session-link]').forEach((link) => {
      link.querySelectorAll('[data-admin-session-guest]').forEach((label) => {
        label.hidden = signedIn;
      });
      link.querySelectorAll('[data-admin-session-user]').forEach((label) => {
        label.hidden = !signedIn;
      });

      const icon = link.querySelector('[data-admin-session-icon]');
      if (icon) {
        icon.className = `bi ${signedIn ? 'bi-speedometer2' : 'bi-person-lock'}`;
      }
      link.setAttribute('aria-label', signedIn ? 'Admin dashboard' : 'Admin sign in');
    });
  }

  function publish(state) {
    try {
      localStorage.setItem(eventKey, JSON.stringify({ state, timestamp: Date.now() }));
    } catch (error) {
      console.warn('Unable to sync admin status across tabs.', error);
    }
  }

  async function refreshStatus() {
    try {
      const response = await fetch(statusEndpoint, {
        cache: 'no-store',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      if (!response.ok) {
        throw new Error(`Session status request failed: HTTP ${response.status}`);
      }
      const status = await response.json();
      if (typeof status.authenticated !== 'boolean') {
        throw new Error('Session status response was invalid.');
      }
      updateLinks(status.authenticated);
    } catch (error) {
      console.warn('Unable to refresh admin sign-in status.', error);
    }
  }

  window.addEventListener('storage', (event) => {
    if (event.key !== eventKey || event.newValue === null) {
      return;
    }

    try {
      const message = JSON.parse(event.newValue);
      if (message.state === 'signed-in' || message.state === 'signed-out') {
        updateLinks(message.state === 'signed-in');
      }
    } catch (error) {
      console.warn('Unable to read admin status from another tab.', error);
    }
  });

  const sessionEvent = document.body.dataset.adminSessionEvent;
  if (sessionEvent === 'signed-in' || sessionEvent === 'signed-out') {
    publish(sessionEvent);
  }

  if (statusEndpoint) {
    refreshStatus();
    window.setInterval(refreshStatus, 5000);
    window.addEventListener('focus', refreshStatus);
  }
})();
