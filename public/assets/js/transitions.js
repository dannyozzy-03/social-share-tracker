/*
 * Loaded in <head> without defer so the pagereveal listener exists before the first frame.
 * The page fade itself is pure CSS (@view-transition in theme.css). This script only names the
 * story's cover image for the length of one transition, so it morphs from the front-page card
 * into the article hero. Naming covers permanently would lift them above the overlapping text
 * panels, because a view-transition-name gives an element its own paint layer.
 */
(function () {
  'use strict';

  function slugOf(url) {
    try {
      return new URL(url).searchParams.get('slug');
    } catch (e) {
      return null;
    }
  }

  function nameCover(slug, transition) {
    if (!slug || !transition) {
      return;
    }
    var images = document.querySelectorAll('img[data-cover]');
    for (var i = 0; i < images.length; i++) {
      var img = images[i];
      var box = img.getBoundingClientRect();
      // Only a cover that is on screen; a name on an off-screen image would fly in from nowhere.
      if (img.getAttribute('data-cover') === slug && box.bottom > 0 && box.top < window.innerHeight) {
        img.style.viewTransitionName = 'cover';
        transition.finished.then(function () { img.style.viewTransitionName = ''; }, function () { img.style.viewTransitionName = ''; });
        return;
      }
    }
  }

  // Leaving this page: name the cover of the story being opened.
  window.addEventListener('pageswap', function (event) {
    if (event.viewTransition && event.activation && event.activation.entry) {
      nameCover(slugOf(event.activation.entry.url), event.viewTransition);
    }
  });

  // Arriving on an article: name its hero so it pairs with the cover named above.
  window.addEventListener('pagereveal', function (event) {
    if (event.viewTransition) {
      nameCover(slugOf(window.location.href), event.viewTransition);
    }
  });
})();
