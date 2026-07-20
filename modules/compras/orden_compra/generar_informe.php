<?php
// Genera informe PDF de órdenes de compra.
require_once __DIR__ . '/../../../config/bootstrap.php';
// Incluir la conexión a la base de datos y la clase para generar PDF
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/orden_compras.php'; // Clase BasePDF (FPDF/TCPDF)

function mapIvaPct(?string $ivaDescri): string {
    $k = strtolower(trim((string)$ivaDescri));
    if ($k === 'iva_10') return '10';
    if ($k === 'iva_5')  return '5';
    return '0';
}

// Validar parámetro de estado del modal
if (!isset($_GET['estado']) || $_GET['estado'] === '') {
    echo "<script>
            alert('No se recibió un estado válido para generar el informe.');
            window.location.href = '../../comunes/reportes/view.php';
          </script>";
    exit;
}

$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
$reFecha = '/^\d{4}-\d{2}-\d{2}$/';
if ($desde === '' || $hasta === '' || !preg_match($reFecha, $desde) || !preg_match($reFecha, $hasta)) {
    echo "<script>
            alert('El rango de fechas es obligatorio y debe tener el formato YYYY-MM-DD.');
            window.location.href = '../../comunes/reportes/view.php';
          </script>";
    exit;
}
if ($hasta < $desde) {
    echo "<script>
            alert('La fecha \"Hasta\" no puede ser anterior a \"Desde\".');
            window.location.href = '../../comunes/reportes/view.php';
          </script>";
    exit;
}

$estadoParam    = trim($_GET['estado']);
$estadoUpper    = strtoupper($estadoParam);
$estadoTitulo   = $estadoUpper;
$esReporteTotal = in_array($estadoUpper, ['REPORTE TOTAL','TOTAL'], true);

try {
    // Conexión PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Query base (detalle por producto)
    $sql = "
        SELECT
            oc.id_orden_compra                          AS orden_id,
            TO_CHAR(oc.orden_fecha, 'YYYY-MM-DD')       AS fecha,
            TO_CHAR(oc.orden_fecha, 'HH24:MI:SS')       AS hora,
            oc.orden_estado                             AS estado,
            oc.orden_total                              AS total,
            oc.orden_condicion                          AS condicion,
            u.username                                  AS usuario,
            s.descripcion_sucursal                      AS sucursal,
            pv.razon_social                             AS proveedor,
            d.id_materia_prima                                AS cod_producto,
            mp.materia_prima_descripcion                      AS producto,
            d.oc_cantidad_compra                        AS cantidad,
            d.oc_precio_compra                          AS precio,
            ti.iva_descri                               AS iva_descri
        FROM orden_de_compra oc
        JOIN orden_detalle_compra d  ON d.id_orden_compra = oc.id_orden_compra
        JOIN materia_prima mp        ON mp.id_materia_prima = d.id_materia_prima
        LEFT JOIN tipo_iva ti        ON ti.iva_id         = mp.iva_id
        JOIN usuarios u              ON u.id_usuario      = oc.id_usuario
        JOIN sucursales s            ON s.id_sucursal     = oc.id_sucursal
        JOIN proveedor pv            ON pv.id_proveedor   = oc.id_proveedor
        WHERE DATE(oc.orden_fecha) BETWEEN :desde AND :hasta
    ";

    if (!$esReporteTotal) {
        $sql .= " AND oc.orden_estado = :estado ";
    }

    $sql .= " ORDER BY oc.id_orden_compra ASC, d.id_materia_prima ASC;";

    $st = $pdo->prepare($sql);
    $st->bindParam(':desde', $desde, PDO::PARAM_STR);
    $st->bindParam(':hasta', $hasta, PDO::PARAM_STR);
    if (!$esReporteTotal) {
        $st->bindParam(':estado', $estadoUpper, PDO::PARAM_STR);
    }
    $st->execute();
    $rows = $st->fetchAll();

    if (!$rows || count($rows) === 0) {
        header("Location: ../../comunes/reportes/view.php?alert=6");
        exit;
    }

    $tituloPdf = $esReporteTotal ? 'Informe de Órdenes de Compra' : 'Informe de Órdenes de Compra - Estado: ' . $estadoTitulo;
    $pdf = new BasePDF('P', 'mm', 'A4', [
        'titulo'    => $tituloPdf,
        'subtitulo' => 'Periodo: ' . $desde . ' al ' . $hasta,
    ]);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $anchos = [8, 18, 34, 16, 15, 40, 14, 18, 20];
    $aligns = ['C', 'C', 'L', 'C', 'R', 'L', 'C', 'R', 'R'];
    $pdf->tablaEncabezado($anchos, ['ID', 'Fecha', 'Proveedor', 'Condicion', 'Total', 'Producto', 'Cant.', 'Precio', 'Subtot.'], $aligns);

    foreach ($rows as $r) {
        $subtotal = (int)$r['cantidad'] * (int)$r['precio'];

        $pdf->tablaFila($anchos, [
            $r['orden_id'],
            $r['fecha'],
            $r['proveedor'],
            $r['condicion'],
            number_format((float)$r['total'], 0, ',', '.'),
            $r['producto'],
            number_format((int)$r['cantidad'], 0, ',', '.'),
            number_format((int)$r['precio'], 0, ',', '.'),
            number_format($subtotal, 0, ',', '.'),
        ], $aligns);
    }

    $pdf->pieDocumento();

    // Salida del PDF
    $nombre = 'Informe_OC_' . ($esReporteTotal ? 'TODOS' : preg_replace('/\s+/', '_', $estadoTitulo)) . '.pdf';
    $pdf->Output('I', $nombre);

} catch (PDOException $e) {
    echo "Error al consultar los datos: " . htmlspecialchars($e->getMessage());
    exit;
}
