<?php
// Genera informe PDF de presupuestos de compra.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/reporte_presupuesto.php';

if (!isset($_GET['pre_id']) || !ctype_digit($_GET['pre_id'])) {
    die('No se proporcionó un ID de presupuesto válido.');
}
$pre_id = (int) $_GET['pre_id'];

function gs($n) { return number_format((float) $n, 0, ',', '.') . ' Gs'; }

$pdf = new BasePDF('P', 'mm', 'A4', [
    'titulo'    => 'Presupuesto de Compra',
    'subtitulo' => 'Detalle del presupuesto N° ' . $pre_id,
]);
$pdf->AliasNbPages();
$pdf->AddPage();

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $sqlCab = "
        SELECT 
            pc.id_presupuesto_compra,
            to_char(pc.presu_fecha, 'DD/MM/YYYY') AS fecha,
            to_char(pc.presu_fecha, 'HH24:MI') AS hora,
            pc.presu_estado,
            pc.presu_total,
            u.username AS usuario,
            s.descripcion_sucursal AS sucursal,
            pr.razon_social AS proveedor
        FROM presupuesto_compra pc
        JOIN usuarios u ON u.id_usuario = pc.id_usuario
        JOIN sucursales s ON s.id_sucursal = pc.id_sucursal
        JOIN proveedor pr ON pr.id_proveedor = pc.id_proveedor
        WHERE pc.id_presupuesto_compra = :id
        LIMIT 1
    ";
    $stCab = $pdo->prepare($sqlCab);
    $stCab->execute([':id' => $pre_id]);
    $cab = $stCab->fetch();

    if (!$cab) {
        die('No se encontró el presupuesto con el ID proporcionado.');
    }

    $pdf->seccionTitulo('Datos del presupuesto');
    $pdf->filaDato('N° Presupuesto:', (string) $cab['id_presupuesto_compra'], 40);
    $pdf->filaDato('Fecha:', $cab['fecha'] . '  ' . $cab['hora'], 40);
    $pdf->filaDato('Usuario:', $cab['usuario'], 40);
    $pdf->filaDato('Sucursal:', $cab['sucursal'], 40);
    $pdf->filaDato('Proveedor:', $cab['proveedor'], 40);
    $pdf->filaDato('Estado:', $cab['presu_estado'], 40);

    $sqlDet = "
        SELECT 
            mp.materia_prima_descripcion AS producto,
            d.detalle_presu_cantidad AS cantidad,
            d.detalle_presu_precio_compra AS precio,
            COALESCE(d.descuento, 0) AS descuento,
            COALESCE(d.detalle_presu_iva, 0) AS iva_monto,
            ti.iva_descri AS iva_descri
        FROM presupuesto_detalle_compra d
        JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
        LEFT JOIN tipo_iva ti ON ti.iva_id = mp.iva_id
        WHERE d.id_presupuesto_compra = :id
        ORDER BY mp.materia_prima_descripcion
    ";
    $stDet = $pdo->prepare($sqlDet);
    $stDet->execute([':id' => $pre_id]);

    $pdf->seccionTitulo('Detalle de materias primas');

    $anchos = [72, 18, 28, 24, 38];
    $aligns = ['L', 'C', 'R', 'R', 'R'];
    $pdf->tablaEncabezado($anchos, ['Materia prima', 'Cant.', 'Precio unit.', 'IVA', 'Subtotal'], $aligns);

    $totalImporte = 0;
    $totalIva = 0;

    while ($d = $stDet->fetch()) {
        $cant = (int) $d['cantidad'];
        $precio = (float) $d['precio'];
        $descuento = (float) ($d['descuento'] ?? 0);
        $ivaMonto = (float) ($d['iva_monto'] ?? 0);
        $ivaDesc = strtolower(trim((string) ($d['iva_descri'] ?? '')));

        $subtotal = ($cant * $precio) - $descuento;

        if ($ivaMonto > 0) {
            $ivaFila = $ivaMonto;
        } else {
            $precioConDescuento = $cant > 0 ? $precio - ($descuento / $cant) : $precio;
            $precioBaseImponible = $precioConDescuento > 0 ? $precioConDescuento : $precio;
            $ivaUnit = 0;
            if ($ivaDesc === 'iva_10') {
                $ivaUnit = floor($precioBaseImponible / 11);
            } elseif ($ivaDesc === 'iva_5') {
                $ivaUnit = floor($precioBaseImponible / 21);
            }
            $ivaFila = $cant * $ivaUnit;
        }

        $totalImporte += $subtotal;
        $totalIva += $ivaFila;

        $pdf->tablaFila($anchos, [
            $d['producto'],
            number_format($cant, 0, ',', '.'),
            gs($precio),
            gs($ivaFila),
            gs($subtotal),
        ], $aligns);
    }

    $pdf->Ln(3);
    $pdf->filaDato('Total IVA:', gs($totalIva), 140);
    $pdf->filaDato('Total importe:', gs($totalImporte), 140);
    $pdf->filaDato('Total en cabecera:', gs($cab['presu_total']), 140);

    $pdf->pieDocumento();
    $pdf->Output('I', "Presupuesto_{$pre_id}.pdf");
} catch (PDOException $e) {
    echo 'Error en la conexión o consulta: ' . htmlspecialchars($e->getMessage());
    exit;
}
