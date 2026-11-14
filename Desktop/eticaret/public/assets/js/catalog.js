// public/assets/js/catalog.js
(function () {
  'use strict';

  var MAX_QTY = 99;

  function money(v) {
    if (window.App && typeof window.App.formatMoney === 'function') {
      return window.App.formatMoney(v);
    }
    return String(v);
  }

  function all(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  // Yalnızca ekranda yüzde göstermek için; sipariş tutarı sunucuda hesaplanır.
  function toCents(s) {
    return Math.round(parseFloat(String(s)) * 100);
  }

  /* ---------- Ürün detayı ---------- */
  function initProduct(root) {
    var variants = [];
    try {
      variants = JSON.parse(root.getAttribute('data-variants') || '[]');
    } catch (e) {
      variants = [];
    }
    if (!Array.isArray(variants) || variants.length === 0) { return; }

    var byId = {};
    variants.forEach(function (v) { byId[v.id] = v; });

    var current = byId[parseInt(root.getAttribute('data-default-variant'), 10)] || variants[0];
    var qtyInput = document.querySelector('[data-qty-input]');
    var stockClasses = ['text-success', 'text-warning-emphasis', 'text-danger'];

    function clampQty(n) {
      n = parseInt(n, 10);
      if (isNaN(n) || n < 1) { return 1; }
      return n > MAX_QTY ? MAX_QTY : n;
    }

    function currentQty() {
      return qtyInput ? clampQty(qtyInput.value) : 1;
    }

    function syncButtons() {
      var qty = currentQty();
      all('[data-add-to-cart]').forEach(function (btn) {
        btn.setAttribute('data-variant-id', String(current.id));
        btn.setAttribute('data-quantity', String(qty));
        btn.disabled = !current.in_stock;
        var label = btn.querySelector('[data-add-label]');
        if (label) { label.textContent = current.in_stock ? 'Sepete ekle' : 'Tükendi'; }
      });
    }

    function applyVariant(v) {
      current = v;

      all('[data-price-final]').forEach(function (el) { el.textContent = money(v.final_price); });

      all('[data-price-old]').forEach(function (el) {
        el.textContent = money(v.price);
        el.classList.toggle('d-none', !v.on_sale);
      });
      all('[data-price-badge]').forEach(function (el) {
        var p = toCents(v.price), f = toCents(v.final_price);
        var pct = v.on_sale && p > 0 ? Math.round(((p - f) * 100) / p) : 0;
        el.textContent = '%' + pct + ' indirim';
        el.classList.toggle('d-none', pct <= 0);
      });

      all('[data-sku]').forEach(function (el) { el.textContent = v.sku; });
      all('[data-weight]').forEach(function (el) { el.textContent = v.weight_grams + ' g'; });

      all('[data-stock-text]').forEach(function (el) {
        stockClasses.forEach(function (c) { el.classList.remove(c); });
        if (!v.in_stock) {
          el.textContent = 'Tükendi';
          el.classList.add('text-danger');
        } else if (v.low) {
          el.textContent = 'Stok azalıyor, acele edin';
          el.classList.add('text-warning-emphasis');
        } else {
          el.textContent = 'Stokta var';
          el.classList.add('text-success');
        }
      });

      all('[data-variant-button]').forEach(function (btn) {
        var id = parseInt(btn.getAttribute('data-variant-id'), 10);
        var active = id === v.id;
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        btn.classList.toggle('btn-success', active);
        btn.classList.toggle('btn-outline-success', !active);
        if (byId[id]) { btn.classList.toggle('text-decoration-line-through', !byId[id].in_stock); }
      });

      syncButtons();
    }

    all('[data-variant-button]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var v = byId[parseInt(btn.getAttribute('data-variant-id'), 10)];
        if (v) { applyVariant(v); }
      });
    });

    // Adet +/-
    if (qtyInput) {
      var setQty = function (n) { qtyInput.value = String(clampQty(n)); syncButtons(); };
      var minus = document.querySelector('[data-qty-minus]');
      var plus = document.querySelector('[data-qty-plus]');
      if (minus) { minus.addEventListener('click', function () { setQty(currentQty() - 1); }); }
      if (plus) { plus.addEventListener('click', function () { setQty(currentQty() + 1); }); }
      qtyInput.addEventListener('input', syncButtons);
      qtyInput.addEventListener('change', function () { setQty(qtyInput.value); });
    }

    initGallery();
    initSticky();
    applyVariant(current);
  }

  function initGallery() {
    var main = document.querySelector('[data-gallery-main]');
    var thumbs = all('[data-gallery-thumb]');
    if (!main || thumbs.length < 2) { return; }

    var index = 0;

    function show(i) {
      index = (i + thumbs.length) % thumbs.length;
      var t = thumbs[index];
      main.src = t.getAttribute('data-src') || '';
      main.alt = t.getAttribute('data-alt') || '';
      thumbs.forEach(function (el, k) {
        el.setAttribute('aria-current', k === index ? 'true' : 'false');
        el.classList.toggle('border-success', k === index);
      });
      if (typeof t.scrollIntoView === 'function') {
        t.scrollIntoView({ block: 'nearest', inline: 'nearest' });
      }
    }

    thumbs.forEach(function (t, i) {
      t.addEventListener('click', function () { show(i); });
      t.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight') { e.preventDefault(); show(index + 1); thumbs[index].focus(); }
        if (e.key === 'ArrowLeft') { e.preventDefault(); show(index - 1); thumbs[index].focus(); }
      });
    });

    // Mobilde kaydırma (swipe)
    var startX = null;
    main.addEventListener('touchstart', function (e) {
      startX = e.touches.length === 1 ? e.touches[0].clientX : null;
    }, { passive: true });
    main.addEventListener('touchend', function (e) {
      if (startX === null || !e.changedTouches.length) { return; }
      var dx = e.changedTouches[0].clientX - startX;
      startX = null;
      if (Math.abs(dx) > 40) { show(dx < 0 ? index + 1 : index - 1); }
    }, { passive: true });

    show(0);
  }

  function initSticky() {
    var bar = document.querySelector('[data-sticky-bar]');
    var mainBtn = document.querySelector('[data-main-add]');
    if (!bar || !mainBtn || !('IntersectionObserver' in window)) { return; }
    // Ana buton görünürken yapışkan çubuk gizlenir.
    new IntersectionObserver(function (entries) {
      bar.classList.toggle('d-none', entries[0].isIntersecting);
    }).observe(mainBtn);
  }

  /* ---------- Liste ---------- */
  function parsePrice(s) {
    s = String(s || '').trim().replace(',', '.');
    if (s === '') { return null; }
    if (!/^\d{1,7}(\.\d{1,2})?$/.test(s)) { return NaN; }
    return parseFloat(s);
  }

  function initFilterForm(form) {
    var inputs = all('[data-price-input]', form);
    var errorEl = form.querySelector('[data-price-error]');

    function setError(msg) {
      if (errorEl) {
        errorEl.textContent = msg;
        errorEl.classList.toggle('d-none', msg === '');
      }
      inputs.forEach(function (i) {
        if (msg === '') { i.removeAttribute('aria-invalid'); } else { i.setAttribute('aria-invalid', 'true'); }
      });
    }

    inputs.forEach(function (i) { i.addEventListener('input', function () { setError(''); }); });

    form.addEventListener('submit', function (e) {
      if (inputs.length === 2) {
        var min = parsePrice(inputs[0].value);
        var max = parsePrice(inputs[1].value);
        if (Number.isNaN(min) || Number.isNaN(max)) {
          e.preventDefault();
          setError('Fiyatı rakamla girin (örn. 150 veya 150,50).');
          inputs[Number.isNaN(min) ? 0 : 1].focus();
          return;
        }
        if (min !== null && max !== null && min > max) {
          e.preventDefault();
          setError('En düşük fiyat, en yüksek fiyattan büyük olamaz.');
          inputs[0].focus();
          return;
        }
      }
      // Boş alanlar URL'ye yazılmasın.
      all('[data-skip-empty]', form).forEach(function (el) {
        if (String(el.value).trim() === '') { el.disabled = true; }
      });
      var submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) { submitBtn.disabled = true; }
    });
  }

  function initList(root) {
    all('[data-sort-submit]', root).forEach(function (b) { b.classList.add('d-none'); });
    all('[data-sort-select]', root).forEach(function (sel) {
      sel.addEventListener('change', function () {
        var form = sel.form;
        if (!form) { return; }
        if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); }
      });
    });

    all('[data-filter-form]', document).forEach(initFilterForm);

    // Masaüstü genişliğine geçilince filtre offcanvas'ı kapat.
    var oc = document.getElementById('ymFilterOffcanvas');
    if (oc && window.matchMedia && window.bootstrap && window.bootstrap.Offcanvas) {
      var mq = window.matchMedia('(min-width: 992px)');
      var onChange = function (e) {
        if (e.matches) {
          var inst = window.bootstrap.Offcanvas.getInstance(oc);
          if (inst) { inst.hide(); }
        }
      };
      if (typeof mq.addEventListener === 'function') { mq.addEventListener('change', onChange); }
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var product = document.querySelector('[data-product-page]');
    if (product) { initProduct(product); }
    var list = document.querySelector('[data-catalog-list]');
    if (list) { initList(list); }
  });
})();