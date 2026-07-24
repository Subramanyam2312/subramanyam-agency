/**
 * Admin portal behaviour. No framework, no build step — this file ships as written.
 */
(function () {
  'use strict';

  const toggle = document.getElementById('nav-toggle');
  const drawer = document.getElementById('mobile-nav');

  if (!toggle || !drawer) {
    return;
  }

  /** Elements that can hold focus, used to trap Tab inside the open drawer. */
  const FOCUSABLE = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

  function openDrawer() {
    drawer.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    document.body.classList.add('overflow-hidden');

    const first = drawer.querySelector(FOCUSABLE);
    if (first) {
      first.focus();
    }
  }

  function closeDrawer() {
    drawer.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('overflow-hidden');
    toggle.focus();
  }

  toggle.addEventListener('click', function () {
    if (drawer.hidden) {
      openDrawer();
    } else {
      closeDrawer();
    }
  });

  drawer.addEventListener('click', function (event) {
    if (event.target.hasAttribute('data-close-nav')) {
      closeDrawer();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (drawer.hidden) {
      return;
    }

    if (event.key === 'Escape') {
      closeDrawer();
      return;
    }

    if (event.key !== 'Tab') {
      return;
    }

    // Keep focus inside the drawer while it is open, or a keyboard user tabs
    // straight into the page behind the overlay.
    const items = Array.from(drawer.querySelectorAll(FOCUSABLE));
    if (items.length === 0) {
      return;
    }

    const first = items[0];
    const last = items[items.length - 1];

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  // Close the drawer if the viewport grows past the breakpoint while it is open.
  window.matchMedia('(min-width: 1024px)').addEventListener('change', function (event) {
    if (event.matches && !drawer.hidden) {
      closeDrawer();
    }
  });
})();
