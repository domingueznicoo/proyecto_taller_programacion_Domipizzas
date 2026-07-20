<?php
/**
 * Pruebas funcionales Compras — inserta/actualiza vía proses.php (mismo camino que el sistema web).
 */
declare(strict_types=1);

require_once __DIR__ . '/qa/qa_bootstrap.php';

/**
 * @param array<string,mixed> $ctx IDs creados durante la corrida (para encadenar escenarios)
 */
function validar_compras_funcional(array &$results, array &$ctx = []): void
{
    $session = qa_func_admin_session();
    $suf = (int)date('His');
    $hoy = qa_func_hoy();

    // ─── 1. PEDIDO: alta ───────────────────────────────────────────────
    $pedId = qa_func_next_id('pedidos_compra', 'id_pedido_compra');
    qa_func_invoke('modules/compras/pedido_compra/proses.php', ['act' => 'insert'], [
        'Guardar'       => '1',
        'codigo'        => (string)$pedId,
        'productos'     => json_encode([['codigo' => 10, 'cantidad' => 15]]),
        'observaciones' => 'QA funcional — pedido auto',
    ], $session);
    $pedOk = (bool)qa_func_scalar('SELECT 1 FROM pedidos_compra WHERE id_pedido_compra = :id AND pedido_estado = \'PENDIENTE\'', [':id' => $pedId]);
    qa_func_check($results, 'func.pedido.insert', 'Pedido: creación vía proses.php', $pedOk, "pedido #$pedId");
    $ctx['pedido_id'] = $pedId;

    // ─── 2. PEDIDO: reporte PDF ────────────────────────────────────────
    if ($pedOk) {
        $rep = qa_func_report_ok('modules/compras/pedido_compra/reporte.php', ['ped_id' => $pedId]);
        qa_func_check($results, 'func.pedido.reporte', 'Pedido: reporte PDF individual', $rep['ok'] ?? false, ($rep['bytes'] ?? 0) . ' bytes');
    }

    // ─── 3. PRESUPUESTO: alta con descuento ────────────────────────────
    $preUi = qa_func_next_id('presupuesto_compra', 'id_presupuesto_compra');
    $detPre = [['codigo' => 10, 'cantidad' => 15, 'precio' => 4000, 'descuento' => 5000, 'iva' => 0]];
    $totalPre = 15 * 4000 - 5000; // 55000
    qa_func_invoke('modules/compras/presupuesto/proses.php', ['act' => 'insert'], [
        'Guardar'         => '1',
        'codigo'          => (string)$preUi,
        'pedido'          => (string)$pedId,
        'proveedor'       => '12',
        'productos'       => json_encode($detPre),
        'total_importe'   => (string)$totalPre,
        'descuento_total' => '0',
        'observaciones'   => 'QA presupuesto',
    ], $session);
    $preId = (int)qa_func_scalar('SELECT id_presupuesto_compra FROM presupuesto_compra WHERE id_pedido_compra = :p ORDER BY id_presupuesto_compra DESC LIMIT 1', [':p' => $pedId]);
    $preOk = $preId > 0 && abs((int)qa_func_scalar('SELECT presu_total FROM presupuesto_compra WHERE id_presupuesto_compra = :id', [':id' => $preId]) - $totalPre) <= 1;
    qa_func_check($results, 'func.presupuesto.insert', 'Presupuesto: creación con descuento por ítem', $preOk, "presupuesto #$preId total=$totalPre");
    $ctx['presupuesto_contado_id'] = $preId;

    // Pedido pasa a APROBADO
    $pedAprob = strtoupper((string)qa_func_scalar('SELECT pedido_estado FROM pedidos_compra WHERE id_pedido_compra = :id', [':id' => $pedId])) === 'APROBADO';
    qa_func_check($results, 'func.pedido.aprobado_por_presu', 'Pedido: estado APROBADO tras presupuesto', $pedAprob, "pedido #$pedId");

    // ─── 4. PRESUPUESTO: edición ───────────────────────────────────────
    if ($preOk) {
        $detPreUpd = [['codigo' => 10, 'cantidad' => 15, 'precio' => 4000, 'descuento' => 6000, 'iva' => 0]];
        $totalPreUpd = 15 * 4000 - 6000;
        qa_func_invoke('modules/compras/presupuesto/proses.php', ['act' => 'update', 'pre_id' => $preId], [
            'Guardar'         => '1',
            'pedido'          => (string)$pedId,
            'proveedor'       => '12',
            'productos'       => json_encode($detPreUpd),
            'total_importe'   => (string)$totalPreUpd,
            'descuento_total' => '0',
        ], $session);
        $preUpdOk = abs((int)qa_func_scalar('SELECT presu_total FROM presupuesto_compra WHERE id_presupuesto_compra = :id', [':id' => $preId]) - $totalPreUpd) <= 1;
        qa_func_check($results, 'func.presupuesto.update', 'Presupuesto: edición EMITIDO', $preUpdOk, "nuevo total=$totalPreUpd");
        $totalPre = $totalPreUpd;
    }

    if ($preOk) {
        $rep = qa_func_report_ok('modules/compras/presupuesto/reporte.php', ['pre_id' => (string)$preId]);
        qa_func_check($results, 'func.presupuesto.reporte', 'Presupuesto: reporte PDF', $rep['ok'] ?? false, ($rep['bytes'] ?? 0) . ' bytes');
    }

    // ─── 5. OC CONTADO ───────────────────────────────────────────────────
    $ocContId = qa_func_next_id('orden_de_compra', 'id_orden_compra');
    $detOc = [['codigo' => 10, 'cantidad' => 15, 'precio' => 4000]];
    qa_func_invoke('modules/compras/orden_compra/proses.php', ['act' => 'insert'], [
        'Guardar'          => '1',
        'oc_codigo'        => (string)$ocContId,
        'presupuesto'      => (string)$preId,
        'proveedor'        => '12',
        'oc_sucursal'      => (string)$session['id_sucursal'],
        'oc_usuario'       => (string)$session['id_usuario'],
        'orden_condicion'  => 'CONTADO',
        'total_importe'    => (string)$totalPre,
        'productos'        => json_encode($detOc),
        'oc_observaciones' => 'QA OC CONTADO',
    ], $session);
    $ocContOk = (bool)qa_func_scalar("SELECT 1 FROM orden_de_compra WHERE id_orden_compra = :id AND orden_condicion = 'CONTADO' AND orden_estado = 'EMITIDA'", [':id' => $ocContId]);
    qa_func_check($results, 'func.oc.insert_contado', 'Orden compra: alta CONTADO', $ocContOk, "OC #$ocContId");
    $ctx['oc_contado_id'] = $ocContId;

    // ─── 6. NOTA REMISIÓN ───────────────────────────────────────────────
    $conductorId = (int)qa_func_scalar('SELECT conductor_id FROM conductores LIMIT 1');
    $vehiculoId  = (int)qa_func_scalar('SELECT vehiculo_id FROM vehiculos LIMIT 1');
    $fechaOc = (string)qa_func_scalar('SELECT orden_fecha::date FROM orden_de_compra WHERE id_orden_compra = :id', [':id' => $ocContId]);
    $fechaNr = $fechaOc !== '' ? $fechaOc : $hoy;
    if ($fechaNr > $hoy) {
        $fechaNr = $hoy;
    }
    $nrNro = sprintf('003-003-%07d', ($suf % 9000000) + 1000000);
    qa_func_invoke('modules/compras/nota_remision/proses.php', ['act' => 'insert_nr'], [
        'id_orden_compra'   => (string)$ocContId,
        'id_proveedor'      => '12',
        'conductor_id'      => (string)$conductorId,
        'vehiculo_id'       => (string)$vehiculoId,
        'nota_remision_nro' => $nrNro,
        'timbrado'          => '11223344',
        'timbrado_vto'      => date('Y-m-d', strtotime($hoy . ' +1 year')),
        'fecha_remision'    => $fechaNr,
        'hora_remision'     => '10:00:00',
        'productos'         => json_encode([['id_materia_prima' => 10, 'cantidad' => 15]]),
    ], $session);
    $nrId = (int)qa_func_scalar('SELECT id_nota_remision FROM nota_remision_compra WHERE id_orden_compra = :oc ORDER BY id_nota_remision DESC LIMIT 1', [':oc' => $ocContId]);
    $nrOk = $nrId > 0;
    qa_func_check($results, 'func.nr.insert', 'Nota remisión: alta vinculada a OC', $nrOk, "NR #$nrId fecha=$fechaNr");
    $ctx['nr_id'] = $nrId;

    // ─── 7. FACTURA CONTADO + aprobar ──────────────────────────────────
    $nroFacC = sprintf('003-003-%07d', ($suf % 8000000) + 2000000);
    $timC = '22334455';
    $postFacCont = [
        'fecha'                 => $hoy,
        'hora'                  => '11:00:00',
        'fecha_emision_factura' => $hoy,
        'orden_compra'          => (string)$ocContId,
        'numero_factura'        => $nroFacC,
        'timbrado'              => $timC,
        'orden_condicion'       => 'CONTADO',
        'cuotas'                => '0',
        'interes_pct'           => '0',
        'total_importe'         => (string)$totalPre,
        'total_con_interes'     => (string)$totalPre,
        'productos'             => json_encode($detOc),
    ];
    if ($nrOk) {
        $postFacCont['recep_prev'] = '1';
        $postFacCont['nota_remision_id'] = (string)$nrId;
    }
    qa_func_invoke('modules/compras/gestionar_compras/proses.php', ['act' => 'insert'], $postFacCont, $session);
    $facContId = (int)qa_func_scalar('SELECT id_factura_compra FROM factura_compra WHERE id_orden_compra = :oc ORDER BY id_factura_compra DESC LIMIT 1', [':oc' => $ocContId]);
    $facContOk = $facContId > 0;
    qa_func_check($results, 'func.factura.insert_contado', 'Factura: alta CONTADO con NR', $facContOk, "factura #$facContId");

    if ($facContOk) {
        qa_func_invoke('modules/compras/gestionar_compras/proses.php', ['act' => 'aprobar', 'fact_id' => $facContId], [], $session);
        $facEmit = strtoupper((string)qa_func_scalar('SELECT fac_estado FROM factura_compra WHERE id_factura_compra = :id', [':id' => $facContId])) === 'EMITIDA';
        $ctaFin = qa_func_row('SELECT monto_pendiente, estado FROM cuentas_pagar WHERE id_factura_compra = :id', [':id' => $facContId]);
        $ctaContOk = $facEmit && $ctaFin && (int)$ctaFin['monto_pendiente'] === 0
            && in_array(strtoupper((string)$ctaFin['estado']), ['FINALIZADO', 'PAGADO', 'PAGADO TOTAL'], true);
        qa_func_check($results, 'func.factura.aprobar_contado', 'Factura CONTADO: aprobar → cuenta FINALIZADO', $ctaContOk, "estado cta={$ctaFin['estado']}");
        $ctx['fac_contado_id'] = $facContId;

        $calc = compras_calcular_total_factura_neto(qa_func_pdo(), $facContId);
        $ivaGuard = (int)qa_func_scalar('SELECT COALESCE(iva_5,0)+COALESCE(iva_10,0) FROM iva_compra WHERE id_factura_compra = :id', [':id' => $facContId]);
        $ivaOk = abs((int)$calc['iva'] - $ivaGuard) <= 2;
        qa_func_check($results, 'func.factura.iva_contado', 'Factura CONTADO: IVA correcto con descuento', $ivaOk, "guard=$ivaGuard calc={$calc['iva']}");

        $rep = qa_func_report_ok('modules/compras/gestionar_compras/reporte.php', ['fac_id' => (string)$facContId]);
        qa_func_check($results, 'func.factura.reporte', 'Factura: reporte PDF paraguayo', $rep['ok'] ?? false, ($rep['bytes'] ?? 0) . ' bytes');
    }

    // ─── 8. FLUJO CRÉDITO (presupuesto #2 existente) ───────────────────
    $preCred = 2;
    $provCred = (int)qa_func_scalar('SELECT id_proveedor FROM presupuesto_compra WHERE id_presupuesto_compra = :id', [':id' => $preCred]);
    $detCredRow = qa_func_pdo()->query("SELECT id_materia_prima, detalle_presu_cantidad, detalle_presu_precio_compra FROM presupuesto_detalle_compra WHERE id_presupuesto_compra = $preCred")->fetch();
    $totalCred = (int)qa_func_scalar('SELECT presu_total FROM presupuesto_compra WHERE id_presupuesto_compra = :id', [':id' => $preCred]);
    $detCred = [['codigo' => (int)$detCredRow['id_materia_prima'], 'cantidad' => (int)$detCredRow['detalle_presu_cantidad'], 'precio' => (int)$detCredRow['detalle_presu_precio_compra']]];

    $ocCredId = (int)qa_func_scalar("SELECT id_orden_compra FROM orden_de_compra WHERE id_presupuesto_compra = :p AND UPPER(orden_estado) NOT IN ('ANULADO','ANULADA') LIMIT 1", [':p' => $preCred]);
    if ($ocCredId <= 0) {
        $ocCredId = qa_func_next_id('orden_de_compra', 'id_orden_compra');
        qa_func_invoke('modules/compras/orden_compra/proses.php', ['act' => 'insert'], [
            'Guardar'         => '1',
            'oc_codigo'       => (string)$ocCredId,
            'presupuesto'     => (string)$preCred,
            'proveedor'       => (string)$provCred,
            'oc_sucursal'     => (string)$session['id_sucursal'],
            'oc_usuario'      => (string)$session['id_usuario'],
            'orden_condicion' => 'CREDITO',
            'total_importe'   => (string)$totalCred,
            'productos'       => json_encode($detCred),
        ], $session);
    }
    $ocCredOk = (bool)qa_func_scalar("SELECT 1 FROM orden_de_compra WHERE id_orden_compra = :id AND orden_condicion = 'CREDITO'", [':id' => $ocCredId]);
    qa_func_check($results, 'func.oc.insert_credito', 'Orden compra: alta CREDITO', $ocCredOk, "OC #$ocCredId");

    $facCredId = (int)qa_func_scalar("SELECT id_factura_compra FROM factura_compra WHERE id_orden_compra = :oc AND UPPER(fac_estado) <> 'ANULADO' ORDER BY id_factura_compra DESC LIMIT 1", [':oc' => $ocCredId]);
    $nroFacCr = sprintf('004-004-%07d', ($suf % 7000000) + 3000000);
    $cuotas = 3;
    $pctInt = 5;
    $cuotaBase = intdiv($totalCred, $cuotas);
    $interesCta = (int)round($cuotaBase * ($pctInt / 100));
    $totalConInt = ($cuotaBase + $interesCta) * $cuotas;

    if ($facCredId <= 0) {
        qa_func_invoke('modules/compras/gestionar_compras/proses.php', ['act' => 'insert'], [
            'fecha'                 => $hoy,
            'hora'                  => '12:00:00',
            'fecha_emision_factura' => $hoy,
            'orden_compra'          => (string)$ocCredId,
            'numero_factura'        => $nroFacCr,
            'timbrado'              => '33445566',
            'orden_condicion'       => 'CREDITO',
            'cuotas'                => (string)$cuotas,
            'interes_pct'           => (string)$pctInt,
            'total_importe'         => (string)$totalCred,
            'total_con_interes'     => (string)$totalConInt,
            'productos'             => json_encode($detCred),
        ], $session);
        $facCredId = (int)qa_func_scalar('SELECT id_factura_compra FROM factura_compra WHERE id_orden_compra = :oc ORDER BY id_factura_compra DESC LIMIT 1', [':oc' => $ocCredId]);
    }
    qa_func_check($results, 'func.factura.insert_credito', 'Factura: alta CREDITO con cuotas', $facCredId > 0, "factura #$facCredId cuotas=$cuotas");

    if ($facCredId > 0) {
        $estFacCr = strtoupper((string)qa_func_scalar('SELECT fac_estado FROM factura_compra WHERE id_factura_compra = :id', [':id' => $facCredId]));
        if ($estFacCr === 'PENDIENTE') {
            qa_func_invoke('modules/compras/gestionar_compras/proses.php', ['act' => 'aprobar', 'fact_id' => $facCredId], [], $session);
        }
        $ctaCr = qa_func_row('SELECT monto_total, monto_pendiente, estado, plazo_cuenta FROM cuentas_pagar WHERE id_factura_compra = :id', [':id' => $facCredId]);
        $ctaCredOk = $ctaCr && (int)$ctaCr['monto_pendiente'] > 0
            && strtoupper((string)$ctaCr['estado']) === 'PENDIENTE'
            && (int)$ctaCr['plazo_cuenta'] === $cuotas;
        qa_func_check($results, 'func.factura.aprobar_credito', 'Factura CREDITO: cuenta PENDIENTE con cuotas', $ctaCredOk,
            "monto={$ctaCr['monto_total']} pend={$ctaCr['monto_pendiente']} plazo={$ctaCr['plazo_cuenta']}");
        $ctx['fac_credito_id'] = $facCredId;

        // Alinear fecha de factura CREDITO al día operativo (evita desfase timezone en NC)
        qa_func_pdo()->prepare("
            UPDATE factura_compra SET fact_fecha_compra = :fh::timestamp
            WHERE id_factura_compra = :id
        ")->execute([':fh' => $hoy . ' 12:00:00', ':id' => $facCredId]);
        qa_func_pdo()->prepare("
            UPDATE cuentas_pagar SET fecha_emision = :f
            WHERE id_factura_compra = :id
        ")->execute([':f' => $hoy, ':id' => $facCredId]);

        // ─── 9. NOTA CRÉDITO parcial sobre factura CREDITO ─────────────
        $yaNc = (bool)qa_func_scalar('SELECT 1 FROM nota_compra WHERE id_factura_compra = :f AND nota_compra_tipo = \'CREDITO\' LIMIT 1', [':f' => $facCredId]);
        if (!$yaNc) {
            $motivoNc = (int)qa_func_scalar("SELECT id_motivo FROM motivo WHERE categoria_motivo = 'NOTA_CREDITO' AND motivo_descripcion LIKE 'Descuento%' LIMIT 1") ?: 4;
            $ncNro = 100000 + ($suf % 800000);
            $ncItems = [[
                'id_materia_prima' => (int)$detCredRow['id_materia_prima'],
                'cantidad'         => 1,
                'precio'           => (int)$detCredRow['detalle_presu_precio_compra'],
            ]];
            $ncTotal = (int)$detCredRow['detalle_presu_precio_compra'];
            qa_func_invoke('modules/compras/nota_credito/proses.php', ['act' => 'insert_nota'], [
                'id_proveedor'   => (string)$provCred,
                'fact_id'        => (string)$facCredId,
                'nota_tipo'      => 'CREDITO',
                'motivo_id'      => (string)$motivoNc,
                'nota_nro'       => (string)$ncNro,
                'nota_timbrado'  => '44556677',
                'nota_emision'   => $hoy,
                'nota_vto'       => date('Y-m-d', strtotime($hoy . ' +1 year')),
                'nota_fecha'     => $hoy,
                'nota_hora'      => '13:00:00',
                'productos'      => json_encode($ncItems),
                'nota_total_num' => (string)$ncTotal,
            ], $session);
        }
        $ncOk = (bool)qa_func_scalar('SELECT 1 FROM nota_compra WHERE id_factura_compra = :f AND nota_compra_tipo = \'CREDITO\'', [':f' => $facCredId]);
        qa_func_check($results, 'func.nota_credito.insert', 'Nota crédito: alta parcial sobre CREDITO', $ncOk, 'NC sobre factura #' . $facCredId);
    }

    $rNcBlock = qa_func_invoke('modules/compras/nota_credito/proses.php', ['act' => 'insert_nota'], [
        'id_proveedor'   => '10',
        'fact_id'        => '1',
        'nota_tipo'      => 'CREDITO',
        'motivo_id'      => '4',
        'nota_nro'       => (string)(200000 + $suf),
        'nota_timbrado'  => '55667788',
        'nota_emision'   => (string)qa_func_scalar('SELECT fact_fecha_compra::date FROM factura_compra WHERE id_factura_compra = 1'),
        'nota_vto'       => date('Y-m-d', strtotime($hoy . ' +1 year')),
        'nota_fecha'     => $hoy,
        'productos'      => json_encode([['id_materia_prima' => 5, 'cantidad' => 1, 'precio' => 1000]]),
        'nota_total_num' => '1000',
    ], $session);
    $ncBlockJson = json_decode(trim((string)($rNcBlock['output'] ?? '')), true);
    if (!is_array($ncBlockJson) && isset($rNcBlock['message'])) {
        $ncBlockJson = $rNcBlock;
    }
    $blocked = ($rNcBlock['ok'] ?? true) === false
        && (is_array($ncBlockJson) ? ($ncBlockJson['success'] ?? true) === false : true)
        && str_contains((string)(is_array($ncBlockJson) ? ($ncBlockJson['message'] ?? '') : ($rNcBlock['output'] ?? '')), 'pagada');
    qa_func_check($results, 'func.nota_credito.rechazo_pagada', 'Nota crédito: rechazo factura pagada (FA#3)', $blocked, $ncBlockJson['message'] ?? 'sin mensaje');

    // ─── 11. AJUSTE DE STOCK ───────────────────────────────────────────
    $depId = (int)qa_func_scalar('SELECT deposito_id FROM deposito LIMIT 1');
    $motAjuste = (int)qa_func_scalar("SELECT id_motivo FROM motivo WHERE categoria_motivo = 'AJUSTE' LIMIT 1");
    qa_func_invoke('modules/compras/ajustes/proses.php', ['act' => 'insert'], [
        'deposito_id'         => (string)$depId,
        'ajuste_motivo_id'    => (string)$motAjuste,
        'ajuste_fecha'        => $hoy,
        'tipo_regularizacion' => '',
        'detalle_json'        => json_encode([['id_materia_prima' => 10, 'cantidad' => 2]]),
    ], $session);
    $ajusteId = (int)qa_func_scalar('SELECT id_ajuste FROM ajustes ORDER BY id_ajuste DESC LIMIT 1');
    $ajusteOk = $ajusteId > 0 && (bool)qa_func_scalar('SELECT 1 FROM ajustes_detalle WHERE id_ajuste = :id', [':id' => $ajusteId]);
    qa_func_check($results, 'func.ajuste.insert', 'Ajustes: alta de stock', $ajusteOk, "ajuste #$ajusteId");

    // ─── 12. RECHAZOS DE ANULACIÓN ─────────────────────────────────────
    qa_func_invoke('modules/compras/pedido_compra/proses.php', ['act' => 'anular', 'ped_id' => $pedId], [], $session);
    $pedNoAnul = strtoupper((string)qa_func_scalar('SELECT pedido_estado FROM pedidos_compra WHERE id_pedido_compra = :id', [':id' => $pedId])) !== 'ANULADO';
    qa_func_check($results, 'func.pedido.rechazo_anular', 'Pedido: no anula si APROBADO/vinculado', $pedNoAnul, "estado ≠ ANULADO");

    // Pedido limpio solo PENDIENTE → anular OK
    $pedSolo = qa_func_next_id('pedidos_compra', 'id_pedido_compra');
    qa_func_invoke('modules/compras/pedido_compra/proses.php', ['act' => 'insert'], [
        'Guardar' => '1', 'codigo' => (string)$pedSolo,
        'productos' => json_encode([['codigo' => 9, 'cantidad' => 5]]),
    ], $session);
    qa_func_invoke('modules/compras/pedido_compra/proses.php', ['act' => 'anular', 'ped_id' => $pedSolo], [], $session);
    $pedAnulOk = strtoupper((string)qa_func_scalar('SELECT pedido_estado FROM pedidos_compra WHERE id_pedido_compra = :id', [':id' => $pedSolo])) === 'ANULADO';
    qa_func_check($results, 'func.pedido.anular', 'Pedido: anulación en PENDIENTE sin vínculos', $pedAnulOk, "pedido #$pedSolo");

    // ─── 13. LIBRO DE COMPRAS ──────────────────────────────────────────
    $libroPath = PROJECT_ROOT . '/modules/compras/libro_compras/consolidar_documentos.php';
    if (is_file($libroPath)) {
        require_once $libroPath;
        try {
            $desde = (string)qa_func_scalar('SELECT COALESCE(MIN(fact_fecha_compra)::date, CURRENT_DATE) FROM factura_compra');
            $hasta = (string)qa_func_scalar('SELECT COALESCE(MAX(fact_fecha_compra)::date, CURRENT_DATE) FROM factura_compra');
            $docs = consolidarDocumentos(qa_func_pdo(), $desde, $hasta);
            qa_func_check($results, 'func.libro.consolidar', 'Libro compras: consolidación con datos QA', count($docs) > 0, count($docs) . ' documento(s)');
        } catch (Throwable $e) {
            qa_func_check($results, 'func.libro.consolidar', 'Libro compras: consolidación', false, $e->getMessage());
        }
    }

    // ─── 14. REPORTES OC / NR ──────────────────────────────────────────
    if ($ocContOk) {
        $rep = qa_func_report_ok('modules/compras/orden_compra/reporte.php', ['orden_id' => (string)$ocContId]);
        qa_func_check($results, 'func.oc.reporte', 'OC: reporte PDF', $rep['ok'] ?? false, ($rep['bytes'] ?? 0) . ' bytes');
    }
    if ($nrOk) {
        $rep = qa_func_report_ok('modules/compras/nota_remision/reporte.php', ['nr_id' => $nrId]);
        qa_func_check($results, 'func.nr.reporte', 'NR: reporte PDF', $rep['ok'] ?? false, ($rep['bytes'] ?? 0) . ' bytes');
    }

    require_once __DIR__ . '/qa_compras_funcional_extra.php';
    validar_compras_funcional_extendido($results, $ctx);
}

function qa_func_check(array &$results, string $id, string $nombre, bool $ok, string $detalle = ''): void
{
    if (function_exists('qa_check')) {
        qa_check($results, $id, $nombre, $ok, $detalle);
        return;
    }
    $results['pruebas'][] = ['id' => $id, 'nombre' => $nombre, 'estado' => $ok ? 'OK' : 'FAIL', 'detalle' => $detalle];
    if ($ok) {
        $results['resumen']['ok']++;
    } else {
        $results['resumen']['fail']++;
    }
}
