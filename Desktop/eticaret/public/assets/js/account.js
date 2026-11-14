// public/assets/js/account.js
(function () {
  'use strict';

  var App = window.App || null;

  function toast(message, type) {
    if (App && typeof App.toast === 'function') {
      App.toast(message, type || 'info');
    }
  }

  // --- Onaylı form gönderimi (sipariş iptali vb.) ---
  // Yakalama aşamasında çalışır: iptal edilirse app.js gönderim kilidi devreye girmez.
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm-submit')) {
      return;
    }
    var message = form.getAttribute('data-confirm-submit') || 'Devam etmek istiyor musunuz?';
    if (!window.confirm(message)) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  // --- Kopyalama (kargo takip no, IBAN) ---
  function fallbackCopy(text) {
    var area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.className = 'visually-hidden';
    document.body.appendChild(area);
    area.select();
    var ok = false;
    try {
      ok = document.execCommand('copy');
    } catch (e) {
      ok = false;
    }
    document.body.removeChild(area);
    return ok;
  }

  function copyText(text) {
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      return navigator.clipboard.writeText(text).then(function () {
        return true;
      }, function () {
        return fallbackCopy(text);
      });
    }
    return Promise.resolve(fallbackCopy(text));
  }

  document.addEventListener('click', function (event) {
    var target = event.target;
    if (!(target instanceof Element)) {
      return;
    }

    var copyBtn = target.closest('[data-copy-text]');
    if (copyBtn) {
      var text = copyBtn.getAttribute('data-copy-text') || '';
      var label = copyBtn.getAttribute('data-copy-label') || 'Kopyalandı.';
      if (text === '') {
        return;
      }
      copyText(text).then(function (ok) {
        toast(ok ? label : 'Kopyalanamadı, lütfen elle kopyalayın.', ok ? 'success' : 'danger');
      });
      return;
    }

    var deleteBtn = target.closest('[data-address-delete]');
    if (deleteBtn) {
      handleDelete(deleteBtn);
      return;
    }

    var defaultBtn = target.closest('[data-address-default]');
    if (defaultBtn) {
      handleDefault(defaultBtn);
    }
  });

  // --- Adres sayfası ---
  function postNoBody(url) {
    if (!App || typeof App.fetchJson !== 'function') {
      return Promise.resolve(null);
    }
    return App.fetchJson(url, { method: 'POST' });
  }

  function reportFailure(result) {
    if (!result) {
      toast('İşlem şu anda yapılamıyor.', 'danger');
      return;
    }
    // 401/419/429 için app.js zaten bildirim gösterir.
    if (result.status === 401 || result.status === 419 || result.status === 429) {
      return;
    }
    var message = result.error && result.error.message ? result.error.message : 'İşlem tamamlanamadı.';
    toast(message, 'danger');
  }

  function handleDelete(button) {
    var title = button.getAttribute('data-title') || 'Bu adres';
    var url = button.getAttribute('data-url');
    if (!url || !window.confirm('"' + title + '" adresini silmek istediğinize emin misiniz?')) {
      return;
    }
    button.disabled = true;
    postNoBody(url).then(function (result) {
      if (!result || !result.ok) {
        button.disabled = false;
        reportFailure(result);
        return;
      }
      var card = button.closest('[data-address-card]');
      var wasDefault = card ? card.querySelector('[data-default-badge]') !== null : false;
      if (card && card.parentNode) {
        card.parentNode.removeChild(card);
      }
      toast('Adres silindi.', 'success');
      // Varsayılan silindiyse veya liste boşaldıysa sayfayı yenile (rozet/boş durum).
      if (wasDefault || document.querySelector('[data-address-card]') === null) {
        window.setTimeout(function () {
          window.location.reload();
        }, 600);
      }
    });
  }

  function handleDefault(button) {
    var url = button.getAttribute('data-url');
    if (!url) {
      return;
    }
    button.disabled = true;
    postNoBody(url).then(function (result) {
      if (!result || !result.ok) {
        button.disabled = false;
        reportFailure(result);
        return;
      }
      window.location.reload();
    });
  }

  // Modal (checkout.js) kaydı tamamlayınca liste yenilenir.
  document.addEventListener('address:saved', function () {
    window.location.reload();
  });
})();