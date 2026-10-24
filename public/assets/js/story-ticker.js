(() => {
  'use strict';

  const ticker = document.querySelector('[data-story-ticker]');
  if (!ticker) {
    return;
  }

  const viewport = ticker.querySelector('[data-story-viewport]');
  const track = ticker.querySelector('.strip__track');
  if (!viewport || !track) {
    return;
  }
  const storyList = track.querySelector('.strip__list:not(.strip__list--clone)');
  if (!storyList) {
    return;
  }

  const updateTickerSize = () => {
    const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
    const distance = storyList.getBoundingClientRect().width + gap;
    track.style.setProperty('--ticker-loop-distance', `${distance}px`);
    track.style.setProperty('--ticker-duration', `${Math.max(30, distance / 55)}s`);
  };

  updateTickerSize();
  if ('ResizeObserver' in window) {
    new ResizeObserver(updateTickerSize).observe(storyList);
  } else {
    window.addEventListener('resize', updateTickerSize);
  }

  let drag = null;
  let suppressClick = false;

  viewport.addEventListener('pointerenter', () => {
    viewport.dataset.hovered = 'true';
  });
  viewport.addEventListener('pointerleave', () => {
    viewport.dataset.hovered = 'false';
  });

  viewport.addEventListener('pointerdown', (event) => {
    if (event.pointerType !== 'mouse' || event.button !== 0) {
      return;
    }
    drag = { pointerId: event.pointerId, startX: event.clientX, startScroll: viewport.scrollLeft, moved: false };
  });

  window.addEventListener('pointermove', (event) => {
    if (!drag || event.pointerId !== drag.pointerId) {
      return;
    }
    const distance = event.clientX - drag.startX;
    if (Math.abs(distance) > 4) {
      drag.moved = true;
    }
    if (drag.moved) {
      viewport.classList.add('is-dragging');
      if (!viewport.hasPointerCapture(event.pointerId)) {
        viewport.setPointerCapture(event.pointerId);
      }
      viewport.scrollLeft = drag.startScroll - distance;
    }
  });

  const finishDrag = (event) => {
    if (!drag || event.pointerId !== drag.pointerId) {
      return;
    }
    if (drag.moved) {
      suppressClick = true;
      window.setTimeout(() => { suppressClick = false; }, 0);
    }
    drag = null;
    viewport.classList.remove('is-dragging');
  };

  window.addEventListener('pointerup', finishDrag);
  window.addEventListener('pointercancel', finishDrag);
  viewport.addEventListener('lostpointercapture', finishDrag);
  viewport.addEventListener('click', (event) => {
    if (suppressClick) {
      event.preventDefault();
      event.stopPropagation();
    }
  }, true);

  document.addEventListener('visibilitychange', () => {
    ticker.dataset.hidden = String(document.hidden);
  });

})();
