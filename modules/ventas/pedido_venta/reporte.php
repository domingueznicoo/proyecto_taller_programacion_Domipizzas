<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/pedido_ventas.php'; // Clase para generar PDF

// Verificar si se recibió el parámetro 'ped_id'
if (!isset($_GET['ped_id']) || empty($_GET['ped_id'])) {
    die("No se proporcionó un ID de pedido válido.");
}

$ped_id = intval($_GET['ped_id']);

$pdf = new BasePDF('P', 'mm', 'A4', [
    'titulo'    => 'Pedido de Venta',
    'subtitulo' => 'Detalle del pedido N° ' . $ped_id,
]);
$pdf->AliasNbPages();
$pdf->AddPage();

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para obtener la información general del pedido
    $queryGeneral = "
        SELECT 
            pv.id_pedido_venta,
            to_char(pv.pedido_fecha, 'YYYY-MM-DD') AS pedido_fecha,
            pv.pedido_estado, 
            c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre,
            c.cliente_ruc,
            us.username AS usuario, 
            suc.descripcion_sucursal AS sucursal
        FROM 
            pedido_venta pv
        JOIN 
            clientes c ON pv.id_cliente = c.id_cliente
        JOIN 
            sucursales suc ON pv.id_sucursal = suc.id_sucursal
        JOIN 
            usuarios us ON pv.id_usuario = us.id_usuario
        WHERE 
            pv.id_pedido_venta = :ped_id
        LIMIT 1
    ";

    $stmtGeneral = $pdo->prepare($queryGeneral);
    $stmtGeneral->bindParam(':ped_id', $ped_id, PDO::PARAM_INT);
    $stmtGeneral->execute();
    $general = $stmtGeneral->fetch(PDO::FETCH_ASSOC);

    if ($general) {
        $pdf->seccionTitulo('Datos del pedido');
        $pdf->filaDato('Pedido N°:', (string)$general['id_pedido_venta'], 38);
        $pdf->filaDato('Fecha:', $general['pedido_fecha'], 38);
        $pdf->filaDato('Cliente:', $general['cliente_nombre'], 38);
        $pdf->filaDato('RUC:', $general['cliente_ruc'], 38);
        $pdf->filaDato('Estado:', $general['pedido_estado'], 38);
        $pdf->filaDato('Usuario:', $general['usuario'], 38);
        $pdf->filaDato('Sucursal:', $general['sucursal'], 38);
        $pdf->Ln(4);
    } else {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'No se encontraron datos generales para este pedido.', 0, 1, 'C');
    }

    // Consulta para obtener los detalles del pedido
    $queryDetails = "
        SELECT 
            p.producto_descri AS producto, 
            d.cantidad_pedido AS cantidad,
            p.producto_precio AS precio_unitario,
            d.pedido_precio_total AS subtotal
        FROM 
            detalle_pedido_venta d
        JOIN 
            productos p ON d.producto_id = p.producto_id
        WHERE 
            d.id_pedido_venta = :ped_id
        ORDER BY 
            p.producto_descri
    ";

    $stmtDetails = $pdo->prepare($queryDetails);
    $stmtDetails->bindParam(':ped_id', $ped_id, PDO::PARAM_INT);
    $stmtDetails->execute();

    if ($stmtDetails->rowCount() > 0) {
        $anchos = [80, 30, 30, 40];
        $aligns = ['L', 'C', 'R', 'R'];
        $pdf->seccionTitulo('Detalle de productos');
        $pdf->tablaEncabezado($anchos, ['Producto', 'Cantidad', 'Precio Unit.', 'Subtotal'], $aligns);

        $total = 0;
        while ($row = $stmtDetails->fetch(PDO::FETCH_ASSOC)) {
            $pdf->tablaFila($anchos, [
                $row['producto'],
                $row['cantidad'],
                number_format($row['precio_unitario'], 0, ',', '.'),
                number_format($row['subtotal'], 0, ',', '.'),
            ], $aligns);
            $total += $row['subtotal'];
        }

        $pdf->tablaTotal($anchos, 'TOTAL', number_format($total, 0, ',', '.'));
    } else {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, convertir('No se encontraron productos para este pedido.'), 0, 1, 'C');
    }

    $pdf->pieDocumento();
    $pdf->Output('I', "Pedido_Venta_$ped_id.pdf");
} catch (PDOException $e) {
    echo "Error en la conexión o consulta: " . $e->getMessage();
    exit;
}
?>

