/**
 * Public site behaviour.
 *
 * No framework, no build step. Everything degrades: with JS off, content is
 * visible, the marquee still scrolls (CSS), and the carousel is a native
 * horizontal scroller.
 */
(function () {
  'use strict';

  // Signals to CSS that JS is available, which is what allows .reveal to start
  // hidden. Without this class the reveal elements never hide, so a JS failure
  // can never leave the page blank.
  document.documentElement.classList.add('js');

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------------------------------------------------------- header state */

  const header = document.getElementById('site-header');

  if (header) {
    const onScroll = function () {
      const scrolled = window.scrollY > 24;
      header.classList.toggle('bg-ink/80', scrolled);
      header.classList.toggle('backdrop-blur-xl', scrolled);
      header.classList.toggle('border-line/60', scrolled);
      header.classList.toggle('border-transparent', !scrolled);
    };

    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* ----------------------------------------------------------- mobile nav */

  const navToggle = document.getElementById('site-nav-toggle');
  const navDrawer = document.getElementById('site-nav-drawer');

  if (navToggle && navDrawer) {
    const FOCUSABLE = 'a[href], button:not([disabled])';

    const openNav = function () {
      navDrawer.hidden = false;
      navToggle.setAttribute('aria-expanded', 'true');
      navToggle.setAttribute('aria-label', 'Close menu');
      document.body.style.overflow = 'hidden';
      const first = navDrawer.querySelector(FOCUSABLE);
      if (first) first.focus();
    };

    const closeNav = function () {
      navDrawer.hidden = true;
      navToggle.setAttribute('aria-expanded', 'false');
      navToggle.setAttribute('aria-label', 'Open menu');
      document.body.style.overflow = '';
      navToggle.focus();
    };

    navToggle.addEventListener('click', function () {
      navDrawer.hidden ? openNav() : closeNav();
    });

    navDrawer.addEventListener('click', function (event) {
      if (event.target.hasAttribute('data-close-nav')) closeNav();
    });

    document.addEventListener('keydown', function (event) {
      if (navDrawer.hidden) return;

      if (event.key === 'Escape') {
        closeNav();
        return;
      }

      if (event.key !== 'Tab') return;

      // Trap focus inside the open drawer, or a keyboard user tabs into the page
      // hidden behind the overlay.
      const items = Array.prototype.slice.call(navDrawer.querySelectorAll(FOCUSABLE));
      if (items.length === 0) return;

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
  }

  /* -------------------------------------------------------- scroll reveal */

  const revealables = document.querySelectorAll('.reveal');

  if (revealables.length > 0) {
    if (reduceMotion || !('IntersectionObserver' in window)) {
      revealables.forEach(function (el) { el.classList.add('is-visible'); });
    } else {
      const observer = new IntersectionObserver(
        function (entries) {
          entries.forEach(function (entry, index) {
            if (!entry.isIntersecting) return;

            // Small stagger so a row of cards arrives in sequence rather than
            // snapping in as one block.
            const delay = Math.min(index * 60, 240);
            window.setTimeout(function () {
              entry.target.classList.add('is-visible');
            }, delay);

            observer.unobserve(entry.target);
          });
        },
        { rootMargin: '0px 0px -12% 0px', threshold: 0.1 }
      );

      revealables.forEach(function (el) { observer.observe(el); });
    }
  }

  /* -------------------------------------------------------------- marquee */

  document.querySelectorAll('[data-marquee]').forEach(function (marquee) {
    const track = marquee.querySelector('.marquee-track');
    if (!track) return;

    // Duration from content width, so the logos always travel at the same speed
    // regardless of how many the CMS is holding.
    const width = track.scrollWidth;
    const seconds = Math.max(18, Math.round(width / 45));
    marquee.style.setProperty('--marquee-duration', seconds + 's');
  });

  /* ------------------------------------------------------------- carousel */

  document.querySelectorAll('[data-slider]').forEach(function (slider) {
    const track = slider.querySelector('[data-slider-track]');
    const prev = slider.querySelector('[data-slider-prev]');
    const next = slider.querySelector('[data-slider-next]');

    if (!track) return;

    const step = function () {
      const item = track.querySelector('[data-slider-item]');
      return item ? item.getBoundingClientRect().width + 24 : track.clientWidth * 0.8;
    };

    if (prev) {
      prev.addEventListener('click', function () {
        track.scrollBy({ left: -step(), behavior: reduceMotion ? 'auto' : 'smooth' });
      });
    }

    if (next) {
      next.addEventListener('click', function () {
        track.scrollBy({ left: step(), behavior: reduceMotion ? 'auto' : 'smooth' });
      });
    }

    // Disable the arrows at each end rather than leaving them looking live.
    const updateArrows = function () {
      const atStart = track.scrollLeft < 8;
      const atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 8;

      if (prev) prev.disabled = atStart;
      if (next) next.disabled = atEnd;
    };

    updateArrows();
    track.addEventListener('scroll', updateArrows, { passive: true });
    window.addEventListener('resize', updateArrows);
  });

  /* ----------------------------------------------------------- hero video */

  /**
   * The video is deliberately the last thing to happen.
   *
   * It is attached only after the page has fully loaded (so it never competes
   * with the LCP element), only above 1024px, never under reduced motion, and
   * never on a connection the browser reports as metered or slow. On mobile the
   * CSS motion layer is the whole effect — which is what keeps the mobile
   * Lighthouse score intact.
   */
  const heroVideo = document.querySelector('[data-hero-video]');

  if (heroVideo) {
    const connection = navigator.connection || {};
    const slowConnection = connection.saveData === true ||
      /(^|-)2g$/.test(connection.effectiveType || '');

    const shouldPlay = !reduceMotion &&
      !slowConnection &&
      window.matchMedia('(min-width: 1024px)').matches;

    if (shouldPlay) {
      window.addEventListener('load', function () {
        window.setTimeout(function () {
          heroVideo.src = heroVideo.dataset.src;
          heroVideo.load();

          const play = heroVideo.play();

          if (play && typeof play.catch === 'function') {
            // Autoplay refusal is normal and not an error worth surfacing; the
            // CSS motion layer is already carrying the hero.
            play.catch(function () {});
          }

          heroVideo.addEventListener('playing', function () {
            heroVideo.classList.add('is-ready');
          }, { once: true });
        }, 600);
      });
    }
  }
})();
