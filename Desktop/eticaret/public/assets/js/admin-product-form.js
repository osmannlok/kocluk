// public/assets/js/admin-product-form.js

/**
 * Ürün formu davranışları (resources/views/admin/products/form.php ile eşleşir).
 * Güvenlik: kullanıcı verisi innerHTML'e yazılmaz (yalnızca textContent/DOM API); CSRF için X-CSRF-Token
 * başlığı formdaki _token alanından okunur. Fiyat karşılaştırması float değil, tamsayı kuruş üzerindendir.
 * Not: HTML5 sürükle-bırak dokunmatik cihazlarda çalışmaz; görsel sıralama için klavyede Alt+Sol/Sağ ok da desteklenir.
 * Kaydetmeden çıkış uyarısı admin.js'teki form[data-dirty-guard] ile sağlanır.
 */
(function () {
    'use strict';

    var form = document.querySelector('form[data-dirty-guard]');
    if (!form) {
        return;
    }

    var Admin = window.Admin || {};

    function notify(message, type) {
        if (typeof Admin.toast === 'function') {
            Admin.toast(message, type || 'info');
        }
    }

    function ask(message) {
        return typeof Admin.confirmAction === 'function' ? Admin.confirmAction(message) : window.confirm(message);
    }

    function csrfToken() {
        var input = form.querySelector('input[name="_token"]');
        return input ? input.value : '';
    }

    /** JSON uçları için fetch yardımcısı: {ok:true,data} bekler, hatada message/errors taşıyan Error fırlatır. */
    function api(url, options) {
        var headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken()
        };
        var init = { method: 'POST', headers: headers, credentials: 'same-origin' };
        if (options && options.json !== undefined) {
            headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(options.json);
        } else if (options && options.form) {
            init.body = options.form;
        }

        return fetch(url, init).then(function (res) {
            return res.json().catch(function () {
                return null;
            }).then(function (payload) {
                if (!res.ok || !payload || payload.ok !== true) {
                    var err = new Error(payload && payload.error && payload.error.message
                        ? payload.error.message : 'İşlem başarısız oldu.');
                    err.status = res.status;
                    err.errors = payload && payload.error && payload.error.errors ? payload.error.errors : {};
                    throw err;
                }
                return payload.data || {};
            });
        });
    }

    function firstError(err) {
        var e = err.errors || {};
        var keys = Object.keys(e);
        if (keys.length > 0) {
            var v = e[keys[0]];
            return String(Array.isArray(v) ? v[0] : v);
        }
        return err.message;
    }

    // ------------------------------------------------------------------
    // Slug önerisi
    // ------------------------------------------------------------------
    var trMap = {
        'ç': 'c', 'Ç': 'c', 'ğ': 'g', 'Ğ': 'g', 'ı': 'i', 'İ': 'i', 'I': 'i',
        'ö': 'o', 'Ö': 'o', 'ş': 's', 'Ş': 's', 'ü': 'u', 'Ü': 'u'
    };

    function slugify(text) {
        return text
            .replace(/[çÇğĞıİIöÖşŞüÜ]/g, function (c) {
                return trMap[c];
            })
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 220);
    }

    function initSlug() {
        var source = form.querySelector('[data-slug-source]');
        var target = form.querySelector('[data-slug-target]');
        if (!source || !target) {
            return;
        }
        // Slug boşsa otomatik önerilir; kullanıcı elle değiştirdiyse veya kayıtlı slug varsa dokunulmaz.
        var auto = target.value.trim() === '';
        target.addEventListener('input', function () {
            auto = target.value.trim() === '';
        });
        source.addEventListener('input', function () {
            if (auto) {
                target.value = slugify(source.value);
            }
        });
    }

    // ------------------------------------------------------------------
    // Varyantlar
    // ------------------------------------------------------------------
    var variantList = form.querySelector('[data-variant-list]');
    var variantTpl = form.querySelector('template[data-variant-template]');

    function rowsOf() {
        if (!variantList) {
            return [];
        }
        return Array.prototype.filter.call(variantList.children, function (el) {
            return el.hasAttribute('data-variant-row');
        });
    }

    /** Alan adı ve id'leri sıraya göre yeniden numaralar (PHP variants[i][alan] biçimi). */
    function reindex() {
        rowsOf().forEach(function (row, i) {
            row.querySelectorAll('[name]').forEach(function (el) {
                el.name = el.name.replace(/^variants\[[^\]]*\]/, 'variants[' + i + ']');
            });
            row.querySelectorAll('[id]').forEach(function (el) {
                el.id = el.id.replace(/^v(?:\d+|__INDEX__)_/, 'v' + i + '_');
            });
            row.querySelectorAll('label[for]').forEach(function (label) {
                label.htmlFor = label.htmlFor.replace(/^v(?:\d+|__INDEX__)_/, 'v' + i + '_');
            });
            var sort = row.querySelector('[data-variant-sort]');
            if (sort) {
                sort.value = String(i);
            }
            var title = row.querySelector('[data-variant-title]');
            var name = row.querySelector('input[name$="[name]"]');
            if (title) {
                title.textContent = 'Varyant ' + (i + 1) + (name && name.value.trim() !== '' ? ' — ' + name.value.trim() : '');
            }
        });
    }

    /** Tam bir aktif varsayılan varyant olmasını sağlar. */
    function ensureDefault(changed) {
        var rows = rowsOf();
        if (rows.length === 0) {
            return;
        }
        var boxes = rows.map(function (r) {
            return r.querySelector('[data-variant-default]');
        });
        var actives = rows.map(function (r) {
            var a = r.querySelector('input[name$="[is_active]"]');
            return !a || a.checked;
        });

        var idx = changed ? boxes.indexOf(changed) : -1;
        if (idx < 0 || !actives[idx]) {
            idx = -1;
            for (var i = 0; i < boxes.length; i++) {
                if (boxes[i] && boxes[i].checked && actives[i]) {
                    idx = i;
                    break;
                }
            }
        }
        if (idx < 0) {
            idx = actives.indexOf(true);
        }
        if (idx < 0) {
            idx = 0;
        }
        boxes.forEach(function (b, i) {
            if (b) {
                b.checked = i === idx;
            }
        });
    }

    /** "1234,50" / "1234.5" -> kuruş (tamsayı); geçersizse null. */
    function toCents(value) {
        var s = String(value).trim().replace(/\s+/g, '').replace(',', '.');
        var m = /^(\d{1,10})(?:\.(\d{1,2}))?$/.exec(s);
        if (!m) {
            return null;
        }
        return parseInt(m[1], 10) * 100 + parseInt((m[2] || '').padEnd(2, '0') || '0', 10);
    }

    function mark(input, message) {
        input.setCustomValidity(message);
        input.classList.toggle('is-invalid', message !== '');
        var holder = input.parentElement;
        var fb = holder ? holder.querySelector('.invalid-feedback[data-client]') : null;
        if (message !== '') {
            if (!fb && holder) {
                fb = document.createElement('div');
                fb.className = 'invalid-feedback';
                fb.setAttribute('data-client', '1');
                holder.appendChild(fb);
            }
            if (fb) {
                fb.textContent = message;
            }
        } else if (fb) {
            fb.remove();
        }
    }

    function validateRow(row) {
        var price = row.querySelector('[data-price]');
        var sale = row.querySelector('[data-sale-price]');
        var start = row.querySelector('input[name$="[sale_starts_at]"]');
        var end = row.querySelector('input[name$="[sale_ends_at]"]');
        var ok = true;

        if (price && sale) {
            var msg = '';
            if (sale.value.trim() !== '') {
                var sc = toCents(sale.value);
                var pc = toCents(price.value);
                if (sc === null) {
                    msg = 'İndirimli fiyat geçersiz.';
                } else if (pc !== null && sc >= pc) {
                    msg = 'İndirimli fiyat, normal fiyattan düşük olmalıdır.';
                }
            }
            mark(sale, msg);
            ok = ok && msg === '';
        }

        if (start && end) {
            var dmsg = '';
            if (start.value !== '' && end.value !== '' && start.value >= end.value) {
                dmsg = 'İndirim bitişi, başlangıçtan sonra olmalıdır.';
            }
            mark(end, dmsg);
            ok = ok && dmsg === '';
        }

        return ok;
    }

    function validateSkus() {
        var seen = {};
        var ok = true;
        rowsOf().forEach(function (row) {
            var sku = row.querySelector('input[name$="[sku]"]');
            if (!sku) {
                return;
            }
            var key = sku.value.trim().toLowerCase();
            var msg = '';
            if (key !== '' && seen[key]) {
                msg = 'Aynı SKU bu formda birden fazla kez kullanılmış.';
                ok = false;
            }
            seen[key] = true;
            mark(sku, msg);
        });
        return ok;
    }

    function showVariantsTab() {
        var btn = document.getElementById('tabVariantsBtn');
        if (btn) {
            btn.click();
        }
    }

    function initVariants() {
        if (!variantList || !variantTpl) {
            return;
        }

        reindex();
        ensureDefault(null);

        var addBtn = form.querySelector('[data-variant-add]');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                variantList.appendChild(variantTpl.content.cloneNode(true));
                reindex();
                ensureDefault(null);
                var rows = rowsOf();
                var focus = rows[rows.length - 1].querySelector('input[name$="[name]"]');
                if (focus) {
                    focus.focus();
                }
            });
        }

        variantList.addEventListener('click', function (e) {
            var row = e.target.closest('[data-variant-row]');
            if (!row) {
                return;
            }
            var rows = rowsOf();
            var idx = rows.indexOf(row);

            if (e.target.closest('[data-variant-remove]')) {
                if (rows.length <= 1) {
                    notify('En az bir varyant gerekli.', 'danger');
                    return;
                }
                var existing = row.querySelector('input[name$="[id]"]') !== null;
                var text = existing
                    ? 'Bu varyant formdan çıkarılacak ve kayıtta pasifleştirilecek. Devam edilsin mi?'
                    : 'Bu varyant kaldırılsın mı?';
                if (!ask(text)) {
                    return;
                }
                row.remove();
                reindex();
                ensureDefault(null);
            } else if (e.target.closest('[data-variant-up]')) {
                if (idx > 0) {
                    variantList.insertBefore(row, rows[idx - 1]);
                    reindex();
                }
            } else if (e.target.closest('[data-variant-down]')) {
                if (idx >= 0 && idx < rows.length - 1) {
                    variantList.insertBefore(rows[idx + 1], row);
                    reindex();
                }
            }
        });

        variantList.addEventListener('change', function (e) {
            var t = e.target;
            if (t.matches('[data-variant-default]')) {
                ensureDefault(t.checked ? t : null);
            } else if (t.matches('input[name$="[is_active]"]')) {
                ensureDefault(null);
            }
        });

        variantList.addEventListener('input', function (e) {
            var t = e.target;
            var row = t.closest('[data-variant-row]');
            if (!row) {
                return;
            }
            if (t.matches('[data-price], [data-sale-price], input[name$="[sale_starts_at]"], input[name$="[sale_ends_at]"]')) {
                validateRow(row);
            } else if (t.matches('input[name$="[sku]"]')) {
                validateSkus();
            } else if (t.matches('input[name$="[name]"]')) {
                reindex();
            }
        });

        // İstemci doğrulaması yalnızca kullanım kolaylığıdır; asıl doğrulama sunucudadır.
        form.addEventListener('submit', function (e) {
            ensureDefault(null);
            var ok = validateSkus();
            rowsOf().forEach(function (row) {
                ok = validateRow(row) && ok;
            });
            if (!ok) {
                e.preventDefault();
                showVariantsTab();
                window.requestAnimationFrame(function () {
                    var bad = form.querySelector('.is-invalid');
                    if (bad) {
                        bad.focus();
                    }
                });
                notify('Varyant alanlarındaki hataları düzeltin.', 'danger');
            }
        });
    }

    // ------------------------------------------------------------------
    // Görseller
    // ------------------------------------------------------------------
    function initImages() {
        var list = form.querySelector('[data-image-list]');
        var input = form.querySelector('[data-image-input]');
        var tpl = form.querySelector('template[data-image-template]');
        if (!list || !input || !tpl) {
            return;
        }

        var progress = form.querySelector('[data-upload-progress]');
        var errorBox = form.querySelector('[data-upload-error]');
        var uploadUrl = input.getAttribute('data-upload-url') || '';
        var limit = parseInt(list.getAttribute('data-limit') || '12', 10);
        var allowed = ['image/jpeg', 'image/png', 'image/webp'];

        function items() {
            return Array.prototype.slice.call(list.querySelectorAll('[data-image-item]'));
        }

        function ids() {
            return items().map(function (el) {
                return el.getAttribute('data-image-id') || '';
            });
        }

        function itemUrl(attr, id) {
            return (list.getAttribute(attr) || '').replace('__ID__', encodeURIComponent(id));
        }

        function syncLimit() {
            input.disabled = items().length >= limit;
        }

        function showError(message) {
            if (!errorBox) {
                return;
            }
            errorBox.textContent = message;
            errorBox.hidden = message === '';
        }

        function setPrimaryUi(item) {
            list.querySelectorAll('[data-primary-badge]').forEach(function (b) {
                b.hidden = true;
            });
            var badge = item.querySelector('[data-primary-badge]');
            if (badge) {
                badge.hidden = false;
            }
        }

        function prepareItem(item) {
            item.setAttribute('tabindex', '0');
            item.setAttribute('draggable', 'true');
        }

        function addItem(image) {
            var fragment = tpl.content.cloneNode(true);
            var item = fragment.querySelector('[data-image-item]');
            if (!item) {
                return;
            }
            item.setAttribute('data-image-id', String(image.id));
            var img = item.querySelector('img');
            if (img) {
                img.setAttribute('src', image.thumb_path || image.path);
                img.setAttribute('alt', image.alt_text || '');
            }
            var badge = item.querySelector('[data-primary-badge]');
            if (badge) {
                badge.hidden = !image.is_primary;
            }
            prepareItem(item);
            list.appendChild(item);
            syncLimit();
        }

        items().forEach(prepareItem);
        syncLimit();

        // Dosya seçimi kayda hazır form değişikliği sayılmasın (dirty-guard tetiklenmesin).
        input.addEventListener('input', function (e) {
            e.stopPropagation();
        });
        input.addEventListener('change', function (e) {
            e.stopPropagation();
            var files = Array.prototype.slice.call(input.files || []);
            input.value = '';
            if (files.length === 0) {
                return;
            }

            var room = limit - items().length;
            if (files.length > room) {
                notify('En çok ' + limit + ' görsel eklenebilir.', 'danger');
                files = files.slice(0, Math.max(room, 0));
            }
            uploadFiles(files);
        });

        function uploadFiles(files) {
            showError('');
            if (progress) {
                progress.max = files.length;
                progress.value = 0;
                progress.hidden = false;
            }
            input.disabled = true;

            var errors = [];
            var chain = Promise.resolve();
            files.forEach(function (file) {
                chain = chain.then(function () {
                    if (allowed.indexOf(file.type) === -1) {
                        errors.push(file.name + ': yalnızca JPG, PNG ve WEBP yüklenebilir.');
                        return null;
                    }
                    var fd = new FormData();
                    fd.append('image', file);

                    return api(uploadUrl, { form: fd }).then(function (data) {
                        if (data.image) {
                            addItem(data.image);
                        }
                    }).catch(function (err) {
                        errors.push(file.name + ': ' + firstError(err));
                    });
                }).then(function () {
                    if (progress) {
                        progress.value = progress.value + 1;
                    }
                });
            });

            chain.then(function () {
                if (progress) {
                    progress.hidden = true;
                }
                syncLimit();
                if (errors.length > 0) {
                    showError(errors.join(' | '));
                }
            });
        }

        // Ana yap / Sil (olay delegasyonu)
        list.addEventListener('click', function (e) {
            var item = e.target.closest('[data-image-item]');
            if (!item) {
                return;
            }
            var id = item.getAttribute('data-image-id') || '';
            var primaryBtn = e.target.closest('[data-image-primary]');
            var deleteBtn = e.target.closest('[data-image-delete]');

            if (primaryBtn) {
                primaryBtn.disabled = true;
                api(itemUrl('data-primary-url', id), {}).then(function () {
                    setPrimaryUi(item);
                }).catch(function (err) {
                    notify(firstError(err), 'danger');
                }).then(function () {
                    primaryBtn.disabled = false;
                });
            } else if (deleteBtn) {
                if (!ask('Bu görsel silinsin mi?')) {
                    return;
                }
                deleteBtn.disabled = true;
                var badge = item.querySelector('[data-primary-badge]');
                var wasPrimary = badge ? !badge.hidden : false;
                api(itemUrl('data-delete-url', id), {}).then(function () {
                    item.remove();
                    syncLimit();
                    if (wasPrimary) {
                        // Sunucu sıradaki görseli ana yapar; arayüz de aynısını gösterir.
                        var first = items()[0];
                        if (first) {
                            setPrimaryUi(first);
                        }
                    }
                }).catch(function (err) {
                    deleteBtn.disabled = false;
                    notify(firstError(err), 'danger');
                });
            }
        });

        // Sıralama: kaydet, başarısızsa eski sıraya dön.
        function saveOrder(after, before) {
            api(list.getAttribute('data-reorder-url') || '', { json: { order: after.map(Number) } }).catch(function (err) {
                before.forEach(function (id) {
                    var el = list.querySelector('[data-image-item][data-image-id="' + id + '"]');
                    if (el) {
                        list.appendChild(el);
                    }
                });
                notify(firstError(err), 'danger');
            });
        }

        var dragged = null;
        var before = [];

        list.addEventListener('dragstart', function (e) {
            var item = e.target.closest('[data-image-item]');
            if (!item) {
                return;
            }
            dragged = item;
            before = ids();
            item.classList.add('opacity-50');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', item.getAttribute('data-image-id') || '');
            }
        });

        list.addEventListener('dragover', function (e) {
            if (!dragged) {
                return;
            }
            e.preventDefault();
            var target = e.target.closest('[data-image-item]');
            if (!target || target === dragged) {
                return;
            }
            var r = target.getBoundingClientRect();
            list.insertBefore(dragged, e.clientX > r.left + r.width / 2 ? target.nextSibling : target);
        });

        list.addEventListener('drop', function (e) {
            if (dragged) {
                e.preventDefault();
            }
        });

        list.addEventListener('dragend', function () {
            if (!dragged) {
                return;
            }
            dragged.classList.remove('opacity-50');
            dragged = null;
            var after = ids();
            if (after.join(',') !== before.join(',')) {
                saveOrder(after, before);
            }
        });

        // Klavye ile sıralama: Alt + Sol/Sağ ok.
        list.addEventListener('keydown', function (e) {
            if (!e.altKey || (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight')) {
                return;
            }
            var item = e.target.closest('[data-image-item]');
            if (!item || e.target !== item) {
                return;
            }
            e.preventDefault();
            var prevOrder = ids();
            if (e.key === 'ArrowLeft' && item.previousElementSibling) {
                list.insertBefore(item, item.previousElementSibling);
            } else if (e.key === 'ArrowRight' && item.nextElementSibling) {
                list.insertBefore(item.nextElementSibling, item);
            } else {
                return;
            }
            item.focus();
            saveOrder(ids(), prevOrder);
        });
    }

    function init() {
        initSlug();
        initVariants();
        initImages();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();