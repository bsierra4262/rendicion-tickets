<?php
declare(strict_types=1);

/**
 * API con tokens y PERMISOS (scopes):
 *   · 'lectura' → consulta de solo lectura (estado, comprobantes, alertas). Ideal ITHOS.
 *   · 'root'    → acceso total, sin límites (para integraciones propias, ej. app de Nicolás).
 * Autenticación por token (X-API-Key / Authorization: Bearer / ?api_key=).
 */
function api_scopes(): array { return ['lectura' => 'Lectura (consultas)', 'root' => 'Root (acceso total)']; }
function api_scope_nivel(string $s): int { return $s === 'root' ? 2 : 1; }

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

/** Exige token válido con el permiso mínimo pedido; si falta, corta con 401/403. */
function api_require(string $minScope = 'lectura'): array
{
    $ruta = function_exists('current_route') ? current_route() : 'api';
    $t = api_token_verify();
    if (!$t) {
        if (function_exists('log_api')) {
            log_api($ruta, ['tipo' => 'auth', 'actor' => 'desconocido', 'detalle' => 'Token inválido o ausente (401)']);
        }
        api_json(['ok' => false, 'error' => 'Token inválido o ausente. Enviá el header X-API-Key.'], 401);
    }
    $scope = (string)($t['scope'] ?? 'lectura');
    if (api_scope_nivel($scope) < api_scope_nivel($minScope)) {
        if (function_exists('log_api')) {
            log_api($ruta, ['tipo' => 'auth', 'actor' => 'Token: ' . ($t['nombre'] ?? '?'), 'detalle' => 'Permiso insuficiente (' . $minScope . ' requerido, tiene ' . $scope . ') · 403']);
        }
        api_json(['ok' => false, 'error' => 'La API-key no tiene permiso: se requiere «' . $minScope . '».'], 403);
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
function api_token_create(string $nombre, int $uid, string $scope = 'lectura'): array
{
    $scope = $scope === 'root' ? 'root' : 'lectura';
    $prefijo = bin2hex(random_bytes(4));
    $full = 'ith_' . $prefijo . '_' . bin2hex(random_bytes(24));
    q('INSERT INTO api_tokens (nombre, prefijo, token_hash, scope, creado_por) VALUES (?, ?, ?, ?, ?)',
        [$nombre, $prefijo, hash('sha256', $full), $scope, $uid]);
    $tid = (int)db()->lastInsertId();
    if (function_exists('log_app')) {
        log_app('token.crear', ['entidad' => 'token', 'entidad_id' => $tid, 'detalle' => 'Token «' . $nombre . '» (' . $scope . ') creado']);
    }
    return ['id' => $tid, 'nombre' => $nombre, 'prefijo' => $prefijo, 'scope' => $scope, 'token' => $full];
}

function api_tokens_all(): array
{
    return q('SELECT id, nombre, prefijo, scope, activo, usos, ultimo_uso, created_at FROM api_tokens ORDER BY created_at DESC')->fetchAll();
}

/**
 * Asegura un token por DEFECTO para ITHOS (scope 'lectura'), idempotente. Guarda el texto
 * plano en un setting para poder mostrarlo/usarlo (ITHOS lo necesita para consumir la API).
 * No se borra en el reset de testing. Devuelve la key en claro (o '' si no se puede recuperar).
 */
function api_token_ithos_asegurar(): string
{
    $saved = function_exists('setting_get') ? (string) setting_get('ithos_api_key', '') : '';
    $ex = q("SELECT id FROM api_tokens WHERE nombre = 'ITHOS' LIMIT 1")->fetch();
    if ($ex && $saved !== '') { return $saved; }
    if (!$ex) {
        $r = api_token_create('ITHOS', 0, 'lectura');
        if (function_exists('setting_set')) { setting_set('ithos_api_key', $r['token']); }
        return $r['token'];
    }
    return $saved;   // existe pero no está el claro guardado (hash irreversible)
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
