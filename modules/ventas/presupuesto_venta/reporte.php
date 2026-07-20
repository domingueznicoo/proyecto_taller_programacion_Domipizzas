<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/pedido_ventas.php'; // Clase BasePDF (reutilizable)

// Verificar si se recibió el parámetro 'pre_id'
if (!isset($_GET['pre_id']) || empty($_GET['pre_id'])) {
    die("No se proporcionó un ID de presupuesto válido.");
}

$pre_id = intval($_GET['pre_id']);

$pdf = new BasePDF('P', 'mm', 'A4', [
    'titulo'    => 'Presupuesto de Venta',
    'subtitulo' => 'Detalle N° ' . $pre_id,
]);
$pdf->AliasNbPages();
$pdf->AddPage();

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para obtener la información general del presupuesto
    $queryGeneral = "
        SELECT 
            pv.id_presupuesto_venta,
            to_char(pv.fecha_presupuesto, 'YYYY-MM-DD') AS fecha_presupuesto,
            pv.estado, 
            pv.validez,
            pv.observacion,
            c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre,
            c.cliente_ruc,
            us.username AS usuario, 
            suc.descripcion_sucursal AS sucursal,
            COALESCE(pv.monto_total, 0) AS monto_total
        FROM 
            presupuesto_venta pv
        JOIN 
            clientes c ON pv.id_cliente = c.id_cliente
        JOIN 
            sucursales suc ON pv.id_sucursal = suc.id_sucursal
        JOIN 
            usuarios us ON pv.id_usuario = us.id_usuario
        WHERE 
            pv.id_presupuesto_venta = :pre_id
        LIMIT 1
    ";

    $stmtGeneral = $pdo->prepare($queryGeneral);
    $stmtGeneral->bindParam(':pre_id', $pre_id, PDO::PARAM_INT);
    $stmtGeneral->execute();
    $general = $stmtGeneral->fetch(PDO::FETCH_ASSOC);

    if ($general) {
        $pdf->seccionTitulo('Datos del presupuesto');
        $pdf->filaDato('Presupuesto N°:', (string)$general['id_presupuesto_venta'], 38);
        $pdf->filaDato('Fecha:', $general['fecha_presupuesto'], 38);
        $pdf->filaDato('Cliente:', $general['cliente_nombre'], 38);
        $pdf->filaDato('RUC:', $general['cliente_ruc'], 38);
        $pdf->filaDato('Estado:', $general['estado'], 38);
        if ($general['validez']) {
            $pdf->filaDato('Validez:', $general['validez'] . ' días', 38);
        }
        if ($general['observacion']) {
            $pdf->filaDato('Observación:', $general['observacion'], 38);
        }
        $pdf->filaDato('Usuario:', $general['usuario'], 38);
        $pdf->filaDato('Sucursal:', $general['sucursal'], 38);
        $pdf->Ln(4);
    } else {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'No se encontraron datos generales para este presupuesto.', 0, 1, 'C');
    }

    // Consulta para obtener los detalles del presupuesto
    $queryDetails = "
        SELECT 
            p.producto_descri AS producto, 
            d.cantidad,
            d.precio_unitario,
            d.iva,
            (d.cantidad * d.precio_unitario) AS subtotal_base,
            CASE 
                WHEN d.iva > 0 THEN (d.cantidad * d.precio_unitario * (1 + d.iva / 100))
                ELSE (d.cantidad * d.precio_unitario)
            END AS subtotal
        FROM 
            detalle_presupuesto_venta d
        JOIN 
            productos p ON d.producto_id = p.producto_id
        WHERE 
            d.id_presupuesto_venta = :pre_id
        ORDER BY 
            p.producto_descri
    ";

    $stmtDetails = $pdo->prepare($queryDetails);
    $stmtDetails->bindParam(':pre_id', $pre_id, PDO::PARAM_INT);
    $stmtDetails->execute();

    if ($stmtDetails->rowCount() > 0) {
        $anchos = [60, 25, 30, 20, 35];
        $aligns = ['L', 'C', 'R', 'C', 'R'];
        $pdf->seccionTitulo('Detalle de productos');
        $pdf->tablaEncabezado($anchos, ['Producto', 'Cantidad', 'Precio Unit.', 'IVA %', 'Subtotal'], $aligns);

        $total = 0;
        $subtotalSinIva = 0;
        $totalIva = 0;
        while ($row = $stmtDetails->fetch(PDO::FETCH_ASSOC)) {
            $pdf->tablaFila($anchos, [
                $row['producto'],
                $row['cantidad'],
                number_format($row['precio_unitario'], 0, ',', '.'),
                $row['iva'] > 0 ? $row['iva'] . '%' : 'Exento',
                number_format($row['subtotal'], 0, ',', '.'),
            ], $aligns);
            $total += $row['subtotal'];
            $subtotalSinIva += $row['subtotal_base'];
            $totalIva += ($row['subtotal'] - $row['subtotal_base']);
        }

        $pdf->tablaTotal($anchos, 'Subtotal (sin IVA)', number_format($subtotalSinIva, 0, ',', '.'));
        $pdf->tablaTotal($anchos, 'Total IVA', number_format($totalIva, 0, ',', '.'));
        $pdf->tablaTotal($anchos, 'TOTAL', number_format($total, 0, ',', '.'));
    } else {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, convertir('No se encontraron productos para este presupuesto.'), 0, 1, 'C');
    }

    $pdf->pieDocumento();
    $pdf->Output('I', "Presupuesto_Venta_$pre_id.pdf");
} catch (PDOException $e) {
    echo "Error en la conexión o consulta: " . $e->getMessage();
    exit;
}
?>

