<?php
declare(strict_types=1);

// ============================================================
//  Envío de correo por SMTP (sin dependencias externas).
//  Config en config.php → $CONFIG['mail'] = [
//    'user' => 'ithos@intellihelp.tech', 'pass' => '<app-password>',
//    'from' => 'ithos@intellihelp.tech', 'from_name' => 'INTELLIHELP',
//    'smtp_host' => 'smtp.gmail.com', 'smtp_port' => 465 ];
//  Google Workspace: usar una "contraseña de aplicación" (no la normal).
// ============================================================

// Config de correo: config.php ($CONFIG['mail']) con override por app_settings (mail_*),
// así se puede configurar sin tocar config.php y sin versionar el secreto.
function mail_cfg(): array
{
    $c = (array)($GLOBALS['CONFIG']['mail'] ?? []);
    if (function_exists('setting_get')) {
        foreach (['user', 'pass', 'from', 'from_name', 'smtp_host', 'smtp_port'] as $k) {
            $v = setting_get('mail_' . $k, null);
            if ($v !== null && $v !== '') { $c[$k] = $v; }
        }
    }
    return $c;
}
function mail_configurado(): bool
{
    $c = mail_cfg();
    return !empty($c['user']) && !empty($c['pass']);
}

/**
 * Envía un correo HTML por SMTP (SSL directo, ej. Gmail 465). Devuelve true/false.
 * No lanza: cualquier error queda en $err y se puede auditar.
 */
function mail_enviar(string $to, string $subject, string $bodyHtml, ?string &$err = null): bool
{
    $c = mail_cfg();
    $host = $c['smtp_host'] ?? 'smtp.gmail.com';
    $port = (int)($c['smtp_port'] ?? 465);
    $user = (string)($c['user'] ?? '');
    $pass = (string)($c['pass'] ?? '');
    $from = (string)($c['from'] ?? $user);
    $fromName = (string)($c['from_name'] ?? 'INTELLIHELP');
    if ($user === '' || $pass === '') { $err = 'SMTP no configurado (falta user/pass en config).'; return false; }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { $err = 'Destinatario inválido: ' . $to; return false; }

    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $fp = @stream_socket_client("ssl://{$host}:{$port}", $en, $es, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { $err = "No se pudo conectar a {$host}:{$port} ({$es})."; return false; }
    stream_set_timeout($fp, 15);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') { break; }   // última línea de la respuesta
        }
        return $data;
    };
    $cmd = function (string $c) use ($fp, $read): string { fwrite($fp, $c . "\r\n"); return $read(); };
    $ok = fn(string $r, string $code) => strpos(ltrim($r), $code) === 0;

    $greet = $read();
    if (!$ok($greet, '220')) { $err = 'SMTP saludo: ' . trim($greet); fclose($fp); return false; }
    $cmd('EHLO intellihelp.tech');
    if (!$ok($cmd('AUTH LOGIN'), '334')) { $err = 'SMTP AUTH no aceptado.'; fclose($fp); return false; }
    $cmd(base64_encode($user));
    $rAuth = $cmd(base64_encode($pass));
    if (!$ok($rAuth, '235')) { $err = 'SMTP autenticación falló: ' . trim($rAuth); fclose($fp); return false; }
    if (!$ok($cmd('MAIL FROM:<' . $from . '>'), '250')) { $err = 'SMTP MAIL FROM rechazado.'; fclose($fp); return false; }
    if (!$ok($cmd('RCPT TO:<' . $to . '>'), '250')) { $err = 'SMTP RCPT rechazado.'; fclose($fp); return false; }
    if (!$ok($cmd('DATA'), '354')) { $err = 'SMTP DATA rechazado.'; fclose($fp); return false; }

    $headers = 'From: ' . mail_encode_name($fromName) . ' <' . $from . ">\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . 'Date: ' . date('r') . "\r\n";
    // Escapar líneas que empiecen con "." (dot-stuffing) y cerrar con <CRLF>.<CRLF>.
    $body = preg_replace('/^\./m', '..', $bodyHtml);
    $rSend = $cmd($headers . "\r\n" . $body . "\r\n.");
    $cmd('QUIT');
    fclose($fp);
    if (!$ok($rSend, '250')) { $err = 'SMTP envío rechazado: ' . trim($rSend); return false; }
    return true;
}

function mail_encode_name(string $name): string
{
    return preg_match('/[^\x20-\x7e]/', $name) ? '=?UTF-8?B?' . base64_encode($name) . '?=' : $name;
}
