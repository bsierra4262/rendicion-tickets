/**
 * paddleocr.js — Motor OCR PaddleOCR (PP-OCRv6 medium) en el navegador (onnxruntime-web).
 *
 * Nota: se descartó correrlo en un Web Worker. La librería `ppu-paddle-ocr/web` + el
 * onnxruntime-web que sirve esm.sh NO cargan el backend WASM dentro de un worker
 * ("[unenv] module.require is not implemented") — quedó registrado en el CRON tras
 * varias pruebas. Corre en el hilo principal con **WebGPU** (cuando está, la inferencia
 * va a la GPU y casi no bloquea). Para no congelar, escanear.js usa detección en baja
 * resolución + cesión de hilo entre pasadas.
 *
 * MODEL_TIER: 'medium' (mejor) | 'small' | 'tiny'. Bajá a 'small' si va lento.
 * Expone window.PaddleOCR = { available, tier, webgpu, mode, ready:Promise<bool>, recognize(canvas) }.
 */
(function () {
    'use strict';
    // 'medium' resultó carísimo (74-103s en el navegador del cliente: no usaba la GPU real,
    // corría en WASM CPU). 'small' tiene el diccionario completo, casi la misma precisión y
    // es ~4-5× más liviano. Volver a 'medium' solo si WebGPU acelera de verdad.
    var MODEL_TIER = 'small';
    // 'onnxruntime-web' es un specifier BARE resuelto por el <script type="importmap"> de la
    // página → la MISMA instancia que usa ppu-paddle-ocr (cargado con ?external=onnxruntime-web).
    // Así, al setear wasmPaths + numThreads acá, la librería SÍ usa multi-hilo.
    var ORT = 'onnxruntime-web';
    var ORT_WASM = 'https://cdn.jsdelivr.net/npm/onnxruntime-web@1.23.2/dist/';
    var svc = null, settled = false, resolveReady, ortRef = null;
    var api = { available: false, tier: MODEL_TIER, webgpu: false, backend: null, mode: null, ready: null, recognize: null };
    api.ready = new Promise(function (res) { resolveReady = res; });
    window.PaddleOCR = api;

    function alog(a, d, t) { if (window.appLog) window.appLog(a, d, t); }
    function finish(ok, err) {
        if (settled) return;
        settled = true;
        api.available = !!ok;
        if (!ok && window.console) console.warn('[PaddleOCR] no disponible:', err && (err.message || err));
        resolveReady(!!ok);
    }
    setTimeout(function () { finish(false, 'timeout de inicialización'); }, 180000);

    // ---- Clasificador de orientación (PP-LCNet doc_ori, 4 clases 0/90/180/270) ----
    // Una inferencia (~ms) endereza la foto ANTES del OCR → reemplaza el "probar 4 rotaciones".
    // Validado 100% en los tickets reales. Si el modelo no carga, escanear.js usa el bruteforce.
    var oriSession = null;
    var OMEAN = [0.485, 0.456, 0.406], OSTD = [0.229, 0.224, 0.225];
    function loadOrientation() {
        if (!ortRef || !ortRef.InferenceSession) { alog('ocr.orient.error', 'onnxruntime no disponible', 'error'); return Promise.resolve(); }
        var url = (window.APP_BASE || '') + 'assets/models/doc_ori.onnx';
        return ortRef.InferenceSession.create(url).then(function (s) {
            oriSession = s; api.orientation = detectOrientation;
            alog('ocr.orient.ok', 'modelo doc_ori cargado', 'sistema');
        }).catch(function (e) { alog('ocr.orient.error', 'doc_ori no cargó: ' + (e && (e.message || e)), 'error'); });
    }
    // Devuelve los GRADOS a rotar para enderezar la imagen (0/90/180/270).
    function detectOrientation(canvas) {
        if (!oriSession || !ortRef) return Promise.resolve(0);
        try {
            var s = 256 / Math.min(canvas.width, canvas.height);
            var rw = Math.max(224, Math.round(canvas.width * s)), rh = Math.max(224, Math.round(canvas.height * s));
            var r = document.createElement('canvas'); r.width = rw; r.height = rh;
            r.getContext('2d').drawImage(canvas, 0, 0, rw, rh);
            var ox = Math.floor((rw - 224) / 2), oy = Math.floor((rh - 224) / 2);
            var c = document.createElement('canvas'); c.width = 224; c.height = 224;
            c.getContext('2d').drawImage(r, ox, oy, 224, 224, 0, 0, 224, 224);
            var d = c.getContext('2d').getImageData(0, 0, 224, 224).data;
            var N = 224 * 224, t = new Float32Array(3 * N), i;
            for (i = 0; i < N; i++) {
                t[i]        = ((d[i * 4]     / 255) - OMEAN[0]) / OSTD[0];
                t[N + i]    = ((d[i * 4 + 1] / 255) - OMEAN[1]) / OSTD[1];
                t[2 * N + i] = ((d[i * 4 + 2] / 255) - OMEAN[2]) / OSTD[2];
            }
            var tensor = new ortRef.Tensor('float32', t, [1, 3, 224, 224]);
            return oriSession.run({ x: tensor }).then(function (o) {
                var a = o[oriSession.outputNames[0]].data, mi = 0, k;
                for (k = 1; k < 4; k++) if (a[k] > a[mi]) mi = k;
                var pred = [0, 90, 180, 270][mi];
                return (360 - pred) % 360;   // grados a rotar para enderezar
            }).catch(function (e) { alog('ocr.orient.run_error', (e && (e.message || e)), 'error'); return 0; });
        } catch (e) { alog('ocr.orient.run_error', 'pre: ' + (e && (e.message || e)), 'error'); return Promise.resolve(0); }
    }

    // ---- Dewarping / rectificación de papel (UVDoc, ppu-doc-correction) ----
    // Carga PEREZOSA (31.6 MB) sólo cuando un ticket difícil lo necesita. INSTRUMENTADO:
    // cada paso (import/creación/run) queda en el CRON con su error → para diagnosticar en vivo.
    var unwarpSvc = null, unwarpLoading = null;
    function withTimeout(p, ms, label) {
        return new Promise(function (resolve, reject) {
            var to = setTimeout(function () { reject(new Error('timeout ' + ms + 'ms en ' + label)); }, ms);
            Promise.resolve(p).then(function (v) { clearTimeout(to); resolve(v); }, function (e) { clearTimeout(to); reject(e); });
        });
    }
    function pngBufferToCanvas(ab) {
        return new Promise(function (resolve) {
            try {
                var url = URL.createObjectURL(new Blob([ab], { type: 'image/png' }));
                var img = new Image();
                img.onload = function () {
                    var c = document.createElement('canvas'); c.width = img.width; c.height = img.height;
                    c.getContext('2d').drawImage(img, 0, 0); URL.revokeObjectURL(url); resolve(c);
                };
                img.onerror = function () { URL.revokeObjectURL(url); resolve(null); };
                img.src = url;
            } catch (e) { resolve(null); }
        });
    }
    function loadUnwarp() {
        if (unwarpSvc) return Promise.resolve(unwarpSvc);
        if (unwarpLoading) return unwarpLoading;
        var url = (window.APP_BASE || '') + 'assets/models/UVDoc.onnx';
        alog('ocr.dewarp.cargando', 'import ppu-doc-correction/web + modelo UVDoc', 'sistema');
        unwarpLoading = withTimeout(import('https://esm.sh/ppu-doc-correction@1.0.1/web?external=onnxruntime-web'), 45000, 'import ppu-doc-correction')
            .then(function (mod) {
                alog('ocr.dewarp.import_ok', 'módulo cargado; creando TextUnwarpService', 'sistema');
                unwarpSvc = new mod.TextUnwarpService({ model: url });
                return unwarpSvc;
            })
            .catch(function (e) {
                unwarpLoading = null;
                alog('ocr.dewarp.error', 'no cargó: ' + (e && (e.message || e)), 'error');
                return null;
            });
        return unwarpLoading;
    }
    // Devuelve un canvas rectificado, o null si no está disponible/falla (todo queda en CRON).
    api.unwarp = function (canvas) {
        return loadUnwarp().then(function (svc) {
            if (!svc) return null;
            var t0 = Date.now();
            return withTimeout(svc.run(canvas), 90000, 'UVDoc.run').then(function (r) {
                alog('ocr.dewarp.run_ok', 'rectificado en ' + (Date.now() - t0) + 'ms', 'accion');
                return (r && r.correctedImage) ? pngBufferToCanvas(r.correctedImage) : null;
            }).catch(function (e) {
                alog('ocr.dewarp.run_error', (e && (e.message || e)), 'error');
                return null;
            });
        }).catch(function (e) { alog('ocr.dewarp.error', 'load: ' + (e && (e.message || e)), 'error'); return null; });
    };

    function boot() {
        // Fijar wasmPaths a un CDN real ANTES de cargar ppu: así onnxruntime-web baja el
        // backend (incl. WebGPU) por fetch en vez de fallar con require → habilita la GPU.
        return import(ORT).then(function (ort) {
            ortRef = ort;
            try {
                if (ort && ort.env && ort.env.wasm) {
                    ort.env.wasm.wasmPaths = ORT_WASM;
                    // Si la página está aislada (COOP/COEP), habilitar multi-hilo WASM (más rápido).
                    if (self.crossOriginIsolated && navigator.hardwareConcurrency > 1) {
                        ort.env.wasm.numThreads = Math.min(4, navigator.hardwareConcurrency);
                    }
                }
            } catch (e) {}
        }).catch(function () {}).then(function () {
            // ?external=onnxruntime-web → ppu NO empaqueta ort; usa el del import map (compartido).
            return import('https://esm.sh/ppu-paddle-ocr@6.2.0/web?external=onnxruntime-web');
        }).then(function (mod) {
            var models = { tiny: mod.V6_TINY_MODEL, small: mod.V6_SMALL_MODEL, medium: mod.V6_MEDIUM_MODEL };
            var gpuCheck = Promise.resolve(false);
            if (typeof mod.isWebGpuAvailable === 'function') { try { gpuCheck = Promise.resolve(mod.isWebGpuAvailable()); } catch (e) {} }
            return gpuCheck.then(function (gpu) {
                api.webgpu = !!gpu;
                svc = new mod.PaddleOcrService({ model: models[MODEL_TIER] || models.medium });
                return svc.initialize();
            });
        }).then(function () {
            api.recognize = function (canvas) { return svc.recognize(canvas).then(function (r) { return (r && r.text) || ''; }); };
            // Libera la sesión ONNX + el contexto WebGPU (se llama al guardar/salir para
            // que la navegación sea rápida y no "quede pensando").
            api.destroy = function () { try { if (svc && svc.destroy) svc.destroy(); } catch (e) {} svc = null; };
            api.mode = 'main';
            // BACKEND REAL (no hardcodeado): onnxruntime-web sólo puebla ort.env.webgpu.device/
            // adapter si una sesión WebGPU se creó de verdad. Si no, la inferencia corrió en
            // WASM (CPU). Erramos del lado seguro: si no se confirma GPU, se reporta CPU.
            var usedGpu = false, threads = 1;
            try {
                var env = ortRef && ortRef.env;
                usedGpu = !!(env && env.webgpu && (env.webgpu.device || env.webgpu.adapter));
                threads = (env && env.wasm && env.wasm.numThreads) || 1;
            } catch (e) {}
            api.webgpu = usedGpu;
            api.backend = usedGpu ? 'GPU (WebGPU)' : ('CPU (WASM' + (threads > 1 ? ' · ' + threads + ' hilos' : '') + ')');
            return loadOrientation();   // carga el clasificador de orientación (no fatal si falla)
        });
    }

    boot().then(function () {
        alog('ocr.motor', 'PaddleOCR ' + MODEL_TIER + ' · ' + (api.backend || 'desconocido') +
            ' · aislado=' + (self.crossOriginIsolated ? 'si' : 'no') + ' · cores=' + (navigator.hardwareConcurrency || '?'), 'sistema');
        finish(true);
    }).catch(function (e) {
        alog('ocr.no_disponible', 'PaddleOCR no cargó: ' + (e && e.message || e), 'error');
        finish(false, e);
    });
})();
