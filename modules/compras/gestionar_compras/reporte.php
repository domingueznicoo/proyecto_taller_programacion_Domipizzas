<?php
// Genera PDF de factura de compra con formato paraguayo (timbrado, IVA por columna).
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/factura_paraguaya_pdf.php';

if (!isset($_GET['fac_id']) || !ctype_digit($_GET['fac_id'])) {
    die('No se proporcionó un ID de factura válido.');
}
$fac_id = (int) $_GET['fac_id'];

function ivaUnitFromDescri(string $descri, float $precioBase): int
{
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

function clasificarIva(string $descri): string
{
    $ivaT = strtolower(trim($descri));
    $normalized = preg_replace('/[\s%_\-]/', '', $ivaT);
    if (strpos($normalized, '10') !== false) {
        return '10';
    }
    if (strpos($normalized, '5') !== false) {
        return '5';
    }
    return 'exento';
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $sqlCab = "
        SELECT 
            f.id_factura_compra,
            f.numero_factura,
            f.timbrado,
            to_char(f.fact_fecha_compra, 'DD/MM/YYYY') AS fecha_emision,
            to_char(f.fact_fecha_compra, 'HH24:MI') AS hora,
            f.fac_total,
            f.fac_estado,
            f.fac_plazo,
            f.fac_remision,
            COALESCE(f.tipo_operacion, 'CONTADO') AS tipo_compra,
            s.descripcion_sucursal AS sucursal,
            pr.razon_social AS proveedor,
            pr.ruc_proveedor,
            pr.direccion_proveedor,
            pr.telefono_proveedor,
            pr.email_proveedor,
            f.id_orden_compra,
            COALESCE(nr.id_nota_remision, 0) AS id_nota_remision_compra
        FROM factura_compra f
        JOIN sucursales s ON s.id_sucursal = f.id_sucursal
        JOIN orden_de_compra oc ON oc.id_orden_compra = f.id_orden_compra
        JOIN proveedor pr ON pr.id_proveedor = oc.id_proveedor
        LEFT JOIN nota_remision_compra nr ON nr.id_factura_compra = f.id_factura_compra
        WHERE f.id_factura_compra = :id
        LIMIT 1
    ";
    $stCab = $pdo->prepare($sqlCab);
    $stCab->execute([':id' => $fac_id]);
    $cab = $stCab->fetch();

    if (!$cab) {
        die('No se encontró la factura de compra con el ID proporcionado.');
    }

    $sqlDet = "
        SELECT 
            mp.materia_prima_descripcion AS producto,
            d.fac_cantidad               AS cantidad,
            d.fac_precio                 AS precio,
            COALESCE(d.fac_iva, 0)       AS iva_monto,
            COALESCE(pdc.descuento, 0)   AS descuento,
            COALESCE(odc.oc_cantidad_compra, d.fac_cantidad) AS cant_oc,
            ti.iva_descri                AS iva_desc
        FROM factura_detalle_compra d
        JOIN factura_compra f ON f.id_factura_compra = d.id_factura_compra
        JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
        LEFT JOIN tipo_iva ti ON ti.iva_id = mp.iva_id
        LEFT JOIN orden_de_compra oc ON oc.id_orden_compra = f.id_orden_compra
        LEFT JOIN presupuesto_detalle_compra pdc
          ON pdc.id_presupuesto_compra = oc.id_presupuesto_compra
         AND pdc.id_materia_prima = d.id_materia_prima
        LEFT JOIN orden_detalle_compra odc
          ON odc.id_orden_compra = f.id_orden_compra
         AND odc.id_materia_prima = d.id_materia_prima
        WHERE d.id_factura_compra = :id
        ORDER BY mp.materia_prima_descripcion
    ";
    $stDet = $pdo->prepare($sqlDet);
    $stDet->execute([':id' => $fac_id]);

    $filasPdf    = [];
    $subExentas  = 0;
    $subGrav5    = 0;
    $subGrav10   = 0;
    $totalDesc   = 0;
    $totalIva5   = 0;
    $totalIva10  = 0;
    $base5       = 0;
    $base10      = 0;
    $totalImporte = 0;

    while ($d = $stDet->fetch()) {
        $cant     = (int) $d['cantidad'];
        $cantOc   = max(1, (int) $d['cant_oc']);
        $precio   = (float) $d['precio'];
        $descFull = (float) ($d['descuento'] ?? 0);
        $descuento = ($cant < $cantOc)
            ? (int) floor($descFull * $cant / $cantOc)
            : (int) floor($descFull);

        $bruto    = $cant * $precio;
        $subtotal = max(0, (int) floor($bruto - $descuento));
        $tipoIva  = clasificarIva((string) ($d['iva_desc'] ?? ''));

        $ivaMontoGuardado = (int) ($d['iva_monto'] ?? 0);
        if ($ivaMontoGuardado > 0) {
            $ivaFila = $ivaMontoGuardado;
        } else {
            $precioNeto = $cant > 0 ? $precio - ($descuento / $cant) : $precio;
            $baseUnit   = $precioNeto > 0 ? $precioNeto : $precio;
            $ivaFila    = $cant * ivaUnitFromDescri((string) $d['iva_desc'], $baseUnit);
        }

        $exentas = $grav5 = $grav10 = '';
        if ($tipoIva === 'exento') {
            $subExentas += $subtotal;
            $exentas = fpy_gs($subtotal);
        } elseif ($tipoIva === '5') {
            $subGrav5 += $subtotal;
            $grav5 = fpy_gs($subtotal);
            $totalIva5 += $ivaFila;
            $base5 += max(0, $subtotal - $ivaFila);
        } else {
            $subGrav10 += $subtotal;
            $grav10 = fpy_gs($subtotal);
            $totalIva10 += $ivaFila;
            $base10 += max(0, $subtotal - $ivaFila);
        }

        $totalDesc    += $descuento;
        $totalImporte += $subtotal;

        $filasPdf[] = [
            'cant'      => (string) $cant,
            'producto'  => $d['producto'],
            'precio'    => fpy_gs($precio),
            'descuento' => $descuento > 0 ? fpy_gs($descuento) : '—',
            'exentas'   => $exentas ?: '—',
            'grav5'     => $grav5 ?: '—',
            'grav10'    => $grav10 ?: '—',
        ];
    }

    $totalIva = $totalIva5 + $totalIva10;

    $pdf = new FacturaParaguayaPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $pdf->cabeceraFactura(
        [
            'razon'     => $cab['proveedor'],
            'ruc'       => $cab['ruc_proveedor'],
            'direccion' => $cab['direccion_proveedor'] ?? '',
            'telefono'  => $cab['telefono_proveedor'] ?? '',
            'email'     => $cab['email_proveedor'] ?? '',
        ],
        [
            'timbrado'  => $cab['timbrado'],
            'numero'    => $cab['numero_factura'],
            'fecha'     => $cab['fecha_emision'],
            'condicion' => strtoupper($cab['tipo_compra']),
            'plazo'     => $cab['fac_plazo'] ?? '',
            'oc'        => $cab['id_orden_compra'],
            'nr'        => ((int) $cab['fac_remision'] === 1) ? $cab['id_nota_remision_compra'] : null,
        ],
        [
            'razon'    => 'DomiPizzas',
            'ruc'      => '',
            'direccion'=> 'Ruta Departamental D027, San Lorenzo - Paraguay',
            'sucursal' => $cab['sucursal'],
        ]
    );

    $pdf->tablaDetalle($filasPdf);

    $nota = 'Estado interno: ' . $cab['fac_estado']
        . ' | Registrado: ' . $cab['fecha_emision'] . ' ' . $cab['hora']
        . ' | Comprobante del proveedor recibido por la empresa.';

    $pdf->bloqueTotales([
        'sub_exentas' => $subExentas,
        'sub_grav5'   => $subGrav5,
        'sub_grav10'  => $subGrav10,
        'descuento'   => $totalDesc,
        'total'       => (float) $cab['fac_total'],
        'iva5'        => $totalIva5,
        'iva10'       => $totalIva10,
        'iva_total'   => $totalIva,
        'base5'       => $base5,
        'base10'      => $base10,
        'nota'        => $nota,
    ]);

    $pdf->Output('I', 'FacturaCompra_' . $fac_id . '.pdf');
} catch (PDOException $e) {
    echo 'Error en la conexión o consulta: ' . htmlspecialchars($e->getMessage());
    exit;
}
