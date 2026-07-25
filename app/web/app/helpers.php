<?php
declare(strict_types=1);

// ============================================================
//  Helpers generales
// ============================================================

/** Escapa para HTML. */
function e($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Construye una URL dentro de la app. */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return (BASE_PATH ?: '') . '/' . $path;
}

/** Ruta actual (desde el front controller). */
function current_route(): string
{
    $r = $_GET['r'] ?? '';
    if ($r === '' && isset($_SERVER['PATH_INFO'])) {
        $r = $_SERVER['PATH_INFO'];
    }
    return trim((string)$r, '/');
}

/** Redirige a una ruta interna y corta la ejecución. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Método HTTP actual. */
function method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function is_post(): bool
{
    return method() === 'POST';
}

/**
 * IP real del cliente, considerando proxies (LiteSpeed/Cloudflare/etc.).
 * `REMOTE_ADDR` en hosting compartido suele ser el proxy; el cliente real
 * viaja en X-Forwarded-For / X-Real-IP / CF-Connecting-IP.
 */
function client_ip(): ?string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $h) {
        if (empty($_SERVER[$h])) { continue; }
        foreach (explode(',', (string)$_SERVER[$h]) as $ip) {
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) { return $ip; }
        }
    }
    return null;
}

// ---- CSRF ----
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $ok = isset($_POST['_csrf'], $_SESSION['_csrf'])
        && hash_equals($_SESSION['_csrf'], (string)$_POST['_csrf']);
    if (!$ok) {
        http_response_code(419);
        exit('Token de seguridad inválido. Volvé atrás y reintentá.');
    }
}

// ---- Flash messages ----
function flash(string $msg, string $type = 'info'): void
{
    $_SESSION['_flash'][] = ['msg' => $msg, 'type' => $type];
}

function take_flash(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

// ---- Render de vistas ----
function render(string $view, array $vars = [], string $title = ''): void
{
    $title = $title !== '' ? $title : ($GLOBALS['CONFIG']['app']['name'] ?? 'App');
    extract($vars, EXTR_SKIP);
    ob_start();
    include APP . '/views/' . $view . '.php';
    $content = ob_get_clean();
    include APP . '/views/layout.php';
}

// ============================================================
//  Dominio fiscal argentino
// ============================================================

/** Deja solo dígitos. */
function normalizar_cuit($s): string
{
    return preg_replace('/\D/', '', (string)$s) ?? '';
}

/** Valida CUIT/CUIL por dígito verificador (módulo 11). */
function validar_cuit($s): bool
{
    $c = normalizar_cuit($s);
    if (strlen($c) !== 11) {
        return false;
    }
    $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $sum = 0;
    for ($i = 0; $i < 10; $i++) {
        $sum += ((int)$c[$i]) * $pesos[$i];
    }
    $dv = 11 - ($sum % 11);
    if ($dv === 11) { $dv = 0; }
    if ($dv === 10) { return false; }
    return $dv === (int)$c[10];
}

/** Formatea CUIT como NN-NNNNNNNN-N. */
function formatear_cuit($s): string
{
    $c = normalizar_cuit($s);
    if (strlen($c) !== 11) {
        return (string)$s;
    }
    return substr($c, 0, 2) . '-' . substr($c, 2, 8) . '-' . substr($c, 10, 1);
}

/**
 * Convierte un texto de importe a float. Soporta formato AR ("1.234,56"),
 * US ("1,234.56"), y números planos. Regla: el separador decimal es el que
 * aparece más a la derecha cuando hay punto y coma.
 */
function parse_importe($s): float
{
    if (is_int($s) || is_float($s)) {
        return (float)$s;
    }
    $s = preg_replace('/[^\d.,\-]/', '', (string)$s);
    if ($s === '' || $s === null) {
        return 0.0;
    }
    $hasComma = strpos($s, ',') !== false;
    $hasDot   = strpos($s, '.') !== false;
    if ($hasComma && $hasDot) {
        if (strrpos($s, ',') > strrpos($s, '.')) {
            $s = str_replace('.', '', $s);   // AR: 1.234,56
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);   // US: 1,234.56
        }
    } elseif ($hasComma) {
        $s = str_replace(',', '.', $s);      // solo coma -> decimal
    }
    // solo punto o sin separador -> tal cual
    return (float)$s;
}

/** Formatea un importe con símbolo de moneda (formato argentino). */
function money($n, string $cur = 'ARS'): string
{
    $sym = $cur === 'USD' ? 'US$' : '$';
    return $sym . ' ' . number_format((float)$n, 2, ',', '.');
}

/** Tasas de IVA vigentes en Argentina. */
function tasas_iva(): array
{
    return [0, 10.5, 21, 27];
}

/** Tipos de comprobante soportados. */
function tipos_comprobante(): array
{
    return ['Fact A', 'Fact B', 'Fact C', 'TK', 'NC A', 'NC B', 'NC C', 'ND A', 'ND B', 'ND C'];
}

/** Devuelve el YYYY-MM de una fecha (Y-m-d). */
function periodo_de(string $fecha): string
{
    return substr($fecha, 0, 7);
}
