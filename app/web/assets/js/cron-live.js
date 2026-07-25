/**
 * cron-live.js — Auto-actualización en tiempo real de la bitácora CRON.
 *
 * Cada ~15 s vuelve a pedir la MISMA URL (mismos filtros/página), extrae del HTML
 * las tarjetas de stats (#cronStats) y el cuerpo de la tabla (#cronBody) y los
 * reemplaza en vivo. Una sola fuente de verdad (el render de PHP), sin duplicar
 * el markup en JS. Se pausa cuando la pestaña está oculta o el usuario está
 * escribiendo en un filtro, y se puede apagar con el switch "En vivo".
 */
(function () {
    'use strict';
    var box = document.getElementById('cronLive');
    if (!box) return;

    var INTERVALO = 15000;
    var timer = null;
    var cargando = false;

    function estaTipeando() {
        var a = document.activeElement;
        return a && a !== box && a.matches && a.matches('input, select, textarea');
    }

    function tick() {
        if (cargando || document.hidden || estaTipeando()) return;
        cargando = true;
        fetch(window.location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                ['cronStats', 'cronBody'].forEach(function (id) {
                    var nuevo = doc.getElementById(id);
                    var actual = document.getElementById(id);
                    if (nuevo && actual) { actual.replaceWith(nuevo); }
                });
            })
            .catch(function () { /* silencioso: reintenta en el próximo tick */ })
            .then(function () { cargando = false; });
    }

    function start() { if (!timer) { timer = setInterval(tick, INTERVALO); } }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }

    box.addEventListener('change', function () { box.checked ? start() : stop(); });
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && box.checked) { tick(); }
    });

    var btn = document.getElementById('cronRefresh');
    if (btn) {
        btn.addEventListener('click', function (e) { e.preventDefault(); tick(); });
    }

    if (box.checked) { start(); }
})();
