<?php
// Genera informe PDF de un pedido de compra individual.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/pedido_compras.php';

if (!isset($_GET['ped_id']) || empty($_GET['ped_id'])) {
    die('No se proporcionó un ID de pedido válido.');
}

$ped_id = (int) $_GET['ped_id'];
if ($ped_id <= 0) {
    die('ID de pedido inválido.');
}

$pdf = new BasePDF('P', 'mm', 'A4', [
    'titulo'    => 'Pedido de Compra',
    'subtitulo' => 'Detalle del pedido N° ' . $ped_id,
]);
$pdf->AliasNbPages();
$pdf->AddPage();

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmtGeneral = $pdo->prepare("
        SELECT
            pc.id_pedido_compra,
            to_char(pc.pedido_fecha_emision, 'DD/MM/YYYY') AS pedido_fecha,
            to_char(pc.pedido_fecha_emision, 'HH24:MI') AS pedido_hora,
            pc.pedido_estado,
            COALESCE(pc.pedido_observaciones, '') AS pedido_observaciones,
            us.username AS usuario,
            suc.descripcion_sucursal AS sucursal
        FROM pedidos_compra pc
        JOIN sucursales suc ON pc.id_sucursal = suc.id_sucursal
        JOIN usuarios us ON pc.id_usuario = us.id_usuario
        WHERE pc.id_pedido_compra = :ped_id
        LIMIT 1
    ");
    $stmtGeneral->execute([':ped_id' => $ped_id]);
    $general = $stmtGeneral->fetch(PDO::FETCH_ASSOC);

    if (!$general) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, convertir('No se encontraron datos para este pedido.'), 0, 1, 'C');
        $pdf->Output('I', "Pedido_Compra_{$ped_id}.pdf");
        exit;
    }

    $pdf->seccionTitulo('Datos del pedido');

    $pdf->filaDato('N° Pedido:', (string) $general['id_pedido_compra'], 35);
    $pdf->filaDato('Fecha:', $general['pedido_fecha'] . '  ' . $general['pedido_hora'], 35);
    $pdf->filaDato('Usuario:', $general['usuario'], 35);
    $pdf->filaDato('Sucursal:', $general['sucursal'], 35);
    $pdf->filaDato('Estado:', $general['pedido_estado'], 35);

    if (trim($general['pedido_observaciones']) !== '') {
        $pdf->filaDato('Observaciones:', $general['pedido_observaciones'], 35);
    }

    $stmtDetails = $pdo->prepare("
        SELECT
            mp.id_materia_prima AS codigo,
            mp.materia_prima_descripcion AS materia_prima,
            COALESCE(um.unidad_descri, '-') AS unidad,
            pd.cantidad_pedido AS cantidad
        FROM pedido_detalle_compra pd
        JOIN materia_prima mp ON pd.id_materia_prima = mp.id_materia_prima
        LEFT JOIN unidad_medida um ON um.id_unidad = mp.id_unidad
        WHERE pd.id_pedido_compra = :ped_id
        ORDER BY mp.materia_prima_descripcion ASC
    ");
    $stmtDetails->execute([':ped_id' => $ped_id]);
    $detalles = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

    $pdf->seccionTitulo('Detalle de materias primas');

    if (count($detalles) === 0) {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 8, convertir('No hay materias primas registradas en este pedido.'), 0, 1, 'C');
    } else {
        $anchos = [18, 98, 24, 40];
        $pdf->tablaEncabezado($anchos, ['Cod.', 'Materia prima', 'Unidad', 'Cantidad']);

        $totalCantidad = 0;
        foreach ($detalles as $row) {
            $pdf->tablaFila(
                $anchos,
                [$row['codigo'], $row['materia_prima'], $row['unidad'], number_format((int) $row['cantidad'], 0, ',', '.')]
            );
            $totalCantidad += (int) $row['cantidad'];
        }

        $pdf->tablaTotal($anchos, 'Total unidades solicitadas', number_format($totalCantidad, 0, ',', '.'));
    }

    $pdf->pieDocumento();
    $pdf->Output('I', "Pedido_Compra_{$ped_id}.pdf");
} catch (PDOException $e) {
    echo 'Error en la conexion o consulta: ' . htmlspecialchars($e->getMessage());
    exit;
}
