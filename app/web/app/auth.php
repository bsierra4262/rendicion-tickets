<?php
declare(strict_types=1);

/** Devuelve el usuario autenticado (array) o null. */
function auth_user(): ?array
{
    if (empty($_SESSION['uid'])) {
        return null;
    }
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $st = q('SELECT id, nombre, email, rol, activo, avatar_url FROM usuarios WHERE id = ? LIMIT 1', [$_SESSION['uid']]);
    $u = $st->fetch();
    if (!$u || (int)$u['activo'] !== 1) {
        auth_logout();
        return null;
    }
    return $cache = $u;
}

function auth_is_admin(): bool
{
    $u = auth_user();
    return $u && $u['rol'] === 'admin';
}

function current_user_id(): ?int
{
    return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
}

/** Intenta iniciar sesión. Devuelve true si las credenciales son válidas. */
function auth_login(string $email, string $password): bool
{
    $email = strtolower(trim($email));
    $st = q('SELECT id, password_hash, activo FROM usuarios WHERE email = ? LIMIT 1', [$email]);
    $u = $st->fetch();
    if (!$u || (int)$u['activo'] !== 1) {
        return false;
    }
    if (!password_verify($password, $u['password_hash'])) {
        return false;
    }
    // Rehash si cambió el algoritmo/coste
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE usuarios SET password_hash = ? WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    q('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?', [$u['id']]);
    return true;
}

function auth_logout(): void
{
    $_SESSION = [];
    if (PHP_SAPI !== 'cli' && ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/** Busca un usuario por email (o null). */
function find_user_by_email(string $email): ?array
{
    $st = q('SELECT id, nombre, email, rol, activo FROM usuarios WHERE email = ? LIMIT 1',
        [strtolower(trim($email))]);
    return $st->fetch() ?: null;
}

/** Inicia sesión para un id de usuario ya validado (usado por Google OAuth). */
function auth_login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    q('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?', [$id]);
    if (function_exists('log_app')) {
        log_app('sesion.ingreso', ['tipo' => 'auth', 'usuario_id' => $id, 'detalle' => 'Ingreso con Google Workspace']);
    }
}

/** Crea un usuario (sin contraseña utilizable) para acceso vía Google. */
function provision_user(string $email, string $nombre, string $rol = 'carga'): int
{
    $randomHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    q('INSERT INTO usuarios (nombre, email, password_hash, rol, activo) VALUES (?, ?, ?, ?, 1)',
        [$nombre !== '' ? $nombre : $email, strtolower(trim($email)), $randomHash, $rol]);
    return (int) db()->lastInsertId();
}

/** Lista todos los usuarios (admin). */
function users_all(): array
{
    return q('SELECT id, nombre, email, rol, activo, avatar_url, ultimo_acceso, created_at FROM usuarios ORDER BY rol, nombre')->fetchAll();
}

/** Actualiza rol y estado de un usuario. */
function user_update(int $id, string $rol, bool $activo): void
{
    $rol = $rol === 'admin' ? 'admin' : 'carga';
    q('UPDATE usuarios SET rol = ?, activo = ? WHERE id = ?', [$rol, $activo ? 1 : 0, $id]);
    if (function_exists('log_app')) {
        log_app('usuario.actualizar', ['entidad' => 'usuario', 'entidad_id' => $id,
            'detalle' => 'rol=' . $rol . ' · ' . ($activo ? 'activo' : 'inactivo')]);
    }
}

/** Cantidad de administradores activos (para no quedarse sin ninguno). */
function admins_activos(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM usuarios WHERE rol='admin' AND activo=1")->fetchColumn();
}

function require_login(): void
{
    if (!auth_user()) {
        redirect('login');
    }
}

function require_admin(): void
{
    require_login();
    if (!auth_is_admin()) {
        http_response_code(403);
        exit('Acceso denegado: se requiere rol administrador.');
    }
}
