// public/assets/js/admin-page-editor.js
(function () {
  'use strict';

  var root = document.querySelector('[data-page-editor]');
  if (!root) {
    return;
  }

  var textarea = root.querySelector('[data-editor-input]');
  var panel = root.querySelector('[data-editor-panel]');
  var statusEl = root.querySelector('[data-editor-status]');
  var previewBtn = root.querySelector('[data-editor-preview]');
  var previewUrl = root.getAttribute('data-preview-url') || '';
  var csrf = root.getAttribute('data-csrf') || '';

  if (!textarea || !panel || !previewUrl) {
    return;
  }

  function setStatus(text) {
    if (statusEl) {
      statusEl.textContent = text;
    }
  }

  function selection() {
    return textarea.value.substring(textarea.selectionStart, textarea.selectionEnd);
  }

  function replaceSelection(text) {
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    textarea.setRangeText(text, start, end, 'end');
    textarea.focus();
    // Değişiklik korumasının (dirty guard) algılaması için.
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function wrap(open, close, placeholder) {
    var sel = selection();
    replaceSelection(open + (sel !== '' ? sel : placeholder) + close);
  }

  function list(tag) {
    var sel = selection();
    var lines = (sel !== '' ? sel : 'Öğe').split(/\r?\n/).filter(function (l) {
      return l.trim() !== '';
    });
    var items = lines.map(function (l) {
      return '  <li>' + l.trim() + '</li>';
    });
    replaceSelection('<' + tag + '>\n' + items.join('\n') + '\n</' + tag + '>');
  }

  function attrEscape(value) {
    return value
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function link() {
    var url = window.prompt('Bağlantı adresi (https://..., mailto:..., tel:... veya /yol):', 'https://');
    if (url === null) {
      return;
    }
    url = url.trim();
    // Yalnızca sunucunun da kabul ettiği şemalar; geri kalanı sunucuda zaten temizlenir.
    if (!/^(https?:\/\/|mailto:|tel:|\/(?!\/))/i.test(url)) {
      setStatus('Geçersiz bağlantı: yalnızca http(s), mailto, tel veya /yol.');
      return;
    }
    var sel = selection();
    var text = sel !== '' ? sel : 'bağlantı metni';
    replaceSelection('<a href="' + attrEscape(url) + '">' + text + '</a>');
  }

  var commands = {
    bold: function () { wrap('<strong>', '</strong>', 'kalın metin'); },
    italic: function () { wrap('<em>', '</em>', 'italik metin'); },
    h2: function () { wrap('<h2>', '</h2>', 'Başlık'); },
    h3: function () { wrap('<h3>', '</h3>', 'Alt başlık'); },
    ul: function () { list('ul'); },
    ol: function () { list('ol'); },
    quote: function () { wrap('<blockquote>', '</blockquote>', 'Alıntı'); },
    link: link
  };

  root.addEventListener('click', function (event) {
    var target = event.target;
    if (!(target instanceof Element)) {
      return;
    }
    var btn = target.closest('[data-editor-cmd]');
    if (!btn || !root.contains(btn)) {
      return;
    }
    var cmd = btn.getAttribute('data-editor-cmd');
    if (cmd && Object.prototype.hasOwnProperty.call(commands, cmd)) {
      event.preventDefault();
      commands[cmd]();
    }
  });

  function renderHtml(html) {
    // Sunucunun temizlediği HTML, innerHTML yerine DOMParser ile düğüm olarak aktarılır.
    var doc = new DOMParser().parseFromString(html, 'text/html');
    var nodes = Array.prototype.slice.call(doc.body.childNodes);
    if (nodes.length === 0) {
      panel.textContent = 'İçerik boş.';
      return;
    }
    panel.replaceChildren.apply(panel, nodes);
  }

  function preview() {
    setStatus('Önizleme hazırlanıyor…');
    if (previewBtn) {
      previewBtn.disabled = true;
    }

    fetch(previewUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrf,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ content: textarea.value })
    })
      .then(function (response) {
        return response.json().then(function (json) {
          return { status: response.status, json: json };
        }, function () {
          return { status: response.status, json: null };
        });
      })
      .then(function (result) {
        if (result.json && result.json.ok === true && result.json.data) {
          renderHtml(String(result.json.data.html || ''));
          setStatus('');
          return;
        }
        var message = 'Önizleme alınamadı.';
        if (result.status === 419) {
          message = 'Oturum süresi doldu; sayfayı yenileyin.';
        } else if (result.json && result.json.error && result.json.error.message) {
          message = String(result.json.error.message);
        }
        setStatus(message);
      })
      .catch(function () {
        setStatus('Ağ hatası: önizleme alınamadı.');
      })
      .then(function () {
        if (previewBtn) {
          previewBtn.disabled = false;
        }
      });
  }

  if (previewBtn) {
    previewBtn.addEventListener('click', preview);
  }
})();