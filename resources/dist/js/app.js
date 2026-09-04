window.ActivityTracker = window.ActivityTracker || {};

(function (AT) {
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

  /* ---------- Filter panel (expand/collapse) ---------- */

  document.addEventListener('click', function (event) {
    var toggle = event.target.closest('[data-at-filter-toggle]');
    if (!toggle) return;

    var panel = document.querySelector('[data-at-filter-panel]');
    if (!panel) return;

    var isOpen = panel.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  /* ---------- Generic collapsible sections (e.g. stack trace) ---------- */

  document.addEventListener('click', function (event) {
    var toggle = event.target.closest('[data-at-collapsible-toggle]');
    if (!toggle) return;

    var targetId = toggle.getAttribute('data-at-collapsible-toggle');
    var body = document.getElementById(targetId);
    if (!body) return;

    var isOpen = body.classList.toggle('is-open');
    toggle.classList.toggle('is-open', isOpen);
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  /* ---------- JSON viewer ---------- */

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function highlightJson(json) {
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

  function initJsonViewers(scope) {
    (scope || document).querySelectorAll('[data-at-json]').forEach(function (container) {
      var raw = container.getAttribute('data-at-json');
      var pre = container.querySelector('pre');
      if (!pre || pre.getAttribute('data-at-highlighted')) return;

      try {
        var parsed = JSON.parse(raw);
        pre.innerHTML = highlightJson(JSON.stringify(parsed, null, 2));
      } catch (e) {
        pre.textContent = raw;
      }
      pre.setAttribute('data-at-highlighted', '1');
    });
  }

  initJsonViewers(document);

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

  /* ---------- Toasts ---------- */

  function toast(message) {
    var stack = document.querySelector('[data-at-toast-stack]');
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'at-toast-stack';
      stack.setAttribute('data-at-toast-stack', '');
      stack.setAttribute('aria-live', 'polite');
      document.body.appendChild(stack);
    }

    var el = document.createElement('div');
    el.className = 'at-toast';
    el.textContent = message;
    stack.appendChild(el);

    window.setTimeout(function () {
      el.remove();
    }, 4000);
  }

  AT.toast = toast;

  /* ---------- Activities table: AJAX (XMLHttpRequest) ---------- */

  var appEl = document.querySelector('[data-at-activities-app]');

  if (appEl) {
    var resultsEl = document.querySelector('[data-at-results]');
    var formEl = document.querySelector('[data-at-filter-form]');
    var currentXhr = null;
    var searchDebounceTimer = null;
    var DEBOUNCE_MS = 400;

    function setLoading(isLoading) {
      if (!resultsEl) return;
      resultsEl.classList.toggle('is-loading', isLoading);

      var spinner = resultsEl.querySelector('[data-at-spinner]');
      if (isLoading && !spinner) {
        spinner = document.createElement('div');
        spinner.className = 'at-results-spinner';
        spinner.setAttribute('data-at-spinner', '');
        spinner.innerHTML = '<span class="at-spinner" aria-hidden="true"></span> Loading&hellip;';
        resultsEl.appendChild(spinner);
      } else if (!isLoading && spinner) {
        spinner.remove();
      }
    }

    function renderError(url) {
      if (!resultsEl) return;

      resultsEl.innerHTML =
        '<div class="at-error-state">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>' +
        '<h3>Unable to load activities.</h3>' +
        '<p>Please try again.</p>' +
        '<button type="button" class="at-btn at-btn-primary" data-at-retry>Retry</button>' +
        '</div>';

      var retry = resultsEl.querySelector('[data-at-retry]');
      if (retry) {
        retry.addEventListener('click', function () {
          fetchActivities(url, { history: 'replace' });
        });
      }
    }

    /**
     * options.history: 'push' (default), 'replace', or 'none' (used when
     * responding to a browser back/forward navigation, where the URL bar
     * has already changed and must not be pushed again).
     */
    function fetchActivities(url, options) {
      options = options || {};

      if (currentXhr) {
        currentXhr.abort();
      }

      setLoading(true);

      var xhr = new XMLHttpRequest();
      currentXhr = xhr;

      xhr.open('GET', url, true);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.setRequestHeader('Accept', 'application/json');

      xhr.onload = function () {
        if (xhr !== currentXhr) return; // superseded by a newer request
        setLoading(false);

        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            var response = JSON.parse(xhr.responseText);
            if (response && response.success && resultsEl) {
              resultsEl.innerHTML = response.data.html;
              initJsonViewers(resultsEl);

              var historyMode = options.history || 'push';
              if (historyMode !== 'none' && window.history) {
                var method = historyMode === 'replace' ? 'replaceState' : 'pushState';
                window.history[method]({ activityTrackerUrl: url }, '', url);
              }
              return;
            }
          } catch (e) {
            // fall through to the error state below
          }
        }

        renderError(url);
      };

      xhr.onerror = function () {
        if (xhr !== currentXhr) return;
        setLoading(false);
        renderError(url);
      };

      xhr.send();
    }

    AT.fetchActivities = fetchActivities;

    function currentUrlFromForm() {
      var action = formEl.getAttribute('action');
      var params = new URLSearchParams(new FormData(formEl));
      var query = params.toString();
      return query ? action + '?' + query : action;
    }

    if (formEl) {
      formEl.addEventListener('submit', function (event) {
        event.preventDefault();
        fetchActivities(currentUrlFromForm());
      });

      var searchInput = formEl.querySelector('[data-at-search-input]');
      if (searchInput) {
        searchInput.addEventListener('input', function () {
          window.clearTimeout(searchDebounceTimer);
          searchDebounceTimer = window.setTimeout(function () {
            fetchActivities(currentUrlFromForm());
          }, DEBOUNCE_MS);
        });
      }

      var perPageSelect = formEl.querySelector('[data-at-ajax-select]');
      if (perPageSelect) {
        perPageSelect.addEventListener('change', function () {
          fetchActivities(currentUrlFromForm());
        });
      }
    }

    // Delegated so it keeps working after resultsEl.innerHTML is replaced
    // (sort links and pagination links live inside the swapped content).
    document.addEventListener('click', function (event) {
      var link = event.target.closest('[data-at-ajax-link]');
      if (!link) return;

      event.preventDefault();
      var isPageOrSort = link.hasAttribute('data-at-page-link') || link.hasAttribute('data-at-sort-link');
      fetchActivities(link.href, { history: isPageOrSort ? 'replace' : 'push' });
    });

    window.addEventListener('popstate', function () {
      fetchActivities(window.location.href, { history: 'none' });
    });
  }
})(window.ActivityTracker);
