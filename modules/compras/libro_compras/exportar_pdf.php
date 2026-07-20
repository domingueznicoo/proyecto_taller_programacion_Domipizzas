<?php
// Exporta datos de libro de compras (exportar_pdf).
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();
require_once PROJECT_ROOT . '/config/database.php';
require_once __DIR__ . '/require_auth.php';
require_once PROJECT_ROOT . '/reporte/reporte_compras.php'; // Clase BasePDF

if (!function_exists('convertir')) {
    function convertir($texto) {
        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
    }
}

$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

$reFecha = '/^\d{4}-\d{2}-\d{2}$/';
if ($desde === '' || $hasta === '' || !preg_match($reFecha, $desde) || !preg_match($reFecha, $hasta)) {
    die('El rango de fechas es obligatorio y debe tener el formato YYYY-MM-DD.');
}
if ($hasta < $desde) {
    die('La fecha "Hasta" no puede ser anterior a "Desde".');
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $proveedorId = isset($_GET['proveedor']) && $_GET['proveedor'] !== '' && $_GET['proveedor'] !== '0' ? (int)$_GET['proveedor'] : null;
    $tipoDoc = isset($_GET['tipo']) && $_GET['tipo'] !== '' ? trim($_GET['tipo']) : null;
    $sucursalId = isset($_GET['sucursal']) && $_GET['sucursal'] !== '' && $_GET['sucursal'] !== '0' ? (int)$_GET['sucursal'] : null;
    
    require 'consolidar_documentos.php';
    $documentos = consolidarDocumentos($pdo, $desde, $hasta, $proveedorId, $tipoDoc, $sucursalId);
    
    // Calcular totales
    $totalExento = 0;
    $totalBase5 = 0;
    $totalIva5 = 0;
    $totalBase10 = 0;
    $totalIva10 = 0;
    $totalGeneral = 0;
    
    foreach ($documentos as $doc) {
        $totalExento += $doc['exento'];
        $totalBase5 += $doc['base_5'];
        $totalIva5 += $doc['iva_5'];
        $totalBase10 += $doc['base_10'];
        $totalIva10 += $doc['iva_10'];
        $totalGeneral += $doc['total'];
    }
    
    $pdf = new BasePDF('P', 'mm', 'A4', [
        'titulo'    => 'Libro de Compras (IVA Compras)',
        'subtitulo' => 'Período: ' . date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta)),
    ]);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    // Mostrar filtros aplicados si existen
    if ($proveedorId !== null || $tipoDoc !== null || $sucursalId !== null) {
        $pdf->SetFont('Arial', 'I', 8);
        $filtros = [];
        if ($proveedorId !== null) {
            $qProv = $pdo->prepare("SELECT razon_social FROM proveedor WHERE id_proveedor = :id");
            $qProv->execute([':id' => $proveedorId]);
            $prov = $qProv->fetch();
            if ($prov) $filtros[] = 'Proveedor: ' . $prov['razon_social'];
        }
        if ($tipoDoc !== null) {
            $tipos = ['FACTURA' => 'Factura', 'CREDITO' => 'Nota de Crédito', 'DEBITO' => 'Nota de Débito'];
            $filtros[] = 'Tipo: ' . ($tipos[$tipoDoc] ?? $tipoDoc);
        }
        if ($sucursalId !== null) {
            $qSuc = $pdo->prepare("SELECT descripcion_sucursal FROM sucursales WHERE id_sucursal = :id");
            $qSuc->execute([':id' => $sucursalId]);
            $suc = $qSuc->fetch();
            if ($suc) $filtros[] = 'Sucursal: ' . $suc['descripcion_sucursal'];
        }
        if (!empty($filtros)) {
            $pdf->Cell(0, 5, 'Filtros: ' . implode(' | ', $filtros), 0, 1, 'C');
        }
    }
    
    $pdf->Ln(3);

    $anchosLibro = [18, 18, 25, 20, 15, 45, 15, 20, 20, 15, 20, 15, 25];
    $titulosLibro = ['Fecha', 'Tipo', 'N° Doc.', 'Timbrado', 'RUC', 'Proveedor', 'Cond.', 'Exento', 'Base 5%', 'IVA 5%', 'Base 10%', 'IVA 10%', 'Total c/IVA'];
    $alignsLibro = array_fill(0, count($anchosLibro), 'C');
    $alignsLibro[5] = 'L';
    for ($i = 7; $i < count($alignsLibro); $i++) {
        $alignsLibro[$i] = 'R';
    }

    $imprimirEncabezadoLibro = function () use ($pdf, $anchosLibro, $titulosLibro, $alignsLibro) {
        $pdf->tablaEncabezado($anchosLibro, $titulosLibro, $alignsLibro);
        $pdf->SetFont('Arial', '', 6);
    };

    $imprimirEncabezadoLibro();
    $altura = 5;
    $maxY = 270;
    
    foreach ($documentos as $doc) {
        if ($pdf->GetY() > $maxY) {
            $pdf->AddPage();
            $imprimirEncabezadoLibro();
        }
        
        $fechaFormateada = date('d/m/Y', strtotime($doc['fecha']));
        $tipoLabel = $doc['tipo'] === 'FACTURA' ? 'F' : ($doc['tipo'] === 'NOTA_CREDITO' ? 'NC' : 'ND');
        $signo = $doc['signo'] > 0 ? '' : '-';
        
        $pdf->Cell(18, $altura, $fechaFormateada, 1, 0, 'C');
        $pdf->Cell(18, $altura, $tipoLabel, 1, 0, 'C');
        $pdf->Cell(25, $altura, convertir($doc['numero']), 1, 0, 'C');
        $pdf->Cell(20, $altura, $doc['timbrado'], 1, 0, 'C');
        $pdf->Cell(15, $altura, $doc['ruc'], 1, 0, 'C');
        $pdf->Cell(45, $altura, convertir(substr($doc['razon'], 0, 25)), 1, 0, 'L');
        $pdf->Cell(15, $altura, convertir($doc['condicion']), 1, 0, 'C');
        $pdf->Cell(20, $altura, $signo . number_format($doc['exento'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(20, $altura, $signo . number_format($doc['base_5'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(15, $altura, $signo . number_format($doc['iva_5'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(20, $altura, $signo . number_format($doc['base_10'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(15, $altura, $signo . number_format($doc['iva_10'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(25, $altura, $signo . number_format($doc['total'], 0, ',', '.'), 1, 1, 'R');
    }
    
    // Totales
    $pdf->prepararEncabezadoTabla(7);
    $pdf->Cell(152, $altura, 'TOTALES:', 1, 0, 'R', true);
    $pdf->Cell(20, $altura, number_format($totalExento, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(20, $altura, number_format($totalBase5, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(15, $altura, number_format($totalIva5, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(20, $altura, number_format($totalBase10, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(15, $altura, number_format($totalIva10, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(25, $altura, number_format($totalGeneral, 0, ',', '.'), 1, 1, 'R', true);

    $pdf->pieDocumento();
    $pdf->Output('libro_compras_' . str_replace('-', '', $desde) . '_' . str_replace('-', '', $hasta) . '.pdf', 'D');
    
} catch (PDOException $e) {
    die("Error al generar el PDF: " . $e->getMessage());
}

