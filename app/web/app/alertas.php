<?php
declare(strict_types=1);

// ============================================================
//  Módulo de Alertas (centralizado)
//   · Cada condición de la app "empuja" una alerta idempotente (clave única).
//   · Vive DENTRO del sistema (bandeja) por default; según config se envía por
//     Email y/o ITHOS (Jarvis) — WhatsApp se hace a través de ITHOS.
//   · La configuración (qué canal por tipo) la maneja el ADMIN.
//  Sin scheduler propio: se evalúa on-demand (throttle) + botón "Evaluar ahora"
//  + endpoint opcional para el cron de Hostinger.
// ============================================================

// ---- Catálogo de tipos de alerta (extensible: agregar acá una nueva regla/tipo) ----
function alerta_tipos(): array
{
    return [
        'gasto_pago_vencido' => ['label' => 'Pago de gasto VENCIDO', 'sev' => 'danger',
            'desc' => 'Un gasto recurrente quedó pendiente y su vencimiento ya pasó.'],
        'gasto_por_vencer'   => ['label' => 'Pago de gasto por vencer', 'sev' => 'warn',
            'desc' => 'Un gasto recurrente pendiente vence en los próximos días.'],
        'comprobante_pendiente' => ['label' => 'Comprobantes sin revisar', 'sev' => 'info',
            'desc' => 'Hay comprobantes cargados por OCR esperando revisión.'],
    ];
}

// ---- Config por tipo (qué canales) — JSON en app_settings ----
function alertas_config(): array
{
    $def = [];
    foreach (alerta_tipos() as $k => $t) {
        $def[$k] = ['activo' => 1, 'email' => 0, 'ithos' => 0]; // in-sistema SIEMPRE on
    }
    $raw = setting_get('alertas_config', null);
    $cfg = $raw ? (json_decode($raw, true) ?: []) : [];
    foreach ($def as $k => $v) {
        if (isset($cfg[$k])) { $def[$k] = array_merge($v, array_map('intval', $cfg[$k])); }
    }
    return $def;
}
function alertas_config_guardar(array $post): void
{
    $out = [];
    foreach (alerta_tipos() as $k => $t) {
        $out[$k] = [
            'activo' => !empty($post['activo'][$k]) ? 1 : 0,
            'email'  => !empty($post['email'][$k]) ? 1 : 0,
            'ithos'  => !empty($post['ithos'][$k]) ? 1 : 0,
        ];
    }
    setting_set('alertas_config', json_encode($out, JSON_UNESCAPED_UNICODE));
    if (function_exists('log_app')) { log_app('alerta.config', ['detalle' => 'Configuración de canales actualizada']); }
}

// ---- Estado de los canales (para la UI) ----
function alertas_canales_estado(): array
{
    return [
        'sistema' => ['ok' => true, 'detalle' => 'Bandeja dentro del sistema (siempre activa).'],
        'email'   => ['ok' => function_exists('mail_configurado') && mail_configurado(),
            'detalle' => (function_exists('mail_configurado') && mail_configurado())
                ? ('SMTP configurado (' . (mail_cfg()['user'] ?? '') . ').') : 'Falta configurar el SMTP (config.php $CONFIG[\'mail\'] o ajustes mail_*).'],
        'ithos'   => ['ok' => (bool)setting_get('alertas_ithos_url'),
            'detalle' => setting_get('alertas_ithos_url') ? 'Endpoint ITHOS configurado.' : 'Falta el endpoint/API-key de ITHOS.'],
    ];
}

// ============================================================
//  Crear / actualizar / resolver alertas
// ============================================================

/**
 * Empuja una alerta idempotente por `clave`. Si es NUEVA, la despacha a los
 * canales habilitados (email/ITHOS). Si ya existe (misma clave), sólo actualiza
 * el texto/severidad (NO re-envía, para no spamear). Devuelve el id.
 */
function alerta_push(string $tipo, array $a): int
{
    $clave = (string)($a['clave'] ?? ($tipo . ':' . ($a['entidad_id'] ?? '')));
    $cfg = alertas_config();
    if (empty($cfg[$tipo]['activo'])) { return 0; } // tipo desactivado por el admin
    $sev = $a['severidad'] ?? (alerta_tipos()[$tipo]['sev'] ?? 'info');

    $ex = q('SELECT id, resuelta_en FROM alertas WHERE clave = ? LIMIT 1', [$clave])->fetch();
    if ($ex) {
        q('UPDATE alertas SET tipo=?, severidad=?, titulo=?, mensaje=?, entidad=?, entidad_id=?, url=?, resuelta_en=NULL WHERE id=?',
            [$tipo, $sev, $a['titulo'] ?? '', $a['mensaje'] ?? null, $a['entidad'] ?? null,
             $a['entidad_id'] ?? null, $a['url'] ?? null, (int)$ex['id']]);
        return (int)$ex['id'];
    }
    q('INSERT INTO alertas (tipo, severidad, titulo, mensaje, entidad, entidad_id, url, clave) VALUES (?,?,?,?,?,?,?,?)',
        [$tipo, $sev, $a['titulo'] ?? '', $a['mensaje'] ?? null, $a['entidad'] ?? null,
         $a['entidad_id'] ?? null, $a['url'] ?? null, $clave]);
    $id = (int) db()->lastInsertId();
    alerta_despachar($id, $tipo, $cfg[$tipo], $a);   // sólo al crear
    return $id;
}

/** Resuelve (cierra) las alertas de un tipo cuya clave YA NO está vigente. */
function alertas_resolver_ausentes(string $tipo, array $clavesVigentes): void
{
    $abiertas = q('SELECT id, clave FROM alertas WHERE tipo = ? AND resuelta_en IS NULL', [$tipo])->fetchAll();
    foreach ($abiertas as $row) {
        if (!in_array($row['clave'], $clavesVigentes, true)) {
            q('UPDATE alertas SET resuelta_en = NOW() WHERE id = ?', [(int)$row['id']]);
        }
    }
}

// ---- Despacho a canales (in-sistema ya está en la DB) ----
function alerta_despachar(int $id, string $tipo, array $cfg, array $a): void
{
    $canales = ['sistema'];
    if (!empty($cfg['email']) && function_exists('mail_configurado') && mail_configurado()) {
        if (alerta_enviar_email($a)) { $canales[] = 'email'; }
    }
    if (!empty($cfg['ithos']) && setting_get('alertas_ithos_url')) {
        if (alerta_enviar_ithos($tipo, $a)) { $canales[] = 'ithos'; }
    }
    q('UPDATE alertas SET canales = ? WHERE id = ?', [implode(',', $canales), $id]);
}

function alerta_email_destinatarios(): array
{
    $to = trim((string)setting_get('alertas_email_to', ''));
    if ($to !== '') { return array_filter(array_map('trim', preg_split('/[,;\s]+/', $to))); }
    // Por default: los administradores.
    try {
        $rows = q("SELECT email FROM usuarios WHERE rol='admin' AND activo=1 AND email <> ''")->fetchAll();
        return array_column($rows, 'email');
    } catch (\Throwable $e) { return []; }
}

function alerta_enviar_email(array $a): bool
{
    $dests = alerta_email_destinatarios();
    if (!$dests) { return false; }
    $titulo = (string)($a['titulo'] ?? 'Alerta');
    $msg = (string)($a['mensaje'] ?? '');
    $url = (string)($a['url'] ?? '');
    $link = $url ? '<p><a href="' . htmlspecialchars($url) . '" style="color:#663399;font-weight:600">Ver en el sistema</a></p>' : '';
    $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto">'
        . '<div style="background:#663399;color:#fff;padding:14px 18px;border-radius:10px 10px 0 0;font-weight:700">INTELLIHELP · Alerta</div>'
        . '<div style="border:1px solid #eee;border-top:0;padding:18px;border-radius:0 0 10px 10px">'
        . '<h2 style="margin:0 0 8px;font-size:17px;color:#161922">' . htmlspecialchars($titulo) . '</h2>'
        . '<p style="color:#444;line-height:1.5">' . nl2br(htmlspecialchars($msg)) . '</p>' . $link
        . '<p style="color:#999;font-size:12px;margin-top:16px">Rendición de comprobantes · INTELLIHELP</p>'
        . '</div></div>';
    $ok = false;
    foreach ($dests as $to) {
        $err = null;
        if (mail_enviar($to, '[INTELLIHELP] ' . $titulo, $html, $err)) { $ok = true; }
        elseif (function_exists('log_app')) { log_app('alerta.email_error', ['detalle' => $to . ': ' . $err]); }
    }
    return $ok;
}

// ITHOS (Jarvis): POST del payload a su endpoint (WhatsApp se resuelve del lado de ITHOS).
// Usa la sección API: Authorization: Bearer <key>. Off hasta configurar url+key.
function alerta_enviar_ithos(string $tipo, array $a): bool
{
    $url = (string)setting_get('alertas_ithos_url', '');
    $key = (string)setting_get('alertas_ithos_key', '');
    if ($url === '') { return false; }
    $payload = json_encode([
        'origen' => 'rendicion-comprobantes', 'tipo' => $tipo,
        'titulo' => $a['titulo'] ?? '', 'mensaje' => $a['mensaje'] ?? '',
        'severidad' => $a['severidad'] ?? 'info', 'url' => $a['url'] ?? '',
        'canal_sugerido' => 'whatsapp',
    ], JSON_UNESCAPED_UNICODE);
    if (!function_exists('curl_init')) { return false; }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => array_filter([
            'Content-Type: application/json',
            $key ? ('Authorization: Bearer ' . $key) : null,
        ]),
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        if (function_exists('log_app')) { log_app('alerta.ithos_error', ['detalle' => 'HTTP ' . $code]); }
        return false;
    }
    return true;
}

// ============================================================
//  Motor de reglas (qué genera alertas)
// ============================================================

function alertas_evaluar(): int
{
    $n = 0;
    $cfg = alertas_config();

    // Regla 1 y 2: gastos recurrentes pendientes, según su vencimiento.
    if (!empty($cfg['gasto_pago_vencido']['activo']) || !empty($cfg['gasto_por_vencer']['activo'])) {
        $rows = q("SELECT g.id, g.periodo, g.fecha_vencimiento, g.valor_mensual, g.moneda,
                          DATEDIFF(CURDATE(), g.fecha_vencimiento) AS dias,
                          p.nombre AS prov
                     FROM gastos_recurrentes g
                     JOIN gastos_proveedores p ON p.id = g.proveedor_id
                    WHERE g.eliminado_en IS NULL AND g.estado <> 'pagado'
                      AND g.fecha_vencimiento IS NOT NULL")->fetchAll();
        $vigVenc = []; $vigPorVenc = [];
        foreach ($rows as $r) {
            $dias = (int)$r['dias'];              // >0 vencido, <0 faltan días
            $url = url('gasto-form') . '?id=' . (int)$r['id'];
            $imp = $r['valor_mensual'] !== null ? ' · $' . number_format((float)$r['valor_mensual'], 2, ',', '.') : '';
            if ($dias > 0) {
                $clave = 'gasto_pago_vencido:' . (int)$r['id'];
                $vigVenc[] = $clave;
                alerta_push('gasto_pago_vencido', [
                    'clave' => $clave, 'entidad' => 'gasto', 'entidad_id' => (int)$r['id'], 'url' => $url,
                    'titulo' => 'Pago VENCIDO · ' . $r['prov'] . ' (' . $r['periodo'] . ')',
                    'mensaje' => 'El pago de «' . $r['prov'] . '» del período ' . $r['periodo'] . $imp
                        . ' venció hace ' . $dias . ' día' . ($dias === 1 ? '' : 's')
                        . ' (venc. ' . date('d/m/Y', strtotime((string)$r['fecha_vencimiento'])) . ') y sigue pendiente.',
                ]);
                $n++;
            } elseif ($dias >= -7) {             // vence en los próximos 7 días
                $clave = 'gasto_por_vencer:' . (int)$r['id'];
                $vigPorVenc[] = $clave;
                $faltan = -$dias;
                alerta_push('gasto_por_vencer', [
                    'clave' => $clave, 'entidad' => 'gasto', 'entidad_id' => (int)$r['id'], 'url' => $url,
                    'titulo' => 'Vence pronto · ' . $r['prov'] . ' (' . $r['periodo'] . ')',
                    'mensaje' => 'El pago de «' . $r['prov'] . '» del período ' . $r['periodo'] . $imp
                        . ($faltan === 0 ? ' vence HOY.' : ' vence en ' . $faltan . ' día' . ($faltan === 1 ? '' : 's')
                        . ' (' . date('d/m/Y', strtotime((string)$r['fecha_vencimiento'])) . ').'),
                ]);
                $n++;
            }
        }
        alertas_resolver_ausentes('gasto_pago_vencido', $vigVenc);
        alertas_resolver_ausentes('gasto_por_vencer', $vigPorVenc);
    }

    // Regla 3: comprobantes pendientes de revisión (una sola alerta agregada).
    if (!empty($cfg['comprobante_pendiente']['activo'])) {
        $cant = (int) q("SELECT COUNT(*) FROM comprobantes WHERE estado='pendiente_revision' AND eliminado_en IS NULL")->fetchColumn();
        if ($cant > 0) {
            alerta_push('comprobante_pendiente', [
                'clave' => 'comprobante_pendiente:global', 'entidad' => 'comprobante', 'url' => url('comprobantes?estado=pendiente_revision'),
                'titulo' => $cant . ' comprobante' . ($cant === 1 ? '' : 's') . ' sin revisar',
                'mensaje' => 'Hay ' . $cant . ' comprobante' . ($cant === 1 ? '' : 's') . ' cargado' . ($cant === 1 ? '' : 's') . ' por OCR esperando revisión.',
            ]);
            $n++;
        } else {
            alertas_resolver_ausentes('comprobante_pendiente', []);
        }
    }

    setting_set('alertas_last_eval', (string)time());
    if (function_exists('log_app')) { log_app('alerta.evaluar', ['detalle' => $n . ' condición(es) evaluada(s)']); }
    return $n;
}

/** Evaluación automática con throttle (para no correr en cada request). */
function alertas_autoeval(int $cada = 1800): void
{
    $last = (int) setting_get('alertas_last_eval', '0');
    if (time() - $last < $cada) { return; }
    try { alertas_evaluar(); } catch (\Throwable $e) {
        if (function_exists('log_app')) { log_app('alerta.evaluar_error', ['detalle' => $e->getMessage()]); }
    }
}

// ============================================================
//  Bandeja (in-sistema)
// ============================================================

function alertas_no_leidas(): int
{
    try { return (int) q('SELECT COUNT(*) FROM alertas WHERE leida_en IS NULL AND resuelta_en IS NULL')->fetchColumn(); }
    catch (\Throwable $e) { return 0; }
}
function alertas_listar(bool $incluirResueltas = false): array
{
    $where = $incluirResueltas ? '' : 'WHERE resuelta_en IS NULL';
    return q("SELECT * FROM alertas $where ORDER BY (leida_en IS NULL) DESC,
                     FIELD(severidad,'danger','warn','info'), created_at DESC LIMIT 200")->fetchAll();
}
function alerta_marcar_leida(int $id): void { q('UPDATE alertas SET leida_en = NOW() WHERE id = ? AND leida_en IS NULL', [$id]); }
function alertas_marcar_todas(): void { q('UPDATE alertas SET leida_en = NOW() WHERE leida_en IS NULL'); }
function alerta_resolver(int $id): void { q('UPDATE alertas SET resuelta_en = NOW(), leida_en = COALESCE(leida_en, NOW()) WHERE id = ?', [$id]); }
