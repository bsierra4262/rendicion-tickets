<?php
declare(strict_types=1);

// ============================================================
//  Google Workspace OAuth 2.0 (Authorization Code, server-side)
// ============================================================

function google_cfg(): array
{
    return $GLOBALS['CONFIG']['google'] ?? [];
}

function google_enabled(): bool
{
    $g = google_cfg();
    return !empty($g['client_id']) && !empty($g['client_secret']);
}

/**
 * URL absoluta de callback (debe coincidir EXACTA con la registrada en Google).
 * El sitio se sirve siempre por HTTPS; forzamos el esquema para evitar
 * "redirect_uri_mismatch" si el origen detrás del CDN reporta http.
 */
function google_redirect_uri(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'intellihelp.tech';
    return 'https://' . $host . url('google-callback');
}

/** URL a la que enviamos al usuario para autenticarse. */
function google_auth_url(string $state): string
{
    $g = google_cfg();
    $scope  = 'openid email profile';
    $params = [
        'client_id'     => $g['client_id'],
        'redirect_uri'  => google_redirect_uri(),
        'response_type' => 'code',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ];
    // Portal único: mientras Drive no esté conectado, el propio login pide el
    // permiso de Drive (acceso offline) y así queda conectado en un solo paso,
    // sin un botón/portal aparte. Una vez conectado, el login vuelve a ser simple.
    if (function_exists('drive_conectado') && !drive_conectado()) {
        $scope .= ' ' . (defined('DRIVE_SCOPE') ? DRIVE_SCOPE : 'https://www.googleapis.com/auth/drive');
        $params['access_type']            = 'offline';
        $params['prompt']                 = 'consent select_account';
        $params['include_granted_scopes'] = 'true';
    }
    $params['scope'] = $scope;
    if (!empty($g['allowed_domain'])) {
        $params['hd'] = $g['allowed_domain'];
    }
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/** Intercambia el "code" por tokens. Devuelve el array de respuesta de Google. */
function google_exchange_code(string $code): array
{
    $g = google_cfg();
    $body = http_build_query([
        'code'          => $code,
        'client_id'     => $g['client_id'],
        'client_secret' => $g['client_secret'],
        'redirect_uri'  => google_redirect_uri(),
        'grant_type'    => 'authorization_code',
    ]);
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return is_string($res) ? (json_decode($res, true) ?: []) : [];
}

/** Decodifica el payload de un id_token (JWT) recibido directo de Google por TLS. */
function google_decode_id_token(string $idToken): array
{
    $parts = explode('.', $idToken);
    if (count($parts) < 2) {
        return [];
    }
    $payload = strtr($parts[1], '-_', '+/');
    $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
    $json = base64_decode($payload, true);
    return $json ? (json_decode($json, true) ?: []) : [];
}
