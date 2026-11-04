/* Autocálculo de IVA/Total, validación de CUIT y autocompletado de proveedor. */
(function () {
    'use strict';
    var base = window.APP_BASE || '/';

    function num(s) {
        if (s === null || s === undefined) return 0;
        s = String(s).replace(/[^\d.,-]/g, '');
        if (s === '') return 0;
        var hasC = s.indexOf(',') !== -1, hasD = s.indexOf('.') !== -1;
        if (hasC && hasD) {
            if (s.lastIndexOf(',') > s.lastIndexOf('.')) s = s.replace(/\./g, '').replace(',', '.');
            else s = s.replace(/,/g, '');
        } else if (hasC) {
            s = s.replace(',', '.');
        }
        var n = parseFloat(s);
        return isNaN(n) ? 0 : n;
    }
    function fmt(n) { n = Math.round(n * 100) / 100; return isNaN(n) ? '0,00' : n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    var $ = function (id) { return document.getElementById(id); };
    var neto = $('f_neto'), tasa = $('f_tasa'), iva = $('f_iva'),
        perc = $('f_perc'), total = $('f_total'),
        cuit = $('f_cuit'), razon = $('f_razon'),
        moneda = $('f_moneda'), tcWrap = $('f_tc_wrap');

    function calcIva() {
        if (!tasa || tasa.value === '') return;
        var t = parseFloat(tasa.value);
        if (!isNaN(t)) iva.value = fmt(num(neto.value) * t / 100);
    }
    function calcTotal() {
        total.value = fmt(num(neto.value) + num(iva.value) + num(perc.value));
    }

    if (neto) neto.addEventListener('input', function () { calcIva(); calcTotal(); });
    if (tasa) tasa.addEventListener('change', function () { calcIva(); calcTotal(); });
    if (iva) iva.addEventListener('input', calcTotal);
    if (perc) perc.addEventListener('input', calcTotal);

    if (moneda && tcWrap) {
        moneda.addEventListener('change', function () {
            tcWrap.style.display = moneda.value === 'USD' ? '' : 'none';
        });
    }

    // ---- CUIT: validación + autocompletado de proveedor ----
    function cuitDV(c) {
        c = String(c).replace(/\D/g, '');
        if (c.length !== 11) return false;
        var p = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2], s = 0;
        for (var i = 0; i < 10; i++) s += parseInt(c[i], 10) * p[i];
        var dv = 11 - (s % 11);
        if (dv === 11) dv = 0;
        if (dv === 10) return false;
        return dv === parseInt(c[10], 10);
    }

    // Aviso «Gasto recurrente detectado»: sólo en formularios de carga NUEVA (data-detect-recurrente).
    // Facturas Compra = compras únicas; si el CUIT es de un proveedor de Gastos recurrentes,
    // el server la guarda como gasto (y el archivo va a la carpeta de Gastos en Drive).
    function showRecurrente(rec) {
        var form = document.querySelector('form[data-detect-recurrente]');
        if (!form) return;
        var box = document.getElementById('recurrenteAviso');
        if (!rec || !rec.nombre) { if (box) box.hidden = true; return; }
        if (!box) {
            box = document.createElement('div');
            box.id = 'recurrenteAviso';
            box.className = 'flash flash-info';
            box.setAttribute('role', 'status');
            form.insertBefore(box, form.firstElementChild ? form.firstElementChild.nextSibling : null);
        }
        box.textContent = '';
        var b = document.createElement('strong');
        b.textContent = 'Gasto recurrente detectado: ' + rec.nombre + '. ';
        box.appendChild(b);
        box.appendChild(document.createTextNode('Al guardar, esta factura se carga en Gastos recurrentes (no como Factura Compra) y el archivo va a su carpeta de Drive.'));
        box.hidden = false;
    }

    if (cuit) {
        var hint = $('cuit_hint');
        var lookup = function () {
            var c = cuit.value.replace(/\D/g, '');
            if (c.length !== 11) { showRecurrente(null); if (hint) { hint.textContent = ''; hint.className = 'hint'; } return; }
            if (!cuitDV(c)) { showRecurrente(null); if (hint) { hint.textContent = 'CUIT inválido (dígito verificador)'; hint.className = 'hint bad'; } return; }
            if (hint) { hint.textContent = 'CUIT válido'; hint.className = 'hint good'; }
            fetch(base + 'api-proveedor?cuit=' + encodeURIComponent(c), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    showRecurrente(d && d.recurrente);
                    if (d && d.found && d.razon_social && razon && razon.value.trim() === '') {
                        razon.value = d.razon_social;
                        if (hint) hint.textContent = 'Proveedor encontrado: ' + d.razon_social;
                    }
                })
                .catch(function () {});
        };
        cuit.addEventListener('blur', lookup);
        cuit.addEventListener('change', lookup);
    }
})();
