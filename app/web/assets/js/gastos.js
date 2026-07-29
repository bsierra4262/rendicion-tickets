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

    // ---- QR AFIP (datos EXACTOS: CUIT emisor, importe, fecha) ----
    function loadJsQr(cb) {
        if (window.jsQR) { return cb(true); }
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';
        s.onload = function () { cb(!!window.jsQR); };
        s.onerror = function () { cb(false); };
        document.head.appendChild(s);
    }
    function parseAfipQr(data) {
        var m = String(data).match(/[?&]p=([^&\s]+)/);
        if (!m) { return null; } // sólo el QR de factura electrónica (?p=base64 JSON) trae datos
        try { return JSON.parse(atob(decodeURIComponent(m[1]))); }
        catch (e) { try { return JSON.parse(atob(m[1])); } catch (e2) { return null; } }
    }
    function scanQrRegion(canvas, scale, crop) {
        if (!window.jsQR) { return null; }
        var sx = 0, sy = 0, sw = canvas.width, sh = canvas.height;
        if (crop) { sx = crop[0] * sw; sy = crop[1] * sh; sw = crop[2] * canvas.width; sh = crop[3] * canvas.height; }
        var w = Math.round(sw * scale), h = Math.round(sh * scale);
        if (w < 120 || w > 4200 || h < 120) { return null; }
        var c = document.createElement('canvas'); c.width = w; c.height = h;
        c.getContext('2d').drawImage(canvas, sx, sy, sw, sh, 0, 0, w, h);
        var d = c.getContext('2d').getImageData(0, 0, w, h);
        var code = window.jsQR(d.data, d.width, d.height, { inversionAttempts: 'attemptBoth' });
        return (code && code.data) ? parseAfipQr(code.data) : null;
    }
    function scanAfipQr(canvas) {
        var big = Math.max(canvas.width, canvas.height), base = canvas;
        if (big > 1600) {
            var s = 1600 / big; base = document.createElement('canvas');
            base.width = Math.round(canvas.width * s); base.height = Math.round(canvas.height * s);
            base.getContext('2d').drawImage(canvas, 0, 0, base.width, base.height);
        }
        return scanQrRegion(base, 1, null) || scanQrRegion(base, 1.5, null)
            || scanQrRegion(base, 1.6, [0, 0.5, 1, 0.5]) || scanQrRegion(base, 1.6, [0, 0, 1, 0.5]);
    }
    // Renderiza la 1ª página del PDF a canvas y busca el QR AFIP. Devuelve el objeto QR o null.
    // Renderiza la 1ª página del PDF (o la imagen) a un canvas. Devuelve el canvas o null.
    function renderFacturaCanvas(file, cb) {
        if (file && /^image\//.test(file.type)) {   // ya es imagen (foto/escaneo): cargarla directo
            var url = URL.createObjectURL(file), img = new Image();
            img.onload = function () {
                var c = document.createElement('canvas'); c.width = img.naturalWidth; c.height = img.naturalHeight;
                c.getContext('2d').drawImage(img, 0, 0); URL.revokeObjectURL(url); cb(c);
            };
            img.onerror = function () { URL.revokeObjectURL(url); cb(null); };
            img.src = url;
            return;
        }
        loadPdfJs(function (okP) {
            if (!okP) { return cb(null); }
            var fr = new FileReader();
            fr.onload = function () {
                window.pdfjsLib.getDocument({ data: new Uint8Array(fr.result) }).promise
                    .then(function (pdf) { return pdf.getPage(1); })
                    .then(function (page) {
                        var vp = page.getViewport({ scale: 2.5 });
                        var c = document.createElement('canvas'); c.width = vp.width; c.height = vp.height;
                        return page.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise.then(function () { return c; });
                    })
                    .then(function (canvas) { cb(canvas); })
                    .catch(function () { cb(null); });
            };
            fr.readAsArrayBuffer(file);
        });
    }
    function scanFacturaQr(file, cb) {
        loadJsQr(function (okQ) {
            if (!okQ) { return cb(null, null); }
            renderFacturaCanvas(file, function (canvas) {
                if (!canvas) { return cb(null, null); }
                cb(scanAfipQr(canvas), canvas);   // devuelve el QR y el canvas (reutilizable para OCR)
            });
        });
    }

    // OCR de imagen (perezoso): sólo para facturas escaneadas/foto SIN texto ni QR. Reutiliza el
    // motor PaddleOCR de comprobantes (assets/js/paddleocr.js), cargado bajo demanda.
    function loadPaddle(cb) {
        if (window.PaddleOCR && window.PaddleOCR.ready) { return window.PaddleOCR.ready.then(function (ok) { cb(ok); }); }
        var s = document.createElement('script');
        s.src = (window.APP_BASE || '') + 'assets/js/paddleocr.js?v=18';
        s.onload = function () {
            if (window.PaddleOCR && window.PaddleOCR.ready) { window.PaddleOCR.ready.then(function (ok) { cb(ok); }); }
            else { cb(false); }
        };
        s.onerror = function () { cb(false); };
        document.head.appendChild(s);
    }
    // Corre OCR sobre un canvas ya renderizado. cb(texto|'').
    function ocrCanvas(canvas, cb) {
        if (!canvas) { return cb(''); }
        loadPaddle(function (ok) {
            if (!ok || !window.PaddleOCR.recognize) { return cb(''); }
            try {
                window.PaddleOCR.recognize(canvas).then(function (txt) { cb(txt || ''); }).catch(function () { cb(''); });
            } catch (e) { cb(''); }
        });
    }
    function provByCuit(cuit) {
        cuit = (cuit || '').replace(/\D/g, '');
        if (!cuit) { return null; }
        for (var i = 0; i < PROVS.length; i++) {
            if ((PROVS[i].cuit || '').split(/\s+/).indexOf(cuit) > -1) { return PROVS[i]; }
        }
        return null;
    }
    function qrFecha(f) { f = String(f || ''); return /^\d{8}$/.test(f) ? (f.slice(0, 4) + '-' + f.slice(4, 6) + '-' + f.slice(6, 8)) : f.slice(0, 10); }
    // Fusiona el resultado del texto con el QR (QR = fuente de verdad para CUIT/importe/fecha).
    function mergeQr(d, qr) {
        if (qr.cuit) { var p = provByCuit(String(qr.cuit)); if (p) { d.proveedor = p; } d.qr_cuit = String(qr.cuit); }
        if (qr.importe != null && +qr.importe > 0) { d.valor = +qr.importe; }
        if (qr.fecha) { d.fecha_factura = qrFecha(qr.fecha); if (!d.periodo && d.fecha_factura) { d.periodo = d.fecha_factura.slice(0, 7); } }
        if (qr.moneda === 'DOL' || qr.moneda === 'USD') { d.moneda = 'USD'; }
        d.desde_qr = true;
        return d;
    }
    window.scanAfipQrGasto = scanAfipQr; // para tests

    // ---- Parser (expuesto para tests) ----
    var MESES = { ene: 1, feb: 2, mar: 3, abr: 4, may: 5, jun: 6, jul: 7, ago: 8, sep: 9, set: 9, oct: 10, nov: 11, dic: 12,
        jan: 1, apr: 4, aug: 8, dec: 12 };  // + inglés (feb/mar/may/jun/jul/sep/oct/nov coinciden)
    function digits(s) { return (s || '').replace(/\D/g, ''); }
    function toISO(d, m, y) { if (y < 100) { y += 2000; } return y + '-' + ('0' + m).slice(-2) + '-' + ('0' + d).slice(-2); }

    function findDates(t) {
        var out = [], m;
        var re = /\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})\b/g;
        while ((m = re.exec(t))) {
            var d = +m[1], mo = +m[2], y = +m[3];
            if (d >= 1 && d <= 31 && mo >= 1 && mo <= 12) { out.push({ iso: toISO(d, mo, y), idx: m.index }); }
        }
        var re2 = /\b(\d{1,2})\s+([a-zA-Záéíóú]{3,})\.?\s+(\d{4})\b/g;   // "12 septiembre 2025"
        while ((m = re2.exec(t))) {
            var mm = MESES[m[2].slice(0, 3).toLowerCase()];
            if (mm) { out.push({ iso: toISO(+m[1], mm, +m[3]), idx: m.index }); }
        }
        var re3 = /\b([a-zA-Z]{3,})\.?\s+(\d{1,2}),?\s+(\d{4})\b/g;       // "September 12, 2025" (inglés, mes primero)
        while ((m = re3.exec(t))) {
            var m3 = MESES[m[1].slice(0, 3).toLowerCase()];
            if (m3) { out.push({ iso: toISO(+m[2], m3, +m[3]), idx: m.index }); }
        }
        return out;
    }
    // Convención de decimales del documento. Algunas facturas (IDT/NET2PHONE, OpenAI/CHATGPT)
    // usan formato US "1,234.56"; la mayoría AR "1.234,56". Detectar mal rompe el importe.
    function isUSFormat(t) {
        var us = (t.match(/\d{1,3}(?:,\d{3})+\.\d{2}/g) || []).length;   // 1,234.56 (miles con coma)
        var ar = (t.match(/\d{1,3}(?:\.\d{3})+,\d{2}/g) || []).length;   // 1.234,56 (miles con punto)
        if (us || ar) { return us >= ar && us > 0; }
        // Sin miles agrupados: decidir por el separador decimal predominante.
        var pd = (t.match(/\d\.\d{2}(?!\d)/g) || []).length;            // 20.00
        var cd = (t.match(/\d,\d{2}(?!\d)/g) || []).length;             // 20,00
        return pd > cd;
    }
    function findAmounts(t) {
        var out = [], m, v;
        // El (?!\s*%) descarta porcentajes (ej. "3,00%").
        if (isUSFormat(t)) {
            var reu = /(\d{1,3}(?:,\d{3})+\.\d{2}|\d+\.\d{2})(?!\s*%)/g;  // US: 1,234.56 / 20.00
            while ((m = reu.exec(t))) { v = parseFloat(m[1].replace(/,/g, '')); if (v > 0) { out.push({ v: v, idx: m.index }); } }
        } else {
            var re = /(\d{1,3}(?:\.\d{3})+,\d{2}|\d+,\d{2})(?!\s*%)/g;    // AR: 1.234,56 / 20,00
            while ((m = re.exec(t))) { v = parseFloat(m[1].replace(/\./g, '').replace(',', '.')); if (v > 0) { out.push({ v: v, idx: m.index }); } }
        }
        return out;
    }
    // Detecta el importe a pagar. Prioriza el par (total, total+recargo) cuando la
    // factura tiene recargo por mora ("Luego del … - N%"): el TOTAL A PAGAR es el menor.
    function parseTotal(text, amounts) {
        if (!amounts.length) { return null; }
        // Impuesto Inmobiliario/ABL: no hay etiqueta "total"; la VALUACIÓN DEL TERRENO es un
        // número enorme que NO es lo que se paga. El monto a pagar (cuota/total) se repite;
        // la valuación aparece una sola vez → gana el más frecuente (desempate: el menor).
        if (/valuaci[óo]n del terreno|porcentual fiscal|inmobiliario y abl/i.test(text)) {
            var freq = {};
            amounts.forEach(function (a) { freq[a.v] = (freq[a.v] || 0) + 1; });
            var mode = amounts.slice().sort(function (a, b) { return (freq[b.v] - freq[a.v]) || (a.v - b.v); })[0];
            if (mode) { return mode; }
        }
        // 1) Etiqueta PEGADA al importe (el número sigue a la etiqueta en ≤ ~22 caracteres):
        //    señal muy confiable (NET2PHONE "Total: $ 26,645.40", OpenAI "Amount due $20.00").
        //    Va ANTES del par-recargo para no confundir alícuotas de IVA con el total (NET2PHONE).
        var TLABS = ['total a pagar', 'amount due', 'balance due', 'total due', 'importe total',
            'total en usd', 'neto a pagar', 'saldo a pagar', 'total factura', 'total:'];
        var tight = nearLabel(text, TLABS, amounts, 22);
        if (tight) { return tight; }
        // 2) Par (total, total+recargo) por mora cuando la etiqueta está LEJOS del número
        //    (EXPENSAS: "TOTAL A PAGAR … 470.895,74 … +3% 485.022,74"): el a pagar es el menor.
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
        // 3) Etiqueta con ventana amplia (por si el número quedó algo lejos).
        var t = nearLabel(text, ['total a pagar', 'importe total', 'total del plan', 'total en usd', 'neto a pagar', 'saldo a pagar', 'total factura', 'amount due', 'balance due', 'total due'], amounts, 1200);
        if (t) { return t; }
        t = nearLabel(text, ['total'], amounts, 200);
        if (t) { return t; }
        // 4) Último recurso: el mayor.
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
        'NET2PHONE': ['net2phone', 'net 2 phone', 'idt corporation', 'idt corp'],
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

    function validCuit(c) {
        if (!/^\d{11}$/.test(c)) { return false; }
        var w = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2], s = 0, i;
        for (i = 0; i < 10; i++) { s += (+c[i]) * w[i]; }
        var dv = 11 - (s % 11); if (dv === 11) { dv = 0; } if (dv === 10) { return false; }
        return dv === +c[10];
    }
    function detectProv(text) {
        var low = text.toLowerCase();
        // CUITs SOLO de la cabecera (antes del detalle/histórico): las tablas de detalle
        // traen cientos de números y alguno puede coincidir con un CUIT válido (falso positivo).
        var cut = low.search(/detalle de (egresos|movimientos|gastos)|movimientos del|hist[oó]rico/);
        var head = (cut > 200) ? text.slice(0, cut) : text;
        var cuits = (head.match(/\b\d{2}-?\d{8}-?\d\b/g) || []).map(digits).filter(validCuit);
        var i;
        // 0) Telecom PERSONAL comparte CUIT con Telecom: "flow"/"resumen de servicios" ⇒ Personal.
        if (/\bflow\b|resumen de servicios|resumenmkt/.test(low)) {
            var per = PROVS.filter(function (p) { return p.nombre.toUpperCase().indexOf('PERSONAL') > -1; })[0];
            if (per) { return per; }
        }
        // 1) CUIT exacto del emisor (lo más confiable). Un proveedor puede tener VARIOS CUIT
        //    (EXPENSAS/BANCOS agrupan varias empresas) → guardados separados por espacio.
        for (i = 0; i < PROVS.length; i++) {
            var pc = (PROVS[i].cuit || '').split(/\s+/);
            for (var kc = 0; kc < pc.length; kc++) { if (pc[kc] && cuits.indexOf(pc[kc]) > -1) { return PROVS[i]; } }
        }
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
        var emis = nearLabel(text, ['emisi', 'fecha de la factura', 'fecha de factura', 'date of issue'], dates);
        var venc = nearLabel(text, ['vencimiento', '1° vto', '1�vto', '1 vto', 'vto.', 'vto ', 'vto:', 'date due', 'due date'], dates);
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
        var qrNote = d.desde_qr ? '<span style="color:var(--accent-600,#5f8a12);font-weight:600">✓ Datos exactos del QR de AFIP.</span> ' : '';
        ov.innerHTML =
            '<div class="modal" role="dialog" aria-modal="true" style="max-width:440px;text-align:left">' +
                '<div class="modal-ic"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v5h5"/><path d="M9 13h6M9 17h4"/></svg></div>' +
                '<h3 class="modal-title">Datos leídos de la factura</h3>' +
                '<p class="modal-msg" style="margin-bottom:12px">' + qrNote + 'Revisá y confirmá. Podés corregir cualquier dato después en el formulario.</p>' +
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

    // Lee una factura (File) y muestra el popup. No toca el input → al re-leer un archivo ya
    // subido (al editar) NO se re-sube (no se duplica en Drive).
    function leerFacturaDe(f) {
        if (!f) { if (statusEl) { statusEl.textContent = 'Elegí primero el PDF de la factura.'; statusEl.className = 'flash flash-info'; } return; }
        if (statusEl) { statusEl.textContent = 'Leyendo la factura…'; statusEl.className = 'flash flash-info'; }
        extractText(f, function (text) {
            scanFacturaQr(f, function (qr, canvas) {
                function finish(txt) {
                    if (!txt && !qr) { if (statusEl) { statusEl.textContent = 'No pude leer la factura (ni texto, ni QR, ni OCR). Cargá los datos a mano.'; statusEl.className = 'flash flash-error'; } return; }
                    var d = parseFactura(txt || '');
                    if (qr) { d = mergeQr(d, qr); }  // QR AFIP = datos exactos (manda sobre el texto/OCR)
                    showPopup(d);
                }
                var hasText = text && text.replace(/\s/g, '').length > 20;
                if (hasText || qr) { return finish(text); }
                // Sin texto ni QR (factura escaneada / foto): recién ahí se activa el OCR de imagen.
                if (statusEl) { statusEl.textContent = 'La factura no tiene texto ni QR; leyéndola con OCR (puede tardar unos segundos)…'; statusEl.className = 'flash flash-info'; }
                ocrCanvas(canvas, function (ocrText) { finish(ocrText); });
            });
        });
    }
    btn.addEventListener('click', function () { leerFacturaDe(facturaInput.files && facturaInput.files[0]); });
    window.gastoLeerFacturaFile = leerFacturaDe;

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
    function leerPagoDe(f) {
        var el = pagoStatus || statusEl;
        if (!f) { if (el) { el.textContent = 'Elegí primero el PDF del pago.'; el.className = 'flash flash-info'; } return; }
        if (el) { el.textContent = 'Leyendo el pago…'; el.className = 'flash flash-info'; }
        extractText(f, function (text) {
            if (!text) { if (el) { el.textContent = 'No pude leer el texto del pago. Cargá la fecha a mano.'; el.className = 'flash flash-error'; } return; }
            fillPago(parsePago(text));
        });
    }
    if (btnPago && pagoInput) {
        btnPago.addEventListener('click', function () { leerPagoDe(pagoInput.files && pagoInput.files[0]); });
    }
    window.gastoLeerPagoFile = leerPagoDe;

    // Re-leer un archivo YA cargado (al editar): lo trae del server (proxy de Drive) y lo pasa
    // al lector SIN usar el input → al guardar no se re-sube (no se duplica).
    form.querySelectorAll('[data-releer]').forEach(function (b) {
        b.addEventListener('click', function () {
            var tipo = b.getAttribute('data-releer');       // 'factura' | 'pago'
            var src = b.getAttribute('data-src');
            var el = (tipo === 'pago' ? pagoStatus : statusEl) || statusEl;
            if (el) { el.textContent = 'Trayendo el ' + tipo + ' ya cargado…'; el.className = 'flash flash-info'; }
            b.disabled = true;
            fetch(src, { headers: { 'X-Requested-With': 'fetch' } })
                .then(function (r) { if (!r.ok) { throw new Error('no disponible'); } return r.blob(); })
                .then(function (blob) {
                    var ext = /pdf/.test(blob.type) ? 'pdf' : 'jpg';
                    var file = new File([blob], tipo + '.' + ext, { type: blob.type || 'application/pdf' });
                    if (tipo === 'pago') { leerPagoDe(file); } else { leerFacturaDe(file); }
                })
                .catch(function (e) { if (el) { el.textContent = 'No se pudo traer el ' + tipo + ': ' + (e && e.message || e); el.className = 'flash flash-error'; } })
                .then(function () { b.disabled = false; });
        });
    });
})();
