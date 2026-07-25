/**
 * confirm.js — Modal de confirmación PROFESIONAL branded (ITH), reemplaza el
 * confirm() nativo del navegador en TODA la app.
 *
 * Uso declarativo (sin JS):
 *   <form data-confirm="¿Seguro?" data-confirm-danger data-confirm-ok="Eliminar"> …
 *   <button data-confirm="¿Seguro?"> …            (dentro de un form)
 *   <a data-confirm-link="¿Seguro?" href="…">      (navega si confirma)
 *
 * Uso programático:
 *   window.appConfirm('¿Seguro?').then(ok => { … })
 *   window.appConfirm({ message, title, okText, cancelText, danger }).then(…)
 */
(function () {
    'use strict';
    var overlay, titleEl, msgEl, okBtn, cancelBtn, iconEl, resolver;

    var ICON_Q = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg>';
    var ICON_ALERT = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg>';

    function build() {
        overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.innerHTML =
            '<div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="mdlTitle" aria-describedby="mdlMsg">' +
                '<div class="modal-ic"></div>' +
                '<h3 class="modal-title" id="mdlTitle">Confirmar</h3>' +
                '<p class="modal-msg" id="mdlMsg"></p>' +
                '<div class="modal-actions">' +
                    '<button type="button" class="btn btn-ghost" data-modal="cancel">Cancelar</button>' +
                    '<button type="button" class="btn btn-primary" data-modal="ok">Aceptar</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);
        titleEl = overlay.querySelector('.modal-title');
        msgEl = overlay.querySelector('.modal-msg');
        iconEl = overlay.querySelector('.modal-ic');
        okBtn = overlay.querySelector('[data-modal="ok"]');
        cancelBtn = overlay.querySelector('[data-modal="cancel"]');
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) { close(false); return; }
            var b = e.target.closest('[data-modal]');
            if (b) close(b.dataset.modal === 'ok');
        });
        document.addEventListener('keydown', function (e) {
            if (!overlay || !overlay.classList.contains('open')) return;
            if (e.key === 'Escape') { e.preventDefault(); close(false); }
            else if (e.key === 'Enter') { e.preventDefault(); close(true); }
        });
    }

    function open(opts) {
        if (!overlay) build();
        var o = typeof opts === 'string' ? { message: opts } : (opts || {});
        var danger = !!o.danger;
        titleEl.textContent = o.title || (danger ? '¿Estás seguro?' : 'Confirmar');
        msgEl.textContent = o.message || '¿Querés continuar?';
        okBtn.textContent = o.okText || 'Aceptar';
        cancelBtn.textContent = o.cancelText || 'Cancelar';
        iconEl.innerHTML = danger ? ICON_ALERT : ICON_Q;
        overlay.classList.toggle('danger', danger);
        overlay.classList.add('open');
        document.body.classList.add('modal-open');
        setTimeout(function () { okBtn.focus(); }, 20);
        return new Promise(function (res) { resolver = res; });
    }
    function close(result) {
        if (!overlay) return;
        overlay.classList.remove('open');
        document.body.classList.remove('modal-open');
        if (resolver) { var r = resolver; resolver = null; r(result); }
    }

    window.appConfirm = open;

    // --- Interceptores declarativos ---
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.nodeName !== 'FORM' || !form.hasAttribute('data-confirm')) return;
        if (form.dataset.ok === '1') { form.dataset.ok = ''; return; } // ya confirmado
        e.preventDefault();
        open({
            message: form.getAttribute('data-confirm'),
            danger: form.hasAttribute('data-confirm-danger'),
            okText: form.getAttribute('data-confirm-ok') || 'Aceptar'
        }).then(function (ok) {
            if (!ok) return;
            form.dataset.ok = '1';
            if (form.requestSubmit) form.requestSubmit(); else form.submit();
        });
    }, true);

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-confirm]');
        if (btn) {
            if (btn.dataset.ok === '1') { btn.dataset.ok = ''; return; }
            e.preventDefault(); e.stopPropagation();
            open({
                message: btn.getAttribute('data-confirm'),
                danger: btn.hasAttribute('data-confirm-danger'),
                okText: btn.getAttribute('data-confirm-ok') || 'Aceptar'
            }).then(function (ok) {
                if (!ok) return;
                btn.dataset.ok = '1';
                var f = btn.form;
                if (f && f.requestSubmit) f.requestSubmit(btn); else btn.click();
            });
            return;
        }
        var a = e.target.closest('a[data-confirm-link]');
        if (a) {
            e.preventDefault();
            open({ message: a.getAttribute('data-confirm-link'), danger: a.hasAttribute('data-confirm-danger') })
                .then(function (ok) { if (ok) window.location.href = a.href; });
        }
    }, true);
})();
