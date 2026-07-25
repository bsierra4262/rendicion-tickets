/**
 * filefield.js — Convierte un <input type="file"> plano en un control con diseño:
 *   · zona de arrastre (drag & drop) que refleja el estado del input real
 *   · muestra el nombre del archivo elegido (o el texto vacío por defecto)
 *   · 100% accesible: el <input> sigue siendo el control real (label lo activa)
 *
 * Markup esperado:
 *   <div class="filefield" data-filefield>
 *     <input class="filefield__input" type="file" id="x" ...>
 *     <label class="filefield__drop" for="x"> … </label>
 *     <p class="filefield__name" data-empty="Sin archivo">Sin archivo</p>
 *   </div>
 *
 * No depende de librerías. Se auto-inicializa al cargar el DOM.
 */
(function () {
    'use strict';

    function humanSize(bytes) {
        if (!bytes && bytes !== 0) return '';
        var u = ['B', 'KB', 'MB', 'GB'], i = 0, n = bytes;
        while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
        return (i === 0 ? n : n.toFixed(1)) + ' ' + u[i];
    }

    function render(field, input) {
        var name = field.querySelector('.filefield__name');
        var has = input.files && input.files.length > 0;
        field.classList.toggle('has-file', has);
        if (!name) return;
        if (has) {
            var f = input.files[0];
            name.textContent = f.name + (f.size ? ' · ' + humanSize(f.size) : '');
        } else {
            name.textContent = name.getAttribute('data-empty') || 'Sin archivo seleccionado';
        }
    }

    function wire(field) {
        var input = field.querySelector('.filefield__input');
        var drop = field.querySelector('.filefield__drop');
        if (!input || field.dataset.wired) return;
        field.dataset.wired = '1';

        input.addEventListener('change', function () { render(field, input); });

        if (drop) {
            // Evitar que el navegador abra el archivo si cae fuera de la zona
            ['dragenter', 'dragover'].forEach(function (ev) {
                drop.addEventListener(ev, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    field.classList.add('is-dragover');
                });
            });
            ['dragleave', 'dragend'].forEach(function (ev) {
                drop.addEventListener(ev, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    field.classList.remove('is-dragover');
                });
            });
            drop.addEventListener('drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                field.classList.remove('is-dragover');
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                    // Asignar los archivos soltados al input real y notificar
                    input.files = e.dataTransfer.files;
                    render(field, input);
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        }

        render(field, input); // estado inicial (por si el navegador recuerda un archivo)
    }

    function init() {
        document.querySelectorAll('[data-filefield]').forEach(wire);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
