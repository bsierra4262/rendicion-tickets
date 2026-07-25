<?php
declare(strict_types=1);
require_login();

$autoload = '/home/u566451014/tickets_vendor/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit('El generador de PDF no está disponible.');
}
require $autoload;

$periodo = $_GET['periodo'] ?? '';
$moneda  = ($_GET['moneda'] ?? 'ARS') === 'USD' ? 'USD' : 'ARS';

$resumen = rep_resumen($periodo, $moneda);
$porCat  = rep_por_categoria($periodo, $moneda);
$porMes  = array_reverse(rep_por_mes($moneda));
$topProv = rep_top_proveedores($periodo, $moneda, 12);
$logo    = base64_encode((string) @file_get_contents(WEBROOT . '/assets/img/ITH-logo-white.png'));

ob_start();
include APP . '/views/pdf/reporte.php';
$html = ob_get_clean();

$options = new \Dompdf\Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Pie de página con paginado en todas las hojas
$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
$canvas->page_text(40, 812, 'INTELLIHELP S.A. · Rendición de Tickets', $font, 8, [0.44, 0.42, 0.49]);
$canvas->page_text(470, 812, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 8, [0.44, 0.42, 0.49]);

$pdf = $dompdf->output();
$fn = 'reporte-tickets-' . date('Ymd-His') . '.pdf';
// Sin cache: Hostinger/CDN debe regenerar siempre (nunca servir uno viejo).
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $fn . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
echo $pdf;
exit;
