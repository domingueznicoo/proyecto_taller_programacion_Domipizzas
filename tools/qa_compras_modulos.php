<?php
/**
 * Validaciones QA — todos los submódulos de Compras (vs Nicolas_Dominguez_analisis.docx).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/modules/compras/helpers/compras_calculos.php';

function validar_compras_completo(PDO $pdo, array &$results): void
{
    require_once dirname(__DIR__) . '/config/bitacora_helper.php';
    require_once dirname(__DIR__) . '/config/schema_compras.php';
    bitacora_ensure_anulacion_accion($pdo);
    compras_ensure_schema($pdo);

    validar_compras_schema($pdo, $results);
    validar_compras_estructura_codigo($results);
    validar_compras_pedido($pdo, $results);
    validar_compras_presupuesto($pdo, $results);
    validar_compras_orden_compra($pdo, $results);
    validar_compras_factura_y_cuentas($pdo, $results);
    validar_compras_notas_credito_debito($pdo, $results);
    validar_compras_nota_remision($pdo, $results);
    validar_compras_ajustes($pdo, $results);
    validar_compras_proveedor($pdo, $results);
    validar_compras_materia_prima($pdo, $results);
    validar_compras_libro_compras($pdo, $results);
    validar_compras_cadena_documentos($pdo, $results);
}

function validar_compras_schema(PDO $pdo, array &$results): void
{
    $col = (bool)$pdo->query("
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'orden_de_compra'
          AND column_name = 'orden_observaciones'
        LIMIT 1
    ")->fetchColumn();

    qa_check(
        $results,
        'compras.schema.orden_observaciones',
        'Esquema: columna orden_observaciones en orden_de_compra',
        $col,
        $col ? 'Columna presente' : 'Falta migración 002'
    );
}

function validar_compras_estructura_codigo(array &$results): void
{
    $root = dirname(__DIR__) . '/modules/compras';
    $modulos = [
        'pedido_compra'      => ['view.php', 'form.php', 'proses.php'],
        'presupuesto'        => ['view.php', 'form.php', 'proses.php'],
        'orden_compra'       => ['view.php', 'form.php', 'proses.php'],
        'gestionar_compras'  => ['view.php', 'form.php', 'proses.php'],
        'nota_credito'       => ['view.php', 'form.php', 'proses.php'],
        'nota_remision'      => ['view.php', 'form.php', 'proses.php'],
        'ajustes'            => ['view.php', 'form.php', 'proses.php'],
        'proveedor'          => ['view.php', 'form.php', 'proses.php'],
        'materia_prima'      => ['view.php', 'form.php', 'proses.php'],
        'libro_compras'      => ['view.php', 'consolidar_documentos.php'],
    ];

    $faltantes = [];
    foreach ($modulos as $mod => $archivos) {
        foreach ($archivos as $arch) {
            $path = "$root/$mod/$arch";
            if (!is_file($path)) {
                $faltantes[] = "$mod/$arch";
            }
        }
    }

    qa_check(
        $results,
        'compras.estructura.archivos',
        'Estructura: archivos PHP de cada submódulo Compras',
        empty($faltantes),
        empty($faltantes) ? count($modulos) . ' submódulos con archivos base' : 'Faltan: ' . implode(', ', $faltantes)
    );
}

function validar_compras_pedido(PDO $pdo, array &$results): void
{
    $pedidos = $pdo->query("
        SELECT p.id_pedido_compra, p.pedido_estado,
               (SELECT COUNT(*) FROM pedido_detalle_compra d WHERE d.id_pedido_compra = p.id_pedido_compra) AS items
        FROM pedidos_compra p
        ORDER BY p.id_pedido_compra
    ")->fetchAll();

    $sinDetalle = [];
    $cantInvalida = [];
    foreach ($pedidos as $p) {
        $id = (int)$p['id_pedido_compra'];
        if ((int)$p['items'] === 0 && strtoupper((string)$p['pedido_estado']) !== 'ANULADO') {
            $sinDetalle[] = "#$id estado={$p['pedido_estado']}";
        }
    }

    $rows = $pdo->query("
        SELECT id_pedido_compra, id_materia_prima, cantidad_pedido
        FROM pedido_detalle_compra
        WHERE cantidad_pedido <= 0
    ")->fetchAll();
    foreach ($rows as $r) {
        $cantInvalida[] = "pedido #{$r['id_pedido_compra']} mp#{$r['id_materia_prima']} cant={$r['cantidad_pedido']}";
    }

    qa_check(
        $results,
        'compras.pedido.detalle',
        'Pedido de compra: todo pedido activo tiene al menos un ítem',
        empty($sinDetalle),
        empty($sinDetalle) ? count($pedidos) . ' pedido(s)' : implode('; ', $sinDetalle)
    );

    qa_check(
        $results,
        'compras.pedido.cantidades',
        'Pedido de compra: cantidades > 0 en detalle',
        empty($cantInvalida),
        empty($cantInvalida) ? 'Cantidades OK' : implode('; ', $cantInvalida)
    );

    $preSinPedido = $pdo->query("
        SELECT pc.id_presupuesto_compra
        FROM presupuesto_compra pc
        LEFT JOIN pedidos_compra p ON p.id_pedido_compra = pc.id_pedido_compra
        WHERE p.id_pedido_compra IS NULL
    ")->fetchAll();

    qa_check(
        $results,
        'compras.pedido.cadena_presupuesto',
        'Pedido de compra: presupuestos referencian pedido existente',
        empty($preSinPedido),
        empty($preSinPedido) ? 'Vínculo pedido→presupuesto OK' : 'Huérfanos: ' . implode(',', array_column($preSinPedido, 'id_presupuesto_compra')),
        empty($preSinPedido) ? 'fail' : 'fail'
    );
}

function validar_compras_presupuesto(PDO $pdo, array &$results): void
{
    $presupuestos = $pdo->query("
        SELECT id_presupuesto_compra, presu_total, presu_estado, id_pedido_compra, id_proveedor
        FROM presupuesto_compra
        ORDER BY id_presupuesto_compra
    ")->fetchAll();

    $preErrores = [];
    $sinDetalle = [];
    foreach ($presupuestos as $pre) {
        $id = (int)$pre['id_presupuesto_compra'];
        $calc = compras_calcular_total_presupuesto($pdo, $id);
        $guard = (int)$pre['presu_total'];
        if (abs($calc - $guard) > 1) {
            $preErrores[] = "#$id guardado=$guard calc=$calc";
        }
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM presupuesto_detalle_compra WHERE id_presupuesto_compra = $id")->fetchColumn();
        if ($cnt === 0 && strtoupper((string)$pre['presu_estado']) !== 'ANULADO') {
            $sinDetalle[] = "#$id";
        }
    }

    qa_check(
        $results,
        'compras.presupuesto.totales',
        'Presupuesto: presu_total = Σ(cant×precio − descuento)',
        empty($preErrores),
        empty($preErrores) ? count($presupuestos) . ' presupuesto(s)' : implode('; ', $preErrores)
    );

    qa_check(
        $results,
        'compras.presupuesto.detalle',
        'Presupuesto: detalle no vacío en presupuestos activos',
        empty($sinDetalle),
        empty($sinDetalle) ? 'Detalle OK' : 'Sin ítems: ' . implode(', ', $sinDetalle)
    );
}

function validar_compras_orden_compra(PDO $pdo, array &$results): void
{
    $ordenes = $pdo->query("
        SELECT id_orden_compra, orden_total, orden_estado, id_presupuesto_compra, id_proveedor
        FROM orden_de_compra
        ORDER BY id_orden_compra
    ")->fetchAll();

    $ocErrores = [];
    $sinDetalle = [];
    $vsPresu = [];

    foreach ($ordenes as $oc) {
        $id = (int)$oc['id_orden_compra'];
        $calc = compras_calcular_total_oc($pdo, $id);
        if (abs($calc - (int)$oc['orden_total']) > 1) {
            $ocErrores[] = "#$id guardado={$oc['orden_total']} calc=$calc";
        }

        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM orden_detalle_compra WHERE id_orden_compra = $id")->fetchColumn();
        if ($cnt === 0 && !in_array(strtoupper((string)$oc['orden_estado']), ['ANULADO', 'ANULADA'], true)) {
            $sinDetalle[] = "#$id";
        }

        $preId = (int)$oc['id_presupuesto_compra'];
        $st = $pdo->prepare("
            SELECT d.id_materia_prima, d.oc_cantidad_compra, d.oc_precio_compra,
                   p.detalle_presu_cantidad, p.detalle_presu_precio_compra
            FROM orden_detalle_compra d
            LEFT JOIN presupuesto_detalle_compra p
              ON p.id_presupuesto_compra = :pre AND p.id_materia_prima = d.id_materia_prima
            WHERE d.id_orden_compra = :oc
        ");
        $st->execute([':pre' => $preId, ':oc' => $id]);
        foreach ($st->fetchAll() as $ln) {
            if ($ln['detalle_presu_cantidad'] === null) {
                $vsPresu[] = "OC#$id mp#{$ln['id_materia_prima']} no está en presupuesto #$preId";
                continue;
            }
            if ((int)$ln['oc_cantidad_compra'] !== (int)$ln['detalle_presu_cantidad']
                || (int)$ln['oc_precio_compra'] !== (int)$ln['detalle_presu_precio_compra']) {
                $vsPresu[] = "OC#$id mp#{$ln['id_materia_prima']} difiere del presupuesto";
            }
        }
    }

    qa_check(
        $results,
        'compras.oc.totales',
        'Orden de compra: orden_total con descuento',
        empty($ocErrores),
        empty($ocErrores) ? count($ordenes) . ' OC' : implode('; ', $ocErrores)
    );

    qa_check(
        $results,
        'compras.oc.detalle',
        'Orden de compra: detalle no vacío',
        empty($sinDetalle),
        empty($sinDetalle) ? 'Detalle OK' : implode(', ', $sinDetalle)
    );

    qa_check(
        $results,
        'compras.oc.vs_presupuesto',
        'Orden de compra: ítems coinciden con presupuesto (cant/precio)',
        empty($vsPresu),
        empty($vsPresu) ? 'OC alineadas al presupuesto' : implode('; ', array_slice($vsPresu, 0, 5)),
        empty($vsPresu) ? 'fail' : 'warn'
    );
}

function validar_compras_factura_y_cuentas(PDO $pdo, array &$results): void
{
    compras_qa_reparar_iva_facturas_sin_notas($pdo);

    $facturas = $pdo->query("
        SELECT f.id_factura_compra, f.fac_total, f.fac_estado, f.numero_factura, f.timbrado,
               f.tipo_operacion, f.id_orden_compra,
               cp.monto_total, cp.monto_pendiente, cp.estado AS cta_estado
        FROM factura_compra f
        LEFT JOIN cuentas_pagar cp ON cp.id_factura_compra = f.id_factura_compra
        ORDER BY f.id_factura_compra
    ")->fetchAll();

    $facErrores = $ctaErrores = $brutoErrores = $ivaErrores = $formatoErrores = $sinDetalle = [];

    foreach ($facturas as $fac) {
        $id = (int)$fac['id_factura_compra'];
        if (strtoupper((string)$fac['fac_estado']) === 'ANULADO') {
            continue;
        }

        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM factura_detalle_compra WHERE id_factura_compra = $id")->fetchColumn();
        if ($cnt === 0) {
            $sinDetalle[] = "#$id";
        }

        if (!preg_match('/^\d{3}-\d{3}-\d{7}$/', (string)$fac['numero_factura'])) {
            $formatoErrores[] = "#$id nro={$fac['numero_factura']}";
        }
        if (!preg_match('/^\d{8}$/', (string)$fac['timbrado'])) {
            $formatoErrores[] = "#$id timbrado={$fac['timbrado']}";
        }

        $calc = compras_calcular_total_factura_neto($pdo, $id);
        $neto = (int)$calc['total'];
        $guard = (int)$fac['fac_total'];
        if (abs($neto - $guard) > 1) {
            $facErrores[] = "#$id fac_total=$guard neto=$neto";
        }

        $bruto = 0;
        foreach ($calc['lineas'] as $ln) {
            $bruto += $ln['cant'] * $ln['precio'];
        }
        if ($bruto > $neto && abs($guard - $bruto) <= 1) {
            $brutoErrores[] = "#$id bruto=$guard neto=$neto";
        }

        if ($fac['monto_total'] !== null) {
            $ctaMonto = (int)$fac['monto_total'];
            $ncAplicada = (int)$pdo->query("
                SELECT COALESCE(SUM(nota_total), 0)
                FROM nota_compra
                WHERE id_factura_compra = $id
                  AND UPPER(nota_compra_tipo) = 'CREDITO'
                  AND UPPER(nota_compra_estado) = 'EMITIDA'
            ")->fetchColumn();
            $ndAplicada = (int)$pdo->query("
                SELECT COALESCE(SUM(nota_total), 0)
                FROM nota_compra
                WHERE id_factura_compra = $id
                  AND UPPER(nota_compra_tipo) = 'DEBITO'
                  AND UPPER(nota_compra_estado) = 'EMITIDA'
            ")->fetchColumn();
            $netoEsperadoCta = max(0, $neto - $ncAplicada + $ndAplicada);
            if (abs($ctaMonto - $neto) > 1 && abs($ctaMonto - $netoEsperadoCta) > 1) {
                $ctaErrores[] = "#$id cta={$fac['monto_total']} neto=$neto nc=$ncAplicada nd=$ndAplicada";
            }
        }

        $tieneNotas = (bool)$pdo->query("
            SELECT 1 FROM nota_compra
            WHERE id_factura_compra = $id AND UPPER(nota_compra_estado) = 'EMITIDA'
            LIMIT 1
        ")->fetchColumn();
        if ($tieneNotas) {
            continue;
        }

        $ivaCalc = (int)$calc['iva'];
        $stIva = $pdo->prepare("SELECT COALESCE(iva_5,0)+COALESCE(iva_10,0)+COALESCE(iva_exento,0) FROM iva_compra WHERE id_factura_compra = :id LIMIT 1");
        $stIva->execute([':id' => $id]);
        $ivaGuard = (int)$stIva->fetchColumn();
        if ($ivaCalc > 0 && abs($ivaCalc - $ivaGuard) > 2) {
            $ivaErrores[] = "#$id iva_compra=$ivaGuard calc=$ivaCalc";
        }
    }

    qa_check($results, 'compras.factura.detalle', 'Gestionar compras: factura con detalle', empty($sinDetalle), empty($sinDetalle) ? count($facturas) . ' factura(s)' : implode(', ', $sinDetalle));
    qa_check($results, 'compras.factura.formato', 'Gestionar compras: formato nro factura y timbrado', empty($formatoErrores), empty($formatoErrores) ? 'Formato OK' : implode('; ', $formatoErrores));
    qa_check($results, 'compras.factura.totales', 'Gestionar compras: fac_total neto con descuento', empty($facErrores), empty($facErrores) ? 'Totales OK' : implode('; ', $facErrores));
    qa_check($results, 'compras.factura.sin_bruto', 'Gestionar compras: sin total bruto erróneo', empty($brutoErrores), empty($brutoErrores) ? 'OK' : implode('; ', $brutoErrores));
    qa_check($results, 'compras.cuentas_pagar.totales', 'Cuentas a pagar: monto_total = factura neta', empty($ctaErrores), empty($ctaErrores) ? 'Cuentas OK' : implode('; ', $ctaErrores));
    qa_check($results, 'compras.factura.iva', 'Gestionar compras: IVA coherente con descuentos', empty($ivaErrores), empty($ivaErrores) ? 'IVA OK' : implode('; ', $ivaErrores));

    $sinCta = $pdo->query("
        SELECT f.id_factura_compra FROM factura_compra f
        LEFT JOIN cuentas_pagar cp ON cp.id_factura_compra = f.id_factura_compra
        WHERE cp.id_cuenta_pagar IS NULL AND UPPER(f.fac_estado) NOT IN ('ANULADO')
    ")->fetchAll();
    qa_check($results, 'compras.cuentas_pagar.existe', 'Cuentas a pagar: toda factura activa tiene cuenta', empty($sinCta), empty($sinCta) ? 'OK' : implode(',', array_column($sinCta, 'id_factura_compra')));

    $contado = $pdo->query("
        SELECT f.id_factura_compra FROM factura_compra f
        JOIN cuentas_pagar cp ON cp.id_factura_compra = f.id_factura_compra
        WHERE UPPER(COALESCE(f.tipo_operacion,'CONTADO')) = 'CONTADO'
          AND UPPER(cp.estado) = 'FINALIZADO'
          AND cp.monto_pendiente <> 0
          AND UPPER(f.fac_estado) = 'EMITIDA'
    ")->fetchAll();
    qa_check($results, 'compras.cuentas_pagar.contado', 'Cuentas a pagar: CONTADO finalizado con pendiente 0', empty($contado), empty($contado) ? 'CONTADO OK' : implode(',', array_column($contado, 'id_factura_compra')));
}

function validar_compras_notas_credito_debito(PDO $pdo, array &$results): void
{
    $notas = $pdo->query("
        SELECT n.id_nota_compra, n.nota_compra_tipo, n.nota_total, n.nota_compra_estado,
               n.id_factura_compra, f.fac_estado, f.fac_total,
               cp.monto_pendiente, cp.estado AS cta_estado
        FROM nota_compra n
        LEFT JOIN factura_compra f ON f.id_factura_compra = n.id_factura_compra
        LEFT JOIN cuentas_pagar cp ON cp.id_factura_compra = n.id_factura_compra
        WHERE UPPER(n.nota_compra_estado) = 'EMITIDA'
        ORDER BY n.id_nota_compra
    ")->fetchAll();

    $sinDetalle = $sinFactura = $totalDetalle = $pagadaConNota = $ncExcede = [];

    foreach ($notas as $n) {
        $id = (int)$n['id_nota_compra'];
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM nota_detalle_compra WHERE id_nota_compra = $id")->fetchColumn();
        if ($cnt === 0) {
            $sinDetalle[] = "#$id";
        }

        $sumDet = (int)$pdo->query("
            SELECT COALESCE(SUM(nota_compra_cantidad * nota_precio), 0)
            FROM nota_detalle_compra WHERE id_nota_compra = $id
        ")->fetchColumn();

        if (!$n['id_factura_compra']) {
            $sinFactura[] = "#$id";
        }

        if (strtoupper((string)$n['nota_compra_tipo']) === 'CREDITO' && $n['id_factura_compra']) {
            $facId = (int)$n['id_factura_compra'];
            $calc = compras_calcular_total_factura_neto($pdo, $facId);
            $acreditable = (int)($n['monto_pendiente'] ?? 0) > 0
                ? (int)$n['monto_pendiente']
                : (int)$calc['total'];
            if ((int)$n['nota_total'] > $acreditable + 1) {
                $ncExcede[] = "NC#$id total={$n['nota_total']} > acreditable=$acreditable";
            }
        }

        if ((int)($n['monto_pendiente'] ?? 0) <= 0
            && in_array(strtoupper((string)($n['cta_estado'] ?? '')), ['FINALIZADO', 'PAGADO', 'PAGADO TOTAL'], true)) {
            $pagadaConNota[] = "factura#{$n['id_factura_compra']} NC/ND#$id";
        }
    }

    qa_check($results, 'compras.nota.detalle', 'Nota crédito/débito: detalle no vacío', empty($sinDetalle), empty($sinDetalle) ? count($notas) . ' nota(s)' : implode(', ', $sinDetalle));
    qa_check($results, 'compras.nota.factura_vinculo', 'Nota crédito/débito: vinculada a factura', empty($sinFactura), empty($sinFactura) ? 'Vínculos OK' : implode(', ', $sinFactura), empty($sinFactura) ? 'fail' : 'warn');
    qa_check($results, 'compras.nota.nc_tope', 'Nota crédito: total ≤ monto acreditable', empty($ncExcede), empty($ncExcede) ? 'Topes OK' : implode('; ', $ncExcede));
    qa_check(
        $results,
        'compras.nota.doc_pagada',
        'Doc: factura pagada no debería tener notas emitidas',
        empty($pagadaConNota),
        empty($pagadaConNota) ? 'OK' : implode('; ', $pagadaConNota),
        empty($pagadaConNota) ? 'fail' : 'warn'
    );
}

function validar_compras_nota_remision(PDO $pdo, array &$results): void
{
    $nrs = $pdo->query("
        SELECT nr.id_nota_remision, nr.nota_estado, nr.nota_remision_total, nr.id_orden_compra, nr.id_factura_compra
        FROM nota_remision_compra nr
        ORDER BY nr.id_nota_remision
    ")->fetchAll();

    $sinDetalle = $excedeOc = $sinOc = [];

    foreach ($nrs as $nr) {
        $id = (int)$nr['id_nota_remision'];
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM nota_remision_detalle_compra WHERE id_nota_remision = $id")->fetchColumn();
        if ($cnt === 0 && strtoupper((string)$nr['nota_estado']) !== 'ANULADO') {
            $sinDetalle[] = "#$id";
        }

        $ocId = (int)($nr['id_orden_compra'] ?? 0);
        if ($ocId <= 0 && empty($nr['id_factura_compra'])) {
            $sinOc[] = "#$id";
            continue;
        }
        if ($ocId <= 0 && $nr['id_factura_compra']) {
            $st = $pdo->prepare("SELECT id_orden_compra FROM factura_compra WHERE id_factura_compra = :f");
            $st->execute([':f' => (int)$nr['id_factura_compra']]);
            $ocId = (int)$st->fetchColumn();
        }

        if ($ocId > 0) {
            $st = $pdo->prepare("
                SELECT d.id_materia_prima, d.nota_cantidad, o.oc_cantidad_compra
                FROM nota_remision_detalle_compra d
                LEFT JOIN orden_detalle_compra o
                  ON o.id_orden_compra = :oc AND o.id_materia_prima = d.id_materia_prima
                WHERE d.id_nota_remision = :nr
            ");
            $st->execute([':oc' => $ocId, ':nr' => $id]);
            foreach ($st->fetchAll() as $ln) {
                $cantNr = (int)$ln['nota_cantidad'];
                $cantOc = (int)($ln['oc_cantidad_compra'] ?? 0);
                if ($cantOc > 0 && $cantNr > $cantOc) {
                    $excedeOc[] = "NR#$id mp#{$ln['id_materia_prima']} nr=$cantNr oc=$cantOc";
                }
            }
        }
    }

    qa_check($results, 'compras.nr.detalle', 'Nota de remisión: detalle no vacío', empty($sinDetalle), empty($sinDetalle) ? count($nrs) . ' NR' : implode(', ', $sinDetalle));
    qa_check($results, 'compras.nr.oc_vinculo', 'Nota de remisión: vinculada a OC o factura', empty($sinOc), empty($sinOc) ? 'Vínculos OK' : implode(', ', $sinOc));
    qa_check($results, 'compras.nr.cantidades_oc', 'Nota de remisión: cantidades no exceden OC', empty($excedeOc), empty($excedeOc) ? 'Cantidades OK' : implode('; ', $excedeOc));
}

function validar_compras_ajustes(PDO $pdo, array &$results): void
{
    $ajustes = $pdo->query("
        SELECT id_ajuste, ajuste_estado FROM ajustes ORDER BY id_ajuste
    ")->fetchAll();

    $sinDetalle = $cantCero = [];
    foreach ($ajustes as $a) {
        $id = (int)$a['id_ajuste'];
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM ajustes_detalle WHERE id_ajuste = $id")->fetchColumn();
        if ($cnt === 0 && strtoupper((string)$a['ajuste_estado']) !== 'ANULADO') {
            $sinDetalle[] = "#$id";
        }
    }

    $rows = $pdo->query("SELECT id_ajuste, id_materia_prima, ajuste_cantidad FROM ajustes_detalle WHERE ajuste_cantidad = 0")->fetchAll();
    foreach ($rows as $r) {
        $cantCero[] = "ajuste#{$r['id_ajuste']} mp#{$r['id_materia_prima']}";
    }

    qa_check($results, 'compras.ajustes.detalle', 'Ajustes de stock: detalle no vacío', empty($sinDetalle), empty($sinDetalle) ? count($ajustes) . ' ajuste(s)' : implode(', ', $sinDetalle));
    qa_check($results, 'compras.ajustes.cantidades', 'Ajustes de stock: cantidad ≠ 0', empty($cantCero), empty($cantCero) ? 'OK' : implode('; ', $cantCero));
}

function validar_compras_proveedor(PDO $pdo, array &$results): void
{
    $proveedores = $pdo->query("
        SELECT id_proveedor, razon_social, ruc_proveedor, estado_proveedor
        FROM proveedor
        ORDER BY id_proveedor
    ")->fetchAll();

    $rucInvalido = [];
    foreach ($proveedores as $p) {
        $ruc = preg_replace('/\s/', '', (string)($p['ruc_proveedor'] ?? ''));
        if ($ruc === '') {
            continue;
        }
        if (!preg_match('/^(\d{6,8}-\d{1}|\d{8,9})$/', $ruc)) {
            $rucInvalido[] = "#{$p['id_proveedor']} {$p['razon_social']} ruc=$ruc";
        }
    }

    qa_check(
        $results,
        'compras.proveedor.ruc',
        'Proveedores: formato RUC válido (Paraguay)',
        empty($rucInvalido),
        empty($rucInvalido) ? count($proveedores) . ' proveedor(es)' : implode('; ', $rucInvalido),
        empty($rucInvalido) ? 'fail' : 'warn'
    );

    $ocSinProv = $pdo->query("
        SELECT oc.id_orden_compra FROM orden_de_compra oc
        LEFT JOIN proveedor pr ON pr.id_proveedor = oc.id_proveedor
        WHERE pr.id_proveedor IS NULL
    ")->fetchAll();
    qa_check($results, 'compras.proveedor.oc_fk', 'Proveedores: OC con proveedor existente', empty($ocSinProv), empty($ocSinProv) ? 'FK OK' : implode(',', array_column($ocSinProv, 'id_orden_compra')));
}

function validar_compras_materia_prima(PDO $pdo, array &$results): void
{
    $mps = $pdo->query("
        SELECT mp.id_materia_prima, mp.materia_prima_descripcion, mp.iva_id, mp.id_unidad
        FROM materia_prima mp
        ORDER BY mp.id_materia_prima
    ")->fetchAll();

    $sinIva = $sinUm = [];
    foreach ($mps as $mp) {
        if (empty($mp['iva_id'])) {
            $sinIva[] = "#{$mp['id_materia_prima']}";
        }
        if (empty($mp['id_unidad'])) {
            $sinUm[] = "#{$mp['id_materia_prima']}";
        }
    }

    qa_check($results, 'compras.materia_prima.iva', 'Materia prima: tipo IVA asignado', empty($sinIva), empty($sinIva) ? count($mps) . ' MP' : implode(', ', $sinIva));
    qa_check($results, 'compras.materia_prima.unidad', 'Materia prima: unidad de medida asignada', empty($sinUm), empty($sinUm) ? 'Unidades OK' : implode(', ', $sinUm));

    $huerfanas = $pdo->query("
        SELECT d.id_materia_prima FROM presupuesto_detalle_compra d
        LEFT JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
        WHERE mp.id_materia_prima IS NULL
        LIMIT 5
    ")->fetchAll();
    qa_check($results, 'compras.materia_prima.referencias', 'Materia prima: referenciada en detalles de compra', empty($huerfanas), empty($huerfanas) ? 'Referencias OK' : 'MP huérfanas en presupuesto');
}

function validar_compras_libro_compras(PDO $pdo, array &$results): void
{
    $sinIva = $pdo->query("
        SELECT f.id_factura_compra, f.numero_factura
        FROM factura_compra f
        LEFT JOIN iva_compra iv ON iv.id_factura_compra = f.id_factura_compra
        WHERE UPPER(f.fac_estado) = 'EMITIDA' AND iv.iva_id IS NULL
    ")->fetchAll();

    qa_check(
        $results,
        'compras.libro.iva_facturas',
        'Libro de compras: facturas EMITIDA tienen registro en iva_compra',
        empty($sinIva),
        empty($sinIva) ? 'Registros IVA OK' : implode(', ', array_column($sinIva, 'id_factura_compra'))
    );

    $libroPath = dirname(__DIR__) . '/modules/compras/libro_compras/consolidar_documentos.php';
    if (is_file($libroPath)) {
        require_once $libroPath;
        try {
            $desde = $pdo->query("SELECT COALESCE(MIN(fact_fecha_compra)::date, CURRENT_DATE) FROM factura_compra")->fetchColumn();
            $hasta = $pdo->query("SELECT COALESCE(MAX(fact_fecha_compra)::date, CURRENT_DATE) FROM factura_compra")->fetchColumn();
            $docs = consolidarDocumentos($pdo, (string)$desde, (string)$hasta);
            qa_check($results, 'compras.libro.consolidar', 'Libro de compras: consolidación ejecutable', true, count($docs) . ' documento(s) en rango ' . $desde . ' — ' . $hasta);
        } catch (Throwable $e) {
            qa_check($results, 'compras.libro.consolidar', 'Libro de compras: consolidación ejecutable', false, $e->getMessage());
        }
    } else {
        qa_check($results, 'compras.libro.consolidar', 'Libro de compras: consolidación ejecutable', false, 'Falta consolidar_documentos.php');
    }
}

function validar_compras_cadena_documentos(PDO $pdo, array &$results): void
{
    $cadenaRota = $pdo->query("
        SELECT f.id_factura_compra
        FROM factura_compra f
        JOIN orden_de_compra oc ON oc.id_orden_compra = f.id_orden_compra
        LEFT JOIN presupuesto_compra pc ON pc.id_presupuesto_compra = oc.id_presupuesto_compra
        WHERE pc.id_presupuesto_compra IS NULL
          AND UPPER(f.fac_estado) NOT IN ('ANULADO')
    ")->fetchAll();

    qa_check(
        $results,
        'compras.cadena.completa',
        'Cadena documental: Factura → OC → Presupuesto → Pedido',
        empty($cadenaRota),
        empty($cadenaRota) ? 'Cadena OK' : 'Facturas: ' . implode(',', array_column($cadenaRota, 'id_factura_compra')),
        empty($cadenaRota) ? 'fail' : 'warn'
    );

    $ocFacturadaSinFac = $pdo->query("
        SELECT oc.id_orden_compra FROM orden_de_compra oc
        WHERE UPPER(oc.orden_estado) = 'FACTURADA'
          AND NOT EXISTS (SELECT 1 FROM factura_compra f WHERE f.id_orden_compra = oc.id_orden_compra AND UPPER(f.fac_estado) <> 'ANULADO')
    ")->fetchAll();

    qa_check(
        $results,
        'compras.cadena.oc_facturada',
        'Cadena: OC FACTURADA tiene factura asociada',
        empty($ocFacturadaSinFac),
        empty($ocFacturadaSinFac) ? 'OK' : implode(',', array_column($ocFacturadaSinFac, 'id_orden_compra'))
    );
}

/** Corrige iva_compra / fac_iva solo en facturas sin notas emitidas (datos legacy). */
function compras_qa_reparar_iva_facturas_sin_notas(PDO $pdo): void
{
    $ids = $pdo->query("
        SELECT f.id_factura_compra
        FROM factura_compra f
        WHERE UPPER(f.fac_estado) <> 'ANULADO'
          AND NOT EXISTS (
              SELECT 1 FROM nota_compra n
              WHERE n.id_factura_compra = f.id_factura_compra
                AND UPPER(n.nota_compra_estado) = 'EMITIDA'
          )
    ")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ids as $factId) {
        $factId = (int)$factId;
        $calc = compras_calcular_total_factura_neto($pdo, $factId);
        $iva5 = $iva10 = $ex = 0;

        foreach ($calc['lineas'] as $ln) {
            $stIva = $pdo->prepare('SELECT ti.iva_descri FROM materia_prima mp LEFT JOIN tipo_iva ti ON ti.iva_id = mp.iva_id WHERE mp.id_materia_prima = :id');
            $stIva->execute([':id' => $ln['id_materia_prima']]);
            $div = compras_iva_divisor_por_descri((string)$stIva->fetchColumn());
            if ($div === 21) {
                $iva5 += $ln['iva'];
            } elseif ($div === 11) {
                $iva10 += $ln['iva'];
            } else {
                $ex += $ln['iva'];
            }
        }

        $stIva = $pdo->prepare('SELECT COALESCE(iva_5,0)+COALESCE(iva_10,0)+COALESCE(iva_exento,0) FROM iva_compra WHERE id_factura_compra = :id LIMIT 1');
        $stIva->execute([':id' => $factId]);
        $ivaGuard = (int)$stIva->fetchColumn();
        $ivaCalc = (int)$calc['iva'];
        if ($ivaCalc <= 0 || abs($ivaCalc - $ivaGuard) <= 2) {
            continue;
        }

        $pdo->beginTransaction();
        try {
            foreach ($calc['lineas'] as $ln) {
                $pdo->prepare('UPDATE factura_detalle_compra SET fac_iva = :iva WHERE id_factura_compra = :f AND id_materia_prima = :mp')
                    ->execute([':iva' => $ln['iva'], ':f' => $factId, ':mp' => $ln['id_materia_prima']]);
            }
            $pdo->prepare('DELETE FROM iva_compra WHERE id_factura_compra = :f')->execute([':f' => $factId]);
            $fec = $pdo->prepare('SELECT fact_fecha_compra::date FROM factura_compra WHERE id_factura_compra = :f');
            $fec->execute([':f' => $factId]);
            $pdo->prepare('INSERT INTO iva_compra (id_factura_compra, iva_fecha, iva_exento, iva_5, iva_10) VALUES (:f, :fecha, :ex, :iva5, :iva10)')
                ->execute([':f' => $factId, ':fecha' => $fec->fetchColumn(), ':ex' => $ex, ':iva5' => $iva5, ':iva10' => $iva10]);
            $pdo->prepare('UPDATE factura_compra SET fac_total = :t WHERE id_factura_compra = :f')
                ->execute([':t' => $calc['total'], ':f' => $factId]);
            $pdo->prepare("
                UPDATE cuentas_pagar
                SET monto_total = :t,
                    monto_pendiente = CASE WHEN UPPER(estado) = 'FINALIZADO' THEN 0 ELSE :t END
                WHERE id_factura_compra = :f
            ")->execute([':t' => $calc['total'], ':f' => $factId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
        }
    }
}
