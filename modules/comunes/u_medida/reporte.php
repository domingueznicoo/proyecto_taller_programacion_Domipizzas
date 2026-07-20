<?php
// Genera informe PDF de unidades de medida.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php'; 
require_once PROJECT_ROOT . '/reporte/reporte_umedida.php'; 

$pdf = new BasePDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

try {
    // Consulta los datos
    $query = "SELECT id_unidad, unidad_descri FROM unidad_medida ORDER BY id_unidad ASC";
    $statement = $pdo->query($query);

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $pdf->SetX(70); // Ajusta este valor según el ancho de tu página y tabla

        $pdf->Cell(20, 10, $row['id_unidad'], 1, 0, 'C');
        $pdf->Cell(70, 10, convertir($row['unidad_descri']), 1, 1, 'C');
    }

    // Mostrar el PDF en el navegador
    $pdf->Output('I', 'Reporte_UnidadDeMedida.pdf');
} catch (PDOException $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}
