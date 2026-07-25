<?php
declare(strict_types=1);

require_admin();

if (is_post()) {
    verify_csrf();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'parent') {
        $pid = trim((string)($_POST['drive_parent_id'] ?? ''));
        // Aceptar tanto el ID como la URL completa de la carpeta.
        if (preg_match('~/folders/([A-Za-z0-9_\-]+)~', $pid, $m)) { $pid = $m[1]; }
        $pid = preg_replace('/[^A-Za-z0-9_\-]/', '', $pid) ?? '';
        if ($pid !== '') {
            setting_set('drive_parent_id', $pid);
            flash('Carpeta raíz actualizada.', 'success');
        } else {
            flash('El ID/URL de la carpeta no es válido.', 'error');
        }
        redirect('drive');
    } elseif ($accion === 'desconectar') {
        drive_desconectar();
        if (function_exists('log_app')) {
            log_app('drive.desconectar', ['tipo' => 'auth', 'detalle' => 'Google Drive desconectado']);
        }
        flash('Google Drive desconectado. Las imágenes seguirán guardándose localmente.', 'info');
        redirect('drive');
    } elseif ($accion === 'probar') {
        [$ok, $mensaje] = drive_probar();
        flash($mensaje, $ok ? 'success' : 'error');
        redirect('drive');
    }
}

// Métricas: cuántos comprobantes tienen respaldo en Drive.
$respaldados = 0; $conImagen = 0;
try {
    $respaldados = (int)db()->query("SELECT COUNT(*) FROM comprobantes WHERE drive_file_id IS NOT NULL AND eliminado_en IS NULL")->fetchColumn();
    $conImagen   = (int)db()->query("SELECT COUNT(*) FROM comprobantes WHERE imagen_path IS NOT NULL AND imagen_path <> '' AND eliminado_en IS NULL")->fetchColumn();
} catch (\Throwable $e) {}

render('drive', [
    'conectado'   => drive_conectado(),
    'parent_id'   => drive_parent_id(),
    'por'         => (string)setting_get('drive_conectado_por', ''),
    'cuando'      => (string)setting_get('drive_conectado_en', ''),
    'respaldados' => $respaldados,
    'con_imagen'  => $conImagen,
], 'Google Drive');
