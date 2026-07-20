<?php
// Genera informe PDF de órdenes de compra.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/orden_compras.php';

if (!isset($_GET['orden_id']) || !ctype_digit($_GET['orden_id'])) {
    die('No se proporcionó un ID de orden válido.');
}
$orden_id = (int) $_GET['orden_id'];

function gs($n) { return number_format((float) $n, 0, ',', '.') . ' Gs'; }

function ivaUnitFromDescri(string $descri, float $precioBase): int {
    $ivaT = strtolower(trim($descri));
    $normalized = preg_replace('/[\s%_\-]/', '', $ivaT);

    if (strpos($normalized, '10') !== false || $ivaT === 'iva_10' || $ivaT === 'iva10') {
        return (int) floor($precioBase / 11);
    }
    if (strpos($normalized, '5') !== false || $ivaT === 'iva_5' || $ivaT === 'iva5') {
        return (int) floor($precioBase / 21);
    }
    return 0;
}

$pdf = new BasePDF('P', 'mm', 'A4', [
    'titulo'    => 'Orden de Compra',
    'subtitulo' => 'Detalle de la orden N° ' . $orden_id,
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
            oc.id_orden_compra,
            TO_CHAR(oc.orden_fecha, 'DD/MM/YYYY') AS fecha,
            TO_CHAR(oc.orden_fecha, 'HH24:MI') AS hora,
            oc.orden_estado,
            oc.orden_total,
            oc.orden_condicion,
            u.username AS usuario,
            s.descripcion_sucursal AS sucursal,
            pr.razon_social AS proveedor,
            oc.id_presupuesto_compra
        FROM orden_de_compra oc
        JOIN usuarios u ON u.id_usuario = oc.id_usuario
        JOIN sucursales s ON s.id_sucursal = oc.id_sucursal
        JOIN proveedor pr ON pr.id_proveedor = oc.id_proveedor
        WHERE oc.id_orden_compra = :id
        LIMIT 1
    ";
    $stCab = $pdo->prepare($sqlCab);
    $stCab->execute([':id' => $orden_id]);
    $cab = $stCab->fetch();

    if (!$cab) {
        die('No se encontró la orden de compra con el ID proporcionado.');
    }

    $pdf->seccionTitulo('Datos de la orden');
    $pdf->filaDato('N° Orden:', (string) $cab['id_orden_compra'], 40);
    $pdf->filaDato('Fecha:', $cab['fecha'] . '  ' . $cab['hora'], 40);
    $pdf->filaDato('Usuario:', $cab['usuario'], 40);
    $pdf->filaDato('Sucursal:', $cab['sucursal'], 40);
    $pdf->filaDato('Proveedor:', $cab['proveedor'], 40);
    $pdf->filaDato('Estado:', $cab['orden_estado'], 40);
    $pdf->filaDato('Condición:', $cab['orden_condicion'], 40);
    $pdf->filaDato('Presupuesto N°:', (string) $cab['id_presupuesto_compra'], 40);

    $sqlDet = "
        SELECT 
            d.id_materia_prima AS codigo,
            mp.materia_prima_descripcion AS producto,
            d.oc_cantidad_compra AS cantidad,
            d.oc_precio_compra AS precio,
            COALESCE(pdc.descuento, 0) AS descuento,
            COALESCE(pdc.detalle_presu_iva, 0) AS iva_monto,
            ti.iva_descri AS iva
        FROM orden_detalle_compra d
        JOIN orden_de_compra oc ON oc.id_orden_compra = d.id_orden_compra
        JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
        LEFT JOIN presupuesto_detalle_compra pdc
          ON pdc.id_presupuesto_compra = oc.id_presupuesto_compra
         AND pdc.id_materia_prima = d.id_materia_prima
        LEFT JOIN tipo_iva ti ON ti.iva_id = mp.iva_id
        WHERE d.id_orden_compra = :id
        ORDER BY mp.materia_prima_descripcion
    ";
    $stDet = $pdo->prepare($sqlDet);
    $stDet->execute([':id' => $orden_id]);

    $pdf->seccionTitulo('Detalle de materias primas');

    $anchos = [14, 52, 14, 26, 24, 22, 28];
    $aligns = ['C', 'L', 'C', 'R', 'R', 'R', 'R'];
    $pdf->tablaEncabezado($anchos, ['Cod.', 'Materia prima', 'Cant.', 'Precio', 'Descuento', 'IVA', 'Subtotal'], $aligns);

    $totalImporte = 0;
    $totalIva = 0;

    while ($d = $stDet->fetch()) {
        $cant = (int) $d['cantidad'];
        $precio = (float) $d['precio'];
        $descuento = (float) ($d['descuento'] ?? 0);
        $ivaMonto = (float) ($d['iva_monto'] ?? 0);

        $subtotal = max(0, (int) floor(($cant * $precio) - $descuento));

        if ($ivaMonto > 0) {
            $ivaFila = (int) floor($ivaMonto);
        } else {
            $precioNeto = $cant > 0 ? $precio - ($descuento / $cant) : $precio;
            $baseImponible = $precioNeto > 0 ? $precioNeto : $precio;
            $ivaFila = $cant * ivaUnitFromDescri((string) $d['iva'], $baseImponible);
        }

        $totalImporte += $subtotal;
        $totalIva += $ivaFila;

        $pdf->tablaFila($anchos, [
            (string) $d['codigo'],
            $d['producto'],
            number_format($cant, 0, ',', '.'),
            gs($precio),
            gs($descuento),
            gs($ivaFila),
            gs($subtotal),
        ], $aligns);
    }

    $pdf->Ln(3);
    $pdf->filaDato('Total IVA:', gs($totalIva), 140);
    $pdf->filaDato('Total importe:', gs($totalImporte), 140);
    $pdf->filaDato('Total en cabecera:', gs($cab['orden_total']), 140);

    $pdf->pieDocumento();
    $pdf->Output('I', "OrdenCompra_{$orden_id}.pdf");
} catch (PDOException $e) {
    echo 'Error en la conexión o consulta: ' . htmlspecialchars($e->getMessage());
    exit;
}
