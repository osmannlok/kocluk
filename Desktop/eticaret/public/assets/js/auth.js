// public/assets/js/auth.js
(function () {
  'use strict';

  function one(selector, root) {
    return (root || document).querySelector(selector);
  }

  function all(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  // Parola göster/gizle
  function initToggles() {
    all('[data-password-toggle]').forEach(function (button) {
      var input = one(button.getAttribute('data-password-toggle'));
      if (!input) {
        return;
      }
      button.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.textContent = show ? 'Gizle' : 'Göster';
        button.setAttribute('aria-pressed', show ? 'true' : 'false');
        button.setAttribute('aria-label', show ? 'Parolayı gizle' : 'Parolayı göster');
      });
    });
  }

  // Parola güç hesabı (yalnızca ipucu; asıl politika sunucudadır)
  function evaluate(password, email) {
    var rules = {
      length: password.length >= 8,
      mix: password.length > 0 && !/^\d+$/.test(password) && !/^\p{L}+$/u.test(password),
      email: true
    };

    if (email && password.length > 0) {
      var lowerEmail = email.toLowerCase();
      var lowerPw = password.toLowerCase();
      var local = lowerEmail.split('@')[0];
      rules.email = lowerPw.indexOf(lowerEmail) === -1 && !(local.length >= 4 && lowerPw.indexOf(local) !== -1);
    } else if (password.length === 0) {
      rules.email = false;
    }

    var score = 0;
    if (password.length > 0) {
      if (rules.length) { score += 1; }
      if (rules.mix) { score += 1; }
      if (rules.email) { score += 1; }

      var kinds = 0;
      if (/[a-zçğıöşü]/.test(password)) { kinds += 1; }
      if (/[A-ZÇĞİÖŞÜ]/.test(password)) { kinds += 1; }
      if (/\d/.test(password)) { kinds += 1; }
      if (/[^\p{L}\d]/u.test(password)) { kinds += 1; }
      if (kinds >= 3) { score += 1; }
      if (password.length >= 12) { score += 1; }
      if (!rules.length) { score = Math.min(score, 1); }
    }

    return { rules: rules, score: score };
  }

  var LEVELS = [
    { label: '', cls: '' },
    { label: 'Zayıf', cls: 'bg-danger' },
    { label: 'Orta', cls: 'bg-warning' },
    { label: 'İyi', cls: 'bg-info' },
    { label: 'İyi', cls: 'bg-info' },
    { label: 'Güçlü', cls: 'bg-success' }
  ];

  function initStrength() {
    all('[data-strength-meter]').forEach(function (meter) {
      var input = one(meter.getAttribute('data-target'));
      if (!input) {
        return;
      }
      var emailInput = meter.getAttribute('data-email') ? one(meter.getAttribute('data-email')) : null;
      var bar = one('[data-strength-bar]', meter);
      var label = one('[data-strength-label]', meter);
      var progress = one('[data-strength-progress]', meter);

      function update() {
        var result = evaluate(input.value, emailInput ? emailInput.value : '');
        var percent = result.score * 20;
        var level = LEVELS[result.score];

        if (bar) {
          bar.className = 'progress-bar ' + level.cls;
          bar.setAttribute('data-width', String(percent));
          bar.style.width = percent + '%';
        }
        if (progress) {
          progress.setAttribute('aria-valuenow', String(percent));
        }
        if (label) {
          label.textContent = level.label ? 'Parola gücü: ' + level.label : '';
        }
        all('[data-rule]', meter).forEach(function (item) {
          var ok = !!result.rules[item.getAttribute('data-rule')];
          item.classList.toggle('text-success', ok);
          item.classList.toggle('text-body-secondary', !ok);
        });
      }

      input.addEventListener('input', update);
      if (emailInput) {
        emailInput.addEventListener('input', update);
      }
      update();
    });
  }

  // Parola eşleşme kontrolü
  function initMatch() {
    all('input[data-match-with]').forEach(function (confirmInput) {
      var other = one(confirmInput.getAttribute('data-match-with'));
      var message = document.getElementById(confirmInput.getAttribute('data-match-message') || '');
      if (!other) {
        return;
      }

      function check() {
        var mismatch = confirmInput.value !== '' && confirmInput.value !== other.value;
        confirmInput.setCustomValidity(mismatch ? 'Parolalar eşleşmiyor.' : '');
        if (message) {
          message.textContent = mismatch ? 'Parolalar eşleşmiyor.' : '';
        }
      }

      confirmInput.addEventListener('input', check);
      other.addEventListener('input', check);
    });
  }

  // Gönderimde düğme kilidi (çift gönderimi önler)
  function initSubmitLock() {
    all('form[data-auth-form]').forEach(function (form) {
      var button = one('[type="submit"]', form);
      if (!button) {
        return;
      }
      var original = button.textContent;

      form.addEventListener('submit', function () {
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
          return;
        }
        button.disabled = true;
        button.textContent = button.getAttribute('data-loading-text') || original;
      });

      // Geri tuşuyla dönüldüğünde düğme yeniden açılsın.
      window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
          button.disabled = false;
          button.textContent = original;
        }
      });
    });
  }

  function init() {
    initToggles();
    initStrength();
    initMatch();
    initSubmitLock();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();