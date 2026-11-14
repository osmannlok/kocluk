// public/assets/js/search.js
(function () {
  'use strict';

  var MIN_CHARS = 2;
  var DELAY = 250;
  var counter = 0;

  function debounce(fn, wait) {
    var t = null;
    return function () {
      var args = arguments;
      window.clearTimeout(t);
      t = window.setTimeout(function () { fn.apply(null, args); }, wait);
    };
  }

  // Yalnızca site içi göreli yol veya https görsel kabul edilir.
  function safeImage(src) {
    return typeof src === 'string' &&
      ((src.charAt(0) === '/' && src.charAt(1) !== '/') || /^https:\/\//i.test(src));
  }

  function init(form) {
    var input = form.querySelector('input[name="q"]');
    if (!input) { return; }

    var id = ++counter;
    var listId = 'ymSearchList' + id;
    var cache = {};
    var controller = null;
    var options = [];
    var active = -1;

    form.classList.add('position-relative');

    var list = document.createElement('ul');
    list.id = listId;
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', 'Arama önerileri');
    list.className = 'd-none position-absolute start-0 end-0 top-100 mt-1 bg-body border rounded shadow list-unstyled mb-0 overflow-hidden z-3';
    form.appendChild(list);

    var status = document.createElement('div');
    status.className = 'visually-hidden';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    form.appendChild(status);

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', listId);
    input.setAttribute('autocomplete', 'off');

    // Liste üzerindeki tıklama input odağını kaybettirmesin.
    list.addEventListener('mousedown', function (e) { e.preventDefault(); });

    function close() {
      list.classList.add('d-none');
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      active = -1;
    }

    function open() {
      if (options.length === 0) { return; }
      list.classList.remove('d-none');
      input.setAttribute('aria-expanded', 'true');
    }

    function setActive(i) {
      options.forEach(function (li, k) {
        var on = k === i;
        li.setAttribute('aria-selected', on ? 'true' : 'false');
        li.classList.toggle('bg-body-secondary', on);
      });
      active = i;
      if (i >= 0) {
        input.setAttribute('aria-activedescendant', options[i].id);
        if (typeof options[i].scrollIntoView === 'function') { options[i].scrollIntoView({ block: 'nearest' }); }
      } else {
        input.removeAttribute('aria-activedescendant');
      }
    }

    function buildOption(index, href, text, image) {
      var li = document.createElement('li');
      li.id = listId + '-o' + index;
      li.setAttribute('role', 'option');
      li.setAttribute('aria-selected', 'false');

      var a = document.createElement('a');
      a.href = href;
      a.tabIndex = -1;
      a.className = 'd-flex align-items-center gap-2 px-3 py-2 text-decoration-none text-body';

      if (image) {
        var img = document.createElement('img');
        img.src = image;
        img.alt = '';
        img.width = 40;
        img.height = 40;
        img.className = 'rounded object-fit-cover flex-shrink-0';
        a.appendChild(img);
      }
      var span = document.createElement('span');
      span.textContent = text; // güvenli: textContent
      a.appendChild(span);

      li.appendChild(a);
      return li;
    }

    function render(items, q) {
      while (list.firstChild) { list.removeChild(list.firstChild); }
      options = [];
      active = -1;

      items.forEach(function (item, i) {
        if (!item || typeof item.slug !== 'string' || typeof item.name !== 'string') { return; }
        var li = buildOption(i, '/urun/' + encodeURIComponent(item.slug), item.name, safeImage(item.image) ? item.image : null);
        list.appendChild(li);
        options.push(li);
      });

      if (options.length === 0) {
        close();
        status.textContent = 'Öneri bulunamadı.';
        return;
      }

      var all = buildOption(options.length, '/ara?q=' + encodeURIComponent(q), '“' + q + '” için tüm sonuçlar', null);
      list.appendChild(all);
      options.push(all);

      status.textContent = options.length - 1 + ' öneri bulundu. Gezinmek için yukarı ve aşağı oklarını kullanın.';
      open();
    }

    function fetchSuggestions(q) {
      if (cache[q]) { render(cache[q], q); return; }
      if (controller) { controller.abort(); }
      controller = typeof AbortController === 'function' ? new AbortController() : null;

      var opts = {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      };
      if (controller) { opts.signal = controller.signal; }

      fetch('/ara/oneri?q=' + encodeURIComponent(q), opts)
        .then(function (res) { return res.ok ? res.json() : null; })
        .then(function (payload) {
          if (!payload || payload.ok !== true || !payload.data || !Array.isArray(payload.data.items)) {
            close();
            return;
          }
          cache[q] = payload.data.items;
          // Gecikmiş yanıt, kullanıcı yazmaya devam ettiyse yok sayılır.
          if (input.value.trim() === q) { render(cache[q], q); }
        })
        .catch(function (err) {
          if (err && err.name === 'AbortError') { return; }
          close();
        });
    }

    var onInput = debounce(function () {
      var q = input.value.trim();
      if (q.length < MIN_CHARS) {
        if (controller) { controller.abort(); }
        close();
        return;
      }
      fetchSuggestions(q);
    }, DELAY);

    input.addEventListener('input', onInput);

    input.addEventListener('keydown', function (e) {
      var isOpen = !list.classList.contains('d-none');
      if (e.key === 'ArrowDown') {
        if (!isOpen) { open(); if (list.classList.contains('d-none')) { return; } }
        e.preventDefault();
        setActive(active + 1 >= options.length ? 0 : active + 1);
      } else if (e.key === 'ArrowUp') {
        if (!isOpen) { return; }
        e.preventDefault();
        setActive(active <= 0 ? options.length - 1 : active - 1);
      } else if (e.key === 'Enter') {
        if (isOpen && active >= 0) {
          e.preventDefault();
          var a = options[active].querySelector('a');
          if (a) { window.location.assign(a.getAttribute('href')); }
        }
      } else if (e.key === 'Escape') {
        if (isOpen) { e.preventDefault(); close(); }
      }
    });

    form.addEventListener('focusout', function (e) {
      if (!e.relatedTarget || !form.contains(e.relatedTarget)) { close(); }
    });
    form.addEventListener('submit', close);
    document.addEventListener('click', function (e) {
      if (!form.contains(e.target)) { close(); }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    Array.prototype.forEach.call(
      document.querySelectorAll('#ymSearch, form[data-search-autocomplete]'),
      init
    );
  });
})();