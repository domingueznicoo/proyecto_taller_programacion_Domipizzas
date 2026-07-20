<?php
/**
 * Cálculos compartidos del módulo Compras (presupuesto, OC, factura).
 */

function compras_cargar_mapa_descuentos_oc(PDO $pdo, int $id_oc): array
{
    if ($id_oc <= 0) {
        return [];
    }
    $st = $pdo->prepare("
        SELECT
            d.id_materia_prima,
            d.oc_cantidad_compra,
            d.oc_precio_compra,
            COALESCE(pdc.descuento, 0)::numeric AS descuento
        FROM orden_detalle_compra d
        JOIN orden_de_compra oc ON oc.id_orden_compra = d.id_orden_compra
        LEFT JOIN presupuesto_detalle_compra pdc
          ON pdc.id_presupuesto_compra = oc.id_presupuesto_compra
         AND pdc.id_materia_prima = d.id_materia_prima
        WHERE d.id_orden_compra = :oc
    ");
    $st->execute([':oc' => $id_oc]);
    $map = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $mpId = (int)$row['id_materia_prima'];
        $map[$mpId] = [
            'cant_oc'   => (int)$row['oc_cantidad_compra'],
            'precio'    => (int)$row['oc_precio_compra'],
            'descuento' => (float)$row['descuento'],
        ];
    }
    return $map;
}

function compras_descuento_aplicado(array $ocItem, int $cant): int
{
    $descFull = (float)($ocItem['descuento'] ?? 0);
    $cantOc   = (int)($ocItem['cant_oc'] ?? 0);
    if ($cantOc > 0 && $cant < $cantOc) {
        return (int)floor($descFull * $cant / $cantOc);
    }
    return (int)floor($descFull);
}

function compras_subtotal_linea(int $cant, int $precio, array $ocItem): int
{
    $desc = compras_descuento_aplicado($ocItem, $cant);
    return max(0, ($cant * $precio) - $desc);
}

function compras_iva_divisor_por_descri(string $descri): int
{
    $t = strtolower(trim($descri));
    $n = preg_replace('/[\s%_\-]/', '', $t);
    if (strpos($n, '10') !== false) {
        return 11;
    }
    if (strpos($n, '5') !== false) {
        return 21;
    }
    return 0;
}

function compras_iva_linea(int $cant, int $precio, array $ocItem, string $ivaDescri): int
{
    $desc = compras_descuento_aplicado($ocItem, $cant);
    $base = ($cant > 0) ? ($precio - ($desc / $cant)) : (float)$precio;
    if ($base <= 0) {
        $base = (float)$precio;
    }
    $div = compras_iva_divisor_por_descri($ivaDescri);
    if ($div <= 0) {
        return 0;
    }
    $ivaUnit = intdiv((int)floor($base), $div);
    return $ivaUnit * $cant;
}

function compras_calcular_total_factura_neto(PDO $pdo, int $factId): array
{
    $st = $pdo->prepare("
        SELECT f.id_orden_compra
        FROM factura_compra f
        WHERE f.id_factura_compra = :id
        LIMIT 1
    ");
    $st->execute([':id' => $factId]);
    $idOc = (int)$st->fetchColumn();
    if ($idOc <= 0) {
        return ['total' => 0, 'iva' => 0, 'lineas' => []];
    }

    $mapa = compras_cargar_mapa_descuentos_oc($pdo, $idOc);
    $stDet = $pdo->prepare("
        SELECT d.id_materia_prima, d.fac_cantidad, d.fac_precio, ti.iva_descri
        FROM factura_detalle_compra d
        JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
        LEFT JOIN tipo_iva ti ON ti.iva_id = mp.iva_id
        WHERE d.id_factura_compra = :f
    ");
    $stDet->execute([':f' => $factId]);

    $total = 0;
    $iva   = 0;
    $lineas = [];
    foreach ($stDet->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $mpId = (int)$row['id_materia_prima'];
        $cant = (int)$row['fac_cantidad'];
        $prec = (int)$row['fac_precio'];
        $ocItem = $mapa[$mpId] ?? ['cant_oc' => $cant, 'precio' => $prec, 'descuento' => 0.0];
        $sub = compras_subtotal_linea($cant, $prec, $ocItem);
        $ivaL = compras_iva_linea($cant, $prec, $ocItem, (string)($row['iva_descri'] ?? ''));
        $total += $sub;
        $iva += $ivaL;
        $lineas[] = [
            'id_materia_prima' => $mpId,
            'cant' => $cant,
            'precio' => $prec,
            'descuento' => compras_descuento_aplicado($ocItem, $cant),
            'subtotal' => $sub,
            'iva' => $ivaL,
        ];
    }
    return ['total' => $total, 'iva' => $iva, 'lineas' => $lineas];
}

function compras_calcular_total_presupuesto(PDO $pdo, int $preId): int
{
    $st = $pdo->prepare("
        SELECT detalle_presu_cantidad, detalle_presu_precio_compra, COALESCE(descuento, 0) AS descuento
        FROM presupuesto_detalle_compra
        WHERE id_presupuesto_compra = :id
    ");
    $st->execute([':id' => $preId]);
    $total = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cant = (int)$row['detalle_presu_cantidad'];
        $prec = (int)$row['detalle_presu_precio_compra'];
        $desc = (int)floor((float)$row['descuento']);
        $total += max(0, ($cant * $prec) - $desc);
    }
    return $total;
}

function compras_calcular_total_oc(PDO $pdo, int $ocId): int
{
    $mapa = compras_cargar_mapa_descuentos_oc($pdo, $ocId);
    $st = $pdo->prepare("
        SELECT id_materia_prima, oc_cantidad_compra, oc_precio_compra
        FROM orden_detalle_compra
        WHERE id_orden_compra = :id
    ");
    $st->execute([':id' => $ocId]);
    $total = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $mpId = (int)$row['id_materia_prima'];
        $cant = (int)$row['oc_cantidad_compra'];
        $prec = (int)$row['oc_precio_compra'];
        $ocItem = $mapa[$mpId] ?? ['cant_oc' => $cant, 'precio' => $prec, 'descuento' => 0.0];
        $total += compras_subtotal_linea($cant, $prec, $ocItem);
    }
    return $total;
}
