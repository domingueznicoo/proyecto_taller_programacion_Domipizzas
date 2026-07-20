<?php
// AJAX: factura detalle de notas de crédito/débito (con descuento del presupuesto).
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
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stOc = $pdo->prepare("SELECT id_orden_compra FROM factura_compra WHERE id_factura_compra = :id LIMIT 1");
    $stOc->execute([':id' => $fid]);
    $idOc = (int)$stOc->fetchColumn();
    $mapa = $idOc > 0 ? compras_cargar_mapa_descuentos_oc($pdo, $idOc) : [];

    $st = $pdo->prepare("
        SELECT 
            d.id_materia_prima,
            mp.materia_prima_descripcion AS producto,
            d.fac_cantidad               AS cantidad,
            d.fac_precio                 AS precio,
            ti.iva_descri                AS iva_descri
        FROM factura_detalle_compra d
        JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
        LEFT JOIN tipo_iva ti ON ti.iva_id = mp.iva_id
        WHERE d.id_factura_compra = :id
        ORDER BY mp.materia_prima_descripcion
    ");
    $st->execute([':id' => $fid]);

    $items = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $mpId = (int)$row['id_materia_prima'];
        $cant = (int)$row['cantidad'];
        $prec = (int)$row['precio'];
        $ocItem = $mapa[$mpId] ?? ['cant_oc' => $cant, 'precio' => $prec, 'descuento' => 0.0];
        $desc = compras_descuento_aplicado($ocItem, $cant);
        $sub = compras_subtotal_linea($cant, $prec, $ocItem);
        $items[] = [
            'id_materia_prima' => $mpId,
            'producto' => $row['producto'],
            'cantidad' => $cant,
            'precio' => $prec,
            'descuento' => $desc,
            'subtotal' => $sub,
            'iva_descri' => $row['iva_descri'],
        ];
    }

    echo json_encode($items, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
