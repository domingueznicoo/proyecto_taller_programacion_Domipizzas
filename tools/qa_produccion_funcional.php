<?php
/**
 * Pruebas funcionales Producción — inserta/actualiza vía proses.php (mismo camino que el sistema web).
 */
declare(strict_types=1);

require_once __DIR__ . '/qa/qa_bootstrap.php';
require_once __DIR__ . '/qa/qa_prod_helpers.php';

/**
 * @param array<string,mixed> $ctx IDs creados durante la corrida
 */
function validar_produccion_funcional(array &$results, array &$ctx = []): void
{
    $session = qa_func_admin_session();
    $hoy = qa_func_hoy();
    $productoId = qa_prod_producto_default();
    $cantidad = 2;
    $tipoPedido = (int)qa_func_scalar("SELECT id_tipo_pedido FROM tipo_pedido WHERE UPPER(TRIM(tipo_pedido_estado)) = 'ACTIVO' ORDER BY id_tipo_pedido LIMIT 1");
    $depositoId = (int)qa_func_scalar('SELECT deposito_id FROM deposito ORDER BY deposito_id LIMIT 1');

    // ─── 1. PEDIDO PRODUCCIÓN ─────────────────────────────────────────
    $pedId = qa_func_next_id('pedido_produccion', 'id_pedido_produccion');
    qa_func_invoke('modules/produccion/pedido_produccion/proses.php', ['act' => 'insert'], [
        'Guardar'         => '1',
        'codigo'          => (string)$pedId,
        'id_tipo_pedido'  => (string)$tipoPedido,
        'productos'       => json_encode([['codigo' => $productoId, 'cantidad' => $cantidad]]),
        'observaciones'   => 'QA funcional — pedido producción',
    ], $session);
    $pedOk = (bool)qa_func_scalar(
        "SELECT 1 FROM pedido_produccion WHERE id_pedido_produccion = :id AND pedido_prod_estado = 'PENDIENTE'",
        [':id' => $pedId]
    );
    qa_func_check($results, 'func.pedido.insert', 'Pedido producción: creación vía proses.php', $pedOk, "pedido #$pedId");
    $ctx['pedido_id'] = $pedId;

    if ($pedOk) {
        $rep = qa_func_report_ok('modules/produccion/pedido_produccion/reporte.php', ['ped_id' => $pedId]);
        qa_func_check($results, 'func.pedido.reporte', 'Pedido producción: reporte PDF individual', $rep['ok'] ?? false, ($rep['bytes'] ?? 0) . ' bytes');
    }

    if ($pedOk) {
        qa_func_invoke('modules/produccion/pedido_produccion/proses.php', ['act' => 'update', 'ped_id' => $pedId], [
            'Guardar'         => '1',
            'pedido_id'       => (string)$pedId,
            'id_tipo_pedido'  => (string)$tipoPedido,
            'cantidad'        => [$productoId => (string)$cantidad],
            'observaciones'   => 'QA pedido editado',
        ], $session);
        $obs = (string)qa_func_scalar('SELECT pedido_prod_observaciones FROM pedido_produccion WHERE id_pedido_produccion = :id', [':id' => $pedId]);
        qa_func_check($results, 'func.pedido.update', 'Pedido producción: edición PENDIENTE', $obs === 'QA pedido editado', "obs=$obs");
    }

    // ─── 3. ORDEN DE PRODUCCIÓN ───────────────────────────────────────
    $fechaEntrega = date('Y-m-d', strtotime($hoy . ' +7 days'));
    qa_func_invoke('modules/produccion/orden_produccion/proses.php', ['act' => 'insert'], [
        'Guardar'                  => '1',
        'id_pedido_produccion'     => (string)$pedId,
        'orden_prod_fecha_entrega' => $fechaEntrega,
    ], $session);
    $ordenId = (int)qa_func_scalar('SELECT orden_id FROM orden_produccion WHERE id_pedido_produccion = :p ORDER BY orden_id DESC LIMIT 1', [':p' => $pedId]);
    $ordenOk = $ordenId > 0 && strtoupper((string)qa_func_scalar('SELECT orden_prod_estado FROM orden_produccion WHERE orden_id = :id', [':id' => $ordenId])) === 'PENDIENTE';
    qa_func_check($results, 'func.orden.insert', 'Orden producción: alta desde pedido', $ordenOk, "orden #$ordenId");
    $ctx['orden_id'] = $ordenId;

    $pedAsignado = strtoupper((string)qa_func_scalar('SELECT pedido_prod_estado FROM pedido_produccion WHERE id_pedido_produccion = :id', [':id' => $pedId])) === 'ASIGNADO';
    qa_func_check($results, 'func.pedido.asignado', 'Pedido: estado ASIGNADO tras orden', $pedAsignado, "pedido #$pedId");

    // ─── 3b. ORDEN: edición (solo PENDIENTE, sin controles) ───────────
    if ($ordenOk) {
        $nuevaFecha = date('Y-m-d', strtotime($hoy . ' +14 days'));
        qa_func_invoke('modules/produccion/orden_produccion/proses.php', ['act' => 'update', 'orden_id' => $ordenId], [
            'Guardar'                  => '1',
            'orden_id'                 => (string)$ordenId,
            'orden_prod_fecha_entrega' => $nuevaFecha,
        ], $session);
        $fechaDb = (string)qa_func_scalar('SELECT orden_prod_fecha_entrega::date FROM orden_produccion WHERE orden_id = :id', [':id' => $ordenId]);
        qa_func_check($results, 'func.orden.update', 'Orden producción: edición fecha entrega', $fechaDb === $nuevaFecha, "fecha=$fechaDb");
    }

    // ─── 4. CONTROL DE PRODUCCIÓN (etapas secuenciales) ─────────────────
    $ctrlOk = $ordenOk && qa_prod_ejecutar_controles($ordenId, $productoId, $cantidad, $session);
    $nCtrl = (int)qa_func_scalar(
        'SELECT COUNT(*) FROM control_produccion WHERE orden_id = :o AND producto_id = :p AND UPPER(TRIM(control_estado)) = \'REGISTRADO\'',
        [':o' => $ordenId, ':p' => $productoId]
    );
    $etapasEsperadas = count(qa_prod_etapas_producto($productoId));
    qa_func_check(
        $results,
        'func.control.insert',
        'Control producción: etapas secuenciales registradas',
        $ctrlOk && $nCtrl === $etapasEsperadas,
        "{$nCtrl}/{$etapasEsperadas} controles"
    );

    // ─── 5. PRODUCTOS TERMINADOS ──────────────────────────────────────
    $fechaVenc = date('Y-m-d', strtotime($hoy . ' +30 days'));
    qa_func_invoke('modules/produccion/productos_terminados/proses.php', ['act' => 'insert'], [
        'Guardar'         => '1',
        'orden_id'        => (string)$ordenId,
        'terminado_fecha' => $hoy,
        'items'           => json_encode([[
            'producto_id'  => $productoId,
            'deposito_id'  => $depositoId,
            'cantidad'     => $cantidad,
            'fecha_elab'   => $hoy,
            'fecha_venc'   => $fechaVenc,
        ]]),
    ], $session);
    $terminadoId = (int)qa_func_scalar('SELECT terminado_id FROM producto_terminado WHERE orden_id = :o ORDER BY terminado_id DESC LIMIT 1', [':o' => $ordenId]);
    $ptOk = $terminadoId > 0 && (int)qa_func_scalar(
        'SELECT terminado_cantidad FROM productos_terminados_detalle WHERE terminado_id = :t AND producto_id = :p',
        [':t' => $terminadoId, ':p' => $productoId]
    ) === $cantidad;
    qa_func_check($results, 'func.pt.insert', 'Productos terminados: registro de lote', $ptOk, "terminado #$terminadoId qty=$cantidad");
    $ctx['terminado_id'] = $terminadoId;

    // ─── 6. CONTROL DE CALIDAD ────────────────────────────────────────
    if ($ptOk) {
        qa_func_invoke('modules/produccion/control_calidad/proses.php', ['act' => 'insert'], [
            'Guardar'        => '1',
            'terminado_id'   => (string)$terminadoId,
            'id_inspectores' => (string)qa_prod_inspector_default(),
            'calidad_fecha'  => $hoy,
            'evaluaciones'   => qa_prod_evaluaciones_json($productoId, $cantidad, true),
        ], $session);
        $calId = (int)qa_func_scalar(
            'SELECT calidad_id FROM control_calidad_produccion WHERE terminado_id = :t AND UPPER(TRIM(calidad_estado)) = \'APROBADO\' LIMIT 1',
            [':t' => $terminadoId]
        );
        qa_func_check($results, 'func.calidad.insert', 'Control calidad: inspección APROBADO', $calId > 0, "calidad #$calId");
        $ctx['calidad_id'] = $calId;
    }

    // ─── 7. PEDIDO MATERIA PRIMA ──────────────────────────────────────
    $mpId = qa_prod_mp_default();
    qa_func_invoke('modules/produccion/pedido_materia_produccion/proses.php', ['act' => 'insert'], [
        'Guardar'           => '1',
        'ped_mat_prod_fecha'=> $hoy,
        'deposito_id'       => (string)$depositoId,
        'items'             => json_encode([['id_materia_prima' => $mpId, 'cantidad' => 5]]),
    ], $session);
    $pedMpId = (int)qa_func_scalar(
        "SELECT id_pedido_mat_prod FROM pedido_materia_produccion WHERE deposito_id = :d ORDER BY id_pedido_mat_prod DESC LIMIT 1",
        [':d' => $depositoId]
    );
    $pedMpOk = $pedMpId > 0 && strtoupper((string)qa_func_scalar(
        'SELECT ped_mat_prod_estado FROM pedido_materia_produccion WHERE id_pedido_mat_prod = :id',
        [':id' => $pedMpId]
    )) === 'PENDIENTE';
    qa_func_check($results, 'func.pedido_mp.insert', 'Pedido materia prima: alta', $pedMpOk, "pedido MP #$pedMpId");
    $ctx['pedido_mp_id'] = $pedMpId;

    // ─── 8. REPOSICIÓN MATERIA ────────────────────────────────────────
    if ($pedMpOk) {
        qa_func_invoke('modules/produccion/reposicion_materia/proses.php', ['act' => 'insert'], [
            'Guardar'              => '1',
            'id_pedido_mat_prod'   => (string)$pedMpId,
            'reposicion_fecha'     => $hoy,
            'items'                => json_encode([['id_materia_prima' => $mpId, 'cantidad' => 5]]),
        ], $session);
        $repId = (int)qa_func_scalar(
            'SELECT reposicion_id FROM reposicion_materia WHERE id_pedido_mat_prod = :p ORDER BY reposicion_id DESC LIMIT 1',
            [':p' => $pedMpId]
        );
        $repOk = $repId > 0 && strtoupper((string)qa_func_scalar(
            'SELECT reposicion_estado FROM reposicion_materia WHERE reposicion_id = :id',
            [':id' => $repId]
        )) === 'REGISTRADO';
        qa_func_check($results, 'func.reposicion.insert', 'Reposición materia: alta completa', $repOk, "reposición #$repId");
        $ctx['reposicion_id'] = $repId;

        $estPedMp = strtoupper((string)qa_func_scalar(
            'SELECT ped_mat_prod_estado FROM pedido_materia_produccion WHERE id_pedido_mat_prod = :id',
            [':id' => $pedMpId]
        ));
        qa_func_check($results, 'func.pedido_mp.completado', 'Pedido MP: estado COMPLETADO tras reposición', $estPedMp === 'COMPLETADO', "estado=$estPedMp");
    }

    require_once __DIR__ . '/qa_produccion_funcional_extra.php';
    validar_produccion_funcional_extendido($results, $ctx);
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
