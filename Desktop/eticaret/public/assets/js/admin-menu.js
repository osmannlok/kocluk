// public/assets/js/admin-menu.js
/**
 * Menü ağacı düzenleyici (admin.js'in window.Admin nesnesine bağlıdır; $pageScripts ile yüklenir).
 * Güvenlik: yalnızca sayısal öğe kimlikleri ve parent_id gönderilir; istek Admin.fetchJson ile X-CSRF-Token
 * taşır; kullanıcı verisi innerHTML'e yazılmaz. Seviye sınırı ve döngü engeli sunucuda da zorlanır,
 * buradaki denetimler yalnızca kullanım kolaylığıdır.
 * Sözleşme (edit.php): #menuTree[data-menu-tree][data-reorder-url][data-max-depth], li[data-item-id],
 * [data-menu-row], ul[data-menu-list], [data-menu-move=up|down|indent|outdent], [data-drag-handle],
 * select[data-link-type], [data-target-group], [data-menu-status].
 * Gönderilen JSON: {"items":[{"id":int,"parent_id":int|null}]} (görsel sırayla, düz liste).
 */
(function () {
    'use strict';

    var ITEM_SELECTOR = 'li[data-item-id]';
    var LIST_SELECTOR = 'ul[data-menu-list]';

    // ---------------------------------------------------------------
    // Bağlantı türüne göre hedef seçici
    // ---------------------------------------------------------------
    function applyTargetGroup(select) {
        var form = select.closest('form');
        if (!form) {
            return;
        }
        var type = select.value;
        var groups = form.querySelectorAll('[data-target-group]');
        Array.prototype.forEach.call(groups, function (group) {
            var active = group.getAttribute('data-target-group') === type;
            group.hidden = !active;
            // Yalnızca etkin grubun alanı gönderilir (aynı adlı link_value alanları çakışmasın).
            Array.prototype.forEach.call(group.querySelectorAll('input, select, textarea'), function (field) {
                field.disabled = !active;
            });
        });
    }

    function initTargetSelectors() {
        var selects = document.querySelectorAll('select[data-link-type]');
        Array.prototype.forEach.call(selects, function (select) {
            applyTargetGroup(select);
            select.addEventListener('change', function () {
                applyTargetGroup(select);
            });
        });
    }

    // ---------------------------------------------------------------
    // Ağaç sıralama
    // ---------------------------------------------------------------
    function initTree() {
        var tree = document.querySelector('[data-menu-tree]');
        if (!tree) {
            return;
        }

        var reorderUrl = tree.getAttribute('data-reorder-url') || '';
        var maxDepth = parseInt(tree.getAttribute('data-max-depth') || '3', 10);
        if (!(maxDepth >= 1)) {
            maxDepth = 3;
        }
        var statusEl = document.querySelector('[data-menu-status]');
        var busy = false;
        var dragged = null;
        var dropInfo = null;

        var INDICATOR_CLASSES = {
            before: ['border-top', 'border-3', 'border-primary'],
            after: ['border-bottom', 'border-3', 'border-primary'],
            nest: ['border', 'border-primary']
        };

        function isItem(el) {
            return el instanceof Element && el.matches(ITEM_SELECTOR);
        }

        function rootList() {
            return tree.querySelector(':scope > ' + LIST_SELECTOR);
        }

        function childList(li, create) {
            var found = null;
            Array.prototype.forEach.call(li.children, function (child) {
                if (!found && child.matches(LIST_SELECTOR)) {
                    found = child;
                }
            });
            if (!found && create) {
                found = document.createElement('ul');
                found.className = 'list-unstyled mb-0 ms-4 mt-2';
                found.setAttribute('data-menu-list', '');
                li.appendChild(found);
            }
            return found;
        }

        function parentItem(li) {
            var parentList = li.parentElement;
            return parentList ? parentList.closest(ITEM_SELECTOR) : null;
        }

        function depthOf(li) {
            var depth = 1;
            var p = parentItem(li);
            while (p) {
                depth += 1;
                p = parentItem(p);
            }
            return depth;
        }

        function heightOf(li) {
            var list = childList(li, false);
            var best = 0;
            if (list) {
                Array.prototype.forEach.call(list.children, function (child) {
                    if (isItem(child)) {
                        best = Math.max(best, heightOf(child));
                    }
                });
            }
            return 1 + best;
        }

        function prevItem(li) {
            var p = li.previousElementSibling;
            while (p && !isItem(p)) {
                p = p.previousElementSibling;
            }
            return p;
        }

        function nextItem(li) {
            var n = li.nextElementSibling;
            while (n && !isItem(n)) {
                n = n.nextElementSibling;
            }
            return n;
        }

        function announce(message) {
            if (statusEl) {
                statusEl.textContent = message;
            }
        }

        function notify(message, type) {
            if (window.Admin && typeof window.Admin.toast === 'function') {
                window.Admin.toast(message, type || 'danger');
            }
        }

        // Kendi alt ağacına veya kendine taşıma (döngü) engellenir.
        function canPlace(li, newDepth) {
            return newDepth >= 1 && newDepth + heightOf(li) - 1 <= maxDepth;
        }

        function rowOf(li) {
            var row = null;
            Array.prototype.forEach.call(li.children, function (child) {
                if (!row && child.matches('[data-menu-row]')) {
                    row = child;
                }
            });
            return row;
        }

        function labelOf(li) {
            var row = rowOf(li);
            var el = row ? row.querySelector('.fw-semibold') : null;
            return el ? (el.textContent || '').trim() : '';
        }

        function refreshButtons() {
            var items = tree.querySelectorAll(ITEM_SELECTOR);
            Array.prototype.forEach.call(items, function (li) {
                var row = rowOf(li);
                if (!row) {
                    return;
                }
                var prev = prevItem(li);
                var next = nextItem(li);
                var parent = parentItem(li);
                var states = {
                    up: !prev,
                    down: !next,
                    outdent: !parent,
                    indent: !prev || !canPlace(li, depthOf(prev) + 1)
                };
                Array.prototype.forEach.call(row.querySelectorAll('[data-menu-move]'), function (btn) {
                    // Yalnızca bu satırın düğmeleri (iç içe satırlar hariç).
                    if (btn.closest(ITEM_SELECTOR) !== li) {
                        return;
                    }
                    var kind = btn.getAttribute('data-menu-move');
                    if (Object.prototype.hasOwnProperty.call(states, kind)) {
                        btn.disabled = states[kind];
                    }
                });
            });
        }

        function serialize() {
            var out = [];
            var walk = function (list, parentId) {
                Array.prototype.forEach.call(list.children, function (li) {
                    if (!isItem(li)) {
                        return;
                    }
                    var id = parseInt(li.getAttribute('data-item-id') || '0', 10);
                    out.push({ id: id, parent_id: parentId });
                    var sub = childList(li, false);
                    if (sub) {
                        walk(sub, id);
                    }
                });
            };
            var root = rootList();
            if (root) {
                walk(root, null);
            }
            return out;
        }

        function save(li, origin, focusBtn) {
            if (!window.Admin || typeof window.Admin.fetchJson !== 'function' || reorderUrl === '') {
                revert(li, origin);
                notify('Sıralama kaydedilemedi.');
                return;
            }
            busy = true;
            window.Admin.fetchJson(reorderUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: { items: serialize() }
            }).then(function () {
                announce('Sıralama kaydedildi: ' + labelOf(li));
            }).catch(function (error) {
                revert(li, origin);
                var message = error && error.message ? String(error.message) : 'Sıralama kaydedilemedi.';
                notify(message);
                announce('Sıralama kaydedilemedi.');
            }).then(function () {
                busy = false;
                refreshButtons();
                if (focusBtn && !focusBtn.disabled) {
                    focusBtn.focus();
                }
            });
        }

        function revert(li, origin) {
            if (origin && origin.list) {
                origin.list.insertBefore(li, origin.next && origin.next.parentNode === origin.list ? origin.next : null);
            }
        }

        function originOf(li) {
            return { list: li.parentElement, next: nextItem(li) };
        }

        // Taşıma işlemleri (düğmeler ve sürükle-bırak ortak kullanır).
        function moveUp(li) {
            var prev = prevItem(li);
            if (!prev) {
                return false;
            }
            prev.parentNode.insertBefore(li, prev);
            return true;
        }

        function moveDown(li) {
            var next = nextItem(li);
            if (!next) {
                return false;
            }
            next.parentNode.insertBefore(li, next.nextSibling);
            return true;
        }

        function indent(li) {
            var prev = prevItem(li);
            if (!prev || !canPlace(li, depthOf(prev) + 1)) {
                return false;
            }
            childList(prev, true).appendChild(li);
            return true;
        }

        function outdent(li) {
            var parent = parentItem(li);
            if (!parent) {
                return false;
            }
            parent.parentNode.insertBefore(li, parent.nextSibling);
            return true;
        }

        tree.addEventListener('click', function (event) {
            var target = event.target;
            if (!(target instanceof Element)) {
                return;
            }
            var btn = target.closest('[data-menu-move]');
            if (!btn || btn.disabled || !tree.contains(btn)) {
                return;
            }
            event.preventDefault();
            if (busy) {
                return;
            }
            var li = btn.closest(ITEM_SELECTOR);
            if (!li) {
                return;
            }
            var origin = originOf(li);
            var moved = false;
            switch (btn.getAttribute('data-menu-move')) {
                case 'up': moved = moveUp(li); break;
                case 'down': moved = moveDown(li); break;
                case 'indent': moved = indent(li); break;
                case 'outdent': moved = outdent(li); break;
                default: moved = false;
            }
            if (!moved) {
                return;
            }
            refreshButtons();
            save(li, origin, btn);
        });

        // -----------------------------------------------------------
        // HTML5 sürükle-bırak (yalnızca tutamaçtan başlar)
        // -----------------------------------------------------------
        function clearIndicator() {
            var marked = tree.querySelectorAll('[data-menu-row]');
            Array.prototype.forEach.call(marked, function (row) {
                ['before', 'after', 'nest'].forEach(function (zone) {
                    INDICATOR_CLASSES[zone].forEach(function (cls) {
                        row.classList.remove(cls);
                    });
                });
            });
        }

        function setDraggable(li, value) {
            if (value) {
                li.setAttribute('draggable', 'true');
            } else {
                li.removeAttribute('draggable');
            }
        }

        tree.addEventListener('mousedown', function (event) {
            var target = event.target;
            if (!(target instanceof Element)) {
                return;
            }
            var handle = target.closest('[data-drag-handle]');
            if (!handle || busy) {
                return;
            }
            var li = handle.closest(ITEM_SELECTOR);
            if (li) {
                setDraggable(li, true);
            }
        });

        document.addEventListener('mouseup', function () {
            Array.prototype.forEach.call(tree.querySelectorAll('[draggable="true"]'), function (li) {
                if (li !== dragged) {
                    setDraggable(li, false);
                }
            });
        });

        tree.addEventListener('dragstart', function (event) {
            var li = event.target instanceof Element ? event.target : null;
            if (!li || !isItem(li) || busy) {
                return;
            }
            dragged = li;
            dropInfo = null;
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                // Firefox, veri atanmadan sürüklemeyi başlatmaz.
                event.dataTransfer.setData('text/plain', li.getAttribute('data-item-id') || '');
            }
            li.classList.add('opacity-50');
        });

        tree.addEventListener('dragover', function (event) {
            if (!dragged) {
                return;
            }
            var target = event.target instanceof Element ? event.target : null;
            var row = target ? target.closest('[data-menu-row]') : null;
            clearIndicator();
            dropInfo = null;
            if (!row) {
                return;
            }
            var targetLi = row.closest(ITEM_SELECTOR);
            // Kendine veya kendi alt ağacına bırakılamaz (döngü engeli).
            if (!targetLi || targetLi === dragged || dragged.contains(targetLi)) {
                return;
            }

            var rect = row.getBoundingClientRect();
            var offset = event.clientY - rect.top;
            var zone = offset < rect.height * 0.25 ? 'before' : (offset > rect.height * 0.75 ? 'after' : 'nest');
            var newDepth = zone === 'nest' ? depthOf(targetLi) + 1 : depthOf(targetLi);
            if (!canPlace(dragged, newDepth)) {
                return;
            }

            event.preventDefault();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'move';
            }
            INDICATOR_CLASSES[zone].forEach(function (cls) {
                row.classList.add(cls);
            });
            dropInfo = { li: targetLi, zone: zone };
        });

        tree.addEventListener('dragleave', function (event) {
            if (event.relatedTarget instanceof Node && tree.contains(event.relatedTarget)) {
                return;
            }
            clearIndicator();
        });

        tree.addEventListener('drop', function (event) {
            if (!dragged || !dropInfo) {
                return;
            }
            event.preventDefault();
            var li = dragged;
            var info = dropInfo;
            clearIndicator();

            var origin = originOf(li);
            if (info.zone === 'before') {
                info.li.parentNode.insertBefore(li, info.li);
            } else if (info.zone === 'after') {
                info.li.parentNode.insertBefore(li, info.li.nextSibling);
            } else {
                childList(info.li, true).appendChild(li);
            }
            refreshButtons();
            save(li, origin, null);
        });

        tree.addEventListener('dragend', function () {
            if (dragged) {
                dragged.classList.remove('opacity-50');
                setDraggable(dragged, false);
            }
            dragged = null;
            dropInfo = null;
            clearIndicator();
        });

        refreshButtons();
    }

    function init() {
        initTargetSelectors();
        initTree();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();