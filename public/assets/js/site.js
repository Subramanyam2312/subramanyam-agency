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

  /* --------------------------------------------------- back to top button */

  const backToTop = document.getElementById('back-to-top');

  if (backToTop) {
    const toggleBackToTop = function () {
      const show = window.scrollY > window.innerHeight * 0.6;
      backToTop.classList.toggle('hidden', !show);
      backToTop.classList.toggle('flex', show);
    };

    toggleBackToTop();
    window.addEventListener('scroll', toggleBackToTop, { passive: true });

    backToTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    });
  }

  /* ------------------------------------------------------ scroll progress */

  const progress = document.getElementById('scroll-progress');

  if (progress) {
    let ticking = false;

    const updateProgress = function () {
      const max = document.documentElement.scrollHeight - window.innerHeight;
      const ratio = max > 0 ? Math.min(1, window.scrollY / max) : 0;
      progress.style.transform = 'scaleX(' + ratio + ')';
      ticking = false;
    };

    // rAF-throttled: the scroll handler fires far more often than the screen
    // repaints, and writing a transform on every event is wasted layout work.
    window.addEventListener('scroll', function () {
      if (!ticking) {
        window.requestAnimationFrame(updateProgress);
        ticking = true;
      }
    }, { passive: true });

    updateProgress();
  }

  /* ------------------------------------------------------ hero parallax */

  const heroMotion = document.querySelector('.hero-motion');

  if (heroMotion && !reduceMotion) {
    let parallaxTicking = false;

    const updateParallax = function () {
      const offset = Math.min(window.scrollY, window.innerHeight) * 0.18;
      heroMotion.style.transform = 'translate3d(0,' + offset + 'px,0)';
      parallaxTicking = false;
    };

    window.addEventListener('scroll', function () {
      if (!parallaxTicking) {
        window.requestAnimationFrame(updateParallax);
        parallaxTicking = true;
      }
    }, { passive: true });
  }

  /* --------------------------------------------------- split-word headings */

  /**
   * Wraps each word of a heading so it can rise into place behind a clip.
   *
   * Done in JS rather than in the templates because the source text comes from
   * the CMS: an editor should be able to type a headline without knowing it will
   * be sliced into spans. The text content is preserved exactly, so screen
   * readers and search engines still see one continuous string.
   */
  if (!reduceMotion) {
    document.querySelectorAll('[data-split]').forEach(function (heading) {
      const words = heading.textContent.trim().split(/\s+/);

      heading.textContent = '';

      words.forEach(function (word, index) {
        const outer = document.createElement('span');
        outer.className = 'word';

        const inner = document.createElement('span');
        inner.textContent = word;
        // Stagger, capped so a long headline does not crawl in for two seconds.
        inner.style.transitionDelay = Math.min(index * 45, 400) + 'ms';

        outer.appendChild(inner);
        heading.appendChild(outer);

        if (index < words.length - 1) {
          heading.appendChild(document.createTextNode(' '));
        }
      });
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

  /* ---------------------------------------------------------- 3D card tilt */

  /**
   * Pointer-driven perspective tilt on cards. The card leans toward the cursor in
   * real 3D (rotateX/rotateY under a perspective parent) and lifts slightly on the
   * Z axis, so a grid of cards responds to the mouse with depth.
   *
   * Disabled entirely under reduced motion and on touch pointers — a tilt that
   * fires on tap is just a flicker. Uses rAF so a fast mouse cannot queue more
   * transform writes than the screen can paint.
   */
  (function initTilt() {
    if (reduceMotion) {
      return;
    }

    var MAX = 7; // degrees; past this it stops looking like paper and starts looking broken

    document.querySelectorAll('[data-tilt]').forEach(function (card) {
      var frame = null;

      // Perspective is baked into the card's own transform rather than set on a
      // parent. That keeps each card self-contained and, crucially, immune to the
      // overflow:hidden on the surrounding hairline grid — an ancestor perspective
      // gets flattened by that clip, a per-element one does not.
      card.classList.add('tilt');

      function onMove(event) {
        if (event.pointerType === 'touch') {
          return;
        }
        if (frame) {
          return;
        }
        frame = window.requestAnimationFrame(function () {
          frame = null;
          var rect = card.getBoundingClientRect();
          var px = (event.clientX - rect.left) / rect.width;   // 0..1
          var py = (event.clientY - rect.top) / rect.height;
          var ry = (px - 0.5) * 2 * MAX;
          var rx = (0.5 - py) * 2 * MAX;
          card.style.transform =
            'perspective(900px) rotateX(' + rx.toFixed(2) + 'deg) rotateY(' + ry.toFixed(2) +
            'deg) translateZ(6px)';
        });
      }

      function reset() {
        if (frame) {
          window.cancelAnimationFrame(frame);
          frame = null;
        }
        card.style.transform = '';
      }

      card.addEventListener('pointermove', onMove, { passive: true });
      card.addEventListener('pointerleave', reset);
      // Never leave a card frozen mid-tilt if the pointer is captured elsewhere.
      card.addEventListener('pointercancel', reset);
    });
  })();

  /* ------------------------------------------------------- async forms */

  /**
   * Progressive enhancement for the contact and newsletter forms: submit over
   * fetch and swap in the result, so the thank-you state arrives without a full
   * page reload. Without JS the same forms post normally and redirect back with
   * a flash message — the outcome is identical, it just costs a page load.
   */
  document.querySelectorAll('form[data-async]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();

      const button = form.querySelector('button[type="submit"]');
      const original = button ? button.textContent : '';

      if (button) {
        button.disabled = true;
        button.textContent = 'Sending…';
      }

      // Clear any errors left from a previous attempt.
      form.querySelectorAll('[data-field-error]').forEach(function (el) { el.remove(); });
      form.querySelectorAll('[aria-invalid]').forEach(function (el) { el.removeAttribute('aria-invalid'); });

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
        .then(function (response) {
          return response.json().then(function (data) {
            return { ok: response.ok, data: data };
          });
        })
        .then(function (result) {
          if (result.data && result.data.ok) {
            const done = document.createElement('div');
            done.className = 'rounded-card border border-positive/40 bg-positive/10 px-6 py-8 text-center';
            done.setAttribute('role', 'status');
            done.innerHTML =
              '<p class="display-md text-positive">Thank you</p>' +
              '<p class="prose-body mx-auto mt-3 text-sm">' + escapeHtml(result.data.message) + '</p>';

            form.replaceWith(done);
            done.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });

            return;
          }

          // Field-level errors, rendered next to the input they belong to.
          const errors = (result.data && result.data.errors) || {};
          let firstField = null;

          Object.keys(errors).forEach(function (name) {
            const field = form.querySelector('[name="' + name + '"]');
            if (!field) return;

            field.setAttribute('aria-invalid', 'true');

            const message = document.createElement('p');
            message.className = 'field-error';
            message.setAttribute('data-field-error', '');
            message.textContent = errors[name];
            field.insertAdjacentElement('afterend', message);

            if (!firstField) firstField = field;
          });

          if (firstField) {
            firstField.focus();
          } else if (errors.message) {
            window.alert(errors.message);
          }
        })
        .catch(function () {
          // Network failure: fall back to a real submit rather than losing what
          // the visitor typed.
          form.removeAttribute('data-async');
          form.submit();
        })
        .finally(function () {
          if (button) {
            button.disabled = false;
            button.textContent = original;
          }
        });
    });
  });

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
    });
  }

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
