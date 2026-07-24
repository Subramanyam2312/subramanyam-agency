/**
 * Admin form behaviour: rich text, media picker, repeatable rows, delete guards.
 *
 * No framework and no build step. Quill is fetched only on pages that actually
 * contain an editor, so list screens do not pay 200 KB for a library they never use.
 */
(function () {
  'use strict';

  /* ------------------------------------------------------------------ helpers */

  function loadOnce(tag, attrs, key) {
    if (document.querySelector('[data-asset="' + key + '"]')) {
      return Promise.resolve();
    }

    return new Promise(function (resolve, reject) {
      const el = document.createElement(tag);
      Object.keys(attrs).forEach(function (name) {
        el.setAttribute(name, attrs[name]);
      });
      el.setAttribute('data-asset', key);
      el.onload = resolve;
      el.onerror = reject;
      document.head.appendChild(el);
    });
  }

  function csrfToken() {
    const input = document.querySelector('input[name="_token"]');
    return input ? input.value : '';
  }

  /* -------------------------------------------------------------- rich text */

  function initEditors() {
    const holders = document.querySelectorAll('.quill-editor');

    if (holders.length === 0) {
      return;
    }

    Promise.all([
      loadOnce('link', { rel: 'stylesheet', href: '/assets/vendor/quill/quill.snow.css' }, 'quill-css'),
      loadOnce('script', { src: '/assets/vendor/quill/quill.js' }, 'quill-js'),
    ])
      .then(function () {
        holders.forEach(function (holder) {
          const input = document.getElementById(holder.dataset.input);

          if (!input || holder.dataset.ready === '1') {
            return;
          }

          const quill = new window.Quill(holder, {
            theme: 'snow',
            modules: {
              toolbar: [
                [{ header: [2, 3, 4, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote', 'code-block'],
                ['link', 'image'],
                [{ align: [] }],
                ['clean'],
              ],
            },
          });

          holder.dataset.ready = '1';

          // Mirror into the hidden input on every change, so a submit triggered by
          // anything (button, Enter, browser autofill) always carries current content.
          quill.on('text-change', function () {
            const html = quill.getSemanticHTML ? quill.getSemanticHTML() : quill.root.innerHTML;
            input.value = html === '<p><br></p>' ? '' : html;
          });

          const form = input.closest('form');
          if (form) {
            form.addEventListener('submit', function () {
              const html = quill.getSemanticHTML ? quill.getSemanticHTML() : quill.root.innerHTML;
              input.value = html === '<p><br></p>' ? '' : html;
            });
          }
        });
      })
      .catch(function () {
        // Editor failed to load: leave the plain hidden input in place rather than
        // silently discarding whatever the field already contained.
        holders.forEach(function (holder) {
          holder.insertAdjacentHTML(
            'beforebegin',
            '<p class="field-error">The editor could not load. Reload the page before editing this field.</p>'
          );
        });
      });
  }

  /* ------------------------------------------------------------ media picker */

  let pickerEl = null;
  let pickerTarget = null;

  function buildPicker() {
    if (pickerEl) {
      return pickerEl;
    }

    pickerEl = document.createElement('div');
    pickerEl.className = 'fixed inset-0 z-50 hidden';
    pickerEl.innerHTML =
      '<div class="absolute inset-0 bg-black/70" data-picker-close></div>' +
      '<div class="absolute inset-x-4 top-8 bottom-8 mx-auto max-w-4xl overflow-hidden rounded-card border border-line bg-surface shadow-card" role="dialog" aria-modal="true" aria-label="Choose an image">' +
      '  <div class="flex items-center gap-3 border-b border-line/70 p-4">' +
      '    <input type="search" class="field-input" placeholder="Search the library…" data-picker-search>' +
      '    <button type="button" class="btn-ghost" data-picker-close>Close</button>' +
      '  </div>' +
      '  <div class="h-[calc(100%-73px)] overflow-y-auto p-4" data-picker-results></div>' +
      '</div>';

    document.body.appendChild(pickerEl);

    pickerEl.addEventListener('click', function (event) {
      if (event.target.hasAttribute('data-picker-close')) {
        closePicker();
      }
    });

    const search = pickerEl.querySelector('[data-picker-search]');
    let timer = null;

    search.addEventListener('input', function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        loadPicker(search.value);
      }, 250);
    });

    return pickerEl;
  }

  function loadPicker(search) {
    const results = pickerEl.querySelector('[data-picker-results]');
    results.innerHTML = '<p class="py-12 text-center text-sm text-muted">Loading…</p>';

    fetch('/admin/media/picker?search=' + encodeURIComponent(search || ''), {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        if (!data.items || data.items.length === 0) {
          results.innerHTML =
            '<p class="py-12 text-center text-sm text-muted">Nothing here. Upload images in the media library first.</p>';
          return;
        }

        results.innerHTML =
          '<ul class="grid grid-cols-2 gap-3 sm:grid-cols-4">' +
          data.items
            .map(function (item) {
              return (
                '<li><button type="button" class="block w-full overflow-hidden rounded-lg border border-line bg-raised text-left hover:border-accent" ' +
                'data-pick-id="' + item.id + '" data-pick-path="' + item.path + '" data-pick-alt="' + escapeHtml(item.alt) + '">' +
                '<span class="block aspect-square"><img src="' + item.path + '" alt="" class="h-full w-full object-cover" loading="lazy"></span>' +
                '<span class="block truncate p-2 text-xs text-muted">' + escapeHtml(item.name) + '</span>' +
                '</button></li>'
              );
            })
            .join('') +
          '</ul>';

        results.querySelectorAll('[data-pick-id]').forEach(function (button) {
          button.addEventListener('click', function () {
            applyPick(button.dataset.pickId, button.dataset.pickPath, button.dataset.pickAlt);
          });
        });
      })
      .catch(function () {
        results.innerHTML = '<p class="py-12 text-center text-sm text-danger">Could not load the library.</p>';
      });
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (character) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
    });
  }

  function applyPick(id, path, alt) {
    if (!pickerTarget) {
      return;
    }

    const input = pickerTarget.querySelector('[data-media-input]');
    const preview = pickerTarget.querySelector('[data-media-preview]');
    const clear = pickerTarget.querySelector('[data-media-clear]');

    input.value = id;
    preview.innerHTML = '<img src="' + path + '" alt="' + escapeHtml(alt) + '" class="h-full w-full object-cover">';

    if (clear) {
      clear.hidden = false;
    }

    closePicker();
  }

  function openPicker(field) {
    pickerTarget = field;
    buildPicker().classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    loadPicker('');
  }

  function closePicker() {
    if (pickerEl) {
      pickerEl.classList.add('hidden');
    }
    document.body.classList.remove('overflow-hidden');
    pickerTarget = null;
  }

  function initMediaFields() {
    document.querySelectorAll('[data-media-field]').forEach(function (field) {
      const choose = field.querySelector('[data-media-choose]');
      const clear = field.querySelector('[data-media-clear]');

      if (choose) {
        choose.addEventListener('click', function () {
          openPicker(field);
        });
      }

      if (clear) {
        clear.addEventListener('click', function () {
          field.querySelector('[data-media-input]').value = '';
          field.querySelector('[data-media-preview]').innerHTML = '';
          clear.hidden = true;
        });
      }
    });
  }

  /* ---------------------------------------------------------------- repeater */

  function initRepeaters() {
    document.querySelectorAll('[data-repeater]').forEach(function (repeater) {
      const items = repeater.querySelector('[data-repeater-items]');
      const template = repeater.querySelector('[data-repeater-template]');
      const add = repeater.querySelector('[data-repeater-add]');

      function nextIndex() {
        // Index off the current row count rather than a running counter, so
        // removing then adding cannot produce a duplicate key.
        return items.querySelectorAll('[data-repeater-item]').length;
      }

      if (add) {
        add.addEventListener('click', function () {
          const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex()));
          items.insertAdjacentHTML('beforeend', html);
        });
      }

      repeater.addEventListener('click', function (event) {
        if (!event.target.hasAttribute('data-repeater-remove')) {
          return;
        }

        const row = event.target.closest('[data-repeater-item]');

        if (row) {
          row.remove();
          reindex(repeater, items);
        }
      });
    });
  }

  /**
   * PHP receives repeater rows as an array, so gaps in the indices after a removal
   * are harmless — but renumbering keeps the DOM honest and the payload compact.
   */
  function reindex(repeater, items) {
    const name = repeater.dataset.name;

    items.querySelectorAll('[data-repeater-item]').forEach(function (row, index) {
      row.querySelectorAll('input, textarea, select').forEach(function (input) {
        input.name = input.name.replace(
          new RegExp('^' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\[\\d+\\]'),
          name + '[' + index + ']'
        );
      });
    });
  }

  /* ------------------------------------------------------- delete confirmation */

  function initConfirmations() {
    document.addEventListener('submit', function (event) {
      const form = event.target;

      if (!form.hasAttribute || !form.hasAttribute('data-confirm')) {
        return;
      }

      if (!window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  }

  /* -------------------------------------------------------------------- boot */

  document.addEventListener('DOMContentLoaded', function () {
    initEditors();
    initMediaFields();
    initRepeaters();
    initConfirmations();
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && pickerEl && !pickerEl.classList.contains('hidden')) {
      closePicker();
    }
  });
})();
