/*
 * Searchable page picker for the dashboard filters (ARIA 1.2 combobox + listbox).
 *
 * A plain <select> has to ship every page in the HTML, which stops being usable once a site
 * has hundreds of articles. This picker asks the server for 20 matches at a time
 * (admin/api/pages.php), loads more as the list is scrolled, and keeps the chosen page ID in
 * a hidden <input name="page"> so the rest of the form works unchanged.
 */
(() => {
  'use strict';

  const PAGE_SIZE = 20;
  const DEBOUNCE_MS = 200;
  const MAX_QUERY = 100;
  const number = new Intl.NumberFormat('en-MY');

  function labelFor(page) {
    return page.title || page.url.replace(/^https?:\/\//i, '');
  }

  function escapeRegExp(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  // Wraps each search word in <mark>, using text nodes only, so page titles can never inject HTML.
  function highlight(text, terms) {
    const fragment = document.createDocumentFragment();
    if (!terms.length) {
      fragment.append(text);
      return fragment;
    }
    const pattern = new RegExp(`(${terms.map(escapeRegExp).join('|')})`, 'gi');
    text.split(pattern).forEach((part, i) => {
      if (part === '') {
        return;
      }
      if (i % 2 === 1) {
        const mark = document.createElement('mark');
        mark.textContent = part;
        fragment.append(mark);
      } else {
        fragment.append(part);
      }
    });
    return fragment;
  }

  function create(root, { onChange }) {
    const input = root.querySelector('[role="combobox"]');
    const hidden = root.querySelector('input[type="hidden"]');
    const popup = root.querySelector('[data-picker-popup]');
    const list = root.querySelector('[role="listbox"]');
    const foot = root.querySelector('[data-picker-foot]');
    const clearButton = root.querySelector('[data-picker-clear]');
    const status = root.querySelector('[data-picker-status]');
    const endpoint = root.dataset.endpoint;

    let selected = hidden.value ? { id: Number(hidden.value), label: input.value } : null;
    let query = '';
    let terms = [];
    let pages = [];
    let nextOffset = null;
    let loading = false;
    let failed = false;
    let activeIndex = -1;
    let controller = null;
    let timer = null;

    const isOpen = () => !popup.hidden;
    const showAllOption = () => query === '';
    // Option 0 is "All pages" while the search box is empty; after that, one option per page.
    const optionCount = () => pages.length + (showAllOption() ? 1 : 0);
    const pageAt = (index) => (showAllOption() ? (index === 0 ? null : pages[index - 1]) : pages[index]);

    function announce(message) {
      status.textContent = message;
    }

    function setActive(index, { scroll = true } = {}) {
      activeIndex = index;
      const options = list.querySelectorAll('[role="option"]');
      options.forEach((option, i) => {
        const active = i === index;
        option.classList.toggle('is-active', active);
        option.setAttribute('aria-selected', String(active));
        if (active && scroll) {
          option.scrollIntoView({ block: 'nearest' });
        }
      });
      if (index >= 0 && options[index]) {
        input.setAttribute('aria-activedescendant', options[index].id);
      } else {
        input.removeAttribute('aria-activedescendant');
      }
    }

    function renderOption(page, index) {
      const isAll = page === null;
      const current = isAll ? selected === null : selected !== null && selected.id === page.id;
      const option = document.createElement('li');
      option.id = isAll ? 'page-option-all' : `page-option-${page.id}`;
      option.className = `picker__option${current ? ' is-current' : ''}${isAll ? ' picker__option--all' : ''}`;
      option.setAttribute('role', 'option');
      option.setAttribute('aria-selected', 'false');
      option.dataset.index = String(index);

      const check = document.createElement('i');
      check.className = 'bi bi-check2 picker__check';
      check.setAttribute('aria-hidden', 'true');

      const text = document.createElement('span');
      text.className = 'picker__text';
      const title = document.createElement('span');
      title.className = 'picker__title';
      if (isAll) {
        title.textContent = 'All pages';
        text.append(title);
      } else {
        title.append(highlight(labelFor(page), terms));
        const address = document.createElement('span');
        address.className = 'picker__url';
        address.append(highlight(page.url.replace(/^https?:\/\//i, ''), terms));
        text.append(title, address);
      }

      if (current) {
        const note = document.createElement('span');
        note.className = 'visually-hidden';
        note.textContent = ' (current filter)';
        title.append(note);
      }
      option.append(check, text);
      if (!isAll) {
        const shares = document.createElement('span');
        shares.className = 'picker__shares';
        shares.textContent = `${number.format(page.shares)} ${page.shares === 1 ? 'share' : 'shares'}`;
        option.append(shares);
      }
      return option;
    }

    function renderFoot() {
      foot.replaceChildren();
      let message = '';
      if (loading) {
        message = pages.length ? 'Loading more pages…' : 'Searching…';
      } else if (failed) {
        message = 'Could not load pages.';
      } else if (!pages.length) {
        message = query ? `No pages match “${query}”.` : 'No pages have been shared yet.';
      } else if (nextOffset !== null) {
        message = `Showing ${number.format(pages.length)} pages.`;
      } else if (pages.length > PAGE_SIZE || query) {
        message = `${number.format(pages.length)} ${pages.length === 1 ? 'page' : 'pages'}${query ? ' found' : ''}.`;
      }
      if (message) {
        const text = document.createElement('span');
        text.textContent = message;
        foot.append(text);
      }
      if (!loading && (failed || nextOffset !== null)) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-link btn-sm picker__more';
        button.textContent = failed ? 'Try again' : 'Show more';
        // Keep focus in the search box, so the list stays open and the keyboard keeps working.
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => (failed && !pages.length ? search(query) : loadMore()));
        foot.append(button);
      }
      foot.hidden = foot.childNodes.length === 0;
    }

    function render() {
      const options = [];
      for (let i = 0; i < optionCount(); i++) {
        options.push(renderOption(pageAt(i), i));
      }
      list.replaceChildren(...options);
      renderFoot();
      setActive(Math.min(activeIndex, optionCount() - 1), { scroll: false });
    }

    async function fetchPages(offset) {
      if (controller) {
        controller.abort();
      }
      controller = new AbortController();
      const own = controller;
      loading = true;
      failed = false;
      renderFoot();

      const params = new URLSearchParams({ q: query, limit: String(PAGE_SIZE), offset: String(offset) });
      try {
        const response = await fetch(`${endpoint}?${params}`, {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
          signal: own.signal,
        });
        if (response.status === 401) {
          window.location.href = `${root.dataset.loginUrl}?expired=1`;
          return;
        }
        const body = await response.json();
        if (!response.ok || !body.ok) {
          throw new Error((body.errors && Object.values(body.errors).join(' ')) || body.error || `HTTP ${response.status}`);
        }
        if (own !== controller) {
          return; // a newer search replaced this one
        }
        const seen = new Set(offset === 0 ? [] : pages.map((page) => page.id));
        const fresh = body.pages.filter((page) => !seen.has(page.id));
        pages = offset === 0 ? fresh : pages.concat(fresh);
        nextOffset = body.next_offset;
        loading = false;
        if (offset === 0) {
          // With a search, the top match is ready for Enter; otherwise start on the current choice.
          const currentIndex = selected === null ? 0 : pages.findIndex((page) => page.id === selected.id) + 1;
          activeIndex = query ? (pages.length ? 0 : -1) : Math.max(0, currentIndex);
          list.scrollTop = 0;
        }
        render();
        announce(pages.length
          ? `${number.format(pages.length)} ${pages.length === 1 ? 'page' : 'pages'}${nextOffset !== null ? ', more available' : ''}.`
          : (query ? 'No matching pages.' : 'No pages have been shared yet.'));
      } catch (error) {
        if (error.name === 'AbortError') {
          return;
        }
        if (own === controller) {
          loading = false;
          failed = true;
          renderFoot();
          announce('Could not load pages.');
        }
        console.warn('Page search failed.', error);
      } finally {
        if (own === controller) {
          controller = null;
          loading = false;
        }
      }
    }

    function search(text) {
      query = text.trim().replace(/\s+/g, ' ').slice(0, MAX_QUERY);
      terms = query ? Array.from(new Set(query.toLowerCase().split(' '))) : [];
      nextOffset = null;
      fetchPages(0);
    }

    function loadMore() {
      if (nextOffset !== null && !loading) {
        fetchPages(nextOffset);
      }
    }

    function open() {
      if (isOpen()) {
        return;
      }
      popup.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      root.classList.add('is-open');
      // The box shows the chosen page's name; that is not a search, so start from the full list.
      search(selected !== null && input.value === selected.label ? '' : input.value);
    }

    function close({ restore = true } = {}) {
      clearTimeout(timer);
      if (controller) {
        controller.abort();
        controller = null;
      }
      loading = false;
      popup.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      root.classList.remove('is-open');
      activeIndex = -1;
      if (restore) {
        input.value = selected ? selected.label : '';
      }
    }

    function show(page) {
      selected = page ? { id: page.id, label: labelFor(page) } : null;
      hidden.value = selected ? String(selected.id) : '';
      input.value = selected ? selected.label : '';
      clearButton.hidden = selected === null;
    }

    function choose(index) {
      const page = pageAt(index);
      const changed = (page ? page.id : null) !== (selected ? selected.id : null);
      show(page);
      close({ restore: false });
      announce(page ? `Showing ${labelFor(page)}.` : 'Showing all pages.');
      if (changed) {
        onChange(selected);
      }
    }

    input.addEventListener('focus', () => input.select());
    input.addEventListener('click', open);
    input.addEventListener('input', () => {
      if (!isOpen()) {
        open();
        return;
      }
      clearTimeout(timer);
      timer = setTimeout(() => search(input.value), DEBOUNCE_MS);
    });
    // The form reloads the report on "change"; typing a search term is not a filter change.
    input.addEventListener('change', (event) => event.stopPropagation());

    input.addEventListener('keydown', (event) => {
      const count = optionCount();
      switch (event.key) {
        case 'ArrowDown':
          event.preventDefault();
          if (!isOpen()) {
            open();
          } else if (activeIndex < count - 1) {
            setActive(activeIndex + 1);
            if (activeIndex >= count - 3) {
              loadMore();
            }
          } else {
            loadMore();
          }
          break;
        case 'ArrowUp':
          event.preventDefault();
          if (isOpen() && activeIndex > 0) {
            setActive(activeIndex - 1);
          }
          break;
        case 'PageDown':
        case 'PageUp':
          if (isOpen() && count) {
            event.preventDefault();
            const step = event.key === 'PageDown' ? 8 : -8;
            setActive(Math.max(0, Math.min(count - 1, activeIndex + step)));
            if (activeIndex >= count - 3) {
              loadMore();
            }
          }
          break;
        case 'Enter':
          if (isOpen()) {
            event.preventDefault(); // choosing a page must not submit the filter form
            if (activeIndex >= 0 && activeIndex < count) {
              choose(activeIndex);
            }
          }
          break;
        case 'Escape':
          if (isOpen()) {
            event.preventDefault();
            close();
          } else if (input.value !== '' && selected === null) {
            input.value = '';
          }
          break;
        case 'Tab':
          if (isOpen()) {
            close();
          }
          break;
        default:
      }
    });

    // mousedown, not click: choosing must happen before the input loses focus and closes the list.
    list.addEventListener('mousedown', (event) => {
      const option = event.target.closest('[role="option"]');
      event.preventDefault();
      if (option) {
        choose(Number(option.dataset.index));
      }
    });
    list.addEventListener('mousemove', (event) => {
      const option = event.target.closest('[role="option"]');
      if (option && Number(option.dataset.index) !== activeIndex) {
        setActive(Number(option.dataset.index), { scroll: false });
      }
    });
    list.addEventListener('scroll', () => {
      if (list.scrollTop + list.clientHeight >= list.scrollHeight - 48) {
        loadMore();
      }
    }, { passive: true });

    clearButton.addEventListener('click', () => {
      const changed = selected !== null;
      show(null);
      close({ restore: false });
      input.focus();
      announce('Showing all pages.');
      if (changed) {
        onChange(null);
      }
    });

    root.addEventListener('focusout', (event) => {
      if (isOpen() && !root.contains(event.relatedTarget)) {
        close();
      }
    });
    document.addEventListener('pointerdown', (event) => {
      if (isOpen() && !root.contains(event.target)) {
        close();
      }
    });

    return {
      /** Selects a page ({id, title, url}) or null for all pages, without calling onChange. */
      set(page) {
        show(page);
        if (isOpen()) {
          close({ restore: false });
        }
      },
      input,
    };
  }

  window.PagePicker = { create, labelFor };
})();
