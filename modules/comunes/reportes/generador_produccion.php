<?php
// Genera informes PDF del área de producción.
require_once __DIR__ . '/../../../config/bootstrap.php';
/**
 * Generador PDF unificado de informes de Producción.
 * Parámetros GET: movimiento, desde, hasta, estado (opcional), sucursal_id (opcional)
 */

require_once PROJECT_ROOT . '/config/database.php';
require_once __DIR__ . '/informes_helper.php';
require_once PROJECT_ROOT . '/reporte/reporte_presupuesto.php';

session_start();
if (empty($_SESSION['username']) || !puedeAccederInformes()) {
    echo "<script>alert('Sin permiso para informes.'); window.location.href='view.php';</script>";
    exit;
}

$movimiento = isset($_GET['movimiento']) ? mb_strtoupper(trim($_GET['movimiento'])) : '';
$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$sucursalId = isset($_GET['sucursal_id']) ? (int)$_GET['sucursal_id'] : 0;

$err = validarParametrosInforme($movimiento, $desde, $hasta, $estado);
if ($err !== null || !puedeInformeMovimiento($movimiento)) {
    echo "<script>alert(" . json_encode($err ?: 'Sin permiso para este informe.') . "); window.location.href='view.php';</script>";
    exit;
}

$estadoUp = normalizarEstadoInforme($estado);
$esTotal = $estadoUp === 'TOTAL' || $estadoUp === '';

function bindFiltroEstado(string $sql, bool $esTotal, string $campo): string
{
    return $esTotal ? $sql : $sql . " AND {$campo} = :estado ";
}

function redirectSinResultados(): void
{
    header('Location: view.php?alert=6');
    exit;
}

function emitirPdf(string $titulo, array $columnas, array $filas, string $nombreArchivo): void
{
    if (empty($filas)) {
        redirectSinResultados();
    }
    $pdf = new BasePDF('L', 'mm', 'A4', ['titulo' => $titulo]);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $anchos = array_fill(0, count($columnas), count($columnas) > 0 ? (270 / count($columnas)) : 30);
    $pdf->tablaEncabezado($anchos, $columnas);
    foreach ($filas as $row) {
        $pdf->tablaFila($anchos, array_map('strval', $row), array_fill(0, count($columnas), 'C'));
    }
    $pdf->pieDocumento();
    $pdf->Output('I', $nombreArchivo);
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $filtroSuc = resolverSucursalInforme($pdo, $sucursalId > 0 ? $sucursalId : null);

    switch ($movimiento) {
        case 'PEDIDO_PRODUCCION':
            $sql = "
                SELECT pp.id_pedido_produccion AS nro, u.username AS usuario,
                       s.descripcion_sucursal AS sucursal, p.producto_descri AS producto,
                       pd.cantidad_pedido AS cantidad, pp.pedido_prod_estado AS estado,
                       to_char(pp.pedido_prod_fecha_emision, 'YYYY-MM-DD') AS fecha
                FROM pedido_produccion pp
                JOIN pedido_detalle_produccion pd ON pd.id_pedido_produccion = pp.id_pedido_produccion
                JOIN productos p ON p.producto_id = pd.producto_id
                JOIN usuarios u ON u.id_usuario = pp.id_usuario
                JOIN sucursales s ON s.id_sucursal = pp.id_sucursal
                WHERE pp.pedido_prod_fecha_emision BETWEEN :desde AND :hasta
            ";
            if ($filtroSuc !== null) {
                $sql .= ' AND pp.id_sucursal = :suc ';
            }
            $sql = bindFiltroEstado($sql, $esTotal, 'pp.pedido_prod_estado') . ' ORDER BY pp.id_pedido_produccion DESC';
            break;

        case 'ORDEN_PRODUCCION':
            $sql = "
                SELECT op.orden_id AS nro, u.username AS usuario,
                       s.descripcion_sucursal AS sucursal, p.producto_descri AS producto,
                       od.orden_prod_cantidad AS cantidad, od.cantidad_pendiente AS pendiente,
                       op.orden_prod_estado AS estado,
                       to_char(op.orden_prod_fecha, 'YYYY-MM-DD') AS fecha
                FROM orden_produccion op
                JOIN orden_detalle_produccion od ON od.orden_id = op.orden_id
                JOIN productos p ON p.producto_id = od.producto_id
                JOIN pedido_produccion pp ON pp.id_pedido_produccion = op.id_pedido_produccion
                JOIN usuarios u ON u.id_usuario = op.id_usuario
                JOIN sucursales s ON s.id_sucursal = pp.id_sucursal
                WHERE op.orden_prod_fecha BETWEEN :desde AND :hasta
            ";
            if ($filtroSuc !== null) {
                $sql .= ' AND pp.id_sucursal = :suc ';
            }
            $sql = bindFiltroEstado($sql, $esTotal, 'op.orden_prod_estado') . ' ORDER BY op.orden_id DESC';
            break;

        case 'PEDIDO_MATERIA':
            $sql = "
                SELECT pm.id_pedido_mat_prod AS nro, u.username AS usuario,
                       s.descripcion_sucursal AS sucursal, d.deposito_descri AS deposito,
                       mp.materia_prima_descripcion AS materia, pd.ped_mat_prod_cantidad AS solicitada,
                       pd.cantidad_repuesta AS repuesta, pm.ped_mat_prod_estado AS estado,
                       to_char(pm.ped_mat_prod_fecha, 'YYYY-MM-DD') AS fecha
                FROM pedido_materia_produccion pm
                JOIN pedido_materia_detalle_produccion pd ON pd.id_pedido_mat_prod = pm.id_pedido_mat_prod
                JOIN materia_prima mp ON mp.id_materia_prima = pd.id_materia_prima
                JOIN deposito d ON d.deposito_id = pm.deposito_id
                JOIN usuarios u ON u.id_usuario = pm.id_usuario
                JOIN sucursales s ON s.id_sucursal = pm.id_sucursal
                WHERE pm.ped_mat_prod_fecha BETWEEN :desde AND :hasta
            ";
            if ($filtroSuc !== null) {
                $sql .= ' AND pm.id_sucursal = :suc ';
            }
            $sql = bindFiltroEstado($sql, $esTotal, 'pm.ped_mat_prod_estado') . ' ORDER BY pm.id_pedido_mat_prod DESC';
            break;

        case 'REPOSICION_MATERIA':
            $sql = "
                SELECT r.reposicion_id AS nro, u.username AS usuario, d.deposito_descri AS deposito,
                       mp.materia_prima_descripcion AS materia, rd.reposicion_cantidad AS cantidad,
                       r.reposicion_estado AS estado, r.id_pedido_mat_prod AS pedido_mp,
                       to_char(r.reposicion_fecha, 'YYYY-MM-DD') AS fecha
                FROM reposicion_materia r
                JOIN reposicion_materia_detalle rd ON rd.reposicion_id = r.reposicion_id
                JOIN materia_prima mp ON mp.id_materia_prima = rd.id_materia_prima
                JOIN deposito d ON d.deposito_id = r.deposito_id
                JOIN usuarios u ON u.id_usuario = r.id_usuario
                WHERE r.reposicion_fecha BETWEEN :desde AND :hasta
            ";
            $sql = bindFiltroEstado($sql, $esTotal, 'r.reposicion_estado') . ' ORDER BY r.reposicion_id DESC';
            break;

        case 'CONTROL_PRODUCCION':
            $sql = "
                SELECT c.control_id AS nro, u.username AS usuario, p.producto_descri AS producto,
                       cd.control_cantidad AS cantidad, c.control_estado AS estado,
                       c.orden_id AS op, to_char(c.control_fecha, 'YYYY-MM-DD') AS fecha
                FROM control_produccion c
                JOIN control_produccion_detalle cd ON cd.control_id = c.control_id AND cd.producto_id = c.producto_id
                JOIN productos p ON p.producto_id = c.producto_id
                JOIN usuarios u ON u.id_usuario = c.id_usuario
                WHERE c.control_fecha BETWEEN :desde AND :hasta
            ";
            $sql = bindFiltroEstado($sql, $esTotal, 'c.control_estado') . ' ORDER BY c.control_id DESC';
            break;

        case 'PRODUCTOS_TERMINADOS':
            $sql = "
                SELECT pt.terminado_id AS nro, pt.orden_id AS op, u.username AS usuario,
                       p.producto_descri AS producto, ptd.terminado_cantidad AS cantidad,
                       to_char(pt.terminado_fecha, 'YYYY-MM-DD') AS fecha
                FROM producto_terminado pt
                JOIN productos_terminados_detalle ptd ON ptd.terminado_id = pt.terminado_id
                JOIN productos p ON p.producto_id = ptd.producto_id
                JOIN usuarios u ON u.id_usuario = pt.id_usuario
                WHERE pt.terminado_fecha BETWEEN :desde AND :hasta
                ORDER BY pt.terminado_id DESC
            ";
            break;

        case 'CONTROL_CALIDAD':
            $sql = "
                SELECT cc.calidad_id AS nro, u.username AS usuario, cc.calidad_estado AS estado,
                       cc.terminado_id AS lote_pt, p.producto_descri AS producto,
                       cd.calidad_cantidad AS cantidad,
                       to_char(cc.calidad_fecha, 'YYYY-MM-DD') AS fecha
                FROM control_calidad_produccion cc
                JOIN control_calidad_detalle cd ON cd.calidad_id = cc.calidad_id
                JOIN productos p ON p.producto_id = cd.producto_id
                JOIN usuarios u ON u.id_usuario = cc.id_usuario
                WHERE cc.calidad_fecha BETWEEN :desde AND :hasta
            ";
            $sql = bindFiltroEstado($sql, $esTotal, 'cc.calidad_estado') . ' ORDER BY cc.calidad_id DESC';
            break;

        case 'PERDIDAS':
            $sql = "
                SELECT pe.perdidas_id AS nro, u.username AS usuario, tp.tipo_perdida_descri AS tipo,
                       p.producto_descri AS producto, pd.perdida_cantidad AS cantidad,
                       pe.perdida_estado AS estado, to_char(pe.perdida_fecha, 'YYYY-MM-DD') AS fecha
                FROM perdidas pe
                JOIN perdidas_detalle pd ON pd.perdidas_id = pe.perdidas_id
                JOIN productos p ON p.producto_id = pd.producto_id
                JOIN tipo_perdida tp ON tp.tipo_perdida_id = pe.tipo_perdida_id
                JOIN usuarios u ON u.id_usuario = pe.id_usuario
                WHERE pe.perdida_fecha BETWEEN :desde AND :hasta
            ";
            $sql = bindFiltroEstado($sql, $esTotal, 'pe.perdida_estado') . ' ORDER BY pe.perdidas_id DESC';
            break;

        default:
            echo "<script>alert('Proceso de producción no implementado.'); window.location.href='view.php';</script>";
            exit;
    }

    $st = $pdo->prepare($sql);
    $st->bindValue(':desde', $desde);
    $st->bindValue(':hasta', $hasta);
    if (!$esTotal && $movimiento !== 'PRODUCTOS_TERMINADOS' && strpos($sql, ':estado') !== false) {
        $st->bindValue(':estado', $estadoUp);
    }
    if ($filtroSuc !== null && strpos($sql, ':suc') !== false) {
        $st->bindValue(':suc', $filtroSuc, PDO::PARAM_INT);
    }
    $st->execute();
    $rows = $st->fetchAll();

    $titulo = 'INFORME — ' . str_replace('_', ' ', $movimiento);
    if (!$esTotal && $estadoUp !== '') {
        $titulo .= ' — ' . $estadoUp;
    }
    $titulo .= " ({$desde} a {$hasta})";

    $columnas = $rows ? array_keys($rows[0]) : [];
    $filas = array_map(static function ($r) {
        return array_values($r);
    }, $rows);

    emitirPdf($titulo, $columnas, $filas, 'Informe_' . $movimiento . '.pdf');
} catch (PDOException $e) {
    echo 'Error al generar informe: ' . htmlspecialchars($e->getMessage());
}
