<?php
declare(strict_types=1);

// Módulo de Alertas — bandeja + configuración (solo admin).
if (is_post()) {
    verify_csrf();
    $acc = (string)($_POST['accion'] ?? '');
    if ($acc === 'leer' && !empty($_POST['id']))        { alerta_marcar_leida((int)$_POST['id']); flash('Alerta marcada como leída.', 'success'); }
    elseif ($acc === 'leer_todas')                       { alertas_marcar_todas(); flash('Todas marcadas como leídas.', 'success'); }
    elseif ($acc === 'resolver' && !empty($_POST['id'])) { alerta_resolver((int)$_POST['id']); flash('Alerta resuelta.', 'success'); }
    elseif ($acc === 'evaluar')                          { $n = alertas_evaluar(); flash('Evaluación completa: ' . $n . ' condición(es).', 'success'); }
    elseif ($acc === 'config') {
        $nuevos = alertas_config_guardar($_POST);
        flash('Configuración de canales guardada.', 'success');
        foreach ($nuevos as $canal) {   // canal recién activado → mandar las últimas 5 (no dejarlas pasar)
            $c = alertas_reenviar_recientes($canal, 5);
            if ($c) { flash('Se reenviaron las últimas ' . $c . ' alerta(s) por ' . strtoupper($canal) . '.', 'success'); }
        }
    }
    elseif ($acc === 'ithos') {
        setting_set('alertas_email_to', trim((string)($_POST['email_to'] ?? '')));
        setting_set('app_entorno', ($_POST['entorno'] ?? 'testing') === 'produccion' ? 'produccion' : 'testing');
        flash('Configuración actualizada.', 'success');
    }
    redirect('alertas');
}

// Clave para el cron-web (fallback), por si se prefiere cron-URL en vez de CLI.
if (!setting_get('alertas_cron_key')) { setting_set('alertas_cron_key', bin2hex(random_bytes(12))); }
// Al entrar, evaluar (asegura la bandeja al día) — barato, con manejo de errores.
try { alertas_evaluar(); } catch (\Throwable $e) { /* auditado en el modelo */ }

// Asegura que el token de ITHOS exista por defecto (los detalles técnicos viven en la
// sección API, no acá — Alertas es una pantalla de operación, no de desarrollo).
try { api_token_ithos_asegurar(); } catch (\Throwable $e) {}

render('alertas', [
    'alertas'  => alertas_listar((($_GET['ver'] ?? '') === 'todas')),
    'verTodas' => ($_GET['ver'] ?? '') === 'todas',
    'tipos'    => alerta_tipos(),
    'cfg'      => alertas_config(),
    'canales'  => alertas_canales_estado(),
    'emailTo'  => (string)setting_get('alertas_email_to', ''),
    'entorno'  => alertas_entorno(),
], 'Alertas');
