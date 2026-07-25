/**
 * paddle-worker.js — PaddleOCR (PP-OCRv6) dentro de un Web Worker (inferencia off-thread).
 *
 * Dos obstáculos resueltos para correr la librería /web en un worker:
 *   1) usa `document.createElement('canvas')` → se shimea a `OffscreenCanvas`.
 *   2) onnxruntime-web (como lo sirve esm.sh) intenta cargar el backend WASM con
 *      `module.require` (que no existe en el worker → "unenv module.require not implemented").
 *      Fix: se fija `ort.env.wasm.wasmPaths` a un CDN real (jsDelivr) y `numThreads=1`
 *      ANTES de importar la librería, para que baje el WASM por fetch. Se importa el MISMO
 *      onnxruntime-web que usa ppu (mismo specifier/target) para compartir la instancia.
 * Con esto la inferencia corre en el worker y la página NO se congela.
 */
(function () {
    'use strict';
    // --- (1) Shim de document ---
    if (typeof self.document === 'undefined') {
        var noop = function () {};
        self.document = {
            createElement: function (t) {
                if (String(t).toLowerCase() === 'canvas') return new OffscreenCanvas(1, 1);
                return { style: {}, setAttribute: noop, appendChild: noop, getContext: function () { return null; } };
            },
            createElementNS: function () { return self.document.createElement('div'); },
            body: { appendChild: noop, removeChild: noop, style: {} },
            head: { appendChild: noop, removeChild: noop },
            documentElement: { style: {} },
            addEventListener: noop, removeEventListener: noop
        };
    }

    var ORT = 'https://esm.sh/onnxruntime-web@1.23.2?target=es2022';
    var ORT_WASM = 'https://cdn.jsdelivr.net/npm/onnxruntime-web@1.23.2/dist/';
    var PPU = 'https://esm.sh/ppu-paddle-ocr@6.2.0/web';
    var svc = null, ready = null;

    self.onmessage = function (e) {
        var d = e.data || {};
        if (d.type === 'init') {
            // (2) Configurar ort ANTES de cargar ppu.
            ready = import(ORT).then(function (ort) {
                try {
                    if (ort && ort.env && ort.env.wasm) {
                        ort.env.wasm.wasmPaths = ORT_WASM;   // baja el WASM por fetch (no require)
                        ort.env.wasm.numThreads = 1;          // sin cross-origin isolation
                    }
                } catch (_) {}
                return import(PPU);
            }).then(function (mod) {
                var models = { tiny: mod.V6_TINY_MODEL, small: mod.V6_SMALL_MODEL, medium: mod.V6_MEDIUM_MODEL };
                var gpuP = Promise.resolve(false);
                try { if (typeof mod.isWebGpuAvailable === 'function') gpuP = Promise.resolve(mod.isWebGpuAvailable()); } catch (_) {}
                return gpuP.then(function (gpu) {
                    svc = new mod.PaddleOcrService({ model: models[d.tier] || mod.V6_MEDIUM_MODEL });
                    return svc.initialize().then(function () { return gpu; });
                });
            });
            ready.then(function (gpu) { self.postMessage({ type: 'ready', gpu: gpu }); })
                 .catch(function (err) { self.postMessage({ type: 'error', error: String(err && err.message || err) }); });
        } else if (d.type === 'ocr') {
            Promise.resolve(ready).then(function () {
                var c = new OffscreenCanvas(d.bitmap.width, d.bitmap.height);
                c.getContext('2d').drawImage(d.bitmap, 0, 0);
                if (d.bitmap.close) d.bitmap.close();
                return svc.recognize(c);
            }).then(function (r) {
                self.postMessage({ type: 'result', id: d.id, text: (r && r.text) || '' });
            }).catch(function () {
                self.postMessage({ type: 'result', id: d.id, text: '' });
            });
        }
    };
})();
