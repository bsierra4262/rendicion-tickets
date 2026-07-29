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
    elseif ($acc === 'config')                           { alertas_config_guardar($_POST); flash('Configuración de canales guardada.', 'success'); }
    elseif ($acc === 'ithos') {
        setting_set('alertas_ithos_url', trim((string)($_POST['ithos_url'] ?? '')));
        setting_set('alertas_ithos_key', trim((string)($_POST['ithos_key'] ?? '')));
        setting_set('alertas_email_to', trim((string)($_POST['email_to'] ?? '')));
        flash('Canales externos actualizados.', 'success');
    }
    redirect('alertas');
}

// Al entrar, evaluar (asegura la bandeja al día) — barato, con manejo de errores.
try { alertas_evaluar(); } catch (\Throwable $e) { /* auditado en el modelo */ }

render('alertas', [
    'alertas'  => alertas_listar((($_GET['ver'] ?? '') === 'todas')),
    'verTodas' => ($_GET['ver'] ?? '') === 'todas',
    'tipos'    => alerta_tipos(),
    'cfg'      => alertas_config(),
    'canales'  => alertas_canales_estado(),
    'ithosUrl' => (string)setting_get('alertas_ithos_url', ''),
    'ithosKey' => (string)setting_get('alertas_ithos_key', ''),
    'emailTo'  => (string)setting_get('alertas_email_to', ''),
], 'Alertas');
