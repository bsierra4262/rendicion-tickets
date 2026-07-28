/**
 * datepicker.js — Calendario PROFESIONAL branded que reemplaza a los
 * <input type="month"> y <input type="date"> nativos en todo el software.
 *
 * Conserva un <input type="hidden"> con el name/value ISO original (fuente de verdad
 * para el form). Progressive-enhancement: si algo falla, se deja el input nativo.
 * Con data-autosubmit envía el formulario al elegir/borrar (filtros que refrescan solos).
 * Sin dependencias. new Date() acá es JS de navegador (no el sandbox de workflows).
 */
(function () {
    'use strict';
    var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var MES_ABR = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    var DIAS = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'];
    var CAL = '<svg class="ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>';
    var NAVL = '<svg class="ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>';
    var NAVR = '<svg class="ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>';
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

    function enhance(input) {
        if (input.dataset.dpReady) return;
        try { build(input); } catch (e) { /* deja el input nativo intacto */ }
    }

    function build(input) {
        input.dataset.dpReady = '1';
        var type = input.type === 'month' ? 'month' : 'date';
        var autosubmit = input.hasAttribute('data-autosubmit');
        var name = input.getAttribute('name') || '';
        var min = input.getAttribute('min') || '';
        var max = input.getAttribute('max') || '';
        var placeholder = input.getAttribute('placeholder') || (type === 'month' ? 'Elegí un mes' : 'Elegí una fecha');

        var wrap = document.createElement('div');
        wrap.className = 'dp';
        input.parentNode.insertBefore(wrap, input);

        var hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = name; hidden.value = input.value || '';
        wrap.appendChild(hidden);

        var toggle = document.createElement('button');
        toggle.type = 'button'; toggle.className = 'dp-toggle';
        toggle.innerHTML = '<span class="dp-val"></span><span class="dp-cal">' + CAL + '</span>';
        wrap.appendChild(toggle);

        var panel = document.createElement('div');
        panel.className = 'dp-panel';
        wrap.appendChild(panel);

        input.remove();
        var valEl = toggle.querySelector('.dp-val');
        var view = { y: 0, m: 0 };

        function syncLabel() {
            if (!hidden.value) { valEl.textContent = placeholder; toggle.classList.add('is-placeholder'); return; }
            toggle.classList.remove('is-placeholder');
            var p = hidden.value.split('-');
            if (type === 'month') { valEl.textContent = cap(MESES[(+p[1]) - 1]) + ' ' + p[0]; }
            else { valEl.textContent = pad(+p[2]) + '/' + pad(+p[1]) + '/' + p[0]; }
        }

        // Sincroniza la vista si el valor del hidden cambia por FUERA (OCR / autocompletar).
        hidden.addEventListener('change', syncLabel);
        hidden.addEventListener('input', syncLabel);

        function initView() {
            var t = new Date();
            var b = hidden.value ? hidden.value.split('-') : [t.getFullYear(), t.getMonth() + 1, t.getDate()];
            view.y = +b[0];
            view.m = type === 'month' ? 0 : (+b[1] - 1);
        }
        function inRange(iso) {
            if (min && iso < min) return false;
            if (max && iso > max) return false;
            return true;
        }
        function footer() {
            return '<div class="dp-foot"><button type="button" class="dp-link" data-act="clear">Borrar</button>' +
                '<button type="button" class="dp-link dp-today" data-act="today">' + (type === 'month' ? 'Este mes' : 'Hoy') + '</button></div>';
        }
        function renderMonth() {
            var t = new Date(), y = view.y;
            var h = '<div class="dp-head"><button type="button" class="dp-nav" data-nav="-1">' + NAVL + '</button><span class="dp-title">' + y + '</span><button type="button" class="dp-nav" data-nav="1">' + NAVR + '</button></div><div class="dp-grid dp-grid-m">';
            for (var m = 0; m < 12; m++) {
                var iso = y + '-' + pad(m + 1);
                var cur = (y === t.getFullYear() && m === t.getMonth());
                h += '<button type="button" class="dp-cell' + (hidden.value === iso ? ' selected' : '') + (cur ? ' today' : '') + '" data-iso="' + iso + '"' + (inRange(iso) ? '' : ' disabled') + '>' + MES_ABR[m] + '</button>';
            }
            panel.innerHTML = h + '</div>' + footer();
        }
        function renderDate() {
            var t = new Date(), y = view.y, m = view.m;
            var h = '<div class="dp-head"><button type="button" class="dp-nav" data-nav="-1">' + NAVL + '</button><span class="dp-title">' + cap(MESES[m]) + ' ' + y + '</span><button type="button" class="dp-nav" data-nav="1">' + NAVR + '</button></div><div class="dp-week">';
            for (var d = 0; d < 7; d++) h += '<span>' + DIAS[d] + '</span>';
            h += '</div><div class="dp-grid dp-grid-d">';
            var offset = (new Date(y, m, 1).getDay() + 6) % 7; // lunes = 0
            var dim = new Date(y, m + 1, 0).getDate();
            for (var i = 0; i < offset; i++) h += '<span class="dp-empty"></span>';
            for (var day = 1; day <= dim; day++) {
                var iso = y + '-' + pad(m + 1) + '-' + pad(day);
                var cur = (y === t.getFullYear() && m === t.getMonth() && day === t.getDate());
                h += '<button type="button" class="dp-cell dp-day' + (hidden.value === iso ? ' selected' : '') + (cur ? ' today' : '') + '" data-iso="' + iso + '"' + (inRange(iso) ? '' : ' disabled') + '>' + day + '</button>';
            }
            panel.innerHTML = h + '</div>' + footer();
        }
        function render() {
            type === 'month' ? renderMonth() : renderDate();
            if (wrap.classList.contains('open')) place();
        }

        // Panel con position:fixed para escapar de contenedores con overflow.
        function place() {
            var r = toggle.getBoundingClientRect();
            panel.style.position = 'fixed';
            panel.style.left = Math.round(r.left) + 'px';
            panel.style.right = 'auto';
            var ph = panel.offsetHeight, below = window.innerHeight - r.bottom;
            if (below < ph + 8 && r.top > below) { panel.style.top = Math.round(r.top - ph - 4) + 'px'; }
            else { panel.style.top = Math.round(r.bottom + 4) + 'px'; }
        }

        function emit() {
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
            if (autosubmit) { var f = wrap.closest('form'); if (f) f.submit(); }
        }
        function pick(iso) { hidden.value = iso; syncLabel(); close(); emit(); }

        function open() {
            initView(); wrap.classList.add('open'); render(); place();
            document.addEventListener('mousedown', outside, true);
            window.addEventListener('scroll', place, true);
            window.addEventListener('resize', place);
        }
        function close() {
            wrap.classList.remove('open');
            document.removeEventListener('mousedown', outside, true);
            window.removeEventListener('scroll', place, true);
            window.removeEventListener('resize', place);
        }
        function outside(e) { if (!wrap.contains(e.target)) close(); }

        toggle.addEventListener('click', function () { wrap.classList.contains('open') ? close() : open(); });
        panel.addEventListener('click', function (e) {
            var nav = e.target.closest('.dp-nav');
            if (nav) {
                var dir = +nav.dataset.nav;
                if (type === 'month') { view.y += dir; }
                else { view.m += dir; if (view.m < 0) { view.m = 11; view.y--; } else if (view.m > 11) { view.m = 0; view.y++; } }
                render(); return;
            }
            var cell = e.target.closest('.dp-cell');
            if (cell && !cell.disabled) { pick(cell.dataset.iso); return; }
            var act = e.target.closest('[data-act]');
            if (act) {
                if (act.dataset.act === 'clear') { hidden.value = ''; syncLabel(); close(); emit(); }
                else {
                    var t = new Date();
                    var iso = type === 'month' ? (t.getFullYear() + '-' + pad(t.getMonth() + 1))
                        : (t.getFullYear() + '-' + pad(t.getMonth() + 1) + '-' + pad(t.getDate()));
                    if (inRange(iso)) pick(iso);
                }
            }
        });

        hidden.addEventListener('change', syncLabel); // reflejar cambios externos (ej. OCR)
        syncLabel();
    }

    function init() { document.querySelectorAll('input[type="month"], input[type="date"]').forEach(enhance); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
    else { init(); }
})();
