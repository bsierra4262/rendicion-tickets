/**
 * viewer.js — Visor de comprobantes propio, en la estética del sitio.
 * Modal branded con encabezado (datos del comprobante) + imagen/PDF.
 *   · ZOOM (scroll / pellizco) y PAN (arrastrar).
 *   · ROTACIÓN LIBRE: se agarra cualquiera de las 4 esquinas y se gira a
 *     cualquier ángulo (con imán a 0/90/180/270°). También botones ⟲/⟳ (90°).
 *   · Cierra con Esc, click afuera o el botón.
 *
 * Uso: <a class="comp-viewer" href="…" data-type="image|iframe"
 *          data-title="…" data-description="…">.
 */
(function () {
    'use strict';
    var overlay = null, titleEl = null, descEl = null, bodyEl = null, hintEl = null, toolsEl = null, lastFocus = null;
    var stage = null, img = null, scale = 1, tx = 0, ty = 0, rot = 0;
    var drag = false, sx = 0, sy = 0, pinchD = 0, pinchA = null;
    var rotating = false, startAngle = 0, baseRot = 0;

    function build() {
        overlay = document.createElement('div');
        overlay.className = 'cv-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML =
            '<div class="cv-card">' +
                '<div class="cv-head">' +
                    '<span style="display:flex;align-items:center;gap:.6rem;min-width:0">' +
                        '<span class="cv-ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 3h16v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M8 7h8M8 11h8M8 15h5"/></svg></span>' +
                        '<span style="min-width:0"><h3 class="cv-title"></h3><p class="cv-desc"></p></span>' +
                    '</span>' +
                    '<span style="display:flex;align-items:center;gap:.35rem">' +
                        '<span class="cv-tools" style="display:flex;gap:.35rem">' +
                            '<button type="button" class="cv-rot" data-dir="-1" title="Rotar 90° a la izquierda" aria-label="Rotar a la izquierda" style="background:rgba(255,255,255,.14);border:0;color:#fff;width:34px;height:34px;border-radius:8px;cursor:pointer;font-size:17px">⟲</button>' +
                            '<button type="button" class="cv-rot" data-dir="1" title="Rotar 90° a la derecha" aria-label="Rotar a la derecha" style="background:rgba(255,255,255,.14);border:0;color:#fff;width:34px;height:34px;border-radius:8px;cursor:pointer;font-size:17px">⟳</button>' +
                        '</span>' +
                        '<button type="button" class="cv-close" aria-label="Cerrar">&times;</button>' +
                    '</span>' +
                '</div>' +
                '<div class="cv-body"></div>' +
                '<div class="cv-hint"></div>' +
            '</div>';
        document.body.appendChild(overlay);
        titleEl = overlay.querySelector('.cv-title');
        descEl = overlay.querySelector('.cv-desc');
        bodyEl = overlay.querySelector('.cv-body');
        hintEl = overlay.querySelector('.cv-hint');
        toolsEl = overlay.querySelector('.cv-tools');
        // Ayuda según el dispositivo (no hardcodeada): táctil vs mouse/PC.
        var esTactil = !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches)
            || ('ontouchstart' in window && !window.matchMedia('(pointer: fine)').matches);
        hintEl.innerHTML = esTactil
            ? 'Zoom: pellizco · mover: arrastrar · girar: <b>con dos dedos</b> · doble toque: restablecer'
            : 'Zoom: rueda del mouse · mover: arrastrar · girar: <b>desde las esquinas</b> · doble clic: restablecer';
        overlay.addEventListener('click', function (e) {
            var rb = e.target.closest('.cv-rot');
            if (rb) { rot = snap(rot + (rb.getAttribute('data-dir') === '1' ? 90 : -90)); apply(); return; }
            if (e.target === overlay || e.target.closest('.cv-close')) close();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('open')) close();
        });
        // Rotación con las esquinas (mouse global mientras se arrastra un handle)
        window.addEventListener('mousemove', function (e) {
            if (rotating) { e.preventDefault(); rotateTo(e.clientX, e.clientY); }
            else if (drag) { tx = e.clientX - sx; ty = e.clientY - sy; apply(); }
        });
        window.addEventListener('mouseup', function () {
            if (rotating) { rotating = false; rot = snap(rot); apply(); }
            if (drag) { drag = false; apply(); }
        });
    }

    function apply() {
        if (!stage) return;
        stage.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + scale + ') rotate(' + rot + 'deg)';
        if (img) img.style.cursor = scale > 1 ? (drag ? 'grabbing' : 'grab') : 'zoom-in';
    }
    function reset() { scale = 1; tx = 0; ty = 0; rot = 0; apply(); }
    function zoomAt(factor) { scale = Math.min(6, Math.max(1, scale * factor)); if (scale === 1) { tx = 0; ty = 0; } apply(); }
    // Normaliza a [0,360) y aplica imán a los múltiplos de 90° cuando está cerca (±6°).
    function snap(deg) {
        deg = ((deg % 360) + 360) % 360;
        var n = Math.round(deg / 90) * 90;
        if (Math.abs(deg - n) <= 6) deg = n % 360;
        return deg;
    }

    function centerXY() {
        var r = stage.getBoundingClientRect();
        return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
    }
    function startRotate(px, py) {
        rotating = true;
        var c = centerXY();
        startAngle = Math.atan2(py - c.y, px - c.x) * 180 / Math.PI;
        baseRot = rot;
    }
    function rotateTo(px, py) {
        var c = centerXY();
        var a = Math.atan2(py - c.y, px - c.x) * 180 / Math.PI;
        rot = baseRot + (a - startAngle);
        apply();
    }

    function buildStage(href) {
        stage = document.createElement('div');
        stage.className = 'cv-stage';
        stage.style.cssText = 'position:relative;display:inline-block;line-height:0;transition:transform .05s linear;transform-origin:center center;will-change:transform';
        img = document.createElement('img');
        img.src = encodeURI(href); img.alt = 'Comprobante';
        img.draggable = false;
        img.style.cssText = 'display:block;max-width:80vw;max-height:74vh;user-select:none;-webkit-user-drag:none';
        stage.appendChild(img);
        // 4 zonas de esquina para girar (INVISIBLES: el visor queda limpio como antes;
        // el cursor cambia al pasar por encima y el pie de ayuda lo explica).
        [['tl', '0', 'auto', 'auto', '0'], ['tr', '0', '0', 'auto', 'auto'],
         ['br', 'auto', '0', '0', 'auto'], ['bl', 'auto', 'auto', '0', '0']]
        .forEach(function (p) {
            var h = document.createElement('span');
            h.className = 'cv-handle cv-' + p[0];
            h.style.cssText = 'position:absolute;top:' + p[1] + ';right:' + p[2] + ';bottom:' + p[3] + ';left:' + p[4] +
                ';width:44px;height:44px;background:transparent;cursor:grab;touch-action:none;z-index:2';
            h.addEventListener('mousedown', function (e) { e.preventDefault(); e.stopPropagation(); startRotate(e.clientX, e.clientY); });
            h.addEventListener('touchstart', function (e) { e.stopPropagation(); if (e.touches.length === 1) startRotate(e.touches[0].clientX, e.touches[0].clientY); }, { passive: true });
            stage.appendChild(h);
        });
        wireStage();
        return stage;
    }

    function wireStage() {
        img.addEventListener('wheel', function (e) { e.preventDefault(); zoomAt(e.deltaY < 0 ? 1.15 : 1 / 1.15); }, { passive: false });
        img.addEventListener('dblclick', function (e) { e.preventDefault(); reset(); });
        img.addEventListener('mousedown', function (e) { if (scale <= 1) return; e.preventDefault(); drag = true; sx = e.clientX - tx; sy = e.clientY - ty; apply(); });
        // Touch: 1 dedo arrastra (si hay zoom); 2 dedos = pellizco (zoom) + giro (rotar).
        img.addEventListener('touchstart', function (e) {
            if (e.touches.length === 1 && scale > 1) { drag = true; sx = e.touches[0].clientX - tx; sy = e.touches[0].clientY - ty; }
            else if (e.touches.length === 2) { pinchD = dist(e.touches); pinchA = angle(e.touches); }
        }, { passive: true });
        img.addEventListener('touchmove', function (e) {
            if (e.touches.length === 2) {
                e.preventDefault();
                var d = dist(e.touches);  if (pinchD) { scale = Math.min(6, Math.max(1, scale * (d / pinchD))); } pinchD = d;
                var a = angle(e.touches); if (pinchA !== null) { rot += (a - pinchA); } pinchA = a;
                apply();
            } else if (drag && e.touches.length === 1) { tx = e.touches[0].clientX - sx; ty = e.touches[0].clientY - sy; apply(); }
        }, { passive: false });
        img.addEventListener('touchend', function () { drag = false; pinchD = 0; if (pinchA !== null) { rot = snap(rot); apply(); pinchA = null; } });
        // Rotación por touch en las manijas (movimiento global)
        window.addEventListener('touchmove', function (e) {
            if (rotating && e.touches.length === 1) { e.preventDefault(); rotateTo(e.touches[0].clientX, e.touches[0].clientY); }
        }, { passive: false });
        window.addEventListener('touchend', function () { if (rotating) { rotating = false; rot = snap(rot); apply(); } });
    }
    function dist(t) { var dx = t[0].clientX - t[1].clientX, dy = t[0].clientY - t[1].clientY; return Math.sqrt(dx * dx + dy * dy); }
    function angle(t) { return Math.atan2(t[1].clientY - t[0].clientY, t[1].clientX - t[0].clientX) * 180 / Math.PI; }

    function open(href, isPdf, title, desc) {
        if (!overlay) build();
        lastFocus = document.activeElement;
        titleEl.textContent = title || 'Comprobante';
        descEl.textContent = desc || '';
        descEl.style.display = desc ? '' : 'none';
        bodyEl.innerHTML = '';
        stage = null; img = null; scale = 1; tx = 0; ty = 0; rot = 0; drag = false; rotating = false;
        if (toolsEl) toolsEl.style.display = isPdf ? 'none' : 'flex';   // rotar sólo para imágenes
        if (isPdf) {
            var f = document.createElement('iframe');
            f.src = encodeURI(href); f.title = 'Comprobante (PDF)';
            bodyEl.appendChild(f);
            hintEl.style.display = 'none';
        } else {
            bodyEl.appendChild(buildStage(href));
            hintEl.style.display = '';
        }
        overlay.classList.add('open');
        document.body.classList.add('modal-open');
        var cb = overlay.querySelector('.cv-close'); if (cb) cb.focus();
    }

    function close() {
        if (!overlay) return;
        overlay.classList.remove('open');
        document.body.classList.remove('modal-open');
        bodyEl.innerHTML = ''; stage = null; img = null;
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    document.addEventListener('click', function (e) {
        var a = e.target.closest('a.comp-viewer');
        if (!a) return;
        e.preventDefault();
        open(a.getAttribute('href'), a.getAttribute('data-type') === 'iframe',
             a.getAttribute('data-title'), a.getAttribute('data-description'));
    });
})();
