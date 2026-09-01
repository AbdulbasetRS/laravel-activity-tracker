(function () {
  'use strict';

  var STORAGE_KEY = 'activity-tracker-theme';
  var root = document.documentElement;

  /* ---------- Theme ---------- */

  function systemPrefersDark() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  }

  function applyTheme(theme) {
    var effective = theme === 'system' ? (systemPrefersDark() ? 'dark' : 'light') : theme;
    root.setAttribute('data-at-theme', effective);
  }

  function currentPreference() {
    try {
      return window.localStorage.getItem(STORAGE_KEY) || root.getAttribute('data-at-default-theme') || 'system';
    } catch (e) {
      return root.getAttribute('data-at-default-theme') || 'system';
    }
  }

  function setPreference(theme) {
    try {
      window.localStorage.setItem(STORAGE_KEY, theme);
    } catch (e) {
      /* localStorage unavailable (privacy mode, etc.) — theme just won't persist. */
    }
    applyTheme(theme);
  }

  applyTheme(currentPreference());

  document.addEventListener('click', function (event) {
    var toggle = event.target.closest('[data-at-theme-toggle]');
    if (!toggle) return;

    var effective = root.getAttribute('data-at-theme') === 'dark' ? 'light' : 'dark';
    setPreference(effective);
  });

  /* ---------- Mobile sidebar ---------- */

  document.addEventListener('click', function (event) {
    var toggle = event.target.closest('[data-at-sidebar-toggle]');
    var sidebar = document.querySelector('[data-at-sidebar]');
    if (!sidebar) return;

    if (toggle) {
      sidebar.classList.toggle('is-open');
      return;
    }

    if (!event.target.closest('[data-at-sidebar]') && sidebar.classList.contains('is-open')) {
      sidebar.classList.remove('is-open');
    }
  });

  /* ---------- Filter panel ---------- */

  document.addEventListener('click', function (event) {
    var toggle = event.target.closest('[data-at-filter-toggle]');
    if (!toggle) return;

    var panel = document.querySelector('[data-at-filter-panel]');
    if (panel) panel.classList.toggle('is-open');
  });

  /* ---------- JSON viewer ---------- */

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function highlight(json) {
    var escaped = escapeHtml(json);
    return escaped.replace(
      /("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false)\b|\bnull\b|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/g,
      function (match) {
        var cls = 'at-json-number';
        if (/^"/.test(match)) {
          cls = /:$/.test(match) ? 'at-json-key' : 'at-json-string';
        } else if (/true|false/.test(match)) {
          cls = 'at-json-boolean';
        } else if (/null/.test(match)) {
          cls = 'at-json-null';
        }
        return '<span class="' + cls + '">' + match + '</span>';
      }
    );
  }

  document.querySelectorAll('[data-at-json]').forEach(function (container) {
    var raw = container.getAttribute('data-at-json');
    var pre = container.querySelector('pre');
    if (!pre) return;

    try {
      var parsed = JSON.parse(raw);
      pre.innerHTML = highlight(JSON.stringify(parsed, null, 2));
    } catch (e) {
      pre.textContent = raw;
    }
  });

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-at-json-copy]');
    if (!button) return;

    var container = button.closest('[data-at-json]');
    var raw = container ? container.getAttribute('data-at-json') : '';

    var finish = function (label) {
      var original = button.textContent;
      button.textContent = label;
      window.setTimeout(function () {
        button.textContent = original;
      }, 1200);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(raw).then(function () {
        finish('Copied');
      }, function () {
        finish('Failed');
      });
    }
  });
})();
