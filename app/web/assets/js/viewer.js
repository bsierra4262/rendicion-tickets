/**
 * viewer.js — Visor de comprobantes propio, en la estética del sitio.
 * Modal branded con encabezado (datos del comprobante) + imagen/PDF.
 * La imagen tiene ZOOM (scroll o pellizco) y PAN (arrastrar) para verla mejor.
 * Cierra con Esc, click afuera o el botón.
 *
 * Uso: <a class="comp-viewer" href="…" data-type="image|iframe"
 *          data-title="…" data-description="…">.
 */
(function () {
    'use strict';
    var overlay = null, titleEl = null, descEl = null, bodyEl = null, hintEl = null, toolsEl = null, lastFocus = null;
    var img = null, scale = 1, tx = 0, ty = 0, rot = 0, drag = false, sx = 0, sy = 0, pinchD = 0;

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
                            '<button type="button" class="cv-rot" data-dir="-1" title="Rotar a la izquierda" aria-label="Rotar a la izquierda" style="background:rgba(255,255,255,.14);border:0;color:#fff;width:34px;height:34px;border-radius:8px;cursor:pointer;font-size:17px">⟲</button>' +
                            '<button type="button" class="cv-rot" data-dir="1" title="Rotar a la derecha" aria-label="Rotar a la derecha" style="background:rgba(255,255,255,.14);border:0;color:#fff;width:34px;height:34px;border-radius:8px;cursor:pointer;font-size:17px">⟳</button>' +
                        '</span>' +
                        '<button type="button" class="cv-close" aria-label="Cerrar">&times;</button>' +
                    '</span>' +
                '</div>' +
                '<div class="cv-body"></div>' +
                '<div class="cv-hint">Scroll para zoom · arrastrá para mover · ⟲ ⟳ para rotar · doble clic para restablecer</div>' +
            '</div>';
        document.body.appendChild(overlay);
        titleEl = overlay.querySelector('.cv-title');
        descEl = overlay.querySelector('.cv-desc');
        bodyEl = overlay.querySelector('.cv-body');
        hintEl = overlay.querySelector('.cv-hint');
        toolsEl = overlay.querySelector('.cv-tools');
        overlay.addEventListener('click', function (e) {
            var rb = e.target.closest('.cv-rot');
            if (rb) { rot = (rot + (rb.getAttribute('data-dir') === '1' ? 90 : -90) + 360) % 360; apply(); return; }
            if (e.target === overlay || e.target.closest('.cv-close')) close();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('open')) close();
        });
    }

    function apply() {
        if (!img) return;
        img.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + scale + ') rotate(' + rot + 'deg)';
        img.style.cursor = scale > 1 ? (drag ? 'grabbing' : 'grab') : 'zoom-in';
    }
    function reset() { scale = 1; tx = 0; ty = 0; rot = 0; apply(); }
    function zoomAt(factor) { scale = Math.min(6, Math.max(1, scale * factor)); if (scale === 1) { tx = 0; ty = 0; } apply(); }

    function wireImage(el) {
        img = el;
        img.style.transition = 'transform .05s linear';
        img.style.transformOrigin = 'center center';
        img.addEventListener('wheel', function (e) { e.preventDefault(); zoomAt(e.deltaY < 0 ? 1.15 : 1 / 1.15); }, { passive: false });
        img.addEventListener('dblclick', function (e) { e.preventDefault(); reset(); });
        img.addEventListener('mousedown', function (e) { if (scale <= 1) return; e.preventDefault(); drag = true; sx = e.clientX - tx; sy = e.clientY - ty; apply(); });
        window.addEventListener('mousemove', function (e) { if (!drag) return; tx = e.clientX - sx; ty = e.clientY - sy; apply(); });
        window.addEventListener('mouseup', function () { if (drag) { drag = false; apply(); } });
        // Touch: arrastrar (1 dedo) + pellizco (2 dedos)
        img.addEventListener('touchstart', function (e) {
            if (e.touches.length === 1 && scale > 1) { drag = true; sx = e.touches[0].clientX - tx; sy = e.touches[0].clientY - ty; }
            else if (e.touches.length === 2) { pinchD = dist(e.touches); }
        }, { passive: true });
        img.addEventListener('touchmove', function (e) {
            if (e.touches.length === 2) { e.preventDefault(); var d = dist(e.touches); if (pinchD) zoomAt(d / pinchD); pinchD = d; }
            else if (drag && e.touches.length === 1) { tx = e.touches[0].clientX - sx; ty = e.touches[0].clientY - sy; apply(); }
        }, { passive: false });
        img.addEventListener('touchend', function () { drag = false; pinchD = 0; });
    }
    function dist(t) { var dx = t[0].clientX - t[1].clientX, dy = t[0].clientY - t[1].clientY; return Math.sqrt(dx * dx + dy * dy); }

    function open(href, isPdf, title, desc) {
        if (!overlay) build();
        lastFocus = document.activeElement;
        titleEl.textContent = title || 'Comprobante';
        descEl.textContent = desc || '';
        descEl.style.display = desc ? '' : 'none';
        bodyEl.innerHTML = '';
        img = null; scale = 1; tx = 0; ty = 0; rot = 0; drag = false;
        if (toolsEl) toolsEl.style.display = isPdf ? 'none' : 'flex';   // rotar sólo para imágenes
        if (isPdf) {
            var f = document.createElement('iframe');
            f.src = encodeURI(href); f.title = 'Comprobante (PDF)';
            bodyEl.appendChild(f);
            hintEl.style.display = 'none';
        } else {
            var el = document.createElement('img');
            el.src = encodeURI(href); el.alt = 'Comprobante';
            bodyEl.appendChild(el);
            wireImage(el);
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
        bodyEl.innerHTML = ''; img = null;
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
