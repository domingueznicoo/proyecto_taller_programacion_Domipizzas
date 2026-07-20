<?php
// Genera informe PDF de notas de crédito/débito de compra.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/reporte_NCND.php';

function fmt_gs($valor){
    return number_format((int)$valor, 0, ',', '.') . ' Gs';
}

$notaId = isset($_GET['nota_id']) ? (int)$_GET['nota_id'] : (int)($_GET['id'] ?? 0);
if ($notaId <= 0) {
    die('Parámetro de nota inválido.');
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Throwable $e) {
    die("No se pudo conectar a la base de datos: ".$e->getMessage());
}

$sqlNota = "
    SELECT
      nc.id_nota_compra,
      nc.nota_compra_tipo,
      nc.nota_compra_fecha,
      nc.nota_compra_inicio AS nota_compra_emision,
      nc.nota_compra_vencimiento,
      nc.nota_nro,
      nc.nota_compra_timbrado,
      nc.nota_compra_estado,
      nc.nota_total,
      nc.id_factura_compra,
      m.motivo_descripcion,
      pr.razon_social,
      pr.ruc_proveedor,
      u.username,
      fc.numero_factura
    FROM nota_compra nc
    JOIN proveedor pr ON pr.id_proveedor = nc.id_proveedor
    JOIN usuarios  u  ON u.id_usuario   = nc.id_usuario
    LEFT JOIN motivo m ON m.id_motivo   = nc.id_motivo
    LEFT JOIN factura_compra fc ON fc.id_factura_compra = nc.id_factura_compra
    WHERE nc.id_nota_compra = :id
    LIMIT 1
";

$stNota = $pdo->prepare($sqlNota);
$stNota->execute([':id'=>$notaId]);
$nota = $stNota->fetch();
if (!$nota) {
    die('Nota no encontrada.');
}

$sqlDet = "
  SELECT
    mp.materia_prima_descripcion,
    d.nota_compra_cantidad AS cantidad,
    d.nota_precio          AS precio,
    d.tipo_iva
  FROM nota_detalle_compra d
  JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
  WHERE d.id_nota_compra = :id
  ORDER BY mp.materia_prima_descripcion
";
$stDet = $pdo->prepare($sqlDet);
$stDet->execute([':id'=>$notaId]);
$detalles = $stDet->fetchAll();

$totIva = ['5'=>0,'10'=>0];
$totalSub = 0;
foreach ($detalles as &$det) {
    $sub = (int)$det['cantidad'] * (int)$det['precio'];
    $det['subtotal'] = $sub;
    $totalSub += $sub;
    $ivaKey = (int)$det['tipo_iva'];
    if ($ivaKey === 10) {
        $totIva['10'] += (int)floor($sub / 11);
    } elseif ($ivaKey === 5) {
        $totIva['5'] += (int)floor($sub / 21);
    }
}
unset($det);

$pdf = new BasePDF('P', 'mm', 'A4', [
    'titulo'    => 'Nota Crédito / Débito',
    'subtitulo' => 'Detalle N° ' . $notaId,
]);
$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->seccionTitulo('Datos de la nota');
$pdf->filaDato('Nota N°:', $nota['nota_nro'], 38);
$pdf->filaDato('Tipo:', $nota['nota_compra_tipo'], 38);
$pdf->filaDato('Fecha:', $nota['nota_compra_fecha'], 38);
$pdf->filaDato('Estado:', $nota['nota_compra_estado'], 38);
$pdf->filaDato('Emisión:', $nota['nota_compra_emision'], 38);
$pdf->filaDato('Vencimiento:', $nota['nota_compra_vencimiento'], 38);
$pdf->filaDato('Proveedor:', $nota['razon_social'], 38);
$pdf->filaDato('RUC:', $nota['ruc_proveedor'] ?? '', 38);
$pdf->filaDato('Motivo:', $nota['motivo_descripcion'] ?? '—', 38);
$pdf->filaDato('Usuario:', $nota['username'], 38);
$pdf->filaDato('Factura origen:', $nota['numero_factura'] ?? '—', 38);

$pdf->seccionTitulo('Detalle de productos');
$anchos = [70, 20, 30, 30, 30];
$aligns = ['L', 'C', 'R', 'R', 'C'];
$pdf->tablaEncabezado($anchos, ['Producto', 'Cantidad', 'Precio', 'Subtotal', 'IVA'], $aligns);

foreach ($detalles as $det) {
    $pdf->tablaFila($anchos, [
        $det['materia_prima_descripcion'],
        $det['cantidad'],
        fmt_gs($det['precio']),
        fmt_gs($det['subtotal']),
        $det['tipo_iva'] . '%',
    ], $aligns);
}

$pdf->Ln(4);
$pdf->filaDato('Subtotal productos:', fmt_gs($totalSub), 38);
$pdf->filaDato('Total Nota:', fmt_gs($nota['nota_total']), 38);
$pdf->filaDato('IVA 5%:', fmt_gs($totIva['5']), 38);
$pdf->filaDato('IVA 10%:', fmt_gs($totIva['10']), 38);

$pdf->pieDocumento();

$pdf->Output('I', "NotaCompra_{$notaId}.pdf");
