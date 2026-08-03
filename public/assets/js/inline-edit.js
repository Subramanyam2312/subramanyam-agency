/**
 * Inline editing for signed-in staff.
 *
 * Loaded only when someone is signed in, so this file never reaches a visitor.
 * It turns elements carrying data-edit into contenteditable regions, tracks which
 * ones actually changed, and posts them to /admin/inline-edit — the same write the
 * page-copy form performs, with the same sanitising applied server-side.
 *
 * Two details worth knowing:
 *
 *  - Headings marked data-split have had their text replaced by per-word <span>s
 *    for the reveal animation. Editing that structure directly produces nonsense,
 *    so entering edit mode flattens them back to plain text and leaving it puts
 *    the page back the way it was by reloading.
 *
 *  - Nothing saves implicitly. Edits are held until Save is pressed, so a stray
 *    keystroke on a live page is never written.
 */
(function () {
  'use strict';

  var bar = document.querySelector('[data-admin-bar]');
  var fields = Array.prototype.slice.call(document.querySelectorAll('[data-edit]'));

  if (!bar || fields.length === 0) {
    return;
  }

  var token = bar.getAttribute('data-csrf') || '';
  var editing = false;
  var dirty = Object.create(null);

  // ---- UI ----------------------------------------------------------------
  var toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.className = 'admin-bar-link';
  toggle.textContent = 'Edit text';

  var save = document.createElement('button');
  save.type = 'button';
  save.className = 'admin-bar-edit';
  save.hidden = true;
  save.textContent = 'Save changes';

  var status = document.createElement('span');
  status.className = 'admin-bar-status';
  status.hidden = true;

  bar.appendChild(toggle);
  bar.appendChild(save);
  bar.appendChild(status);

  function say(message, tone) {
    status.textContent = message;
    status.hidden = message === '';
    status.dataset.tone = tone || '';
  }

  function key(el) {
    return el.getAttribute('data-edit') + '::' + el.getAttribute('data-edit-block');
  }

  function readValue(el) {
    return el.getAttribute('data-edit-type') === 'html'
      ? el.innerHTML.trim()
      : (el.textContent || '').trim();
  }

  // ---- Mode --------------------------------------------------------------
  function start() {
    editing = true;
    document.body.classList.add('is-inline-editing');

    fields.forEach(function (el) {
      // Flatten split headings so editing operates on real text, not word spans.
      if (el.hasAttribute('data-split')) {
        el.textContent = (el.textContent || '').replace(/\s+/g, ' ').trim();
      }

      el.setAttribute('contenteditable', el.getAttribute('data-edit-type') === 'html' ? 'true' : 'plaintext-only');
      el.dataset.editOriginal = readValue(el);

      el.addEventListener('input', onInput);
      el.addEventListener('keydown', onKeydown);
    });

    toggle.textContent = 'Cancel';
    save.hidden = false;
    say('Click any highlighted text to edit it.', '');
  }

  function onInput(event) {
    var el = event.currentTarget;

    if (readValue(el) === el.dataset.editOriginal) {
      delete dirty[key(el)];
    } else {
      dirty[key(el)] = el;
    }

    var count = Object.keys(dirty).length;
    say(count === 0 ? 'No changes yet.' : count + (count === 1 ? ' change' : ' changes') + ' pending.', '');
  }

  function onKeydown(event) {
    // Enter commits a single-line field rather than inserting a newline.
    if (event.key === 'Enter' && event.currentTarget.getAttribute('data-edit-type') !== 'html') {
      event.preventDefault();
      event.currentTarget.blur();
    }

    if (event.key === 'Escape') {
      event.currentTarget.blur();
    }
  }

  // ---- Save --------------------------------------------------------------
  function commit() {
    var pending = Object.keys(dirty).map(function (k) {
      var el = dirty[k];

      return {
        page: el.getAttribute('data-edit'),
        block: el.getAttribute('data-edit-block'),
        value: readValue(el)
      };
    });

    if (pending.length === 0) {
      say('Nothing to save.', '');

      return;
    }

    save.disabled = true;
    say('Saving…', '');

    fetch('/admin/inline-edit', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': token,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ edits: pending })
    })
      .then(function (response) {
        return response.json().then(function (body) {
          return { ok: response.ok, body: body };
        });
      })
      .then(function (result) {
        save.disabled = false;

        if (!result.ok) {
          say(result.body.message || 'Could not save.', 'error');

          return;
        }

        dirty = Object.create(null);
        say(result.body.message || 'Saved.', 'ok');
        // Reload so the page renders exactly what was stored, split headings and all.
        window.setTimeout(function () { window.location.reload(); }, 700);
      })
      .catch(function () {
        save.disabled = false;
        say('Could not reach the server.', 'error');
      });
  }

  toggle.addEventListener('click', function () {
    if (!editing) {
      start();

      return;
    }

    if (Object.keys(dirty).length > 0 && !window.confirm('Discard your unsaved changes?')) {
      return;
    }

    window.location.reload();
  });

  save.addEventListener('click', commit);

  window.addEventListener('beforeunload', function (event) {
    if (editing && Object.keys(dirty).length > 0) {
      event.preventDefault();
      event.returnValue = '';
    }
  });
})();
