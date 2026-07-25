/* ============================================================
   Escaneo de tickets — pipeline 100% gratis en el navegador:
   1) PDF -> canvas (pdf.js)   2) QR de AFIP (jsQR, datos exactos)
   3) OCR con preprocesado (Tesseract.js)   4) autoclasificación por CUIT.
   pdf.js y jsQR se cargan desde la vista; Tesseract on-demand.
   ============================================================ */
(function () {
    'use strict';
    var base = window.APP_BASE || '/';
    var fileInput = document.getElementById('scanFile');
    var btn = document.getElementById('scanBtn');
    var statusEl = document.getElementById('scanStatus');
    var preview = document.getElementById('scanPreview');
    var ocrText = document.getElementById('ocrText');
    if (!fileInput || !btn) return;

    // Al GUARDAR (submit), liberar el modelo/WebGPU del OCR para que la navegación sea
    // instantánea (si no, la página queda "pensando" liberando ~15MB + contexto GPU).
    var compForm = document.getElementById('compForm');
    function showSaveErrors(errors) {
        var msgs = [];
        for (var k in errors) { if (errors.hasOwnProperty(k)) msgs.push(errors[k]); }
        var box = document.getElementById('ajaxErrors');
        if (!box) {
            box = document.createElement('div');
            box.id = 'ajaxErrors'; box.className = 'flash flash-error';
            compForm.insertBefore(box, compForm.firstChild);
        }
        box.textContent = 'Revisá los campos marcados: ' + (msgs.join(' · ') || 'hay datos incompletos.');
        box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var first = Object.keys(errors)[0];
        var el = first && document.querySelector('[name="' + first + '"]');
        if (el && el.focus) { try { el.focus(); } catch (e) {} }
    }
    if (compForm) {
        // Guardado por AJAX: si hay error, NO recarga (la imagen + datos quedan intactos);
        // muestra el error inline, corregís y guardás de nuevo sin re-escanear.
        compForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var save = compForm.querySelector('[type="submit"]'); if (save) save.disabled = true;
            if (window.appLog) window.appLog('comprobante.guardando', 'Cliente envió el formulario', 'accion');
            setStatus('Guardando…');
            var fd = new FormData(compForm);
            // Guardar la imagen YA PROCESADA (orientada / varias fotos unidas), no la original.
            if (workCanvas) {
                try {
                    workCanvas.toBlob(function (blob) { if (blob) fd.set('imagen', blob, 'ticket.jpg'); send(fd); }, 'image/jpeg', 0.9);
                    return;
                } catch (e2) {}
            }
            send(fd);
        });
        function send(fd) {
            var save = compForm.querySelector('[type="submit"]');
            fetch(compForm.getAttribute('action') || window.location.href, {
                method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' }
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (d && d.ok) {
                    try { if (window.PaddleOCR && window.PaddleOCR.destroy) window.PaddleOCR.destroy(); } catch (e) {}
                    window.location.href = d.redirect;
                } else {
                    if (save) save.disabled = false;
                    setStatus('Revisá los campos marcados (la imagen y los datos se conservan).');
                    showSaveErrors((d && d.errors) || {});
                }
            }).catch(function () {
                // Si el AJAX falla, guardado normal (último recurso).
                if (save) save.disabled = false;
                compForm.submit();
            });
        }
    }

    var workCanvas = null;
    var worker = null;
    var engine = 'paddle'; // sólo PaddleOCR (Tesseract deshabilitado a pedido del cliente)
    var stagePrefix = 'Escaneando';
    function setStatus(t) { if (statusEl) statusEl.textContent = t; }

    // Barra de progreso (para que medium + 4 pasadas nunca parezca colgado).
    var progWrap = null, progBar = null;
    function ensureProgress() {
        if (progWrap || !statusEl || !statusEl.parentNode) return;
        progWrap = document.createElement('div');
        progWrap.style.cssText = 'height:8px;background:rgba(102,51,153,.14);border-radius:6px;overflow:hidden;margin:8px 0;display:none';
        progBar = document.createElement('div');
        progBar.style.cssText = 'height:100%;width:0;background:var(--primary-600,#663399);transition:width .35s ease';
        progWrap.appendChild(progBar);
        statusEl.parentNode.insertBefore(progWrap, statusEl.nextSibling);
    }
    function setProgress(pct) {
        ensureProgress();
        if (!progWrap) return;
        if (pct == null) { progWrap.style.display = 'none'; progBar.style.width = '0'; return; }
        progWrap.style.display = 'block';
        progBar.style.width = Math.max(0, Math.min(100, pct)) + '%';
    }

    // Resumen SIEMPRE en base al estado REAL de los campos (no a una lista previa): así no
    // dice "falta Categoría" cuando ya se cargó por keyword/padrón (bug del cliente). Se
    // re-llama tras cada paso async (categoría, padrón) para reflejar la verdad.
    var summaryExtra = '', summaryNotes = [];
    function refreshSummary() {
        if (!statusEl) return;
        var map = [['Fecha', 'fecha_emision'], ['Tipo', 'tipo_comprobante'], ['Punto de venta', 'punto_venta'],
            ['Número', 'numero'], ['CUIT', 'cuit'], ['Razón social', 'razon_social'], ['Tasa IVA', 'tasa_iva'],
            ['Neto', 'importe_neto'], ['IVA', 'importe_iva'], ['Total', 'importe_total'], ['Categoría', 'concepto_id']];
        var esenc = { 'Fecha': 1, 'Tipo': 1, 'Punto de venta': 1, 'Número': 1, 'CUIT': 1, 'Total': 1, 'Categoría': 1 };
        var cargado = [], falta = [];
        map.forEach(function (f) {
            var el = q(f[1]); var v = el ? String(el.value || '').trim() : '';
            if (f[1].indexOf('importe_') === 0 && (v === '' || parseNum(v) === 0)) v = '';   // 0/0,00 = vacío
            if (v) cargado.push(f[0]); else if (esenc[f[0]]) falta.push(f[0]);
        });
        var html = '<strong>Listo' + (summaryExtra || '') + '.</strong>';
        html += cargado.length
            ? '<br><span style="color:var(--accent-600)">✓ Detecté y cargué:</span> ' + cargado.join(' · ')
            : '<br>No pude leer datos claros de esta foto (probá otra o cargá a mano).';
        if (falta.length) html += '<br><span style="color:var(--warn)">✎ Completá a mano:</span> ' + falta.join(' · ');
        else html += '<br><span style="color:var(--accent-600)">✓ Todo cargado</span> — revisá y guardá.';
        for (var i = 0; i < summaryNotes.length; i++) html += '<br>' + summaryNotes[i];
        statusEl.innerHTML = html;
    }
    function q(name) { return document.querySelector('[name="' + name + '"]'); }
    function setVal(name, val) {
        if (val === null || val === undefined || val === '') return false;
        var el = q(name);
        if (!el) return false;
        el.value = val;
        // 'input' para el autocálculo; 'change' para que el combobox/datepicker
        // sincronicen su etiqueta visible (antes quedaban sin actualizarse).
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    // ---------- carga del archivo ----------
    fileInput.addEventListener('change', function () {
        var files = fileInput.files;
        if (!files || !files.length) return;
        workCanvas = null;
        if (files.length > 1) { loadMultiple(files); return; }   // varias fotos → 1 ticket
        var f = files[0];
        if (/pdf$/i.test(f.type) || /\.pdf$/i.test(f.name)) loadPdf(f);
        else loadImage(f);
    });

    // Varias fotos de un ticket ULTRA-LARGO → se apilan verticalmente en un solo canvas
    // (el mismo ancho), y el OCR lo lee por franjas. Sirve para recibos de 1m+ fotografiados
    // por partes a máxima calidad. Sin límite de cantidad.
    function loadMultiple(files) {
        var arr = Array.prototype.slice.call(files).filter(function (f) {
            return /^image\//.test(f.type) || /\.(jpe?g|png|webp|bmp)$/i.test(f.name);
        });
        if (!arr.length) { setStatus('Elegí fotos (imágenes) para unir en un ticket largo.'); return; }
        setStatus('Uniendo ' + arr.length + ' fotos en un solo ticket…');
        var imgs = new Array(arr.length), loaded = 0;
        arr.forEach(function (f, i) {
            var img = new Image();
            img.onload = function () { imgs[i] = img; if (++loaded === arr.length) combine(); };
            img.onerror = function () { imgs[i] = null; if (++loaded === arr.length) combine(); };
            img.src = URL.createObjectURL(f);
        });
        function combine() {
            var valid = imgs.filter(Boolean);
            if (!valid.length) { setStatus('No se pudieron leer las fotos.'); return; }
            var W = 0; valid.forEach(function (im) { W = Math.max(W, im.width); });
            W = Math.min(W, 2000);                                  // cap de ancho (memoria)
            var rows = valid.map(function (im) { return { im: im, h: Math.round(im.height * W / im.width) }; });
            var H = rows.reduce(function (a, r) { return a + r.h; }, 0);
            var c = document.createElement('canvas'); c.width = W; c.height = H;
            var ctx = c.getContext('2d'), y = 0;
            rows.forEach(function (r) { ctx.drawImage(r.im, 0, y, W, r.h); y += r.h; try { URL.revokeObjectURL(r.im.src); } catch (e) {} });
            workCanvas = c; showPreview(c);
            setStatus('✓ ' + valid.length + ' fotos unidas en 1 ticket (' + W + '×' + H + 'px). Tocá "Escanear con OCR".');
            if (window.appLog) window.appLog('ocr.multifoto', valid.length + ' fotos unidas · ' + W + 'x' + H, 'accion');
        }
    }

    function showPreview(c) { try { preview.src = c.toDataURL('image/jpeg', 0.8); preview.style.display = 'block'; } catch (e) {} }

    function fitCanvas(img) {
        var w = img.width, h = img.height, s = 1;
        if (w < 1100) s = 1600 / w; else if (w > 2400) s = 2400 / w;
        var c = document.createElement('canvas');
        c.width = Math.round(w * s); c.height = Math.round(h * s);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        return c;
    }
    function loadImage(f) {
        setStatus('Preparando imagen…');
        var img = new Image();
        img.onload = function () { workCanvas = fitCanvas(img); showPreview(workCanvas); setStatus('Imagen lista. Tocá "Escanear con OCR".'); URL.revokeObjectURL(img.src); };
        img.onerror = function () { setStatus('No se pudo leer la imagen.'); };
        img.src = URL.createObjectURL(f);
    }
    // pdf.js se carga BAJO DEMANDA (solo si el usuario sube un PDF) → la página de
    // escaneo pesa 377KB menos en el caso común (fotos).
    function ensurePdfLib(cb) {
        if (window.pdfjsLib) return cb(true);
        setStatus('Cargando lector de PDF…');
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/legacy/build/pdf.min.js';
        s.onload = function () {
            try { if (window.pdfjsLib) window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/legacy/build/pdf.worker.min.js'; } catch (e) {}
            cb(!!window.pdfjsLib);
        };
        s.onerror = function () { cb(false); };
        document.head.appendChild(s);
    }
    function loadPdf(f) {
        ensurePdfLib(function (ok) {
            if (!ok) { setStatus('El lector de PDF no está disponible; probá con una foto.'); return; }
            renderPdf(f);
        });
    }
    function renderPdf(f) {
        setStatus('Leyendo PDF…');
        var fr = new FileReader();
        fr.onload = function () {
            window.pdfjsLib.getDocument({ data: new Uint8Array(fr.result) }).promise
                .then(function (pdf) { return pdf.getPage(1); })
                .then(function (page) {
                    var vp = page.getViewport({ scale: 3 });
                    var c = document.createElement('canvas'); c.width = vp.width; c.height = vp.height;
                    return page.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise.then(function () { return c; });
                })
                .then(function (c) { workCanvas = c; showPreview(c); setStatus('PDF listo. Tocá "Escanear con OCR".'); })
                .catch(function (e) { setStatus('No se pudo procesar el PDF: ' + (e && e.message || e)); });
        };
        fr.readAsArrayBuffer(f);
    }

    // ---------- botón procesar ----------
    // Vacía los campos que llena el OCR → cada escaneo empieza limpio (no deja valores
    // de un ticket anterior). Los no detectados quedan VACÍOS (para completar a mano).
    function clearFields() {
        ['fecha_emision', 'tipo_comprobante', 'letra', 'punto_venta', 'numero', 'cuit',
         'razon_social', 'importe_neto', 'tasa_iva', 'importe_iva', 'importe_percepcion',
         'importe_total', 'observaciones', 'concepto_id'].forEach(function (n) {
            var el = q(n); if (!el) return;
            el.value = '';
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    btn.addEventListener('click', function () {
        if (!workCanvas) { setStatus('Elegí o sacá una foto/PDF primero.'); return; }
        btn.disabled = true;
        clearFields();                       // arranca limpio: sin datos del escaneo anterior
        setStatus('Analizando imagen…'); setProgress(4);
        var barcode = null, scanT0 = Date.now();
        if (window.appLog) window.appLog('ocr.inicio', 'Escaneo iniciado', 'accion');
        // Cedemos el hilo un instante para que el estado + la barra se PINTEN antes del
        // trabajo pesado (así no parece congelado al apretar el botón).
        setTimeout(runScan, 30);

        function runScan() {
            // 1) QR + código de barras con el mejor lector disponible (nativo → JS)
            scanCodes(workCanvas, function (res) {
                if (res.qr) {   // QR de ARCA con datos = fuente de verdad, sin OCR
                    var n = fillFromQr(res.qr);
                    setProgress(100); setTimeout(function () { setProgress(null); }, 600);
                    summaryExtra = ' · datos exactos del QR de ARCA';
                    summaryNotes = ['<span style="color:var(--accent-600)">✓ Leído del QR de ARCA</span> (' + n + ' campos, sin OCR)'];
                    refreshSummary();
                    if (window.appLog) window.appLog('ocr.qr', 'QR ARCA leído · ' + n + ' campos', 'accion');
                    keywordCategory((q('razon_social') ? q('razon_social').value : '')); refreshSummary();
                    if (res.qr.cuit) enrichProveedor(res.qr.cuit, '');
                    btn.disabled = false;
                    return;
                }
                barcode = res.bc; // CUIT/tipo/PtoVta/CAE — fuente extra, aunque el OCR falle
                // 2) OCR — sólo PaddleOCR (Tesseract deshabilitado a pedido del cliente)
                setStatus('Cargando PaddleOCR…'); setProgress(8);
                ensureEngine(function () { ocrWithRotation(finishScan); });
            });
        }

        function finishScan(best) {
            var text = best.text || '';
            if (ocrText) ocrText.value = text;
            var r = parseAndFill(text);
            // El código de barras (si se leyó) PISA CUIT/tipo/PtoVta: es machine-readable,
            // más confiable que el OCR. El CAE queda en Observaciones.
            if (barcode && barcode.cuit) {
                setVal('cuit', barcode.cuit);
                if (barcode.ptoVta) setVal('punto_venta', ('0000' + barcode.ptoVta).slice(-4));
                var tp = AFIP_TIPO[barcode.tipoCod];
                if (tp) { setVal('tipo_comprobante', tp[0]); setVal('letra', tp[1]); }
                if (barcode.cae) {
                    var obs = q('observaciones'), vto = barcode.vto;
                    var vtoTxt = /^\d{8}$/.test(vto) ? (' · Vto ' + vto.slice(6, 8) + '/' + vto.slice(4, 6) + '/' + vto.slice(0, 4)) : '';
                    if (obs && !obs.value.trim()) obs.value = 'CAE ' + barcode.cae + vtoTxt;
                }
                r.cuit = barcode.cuit;
                if (r.filled.indexOf('CUIT') === -1) r.filled.push('CUIT');
            }
            var P = window.PaddleOCR || {};
            var motor = 'PaddleOCR ' + (P.tier || '') + (P.backend ? ' · ' + P.backend : '');
            var extra = (best.deg ? ' · imagen girada ' + best.deg + '° automáticamente' : '') + (barcode ? ' · + código de barras' : '') + ' · motor: ' + motor.trim();
            summaryExtra = extra; summaryNotes = []; refreshSummary();
            if (window.appLog) {
                var fn = (fileInput.files && fileInput.files[0] && fileInput.files[0].name) || 'imagen';
                window.appLog('ocr.escaneo', fn + ' · ' + motor.trim() + ' · ' + (Date.now() - scanT0) + 'ms' + (best.deg ? ' · girada ' + best.deg + '°' : '') + (barcode ? ' · +barra' : '') + ' · detectó: ' + (r.filled.join(', ') || 'nada'), 'accion');
            }
            // Categoría SIEMPRE por palabras clave (texto + razón social). Si el CUIT
            // está en el padrón, el enriquecimiento refina categoría/razón desde la DB.
            var rz = q('razon_social');
            keywordCategory(text + ' ' + (rz ? rz.value : ''));
            refreshSummary();
            // Enriquecer desde el padrón con VALIDACIÓN CRUZADA: CUIT y/o razón + el CUIT
            // crudo (hint difuso). Resuelve el emisor aunque el OCR haya leído mal el CUIT.
            enrichProveedor(r.cuit, rz ? rz.value : '', r.cuitOcr);
            setProgress(100); setTimeout(function () { setProgress(null); }, 700);
            btn.disabled = false;
        }
    });

    // Motor: SÓLO PaddleOCR (Tesseract deshabilitado a pedido, para evaluar Paddle solo).
    function ensureEngine(cb) {
        if (window.PaddleOCR && window.PaddleOCR.ready) {
            setStatus('Cargando PaddleOCR (medium)… la 1ª vez baja el modelo, después va rápido.');
            window.PaddleOCR.ready.then(function (ok) {
                if (ok) { engine = 'paddle'; cb(); }
                else { setStatus('⚠ No se pudo cargar PaddleOCR. Recargá con Ctrl+F5 y reintentá.'); setProgress(null); btn.disabled = false; }
            });
        } else {
            setStatus('⚠ PaddleOCR no disponible en este navegador. Recargá con Ctrl+F5.'); setProgress(null); btn.disabled = false;
        }
    }
    // Preproceso según motor: Tesseract → gris+Otsu; PaddleOCR → color redimensionado.
    function prepFor(src, maxSide) {
        if (engine !== 'paddle') return preprocess(src, maxSide);
        var big = Math.max(src.width, src.height), sc = 1;
        if (maxSide && big > maxSide) sc = maxSide / big;
        else if (big < 1500) sc = Math.min(2, 1500 / big);
        if (sc === 1) return src;
        var c = document.createElement('canvas'); c.width = Math.round(src.width * sc); c.height = Math.round(src.height * sc);
        c.getContext('2d').drawImage(src, 0, 0, c.width, c.height);
        return c;
    }
    // Reconoce un canvas y devuelve el texto (según el motor activo).
    function recognize(src, maxSide) {
        var img = prepFor(src, maxSide);
        if (engine === 'paddle') return window.PaddleOCR.recognize(img);
        return worker.recognize(img).then(function (r) { return (r && r.data && r.data.text) || ''; });
    }

    // Carga la librería y crea un worker persistente configurado para recibos.
    function ensureWorker(cb) {
        if (worker) return cb(worker);
        ensureTesseractLib(function () {
            setStatus('Inicializando OCR…');
            window.Tesseract.createWorker('spa+eng', 1, {
                logger: function (m) { if (m.status === 'recognizing text') setStatus(stagePrefix + '… ' + Math.round(m.progress * 100) + '%'); }
            }).then(function (w) {
                return w.setParameters({ tessedit_pageseg_mode: '6', preserve_interword_spaces: '1' }).then(function () { worker = w; cb(w); });
            }).catch(function (e) { setStatus('No se pudo iniciar el OCR: ' + (e && e.message || e)); btn.disabled = false; });
        });
    }
    function ensureTesseractLib(cb) {
        if (window.Tesseract) return cb();
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
        s.onload = cb;
        s.onerror = function () { setStatus('No se pudo cargar el motor OCR (revisá la conexión).'); btn.disabled = false; };
        document.head.appendChild(s);
    }

    // Cuenta señales típicas de un ticket para elegir la mejor orientación.
    function scoreText(t) {
        var U = t.toUpperCase();
        var kw = (U.match(/CUIT|C\.U\.I\.T|TOTAL|FACTURA|TIQUE|\bIVA\b|IMPORTE|SUBTOTAL|RESPONSABLE|CONSUMIDOR/g) || []).length;
        var dates = (U.match(/\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}/g) || []).length;
        var money = (U.match(/\d{1,3}(?:[.,]\d{3})*[.,]\d{2}\b/g) || []).length;
        return kw + dates * 2 + Math.min(money, 5);
    }
    function rotate(canvas, deg) {
        var c = document.createElement('canvas'), rad = deg * Math.PI / 180;
        if (deg === 90 || deg === 270) { c.width = canvas.height; c.height = canvas.width; }
        else { c.width = canvas.width; c.height = canvas.height; }
        var ctx = c.getContext('2d');
        ctx.translate(c.width / 2, c.height / 2); ctx.rotate(rad);
        ctx.drawImage(canvas, -canvas.width / 2, -canvas.height / 2);
        return c;
    }
    // Orientación: 1º el CLASIFICADOR ONNX (doc_ori, 1 inferencia ~ms, ~99%) endereza la foto;
    // si el modelo no está, cae al bruteforce (probar las 4 orientaciones a baja resolución).
    // Luego una lectura final a resolución completa (adaptativa + franjas si es largo).
    function ocrWithRotation(done) {
        var P = window.PaddleOCR || {};
        if (engine === 'paddle' && typeof P.orientation === 'function') {
            setStatus('Detectando orientación…'); setProgress(16);
            P.orientation(workCanvas).then(function (corr) {
                corr = corr || 0;
                if (corr) {
                    // Enderezar la imagen DE UNA: se rota el canvas y se deja el preview ya
                    // orientado (pedido del cliente). La lectura final ya trabaja derecha.
                    workCanvas = rotate(workCanvas, corr);
                    showPreview(workCanvas);
                    if (window.appLog) window.appLog('ocr.orientacion', 'imagen enderezada ' + corr + '° (modelo doc-ori)', 'accion');
                }
                finalRead(0, '', corr);
            }).catch(function (e) { if (window.appLog) window.appLog('ocr.orient.run_error', (e && (e.message || e)) || 'orientación', 'error'); bruteForce(); });
        } else {
            bruteForce();
        }

        // Fallback: probar las 4 orientaciones a 640px y quedarse con la de mejor score.
        function bruteForce() {
            var queue = [0, 180, 90, 270], qi = 0, results = [];
            (function detect() {
                if (qi >= queue.length) {
                    var b = results.slice().sort(function (a, c) { return c.score - a.score; })[0] || { deg: 0, text: '' };
                    return finalRead(b.deg, b.text);
                }
                var deg = queue[qi];
                var src = deg ? rotate(workCanvas, deg) : workCanvas;
                stagePrefix = 'Buscando orientación ' + (qi + 1) + ' de 4';
                setStatus(stagePrefix + '…'); setProgress(12 + qi * 15);
                recognize(src, 640).then(function (txt) {
                    var sc = scoreText(txt || '');
                    results.push({ deg: deg, text: txt || '', score: sc });
                    if (qi === 0 && sc >= 8) { qi = queue.length; return setTimeout(detect, 20); }
                    qi++; setTimeout(detect, 20);
                }).catch(function () { qi++; setTimeout(detect, 20); });
            })();
        }

        // Lectura final ADAPTATIVA: 1) lectura rápida 1200px → si trae CUIT+señales, listo.
        // 2) si no alcanza → DEWARP (rectifica papel) y re-lee. 3) si sigue faltando y es largo
        // → franjas a máxima calidad. displayDeg = grados que se enderezó (para el resumen/CRON).
        function finalRead(deg, seedText, displayDeg) {
            var src = deg ? rotate(workCanvas, deg) : workCanvas;
            var showDeg = (displayDeg != null) ? displayDeg : deg;
            var triedUnwarp = false;
            stagePrefix = 'Lectura final' + (showDeg ? ' · imagen girada ' + showDeg + '°' : '');
            setStatus(stagePrefix + '…'); setProgress(84);
            attempt(src, seedText);

            function isEnough(txt) {
                return /\b\d{2}[-\s.]?\d{8}[-\s.]?\d\b/.test(txt) && scoreText(txt) >= 6 && txt.length > 350;
            }
            function attempt(canvas, seed) {
                recognize(canvas, 1200).then(function (txt) {
                    txt = txt || seed || '';
                    if (isEnough(txt) || engine !== 'paddle') return finish(canvas, txt);
                    var P = window.PaddleOCR || {};
                    if (!triedUnwarp && typeof P.unwarp === 'function') {
                        triedUnwarp = true;
                        setStatus('Corrigiendo distorsión del papel…'); setProgress(88);
                        if (window.appLog) window.appLog('ocr.dewarp.intento', 'lectura pobre → rectificando papel', 'accion');
                        P.unwarp(canvas).then(function (uc) {
                            if (uc) { if (window.appLog) window.appLog('ocr.dewarp', 'papel rectificado, re-leyendo', 'accion'); attempt(uc, txt); }
                            else finish(canvas, txt);
                        }).catch(function () { finish(canvas, txt); });
                        return;
                    }
                    finish(canvas, txt);
                }).catch(function (e) {
                    if (window.appLog) window.appLog('ocr.lectura_error', (e && (e.message || e)) || 'recognize', 'error');
                    finish(canvas, seed || '');
                });
            }
            function finish(canvas, txt) {
                if (engine === 'paddle' && !isEnough(txt) && canvas.height / canvas.width > 1.4) {
                    setStatus('Recibo largo: leyendo con más detalle…');
                    readTiled(canvas, function (t2) { done({ deg: showDeg, text: (t2 && t2.length > txt.length) ? t2 : txt }); });
                } else {
                    done({ deg: showDeg, text: txt });
                }
            }
        }
    }

    // Lectura por franjas para tickets largos: divide en tiras horizontales solapadas,
    // cada una a buena resolución, y concatena el texto. Cada tira va al worker (fluido).
    function readTiled(src, cb) {
        var ratio = src.height / src.width;
        // Recibos largos/extralargos: MUCHAS franjas (mínimo 10, hasta 16) a MÁXIMA calidad →
        // cada franja abarca menos alto = texto grande y legible → no se saltea nada (cliente).
        var stripes = Math.max(10, Math.min(16, Math.ceil(ratio * 6)));
        // franja a ~1800px de ancho (máxima calidad) + solape 22% (SAHI: 20-25% óptimo) para
        // que ningún renglón quede cortado entre franjas y no se saltee nada.
        var over = 0.22, sc = Math.min(3.0, 1800 / src.width), results = [], i = 0;
        function next() {
            if (i >= stripes) { cb(results.join('\n')); return; }
            var sh = src.height / stripes;
            var sy = Math.max(0, i * sh - sh * over);
            var h = Math.min(src.height - sy, sh + sh * over * 2);
            var c = document.createElement('canvas');
            c.width = Math.round(src.width * sc); c.height = Math.round(h * sc);
            c.getContext('2d').drawImage(src, 0, sy, src.width, h, 0, 0, c.width, c.height);
            stagePrefix = 'Leyendo franja ' + (i + 1) + ' de ' + stripes + ' (ticket largo)';
            setStatus(stagePrefix + '…'); setProgress(80 + Math.round(i / stripes * 18));
            window.PaddleOCR.recognize(c).then(function (txt) { results.push(txt || ''); i++; setTimeout(next, 10); })
                .catch(function () { i++; setTimeout(next, 10); });
        }
        next();
    }

    // ---------- QR de AFIP (fuente de verdad cuando trae datos) ----------
    // Escanea una región (crop opcional [x%,y%,w%,h%]) a una escala dada.
    // Umbral de Otsu in-place (blanco/negro) — jsQR decodifica mucho mejor con la
    // imagen binarizada, sobre todo en fotos con poco contraste o papel grisáceo.
    function otsuBinarize(img) {
        var data = img.data, n = data.length, hist = new Array(256), i, g;
        for (i = 0; i < 256; i++) hist[i] = 0;
        for (i = 0; i < n; i += 4) { g = (data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114) | 0; hist[g]++; }
        var total = n / 4, sum = 0; for (i = 0; i < 256; i++) sum += i * hist[i];
        var sumB = 0, wB = 0, wF, maxVar = 0, thr = 127, mB, mF, bt;
        for (i = 0; i < 256; i++) {
            wB += hist[i]; if (!wB) continue; wF = total - wB; if (!wF) break;
            sumB += i * hist[i]; mB = sumB / wB; mF = (sum - sumB) / wF;
            bt = wB * wF * (mB - mF) * (mB - mF); if (bt > maxVar) { maxVar = bt; thr = i; }
        }
        for (i = 0; i < n; i += 4) {
            g = data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;
            var v = g > thr ? 255 : 0; data[i] = data[i + 1] = data[i + 2] = v;
        }
    }
    function scanQr(canvas, scale, crop, binarize) {
        if (!window.jsQR) return null;
        var sx = 0, sy = 0, sw = canvas.width, sh = canvas.height;
        if (crop) { sx = crop[0] * sw; sy = crop[1] * sh; sw = crop[2] * canvas.width; sh = crop[3] * canvas.height; }
        var w = Math.round(sw * scale), h = Math.round(sh * scale);
        if (w < 120 || w > 4200 || h < 120) return null;
        var c = document.createElement('canvas'); c.width = w; c.height = h;
        c.getContext('2d').drawImage(canvas, sx, sy, sw, sh, 0, 0, w, h);
        var d = c.getContext('2d').getImageData(0, 0, w, h);
        if (binarize) otsuBinarize(d);
        var code = window.jsQR(d.data, d.width, d.height, { inversionAttempts: 'attemptBoth' });
        return (code && code.data) ? parseAfipQrData(code.data) : null;
    }
    function tryAfipQr(canvas) {
        if (!window.jsQR) return null;
        // Trabajar sobre una copia ACOTADA (el QR no necesita alta resolución). Antes se
        // binarizaba a 2-3× la imagen completa en el hilo principal → congelaba la página.
        var base = canvas, big = Math.max(canvas.width, canvas.height);
        if (big > 1400) {
            var s = 1400 / big;
            base = document.createElement('canvas');
            base.width = Math.round(canvas.width * s); base.height = Math.round(canvas.height * s);
            base.getContext('2d').drawImage(canvas, 0, 0, base.width, base.height);
        }
        var i, f;
        // 1) completa (normal y un poco ampliada)
        var scales = [1, 1.5];
        for (i = 0; i < scales.length; i++) { f = scanQr(base, scales[i], null); if (f) return f; }
        // 2) mitad inferior / superior (donde suele estar el QR)
        var crops = [[0, 0.5, 1, 0.5], [0, 0, 1, 0.5]];
        for (i = 0; i < crops.length; i++) { f = scanQr(base, 1.6, crops[i]); if (f) return f; }
        // 3) una pasada binarizada liviana (poco contraste) — sobre la copia acotada
        f = scanQr(base, 1.4, null, true); if (f) return f;
        f = scanQr(base, 1.6, [0, 0.5, 1, 0.5], true); if (f) return f;
        // 4) girada (QR de costado / cabeza abajo)
        for (var deg = 90; deg < 360; deg += 90) {
            var rc = rotate(base, deg);
            f = scanQr(rc, 1.3, null) || scanQr(rc, 1.5, [0, 0.5, 1, 0.5], true);
            if (f) return f;
        }
        return null;
    }
    // Sólo el QR de factura electrónica (?p=base64 JSON) trae datos. El QR de tique
    // de controlador fiscal (qr.afip.gob.ar/?qr=CÓDIGO) es sólo un código → sin datos.
    function parseAfipQrData(data) {
        var m = data.match(/[?&]p=([^&\s]+)/);
        if (!m) return null; // otros formatos (?qr=…) no traen los datos embebidos
        try { return JSON.parse(atob(decodeURIComponent(m[1]))); }
        catch (e) { try { return JSON.parse(atob(m[1])); } catch (e2) { return null; } }
    }
    var AFIP_TIPO = {
        1: ['Fact A', 'A'], 6: ['Fact B', 'B'], 11: ['Fact C', 'C'], 51: ['Fact A', 'A'],
        81: ['Fact A', 'A'], 82: ['Fact B', 'B'], 83: ['Fact C', 'C'],   // tique factura A/B/C
        2: ['ND A', 'A'], 3: ['NC A', 'A'], 7: ['ND B', 'B'], 8: ['NC B', 'B'], 12: ['ND C', 'C'], 13: ['NC C', 'C']
    };
    function fillFromQr(qr) {
        var n = 0;
        if (qr.fecha) { n += setVal('fecha_emision', String(qr.fecha).slice(0, 10)) ? 1 : 0; }
        if (qr.cuit) { n += setVal('cuit', String(qr.cuit)) ? 1 : 0; }
        if (qr.ptoVta) { n += setVal('punto_venta', ('0000' + qr.ptoVta).slice(-4)) ? 1 : 0; }
        if (qr.nroCmp) { n += setVal('numero', ('00000000' + qr.nroCmp).slice(-8)) ? 1 : 0; }
        if (qr.importe) { n += setVal('importe_total', fmtAR(qr.importe)) ? 1 : 0; }
        var t = AFIP_TIPO[qr.tipoCmp];
        if (t) { setVal('tipo_comprobante', t[0]); setVal('letra', t[1]); n++; }
        if (qr.moneda === 'DOL') { setVal('moneda', 'USD'); }
        return n;
    }

    // ---------- Código de barras AFIP/ARCA (Interleaved 2of5, RG 1702) ----------
    // Codifica CUIT(11)+CodTipo(2)+PtoVta(4)+CAE(14)+VtoCAE(8)+DV(1) = ~40 dígitos.
    // Fuente extra 100% offline: rescata CUIT/tipo/PtoVta/CAE aunque el OCR no los lea.
    function parseAfipBarcode(code) {
        var d = String(code || '').replace(/\D/g, '');
        if (d.length < 39 || d.length > 41) return null;
        return { cuit: d.slice(0, 11), tipoCod: parseInt(d.slice(11, 13), 10), ptoVta: d.slice(13, 17), cae: d.slice(17, 31), vto: d.slice(31, 39) };
    }
    function decodeBarcodeRegion(reader, canvas, crop) {
        try {
            var sx = 0, sy = 0, sw = canvas.width, sh = canvas.height;
            if (crop) { sx = crop[0] * sw; sy = crop[1] * sh; sw = crop[2] * canvas.width; sh = crop[3] * canvas.height; }
            var scale = Math.min(2, 1600 / Math.max(sw, sh)); if (scale < 1) scale = 1;
            var w = Math.round(sw * scale), h = Math.round(sh * scale);
            var c = document.createElement('canvas'); c.width = w; c.height = h;
            c.getContext('2d').drawImage(canvas, sx, sy, sw, sh, 0, 0, w, h);
            var img = c.getContext('2d').getImageData(0, 0, w, h).data;
            var lum = new Uint8ClampedArray(w * h);
            for (var i = 0, j = 0; j < lum.length; i += 4, j++) lum[j] = (img[i] * 299 + img[i + 1] * 587 + img[i + 2] * 114) / 1000 | 0;
            var source = new ZXing.RGBLuminanceSource(lum, w, h);
            var bitmap = new ZXing.BinaryBitmap(new ZXing.HybridBinarizer(source));
            var res = reader.decode(bitmap);
            if (reader.reset) reader.reset();
            return res ? parseAfipBarcode(res.getText()) : null;
        } catch (e) { return null; }
    }
    function tryBarcode(canvas) {
        if (!window.ZXing || !ZXing.MultiFormatReader) return null;
        try {
            var hints = new Map();
            hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.ITF, ZXing.BarcodeFormat.CODE_128]);
            hints.set(ZXing.DecodeHintType.TRY_HARDER, true);
            var reader = new ZXing.MultiFormatReader(); reader.setHints(hints);
            // el código suele estar en la franja inferior; probamos ahí y en toda la imagen
            var regions = [[0, 0.55, 1, 0.45], [0, 0.4, 1, 0.6], null], ri, f;
            for (ri = 0; ri < regions.length; ri++) { f = decodeBarcodeRegion(reader, canvas, regions[ri]); if (f && f.cuit) return f; }
        } catch (e) {}
        return null;
    }

    // ---------- Lector unificado QR + barra: lo mejor disponible ----------
    // 1º BarcodeDetector NATIVO (Chrome/Edge: acelerado por hardware, muy robusto, QR + barra
    // en una sola pasada). Si no está o no encuentra, cae a jsQR (QR) + ZXing (barra).
    function scanCodes(canvas, cb) {
        function done(qr, bc) { cb({ qr: qr || tryAfipQr(canvas), bc: bc || tryBarcode(canvas) }); }
        if (typeof window.BarcodeDetector === 'function') {
            try {
                var det = new window.BarcodeDetector({ formats: ['qr_code', 'itf', 'code_128', 'code_39'] });
                det.detect(canvas).then(function (codes) {
                    var qr = null, bc = null, i, c;
                    for (i = 0; i < (codes || []).length; i++) {
                        c = codes[i];
                        if (c.format === 'qr_code') { var p = parseAfipQrData(c.rawValue); if (p) qr = p; }
                        else { var b = parseAfipBarcode(c.rawValue); if (b) bc = b; }
                    }
                    done(qr, bc);
                }).catch(function () { done(null, null); });
                return;
            } catch (e) {}
        }
        done(null, null);
    }

    // ---------- preprocesado (upscale + gris + Otsu) ----------
    // maxSide: si se pasa, BAJA la resolución a ese lado (pasadas rápidas de orientación).
    function preprocess(src, maxSide) {
        var big = Math.max(src.width, src.height), sc = 1;
        if (maxSide && big > maxSide) sc = maxSide / big;               // rápido (detección)
        else if (big < 1800) sc = Math.min(2.2, 1800 / big);           // upscale texto chico
        var c = document.createElement('canvas'); c.width = Math.round(src.width * sc); c.height = Math.round(src.height * sc);
        var ctx = c.getContext('2d'); ctx.imageSmoothingEnabled = true; ctx.drawImage(src, 0, 0, c.width, c.height);
        var im = ctx.getImageData(0, 0, c.width, c.height), d = im.data, hist = new Array(256).fill(0);
        for (var i = 0; i < d.length; i += 4) { var g = (d[i] * 0.299 + d[i + 1] * 0.587 + d[i + 2] * 0.114) | 0; d[i] = d[i + 1] = d[i + 2] = g; hist[g]++; }
        var total = c.width * c.height, sum = 0, t2;
        for (t2 = 0; t2 < 256; t2++) sum += t2 * hist[t2];
        var sumB = 0, wB = 0, mx = 0, thr = 127;
        for (t2 = 0; t2 < 256; t2++) { wB += hist[t2]; if (!wB) continue; var wF = total - wB; if (!wF) break; sumB += t2 * hist[t2]; var mB = sumB / wB, mF = (sum - sumB) / wF, bet = wB * wF * (mB - mF) * (mB - mF); if (bet > mx) { mx = bet; thr = t2; } }
        for (i = 0; i < d.length; i += 4) { var v = d[i] < thr ? 0 : 255; d[i] = d[i + 1] = d[i + 2] = v; }
        ctx.putImageData(im, 0, 0);
        return c;
    }

    // ---------- parser de texto OCR ----------
    function parseNum(s) { s = String(s).replace(/[^\d.,-]/g, ''); if (s === '') return NaN; var hc = s.indexOf(',') !== -1, hd = s.indexOf('.') !== -1; if (hc && hd) { if (s.lastIndexOf(',') > s.lastIndexOf('.')) s = s.replace(/\./g, '').replace(',', '.'); else s = s.replace(/,/g, ''); } else if (hc) s = s.replace(',', '.'); return parseFloat(s); }
    function cuitDV(c) { c = String(c).replace(/\D/g, ''); if (c.length !== 11) return false; var p = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2], s = 0; for (var i = 0; i < 10; i++) s += parseInt(c[i], 10) * p[i]; var dv = 11 - (s % 11); if (dv === 11) dv = 0; if (dv === 10) return false; return dv === parseInt(c[10], 10); }
    // Formato de importe legible es-AR: 6.500,00 (miles con punto, decimales con coma).
    function fmtAR(n) { n = Math.round((+n) * 100) / 100; return isNaN(n) ? '' : n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    // Monto: soporta miles CON separador (1.234,56 / 1,234.56) Y SIN separador
    // (7800.00 / 22700,00 / 202.62). Antes fallaba con miles sin separador y
    // agarraba sólo los últimos 3 dígitos (7800.00 → 800.00).
    var MONEY = '(?:\\d{1,3}(?:[.,]\\d{3})+[.,]\\d{2}|\\d+[.,]\\d{2})';
    function amounts(t) { return (t.match(new RegExp(MONEY, 'g')) || []).map(parseNum).filter(function (n) { return !isNaN(n); }); }
    function labeled(U, label) {
        // Alternancia agrupada en (?:…) — antes 'NETO|GRAVADO|SUBTOTAL' matcheaba
        // "NETO" sin capturar número y devolvía NaN. [^\d\n] no cruza de renglón.
        var m = U.match(new RegExp('(?:' + label + ')[^\\d\\n]{0,15}(' + MONEY + ')'));
        var v = m ? parseNum(m[1]) : null;
        return (v == null || isNaN(v)) ? null : v;
    }

    function parseAndFill(t) {
        var count = 0, U = t.toUpperCase().replace(/ /g, ' '), foundCuit = null;

        var filled = [];

        // --- Fecha (preferir la etiquetada; año plausible) ---
        var dm = t.match(/(?:FECHA|F\.?\s*EMISI[OÓ]N)[^\d]{0,6}(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/i)
            || t.match(/(3[01]|[12]\d|0?[1-9])[\/\-.](1[0-2]|0?[1-9])[\/\-.](20\d{2}|\d{2})\b/);
        if (dm) {
            var yy = dm[3].length === 2 ? '20' + dm[3] : dm[3];
            if (+yy >= 2015 && +yy <= 2035 && setVal('fecha_emision', yy + '-' + ('0' + dm[2]).slice(-2) + '-' + ('0' + dm[1]).slice(-2))) filled.push('Fecha');
        }

        // --- CUIT: preferir el EMISOR (vendedor, impreso primero/arriba). El CUIT del
        // receptor/comprador aparece después de "A CONSUMIDOR FINAL", "Cliente",
        // "Sr(es)", "DNI" o el nombre de la propia empresa (INTELLIHELP). Si el único
        // CUIT válido está en esa zona → NO lo tomamos (es el comprador): dejamos que el
        // lookup por razón social traiga el CUIT real del emisor.  (idea del cliente:
        // "si tenés la razón social bien podés sacar el CUIT correcto")
        var cuitRe = /\b\d{2}[-\s.]?\d{8}[-\s.]?\d\b/g, cm, cands = [];
        while ((cm = cuitRe.exec(t))) {
            var craw = cm[0].replace(/\D/g, '');
            if (craw.length === 11) cands.push({ cuit: craw, idx: cm.index, valid: cuitDV(craw) });
        }
        var buyerM = U.match(/A\s*CONSUMIDOR|CONSUMIDOR\s*FINAL|\bCLIENTE\b|SE[NÑ]OR(?:ES)?\b|\bSR(?:ES)?\.?\s|D\.?N\.?I\.?[\s:.]|APELLIDO\s*Y\s*NOMBRE|INTELLI\s?HELP/);
        var buyerIdx = (buyerM && buyerM.index > 15) ? buyerM.index : -1;
        var valids = cands.filter(function (x) { return x.valid; });
        if (valids.length) {
            var pick = valids[0];
            var soloComprador = (valids.length === 1 && buyerIdx >= 0 && pick.idx > buyerIdx);
            if (!soloComprador) { foundCuit = pick.cuit; if (setVal('cuit', pick.cuit)) filled.push('CUIT'); }
        }
        // Hint CRUDO del CUIT del emisor (aunque falle el dígito verificador o tenga 10-12
        // dígitos por error de OCR) → habilita la recuperación difusa contra el padrón.
        var cuitOcr = '';
        var zoneTop = (buyerIdx >= 0) ? t.slice(0, buyerIdx) : t;
        var mrun = zoneTop.match(/C\.?\s*U\.?\s*I\.?\s*T\.?[^\d]{0,10}(\d[\d\s.\-]{8,14}\d)/i);
        if (mrun) { var dd = mrun[1].replace(/\D/g, ''); if (dd.length >= 10 && dd.length <= 12) cuitOcr = dd; }
        if (!cuitOcr) { // primer candidato de 11 en la zona del emisor, aunque sea inválido
            for (var ci = 0; ci < cands.length; ci++) { if (buyerIdx < 0 || cands[ci].idx < buyerIdx) { cuitOcr = cands[ci].cuit; break; } }
        }

        // --- Tipo / letra (FACTURA A/B/C, TIQUE FACTURA, Cód.001/006/011/081, Código 6) ---
        var letra = null;
        var mt = U.match(/(?:T[I1][QD]UE\s*)?FACT\w{1,3}\s*["'\s]{0,2}([ABC])/); // tolera OCR: "FACTIRA A"
        if (mt) letra = mt[1];
        else if (/C[OÓ]D(?:IGO)?\.?\s*0*(?:1|51|81)\b/.test(U)) letra = 'A';
        else if (/C[OÓ]D(?:IGO)?\.?\s*0*6\b/.test(U)) letra = 'B';
        else if (/C[OÓ]D(?:IGO)?\.?\s*0*11\b/.test(U)) letra = 'C';
        var esTique = /T[I1][QD]UE|\bTICKET\b|\bT\.?K\.?\b|C[OÓ]D(?:IGO)?\.?\s*0?8[0-3]\b/.test(U);
        if (!letra && esTique) letra = 'C';
        if (letra) {
            var tipo = /FACTURA/.test(U) ? 'Fact ' + letra : (esTique ? 'TK' : 'Fact ' + letra);
            setVal('tipo_comprobante', tipo); setVal('letra', letra); filled.push('Tipo');
        }

        // --- Punto de venta + número (00688-00001173 / N° 05148-00035742 / P.V.: 00010 + Nro: 00001432) ---
        var pv = null, nu = null;
        var mComb = t.match(/(?:\D|^)(\d{4,5})\s*[-–]\s*(\d{6,8})/); // "N05148-00035742" pegado
        var mPV = U.match(/P[.\s]*[VU][^\d]{0,6}(\d{3,5})/); // "P.V.", "PV", "P.U.NO", "P.V. N°0005"
        var mNro = U.match(/\bNRO?\.?\s*[:.]?\s*(\d{5,8})/);
        var mNT = U.match(/(?:N[O0]\s*)?\bT[.\s]*(\d{6,8})\b/); // número tras "T"/"NO T."/"T0001605" (tiques)
        if (mComb) { pv = mComb[1]; nu = mComb[2]; }
        if (!pv && mPV) pv = mPV[1];
        if (!nu && mNro) nu = mNro[1];
        if (!nu && mNT) nu = mNT[1];
        if (pv) { setVal('punto_venta', ('0000' + pv).slice(-4)); filled.push('Punto de venta'); }
        if (nu) { setVal('numero', ('00000000' + nu).slice(-8)); filled.push('Número'); }

        // --- IVA: sumar "IVA xx,xx% <monto>" y "ALICUOTA xx,xx% <monto>" ---
        var ivaSum = 0, ivaHits = 0, tasa = null, mm;
        var reIva = new RegExp('(?:IVA|AL[IÍ]CUOTA)[^\\d\\n%]{0,10}(\\d{1,2}[.,]\\d{2})\\s*%?[^\\d\\n]{0,12}(' + MONEY + ')', 'g');
        while ((mm = reIva.exec(U))) { var iv = parseNum(mm[2]); if (!isNaN(iv)) { ivaSum += iv; ivaHits++; tasa = mm[1]; } }
        var ivaVal = null;
        if (ivaHits > 0) { ivaVal = ivaSum; if (ivaHits > 1) tasa = null; }
        else { ivaVal = labeled(U, 'I[VU]A\\s*CONTENIDO'); if (ivaVal == null) ivaVal = labeled(U, 'I\\.?[VU]\\.?A\\.?'); } // tolera OCR: "Iua contenido"

        // --- Neto / Percepción ---
        var neto = labeled(U, '(?:IMP\\.?\\s*)?NETO\\s*(?:GRAVADO|SIN\\s*IVA)?');
        if (neto == null) neto = labeled(U, 'GRAVADO');
        if (neto == null) neto = labeled(U, 'SUBTOTAL');
        // Percepción: "Percepción", "Perc. IIBB", "Perc Ing. Brutos" (NO el nº de IIBB del encabezado)
        var percep = labeled(U, 'PERCEP\\w*');
        if (percep == null) percep = labeled(U, 'PERC\\.?\\s*(?:IIBB|ING|I\\.?B\\.?)');

        // --- Total (excluye SUBTOTAL por \b y "TOTAL OTROS TRIBUTOS") ---
        // El monto puede estar en el renglón siguiente, pero el hueco sólo admite
        // conectores ($ : . - espacio/salto), no letras — así no agarra la línea de IVA.
        // CAP descarta basura del OCR (concatenación de códigos → números gigantes).
        var CAP = 1e8;
        var totals = [], rt = new RegExp('\\bTOTAL\\b(?!\\s+OTROS)[\\s$:.\\-#*]{0,6}(' + MONEY + ')', 'g'), rm;
        while ((rm = rt.exec(U))) { var tv = parseNum(rm[1]); if (!isNaN(tv) && tv < CAP) totals.push(tv); }
        var total = totals.length ? Math.max.apply(null, totals) : null;
        if (total == null) { var a = amounts(t).filter(function (x) { return x < CAP; }); total = a.length ? Math.max.apply(null, a) : null; }

        // --- Reconciliación: dejar Neto + IVA + Percepción = Total (todo coherente) ---
        // Objetivo del cliente: TODOS los importes autocompletados y sin "el total no
        // coincide". Con lo que el OCR sí leyó, derivamos lo que falte.
        var r2 = function (n) { return Math.round(n * 100) / 100; };
        var p = (percep != null && !isNaN(percep)) ? percep : 0;
        var tn = tasa != null ? parseNum(tasa) : null;
        if ([0, 10.5, 21, 27].indexOf(tn) === -1) tn = null;
        if (total != null && total < CAP) {
            if (neto != null && ivaVal == null) ivaVal = r2(total - neto - p);
            else if (neto == null && ivaVal != null) neto = r2(total - ivaVal - p);
            else if (neto == null && ivaVal == null && tn) { neto = r2((total - p) / (1 + tn / 100)); ivaVal = r2(total - p - neto); }
        } else if (total == null && neto != null && ivaVal != null) {
            total = r2(neto + ivaVal + p);
        }
        // Derivar la tasa a partir de neto e IVA (21 / 10,5 / 27)
        if (tn == null && neto && ivaVal != null && neto > 0) {
            var ratio = ivaVal / neto * 100;
            [21, 10.5, 27, 0].forEach(function (x) { if (tn == null && Math.abs(ratio - x) <= 1.2) tn = x; });
        }
        if (neto != null && neto < 0) neto = null;   // OCR malo → no inventar negativos
        if (ivaVal != null && ivaVal < 0) ivaVal = null;
        // COHERENCIA: si se leyeron Neto Y IVA pero Neto+IVA+Percep NO da el Total (p. ej. el
        // OCR mezcló renglones y quedó Neto=IVA), los importes son dudosos → NO se completan
        // Neto/IVA/Tasa (profesional: mejor vacío para revisar que un número incoherente).
        if (total != null && neto != null && ivaVal != null) {
            var tol = Math.max(1, total * 0.02);
            if (Math.abs((neto + ivaVal + p) - total) > tol) { neto = null; ivaVal = null; tn = null; }
        }

        // --- Setear importes (orden percep→tasa→neto→iva→total para que el autocálculo cuadre) ---
        if (p > 0) { setVal('importe_percepcion', fmtAR(p)); filled.push('Percepción'); }
        if (tn != null && setVal('tasa_iva', String(tn))) filled.push('Tasa IVA');
        if (neto != null) { setVal('importe_neto', fmtAR(neto)); filled.push('Neto'); }
        if (ivaVal != null) { setVal('importe_iva', fmtAR(ivaVal)); filled.push('IVA'); }
        if (total != null) { setVal('importe_total', fmtAR(total)); filled.push('Total'); }

        // --- Razón social: 1) etiqueta explícita  2) primera línea "nombre" (aún con CUIT) ---
        // El padrón/DB (autocategorize) la pisa después si el CUIT está registrado.
        var razon = null;
        var mrs = t.match(/RAZ[OÓ]N\s*SOCIAL[:\s]+([^\n]{3,45})/i);
        if (mrs) razon = mrs[1].replace(/\s{2,}/g, ' ').trim();
        if (!razon) {
            var lines = t.split(/\n/), li, ln;
            for (li = 0; li < lines.length && li < 6; li++) {
                ln = lines[li].replace(/\s{2,}/g, ' ').trim();
                var UL = ln.toUpperCase();
                // Descartar encabezados fiscales, direcciones, líneas de ítems/importes, etc.
                if (!ln || /CUIT|C\.U\.I\.T|FACTURA|TIQUE|RESPONSABLE|CONSUMIDOR|DOMICILIO|DIRECC|COMPROBANTE|ORIGINAL|ING\.?\s*BRUTOS|INGRESOS\s*BRUTOS|INICIO\s*(?:DE)?\s*ACTIV|PIS[OT]\b|\bP\.?B\.?\b|\bAV(?:DA|E)?\.?\b|\bCALLE\b|LOCAL\s*\d|\bC\.?P\.?\s*\d|BUENOS\s*AIRES|CAPITAL\s*FEDERAL/.test(UL)) continue;
                // Ruido típico de OCR / renglones de ítems: importes, cantidades, precios…
                if (/\b(IMPORTE|SUBTOTAL|NETO|TOTAL|\bIVA\b|PRECIO|CANT|UNITARIO|DESCUENTO|ART[IÍ]CULO|EFECTIVO|VUELTO|CAMBIO|TARJETA|D[EÉ]BITO|CR[EÉ]DITO|GRACIAS)\b/.test(UL)) continue;
                // Un nombre real EMPIEZA con letra y NO lleva dígitos ni ':' → filtra basura
                // como "[NBI 1 IMPORTE" o ".SHARTIN4 SAATT" (profesional: si no es claro, no se pone).
                if (!/^[A-Za-zÁÉÍÓÚÑ]/.test(ln) || /\d/.test(ln) || ln.indexOf(':') >= 0) continue;
                var letters = (ln.match(/[A-Za-zÁÉÍÓÚÑ]/g) || []).length;
                var nonSpace = ln.replace(/\s/g, '').length || 1;
                // Nombre real: ≥5 letras, mayormente letras, y alguna palabra de ≥4 letras
                if (letters >= 5 && letters / nonSpace >= 0.7 && ln.length <= 45 && /[A-Za-zÁÉÍÓÚÑ]{4,}/.test(ln)) { razon = ln; break; }
            }
        }
        if (razon && setVal('razon_social', razon)) filled.push('Razón social');

        // CAE (código de autorización) → Observaciones, si se lee claro (14 dígitos).
        var mcae = U.match(/C\.?A\.?E\.?[\s.:N°ºo]{0,4}(\d{14})/);
        if (mcae) { var obsc = q('observaciones'); if (obsc && !obsc.value.trim()) { obsc.value = 'CAE ' + mcae[1]; filled.push('CAE'); } }

        return { filled: filled, cuit: foundCuit, cuitOcr: cuitOcr, text: t };
    }

    // ---------- autoclasificación ----------
    function selectCategoriaByName(name) {
        var sel = q('concepto_id'); if (!sel || sel.value) return false;
        var target = String(name).toLowerCase();
        for (var i = 0; i < sel.options.length; i++) {
            if (sel.options[i].text.toLowerCase() === target) {
                sel.value = sel.options[i].value;
                sel.dispatchEvent(new Event('change', { bubbles: true })); // sincroniza el combobox
                return true;
            }
        }
        return false;
    }
    function applyProveedor(d) {
        // CUIT resuelto por el padrón: completa si está vacío y, en coincidencia INEQUÍVOCA
        // (confianza alta), CORRIGE el que puso el OCR (recupera errores de lectura del CUIT).
        if (d.cuit && String(d.cuit).length === 11) {
            var cf = q('cuit');
            if (cf) {
                var cur = cf.value.replace(/\D/g, '');
                if (!cur || (d.confianza === 'alta' && cur !== d.cuit)) { cf.value = d.cuit; cf.dispatchEvent(new Event('input', { bubbles: true })); }
            }
        }
        // CUIT → razón: la razón del padrón es limpia (pisa la del OCR, que suele venir mal).
        var rz = q('razon_social');
        if (d.found && d.razon_social && rz) { rz.value = d.razon_social; rz.dispatchEvent(new Event('input', { bubbles: true })); }
        if (d.found && d.fuente === 'nacional') {
            var nota = d.confianza === 'media' ? ' <span class="muted">(verificá el CUIT)</span>' : '';
            summaryNotes.push('<span style="color:var(--accent-600)">✓ Proveedor del padrón nacional:</span> ' + d.razon_social + nota);
            if (window.appLog) window.appLog('ocr.padron_nacional', 'CUIT ' + (d.cuit || '') + ' → ' + d.razon_social + ' [' + (d.confianza || '') + ']', 'accion');
        }
        var sel = q('concepto_id');
        if (d.concepto_id && sel && !sel.value) {
            sel.value = String(d.concepto_id);
            sel.dispatchEvent(new Event('change', { bubbles: true })); // sincroniza el combobox
            summaryNotes.push('<span style="color:var(--accent-600)">✓ Concepto automático:</span> ' + d.concepto);
        }
        refreshSummary();   // el resumen ahora refleja CUIT/razón/categoría recién cargados
    }
    // Consulta al padrón por CUIT y/o razón social (bidireccional, 100% local): el CUIT
    // trae la razón + categoría; la razón trae el CUIT correcto del emisor.
    function enrichProveedor(cuit, razon, cuitOcr) {
        var c = String(cuit || '').replace(/\D/g, '');
        var rz = String(razon || '').trim();
        var co = String(cuitOcr || '').replace(/\D/g, '');
        var params = [];
        if (c.length === 11) params.push('cuit=' + c);
        if (rz.length >= 3) params.push('razon=' + encodeURIComponent(rz));
        if (co.length >= 10 && co.length <= 12 && co !== c) params.push('cuit_ocr=' + co); // hint difuso
        if (!params.length) return;
        fetch(base + 'api-proveedor?' + params.join('&'), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(applyProveedor).catch(function () {});
    }
    // Cada categoría con su descripción (qué gastos cubre) y sus palabras clave.
    // Se puntúa por cantidad de coincidencias en (razón social + texto del ticket) y
    // gana la de mayor puntaje → decide de forma más inteligente, no al primer match.
    var CATS = [
        { name: 'Supermercado', desc: 'compras en super, autoservicios y mayoristas de consumo', kw: [/COTO/, /\bD[IÍ]A%?\b/, /JUMBO/, /CARREFOUR/, /LA\s?AN[OÓ]NIMA/, /\bVEA\b/, /DISCO/, /MAKRO/, /CHANGO\s?M[AÁ]S|CHANGOMAS/, /MAXICONSUMO/, /SUPERMERCAD/, /AUTOSERVICIO/, /MINIMERCADO/, /ALMAC[EÉ]N/, /\bYAGUAR\b/] },
        { name: 'Combustible', desc: 'nafta, gasoil y GNC en estaciones de servicio', kw: [/\bYPF\b/, /SHELL/, /AXION/, /\bPUMA\b/, /PAN\s?AMERICAN/, /REFINOR/, /\bGNC\b/, /ESTACI[OÓ]N\s?DE\s?SERVICIO/, /NAFTA|GAS[\s-]?OIL|GASOIL|COMBUSTIBLE/, /SURTIDOR/, /LUBRICANTES?/] },
        { name: 'Estacionamiento', desc: 'cocheras, garages, playas de estacionamiento y parking', kw: [/ESTACIONAMIENTO/, /PARKING/, /GARAGE|GARAJE/, /COCHERA/, /PLAYA\s?DE/, /BAULERA/] },
        { name: 'Peaje', desc: 'peajes de autopistas y rutas', kw: [/PEAJE/, /AUTOPISTA/, /AUSOL/, /AUBASA/, /TELEPASE/, /CAMINOS\s?DEL/, /CORREDOR\s?VIAL/] },
        { name: 'Comida', desc: 'restaurantes, cafés, bares, panaderías, kioscos y gastronomía en general', kw: [/RESTAURANT|\bRESTO\b/, /CAF[EÉ]/, /\bBAR\b/, /PARRILLA/, /PIZZ/, /ROTISER/, /\bCOMIDA\b/, /PANADER[IÍ]A/, /CONFITER[IÍ]A/, /HELADER[IÍ]A/, /KIOSCO|KIOSKO/, /CERVECER[IÍ]A/, /STARBUCKS|MCDONALD|BURGER|SUBWAY|MOSTAZA/, /GASTRONOM/] },
        { name: 'Librería', desc: 'librerías, papelerías y fotocopias', kw: [/LIBRER[IÍ]A/, /PAPELER[IÍ]A/, /FOTOCOPI/, /\bPAPEL\b/] },
        { name: 'Fletes', desc: 'fletes, mudanzas, logística y mensajería', kw: [/\bFLETE/, /MUDANZA/, /LOG[IÍ]STICA/, /MENSAJER[IÍ]A/, /ENCOMIENDA/, /\bOCA\b|ANDREANI|CORREO/] },
        { name: 'Taller', desc: 'talleres mecánicos, gomerías, repuestos y lubricentros', kw: [/TALLER/, /MEC[AÁ]NIC/, /GOMER[IÍ]A/, /LUBRICENTRO/, /REPUEST/, /AUTOPARTES/, /NEUM[AÁ]TIC/, /\bCUBIERTAS?\b/] },
        { name: 'Servicios', desc: 'luz, gas, agua, teléfono, internet y servicios profesionales', kw: [/EDENOR|EDESUR/, /METROGAS|NATURGY|CAMUZZI/, /\bAYSA\b/, /TELECOM|MOVISTAR|\bCLARO\b|PERSONAL|FIBERTEL|\bFLOW\b/, /INTERNET/, /HONORARIO|SERVICIO\s?PROFESIONAL/, /ABONO\s?MENSUAL/] },
        { name: 'Viáticos', desc: 'transporte, taxis, remises, apps de viaje, hoteles y hospedaje', kw: [/\bSUBE\b/, /VI[AÁ]TICO/, /\bHOTEL\b/, /HOSPEDAJE/, /REMIS/, /\bTAXI\b/, /CABIFY/, /\bUBER\b/, /\bDIDI\b/, /PASAJE|COLECTIVO|\bMICRO\b/] },
        { name: 'Insumos', desc: 'ferreterías, sanitarios, corralones, pinturerías, materiales e insumos varios', kw: [/FERRETER[IÍ]A/, /INSUMO/, /CORRAL[OÓ]N/, /SANITARIO/, /PINTURER[IÍ]A|PINTURA/, /MATERIALES/, /ELECTRICIDAD|ELECTR[OÓ]NIC/, /BULONER[IÍ]A/, /\bMAYORISTA\b/] },
    ];
    function keywordCategory(text) {
        var U = String(text || '').toUpperCase(), best = null, bestScore = 0;
        for (var i = 0; i < CATS.length; i++) {
            var s = 0;
            for (var j = 0; j < CATS[i].kw.length; j++) if (CATS[i].kw[j].test(U)) s++;
            if (s > bestScore) { bestScore = s; best = CATS[i].name; }
        }
        if (best && selectCategoriaByName(best)) {
            summaryNotes.push('<span style="color:var(--accent-600)">✓ Concepto sugerido:</span> ' + best);
        }
    }

    // ---------- reset de estado (evita que quede cacheado al volver/recargar) ----------
    function resetScan() {
        workCanvas = null;
        try { fileInput.value = ''; } catch (e) {}
        fileInput.dispatchEvent(new Event('change', { bubbles: true })); // limpia el nombre del filefield
        if (preview) { preview.style.display = 'none'; preview.removeAttribute('src'); }
        if (ocrText) ocrText.value = '';
        setStatus('');
    }
    // Al restaurar desde el bfcache (botón Atrás) o recargar, empezar limpio.
    window.addEventListener('pageshow', function (e) { if (e.persisted) resetScan(); });
    resetScan();
})();
