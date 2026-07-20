<?php
// Enruta la generación de informes por módulo.
require_once __DIR__ . '/../../../config/bootstrap.php';
/**
 * Router de Informes — Compras y Producción.
 * Recibe: movimiento, estado, desde, hasta (GET) y redirige al generador correspondiente.
 */

session_start();

require_once PROJECT_ROOT . '/config/database.php';
require_once __DIR__ . '/informes_helper.php';

function failInforme(string $msg, string $back = 'view.php'): void
{
    echo '<script>alert(' . json_encode($msg) . '); window.location.href=' . json_encode($back) . ';</script>';
    exit;
}

if (empty($_SESSION['username']) || !puedeAccederInformes()) {
    failInforme('Sin permiso para acceder a informes.');
}

$movimiento = isset($_GET['movimiento']) ? mb_strtoupper(trim($_GET['movimiento'])) : '';
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

$err = validarParametrosInforme($movimiento, $desde, $hasta, $estado);
if ($err !== null) {
    failInforme($err);
}

$destinos = [
    // Compras
    'PEDIDO' => '../../compras/pedido_compra/generar_informe.php',
    'PRESUPUESTO' => '../../compras/presupuesto/generar_informe.php',
    'ORDEN_COMPRA' => '../../compras/orden_compra/generar_informe.php',
    'FACTURA' => '../../compras/gestionar_compras/generar_informe.php',
    'NOTA_CREDITO' => '../../compras/nota_credito/generar_informe.php',
    'NOTA_DEBITO' => '../../compras/nota_credito/generar_informe.php',
    'NOTA_REMISION' => '../../compras/nota_remision/generar_informe.php',
    'AJUSTES' => '../../compras/ajustes/generar_informe.php',
    'LIBRO_COMPRAS' => 'libro_compras.php',
    // Producción
    'PEDIDO_PRODUCCION' => 'generador_produccion.php',
    'ORDEN_PRODUCCION' => 'generador_produccion.php',
    'PEDIDO_MATERIA' => 'generador_produccion.php',
    'REPOSICION_MATERIA' => 'generador_produccion.php',
    'CONTROL_PRODUCCION' => 'generador_produccion.php',
    'PRODUCTOS_TERMINADOS' => 'generador_produccion.php',
    'CONTROL_CALIDAD' => 'generador_produccion.php',
    'PERDIDAS' => 'generador_produccion.php',
];

if (!isset($destinos[$movimiento])) {
    failInforme('Movimiento no reconocido.');
}

$estadoUp = normalizarEstadoInforme($estado);

$params = [
    'desde' => $desde,
    'hasta' => $hasta,
];
if (movimientoRequiereEstado($movimiento) && $estadoUp !== '') {
    $params['estado'] = $estadoUp;
}
$destinosProduccion = [
    'PEDIDO_PRODUCCION', 'ORDEN_PRODUCCION', 'PEDIDO_MATERIA', 'REPOSICION_MATERIA',
    'CONTROL_PRODUCCION', 'PRODUCTOS_TERMINADOS', 'CONTROL_CALIDAD', 'PERDIDAS',
];
if (in_array($movimiento, $destinosProduccion, true)) {
    $params['movimiento'] = $movimiento;
}
if ($movimiento === 'NOTA_CREDITO') {
    $params['tipo'] = 'CREDITO';
} elseif ($movimiento === 'NOTA_DEBITO') {
    $params['tipo'] = 'DEBITO';
}

header('Location: ' . $destinos[$movimiento] . '?' . http_build_query($params));
exit;
