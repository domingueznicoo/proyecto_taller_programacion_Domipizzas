<?php
// Genera informe PDF de presupuestos de compra.
require_once __DIR__ . '/../../../config/bootstrap.php';
// Incluir DB y PDF
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/reporte_presupuesto.php'; // Clase BasePDF (FPDF/TCPDF)

// Validar parámetro
if (!isset($_GET['estado']) || $_GET['estado'] === '') {
    echo "<script>
            alert('No se recibió un estado válido para generar el informe.');
            window.location.href = '../../comunes/reportes/view.php';
          </script>";
    exit();
}

$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
$reFecha = '/^\d{4}-\d{2}-\d{2}$/';
if ($desde === '' || $hasta === '' || !preg_match($reFecha, $desde) || !preg_match($reFecha, $hasta)) {
    echo "<script>
            alert('El rango de fechas es obligatorio y debe tener el formato YYYY-MM-DD.');
            window.location.href = '../../comunes/reportes/view.php';
          </script>";
    exit();
}
if ($hasta < $desde) {
    echo "<script>
            alert('La fecha \"Hasta\" no puede ser anterior a \"Desde\".');
            window.location.href = '../../comunes/reportes/view.php';
          </script>";
    exit();
}

$estadoParam = trim($_GET['estado']);
$estadoUpper = mb_strtoupper($estadoParam);
$esTotal     = in_array($estadoUpper, ['REPORTE TOTAL','TOTAL'], true);

// Helper para convertir a ISO si tu BasePDF trabaja en ISO-8859-1 (opcional)
if (!function_exists('conv')) {
    function conv($s) {
        if ($s === null) return '';
        // Ajustá la conversión si tu BasePDF ya maneja UTF-8
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$s);
    }
}

try {
    // Conexión PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // ================= SQL base =================
    // Traemos cabecera + detalle + producto + iva + usuario (y proveedor si querés mostrarlo)
    $sql = "
        SELECT
            pc.id_presupuesto_compra                 AS pre_id,
            to_char(pc.presu_fecha,'YYYY-MM-DD')     AS fecha,
            to_char(pc.presu_fecha,'HH24:MI:SS')     AS hora,
            pc.presu_estado                          AS estado,
            u.username                               AS usuario,
            pr.razon_social                          AS proveedor,

            mp.materia_prima_descripcion             AS producto,
            d.detalle_presu_cantidad                 AS cantidad,
            d.detalle_presu_precio_compra            AS precio,
            ti.iva_descri                            AS iva_descri
        FROM presupuesto_compra pc
        JOIN usuarios u   ON u.id_usuario      = pc.id_usuario
        JOIN proveedor pr ON pr.id_proveedor   = pc.id_proveedor
        JOIN presupuesto_detalle_compra d ON d.id_presupuesto_compra = pc.id_presupuesto_compra
        JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
        LEFT JOIN tipo_iva ti  ON ti.iva_id         = mp.iva_id
        WHERE DATE(pc.presu_fecha) BETWEEN :desde AND :hasta
    ";

    if (!$esTotal) {
        $sql .= " AND pc.presu_estado = :estado ";
    }

    $sql .= " ORDER BY pc.presu_fecha DESC, pc.id_presupuesto_compra DESC, mp.materia_prima_descripcion ASC;";

    $st = $pdo->prepare($sql);
    $st->bindParam(':desde', $desde, PDO::PARAM_STR);
    $st->bindParam(':hasta', $hasta, PDO::PARAM_STR);
    if (!$esTotal) {
        $st->bindParam(':estado', $estadoUpper, PDO::PARAM_STR);
    }
    $st->execute();

    $rows = $st->fetchAll();
    if (count($rows) === 0) {
        header("Location: ../../comunes/reportes/view.php?alert=6");
        exit();
    }

    // ================= PDF =================
    $titulo = $esTotal
        ? 'INFORME DE PRESUPUESTOS - TODOS LOS ESTADOS'
        : 'INFORME DE PRESUPUESTOS - ESTADO: ' . $estadoUpper;

    // Si BasePDF acepta opciones, pásalas; si no, podés imprimir el título manualmente
    $pdf = new BasePDF('P', 'mm', 'A4', [
        'titulo'    => $titulo,
        'subtitulo' => 'Periodo: ' . $desde . ' al ' . $hasta,
    ]);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $anchos = [16, 30, 52, 12, 18, 22, 20, 20];
    $aligns = ['C', 'L', 'L', 'C', 'R', 'R', 'C', 'C'];
    $pdf->tablaEncabezado($anchos, ['Presu', 'Proveedor', 'Producto', 'Cant.', 'Precio', 'Subtotal', 'Fecha', 'Estado'], $aligns);

    $totalImporte = 0;
    $totalIva     = 0;
    $docCount     = 0;
    $ultimoPreId  = null;

    foreach ($rows as $r) {
        $preId   = (int)$r['pre_id'];
        $fecha   = $r['fecha'];
        $estadoR = $r['estado'];
        $prov    = $r['proveedor'];

        $producto = $r['producto'];
        $cantidad = (int)$r['cantidad'];
        $precio   = (float)$r['precio'];
        $ivaDesc  = strtolower(trim((string)$r['iva_descri'])); // 'iva_10' | 'iva_5' | otro

        // Subtotal
        $subtotal = $cantidad * $precio;

        // IVA por fila (10% => /11, 5% => /21)
        $ivaUnit = 0;
        if ($ivaDesc === 'iva_10')      $ivaUnit = floor($precio / 11);
        elseif ($ivaDesc === 'iva_5')   $ivaUnit = floor($precio / 21);
        $ivaFila = $cantidad * $ivaUnit;

        $totalImporte += $subtotal;
        $totalIva     += $ivaFila;

        // Contar documentos únicos
        if ($ultimoPreId === null || $ultimoPreId !== $preId) {
            $docCount++;
            $ultimoPreId = $preId;
        }

        $pdf->tablaFila($anchos, [
            number_format($preId, 0, ',', '.'),
            conv($prov),
            conv($producto),
            number_format($cantidad, 0, ',', '.'),
            number_format($precio, 0, ',', '.') . ' Gs',
            number_format($subtotal, 0, ',', '.') . ' Gs',
            conv($fecha),
            conv($estadoR),
        ], $aligns);
    }

    // ===== Resumen final =====
    $pdf->Ln(5);
    $pdf->SetFont('Times','B',11);
    $pdf->Cell(0, 7, 'Resumen', 0, 1, 'R');

    $pdf->SetFont('Times','',10);
    $pdf->Cell(140, 7, 'Cantidad de presupuestos (documentos):', 0, 0, 'R');
    $pdf->Cell(50,  7, number_format($docCount, 0, ',', '.'), 0, 1, 'R');

    $pdf->Cell(140, 7, 'Total IVA (suma de IVA por fila):', 0, 0, 'R');
    $pdf->Cell(50,  7, number_format($totalIva, 0, ',', '.') . ' Gs', 0, 1, 'R');

    $pdf->Cell(140, 7, 'Total Importe (suma de subtotales):', 0, 0, 'R');
    $pdf->Cell(50,  7, number_format($totalImporte, 0, ',', '.') . ' Gs', 0, 1, 'R');

    $pdf->pieDocumento();

    // Salida
    $nombreSalida = $esTotal
        ? 'Informe_Presupuestos_Todos.pdf'
        : 'Informe_Presupuestos_' . preg_replace('/\s+/', '_', $estadoUpper) . '.pdf';

    $pdf->Output('I', $nombreSalida);

} catch (PDOException $e) {
    echo "Error al consultar los datos: " . $e->getMessage();
    exit;
}
