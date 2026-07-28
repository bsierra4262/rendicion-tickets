/**
 * padron.js — Autocompletado contra el padrón nacional (1,13 M de empresas) al
 * agregar un proveedor en "Prov. comprob.". Escribís razón social o CUIT y elegís
 * de lo que YA tenemos: se completan CUIT + razón, sin duplicar ni tipear a mano.
 */
(function () {
    'use strict';
    var base = (window.__sesion && window.__sesion.base) || '';

    function fmtCuit(c) { c = (c || '').replace(/\D/g, ''); return c.length === 11 ? c.slice(0, 2) + '-' + c.slice(2, 10) + '-' + c.slice(10) : c; }
    function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

    Array.prototype.forEach.call(document.querySelectorAll('[data-padron-form]'), function (form) {
        var razon = form.querySelector('[name="razon_social"]');
        var cuit  = form.querySelector('[name="cuit"]');
        if (razon) { wire(razon, razon, cuit); }
        if (cuit)  { wire(cuit, razon, cuit); }
    });

    function wire(input, razonEl, cuitEl) {
        input.setAttribute('autocomplete', 'off');
        var box = document.createElement('div');
        box.className = 'padron-ac';
        box.style.cssText = 'position:absolute;z-index:60;background:var(--surface,#fff);border:1px solid var(--line,#e7e4ef);' +
            'border-radius:10px;box-shadow:var(--shadow-lg,0 24px 60px -20px rgba(74,36,112,.42));max-height:300px;overflow:auto;display:none';
        document.body.appendChild(box);
        var t, lastItems = [];

        input.addEventListener('input', function () {
            var q = input.value.trim();
            clearTimeout(t);
            if (q.length < 3) { hide(); return; }
            t = setTimeout(function () { search(q); }, 250);
        });
        input.addEventListener('blur', function () { setTimeout(hide, 180); });
        input.addEventListener('focus', function () { if (lastItems.length && input.value.trim().length >= 3) { render(lastItems); } });

        function search(q) {
            fetch(base + 'api-padron?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) { lastItems = (d && d.items) || []; render(lastItems); })
                .catch(hide);
        }
        function render(items) {
            box.innerHTML = '';
            if (!items.length) { hide(); return; }
            items.forEach(function (it) {
                var row = document.createElement('div');
                row.style.cssText = 'padding:.5rem .7rem;cursor:pointer;border-bottom:1px solid var(--line-soft,#f0edf6)';
                row.innerHTML = '<strong>' + esc(it.razon) + '</strong><br><span class="muted small">' + fmtCuit(it.cuit) + '</span>';
                row.addEventListener('mousedown', function (e) { e.preventDefault(); pick(it); });
                row.addEventListener('mouseover', function () { row.style.background = 'var(--primary-050,#f2ecf9)'; });
                row.addEventListener('mouseout', function () { row.style.background = ''; });
                box.appendChild(row);
            });
            position();
        }
        function pick(it) {
            if (razonEl) { razonEl.value = it.razon; }
            if (cuitEl)  { cuitEl.value = fmtCuit(it.cuit); }
            hide();
            if (razonEl) { razonEl.focus(); }
        }
        function position() {
            var r = input.getBoundingClientRect();
            box.style.left  = (window.scrollX + r.left) + 'px';
            box.style.top   = (window.scrollY + r.bottom + 4) + 'px';
            box.style.width = Math.max(r.width, 280) + 'px';
            box.style.display = 'block';
        }
        function hide() { box.style.display = 'none'; }
        window.addEventListener('resize', hide);
        window.addEventListener('scroll', function () { if (box.style.display !== 'none') { position(); } }, true);
    }
})();
