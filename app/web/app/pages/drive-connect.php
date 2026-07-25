<?php
declare(strict_types=1);

require_admin();

if (!google_enabled()) {
    flash('Falta configurar Google (client_id/secret) para poder conectar Drive.', 'error');
    redirect('drive');
}

$state = 'drive:' . bin2hex(random_bytes(16));
$_SESSION['drive_state'] = $state;
header('Location: ' . drive_auth_url($state));
exit;
