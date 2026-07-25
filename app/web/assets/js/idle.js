/**
 * idle.js — Auto-cierre de sesión por inactividad (UX).
 *
 * La seguridad real la impone el servidor (bootstrap.php destruye la sesión
 * vencida en la próxima request). Este script es solo la experiencia:
 *   · Detecta actividad real (mouse, teclado, scroll, touch).
 *   · Manda un "keepalive" al servidor ante actividad (throttle 60 s) para que
 *     la sesión no expire mientras el usuario trabaja sin recargar la página.
 *   · A los (IDLE − AVISO) segundos muestra un aviso con cuenta regresiva.
 *   · Si no hay respuesta, redirige a /logout (que limpia y avisa en el login).
 *   · Corta también al llegar al máximo absoluto de sesión (8 h).
 *
 * Config inyectada por el layout en window.__sesion = { idle, abs, loginAt, base }.
 */
(function () {
    'use strict';
    var cfg = window.__sesion || {};
    var IDLE = (cfg.idle | 0) || 600;      // seg. de inactividad hasta cerrar
    var ABS  = (cfg.abs  | 0) || 28800;    // seg. máximos de sesión (8 h)
    var WARN = Math.min(60, Math.max(15, Math.floor(IDLE * 0.1))); // aviso previo
    var base = cfg.base || '';
    var loginAt = cfg.loginAt ? cfg.loginAt * 1000 : Date.now();

    var last = Date.now();
    var lastPing = 0;
    var overlay = null, cdEl = null, tick = null, redirecting = false;

    function go(motivo) {
        if (redirecting) { return; }
        redirecting = true;
        window.location.href = base + 'logout?expiro=' + (motivo || 'inactividad');
    }

    function ping() {
        var now = Date.now();
        if (now - lastPing < 60000) { return; }   // como mucho 1 vez por minuto
        lastPing = now;
        try {
            fetch(base + 'api-sesion', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                keepalive: true
            }).then(function (r) {
                if (r && r.status === 440) { go('inactividad'); }
            }).catch(function () {});
        } catch (e) {}
    }

    function onActivity() {
        last = Date.now();
        if (overlay) { hideModal(); }
        ping();
    }

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function (ev) {
        window.addEventListener(ev, onActivity, { passive: true });
    });

    function buildModal() {
        overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.setAttribute('data-idle', '1');
        overlay.innerHTML =
            '<div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="idleT" aria-describedby="idleM">' +
                '<div class="modal-ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>' +
                '<h3 class="modal-title" id="idleT">¿Seguís ahí?</h3>' +
                '<p class="modal-msg" id="idleM">Vamos a cerrar tu sesión por seguridad en <strong id="idleCd">' + WARN + '</strong> segundos.</p>' +
                '<div class="modal-actions">' +
                    '<button type="button" class="btn btn-ghost" data-idle="salir">Salir ahora</button>' +
                    '<button type="button" class="btn btn-primary" data-idle="seguir">Seguir conectado</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);
        cdEl = overlay.querySelector('#idleCd');
        overlay.addEventListener('click', function (e) {
            var b = e.target.closest('[data-idle]');
            if (!b) { return; }
            if (b.getAttribute('data-idle') === 'salir') { go('inactividad'); }
            else { onActivity(); }   // "Seguir conectado": resetea y hace ping
        });
        // forzar reflow y abrir (transición CSS existente)
        void overlay.offsetWidth;
        overlay.classList.add('open');
    }

    function showModal() {
        if (overlay) { return; }
        buildModal();
    }

    function hideModal() {
        if (!overlay) { return; }
        var o = overlay;
        overlay = null; cdEl = null;
        o.classList.remove('open');
        setTimeout(function () { if (o && o.parentNode) { o.parentNode.removeChild(o); } }, 250);
    }

    tick = setInterval(function () {
        var idleSec = (Date.now() - last) / 1000;
        var absSec  = (Date.now() - loginAt) / 1000;
        if (absSec >= ABS) { go('expiro'); return; }
        if (idleSec >= IDLE) { go('inactividad'); return; }
        if (idleSec >= IDLE - WARN) {
            showModal();
            if (cdEl) { cdEl.textContent = String(Math.max(0, Math.ceil(IDLE - idleSec))); }
        }
    }, 2000);

    // Al volver a una pestaña que estuvo en segundo plano, revalidar de inmediato.
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            var idleSec = (Date.now() - last) / 1000;
            if (idleSec >= IDLE || (Date.now() - loginAt) / 1000 >= ABS) { go('inactividad'); }
        }
    });
})();
