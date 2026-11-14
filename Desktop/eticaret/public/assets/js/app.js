// public/assets/js/app.js
// Yöresel Market ön yüz çekirdeği: window.App. Satır içi JS yok (CSP). Kullanıcı verisi yalnızca textContent ile yazılır.
(function () {
  'use strict';

  var App = window.App = window.App || {};

  /* ---------- Yardımcılar ---------- */
  function csrfToken() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? (m.getAttribute('content') || '') : '';
  }

  function debounce(fn, wait) {
    var t = null;
    return function () {
      var ctx = this, args = arguments;
      if (t) { clearTimeout(t); }
      t = setTimeout(function () { t = null; fn.apply(ctx, args); }, wait || 250);
    };
  }

  var moneyFmt = null;
  function formatMoney(value) {
    var n = Number(value);
    if (!isFinite(n)) { n = 0; }
    if (!moneyFmt) {
      moneyFmt = new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    // PHP tarafındaki Money::format ile aynı biçim: "1.234,50 ₺"
    return moneyFmt.format(n) + ' ₺';
  }

  /* ---------- Toast ---------- */
  function toastContainer() {
    var c = document.getElementById('ymToasts');
    if (!c) {
      c = document.createElement('div');
      c.id = 'ymToasts';
      c.className = 'ym-toasts';
      c.setAttribute('aria-live', 'polite');
      c.setAttribute('aria-atomic', 'false');
      document.body.appendChild(c);
    }
    return c;
  }

  function toast(msg, type) {
    var kind = ['success', 'danger', 'warning', 'info'].indexOf(type) >= 0 ? type : 'info';
    var el = document.createElement('div');
    el.className = 'ym-toast ym-toast--' + kind;
    el.setAttribute('role', kind === 'danger' ? 'alert' : 'status');

    var p = document.createElement('p');
    p.className = 'ym-toast__msg';
    p.textContent = String(msg == null ? '' : msg);

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ym-toast__close';
    btn.setAttribute('aria-label', 'Bildirimi kapat');
    btn.textContent = '×';

    function close() {
      if (!el.parentNode) { return; }
      el.classList.add('is-leaving');
      setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 220);
    }
    btn.addEventListener('click', close);

    el.appendChild(p);
    el.appendChild(btn);
    toastContainer().appendChild(el);
    setTimeout(close, kind === 'danger' ? 8000 : 5000);
    return el;
  }

  /* ---------- fetchJson ---------- */
  // Her zaman çözülür: {ok, status, data, error:{code,message,errors}|null}. Çağıran ok alanına bakar.
  function fetchJson(url, opts) {
    opts = opts || {};
    var method = String(opts.method || 'GET').toUpperCase();
    var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    var extra = opts.headers || {};
    Object.keys(extra).forEach(function (k) { headers[k] = extra[k]; });

    var init = { method: method, headers: headers, credentials: 'same-origin' };

    if (method !== 'GET' && method !== 'HEAD') {
      headers['X-CSRF-Token'] = csrfToken();
      var body = opts.body;
      if (body !== undefined && body !== null) {
        if (typeof FormData !== 'undefined' && body instanceof FormData) {
          init.body = body;
        } else if (typeof body === 'string') {
          init.body = body;
          if (!headers['Content-Type']) { headers['Content-Type'] = 'application/json'; }
        } else {
          init.body = JSON.stringify(body);
          headers['Content-Type'] = 'application/json';
        }
      }
    }

    return fetch(url, init).then(function (res) {
      return res.text().then(function (text) {
        var json = null;
        if (text) { try { json = JSON.parse(text); } catch (e) { json = null; } }
        var status = res.status;

        if (res.ok && json && json.ok !== false) {
          return { ok: true, status: status, data: json.data === undefined ? {} : json.data, error: null };
        }

        var err = (json && json.error) ? json.error : {};
        var error = {
          code: err.code || 'error',
          message: err.message || 'Beklenmeyen bir hata oluştu.',
          errors: err.errors || {}
        };

        if (status === 401) {
          error.code = 'unauthenticated';
          toast('Devam etmek için giriş yapmalısınız.', 'warning');
          setTimeout(function () { window.location.assign('/giris'); }, 600);
        } else if (status === 419) {
          error.code = 'csrf';
          toast('Oturumunuzun süresi doldu. Lütfen sayfayı yenileyip tekrar deneyin.', 'warning');
        } else if (status === 422) {
          error.code = 'validation';
        } else if (status === 429) {
          error.code = 'rate_limited';
          toast('Çok fazla istek gönderdiniz. Lütfen biraz bekleyin.', 'warning');
        }

        return { ok: false, status: status, data: null, error: error };
      });
    }).catch(function () {
      toast('Bağlantı hatası. İnternet bağlantınızı kontrol edip tekrar deneyin.', 'danger');
      return { ok: false, status: 0, data: null, error: { code: 'network', message: 'Bağlantı hatası.', errors: {} } };
    });
  }

  /* ---------- Sepet rozeti ---------- */
  function setCartCount(n) {
    var count = parseInt(n, 10);
    if (!isFinite(count) || count < 0) { count = 0; }
    var els = document.querySelectorAll('[data-cart-count]');
    for (var i = 0; i < els.length; i++) {
      els[i].textContent = count > 99 ? '99+' : String(count);
      els[i].setAttribute('data-count', String(count));
      els[i].hidden = count === 0;
    }
  }

  document.addEventListener('cart:changed', function (e) {
    if (e && e.detail && typeof e.detail.count !== 'undefined') { setCartCount(e.detail.count); }
  });

  /* ---------- data-width (CSP uyumlu dinamik genişlik) ---------- */
  function applyWidths(root) {
    var scope = root || document;
    var els = scope.querySelectorAll('[data-width]');
    for (var i = 0; i < els.length; i++) {
      var raw = String(els[i].getAttribute('data-width') || '').trim();
      if (/^\d+(\.\d+)?$/.test(raw)) { raw += '%'; }
      if (/^\d+(\.\d+)?(%|px|rem|em)$/.test(raw)) { els[i].style.width = raw; }
    }
  }

  /* ---------- Onay ([data-confirm]) ---------- */
  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-confirm]') : null;
    if (!t || t.tagName === 'FORM') { return; }
    if (!window.confirm(t.getAttribute('data-confirm') || 'Emin misiniz?')) {
      e.preventDefault();
      e.stopPropagation();
    }
  }, true);

  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f && f.tagName === 'FORM' && f.hasAttribute('data-confirm')) {
      if (!window.confirm(f.getAttribute('data-confirm') || 'Emin misiniz?')) {
        e.preventDefault();
        e.stopImmediatePropagation();
      }
    }
  }, true);

  /* ---------- Çift gönderim kilidi ---------- */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f || f.tagName !== 'FORM' || f.hasAttribute('data-no-lock')) { return; }
    if (e.defaultPrevented) { return; }
    if (f.getAttribute('data-submitting') === '1') {
      e.preventDefault();
      return;
    }
    f.setAttribute('data-submitting', '1');
    f.setAttribute('aria-busy', 'true');
    // Gönderen düğmenin değeri forma işlendikten sonra pasifleştirilir.
    setTimeout(function () {
      var btns = f.querySelectorAll('button[type="submit"], input[type="submit"]');
      for (var i = 0; i < btns.length; i++) { btns[i].disabled = true; }
    }, 0);
  });

  window.addEventListener('pageshow', function (e) {
    if (!e.persisted) { return; }
    var forms = document.querySelectorAll('form[data-submitting="1"]');
    for (var i = 0; i < forms.length; i++) {
      forms[i].removeAttribute('data-submitting');
      forms[i].removeAttribute('aria-busy');
      var btns = forms[i].querySelectorAll('button[type="submit"], input[type="submit"]');
      for (var j = 0; j < btns.length; j++) { btns[j].disabled = false; }
    }
  });

  /* ---------- Mobil arama aç/kapa ---------- */
  function initSearchToggle() {
    var panel = document.getElementById('ymSearch');
    var toggles = document.querySelectorAll('[data-search-toggle]');
    if (!panel || !toggles.length) { return; }

    function setOpen(open, focus) {
      panel.classList.toggle('is-open', open);
      for (var i = 0; i < toggles.length; i++) { toggles[i].setAttribute('aria-expanded', open ? 'true' : 'false'); }
      if (open && focus) {
        var input = panel.querySelector('input[type="search"], input[name="q"]');
        if (input) { input.focus(); }
      }
    }

    for (var i = 0; i < toggles.length; i++) {
      toggles[i].addEventListener('click', function () {
        setOpen(!panel.classList.contains('is-open'), true);
      });
    }
    panel.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && panel.classList.contains('is-open')) {
        setOpen(false, false);
        if (toggles[0]) { toggles[0].focus(); }
      }
    });
  }

  /* ---------- Duyuru şeridi ---------- */
  function initAnnouncement() {
    var bar = document.getElementById('ymAnnouncement');
    if (!bar) { return; }
    var KEY = 'ym_announcement_closed';

    try {
      if (window.sessionStorage.getItem(KEY) === '1') { bar.hidden = true; return; }
    } catch (e) { /* depolama yoksa şerit görünür kalır */ }

    var close = bar.querySelector('[data-announcement-dismiss]');
    if (close) {
      close.addEventListener('click', function () {
        bar.hidden = true;
        try { window.sessionStorage.setItem(KEY, '1'); } catch (e) { /* yoksay */ }
      });
    }

    var items = bar.querySelectorAll('.ym-announcement__item');
    if (items.length < 2) { return; }
    var idx = 0;
    var paused = false;

    function show(i) {
      for (var k = 0; k < items.length; k++) { items[k].hidden = k !== i; }
    }
    show(0);
    bar.addEventListener('mouseenter', function () { paused = true; });
    bar.addEventListener('mouseleave', function () { paused = false; });
    bar.addEventListener('focusin', function () { paused = true; });
    bar.addEventListener('focusout', function () { paused = false; });
    setInterval(function () {
      if (paused || document.hidden) { return; }
      idx = (idx + 1) % items.length;
      show(idx);
    }, 5000);
  }

  /* ---------- Hareketi azalt: slider kendiliğinden dönmesin ---------- */
  function initReducedMotion() {
    if (!window.matchMedia || !window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
    var cs = document.querySelectorAll('.carousel[data-bs-ride]');
    for (var i = 0; i < cs.length; i++) { cs[i].removeAttribute('data-bs-ride'); }
  }

  /* ---------- Sepete ekle ([data-add-to-cart]) ---------- */
  // İstemci sözleşmesi: POST /sepet/ekle {variant_id, quantity} -> {ok:true,data:{count}}.
  function initAddToCart() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('[data-add-to-cart]') : null;
      if (!btn || btn.disabled) { return; }
      e.preventDefault();
      var variantId = parseInt(btn.getAttribute('data-variant-id'), 10);
      if (!variantId || variantId < 1) { return; }
      var qty = parseInt(btn.getAttribute('data-quantity') || '1', 10);
      if (!qty || qty < 1) { qty = 1; }
      if (btn.getAttribute('data-busy') === '1') { return; }
      btn.setAttribute('data-busy', '1');
      btn.disabled = true;

      fetchJson('/sepet/ekle', { method: 'POST', body: { variant_id: variantId, quantity: qty } }).then(function (r) {
        btn.removeAttribute('data-busy');
        btn.disabled = false;
        if (r.ok) {
          if (r.data && typeof r.data.count !== 'undefined') {
            document.dispatchEvent(new CustomEvent('cart:changed', { detail: { count: r.data.count } }));
          }
          toast('Ürün sepetinize eklendi.', 'success');
        } else if (r.error && r.error.code !== 'network' && r.error.code !== 'unauthenticated' && r.error.code !== 'csrf' && r.error.code !== 'rate_limited') {
          toast(r.error.message || 'Ürün sepete eklenemedi.', 'danger');
        }
      });
    });
  }

  /* ---------- Dışa açılan API ---------- */
  App.csrfToken = csrfToken;
  App.fetchJson = fetchJson;
  App.toast = toast;
  App.debounce = debounce;
  App.formatMoney = formatMoney;
  App.setCartCount = setCartCount;
  App.applyWidths = applyWidths;

  function init() {
    initReducedMotion();
    applyWidths(document);
    initSearchToggle();
    initAnnouncement();
    initAddToCart();
    var badge = document.querySelector('[data-cart-count]');
    if (badge) { setCartCount(badge.getAttribute('data-count') || badge.textContent || 0); }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
