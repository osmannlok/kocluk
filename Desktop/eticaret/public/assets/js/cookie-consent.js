// public/assets/js/cookie-consent.js
// Çerez çubuğu: "Kabul et" / "Yalnızca zorunlu". Tercih localStorage + SameSite=Lax çerezde 12 ay saklanır.
(function () {
  'use strict';

  var App = window.App = window.App || {};
  var KEY = 'ym_consent';
  var listeners = [];
  var state = null;

  function isHttps() { return window.location.protocol === 'https:'; }

  function readCookie() {
    var m = document.cookie.match(/(?:^|;\s*)ym_consent=(all|necessary)(?:;|$)/);
    return m ? m[1] : null;
  }

  function writeCookie(level) {
    var d = new Date();
    d.setMonth(d.getMonth() + 12);
    // SameSite=Lax; HTTPS'te Secure. Çerez yalnızca tercih düzeyini taşır, kişisel veri içermez.
    document.cookie = KEY + '=' + level + '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax' + (isHttps() ? '; Secure' : '');
  }

  function load() {
    var s = null;
    try {
      var raw = window.localStorage.getItem(KEY);
      if (raw) {
        var o = JSON.parse(raw);
        if (o && (o.level === 'all' || o.level === 'necessary') && o.ts) {
          var age = Date.now() - new Date(o.ts).getTime();
          if (isFinite(age) && age >= 0 && age < 366 * 24 * 3600 * 1000) { s = { level: o.level, all: o.level === 'all', ts: o.ts }; }
        }
      }
    } catch (e) { s = null; }

    if (!s) {
      var c = readCookie();
      if (c) { s = { level: c, all: c === 'all', ts: new Date().toISOString() }; }
    }
    return s;
  }

  function save(level) {
    var ts = new Date().toISOString();
    try { window.localStorage.setItem(KEY, JSON.stringify({ level: level, ts: ts })); } catch (e) { /* yoksay */ }
    writeCookie(level);
    state = { level: level, all: level === 'all', ts: ts };
    var cbs = listeners.slice();
    for (var i = 0; i < cbs.length; i++) {
      try { cbs[i](state); } catch (e) { /* bir kanca diğerlerini engellemesin */ }
    }
  }

  // Tercih verildiğinde (veya zaten verilmişse hemen) cb({level, all, ts}) çağrılır.
  App.onConsent = function (cb) {
    if (typeof cb !== 'function') { return; }
    if (state) {
      try { cb(state); } catch (e) { /* yoksay */ }
    }
    listeners.push(cb);
  };

  App.getConsent = function () { return state; };

  function init() {
    var banner = document.getElementById('ymCookieBanner');
    state = load();

    if (!banner) { return; }

    if (!state) { banner.hidden = false; }

    banner.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('[data-consent]') : null;
      if (!btn) { return; }
      var level = btn.getAttribute('data-consent') === 'all' ? 'all' : 'necessary';
      save(level);
      banner.hidden = true;
    });

    // Tercihi yeniden açmak için: <a data-consent-open>
    document.addEventListener('click', function (e) {
      var a = e.target.closest ? e.target.closest('[data-consent-open]') : null;
      if (!a) { return; }
      e.preventDefault();
      banner.hidden = false;
      var first = banner.querySelector('[data-consent]');
      if (first) { first.focus(); }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
