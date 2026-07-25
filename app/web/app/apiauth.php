<?php
declare(strict_types=1);

/**
 * API de solo lectura — autenticación por token (X-API-Key) y helpers JSON.
 * Pensada para consumir el estado y los comprobantes desde servicios externos
 * (ej. el asistente "Jarvis"). No expone escritura.
 */

/** Lee el token del header X-API-Key (o Authorization: Bearer). */
function api_read_key(): string
{
    $k = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($k === '') {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (stripos($auth, 'bearer ') === 0) {
            $k = substr($auth, 7);
        }
    }
    if ($k === '' && isset($_GET['api_key'])) {   // fallback simple para pruebas
        $k = (string)$_GET['api_key'];
    }
    return trim($k);
}

/** Devuelve el token válido y activo (y registra el uso), o null. */
function api_token_verify(): ?array
{
    $key = api_read_key();
    if ($key === '') {
        return null;
    }
    $t = q('SELECT * FROM api_tokens WHERE token_hash = ? AND activo = 1 LIMIT 1', [hash('sha256', $key)])->fetch();
    if (!$t) {
        return null;
    }
    q('UPDATE api_tokens SET usos = usos + 1, ultimo_uso = NOW() WHERE id = ?', [$t['id']]);
    return $t;
}

/** Responde JSON y corta. */
function api_json($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/** Exige token válido; si falta, corta con 401. */
function api_require(): array
{
    $ruta = function_exists('current_route') ? current_route() : 'api';
    $t = api_token_verify();
    if (!$t) {
        if (function_exists('log_api')) {
            log_api($ruta, ['tipo' => 'auth', 'actor' => 'desconocido', 'detalle' => 'Token inválido o ausente (401)']);
        }
        api_json(['ok' => false, 'error' => 'Token inválido o ausente. Enviá el header X-API-Key.'], 401);
    }
    if (function_exists('log_api')) {
        log_api($ruta, ['actor' => 'Token: ' . ($t['nombre'] ?? '?'), 'entidad' => 'token', 'entidad_id' => (int)$t['id'],
            'detalle' => 'Pedido autorizado' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? ' · ?' . mb_substr((string)$_SERVER['QUERY_STRING'], 0, 200) : '')]);
    }
    return $t;
}

/** Da forma pública a una fila de comprobante para la API. */
function api_row(array $r): array
{
    return [
        'id'          => (int)$r['id'],
        'fecha'       => $r['fecha_emision'],
        'periodo'     => $r['periodo'],
        'tipo'        => $r['tipo_comprobante'],
        'letra'       => $r['letra'],
        'punto_venta' => $r['punto_venta'],
        'numero'      => $r['numero'],
        'proveedor'   => $r['prov_razon'],
        'cuit'        => $r['prov_cuit'],
        'categoria'   => $r['concepto'],
        'moneda'      => $r['moneda'],
        'neto'        => (float)$r['importe_neto'],
        'iva'         => (float)$r['importe_iva'],
        'percepcion'  => (float)$r['importe_percepcion'],
        'total'       => (float)$r['importe_total'],
        'estado'      => $r['estado'],
        'origen'      => $r['origen'],
    ];
}

// ---- Gestión de tokens (admin) ----
function api_token_create(string $nombre, int $uid): array
{
    $prefijo = bin2hex(random_bytes(4));
    $full = 'ith_' . $prefijo . '_' . bin2hex(random_bytes(24));
    q('INSERT INTO api_tokens (nombre, prefijo, token_hash, creado_por) VALUES (?, ?, ?, ?)',
        [$nombre, $prefijo, hash('sha256', $full), $uid]);
    $tid = (int)db()->lastInsertId();
    if (function_exists('log_app')) {
        log_app('token.crear', ['entidad' => 'token', 'entidad_id' => $tid, 'detalle' => 'Token «' . $nombre . '» creado']);
    }
    return ['id' => $tid, 'nombre' => $nombre, 'prefijo' => $prefijo, 'token' => $full];
}

function api_tokens_all(): array
{
    return q('SELECT id, nombre, prefijo, activo, usos, ultimo_uso, created_at FROM api_tokens ORDER BY created_at DESC')->fetchAll();
}

function api_token_set_active(int $id, bool $activo): void
{
    q('UPDATE api_tokens SET activo = ? WHERE id = ?', [$activo ? 1 : 0, $id]);
    if (function_exists('log_app')) {
        log_app('token.estado', ['entidad' => 'token', 'entidad_id' => $id, 'detalle' => $activo ? 'Activado' : 'Revocado']);
    }
}

function api_token_delete(int $id): void
{
    q('DELETE FROM api_tokens WHERE id = ?', [$id]);
    if (function_exists('log_app')) {
        log_app('token.eliminar', ['entidad' => 'token', 'entidad_id' => $id, 'detalle' => 'Token eliminado']);
    }
}
