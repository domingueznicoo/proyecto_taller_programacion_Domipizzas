<?php
// Genera informe PDF de notas de crédito/débito de compra.
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
$tipoFiltro = isset($_GET['tipo']) ? mb_strtoupper(trim($_GET['tipo'])) : '';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$database", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = "
        SELECT
            nc.id_nota_compra,
            nc.nota_compra_tipo,
            nc.nota_nro,
            nc.nota_total,
            nc.nota_compra_estado,
            TO_CHAR(nc.nota_compra_fecha, 'YYYY-MM-DD') AS fecha,
            u.username AS usuario,
            pr.razon_social AS proveedor,
            m.motivo_descripcion AS motivo,
            mp.materia_prima_descripcion AS producto,
            nd.nota_compra_cantidad AS cantidad,
            nd.nota_precio AS precio
        FROM nota_compra nc
        JOIN nota_detalle_compra nd ON nd.id_nota_compra = nc.id_nota_compra
        JOIN materia_prima mp ON mp.id_materia_prima = nd.id_materia_prima
        JOIN usuarios u ON u.id_usuario = nc.id_usuario
        JOIN proveedor pr ON pr.id_proveedor = nc.id_proveedor
        JOIN motivo m ON m.id_motivo = nc.id_motivo
        WHERE DATE(nc.nota_compra_fecha) BETWEEN :desde AND :hasta
    ";
    if (in_array($tipoFiltro, ['CREDITO', 'DEBITO'], true)) {
        $sql .= " AND UPPER(nc.nota_compra_tipo) = :tipo ";
    }
    if (!$esTotal) {
        $sql .= " AND UPPER(nc.nota_compra_estado) = :estado ";
    }
    $sql .= " ORDER BY nc.nota_compra_fecha DESC, nc.id_nota_compra DESC";

    $st = $pdo->prepare($sql);
    $st->bindValue(':desde', $desde);
    $st->bindValue(':hasta', $hasta);
    if (in_array($tipoFiltro, ['CREDITO', 'DEBITO'], true)) {
        $st->bindValue(':tipo', $tipoFiltro);
    }
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
        'titulo'    => $tipoFiltro === 'DEBITO' ? 'Informe de Notas de Débito' : ($tipoFiltro === 'CREDITO' ? 'Informe de Notas de Crédito' : 'Informe de Notas de Crédito/Débito'),
        'subtitulo' => 'Periodo: ' . $desde . ' al ' . $hasta,
    ]);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $anchos = [15, 18, 22, 35, 25, 30, 12, 18, 15];
    $aligns = ['C', 'C', 'C', 'L', 'L', 'L', 'C', 'R', 'C'];
    $pdf->tablaEncabezado($anchos, ['ID', 'Tipo', 'Nro', 'Proveedor', 'Motivo', 'Producto', 'Cant', 'Precio', 'Estado'], $aligns);
    foreach ($rows as $r) {
        $pdf->tablaFila($anchos, [
            $r['id_nota_compra'],
            $r['nota_compra_tipo'],
            $r['nota_nro'],
            $r['proveedor'],
            $r['motivo'],
            $r['producto'],
            $r['cantidad'],
            number_format((float)$r['precio'], 0, ',', '.'),
            $r['nota_compra_estado'],
        ], $aligns);
    }
    $pdf->pieDocumento();
    $pdf->Output('I', 'Informe_Notas_Compra.pdf');
} catch (Throwable $e) {
    echo 'Error al generar informe: ' . htmlspecialchars($e->getMessage());
}
