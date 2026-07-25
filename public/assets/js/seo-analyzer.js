/**
 * Live SEO analysis panel for the post editor.
 *
 * As the author edits, this gathers the current field values, posts them to
 * /admin/seo/analyze (debounced), and renders the returned score gauge and
 * checklist. All the scoring logic lives on the server in App\Core\SeoAnalyzer —
 * this file only collects inputs and paints the result, so the live panel and the
 * score stored on save can never drift apart.
 *
 * Self-hosted, so script-src 'self' covers it. The request carries the page's CSRF
 * token because the analyse route is a POST.
 */
(function () {
  'use strict';

  var panel = document.getElementById('seo-panel');
  if (!panel) {
    return;
  }

  var scoreEl = panel.querySelector('[data-seo-score]');
  var ringEl = panel.querySelector('[data-seo-ring]');
  var ratingEl = panel.querySelector('[data-seo-rating]');
  var listEl = panel.querySelector('[data-seo-checks]');
  var emptyEl = panel.querySelector('[data-seo-empty]');

  function field(name) {
    return document.querySelector('[name="' + name + '"]');
  }

  function value(name) {
    var el = field(name);
    return el ? el.value : '';
  }

  function csrfToken() {
    var el = document.querySelector('input[name="_token"]');
    return el ? el.value : '';
  }

  function collect() {
    return {
      focus_keyword: value('focus_keyword'),
      title: value('title'),
      meta_title: value('meta_title'),
      excerpt: value('excerpt'),
      meta_description: value('meta_description'),
      slug: value('slug'),
      // The rich-text editor mirrors its HTML into this hidden input on every
      // change, so reading the input gets the current content without touching Quill.
      content: value('content')
    };
  }

  var STATUS_ICON = {
    good: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M20 6 9 17l-5-5"/></svg>',
    ok: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-4 w-4"><path d="M12 8v5M12 16h.01"/><circle cx="12" cy="12" r="9"/></svg>',
    bad: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-4 w-4"><path d="M18 6 6 18M6 6l12 12"/></svg>'
  };

  var STATUS_CLASS = { good: 'text-positive', ok: 'text-warning', bad: 'text-danger' };
  var RING_CLASS = { great: 'text-positive', ok: 'text-warning', poor: 'text-danger', none: 'text-muted' };
  var RING_LABEL = { great: 'Good', ok: 'Needs work', poor: 'Poor', none: 'No keyword' };

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function render(result) {
    var score = result.score || 0;
    var rating = result.rating || 'none';

    scoreEl.textContent = score;

    // Sweep the SVG ring to match the score (circumference of r=15.9 ≈ 100).
    if (ringEl) {
      ringEl.setAttribute('stroke-dasharray', score + ', 100');
      ringEl.setAttribute('class', RING_CLASS[rating] + ' transition-all duration-500');
    }

    if (ratingEl) {
      ratingEl.textContent = RING_LABEL[rating];
      ratingEl.className = 'text-xs font-medium ' + (STATUS_CLASS[rating === 'great' ? 'good' : rating === 'ok' ? 'ok' : rating === 'poor' ? 'bad' : ''] || 'text-muted');
    }

    if (rating === 'none') {
      if (emptyEl) { emptyEl.hidden = false; }
      listEl.innerHTML = '';
      return;
    }

    if (emptyEl) { emptyEl.hidden = true; }

    // Worst first, so the most useful fixes are at the top.
    var order = { bad: 0, ok: 1, good: 2 };
    var checks = (result.checks || []).slice().sort(function (a, b) {
      return order[a.status] - order[b.status];
    });

    listEl.innerHTML = checks.map(function (check) {
      return '' +
        '<li class="flex items-start gap-2.5 py-2">' +
        '<span class="mt-0.5 shrink-0 ' + STATUS_CLASS[check.status] + '">' + STATUS_ICON[check.status] + '</span>' +
        '<span class="min-w-0"><span class="block text-sm text-body">' + escapeHtml(check.label) + '</span>' +
        '<span class="block text-xs text-muted">' + escapeHtml(check.message) + '</span></span>' +
        '</li>';
    }).join('');
  }

  var inFlight = null;

  function run() {
    // Abort a still-running request so a fast typist always sees the latest result.
    if (inFlight) {
      inFlight.abort();
    }

    var controller = new AbortController();
    inFlight = controller;

    fetch('/admin/seo/analyze', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-CSRF-Token': csrfToken(),
        Accept: 'application/json'
      },
      credentials: 'same-origin',
      body: new URLSearchParams(collect()).toString(),
      signal: controller.signal
    })
      .then(function (response) { return response.json(); })
      .then(function (result) { inFlight = null; render(result); })
      .catch(function (error) {
        inFlight = null;
        if (error.name !== 'AbortError') {
          // A failed analysis must never block editing — leave the last result up.
          panel.setAttribute('data-seo-stale', 'true');
        }
      });
  }

  var timer = null;
  function schedule() {
    window.clearTimeout(timer);
    timer = window.setTimeout(run, 650);
  }

  // Re-analyse on any edit to the fields that feed the score.
  ['focus_keyword', 'title', 'meta_title', 'excerpt', 'meta_description', 'slug', 'content'].forEach(function (name) {
    var el = field(name);
    if (el) {
      el.addEventListener('input', schedule);
      el.addEventListener('change', schedule);
    }
  });

  // The rich-text content input is written to programmatically by the editor, which
  // does not fire 'input'. Poll it for changes so typing in the body still counts.
  var lastContent = value('content');
  window.setInterval(function () {
    var current = value('content');
    if (current !== lastContent) {
      lastContent = current;
      schedule();
    }
  }, 1200);

  // First analysis on load, so an existing post shows its score immediately.
  run();
})();
