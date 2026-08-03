/**
 * Inline editing inside a CMS preview.
 *
 * Only ever loaded on a page opened from the portal by a signed-in user, so this
 * file never reaches a visitor. It turns elements carrying data-edit into editable
 * regions and posts changed ones to /admin/inline-edit, which stores them as
 * drafts — typing here cannot change the live site, and Publish stays a separate,
 * deliberate press.
 *
 * Two details worth knowing:
 *
 *  - Headings marked data-split have had their text replaced by per-word <span>s
 *    for the reveal animation. Editing that structure produces nonsense, so
 *    entering edit mode flattens them back to plain text.
 *
 *  - Nothing saves implicitly. Edits are held until Save is pressed, and leaving
 *    with unsaved changes asks first.
 */
(function () {
  'use strict';

  var bar = document.querySelector('[data-cms-bar]');
  var fields = Array.prototype.slice.call(document.querySelectorAll('[data-edit]'));

  if (!bar || fields.length === 0) {
    return;
  }

  var token = bar.getAttribute('data-csrf') || '';
  var editBtn = bar.querySelector('[data-cms-edit]');
  var status = bar.querySelector('[data-cms-status]');
  var editing = false;
  var dirty = Object.create(null);

  if (!editBtn) {
    return;
  }

  // ---- Buttons that only exist while editing -----------------------------
  function button(label, className) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = className;
    b.textContent = label;
    b.hidden = true;
    editBtn.parentNode.insertBefore(b, editBtn);

    return b;
  }

  var doneBtn = button('Done', 'cms-bar-link');
  var saveBtn = button('Save', 'cms-bar-link');

  function say(message) {
    if (status) {
      status.textContent = message;
    }
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

      el.setAttribute(
        'contenteditable',
        el.getAttribute('data-edit-type') === 'html' ? 'true' : 'plaintext-only'
      );
      el.dataset.editOriginal = readValue(el);

      el.addEventListener('input', onInput);
      el.addEventListener('keydown', onKeydown);
    });

    editBtn.hidden = true;
    doneBtn.hidden = false;
    saveBtn.hidden = false;
    say('Click any outlined text to edit it.');
  }

  function onInput(event) {
    var el = event.currentTarget;

    if (readValue(el) === el.dataset.editOriginal) {
      delete dirty[key(el)];
    } else {
      dirty[key(el)] = el;
    }

    var count = Object.keys(dirty).length;
    say(count === 0 ? 'No changes yet.' : count + (count === 1 ? ' change' : ' changes') + ' not saved.');
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
      say('Nothing to save.');

      return;
    }

    saveBtn.disabled = true;
    say('Saving…');

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
        saveBtn.disabled = false;

        if (!result.ok) {
          say(result.body.message || 'Could not save.');

          return;
        }

        dirty = Object.create(null);
        say(result.body.message || 'Saved as a draft.');
        // Reload so the page renders the stored draft, split headings and all,
        // and the bar picks up the new publish state.
        window.setTimeout(function () { window.location.reload(); }, 600);
      })
      .catch(function () {
        saveBtn.disabled = false;
        say('Could not reach the server.');
      });
  }

  function leaveEditing() {
    if (Object.keys(dirty).length > 0 && !window.confirm('Leave without saving your changes?')) {
      return;
    }

    window.location.reload();
  }

  editBtn.addEventListener('click', function (event) {
    event.preventDefault();
    start();
  });

  doneBtn.addEventListener('click', leaveEditing);
  saveBtn.addEventListener('click', commit);

  window.addEventListener('beforeunload', function (event) {
    if (editing && Object.keys(dirty).length > 0) {
      event.preventDefault();
      event.returnValue = '';
    }
  });
})();
