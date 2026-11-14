// public/assets/js/cart.js
(function () {
  'use strict';

  const App = window.App;
  if (!App) { return; }

  const MAX_QTY = 20;
  const JSON_HEADERS = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
  const page = document.querySelector('[data-cart-page]');
  const mini = document.getElementById('ymCartOffcanvas');
  const qtyTimers = new Map();
  let internal = false; // kendi tetiklediğimiz cart:changed döngüsünü engeller
  let queue = Promise.resolve();

  // ---- İstek yardımcıları (istekler sırayla gider: sıra bozulmasın) ----
  function enqueue(task) {
    const p = queue.then(task);
    queue = p.catch(function () { /* sıra devam etsin */ });
    return p;
  }
  function post(url, payload) {
    return App.fetchJson(url, { method: 'POST', headers: JSON_HEADERS, body: JSON.stringify(payload || {}) });
  }
  function get(url) {
    return App.fetchJson(url, { method: 'GET' });
  }

  // ---- Para: yalnızca görüntü için tamsayı kuruş ----
  function toCents(v) {
    const m = /^(-?)(\d+)(?:\.(\d{1,2}))?$/.exec(String(v));
    if (!m) { return 0; }
    const c = parseInt(m[2], 10) * 100 + parseInt((m[3] || '').padEnd(2, '0') || '0', 10);
    return m[1] === '-' ? -c : c;
  }
  function fromCents(c) {
    const a = Math.abs(c);
    return (c < 0 ? '-' : '') + Math.floor(a / 100) + '.' + String(a % 100).padStart(2, '0');
  }
  function money(v) { return App.formatMoney(String(v)); }

  // ---- Küçük DOM yardımcıları (innerHTML KULLANILMAZ) ----
  function show(el, visible) { if (el) { el.classList.toggle('d-none', !visible); } }
  function field(node, name) { return node.querySelector('[data-field="' + name + '"]'); }
  function setText(el, text) { if (el) { el.textContent = text; } }
  function safeImage(path) {
    return typeof path === 'string' && (/^\/(?!\/)/.test(path) || /^https?:\/\//i.test(path)) ? path : null;
  }
  function safeSlug(slug) { return typeof slug === 'string' && /^[a-z0-9]+(-[a-z0-9]+)*$/.test(slug) ? slug : null; }
  function setCount(n) {
    internal = true;
    try { App.setCartCount(n); } finally { internal = false; }
  }
  function notifyError(res) {
    // 401/419/429 için App.fetchJson zaten uyarı gösterir.
    if (res && [401, 419, 429].indexOf(res.status) === -1) {
      App.toast((res.error && res.error.message) || 'İşlem tamamlanamadı.', 'danger');
    }
  }

  // ---- Liste eşitleme: düğümler korunur (odak kaybolmaz) ----
  function syncList(container, tpl, items, fill) {
    const existing = new Map();
    Array.prototype.forEach.call(container.children, function (n) { existing.set(n.dataset.lineVariant, n); });
    const seen = new Set();
    items.forEach(function (item) {
      const key = String(item.variant_id);
      seen.add(key);
      let node = existing.get(key);
      if (!node) {
        node = tpl.content.firstElementChild.cloneNode(true);
        node.dataset.lineVariant = key;
        container.appendChild(node);
      }
      fill(node, item);
    });
    existing.forEach(function (n, k) { if (!seen.has(k)) { n.remove(); } });
  }

  function fillImage(img, path) {
    const src = safeImage(path);
    if (src) { img.setAttribute('src', src); img.classList.remove('d-none'); } else { img.removeAttribute('src'); img.classList.add('d-none'); }
  }

  // ---- Sepet sayfası ----
  function fillLine(node, l) {
    const slug = safeSlug(l.slug);
    const href = slug ? '/urun/' + slug : '#';
    const name = field(node, 'name');
    setText(name, l.product_name);
    name.setAttribute('href', href);
    field(node, 'imageLink').setAttribute('href', href);
    fillImage(field(node, 'image'), l.image_path);
    setText(field(node, 'variant'), l.variant_name);
    setText(field(node, 'unit'), money(l.unit_price));
    const list = field(node, 'list');
    const onSale = toCents(l.list_price) > toCents(l.unit_price);
    setText(list, onSale ? money(l.list_price) : '');
    show(list, onSale);

    // Kullanıcı o sırada +/- yapıyorsa yerel adedi ezme.
    const pending = qtyTimers.has(String(l.variant_id));
    const qty = pending ? parseInt(node.dataset.qty, 10) : l.quantity;
    node.dataset.qty = String(qty);
    node.dataset.unitCents = String(toCents(l.unit_price));
    node.dataset.maxQty = String(Math.max(1, Math.min(l.available_qty, MAX_QTY)));
    setText(node.querySelector('[data-qty-value]'), String(qty));
    setText(field(node, 'lineTotal'), money(pending ? fromCents(toCents(l.unit_price) * qty) : l.line_total));
    node.querySelector('[data-qty-dec]').disabled = qty <= 1;
    node.querySelector('[data-qty-inc]').disabled = !l.available || qty >= parseInt(node.dataset.maxQty, 10);

    const note = field(node, 'note');
    let text = '';
    if (!l.available) { text = 'Bu ürün satışta değil veya stokta yok.'; }
    else if (l.quantity > l.available_qty) { text = 'Stokta en fazla ' + l.available_qty + ' adet var.'; }
    setText(note, text);
    show(note, text !== '');
  }

  function renderAlerts(root, s) {
    const box = root.querySelector('[data-alerts]');
    if (!box) { return; }
    box.replaceChildren();
    const add = function (cls, msg) {
      const d = document.createElement('div');
      d.className = 'alert ' + cls + ' py-2 small';
      d.setAttribute('role', 'alert');
      d.textContent = msg;
      box.appendChild(d);
    };
    (s.warnings || []).forEach(function (m) { add('alert-warning', m); });
    (s.blocking_errors || []).forEach(function (m) { add('alert-danger', m); });
  }

  function renderFree(root, s) {
    const fs = s.free_shipping || {};
    const box = root.querySelector('[data-free-box]');
    const enabled = toCents(fs.threshold || '0') > 0;
    show(box, enabled);
    if (!enabled) { return; }
    setText(root.querySelector('[data-free-text]'), fs.is_free
      ? 'Tebrikler! Ücretsiz kargo kazandınız.'
      : 'Ücretsiz kargo için ' + money(fs.remaining) + ' daha ekleyin.');
    const bar = root.querySelector('[data-free-bar]');
    const pct = Math.max(0, Math.min(100, parseInt(fs.percent, 10) || 0));
    bar.setAttribute('data-width', String(pct));
    root.querySelector('[data-free-progress]').setAttribute('aria-valuenow', String(pct));
    App.applyWidths(root);
  }

  function renderCoupon(root, s) {
    const code = s.coupon && s.coupon.code;
    show(root.querySelector('[data-coupon-form]'), !code);
    show(root.querySelector('[data-coupon-applied]'), !!code);
    setText(root.querySelector('[data-coupon-code]'), code || '');
  }

  function renderSums(s) {
    const set = function (name, text) {
      document.querySelectorAll('[data-sum="' + name + '"]').forEach(function (el) { el.textContent = text; });
    };
    set('subtotal', money(s.subtotal));
    set('discount_total', '-' + money(s.discount_total));
    set('shipping_total', s.shipping_is_free ? 'Ücretsiz' : money(s.shipping_total));
    set('grand_total', money(s.grand_total));
    const hasDiscount = toCents(s.discount_total) > 0;
    document.querySelectorAll('[data-row="discount"]').forEach(function (el) { show(el, hasDiscount); });
  }

  function renderCheckout(s) {
    const blocked = (s.blocking_errors || []).length > 0;
    document.querySelectorAll('[data-checkout]').forEach(function (a) {
      a.classList.toggle('disabled', blocked);
      a.setAttribute('aria-disabled', blocked ? 'true' : 'false');
      if (blocked) { a.setAttribute('tabindex', '-1'); } else { a.removeAttribute('tabindex'); }
    });
  }

  function renderPage(s) {
    const has = (s.lines || []).length > 0;
    show(page.querySelector('[data-cart-empty]'), !has);
    show(page.querySelector('[data-cart-content]'), has);
    show(document.querySelector('[data-sticky-bar]'), has);
    if (!has) { return; }
    syncList(page.querySelector('[data-cart-lines]'), document.getElementById('ymCartLineTpl'), s.lines, fillLine);
    renderAlerts(page, s);
    renderFree(page, s);
    renderCoupon(page, s);
    renderSums(s);
    renderCheckout(s);
  }

  // ---- Mini sepet ----
  function fillMini(node, l) {
    const slug = safeSlug(l.slug);
    const name = field(node, 'name');
    setText(name, l.product_name);
    name.setAttribute('href', slug ? '/urun/' + slug : '#');
    fillImage(field(node, 'image'), l.image_path);
    setText(field(node, 'variant'), l.variant_name);
    setText(field(node, 'qty'), String(l.quantity));
    setText(field(node, 'unit'), money(l.unit_price));
    setText(field(node, 'lineTotal'), money(l.line_total));
  }

  function renderMini(s) {
    const has = (s.lines || []).length > 0;
    show(mini.querySelector('[data-mini-empty]'), !has);
    show(mini.querySelector('[data-mini-footer]'), has);
    syncList(mini.querySelector('[data-mini-list]'), document.getElementById('ymMiniCartItemTpl'), s.lines || [], fillMini);
    setText(mini.querySelector('[data-mini-subtotal]'), money(s.subtotal));
    if (!page) { renderCheckout(s); }
  }

  function apply(s) {
    if (page) { renderPage(s); }
    if (mini) { renderMini(s); }
    setCount(s.count);
  }

  function loadSummary() {
    return enqueue(function () {
      return get('/sepet/ozet').then(function (res) {
        if (res.ok && res.data) { apply(res.data); }
        return res;
      });
    });
  }

  // Mutasyon sonucu: başarıda özet uygulanır, hatada mesaj + yeniden senkron.
  function handleMutation(res, okMessage) {
    if (res.ok && res.data) {
      apply(res.data);
      const msg = okMessage || null;
      if (msg) { App.toast(msg, 'success'); }
      return true;
    }
    notifyError(res);
    loadSummary();
    return false;
  }

  // ---- Adet ----
  function changeQty(node, delta) {
    const id = node.dataset.lineVariant;
    const current = parseInt(node.dataset.qty, 10) || 1;
    const max = parseInt(node.dataset.maxQty, 10) || MAX_QTY;
    const next = Math.max(1, Math.min(max, current + delta));
    if (next === current) { return; }

    // Anlık geri bildirim (yalnızca gösterim; gerçek toplam sunucudan gelir).
    node.dataset.qty = String(next);
    setText(node.querySelector('[data-qty-value]'), String(next));
    setText(field(node, 'lineTotal'), money(fromCents(parseInt(node.dataset.unitCents, 10) * next)));
    node.querySelector('[data-qty-dec]').disabled = next <= 1;
    node.querySelector('[data-qty-inc]').disabled = next >= max;

    clearTimeout(qtyTimers.get(id));
    qtyTimers.set(id, setTimeout(function () {
      qtyTimers.delete(id);
      enqueue(function () {
        return post('/sepet/guncelle', { variant_id: parseInt(id, 10), quantity: next }).then(function (res) {
          if (handleMutation(res, null) && res.data.message && res.data.message !== 'Sepet güncellendi.') {
            App.toast(res.data.message, 'warning');
          }
        });
      });
    }, 400));
  }

  function removeLine(btn) {
    const node = btn.closest('[data-line-variant]');
    if (!node) { return; }
    const id = parseInt(node.dataset.lineVariant, 10);
    clearTimeout(qtyTimers.get(String(id)));
    qtyTimers.delete(String(id));
    btn.disabled = true;
    enqueue(function () {
      return post('/sepet/sil', { variant_id: id }).then(function (res) {
        btn.disabled = false;
        handleMutation(res, 'Ürün sepetten çıkarıldı.');
      });
    });
  }

  // ---- Sepete ekle (data-add-to-cart OLMAYANLAR; onları app.js yönetir) ----
  function addToCart(el) {
    const id = parseInt(el.dataset.variantId, 10);
    const qty = parseInt(el.dataset.quantity || '1', 10);
    if (!(id > 0) || !(qty > 0) || el.getAttribute('aria-busy') === 'true') { return; }
    el.setAttribute('aria-busy', 'true');
    if ('disabled' in el) { el.disabled = true; }
    enqueue(function () {
      return post('/sepet/ekle', { variant_id: id, quantity: qty }).then(function (res) {
        el.removeAttribute('aria-busy');
        if ('disabled' in el) { el.disabled = false; }
        if (res.ok && res.data) {
          App.toast((res.data.message) || 'Ürün sepete eklendi.', 'success');
          setCount(res.data.count);
          document.dispatchEvent(new CustomEvent('cart:changed', { detail: { count: res.data.count } }));
        } else {
          notifyError(res);
        }
      });
    });
  }

  // ---- Kupon ----
  function setCouponFeedback(text) {
    const el = page && page.querySelector('[data-coupon-feedback]');
    if (!el) { return; }
    setText(el, text);
    show(el, text !== '');
  }

  function submitCoupon(form) {
    const input = form.querySelector('input[name="code"]');
    const code = input.value.trim().toUpperCase();
    if (code === '') { setCouponFeedback('Kupon kodunu girin.'); return; }
    setCouponFeedback('');
    enqueue(function () {
      return post('/sepet/kupon', { code: code }).then(function (res) {
        if (res.ok && res.data) {
          input.value = '';
          apply(res.data);
          App.toast(res.data.message || 'Kupon uygulandı.', 'success');
        } else if (res.status === 422 && res.error) {
          setCouponFeedback(res.error.message || 'Kupon uygulanamadı.');
        } else {
          notifyError(res);
        }
      });
    });
  }

  function removeCoupon() {
    enqueue(function () {
      return post('/sepet/kupon/kaldir', {}).then(function (res) { handleMutation(res, 'Kupon kaldırıldı.'); });
    });
  }

  // ---- Olaylar ----
  document.addEventListener('click', function (e) {
    const t = e.target instanceof Element ? e.target : null;
    if (!t) { return; }

    const checkout = t.closest('[data-checkout]');
    if (checkout && checkout.getAttribute('aria-disabled') === 'true') { e.preventDefault(); return; }

    const dec = t.closest('[data-qty-dec]');
    const inc = t.closest('[data-qty-inc]');
    if (dec || inc) {
      const node = t.closest('[data-line-variant]');
      if (node) { changeQty(node, dec ? -1 : 1); }
      return;
    }

    const rm = t.closest('[data-remove-line]');
    if (rm) { removeLine(rm); return; }

    if (t.closest('[data-coupon-remove]')) { removeCoupon(); return; }

    const add = t.closest('button[data-variant-id], a[data-variant-id]');
    if (add && !add.hasAttribute('data-add-to-cart')) { e.preventDefault(); addToCart(add); }
  });

  document.addEventListener('submit', function (e) {
    const form = e.target instanceof Element ? e.target.closest('[data-coupon-form]') : null;
    if (form) { e.preventDefault(); submitCoupon(form); }
  });

  document.addEventListener('cart:changed', function () {
    if (internal) { return; }
    if (page || (mini && mini.classList.contains('show'))) { loadSummary(); }
  });

  if (mini) {
    mini.addEventListener('show.bs.offcanvas', function () { loadSummary(); });
  }

  // ---- Başlangıç ----
  if (page) {
    try {
      const initial = JSON.parse(page.dataset.initial || 'null');
      if (initial) { apply(initial); }
    } catch (err) {
      loadSummary();
    }
  }
})();