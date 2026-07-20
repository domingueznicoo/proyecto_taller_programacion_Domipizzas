<?php
// Genera informe PDF de notas de remisión de compra.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/reporte_compras.php';

if (!isset($_GET['estado']) || $_GET['estado'] === '') {
    echo "<script>alert('No se recibió un estado válido.'); window.location.href = '../../comunes/reportes/view.php';</script>";
    exit;
}

$desde = trim($_GET['desde'] ?? '');
$hasta = trim($_GET['hasta'] ?? '');
if ($desde === '' || $hasta === '' || $hasta < $desde) {
    echo "<script>alert('Rango de fechas inválido.'); window.location.href = '../../comunes/reportes/view.php';</script>";
    exit;
}

$estadoUpper = mb_strtoupper(trim($_GET['estado']));
$esTotal = in_array($estadoUpper, ['REPORTE TOTAL', 'TOTAL'], true);

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$database", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = "
        SELECT
            nr.id_nota_remision,
            nr.nota_remision_nro,
            nr.nota_estado,
            nr.nota_remision_total,
            TO_CHAR(nr.nota_fecha, 'YYYY-MM-DD') AS fecha,
            u.username AS usuario,
            pr.razon_social AS proveedor,
            mp.materia_prima_descripcion AS producto,
            nd.nota_cantidad AS cantidad
        FROM nota_remision_compra nr
        JOIN nota_remision_detalle_compra nd ON nd.id_nota_remision = nr.id_nota_remision
        JOIN materia_prima mp ON mp.id_materia_prima = nd.id_materia_prima
        JOIN usuarios u ON u.id_usuario = nr.id_usuario
        JOIN proveedor pr ON pr.id_proveedor = nr.id_proveedor
        WHERE DATE(nr.nota_fecha) BETWEEN :desde AND :hasta
    ";
    if (!$esTotal) {
        $sql .= " AND UPPER(nr.nota_estado) = :estado ";
    }
    $sql .= " ORDER BY nr.nota_fecha DESC, nr.id_nota_remision DESC";

    $st = $pdo->prepare($sql);
    $st->bindValue(':desde', $desde);
    $st->bindValue(':hasta', $hasta);
    if (!$esTotal) {
        $st->bindValue(':estado', $estadoUpper);
    }
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        header('Location: ../../comunes/reportes/view.php?alert=6');
        exit;
    }

    $pdf = new BasePDF('P', 'mm', 'A4', [
        'titulo'    => 'Informe de Notas de Remisión',
        'subtitulo' => 'Periodo: ' . $desde . ' al ' . $hasta,
    ]);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $anchos = [15, 25, 22, 40, 30, 35, 15, 18];
    $aligns = ['C', 'C', 'C', 'L', 'L', 'L', 'C', 'R'];
    $pdf->tablaEncabezado($anchos, ['ID', 'Nro', 'Fecha', 'Proveedor', 'Producto', 'Usuario', 'Cant', 'Total'], $aligns);
    foreach ($rows as $r) {
        $pdf->tablaFila($anchos, [
            $r['id_nota_remision'],
            $r['nota_remision_nro'],
            $r['fecha'],
            $r['proveedor'],
            $r['producto'],
            $r['usuario'],
            $r['cantidad'],
            number_format((float)$r['nota_remision_total'], 0, ',', '.'),
        ], $aligns);
    }
    $pdf->pieDocumento();
    $pdf->Output('I', 'Informe_Notas_Remision.pdf');
} catch (Throwable $e) {
    echo 'Error al generar informe: ' . htmlspecialchars($e->getMessage());
}
