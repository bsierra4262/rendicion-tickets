<?php
declare(strict_types=1);
// API de alertas — la consume ITHOS (Jarvis) para reenviar al grupo de WhatsApp.
// Auth por API-key (X-API-Key / Authorization: Bearer / ?api_key=). Polling incremental:
//   GET api/alertas?desde=<ultimo_id>  → alertas abiertas nuevas (con [TEST] si testing).
api_require();

$desde = (int)($_GET['desde'] ?? 0);
api_json([
    'ok'      => true,
    'entorno' => alertas_entorno(),
    'test'    => alertas_en_testing(),
    'alertas' => alertas_api($desde),
]);
