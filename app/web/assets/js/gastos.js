/**
 * gastos.js — Lectura automática de la factura (PDF) de un gasto recurrente.
 * Extrae el TEXTO del PDF (pdf.js) → detecta proveedor, valor mensual, fecha de
 * emisión y 1er vencimiento → muestra un popup de confirmación que completa el
 * formulario. Como el OCR de comprobantes: asiste, y el usuario confirma/corrige.
 */
(function () {
    'use strict';
    var form = document.getElementById('gastoForm');
    if (!form) { return; }
    var PROVS = window.__gprov || [];
    var facturaInput = form.querySelector('input[name="factura"]');
    var btn = document.getElementById('leerFactura');
    var statusEl = document.getElementById('leerEstado');
    if (!btn || !facturaInput) { return; }

    // ---- pdf.js on-demand (mismo CDN/version que el escaneo) ----
    function loadPdfJs(cb) {
        if (window.pdfjsLib) { return cb(true); }
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/legacy/build/pdf.min.js';
        s.onload = function () {
            try { window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/legacy/build/pdf.worker.min.js'; } catch (e) {}
            cb(!!window.pdfjsLib);
        };
        s.onerror = function () { cb(false); };
        document.head.appendChild(s);
    }

    function extractText(file, cb) {
        loadPdfJs(function (ok) {
            if (!ok) { cb(null); return; }
            var fr = new FileReader();
            fr.onload = function () {
                window.pdfjsLib.getDocument({ data: new Uint8Array(fr.result) }).promise.then(function (pdf) {
                    var pages = Math.min(pdf.numPages, 3), txt = '';
                    var chain = Promise.resolve();
                    for (var i = 1; i <= pages; i++) {
                        (function (p) {
                            chain = chain.then(function () {
                                return pdf.getPage(p).then(function (pg) {
                                    return pg.getTextContent().then(function (tc) {
                                        txt += ' ' + tc.items.map(function (it) { return it.str; }).join(' ');
                                    });
                                });
                            });
                        })(i);
                    }
                    chain.then(function () { cb(txt); }).catch(function () { cb(txt || null); });
                }).catch(function () { cb(null); });
            };
            fr.readAsArrayBuffer(file);
        });
    }

    // ---- Parser (expuesto para tests) ----
    var MESES = { ene: 1, feb: 2, mar: 3, abr: 4, may: 5, jun: 6, jul: 7, ago: 8, sep: 9, set: 9, oct: 10, nov: 11, dic: 12 };
    function digits(s) { return (s || '').replace(/\D/g, ''); }
    function toISO(d, m, y) { if (y < 100) { y += 2000; } return y + '-' + ('0' + m).slice(-2) + '-' + ('0' + d).slice(-2); }

    function findDates(t) {
        var out = [], m;
        var re = /\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})\b/g;
        while ((m = re.exec(t))) {
            var d = +m[1], mo = +m[2], y = +m[3];
            if (d >= 1 && d <= 31 && mo >= 1 && mo <= 12) { out.push({ iso: toISO(d, mo, y), idx: m.index }); }
        }
        var re2 = /\b(\d{1,2})\s+([a-zA-Záéíóú]{3,})\.?\s+(\d{4})\b/g;
        while ((m = re2.exec(t))) {
            var mm = MESES[m[2].slice(0, 3).toLowerCase()];
            if (mm) { out.push({ iso: toISO(+m[1], mm, +m[3]), idx: m.index }); }
        }
        return out;
    }
    function findAmounts(t) {
        var out = [], m;
        // Importes AR; el (?!\s*%) descarta porcentajes (ej. "3,00%").
        var re = /(\d{1,3}(?:\.\d{3})+,\d{2}|\d+,\d{2})(?!\s*%)/g;
        while ((m = re.exec(t))) {
            var v = parseFloat(m[1].replace(/\./g, '').replace(',', '.'));
            if (v > 0) { out.push({ v: v, idx: m.index }); }
        }
        return out;
    }
    // Detecta el importe a pagar. Prioriza el par (total, total+recargo) cuando la
    // factura tiene recargo por mora ("Luego del … - N%"): el TOTAL A PAGAR es el menor.
    function parseTotal(text, amounts) {
        if (!amounts.length) { return null; }
        var pctM = text.match(/(\d{1,2}(?:[.,]\d{1,2})?)\s*%/);
        if (pctM) {
            var pct = parseFloat(pctM[1].replace(',', '.'));
            if (pct > 0 && pct < 30) {
                var f = 1 + pct / 100;
                for (var i = 0; i < amounts.length; i++) {
                    for (var j = 0; j < amounts.length; j++) {
                        if (i === j) { continue; }
                        var A = amounts[i].v, B = amounts[j].v;
                        if (A > 100 && Math.abs(B - A * f) <= Math.max(2, A * 0.002)) {
                            return amounts[i]; // el menor = total a pagar en término
                        }
                    }
                }
            }
        }
        var t = nearLabel(text, ['total a pagar', 'importe total', 'total del plan', 'total en usd', 'neto a pagar', 'saldo a pagar', 'total factura'], amounts, 1200);
        if (t) { return t; }
        t = nearLabel(text, ['total'], amounts, 200);
        if (t) { return t; }
        return amounts.reduce(function (a, b) { return b.v > a.v ? b : a; }, amounts[0]);
    }
    function nearLabel(text, labels, items, win) {
        var low = text.toLowerCase(), best = null, bestD = 1e9;
        labels.forEach(function (lab) {
            var p = low.indexOf(lab);
            while (p > -1) {
                items.forEach(function (it) { var d = it.idx - p; if (d >= 0 && d < (win || 500) && d < bestD) { bestD = d; best = it; } });
                p = low.indexOf(lab, p + 1);
            }
        });
        return best;
    }
    // Palabras clave DISTINTIVAS por proveedor (la coincidencia más larga/específica gana).
    // Resuelve casos ambiguos (TELECOM vs PERSONAL, ambas de Telecom) y evita falsos
    // positivos por palabras genéricas ("servicio", "empresas").
    var KEYWORDS = {
        'EDENOR': ['edenor', 'comercializadora norte'],
        'ADMINISTRACION SILVANA LORENA GUAYCOCHEA': ['expensas', 'guaycochea'],
        'MAURICIO SZTERN': ['sztern'],
        'ESTUDIO COLLINET': ['collinet'],
        'NET2PHONE': ['net2phone', 'net 2 phone'],
        'GUPAZ': ['gupaz', 'everfull'],
        'CLARO EMPRESAS': ['claro'],
        'TELECOM EMPRESAS': ['telecom argentina'],
        'SANCOR SALUD': ['sancor'],
        'JOI SEGUROS': ['joi seguros', 'joi'],
        'GCBA - ABL': ['inmobiliario y abl', 'impuesto inmobiliario', 'y abl'],
        'GCBA - PATENTES': ['patente'],
        'ARCA': ['arca', 'a.f.i.p', 'afip'],
        'BANCOS': [],
        'GOOGLE SUITE': ['google workspace', 'google', 'gsuit'],
        'HOSPITAL ALEMAN': ['hospital aleman', 'hospital alemán'],
        'OSDE': ['osde'],
        'CHATGPT': ['chatgpt', 'openai'],
        'PERSONAL FLOW': ['telecom personal', 'personal flow']
    };
    var STOP = { servicio: 1, servicios: 1, empresas: 1, empresa: 1, sociedad: 1, anonima: 1,
        administracion: 1, seguros: 1, medicina: 1, prepaga: 1, salud: 1, gcba: 1, grupo: 1,
        argentina: 1, nacional: 1, federal: 1, publico: 1 };

    function detectProv(text) {
        var low = text.toLowerCase();
        var cuits = (text.match(/\b\d{2}-?\d{8}-?\d\b/g) || []).map(digits);
        var i;
        // 0) Telecom PERSONAL comparte CUIT con Telecom: "flow"/"resumen de servicios" ⇒ Personal.
        if (/\bflow\b|resumen de servicios|resumenmkt/.test(low)) {
            var per = PROVS.filter(function (p) { return p.nombre.toUpperCase().indexOf('PERSONAL') > -1; })[0];
            if (per) { return per; }
        }
        // 1) CUIT exacto del emisor (lo más confiable).
        for (i = 0; i < PROVS.length; i++) { if (PROVS[i].cuit && cuits.indexOf(PROVS[i].cuit) > -1) { return PROVS[i]; } }
        // 2) Keyword curado: gana la coincidencia MÁS LARGA (más específica).
        var best = null, bestLen = 0;
        PROVS.forEach(function (p) {
            var kws = KEYWORDS[p.nombre.toUpperCase()] || [];
            kws.forEach(function (kw) { if (kw && low.indexOf(kw) > -1 && kw.length > bestLen) { bestLen = kw.length; best = p; } });
        });
        if (best) { return best; }
        // 3) Fallback estricto por token distintivo del nombre (para proveedores nuevos),
        //    ignorando genéricos y exigiendo un mínimo → si no, NO adivina (deja vacío).
        var bestS = null, bestScore = 0;
        PROVS.forEach(function (p) {
            var toks = p.nombre.toLowerCase().split(/[^a-záéíóúñ0-9]+/).filter(function (x) { return x.length >= 4 && !STOP[x]; });
            var score = 0;
            toks.forEach(function (tk) { if (low.indexOf(tk) > -1) { score += tk.length; } });
            if (score > bestScore) { bestScore = score; bestS = p; }
        });
        return bestScore >= 5 ? bestS : null;
    }

    // Lee el comprobante de PAGO: fecha de pago + marca "pagado" (y valor si falta).
    function parsePago(text) {
        text = text || '';
        var dates = findDates(text), amounts = findAmounts(text);
        return {
            fecha_pago: dates.length ? dates[dates.length - 1].iso : '',
            valor: amounts.length ? amounts.reduce(function (a, b) { return b.v > a.v ? b : a; }, amounts[0]).v : null,
            estado: 'pagado'
        };
    }
    window.parsePagoGasto = parsePago;

    // Período explícito de la factura (ej. "Liquidación: 6/2026", "Período: 04/2026",
    // "Periodo facturado Septiembre 2024") → "AAAA-MM". Más confiable que deducirlo de una fecha.
    function parsePeriodoLabel(text) {
        var low = (text || '').toLowerCase();
        var m = low.match(/(?:liquidaci[óo]n|per[íi]odo(?:\s+facturado)?)\s*:?\s*(\d{1,2})\s*\/\s*(\d{4})/);
        if (m) { return m[2] + '-' + ('0' + m[1]).slice(-2); }
        m = low.match(/(?:per[íi]odo(?:\s+facturado)?|resumen\s+de)\s*:?\s*([a-záéíóú]{3,})\.?\s+(?:de\s+)?(\d{4})/);
        if (m) { var mm = MESES[m[1].slice(0, 3)]; if (mm) { return m[2] + '-' + ('0' + mm).slice(-2); } }
        return '';
    }

    function parseFactura(text) {
        text = text || '';
        var dates = findDates(text), amounts = findAmounts(text);
        var isUSD = /US\$|\bUSD\b|d[óo]lar/i.test(text);
        var emis = nearLabel(text, ['emisi', 'fecha de la factura', 'fecha de factura'], dates);
        var venc = nearLabel(text, ['vencimiento', '1° vto', '1�vto', '1 vto', 'vto.', 'vto ', 'vto:'], dates);
        // Importe: usar la CABECERA (antes del detalle/histórico) para no agarrar
        // números de las tablas de movimientos.
        var cut = text.toLowerCase().search(/detalle de (egresos|movimientos|gastos)|movimientos del|hist[oó]rico de/);
        var head = (cut > 200) ? text.slice(0, cut) : text;
        var total = parseTotal(head, findAmounts(head)) || parseTotal(text, amounts);
        if (!venc && dates.length) { venc = dates[dates.length - 1]; }
        // Emisión: NO usar la fecha de vencimiento como emisión. Tomar la fecha más
        // temprana que NO sea el vencimiento; si no hay otra, dejar vacío.
        if (!emis && dates.length) {
            var cand = dates.filter(function (d) { return !venc || d.iso !== venc.iso; });
            emis = cand.length ? cand.reduce(function (a, b) { return b.iso < a.iso ? b : a; }, cand[0]) : null;
        }
        var periodo = parsePeriodoLabel(text) || (emis ? emis.iso.slice(0, 7) : (venc ? venc.iso.slice(0, 7) : ''));
        return {
            proveedor: detectProv(text),
            valor: total ? total.v : null,
            moneda: isUSD ? 'USD' : 'ARS',
            fecha_factura: emis ? emis.iso : '',
            fecha_vencimiento: venc ? venc.iso : '',
            periodo: periodo
        };
    }
    window.parseFacturaGasto = parseFactura; // para tests

    // ---- Popup de confirmación (branded) ----
    function money(v, mon) {
        if (v == null) { return '—'; }
        var s = (mon === 'USD' ? 'US$ ' : '$ ') + v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return s;
    }
    function fmtFecha(iso) { if (!iso) { return '—'; } var p = iso.split('-'); return p[2] + '/' + p[1] + '/' + p[0]; }

    function showPopup(d) {
        var ov = document.createElement('div');
        ov.className = 'modal-overlay';
        var provNom = d.proveedor ? d.proveedor.nombre : null;
        ov.innerHTML =
            '<div class="modal" role="dialog" aria-modal="true" style="max-width:440px;text-align:left">' +
                '<div class="modal-ic"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v5h5"/><path d="M9 13h6M9 17h4"/></svg></div>' +
                '<h3 class="modal-title">Datos leídos de la factura</h3>' +
                '<p class="modal-msg" style="margin-bottom:12px">Revisá y confirmá. Podés corregir cualquier dato después en el formulario.</p>' +
                '<div class="gp-read">' +
                    row('Proveedor', provNom || '<span style="color:var(--warn)">no detectado (elegilo a mano)</span>') +
                    row('Valor mensual', money(d.valor, d.moneda)) +
                    row('Fecha de factura', fmtFecha(d.fecha_factura)) +
                    row('1er vencimiento', fmtFecha(d.fecha_vencimiento)) +
                    row('Período', d.periodo || '—') +
                '</div>' +
                '<div class="modal-actions" style="margin-top:16px">' +
                    '<button type="button" class="btn btn-ghost" data-x="cancel">Cancelar</button>' +
                    '<button type="button" class="btn btn-primary" data-x="ok">Usar estos datos</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(ov);
        void ov.offsetWidth; ov.classList.add('open');
        function close() { ov.classList.remove('open'); setTimeout(function () { ov.remove(); }, 200); }
        ov.addEventListener('click', function (e) {
            if (e.target === ov || e.target.closest('[data-x="cancel"]')) { close(); return; }
            if (e.target.closest('[data-x="ok"]')) { fillForm(d); close(); }
        });
    }
    function row(k, v) {
        return '<div style="display:flex;justify-content:space-between;gap:12px;padding:.4rem 0;border-bottom:1px solid var(--line-soft)">' +
               '<span class="muted small">' + k + '</span><strong style="text-align:right">' + v + '</strong></div>';
    }

    function setVal(name, value) {
        var el = form.querySelector('[name="' + name + '"]');
        if (!el || value == null || value === '') { return; }
        el.value = value;
        // 'input' + 'change' para que el combobox (proveedor/moneda) y el datepicker
        // (fechas) reflejen el valor en pantalla — igual que el OCR de comprobantes.
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }
    function fillForm(d) {
        if (d.proveedor) { setVal('proveedor_id', d.proveedor.id); }
        if (d.valor != null) { setVal('valor_mensual', String(d.valor).replace('.', ',')); }
        setVal('moneda', d.moneda);
        setVal('fecha_factura', d.fecha_factura);
        setVal('fecha_vencimiento', d.fecha_vencimiento);
        setVal('periodo', d.periodo);
        if (statusEl) { statusEl.textContent = 'Datos cargados desde la factura. Revisá y guardá.'; statusEl.className = 'flash flash-success'; }
    }

    btn.addEventListener('click', function () {
        var f = facturaInput.files && facturaInput.files[0];
        if (!f) { if (statusEl) { statusEl.textContent = 'Elegí primero el PDF de la factura.'; statusEl.className = 'flash flash-info'; } return; }
        if (statusEl) { statusEl.textContent = 'Leyendo la factura…'; statusEl.className = 'flash flash-info'; }
        extractText(f, function (text) {
            if (!text) { if (statusEl) { statusEl.textContent = 'No pude leer el texto del PDF (¿es un escaneo/imagen?). Cargá los datos a mano.'; statusEl.className = 'flash flash-error'; } return; }
            showPopup(parseFactura(text));
        });
    });

    // ---- Leer el comprobante de PAGO ----
    var pagoInput = form.querySelector('input[name="pago"]');
    var btnPago = document.getElementById('leerPago');
    var pagoStatus = document.getElementById('leerPagoEstado');
    function fillPago(d) {
        setVal('fecha_pago', d.fecha_pago);
        setVal('estado', 'pagado');
        if (d.valor != null) {
            var vf = form.querySelector('[name="valor_mensual"]');
            if (vf && !vf.value.trim()) { vf.value = String(d.valor).replace('.', ','); }
        }
        var el = pagoStatus || statusEl;
        if (el) {
            el.textContent = 'Pago leído: ' + (d.fecha_pago ? fmtFecha(d.fecha_pago) : 'sin fecha') + ' · marcado como Pagado.';
            el.className = 'flash flash-success';
        }
    }
    if (btnPago && pagoInput) {
        btnPago.addEventListener('click', function () {
            var f = pagoInput.files && pagoInput.files[0];
            var el = pagoStatus || statusEl;
            if (!f) { if (el) { el.textContent = 'Elegí primero el PDF del pago.'; el.className = 'flash flash-info'; } return; }
            if (el) { el.textContent = 'Leyendo el pago…'; el.className = 'flash flash-info'; }
            extractText(f, function (text) {
                if (!text) { if (el) { el.textContent = 'No pude leer el texto del pago. Cargá la fecha a mano.'; el.className = 'flash flash-error'; } return; }
                fillPago(parsePago(text));
            });
        });
    }
})();
