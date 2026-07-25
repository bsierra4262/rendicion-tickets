/**
 * combobox.js — Desplegable PROFESIONAL para TODOS los <select> del sistema.
 *
 * - Look unificado (mismo panel branded en Estado, Moneda, roles, categorías, etc.).
 * - Buscador sólo cuando conviene (más de SEARCH_MIN opciones) o si permite crear.
 * - Con data-combobox-create="URL" agrega "Crear «texto»" (sólo categorías) con una
 *   fila fija "+ Crear concepto nuevo" siempre visible para que sea descubrible.
 * - El <select> nativo queda oculto como fuente de verdad; opt-out con data-no-combobox.
 * Sin dependencias.
 */
(function () {
    'use strict';
    var SEARCH_MIN = 7;

    function norm(s) {
        return (s || '').toString().toLowerCase()
            .replace(/[áàäâã]/g, 'a').replace(/[éèëê]/g, 'e').replace(/[íìïî]/g, 'i')
            .replace(/[óòöôõ]/g, 'o').replace(/[úùüû]/g, 'u').replace(/ñ/g, 'n');
    }
    function esc(s) {
        return s.replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    var CARET = '<svg class="ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
    var PLUS = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>';

    function enhance(select) {
        if (select.dataset.cbxReady || select.multiple || select.dataset.noCombobox !== undefined) return;
        select.dataset.cbxReady = '1';

        var createUrl = select.getAttribute('data-combobox-create') || '';
        select.removeAttribute('required'); // se valida en el server; evita "not focusable"
        var withSearch = select.options.length > SEARCH_MIN || !!createUrl;

        var placeholder = 'Elegí…';
        for (var i = 0; i < select.options.length; i++) {
            if (select.options[i].value === '') { placeholder = select.options[i].text; break; }
        }

        var wrap = document.createElement('div');
        wrap.className = 'cbx' + (withSearch ? '' : ' cbx--nosearch');
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'cbx-toggle';
        toggle.setAttribute('aria-haspopup', 'listbox');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = '<span class="cbx-val"></span><span class="cbx-caret">' + CARET + '</span>';
        wrap.appendChild(toggle);

        var panel = document.createElement('div');
        panel.className = 'cbx-panel';
        panel.innerHTML = '<div class="cbx-search-wrap"><input type="text" class="cbx-search" placeholder="Buscar…" autocomplete="off"></div><ul class="cbx-list" role="listbox" tabindex="-1"></ul>';
        wrap.appendChild(panel);

        var valEl = toggle.querySelector('.cbx-val');
        var search = panel.querySelector('.cbx-search');
        var list = panel.querySelector('.cbx-list');
        var items = [], activeIdx = -1;

        function syncLabel() {
            var o = select.options[select.selectedIndex], ph = !select.value;
            valEl.textContent = ph ? placeholder : (o ? o.text : placeholder);
            toggle.classList.toggle('is-placeholder', ph);
        }

        function render() {
            var term = norm(search.value.trim());
            list.innerHTML = '';
            items = [];
            var exact = false, shown = 0;
            for (var i = 0; i < select.options.length; i++) {
                var o = select.options[i];
                if (o.value === '' && term !== '') continue;
                var label = o.text;
                if (term !== '' && norm(label).indexOf(term) === -1) continue;
                if (norm(label) === term) exact = true;
                var li = document.createElement('li');
                li.className = 'cbx-item' + (o.value === select.value ? ' selected' : '');
                li.textContent = label;
                li.setAttribute('role', 'option');
                li.dataset.value = o.value;
                list.appendChild(li);
                items.push(li);
                shown++;
            }
            if (shown === 0 && !createUrl) {
                var em = document.createElement('li');
                em.className = 'cbx-empty';
                em.textContent = 'Sin coincidencias';
                list.appendChild(em);
            }
            if (createUrl) {
                var t = search.value.trim();
                var cli = document.createElement('li');
                cli.className = 'cbx-item cbx-create';
                if (t === '' || exact) { cli.innerHTML = '<span class="cbx-plus">' + PLUS + '</span> Crear concepto nuevo'; cli.dataset.hint = '1'; }
                else { cli.innerHTML = '<span class="cbx-plus">' + PLUS + '</span> Crear «' + esc(t) + '»'; cli.dataset.create = t; }
                list.appendChild(cli);
                items.push(cli);
            }
            activeIdx = -1;
            if (wrap.classList.contains('open')) place();
        }

        // Posiciona el panel con position:fixed para que escape de contenedores
        // con overflow (ej. la tabla de Usuarios, que antes lo recortaba).
        function place() {
            var r = toggle.getBoundingClientRect();
            panel.style.position = 'fixed';
            panel.style.left = Math.round(r.left) + 'px';
            panel.style.width = Math.round(r.width) + 'px';
            panel.style.right = 'auto';
            var ph = panel.offsetHeight;
            var below = window.innerHeight - r.bottom;
            if (below < ph + 8 && r.top > below) { panel.style.top = Math.round(r.top - ph - 4) + 'px'; }
            else { panel.style.top = Math.round(r.bottom + 4) + 'px'; }
        }

        function open() {
            wrap.classList.add('open');
            toggle.setAttribute('aria-expanded', 'true');
            search.value = '';
            search.placeholder = 'Buscar…';
            render();
            place();
            setTimeout(function () { (withSearch ? search : list).focus(); }, 0);
            document.addEventListener('mousedown', outside, true);
            window.addEventListener('scroll', place, true);
            window.addEventListener('resize', place);
        }
        function close() {
            wrap.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
            document.removeEventListener('mousedown', outside, true);
            window.removeEventListener('scroll', place, true);
            window.removeEventListener('resize', place);
        }
        function outside(e) { if (!wrap.contains(e.target)) close(); }

        function choose(li) {
            if (li.dataset.create !== undefined) { create(li.dataset.create); return; }
            if (li.dataset.hint !== undefined) { search.placeholder = 'Escribí el nombre del concepto…'; search.focus(); return; }
            if (li.dataset.value === undefined) return;
            select.value = li.dataset.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            syncLabel();
            close();
            toggle.focus();
        }

        function create(nombre) {
            var ask = window.appConfirm
                ? window.appConfirm({ message: '¿Crear el concepto «' + nombre + '»?', okText: 'Crear' })
                : Promise.resolve(window.confirm('¿Crear el concepto «' + nombre + '»?'));
            ask.then(function (confirmado) {
            if (!confirmado) { search.focus(); return; }
            var meta = document.querySelector('meta[name="csrf"]');
            var body = new URLSearchParams();
            body.set('nombre', nombre);
            body.set('_csrf', meta ? meta.getAttribute('content') : '');
            var cli = list.querySelector('.cbx-create');
            if (cli) { cli.setAttribute('aria-disabled', 'true'); cli.textContent = 'Creando…'; }
            fetch(createUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-Requested-With': 'fetch', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || !res.ok) { throw new Error(res && res.error ? res.error : 'No se pudo crear'); }
                    var opt = null;
                    for (var i = 0; i < select.options.length; i++) {
                        if (String(select.options[i].value) === String(res.id)) { opt = select.options[i]; break; }
                    }
                    if (!opt) { opt = document.createElement('option'); opt.value = res.id; opt.text = res.nombre; select.appendChild(opt); }
                    select.value = res.id;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    syncLabel();
                    close();
                    toggle.focus();
                })
                .catch(function (err) {
                    if (cli) { cli.removeAttribute('aria-disabled'); cli.textContent = 'Error: ' + (err.message || 'reintentá'); }
                });
            });
        }

        function paintActive() {
            items.forEach(function (li, i) { li.classList.toggle('active', i === activeIdx); });
            if (items[activeIdx]) items[activeIdx].scrollIntoView({ block: 'nearest' });
        }
        function onKey(e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = Math.min(activeIdx + 1, items.length - 1); paintActive(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(activeIdx - 1, 0); paintActive(); }
            else if (e.key === 'Enter') { e.preventDefault(); if (items[activeIdx]) choose(items[activeIdx]); else if (items.length) choose(items[0]); }
            else if (e.key === 'Escape') { e.preventDefault(); close(); toggle.focus(); }
        }

        toggle.addEventListener('click', function () { wrap.classList.contains('open') ? close() : open(); });
        search.addEventListener('input', render);
        search.addEventListener('keydown', onKey);
        list.addEventListener('keydown', onKey);
        list.addEventListener('click', function (e) { var li = e.target.closest('.cbx-item'); if (li) choose(li); });
        select.addEventListener('change', syncLabel);
        syncLabel();
    }

    function init() { document.querySelectorAll('select').forEach(enhance); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
    else { init(); }
})();
