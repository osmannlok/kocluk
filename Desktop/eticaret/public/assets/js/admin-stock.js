// public/assets/js/admin-stock.js

/**
 * Stok düzeltme modalı (resources/views/admin/stock/index.php ile eşleşir).
 * Güvenlik: kullanıcı verisi innerHTML'e yazılmaz (yalnızca textContent/DOM API); CSRF için X-CSRF-Token
 * başlığı formdaki _token alanından okunur. Burada yapılan doğrulama yalnızca kullanım kolaylığıdır:
 * asıl kurallar (not zorunlu, eksiye düşmeme, tür beyaz listesi, transaction) StockAdminController
 * ve StockService'tedir.
 */
(function () {
    'use strict';

    var modalEl = document.getElementById('stockModal');
    if (!modalEl) {
        return;
    }
    var form = modalEl.querySelector('[data-stock-form]');
    if (!form) {
        return;
    }

    var table = document.querySelector('[data-stock-table]');
    var statusMap = {};
    if (table) {
        try {
            statusMap = JSON.parse(table.getAttribute('data-status') || '{}') || {};
        } catch (e) {
            statusMap = {};
        }
    }

    var Admin = window.Admin || {};
    var MAX = 1000000;

    var adjustUrl = modalEl.getAttribute('data-adjust-url') || form.getAttribute('action') || '';
    var variantInput = form.elements.variant_id;
    var typeSelect = form.elements.type;
    var noteInput = form.elements.note;
    var deltaInput = form.elements.delta;
    var newInput = form.elements.new_quantity;
    var modeRadios = Array.prototype.slice.call(form.querySelectorAll('input[name="mode"]'));
    var submitBtn = form.querySelector('[data-stock-submit]');
    var errorBox = form.querySelector('[data-stock-error]');
    var targetEl = form.querySelector('[data-stock-target]');
    var currentEl = form.querySelector('[data-stock-current]');
    var resultEl = form.querySelector('[data-stock-result]');

    var currentQty = 0;
    var busy = false;

    function notify(message, type) {
        if (typeof Admin.toast === 'function') {
            Admin.toast(message, type || 'info');
        }
    }

    function csrfToken() {
        var input = form.querySelector('input[name="_token"]');
        return input ? input.value : '';
    }

    /** "12", "-3" gibi tam sayıları kabul eder; ondalık, boş veya harf içeren değer null döner. */
    function toInt(value) {
        var s = String(value === null || value === undefined ? '' : value).trim();
        if (!/^-?\d+$/.test(s)) {
            return null;
        }
        var n = parseInt(s, 10);
        return isFinite(n) ? n : null;
    }

    function currentMode() {
        for (var i = 0; i < modeRadios.length; i++) {
            if (modeRadios[i].checked) {
                return modeRadios[i].value;
            }
        }
        return 'delta';
    }

    function modeRadio(value) {
        for (var i = 0; i < modeRadios.length; i++) {
            if (modeRadios[i].value === value) {
                return modeRadios[i];
            }
        }
        return null;
    }

    function clearErrors() {
        form.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('[data-error-for]').forEach(function (el) {
            el.textContent = '';
        });
        if (errorBox) {
            errorBox.hidden = true;
            errorBox.textContent = '';
        }
    }

    function showFieldError(field, message) {
        var feedback = form.querySelector('[data-error-for="' + field + '"]');
        var input = form.elements[field];
        if (feedback && input) {
            feedback.textContent = message;
            input.classList.add('is-invalid');
            return true;
        }
        return false;
    }

    function showGeneralError(message) {
        if (errorBox) {
            errorBox.textContent = message;
            errorBox.hidden = false;
        }
    }

    /** Hata sözlüğünü alanlara dağıtır; alanı olmayan hatalar üstteki uyarı kutusuna yazılır. */
    function showErrors(errors) {
        var general = [];
        Object.keys(errors).forEach(function (field) {
            var raw = errors[field];
            var message = String(Array.isArray(raw) ? raw[0] : raw);
            if (!showFieldError(field, message)) {
                general.push(message);
            }
        });
        if (general.length > 0) {
            showGeneralError(general.join(' '));
        }
    }

    /** Hareket türüne ve kipe göre alanları açıp kapatır. */
    function applyMode() {
        var isPurchase = typeSelect.value === 'purchase';
        var newRadio = modeRadio('new_quantity');
        if (newRadio) {
            // Mal kabulde yalnızca değişim miktarı girilir (sunucu kuralıyla aynı).
            newRadio.disabled = isPurchase;
            if (isPurchase && newRadio.checked) {
                var deltaRadio = modeRadio('delta');
                if (deltaRadio) {
                    deltaRadio.checked = true;
                }
            }
        }

        var mode = currentMode();
        modalEl.querySelectorAll('[data-mode-group]').forEach(function (group) {
            var active = group.getAttribute('data-mode-group') === mode;
            group.hidden = !active;
            var input = group.querySelector('input');
            if (input) {
                input.disabled = !active;
            }
        });
        if (deltaInput) {
            deltaInput.min = isPurchase ? '1' : '';
        }
        updateResult();
    }

    /** Girilen değerden beklenen yeni stoğu hesaplar; geçersizse null döner. */
    function computeDelta() {
        if (currentMode() === 'new_quantity') {
            var q = toInt(newInput.value);
            return q === null ? null : q - currentQty;
        }
        return toInt(deltaInput.value);
    }

    function updateResult() {
        if (!resultEl) {
            return;
        }
        var delta = computeDelta();
        resultEl.classList.remove('text-danger');
        if (delta === null) {
            resultEl.textContent = '—';
            return;
        }
        var result = currentQty + delta;
        resultEl.textContent = String(result) + ' (' + (delta > 0 ? '+' : '') + delta + ')';
        if (result < 0) {
            resultEl.classList.add('text-danger');
        }
    }

    /** @returns {Object} alan => hata metni (boşsa geçerli) */
    function validate() {
        var errors = {};
        var isPurchase = typeSelect.value === 'purchase';

        if (toInt(variantInput.value) === null || toInt(variantInput.value) < 1) {
            errors.variant_id = 'Geçerli bir varyant seçin.';
        }

        if (noteInput.value.trim() === '') {
            errors.note = 'Not zorunludur.';
        } else if (noteInput.value.trim().length > 255) {
            errors.note = 'Not en çok 255 karakter olabilir.';
        }

        if (currentMode() === 'new_quantity') {
            var q = toInt(newInput.value);
            if (q === null || q < 0 || q > MAX) {
                errors.new_quantity = 'Yeni miktar 0 veya daha büyük bir tam sayı olmalıdır.';
            } else if (q - currentQty === 0) {
                errors.new_quantity = 'Stok değişmiyor; farklı bir miktar girin.';
            }
        } else {
            var d = toInt(deltaInput.value);
            if (d === null || Math.abs(d) > MAX) {
                errors.delta = 'Değişim miktarı geçerli bir tam sayı olmalıdır.';
            } else if (d === 0) {
                errors.delta = 'Stok değişmiyor; farklı bir miktar girin.';
            } else if (isPurchase && d < 0) {
                errors.delta = 'Mal kabul için pozitif miktar girin.';
            } else if (currentQty + d < 0) {
                errors.delta = 'Stok eksiye düşemez (mevcut stok: ' + currentQty + ').';
            }
        }

        return errors;
    }

    function setBusy(state) {
        busy = state;
        if (submitBtn) {
            submitBtn.disabled = state;
        }
    }

    /** JSON ucu: {ok:true,data} bekler; hatada message/errors taşıyan Error fırlatır. */
    function post(payload) {
        return fetch(adjustUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken()
            },
            body: JSON.stringify(payload)
        }).then(function (res) {
            return res.json().catch(function () {
                return null;
            }).then(function (body) {
                if (!res.ok || !body || body.ok !== true) {
                    var err = new Error(body && body.error && body.error.message
                        ? body.error.message : 'İşlem başarısız oldu.');
                    err.errors = body && body.error && body.error.errors ? body.error.errors : {};
                    throw err;
                }
                return body.data || {};
            });
        });
    }

    /** Tablo satırını sunucunun döndürdüğü gerçek stok ve durumla günceller. */
    function updateRow(data) {
        if (!table) {
            return;
        }
        var row = table.querySelector('tr[data-variant-id="' + String(parseInt(data.variant_id, 10)) + '"]');
        if (!row) {
            return;
        }
        var stockCell = row.querySelector('[data-stock-cell]');
        if (stockCell) {
            stockCell.textContent = String(data.stock_quantity);
        }
        var statusCell = row.querySelector('[data-status-cell]');
        var spec = statusMap[data.status];
        if (statusCell && spec) {
            var badge = document.createElement('span');
            badge.className = 'badge ' + String(spec.class || '');
            badge.textContent = String(spec.label || '');
            statusCell.replaceChildren(badge);
        }
        // Düzelten kişi değişikliği görsün: satır kısa süre vurgulanır.
        row.classList.add('table-active');
        window.setTimeout(function () {
            row.classList.remove('table-active');
        }, 2500);
    }

    function closeModal() {
        if (window.bootstrap && window.bootstrap.Modal) {
            var instance = window.bootstrap.Modal.getInstance(modalEl);
            if (instance) {
                instance.hide();
            }
        }
    }

    // Modal açılırken: tetikleyen düğmeden varyant bilgisi alınır, form sıfırlanır.
    modalEl.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        if (!trigger || !trigger.getAttribute) {
            return;
        }
        var id = trigger.getAttribute('data-variant-id') || '';
        var row = table ? table.querySelector('tr[data-variant-id="' + id.replace(/[^0-9]/g, '') + '"]') : null;
        var stockCell = row ? row.querySelector('[data-stock-cell]') : null;
        var parsed = stockCell ? toInt(stockCell.textContent) : null;

        currentQty = parsed === null ? 0 : parsed;
        form.reset();
        clearErrors();
        variantInput.value = id;
        if (targetEl) {
            targetEl.textContent = (trigger.getAttribute('data-product-name') || '') + ' — '
                + (trigger.getAttribute('data-variant-name') || '')
                + ' (' + (trigger.getAttribute('data-sku') || '') + ')';
        }
        if (currentEl) {
            currentEl.textContent = String(currentQty);
        }
        var deltaRadio = modeRadio('delta');
        if (deltaRadio) {
            deltaRadio.checked = true;
        }
        applyMode();
    });

    modalEl.addEventListener('shown.bs.modal', function () {
        var first = currentMode() === 'new_quantity' ? newInput : deltaInput;
        if (first) {
            first.focus();
        }
    });

    typeSelect.addEventListener('change', applyMode);
    modeRadios.forEach(function (radio) {
        radio.addEventListener('change', applyMode);
    });
    [deltaInput, newInput].forEach(function (input) {
        if (input) {
            input.addEventListener('input', updateResult);
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (busy) {
            return;
        }

        clearErrors();
        var errors = validate();
        if (Object.keys(errors).length > 0) {
            showErrors(errors);
            return;
        }

        // Yalnızca etkin kipin alanı gönderilir: sunucu "ikisi birden"i reddeder.
        var payload = {
            variant_id: toInt(variantInput.value),
            type: typeSelect.value,
            note: noteInput.value.trim()
        };
        if (currentMode() === 'new_quantity') {
            payload.new_quantity = toInt(newInput.value);
        } else {
            payload.delta = toInt(deltaInput.value);
        }

        setBusy(true);
        post(payload).then(function (data) {
            updateRow(data);
            closeModal();
            notify('Stok güncellendi (yeni stok: ' + data.stock_quantity + ').', 'success');
        }).catch(function (err) {
            var fieldErrors = err.errors || {};
            if (Object.keys(fieldErrors).length > 0) {
                showErrors(fieldErrors);
            } else {
                showGeneralError(err.message);
            }
        }).then(function () {
            setBusy(false);
        });
    });
})();