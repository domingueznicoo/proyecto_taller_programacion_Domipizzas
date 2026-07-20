<?php
// AJAX: cabecera de factura para notas de crédito/débito.
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/modules/compras/helpers/compras_calculos.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isset($_GET['fact_id']) || !ctype_digit($_GET['fact_id'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'fact_id inválido']);
    exit;
}
$fid = (int)$_GET['fact_id'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $st = $pdo->prepare("
        SELECT 
            f.id_factura_compra,
            f.numero_factura,
            f.fac_plazo,
            COALESCE(f.tipo_operacion, 'CONTADO') AS tipo_compra,
            f.fact_fecha_compra::date AS factura_emision,
            f.fac_total AS fac_total_registrado,
            f.fac_estado,
            COALESCE(cp.monto_pendiente, 0)::int AS saldo_pendiente,
            COALESCE(cp.monto_total, 0)::int AS monto_total_cta,
            COALESCE(cp.estado, '') AS cta_estado,
            pr.id_proveedor,
            pr.razon_social AS proveedor,
            pr.ruc_proveedor AS ruc
        FROM factura_compra f
        JOIN orden_de_compra oc ON oc.id_orden_compra = f.id_orden_compra
        JOIN proveedor pr ON pr.id_proveedor = oc.id_proveedor
        LEFT JOIN cuentas_pagar cp ON cp.id_factura_compra = f.id_factura_compra
        WHERE f.id_factura_compra = :id
        LIMIT 1
    ");
    $st->execute([':id' => $fid]);
    $row = $st->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'msg' => 'No existe la factura']);
        exit;
    }

    $calc = compras_calcular_total_factura_neto($pdo, $fid);
    $facTotalNeto = (int)$calc['total'];
    $row['fac_total'] = $facTotalNeto;
    $row['fac_total_neto'] = $facTotalNeto;

    $saldo = (int)$row['saldo_pendiente'];
    $row['monto_acreditable'] = $saldo > 0 ? $saldo : $facTotalNeto;
    $row['permite_nota'] = !(
        $saldo <= 0
        && in_array(strtoupper((string)$row['cta_estado']), ['FINALIZADO', 'PAGADO', 'PAGADO TOTAL'], true)
    );

    echo json_encode($row, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
