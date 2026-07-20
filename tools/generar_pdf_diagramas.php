<?php
/**
 * Genera PDFs con diagramas UML (solo imagen + título, sin código PlantUML).
 * Uso: php tools/generar_pdf_diagramas.php
 */
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$diagramBase = $projectRoot . DIRECTORY_SEPARATOR . 'backup bd' . DIRECTORY_SEPARATOR . 'diagramas final';
$fpdfPath = $projectRoot . DIRECTORY_SEPARATOR . 'reporte' . DIRECTORY_SEPARATOR . 'fpdf185' . DIRECTORY_SEPARATOR . 'fpdf.php';

if (!is_file($fpdfPath)) {
    fwrite(STDERR, "No se encontró FPDF en: {$fpdfPath}\n");
    exit(1);
}
if (!is_dir($diagramBase)) {
    fwrite(STDERR, "No se encontró carpeta de diagramas: {$diagramBase}\n");
    exit(1);
}

require_once $fpdfPath;

function pdfText(string $text): string
{
    $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
    return $converted !== false ? $converted : $text;
}

function extractTitleFromTxt(string $pngPath): ?string
{
    $dir = dirname($pngPath);
    $txts = glob($dir . DIRECTORY_SEPARATOR . '*.txt');
    if (!$txts) {
        return null;
    }
    $content = file_get_contents($txts[0]);
    if ($content === false) {
        return null;
    }
    if (preg_match('/^title\s+(.+)$/m', $content, $m)) {
        return trim($m[1]);
    }
    return null;
}

function resolveImage(string $base, string $relative): string
{
    $path = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        throw new RuntimeException("Imagen no encontrada: {$relative}");
    }
    return $path;
}

/** @return list<array{section: string, image: string}> */
function orderedClassDiagrams(): array
{
    return [
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Clases Referenciales/Clase acceso/Clase acceso.png'],
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Clases Referenciales/Clase Usuarios/Clase Usuarios.png'],
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Clases Referenciales/Clase Materia prima/Clase Materia Prima.png'],
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Clases Referenciales/Clase Proveedor/Clase Proveedor.png'],
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Clases Referenciales/Clase unidad de medida/Clase unidad de medida.png'],
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Clases Referenciales/Clase Trabajadores/Clase Trabajadores.png'],
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Clases Referenciales/Clase Inspectores/Clase inspectores.png'],
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Clases Referenciales/Clase Parametros de Control/Clase Parametros de Control.png'],
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Elaborar informes/clase elaborar informes.png'],

        ['section' => 'MÓDULO DE COMPRAS', 'image' => 'Compras/Clase Compras Movimientos/Clase Pedidos de compras/Clase Pedido Compra.png'],
        ['section' => 'MÓDULO DE COMPRAS', 'image' => 'Compras/Clase Compras Movimientos/Clase Presupuesto compras/Clase Presupuesto Compras.png'],
        ['section' => 'MÓDULO DE COMPRAS', 'image' => 'Compras/Clase Compras Movimientos/Clase Orden de compra/Clase Orden de compra.png'],
        ['section' => 'MÓDULO DE COMPRAS', 'image' => 'Compras/Clase Compras Movimientos/Clase Gestionar Compras/Clase Gestionar compras.png'],
        ['section' => 'MÓDULO DE COMPRAS', 'image' => 'Compras/Clase Compras Movimientos/Clase Libro compras/Clase Libro compras.png'],
        ['section' => 'MÓDULO DE COMPRAS', 'image' => 'Compras/Clase Compras Movimientos/Clase Registras Ajustes/Clase Registrar Ajustes.png'],
        ['section' => 'MÓDULO DE COMPRAS', 'image' => 'Compras/Clase Compras Movimientos/Clase Nota de CreditoDebito/Clase Nota de CreditoDebito.png'],
        ['section' => 'MÓDULO DE COMPRAS', 'image' => 'Compras/Clase Compras Movimientos/Clase Nota de Remision/Clase Nota de Remision.png'],

        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Clase Produccion Movimientos/Clase Pedido de Produccion/Clase Pedido de Produccion.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Clase Produccion Movimientos/Clase Pedido de Materia Prima/Clase Pedido de Materia Prima.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Clase Produccion Movimientos/Clase Orden de Produccion/Clase Orden de Produccion.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Clase Produccion Movimientos/Clase Control de Produccion/Clase Control de Produccion.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Clase Produccion Movimientos/Clase Etapa de Produccion/Clase Etapa de Produccion.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Clase Produccion Movimientos/Clase Equipos de trabajo/Clase equipos de trabajo.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Clase Produccion Movimientos/Clase Control de Calidad/Clase Control de Calidad.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Clase Produccion Movimientos/Clase Productos terminados/Clase Productos terminados.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Clase Produccion Movimientos/Clase Registrar Perdidas/Clase Registrar Perdidas.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Clase Produccion Movimientos/Clase Reposicion de Materia Prima/Clase Reposicion de Materia prima.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Clase Produccion Movimientos/Clase Costos de Produccion/Clase Costos de Produccion.png'],
    ];
}

/** @return list<array{section: string, image: string}> */
function orderedSequenceDiagrams(): array
{
    return [
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Secuencia Referenciales/Secuencia Acceso/Secuencia acceso.png'],
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Secuencia Referenciales/Secuencia Usuarios/Secuencia Usuarios.png'],
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Secuencia Referenciales/Secuencia Materia Prima/Secuencia Materia Prima.png'],
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Secuencia Referenciales/Secuencia Proveedor/Secuencia Proveedor.png'],
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Secuencia Referenciales/Secuencia Unidad de medida/Secuencia Unidad de medida.png'],
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Secuencia Referenciales/Secuencia trabajadores/Secuencia Trabajadores.png'],
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Secuencia Referenciales/Secuencia inspectores/Secuencia Inspectores.png'],
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Secuencia Referenciales/Secuencia Parametros de control/Secuencia Parametros de Control.png'],
        ['section' => 'REFERENCIALES', 'image' => 'Referenciales/Elaborar informes/Secuencia elaborar informes.png'],

        ['section' => 'MÓDULO DE COMPRAS', 'image' => 'Compras/Secuencia Compras Movimientos/Secuencia Pedidos de compras/Secuencia Pedidos de compra.png'],
        ['section' => 'MÓDULO DE COMPRAS', 'image' => 'Compras/Secuencia Compras Movimientos/Secuencia Presupuesto compras/Secuencia presupuesto compras.png'],
        ['section' => 'MÓDULO DE COMPRAS', 'image' => 'Compras/Secuencia Compras Movimientos/Secuencia Orden de compras/Secuencia Orden de compras.png'],
        ['section' => 'MÓDULO DE COMPRAS', 'image' => 'Compras/Secuencia Compras Movimientos/Secuencia Gestionar compras/Secuencia Gestionar compras.png'],
        ['section' => 'MÓDULO DE COMPRAS', 'image' => 'Compras/Secuencia Compras Movimientos/Secuencia Libro Compras/Secuencia Libro Compras.png'],
        ['section' => 'MÓDULO DE COMPRAS', 'image' => 'Compras/Secuencia Compras Movimientos/Secuencia Registrar Ajustes/Secuencia Registrar Ajustes.png'],
        ['section' => 'MÓDULO DE COMPRAS', 'image' => 'Compras/Secuencia Compras Movimientos/Secuencia Nota de CreditoDebito/Secuencia Nota de CreditoDebito.png'],
        ['section' => 'MÓDULO DE COMPRAS', 'image' => 'Compras/Secuencia Compras Movimientos/Secuencia Nota de Remision/Secuencia nota de Remision.png'],

        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Secuencia Produccion Movimientos/Secuencia Pedido de Produccion/Secuencia Pedido de Produccion.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Secuencia Produccion Movimientos/Secuencia Pedido de Materia Prima/Secuencia Pedido de Materia Prima.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Secuencia Produccion Movimientos/Secuencia Orden de Produccion/Secuencia Orden de Produccion.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Secuencia Produccion Movimientos/Secuencia Control de Produccion/Secuencia Control de Produccion.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Secuencia Produccion Movimientos/Secuencia Etapa de Produccion/Secuencia Etapa de Produccion.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Secuencia Produccion Movimientos/Secuencia Equipos de Trabajo/Secuencia Equipos de Trabajo.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Secuencia Produccion Movimientos/Secuencia Control de Calidad/Secuencia Control de Calidad.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Secuencia Produccion Movimientos/Secuencia Productos terminados/Secuencia Productos terminados.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Secuencia Produccion Movimientos/Secuencia Registrar Pedidas/Secuencia Registrar Pedidas.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Secuencia Produccion Movimientos/Secuencia Reposicion de Materia Prima/Secuencia Reposicion de Materia Prima.png'],
        ['section' => 'MÓDULO DE PRODUCCIÓN', 'image' => 'Produccion/Secuencia Produccion Movimientos/Secuencia Costos de Produccion/Secuencia Costos de Produccion.png'],
    ];
}

function imageSizeMm(string $imagePath, float $maxW, float $maxH): array
{
    $info = @getimagesize($imagePath);
    if ($info === false) {
        throw new RuntimeException("No se pudo leer imagen: {$imagePath}");
    }
    [$pxW, $pxH] = $info;
    $ratio = $pxW / max(1, $pxH);
    $w = $maxW;
    $h = $w / $ratio;
    if ($h > $maxH) {
        $h = $maxH;
        $w = $h * $ratio;
    }
    return [$w, $h];
}

/**
 * @param list<array{section: string, image: string}> $entries
 */
function buildDiagramPdf(string $base, string $docTitle, array $entries, string $outputPath): void
{
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(false);
    $pdf->SetMargins(15, 15, 15);

    $pageW = 210.0;
    $margin = 15.0;
    $maxImgW = $pageW - ($margin * 2);

    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetXY(15, 110);
    $pdf->Cell(180, 10, pdfText($docTitle), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(180, 8, pdfText('Sistema Emmanuels — Documentación UML'), 0, 1, 'C');
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Ln(6);
    $pdf->Cell(180, 6, pdfText('Orden: Referenciales → Compras → Producción'), 0, 1, 'C');

    $currentSection = '';
    foreach ($entries as $entry) {
        $imgPath = resolveImage($base, $entry['image']);
        $title = extractTitleFromTxt($imgPath) ?? basename($imgPath, '.png');

        $pdf->AddPage();
        if ($entry['section'] !== $currentSection) {
            $currentSection = $entry['section'];
            $pdf->SetFont('Arial', 'B', 13);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetXY($margin, 8);
            $pdf->Cell($maxImgW, 8, pdfText($currentSection), 0, 1, 'C', true);
            $titleYPage = 20.0;
            $imgTop = 36.0;
        } else {
            $titleYPage = 15.0;
            $imgTop = 32.0;
        }

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetXY($margin, $titleYPage);
        $pdf->MultiCell($maxImgW, 6, pdfText($title), 0, 'C');

        $maxH = 297.0 - $imgTop - $margin;
        [$imgW, $imgH] = imageSizeMm($imgPath, $maxImgW, $maxH);
        $x = ($pageW - $imgW) / 2.0;
        $pdf->Image($imgPath, $x, $imgTop, $imgW, $imgH, 'PNG');
    }

    $pdf->Output('F', $outputPath);
}

$outDir = $projectRoot . DIRECTORY_SEPARATOR . 'docs';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

try {
    $claseOut = $outDir . DIRECTORY_SEPARATOR . 'diagramas_clases.pdf';
    $secOut = $outDir . DIRECTORY_SEPARATOR . 'diagramas_secuencia.pdf';

    buildDiagramPdf($diagramBase, 'Diagramas de Clases', orderedClassDiagrams(), $claseOut);
    echo "OK: {$claseOut}\n";

    buildDiagramPdf($diagramBase, 'Diagramas de Secuencia', orderedSequenceDiagrams(), $secOut);
    echo "OK: {$secOut}\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
