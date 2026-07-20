<?php
// Genera informe PDF de órdenes de producción.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/pedido_ventas.php';

if (!isset($_GET['orden_id']) || !is_numeric($_GET['orden_id'])) {
    die('ID de orden inválido.');
}

$ordenId = (int)$_GET['orden_id'];
$pdf = new BasePDF('P', 'mm', 'A4', [
    'titulo'    => 'Orden de Producción',
    'subtitulo' => 'Detalle de la orden N° ' . $ordenId,
]);
$pdf->AliasNbPages();
$pdf->AddPage();

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stmtGeneral = $pdo->prepare("
        SELECT
            op.orden_id,
            to_char(op.orden_prod_fecha, 'YYYY-MM-DD') AS fecha_emision,
            to_char(op.orden_prod_fecha_entrega, 'YYYY-MM-DD') AS fecha_entrega,
            op.orden_prod_estado,
            op.id_pedido_produccion,
            us.username AS usuario,
            tp.tipo_pedido_descri
        FROM orden_produccion op
        JOIN pedido_produccion pp ON pp.id_pedido_produccion = op.id_pedido_produccion
        JOIN tipo_pedido tp ON tp.id_tipo_pedido = pp.id_tipo_pedido
        JOIN usuarios us ON us.id_usuario = op.id_usuario
        WHERE op.orden_id = :id
        LIMIT 1
    ");
    $stmtGeneral->execute([':id' => $ordenId]);
    $general = $stmtGeneral->fetch(PDO::FETCH_ASSOC);

    if ($general) {
        $pdf->seccionTitulo('Datos de la orden');
        $pdf->filaDato('Orden N°:', (string)$general['orden_id'], 38);
        $pdf->filaDato('Fecha emisión:', $general['fecha_emision'], 38);
        $pdf->filaDato('Entrega prevista:', $general['fecha_entrega'], 38);
        $pdf->filaDato('Estado:', $general['orden_prod_estado'], 38);
        $pdf->filaDato('Pedido N°:', (string)$general['id_pedido_produccion'], 38);
        $pdf->filaDato('Tipo pedido:', $general['tipo_pedido_descri'], 38);
        $pdf->filaDato('Usuario:', $general['usuario'], 38);
        $pdf->Ln(4);
    }

    $stmtDetails = $pdo->prepare("
        SELECT p.producto_descri AS producto, d.orden_prod_cantidad AS cantidad, d.cantidad_pendiente
        FROM orden_detalle_produccion d
        JOIN productos p ON p.producto_id = d.producto_id
        WHERE d.orden_id = :id
        ORDER BY p.producto_descri
    ");
    $stmtDetails->execute([':id' => $ordenId]);

    $pdf->seccionTitulo('Detalle de productos');
    if ($stmtDetails->rowCount() > 0) {
        $anchos = [90, 45, 45];
        $pdf->tablaEncabezado($anchos, ['Producto', 'Cantidad', 'Pendiente'], ['L', 'C', 'C']);
        while ($row = $stmtDetails->fetch(PDO::FETCH_ASSOC)) {
            $pdf->tablaFila($anchos, [$row['producto'], $row['cantidad'], $row['cantidad_pendiente']], ['L', 'C', 'C']);
        }
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 10, convertir('Sin detalle de productos.'), 0, 1, 'C');
    }

    $pdf->pieDocumento();
    $pdf->Output('I', "Orden_Produccion_{$ordenId}.pdf");
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}
