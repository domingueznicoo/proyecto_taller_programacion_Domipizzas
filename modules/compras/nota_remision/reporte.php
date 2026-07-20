<?php
// Genera informe PDF de notas de remisión.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/reporte_remision.php';

if (!isset($_GET['nr_id']) || empty($_GET['nr_id'])) {
    die("No se proporcionó un ID de nota válido.");
}

$nr_id = intval($_GET['nr_id']);

$pdf = new BasePDF('P', 'mm', 'A4', [
    'titulo'    => 'Nota de Remisión',
    'subtitulo' => 'Detalle N° ' . $nr_id,
]);
$pdf->AliasNbPages();
$pdf->AddPage();

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $queryNota = "
        SELECT 
            nr.id_nota_remision,
            nr.nota_fecha,
            nr.nota_remision_total,
            nr.nota_remision_nro,
            nr.nota_estado,
            u.username,
            pr.razon_social,
            COALESCE(c.conductor_nombre || ' ' || c.conductor_apellido, 'N/D') AS conductor,
            COALESCE(v.vehiculo_marca || ' ' || v.vehiculo_ano || ' ' || v.vehiculo_color, 'N/D') AS vehiculo,
            oc.id_orden_compra
        FROM 
            nota_remision_compra nr
        JOIN 
            usuarios u ON u.id_usuario = nr.id_usuario
        JOIN 
            proveedor pr ON pr.id_proveedor = nr.id_proveedor
        LEFT JOIN 
            orden_de_compra oc ON oc.id_orden_compra = nr.id_orden_compra
        LEFT JOIN 
            conductores c ON c.conductor_id = nr.conductor_id
        LEFT JOIN 
            vehiculos v ON v.vehiculo_id = nr.vehiculo_id
        WHERE 
            nr.id_nota_remision = :nr_id
    ";

    $stmtNota = $pdo->prepare($queryNota);
    $stmtNota->bindParam(':nr_id', $nr_id, PDO::PARAM_INT);
    $stmtNota->execute();

    $nota = $stmtNota->fetch(PDO::FETCH_ASSOC);
    if (!$nota) {
        die("No se encontró información para la nota proporcionada.");
    }

    $pdf->seccionTitulo('Datos de la nota');
    $pdf->filaDato('N° Remisión:', $nota['nota_remision_nro'] ?? 'N/D', 38);
    $pdf->filaDato('Estado:', $nota['nota_estado'], 38);
    $pdf->filaDato('Fecha:', $nota['nota_fecha'], 38);
    $pdf->filaDato('Orden Compra:', $nota['id_orden_compra'] ?? 'N/D', 38);
    $pdf->filaDato('Total:', number_format($nota['nota_remision_total'], 0, ',', '.') . ' Gs', 38);
    $pdf->filaDato('Proveedor:', $nota['razon_social'], 38);
    $pdf->filaDato('Usuario:', $nota['username'], 38);
    $pdf->filaDato('Conductor:', $nota['conductor'], 38);
    $pdf->filaDato('Vehículo:', $nota['vehiculo'], 38);

    $queryDetalles = "
        SELECT 
            mp.materia_prima_descripcion AS producto,
            d.nota_cantidad        AS cantidad
        FROM 
            nota_remision_detalle_compra d
        JOIN 
            materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
        WHERE 
            d.id_nota_remision = :nr_id
        ORDER BY mp.materia_prima_descripcion
    ";

    $stmtDetalles = $pdo->prepare($queryDetalles);
    $stmtDetalles->bindParam(':nr_id', $nr_id, PDO::PARAM_INT);
    $stmtDetalles->execute();

    $pdf->seccionTitulo('Detalle de productos');
    $anchos = [130, 50];
    $pdf->tablaEncabezado($anchos, ['Producto', 'Cantidad'], ['L', 'C']);

    while ($detalle = $stmtDetalles->fetch(PDO::FETCH_ASSOC)) {
        $pdf->tablaFila($anchos, [
            $detalle['producto'],
            number_format($detalle['cantidad'], 0, '', '.'),
        ], ['L', 'C']);
    }

    $pdf->pieDocumento();
    $pdf->Output('I', "Detalle_Nota_Remision_$nr_id.pdf");

} catch (PDOException $e) {
    echo "Error en la conexión o consulta: " . $e->getMessage();
    exit;
}
?>
