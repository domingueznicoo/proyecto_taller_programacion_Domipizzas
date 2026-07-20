<?php
// Genera informe PDF de pedidos de producción.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/pedido_ventas.php';

if (!isset($_GET['ped_id']) || !is_numeric($_GET['ped_id'])) {
    die('ID de pedido inválido.');
}

$pedId = (int)$_GET['ped_id'];
$pdf = new BasePDF('P', 'mm', 'A4', [
    'titulo'    => 'Pedido de Producción',
    'subtitulo' => 'Detalle del pedido N° ' . $pedId,
]);
$pdf->AliasNbPages();
$pdf->AddPage();

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stmtGeneral = $pdo->prepare("
        SELECT
            pp.id_pedido_produccion,
            to_char(pp.pedido_prod_fecha_emision, 'YYYY-MM-DD') AS pedido_fecha,
            pp.pedido_prod_estado,
            tp.tipo_pedido_descri,
            us.username AS usuario,
            suc.descripcion_sucursal AS sucursal,
            pp.pedido_prod_observaciones
        FROM pedido_produccion pp
        JOIN sucursales suc ON pp.id_sucursal = suc.id_sucursal
        JOIN usuarios us ON pp.id_usuario = us.id_usuario
        JOIN tipo_pedido tp ON tp.id_tipo_pedido = pp.id_tipo_pedido
        WHERE pp.id_pedido_produccion = :ped_id
        LIMIT 1
    ");
    $stmtGeneral->execute([':ped_id' => $pedId]);
    $general = $stmtGeneral->fetch(PDO::FETCH_ASSOC);

    if ($general) {
        $pdf->seccionTitulo('Datos del pedido');
        $pdf->filaDato('Pedido N°:', (string)$general['id_pedido_produccion'], 38);
        $pdf->filaDato('Fecha:', $general['pedido_fecha'], 38);
        $pdf->filaDato('Estado:', $general['pedido_prod_estado'], 38);
        $pdf->filaDato('Tipo:', $general['tipo_pedido_descri'], 38);
        $pdf->filaDato('Usuario:', $general['usuario'], 38);
        $pdf->filaDato('Sucursal:', $general['sucursal'], 38);
        if (!empty($general['pedido_prod_observaciones'])) {
            $pdf->filaDato('Obs.:', $general['pedido_prod_observaciones'], 38);
        }
        $pdf->Ln(4);
    }

    $stmtDetails = $pdo->prepare("
        SELECT p.producto_descri AS producto, d.cantidad_pedido AS cantidad
        FROM pedido_detalle_produccion d
        JOIN productos p ON p.producto_id = d.producto_id
        WHERE d.id_pedido_produccion = :ped_id
        ORDER BY p.producto_descri
    ");
    $stmtDetails->execute([':ped_id' => $pedId]);

    $pdf->seccionTitulo('Detalle de productos');
    if ($stmtDetails->rowCount() > 0) {
        $anchos = [120, 60];
        $pdf->tablaEncabezado($anchos, ['Producto', 'Cantidad'], ['L', 'C']);
        while ($row = $stmtDetails->fetch(PDO::FETCH_ASSOC)) {
            $pdf->tablaFila($anchos, [$row['producto'], $row['cantidad']], ['L', 'C']);
        }
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 10, convertir('Sin detalle de productos.'), 0, 1, 'C');
    }

    $pdf->pieDocumento();
    $pdf->Output('I', "Pedido_Produccion_{$pedId}.pdf");
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}
