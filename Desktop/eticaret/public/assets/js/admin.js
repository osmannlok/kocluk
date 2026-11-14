// public/assets/js/admin.js
// Yönetim paneli ortak yardımcıları: window.Admin. Satır içi JS yok (CSP).
(() => {
    'use strict';

    const SIDEBAR_KEY = 'admin.sidebar';

    /* ---------- CSRF + fetch ---------- */

    const csrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.getAttribute('content') || '') : '';
    };

    class AdminError extends Error {
        constructor(message, status, code, errors) {
            super(message);
            this.name = 'AdminError';
            this.status = status;
            this.code = code;
            this.errors = errors || {};
        }
    }

    // Sunucu biçimi: {"ok":true,"data":{}} / {"ok":false,"error":{code,message,errors}}
    async function fetchJson(url, options = {}) {
        const method = (options.method || 'GET').toUpperCase();
        const headers = new Headers(options.headers || {});
        headers.set('Accept', 'application/json');
        headers.set('X-Requested-With', 'XMLHttpRequest');
        // Neden: durum değiştiren her istek CSRF başlığı taşır.
        if (method !== 'GET' && method !== 'HEAD') {
            headers.set('X-CSRF-Token', csrfToken());
        }

        let body = options.body;
        if (body !== undefined && body !== null && !(body instanceof FormData) && typeof body === 'object') {
            headers.set('Content-Type', 'application/json');
            body = JSON.stringify(body);
        }

        let response;
        try {
            response = await fetch(url, { method, headers, body, credentials: 'same-origin' });
        } catch (err) {
            throw new AdminError('Sunucuya ulaşılamadı. Bağlantınızı kontrol edin.', 0, 'network', {});
        }

        let payload = null;
        try {
            payload = await response.json();
        } catch (err) {
            payload = null;
        }

        if (!response.ok || !payload || payload.ok !== true) {
            const error = (payload && payload.error) || {};
            throw new AdminError(
                error.message || 'İşlem tamamlanamadı.',
                response.status,
                error.code || 'error',
                error.errors
            );
        }

        return payload.data === undefined || payload.data === null ? {} : payload.data;
    }

    /* ---------- Bildirim (toast) ---------- */

    function toast(message, type = 'success', timeout = 4500) {
        const area = document.getElementById('adminToastArea');
        if (!area) {
            return;
        }

        const allowed = ['success', 'danger', 'warning', 'info'];
        const kind = allowed.includes(type) ? type : 'info';

        const el = document.createElement('div');
        el.className = 'toast align-items-center border-0 text-bg-' + kind;
        el.setAttribute('role', kind === 'danger' ? 'alert' : 'status');

        const wrap = document.createElement('div');
        wrap.className = 'd-flex';

        const bodyEl = document.createElement('div');
        bodyEl.className = 'toast-body';
        bodyEl.textContent = String(message); // Kullanıcı verisi innerHTML'e yazılmaz.

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close btn-close-white me-2 m-auto';
        close.setAttribute('data-bs-dismiss', 'toast');
        close.setAttribute('aria-label', 'Kapat');

        wrap.append(bodyEl, close);
        el.append(wrap);
        area.append(el);

        el.addEventListener('hidden.bs.toast', () => el.remove());

        if (window.bootstrap && window.bootstrap.Toast) {
            window.bootstrap.Toast.getOrCreateInstance(el, { delay: timeout }).show();
        } else {
            el.classList.add('show');
            window.setTimeout(() => el.remove(), timeout);
        }
    }

    /* ---------- Onay penceresi ([data-confirm]) ---------- */

    function confirmAction(message) {
        return window.confirm(message || 'Bu işlemi onaylıyor musunuz?');
    }

    function initConfirm() {
        // Tıklamada: buton/bağlantı üzerindeki data-confirm.
        document.addEventListener('click', (event) => {
            const el = event.target.closest('[data-confirm]');
            if (!el || el.tagName === 'FORM') {
                return;
            }
            if (!confirmAction(el.getAttribute('data-confirm'))) {
                event.preventDefault();
                event.stopPropagation();
            }
        });

        // Yakalama aşamasında: form üzerindeki data-confirm (diğer dinleyicilerden önce).
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (form instanceof HTMLFormElement && form.hasAttribute('data-confirm')) {
                if (!confirmAction(form.getAttribute('data-confirm'))) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            }
        }, true);
    }

    /* ---------- Kaydedilmemiş değişiklik uyarısı ---------- */

    let dirty = false;
    let submitting = false;

    function markClean() {
        dirty = false;
    }

    function initDirtyGuard() {
        const forms = document.querySelectorAll('form[data-dirty-guard]');
        if (forms.length === 0) {
            return;
        }

        forms.forEach((form) => {
            form.addEventListener('input', () => { dirty = true; });
            form.addEventListener('change', () => { dirty = true; });
            form.addEventListener('reset', () => { dirty = false; });
            form.addEventListener('submit', (event) => {
                if (!event.defaultPrevented) {
                    submitting = true;
                }
            });
        });

        window.addEventListener('beforeunload', (event) => {
            if (dirty && !submitting) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
    }

    /* ---------- Tarih aralığı yardımcıları ---------- */

    const pad = (n) => String(n).padStart(2, '0');
    const toISODate = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());

    function presetRange(name) {
        const from = new Date();
        const to = new Date();

        switch (name) {
            case 'today':
                break;
            case 'yesterday':
                from.setDate(from.getDate() - 1);
                to.setDate(to.getDate() - 1);
                break;
            case '7':
                from.setDate(from.getDate() - 6);
                break;
            case '30':
                from.setDate(from.getDate() - 29);
                break;
            case 'month':
                from.setDate(1);
                break;
            default:
                return null;
        }

        return { from: toISODate(from), to: toISODate(to) };
    }

    // Biçim: <div data-date-range [data-range-submit]> <input data-range-from> <input data-range-to> <button data-range-preset="7">
    function bindDateRanges(root = document) {
        root.querySelectorAll('[data-date-range]').forEach((group) => {
            if (group.dataset.rangeBound === '1') {
                return;
            }
            const from = group.querySelector('[data-range-from]');
            const to = group.querySelector('[data-range-to]');
            if (!from || !to) {
                return;
            }
            group.dataset.rangeBound = '1';

            group.addEventListener('click', (event) => {
                const btn = event.target.closest('[data-range-preset]');
                if (!btn) {
                    return;
                }
                const range = presetRange(btn.getAttribute('data-range-preset') || '');
                if (!range) {
                    return;
                }
                event.preventDefault();
                from.value = range.from;
                to.value = range.to;
                from.dispatchEvent(new Event('change', { bubbles: true }));

                if (group.hasAttribute('data-range-submit')) {
                    const form = group.closest('form');
                    if (form) {
                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit();
                        } else {
                            form.submit();
                        }
                    }
                }
            });

            // Başlangıç bitişten büyük olamaz.
            const sync = (changed) => {
                if (from.value && to.value && from.value > to.value) {
                    if (changed === from) {
                        to.value = from.value;
                    } else {
                        from.value = to.value;
                    }
                }
            };
            from.addEventListener('change', () => sync(from));
            to.addEventListener('change', () => sync(to));
        });
    }

    /* ---------- Kenar çubuğu durumu (masaüstü) ---------- */

    function applySidebar(collapsed) {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        document.querySelectorAll('[data-admin-sidebar-toggle]').forEach((btn) => {
            btn.setAttribute('aria-expanded', String(!collapsed));
        });
    }

    function initSidebar() {
        let collapsed = false;
        try {
            collapsed = window.localStorage.getItem(SIDEBAR_KEY) === 'collapsed';
        } catch (err) {
            collapsed = false; // Depolama kapalıysa varsayılan açık.
        }
        applySidebar(collapsed);

        document.addEventListener('click', (event) => {
            const btn = event.target.closest('[data-admin-sidebar-toggle]');
            if (!btn) {
                return;
            }
            const next = !document.body.classList.contains('sidebar-collapsed');
            applySidebar(next);
            try {
                window.localStorage.setItem(SIDEBAR_KEY, next ? 'collapsed' : 'open');
            } catch (err) {
                /* yoksay */
            }
        });
    }

    /* ---------- Başlatma ---------- */

    function init() {
        initConfirm();
        initDirtyGuard();
        initSidebar();
        bindDateRanges();
    }

    window.Admin = {
        fetchJson,
        AdminError,
        confirmAction,
        toast,
        markClean,
        dateRange: { toISODate, preset: presetRange, bind: bindDateRanges },
        sidebar: { apply: applySidebar },
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();