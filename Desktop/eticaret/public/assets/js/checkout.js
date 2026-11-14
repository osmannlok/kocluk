// public/assets/js/checkout.js
(function () {
    'use strict';

    var App = window.App;
    if (!App) {
        return;
    }

    var ADDRESS_URL = '/hesabim/adresler';
    var ORDER_SUMMARY_URL = '/odeme/ozet';

    function qs(sel, root) { return (root || document).querySelector(sel); }
    function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    function postJson(url, payload) {
        return App.fetchJson(url, {
            method: 'POST',
            body: JSON.stringify(payload),
            headers: { 'Content-Type': 'application/json' }
        });
    }

    function showStep(id) {
        var el = document.getElementById(id);
        if (el && window.bootstrap) {
            window.bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).show();
        }
        return el;
    }

    function hideStep(el) {
        if (el && window.bootstrap) {
            window.bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).hide();
        }
    }

    // ---------- Kopyala düğmeleri (tamamlandı sayfası) ----------
    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        var ok = false;
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.className = 'visually-hidden';
        document.body.appendChild(ta);
        ta.select();
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        document.body.removeChild(ta);
        return ok;
    }

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).then(function () { return true; }, function () { return fallbackCopy(text); });
        }
        return Promise.resolve(fallbackCopy(text));
    }

    function initCopy() {
        document.addEventListener('click', function (ev) {
            var btn = ev.target.closest('[data-copy]');
            if (!btn) { return; }
            copyText(btn.getAttribute('data-copy') || '').then(function (ok) {
                App.toast(ok ? 'Kopyalandı.' : 'Kopyalanamadı, lütfen elle seçin.', ok ? 'success' : 'error');
            });
        });
    }

    // ---------- Adres modalı ----------
    var modalEl = qs('#ymAddressModal');
    var addressForm = modalEl ? qs('#ymAddressForm', modalEl) : null;
    var modalInstance = null;
    var saving = false;
    var ADDRESS_FIELDS = ['title', 'full_name', 'phone', 'city', 'district', 'neighborhood', 'address_line',
        'postal_code', 'tc_identity_no', 'company_name', 'tax_office', 'tax_number'];

    function getModal() {
        if (!modalInstance && window.bootstrap && modalEl) {
            modalInstance = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        }
        return modalInstance;
    }

    function clearFormErrors() {
        qsa('.is-invalid', addressForm).forEach(function (el) { el.classList.remove('is-invalid'); });
        qsa('[data-error-for]', addressForm).forEach(function (el) { el.textContent = ''; });
        var box = qs('#ymAddressFormError', modalEl);
        if (box) { box.textContent = ''; box.classList.add('d-none'); }
    }

    function setInvoicePanels(type) {
        qsa('[data-invoice-panel]', addressForm).forEach(function (panel) {
            var active = panel.getAttribute('data-invoice-panel') === type;
            panel.hidden = !active;
            qsa('input,select,textarea', panel).forEach(function (input) { input.disabled = !active; });
        });
    }

    function openAddressModal(address) {
        if (!addressForm) { return; }
        clearFormErrors();
        addressForm.reset();
        addressForm.elements.id.value = address ? String(address.id) : '';
        var title = qs('[data-address-modal-title]', modalEl);
        if (title) { title.textContent = address ? 'Adresi düzenle' : 'Yeni adres'; }

        if (address) {
            ADDRESS_FIELDS.forEach(function (name) {
                if (addressForm.elements[name]) { addressForm.elements[name].value = address[name] || ''; }
            });
            addressForm.elements.is_default.checked = !!address.is_default;
        }
        var type = address && address.invoice_type === 'corporate' ? 'corporate' : 'individual';
        qsa('input[name="invoice_type"]', addressForm).forEach(function (r) { r.checked = r.value === type; });
        setInvoicePanels(type);

        var m = getModal();
        if (m) { m.show(); }
    }

    function collectAddress() {
        var payload = {};
        ADDRESS_FIELDS.forEach(function (name) {
            var el = addressForm.elements[name];
            if (el && !el.disabled) { payload[name] = String(el.value).trim(); }
        });
        var checked = qs('input[name="invoice_type"]:checked', addressForm);
        payload.invoice_type = checked ? checked.value : 'individual';
        payload.is_default = addressForm.elements.is_default.checked;
        return payload;
    }

    function showFormErrors(error) {
        var box = qs('#ymAddressFormError', modalEl);
        var errors = (error && error.errors) || {};
        var general = [];
        Object.keys(errors).forEach(function (field) {
            var msg = Array.isArray(errors[field]) ? errors[field][0] : errors[field];
            var holder = qs('[data-error-for="' + field + '"]', addressForm);
            var input = addressForm.elements[field];
            if (holder && input) {
                holder.textContent = String(msg);
                input.classList.add('is-invalid');
            } else {
                general.push(String(msg));
            }
        });
        if (general.length === 0 && Object.keys(errors).length === 0) {
            general.push((error && error.message) || 'Adres kaydedilemedi.');
        }
        if (box && general.length) {
            box.textContent = general.join(' ');
            box.classList.remove('d-none');
        }
    }

    function initAddressModal() {
        if (!addressForm) { return; }

        qsa('input[name="invoice_type"]', addressForm).forEach(function (r) {
            r.addEventListener('change', function () { setInvoicePanels(r.value); });
        });
        setInvoicePanels('individual');

        document.addEventListener('click', function (ev) {
            var newBtn = ev.target.closest('[data-address-new]');
            if (newBtn) {
                ev.preventDefault();
                openAddressModal(null);
                return;
            }
            // Hesap sayfası: düğme üzerinde JSON veri bulunur (data-address).
            var editBtn = ev.target.closest('[data-address-edit][data-address]');
            if (editBtn) {
                ev.preventDefault();
                try {
                    openAddressModal(JSON.parse(editBtn.getAttribute('data-address') || 'null'));
                } catch (e) {
                    App.toast('Adres okunamadı.', 'error');
                }
            }
        });

        addressForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            if (saving) { return; }
            clearFormErrors();

            var id = addressForm.elements.id.value;
            var url = id ? ADDRESS_URL + '/' + encodeURIComponent(id) + '/guncelle' : ADDRESS_URL;
            var btn = qs('[data-address-save]', addressForm);

            saving = true;
            if (btn) { btn.disabled = true; }
            postJson(url, collectAddress()).then(function (res) {
                saving = false;
                if (btn) { btn.disabled = false; }
                if (!res.ok) {
                    showFormErrors(res.error);
                    return;
                }
                var m = getModal();
                if (m) { m.hide(); }
                App.toast('Adres kaydedildi.', 'success');
                var newId = res.data && res.data.id ? Number(res.data.id) : 0;
                document.dispatchEvent(new CustomEvent('address:saved', { detail: { id: newId } }));
            });
        });
    }

    // ---------- Checkout sayfası ----------
    function initCheckout() {
        var form = qs('#ymCheckoutForm');
        if (!form) { return; }

        var listEl = qs('#ymAddressList');
        var tpl = qs('#ymAddressCardTpl');
        var emptyEl = qs('#ymAddressEmpty');
        var errorBox = qs('#ymCheckoutErrors');
        var blockingBox = qs('#ymBlockingErrors');
        var placeButtons = qsa('[data-place-order]');
        var addresses = [];
        var selectedId = 0;
        var locked = false;
        var summarySeq = 0;
        var hasBlocking = blockingBox ? !blockingBox.classList.contains('d-none') : false;

        function setField(node, name, text) {
            var el = qs('[data-f="' + name + '"]', node);
            if (el) { el.textContent = text; }
        }

        function renderAddresses() {
            listEl.textContent = '';
            addresses.forEach(function (a) {
                var node = tpl.content.firstElementChild.cloneNode(true);
                var radio = qs('input[type="radio"]', node);
                var label = qs('[data-f="label"]', node);
                radio.value = String(a.id);
                radio.id = 'ymAddr' + a.id;
                radio.checked = a.id === selectedId;
                label.setAttribute('for', radio.id);
                radio.addEventListener('change', function () { selectedId = a.id; });

                setField(node, 'title', a.title);
                setField(node, 'person', a.full_name + ' · ' + a.phone);
                var line = a.address_line + (a.neighborhood ? ', ' + a.neighborhood : '') + ', ' + a.district + ' / ' + a.city;
                setField(node, 'address', line);
                var def = qs('[data-f="default"]', node);
                if (def && a.is_default) { def.classList.remove('d-none'); }

                qs('[data-address-edit-card]', node).addEventListener('click', function () { openAddressModal(a); });
                listEl.appendChild(node);
            });
            if (emptyEl) { emptyEl.classList.toggle('d-none', addresses.length > 0); }
        }

        function loadInitial() {
            try { addresses = JSON.parse(listEl.getAttribute('data-initial') || '[]'); } catch (e) { addresses = []; }
            selectedId = parseInt(listEl.getAttribute('data-selected') || '0', 10) || 0;
            renderAddresses();
        }

        function refreshAddresses(preferId) {
            App.fetchJson(ADDRESS_URL + '/liste').then(function (res) {
                if (!res.ok || !res.data) {
                    App.toast('Adresler yenilenemedi.', 'error');
                    return;
                }
                addresses = res.data.addresses || [];
                selectedId = preferId || selectedId;
                if (!addresses.some(function (a) { return a.id === selectedId; })) {
                    selectedId = addresses.length ? addresses[0].id : 0;
                }
                renderAddresses();
            });
        }

        // ----- Fatura tipi -----
        function setBilling(type) {
            qsa('[data-billing-panel]', form).forEach(function (panel) {
                var active = panel.getAttribute('data-billing-panel') === type;
                panel.hidden = !active;
                qsa('input', panel).forEach(function (input) { input.disabled = !active; });
            });
        }

        // ----- Özet -----
        function money(v) { return App.formatMoney(v); }
        function isZero(v) { return parseFloat(String(v)) === 0; }

        function applySummary(s) {
            ['subtotal', 'discount_total', 'cod_fee', 'grand_total'].forEach(function (key) {
                qsa('[data-sum="' + key + '"]').forEach(function (el) { el.textContent = money(s[key]); });
            });
            qsa('[data-sum="shipping_total"]').forEach(function (el) {
                el.textContent = s.shipping_is_free ? 'Ücretsiz' : money(s.shipping_total);
            });
            qsa('[data-sum-row="discount_total"]').forEach(function (el) { el.classList.toggle('d-none', isZero(s.discount_total)); });
            qsa('[data-sum-row="cod_fee"]').forEach(function (el) { el.classList.toggle('d-none', isZero(s.cod_fee)); });

            var errors = Array.isArray(s.blocking_errors) ? s.blocking_errors : [];
            hasBlocking = errors.length > 0;
            if (blockingBox) {
                var ul = qs('ul', blockingBox);
                if (ul) {
                    ul.textContent = '';
                    errors.forEach(function (msg) {
                        var li = document.createElement('li');
                        li.textContent = String(msg);
                        ul.appendChild(li);
                    });
                }
                blockingBox.classList.toggle('d-none', !hasBlocking);
            }
            placeButtons.forEach(function (b) { b.disabled = hasBlocking || locked; });
        }

        function refreshSummary(method) {
            var seq = ++summarySeq;
            postJson(ORDER_SUMMARY_URL, { payment_method: method }).then(function (res) {
                if (seq !== summarySeq) { return; } // eski yanıt, daha yeni istekle ezilmesin
                if (!res.ok || !res.data) {
                    App.toast((res.error && res.error.message) || 'Sipariş özeti güncellenemedi.', 'error');
                    return;
                }
                applySummary(res.data);
            });
        }

        // ----- Doğrulama -----
        function validateStep(stepId) {
            var problems = [];
            if (stepId === 'ymStep1' && !qs('input[name="shipping_address_id"]:checked', form)) {
                problems.push({ step: 'ymStep1', msg: 'Lütfen bir teslimat adresi seçin veya yeni adres ekleyin.' });
            }
            if (stepId === 'ymStep2') {
                var type = (qs('input[name="billing_type"]:checked', form) || {}).value;
                if (type === 'corporate') {
                    ['billing_company_name', 'billing_tax_office', 'billing_tax_number'].forEach(function (name) {
                        var el = form.elements[name];
                        if (el && String(el.value).trim() === '') {
                            problems.push({ step: 'ymStep2', msg: 'Kurumsal fatura için şirket adı, vergi dairesi ve vergi numarası zorunludur.' });
                        }
                    });
                }
            }
            if (stepId === 'ymStep3' && !qs('input[name="payment_method"]:checked', form)) {
                problems.push({ step: 'ymStep3', msg: 'Lütfen bir ödeme yöntemi seçin.' });
            }
            if (stepId === 'ymStep5') {
                if (!form.elements.accept_distance_sales.checked) {
                    problems.push({ step: 'ymStep5', msg: 'Mesafeli Satış Sözleşmesi ve Ön Bilgilendirme Formu onayı zorunludur.' });
                }
                if (!form.elements.accept_kvkk.checked) {
                    problems.push({ step: 'ymStep5', msg: 'KVKK Aydınlatma Metni onayı zorunludur.' });
                }
            }
            return problems;
        }

        function showErrors(problems) {
            if (!errorBox) { return; }
            errorBox.textContent = '';
            var ul = document.createElement('ul');
            ul.className = 'mb-0';
            var seen = {};
            problems.forEach(function (p) {
                if (seen[p.msg]) { return; }
                seen[p.msg] = true;
                var li = document.createElement('li');
                li.textContent = p.msg;
                ul.appendChild(li);
            });
            errorBox.appendChild(ul);
            errorBox.classList.remove('d-none');
            showStep(problems[0].step);
            errorBox.focus();
            errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function lock() {
            locked = true;
            placeButtons.forEach(function (b) {
                b.disabled = true;
                b.setAttribute('aria-busy', 'true');
                b.textContent = 'Siparişiniz oluşturuluyor…';
            });
        }

        function unlock() {
            locked = false;
            placeButtons.forEach(function (b) {
                b.disabled = hasBlocking;
                b.removeAttribute('aria-busy');
                b.textContent = 'Siparişi onayla';
            });
        }

        // ----- Olaylar -----
        loadInitial();
        document.addEventListener('address:saved', function (ev) {
            refreshAddresses(ev.detail && ev.detail.id ? ev.detail.id : 0);
        });

        qsa('input[name="billing_type"]', form).forEach(function (r) {
            r.addEventListener('change', function () { setBilling(r.value); });
        });
        var currentType = qs('input[name="billing_type"]:checked', form);
        setBilling(currentType ? currentType.value : 'individual');

        qsa('input[name="payment_method"]', form).forEach(function (r) {
            r.addEventListener('change', function () { refreshSummary(r.value); });
        });

        qsa('[data-step-next]', form).forEach(function (btn) {
            btn.addEventListener('click', function () {
                var current = btn.closest('.accordion-collapse');
                var problems = current ? validateStep(current.id) : [];
                if (problems.length) {
                    showErrors(problems);
                    return;
                }
                if (errorBox) { errorBox.classList.add('d-none'); }
                showStep(btn.getAttribute('data-step-next'));
                hideStep(current);
            });
        });

        form.addEventListener('submit', function (ev) {
            if (locked || hasBlocking) {
                ev.preventDefault();
                return;
            }
            var problems = [];
            ['ymStep1', 'ymStep2', 'ymStep3', 'ymStep5'].forEach(function (id) {
                problems = problems.concat(validateStep(id));
            });
            if (problems.length) {
                ev.preventDefault();
                showErrors(problems);
                return;
            }
            lock(); // çift gönderim kilidi (sunucuda ayrıca tek kullanımlık nonce vardır)
        });

        // Geri tuşuyla dönüşte kilidi aç (önbellekten gelen sayfa).
        window.addEventListener('pageshow', function (ev) {
            if (ev.persisted) { unlock(); }
        });
    }

    initCopy();
    initAddressModal();
    initCheckout();
}());