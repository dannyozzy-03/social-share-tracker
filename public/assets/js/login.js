(() => {
  'use strict';

  const form = document.getElementById('login-form');
  const password = document.getElementById('password');
  const toggle = document.getElementById('toggle-password');
  const caps = document.getElementById('caps-warning');
  const submit = document.getElementById('login-submit');
  if (!form || !password || !toggle) {
    return;
  }

  toggle.hidden = false;
  toggle.addEventListener('click', () => {
    const show = password.type === 'password';
    password.type = show ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', String(show));
    toggle.querySelector('.bi').className = `bi ${show ? 'bi-eye-slash' : 'bi-eye'}`;
    toggle.querySelector('.login-reveal__text').textContent = show ? 'Hide' : 'Show';
    password.focus();
  });

  const checkCaps = (event) => {
    if (typeof event.getModifierState === 'function') {
      caps.hidden = !event.getModifierState('CapsLock');
    }
  };
  password.addEventListener('keydown', checkCaps);
  password.addEventListener('keyup', checkCaps);
  password.addEventListener('blur', () => { caps.hidden = true; });

  form.querySelectorAll('.is-invalid').forEach((input) => {
    input.addEventListener('input', () => {
      input.classList.remove('is-invalid');
      input.removeAttribute('aria-invalid');
    }, { once: true });
  });

  form.addEventListener('submit', () => {
    password.type = 'password';
    submit.disabled = true;
    submit.querySelector('.login-submit__label').hidden = true;
    submit.querySelector('.login-submit__busy').hidden = false;
  });
})();
