<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
// Incluir la conexión a la base de datos y la clase para generar PDF
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/reporte_compras.php'; // Clase BasePDF para generar el PDF

// Verificar si se recibió el estado desde el modal
if (isset($_GET['estado'])) {
    $estado = $_GET['estado'];

    try {
        // Crear la conexión usando PDO
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Consultar las facturas y detalles con estado específico
        $query = $pdo->prepare("
            SELECT 
                fc.fact_id, 
                fc.fact_nro, 
                fc.fact_total, 
                fc.fact_fecha, 
                fc.fact_estado, 
                fc.timbrado_id, 
                pv.razon_social AS proveedor, 
                u.username AS usuario, 
                p.p_descrip AS producto, 
                fcd.fact_cantidad AS cantidad, 
                fcd.fact_precio AS precio, 
                fcd.fact_iva AS iva
            FROM facturas_compra fc
            JOIN facturas_detalle_compra fcd ON fc.fact_id = fcd.fact_id
            JOIN producto p ON fcd.cod_producto = p.cod_producto
            JOIN proveedor pv ON fc.cod_proveedor = pv.cod_proveedor
            JOIN usuarios u ON fc.id_usuario = u.id_usuario
            WHERE fc.fact_estado = :estado
            ORDER BY fc.fact_fecha DESC, fc.fact_id, p.p_descrip
        ");
        $query->bindParam(':estado', $estado, PDO::PARAM_STR);
        $query->execute();

        // Obtener los resultados
        $facturas = $query->fetchAll(PDO::FETCH_ASSOC);

        if (count($facturas) > 0) {
            // Crear un nuevo documento PDF
            $pdf = new BasePDF('P', 'mm', 'A4', [
                'titulo'    => 'Informe de Facturas',
                'subtitulo' => 'Estado: ' . $estado,
            ]);
            $pdf->AliasNbPages();
            $pdf->AddPage();

            $anchos = [15, 25, 25, 30, 30, 15, 20, 10, 20];
            $aligns = ['C', 'C', 'C', 'L', 'L', 'C', 'R', 'C', 'R'];
            $pdf->tablaEncabezado($anchos, ['ID', 'Nro Factura', 'Usuario', 'Proveedor', 'Producto', 'Cantidad', 'Precio', 'IVA', 'Total Factura'], $aligns);

            foreach ($facturas as $factura) {
                $pdf->tablaFila($anchos, [
                    $factura['fact_id'],
                    $factura['fact_nro'],
                    $factura['usuario'],
                    $factura['proveedor'],
                    $factura['producto'],
                    $factura['cantidad'],
                    number_format($factura['precio'], 2),
                    $factura['iva'],
                    number_format($factura['fact_total'], 2) . ' Gs',
                ], $aligns);
            }

            $pdf->pieDocumento();
            $pdf->Output('I', 'Informe_Facturas_' . $estado . '.pdf');
        } else {
            echo "<h2>No se encontraron facturas con el estado: " . htmlspecialchars($estado) . "</h2>";
        }
    } catch (PDOException $e) {
        // Mostrar error si ocurre alguno
        echo "Error al consultar los datos: " . $e->getMessage();
    }
} else {
    // Si no se recibe el estado, redirigir al inicio
    echo "<script>
            alert('No se recibió un estado válido para generar el informe.');
            window.location.href = 'view.php';
          </script>";
    exit();
}

// Función para convertir texto con codificación
function convertirTexto($texto) {
    if (is_null($texto) || $texto === '') {
        return '';
    }
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}
?>
