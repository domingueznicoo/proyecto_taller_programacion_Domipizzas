<?php
// Funciones compartidas del módulo informes.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/permissions.php';
require_once PROJECT_ROOT . '/config/app_modules.php';

function puedeAccederInformes(): bool
{
    return has_permission('REPORTES_GENERALES', false)
        || has_permission('REPORTES_COMPRAS', false)
        || has_permission('REPORTES_PRODUCCION', false);
}

function puedeInformesCompras(): bool
{
    return has_permission('REPORTES_GENERALES', false) || has_permission('REPORTES_COMPRAS', false);
}

function puedeInformesProduccion(): bool
{
    return UI_MODULO_PRODUCCION && (
        has_permission('REPORTES_GENERALES', false) || has_permission('REPORTES_PRODUCCION', false)
    );
}

function puedeInformeMovimiento(string $movimiento): bool
{
    $mov = mb_strtoupper(trim($movimiento));
    $compras = ['PEDIDO', 'PRESUPUESTO', 'ORDEN_COMPRA', 'FACTURA', 'NOTA_CREDITO', 'NOTA_DEBITO', 'NOTA_REMISION', 'AJUSTES', 'LIBRO_COMPRAS'];
    $produccion = [
        'PEDIDO_PRODUCCION', 'ORDEN_PRODUCCION', 'PEDIDO_MATERIA', 'REPOSICION_MATERIA',
        'CONTROL_PRODUCCION', 'PRODUCTOS_TERMINADOS', 'CONTROL_CALIDAD', 'PERDIDAS',
    ];

    if (in_array($mov, $compras, true)) {
        return puedeInformesCompras();
    }
    if (in_array($mov, $produccion, true)) {
        return puedeInformesProduccion();
    }
    return false;
}

/** Estados por movimiento (Compras + Producción). */
function estadosPorMovimientoInforme(): array
{
    return [
        // Compras
        'PEDIDO' => ['REPORTE TOTAL', 'PENDIENTE', 'APROBADO', 'ANULADO', 'FINALIZADO'],
        'PRESUPUESTO' => ['REPORTE TOTAL', 'PENDIENTE', 'APROBADO', 'ANULADO', 'FINALIZADO'],
        'ORDEN_COMPRA' => ['REPORTE TOTAL', 'EMITIDA', 'FACTURADA', 'FINALIZADO', 'ANULADO'],
        'FACTURA' => ['REPORTE TOTAL', 'PENDIENTE', 'EMITIDA', 'APROBADO', 'ANULADO'],
        'NOTA_CREDITO' => ['REPORTE TOTAL', 'PENDIENTE', 'EMITIDA', 'APROBADO', 'ANULADO'],
        'NOTA_DEBITO' => ['REPORTE TOTAL', 'PENDIENTE', 'EMITIDA', 'APROBADO', 'ANULADO'],
        'NOTA_REMISION' => ['REPORTE TOTAL', 'PENDIENTE', 'EMITIDA', 'FINALIZADO', 'ANULADO'],
        'AJUSTES' => ['REPORTE TOTAL', 'APROBADO', 'ANULADO'],
        'LIBRO_COMPRAS' => [],
        // Producción
        'PEDIDO_PRODUCCION' => ['REPORTE TOTAL', 'PENDIENTE', 'ASIGNADO', 'ANULADO'],
        'ORDEN_PRODUCCION' => ['REPORTE TOTAL', 'PENDIENTE', 'EN_PROCESO', 'TERMINADA', 'ANULADA'],
        'PEDIDO_MATERIA' => ['REPORTE TOTAL', 'PENDIENTE', 'PARCIAL', 'COMPLETADO', 'ANULADO'],
        'REPOSICION_MATERIA' => ['REPORTE TOTAL', 'REGISTRADO', 'ANULADO'],
        'CONTROL_PRODUCCION' => ['REPORTE TOTAL', 'REGISTRADO', 'ANULADO'],
        'PRODUCTOS_TERMINADOS' => [],
        'CONTROL_CALIDAD' => ['REPORTE TOTAL', 'APROBADO', 'NO CONFORME', 'ANULADO'],
        'PERDIDAS' => ['REPORTE TOTAL', 'REGISTRADO', 'ANULADO'],
    ];
}

function movimientoRequiereEstado(string $movimiento): bool
{
    $mov = mb_strtoupper(trim($movimiento));
    $sinEstado = ['LIBRO_COMPRAS', 'PRODUCTOS_TERMINADOS'];
    return !in_array($mov, $sinEstado, true);
}

function normalizarEstadoInforme(string $estado): string
{
    $e = mb_strtoupper(trim($estado));
    return in_array($e, ['REPORTE TOTAL', 'TOTAL'], true) ? 'TOTAL' : $e;
}

function validarParametrosInforme(string $movimiento, string $desde, string $hasta, string $estado = ''): ?string
{
    $mov = mb_strtoupper(trim($movimiento));
    if ($mov === '' || $desde === '' || $hasta === '') {
        return 'Faltan parámetros obligatorios: movimiento y rango de fechas.';
    }
    $reFecha = '/^\d{4}-\d{2}-\d{2}$/';
    if (!preg_match($reFecha, $desde) || !preg_match($reFecha, $hasta)) {
        return 'Formato de fecha inválido. Use YYYY-MM-DD.';
    }
    if ($hasta < $desde) {
        return 'La fecha Hasta no puede ser anterior a Desde.';
    }
    if (!puedeInformeMovimiento($mov)) {
        return 'Proceso no reconocido o sin permiso.';
    }
    if (movimientoRequiereEstado($mov) && trim($estado) === '') {
        return 'Debe seleccionar un estado para el movimiento elegido.';
    }
    return null;
}

function resolverSucursalInforme(PDO $pdo, ?int $sucursalFiltro): ?int
{
    $sesionSuc = (int)($_SESSION['id_sucursal'] ?? $_SESSION['sucursal_id'] ?? 0);
    if ($sesionSuc === 0) {
        $st = $pdo->prepare('SELECT id_sucursal FROM usuarios WHERE username = :u LIMIT 1');
        $st->execute([':u' => $_SESSION['username'] ?? '']);
        $sesionSuc = (int)$st->fetchColumn();
    }
    if (has_permission('REPORTES_GENERALES', false) || has_permission('ADMINISTRACION', false)) {
        return ($sucursalFiltro !== null && $sucursalFiltro > 0) ? $sucursalFiltro : null;
    }
    return $sesionSuc > 0 ? $sesionSuc : null;
}
