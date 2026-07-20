<?php
/**
 * Escenarios ampliados Producción — permisos, anulaciones, informes, ediciones.
 */
declare(strict_types=1);

require_once __DIR__ . '/qa/qa_prod_helpers.php';

function validar_produccion_funcional_extendido(array &$results, array &$ctx = []): void
{
    validar_produccion_permisos_roles($results);
    validar_produccion_referenciales_funcional($results, $ctx);
    validar_produccion_equipos_y_costos($results, $ctx);
    validar_produccion_perdidas($results, $ctx);
    validar_produccion_informes_rango($results);
    validar_produccion_anulaciones($results, $ctx);
    validar_produccion_anulaciones_extendidas($results, $ctx);
}

function validar_produccion_permisos_roles(array &$results): void
{
    $admin = qa_func_session_for_username('admin');
    $jefe = qa_func_session_for_username('jefe_prod');
    $enc = qa_func_session_for_username('enc_prod');
    $jefeComp = qa_func_session_for_username('jefe_compras');

    qa_func_check($results, 'func.perm.admin_pedido', 'Permisos: admin → PEDIDO_PRODUCCION', qa_func_has_permission($admin, 'PEDIDO_PRODUCCION'), 'admin');
    qa_func_check($results, 'func.perm.admin_orden', 'Permisos: admin → ORDEN_PRODUCCION', qa_func_has_permission($admin, 'ORDEN_PRODUCCION'), 'admin');
    qa_func_check($results, 'func.perm.admin_reportes', 'Permisos: admin → REPORTES_PRODUCCION', qa_func_has_permission($admin, 'REPORTES_PRODUCCION'), 'admin');

    qa_func_check($results, 'func.perm.jefe_pedido', 'Permisos: jefe_prod → PEDIDO_PRODUCCION', qa_func_has_permission($jefe, 'PEDIDO_PRODUCCION'), 'jefe_prod');
    qa_func_check($results, 'func.perm.jefe_reportes', 'Permisos: jefe_prod → REPORTES_PRODUCCION', qa_func_has_permission($jefe, 'REPORTES_PRODUCCION'), 'jefe_prod');

    qa_func_check($results, 'func.perm.enc_control', 'Permisos: enc_prod → CONTROL_PRODUCCION', qa_func_has_permission($enc, 'CONTROL_PRODUCCION'), 'enc_prod');
    qa_func_check($results, 'func.perm.enc_perdidas', 'Permisos: enc_prod → PERDIDAS', qa_func_has_permission($enc, 'PERDIDAS'), 'enc_prod');
    qa_func_check($results, 'func.perm.enc_equipos', 'Permisos: enc_prod → EQUIPOS_PRODUCCION', qa_func_has_permission($enc, 'EQUIPOS_PRODUCCION'), 'enc_prod');
    qa_func_check($results, 'func.perm.enc_etapas', 'Permisos: enc_prod → ETAPAS_PRODUCCION', qa_func_has_permission($enc, 'ETAPAS_PRODUCCION'), 'enc_prod');
    qa_func_check($results, 'func.perm.enc_sin_referenciales', 'Permisos: enc_prod NO → REFERENCIALES', !qa_func_has_permission($enc, 'REFERENCIALES'), 'bloqueado');
    qa_func_check($results, 'func.perm.enc_sin_reportes', 'Permisos: enc_prod NO → REPORTES_PRODUCCION', !qa_func_has_permission($enc, 'REPORTES_PRODUCCION'), 'bloqueado');
    qa_func_check($results, 'func.perm.admin_referenciales', 'Permisos: admin → REFERENCIALES (trabajadores/inspectores)', qa_func_has_permission($admin, 'REFERENCIALES'), 'admin');

    qa_func_check($results, 'func.perm.comp_sin_prod', 'Permisos: jefe_compras NO → PEDIDO_PRODUCCION', !qa_func_has_permission($jefeComp, 'PEDIDO_PRODUCCION'), 'aislamiento módulos');
}

function validar_produccion_informes_rango(array &$results): void
{
    $movimientos = [
        ['func.informe.pedido', 'Informe rango: pedidos producción', 'PEDIDO_PRODUCCION'],
        ['func.informe.orden', 'Informe rango: órdenes producción', 'ORDEN_PRODUCCION'],
        ['func.informe.control', 'Informe rango: control producción', 'CONTROL_PRODUCCION'],
        ['func.informe.pt', 'Informe rango: productos terminados', 'PRODUCTOS_TERMINADOS'],
        ['func.informe.calidad', 'Informe rango: control calidad', 'CONTROL_CALIDAD'],
        ['func.informe.pedido_mp', 'Informe rango: pedido materia prima', 'PEDIDO_MATERIA'],
        ['func.informe.reposicion', 'Informe rango: reposición materia', 'REPOSICION_MATERIA'],
        ['func.informe.perdidas', 'Informe rango: pérdidas', 'PERDIDAS'],
    ];

    foreach ($movimientos as [$id, $nombre, $mov]) {
        $rep = qa_prod_informe_ok($mov);
        $det = ($rep['bytes'] ?? 0) . ' bytes';
        if (!empty($rep['sin_datos'])) {
            $det .= ' (sin datos)';
        }
        qa_func_check($results, $id, $nombre, $rep['ok'] ?? false, $det);
    }

    $hoy = qa_func_hoy();
    $bad = qa_func_report_ok('modules/comunes/reportes/generador_produccion.php', [
        'movimiento' => 'PEDIDO_PRODUCCION',
        'estado'     => 'REPORTE TOTAL',
        'desde'      => date('Y-m-d', strtotime($hoy . ' +10 days')),
        'hasta'      => $hoy,
    ]);
    qa_func_check($results, 'func.informe.rechazo_fechas', 'Informe producción: rechazo Desde > Hasta', !($bad['is_pdf'] ?? false), 'sin PDF');
}

function validar_produccion_referenciales_funcional(array &$results, array &$ctx): void
{
    $session = qa_func_admin_session();
    $productoId = qa_prod_producto_default();
    $suf = (int)date('His');
    $etapaId = qa_prod_primer_etapa_id($productoId);

    $personalTrab = qa_prod_personal_disponible(true);
    if ($personalTrab > 0 && $etapaId > 0) {
        qa_func_invoke('modules/produccion/trabajadores/proses.php', ['act' => 'insert'], [
            'Guardar'               => '1',
            'id_personal'           => (string)$personalTrab,
            'trabajador_rol'        => 'OPERARIO QA',
            'trabajador_turno'      => 'MAÑANA',
            'trabajador_costo_hora' => '15000',
            'id_etapa'              => (string)$etapaId,
        ], $session);
        $trabId = (int)qa_func_scalar(
            'SELECT trabajadores_id FROM trabajadores WHERE id_personal = :p ORDER BY trabajadores_id DESC LIMIT 1',
            [':p' => $personalTrab]
        );
        qa_func_check($results, 'func.trabajador.insert', 'Trabajador: alta vía proses.php', $trabId > 0, "trabajador #$trabId");
        $ctx['trabajador_id'] = $trabId;
    } else {
        $trabId = (int)qa_func_scalar("SELECT trabajadores_id FROM trabajadores WHERE UPPER(TRIM(trabajador_estado)) = 'ACTIVO' LIMIT 1");
        $ctx['trabajador_id'] = $trabId;
        qa_func_check($results, 'func.trabajador.insert', 'Trabajador: alta vía proses.php', $trabId > 0, "existente #$trabId");
    }

    $personalInsp = qa_prod_personal_disponible(false);
    if ($personalInsp > 0) {
        qa_func_invoke('modules/produccion/inspectores/proses.php', ['act' => 'insert'], [
            'id_personal' => (string)$personalInsp,
        ], $session);
        $inspId = (int)qa_func_scalar(
            'SELECT id_inspectores FROM inspectores WHERE id_personal = :p ORDER BY id_inspectores DESC LIMIT 1',
            [':p' => $personalInsp]
        );
        qa_func_check($results, 'func.inspector.insert', 'Inspector: alta vía proses.php', $inspId > 0, "inspector #$inspId");
    }

    $paramDesc = "QA PARAM $suf";
    $rParam = qa_func_invoke('modules/produccion/parametros_control/proses.php', ['act' => 'insert'], [
        'producto_id'      => (string)$productoId,
        'parametro_descri' => $paramDesc,
        'parametro_estado' => 'ACTIVO',
    ], $session);
    $paramId = (int)qa_func_scalar(
        'SELECT parametro_id FROM parametros_control WHERE UPPER(TRIM(parametro_descri)) = UPPER(TRIM(:d)) AND producto_id = :p ORDER BY parametro_id DESC LIMIT 1',
        [':d' => $paramDesc, ':p' => $productoId]
    );
    qa_func_check(
        $results,
        'func.parametro.insert',
        'Parámetro control: alta vía proses.php',
        $paramId > 0 || qa_prod_invoke_json_ok($rParam),
        "parametro #$paramId"
    );
}

function validar_produccion_equipos_y_costos(array &$results, array &$ctx): void
{
    $session = qa_func_admin_session();
    $hoy = qa_func_hoy();
    $productoId = qa_prod_producto_default();
    $trabId = (int)($ctx['trabajador_id'] ?? 0);
    if ($trabId <= 0) {
        $trabId = (int)qa_func_scalar("SELECT trabajadores_id FROM trabajadores WHERE UPPER(TRIM(trabajador_estado)) = 'ACTIVO' LIMIT 1");
    }

    $flujo = qa_prod_crear_pedido_orden($session, $productoId, 2);
    $ordenEq = (int)$flujo['orden_id'];
    $etapaId = qa_prod_primer_etapa_id($productoId);

    if ($ordenEq > 0 && $etapaId > 0 && $trabId > 0) {
        qa_func_invoke('modules/produccion/equipos_produccion/proses.php', ['act' => 'insert'], [
            'Guardar'       => '1',
            'orden_id'      => (string)$ordenEq,
            'etapa_id'      => (string)$etapaId,
            'equipo_descri' => 'QA EQUIPO TURNO A',
            'equipo_fecha'  => $hoy,
            'miembros'      => json_encode([['trabajadores_id' => $trabId, 'tarea_rol' => 'Operación QA']]),
        ], $session);
        $equipoId = (int)qa_func_scalar(
            'SELECT equipo_id FROM equipos_produccion WHERE orden_id = :o ORDER BY equipo_id DESC LIMIT 1',
            [':o' => $ordenEq]
        );
        $eqOk = $equipoId > 0 && strtoupper((string)qa_func_scalar(
            'SELECT equipo_estado FROM equipos_produccion WHERE equipo_id = :id',
            [':id' => $equipoId]
        )) === 'PENDIENTE';
        qa_func_check($results, 'func.equipo.insert', 'Equipos: alta vinculada a OP y etapa', $eqOk, "equipo #$equipoId");
        $ctx['equipo_id'] = $equipoId;
        $ctx['orden_equipo_id'] = $ordenEq;
    }

    $flujoCosto = qa_prod_crear_pedido_orden($session, $productoId, 1);
    $ordenCosto = (int)$flujoCosto['orden_id'];
    qa_prod_ejecutar_controles($ordenCosto, $productoId, 1, $session);
    $mpId = qa_prod_mp_default();
    qa_func_invoke('modules/produccion/costos_produccion/proses.php', ['act' => 'insert'], [
        'Guardar'     => '1',
        'orden_id'    => (string)$ordenCosto,
        'costo_fecha' => $hoy,
        'lineas'      => json_encode([
            'mp'  => [['tipo' => 'MP', 'id_materia_prima' => $mpId, 'cantidad' => 3, 'precio' => 1000]],
            'cif' => [['tipo' => 'CIF', 'concepto' => 'QA ENERGIA', 'cantidad' => 1, 'precio' => 5000]],
        ]),
    ], $session);
    $costoId = (int)qa_func_scalar(
        'SELECT costo_id FROM costo_produccion WHERE orden_id = :o ORDER BY costo_id DESC LIMIT 1',
        [':o' => $ordenCosto]
    );
    $costoOk = $costoId > 0 && (int)qa_func_scalar('SELECT costo_total FROM costo_produccion WHERE costo_id = :id', [':id' => $costoId]) > 0;
    qa_func_check($results, 'func.costo.insert', 'Costos producción: alta MP + CIF', $costoOk, "costo #$costoId");
    $ctx['costo_id'] = $costoId;
    $ctx['orden_costo_id'] = $ordenCosto;
}

function validar_produccion_perdidas(array &$results, array &$ctx): void
{
    $session = qa_func_admin_session();
    $hoy = qa_func_hoy();
    $productoId = qa_prod_producto_default();
    $depositoId = qa_prod_deposito_id();
    $cantidad = 2;

    $flujoProc = qa_prod_crear_pedido_orden($session, $productoId, $cantidad);
    $ordenProc = (int)$flujoProc['orden_id'];
    qa_prod_ejecutar_controles($ordenProc, $productoId, $cantidad, $session);
    $controlUlt = qa_prod_ultimo_control_id($ordenProc, $productoId);

    if ($controlUlt > 0) {
        qa_func_invoke('modules/produccion/perdidas/proses.php', ['act' => 'insert'], [
            'Guardar'         => '1',
            'tipo_perdida_id' => '2',
            'perdida_fecha'   => $hoy,
            'control_id'      => (string)$controlUlt,
            'calidad_id'      => '0',
            'items'           => json_encode([[
                'producto_id' => $productoId,
                'cantidad'    => 1,
                'motivo'      => 'QA perdida proceso',
            ]]),
        ], $session);
        $perdProcId = (int)qa_func_scalar(
            'SELECT perdidas_id FROM perdidas WHERE control_id = :c AND UPPER(TRIM(perdida_estado)) = \'REGISTRADO\' ORDER BY perdidas_id DESC LIMIT 1',
            [':c' => $controlUlt]
        );
        qa_func_check($results, 'func.perdida.proceso', 'Pérdidas: registro en proceso (última etapa)', $perdProcId > 0, "perdida #$perdProcId");
        $ctx['perdida_proceso_id'] = $perdProcId;
    }

    $flujoMerma = qa_prod_crear_pedido_orden($session, $productoId, 1);
    $ordenMerma = (int)$flujoMerma['orden_id'];
    qa_prod_ejecutar_controles($ordenMerma, $productoId, 1, $session);
    $terminadoMerma = qa_prod_registrar_pt($session, $ordenMerma, $productoId, 1, $depositoId);
    if ($terminadoMerma > 0) {
        qa_func_invoke('modules/produccion/control_calidad/proses.php', ['act' => 'insert'], [
            'Guardar'        => '1',
            'terminado_id'   => (string)$terminadoMerma,
            'id_inspectores' => (string)qa_prod_inspector_default(),
            'calidad_fecha'  => $hoy,
            'evaluaciones'   => qa_prod_evaluaciones_json($productoId, 1, false),
        ], $session);
        $calNcId = (int)qa_func_scalar(
            "SELECT calidad_id FROM control_calidad_produccion WHERE terminado_id = :t AND UPPER(TRIM(calidad_estado)) = 'NO CONFORME' LIMIT 1",
            [':t' => $terminadoMerma]
        );
        if ($calNcId > 0) {
            qa_func_invoke('modules/produccion/perdidas/proses.php', ['act' => 'insert'], [
                'Guardar'         => '1',
                'tipo_perdida_id' => '1',
                'perdida_fecha'   => $hoy,
                'calidad_id'      => (string)$calNcId,
                'control_id'      => '0',
                'items'           => json_encode([[
                    'producto_id' => $productoId,
                    'cantidad'    => 1,
                    'motivo'      => 'QA merma calidad',
                    'deposito_id' => $depositoId,
                ]]),
            ], $session);
            $perdMermaId = (int)qa_func_scalar(
                'SELECT perdidas_id FROM perdidas WHERE calidad_id = :c AND UPPER(TRIM(perdida_estado)) = \'REGISTRADO\' ORDER BY perdidas_id DESC LIMIT 1',
                [':c' => $calNcId]
            );
            qa_func_check($results, 'func.perdida.merma', 'Pérdidas: merma por calidad NO CONFORME', $perdMermaId > 0, "perdida #$perdMermaId");
            $ctx['perdida_merma_id'] = $perdMermaId;
            $ctx['calidad_nc_id'] = $calNcId;
        } else {
            qa_func_check($results, 'func.perdida.merma', 'Pérdidas: merma por calidad NO CONFORME', false, 'sin CC NO CONFORME');
        }
    }
}

function validar_produccion_anulaciones_extendidas(array &$results, array &$ctx): void
{
    $session = qa_func_admin_session();
    $hoy = qa_func_hoy();
    $productoId = qa_prod_producto_default();
    $depositoId = qa_prod_deposito_id();

    // Orden PENDIENTE sin controles → anular
    $flujoOrden = qa_prod_crear_pedido_orden($session, $productoId, 1);
    $ordenAnul = (int)$flujoOrden['orden_id'];
    if ($ordenAnul > 0) {
        $rOrd = qa_func_invoke_json('modules/produccion/orden_produccion/anular_orden.php', [], ['orden_id' => $ordenAnul], $session);
        $estOrd = strtoupper((string)qa_func_scalar('SELECT orden_prod_estado FROM orden_produccion WHERE orden_id = :id', [':id' => $ordenAnul]));
        qa_func_check($results, 'func.orden.anular', 'Orden producción: anulación PENDIENTE', ($rOrd['ok'] ?? false) && $estOrd === 'ANULADA', "orden #$ordenAnul");
    }

    // Control REGISTRADO sin PT → anular primer control
    $flujoCtrl = qa_prod_crear_pedido_orden($session, $productoId, 1);
    $ordenCtrl = (int)$flujoCtrl['orden_id'];
    $etapa1 = qa_prod_primer_etapa_id($productoId);
    if ($ordenCtrl > 0 && $etapa1 > 0) {
        qa_prod_control_etapa($session, $ordenCtrl, $productoId, $etapa1, 1);
        $ctrlId = qa_prod_primer_control_id($ordenCtrl, $productoId);
        $rCtrl = qa_func_invoke_json('modules/produccion/control_produccion/anular_control.php', [], ['control_id' => $ctrlId], $session);
        $estCtrl = strtoupper((string)qa_func_scalar('SELECT control_estado FROM control_produccion WHERE control_id = :id', [':id' => $ctrlId]));
        qa_func_check($results, 'func.control.anular', 'Control producción: anulación REGISTRADO', ($rCtrl['ok'] ?? false) && $estCtrl === 'ANULADO', "control #$ctrlId");
    }

    // PT sin calidad → anular
    $flujoPt = qa_prod_crear_pedido_orden($session, $productoId, 1);
    $ordenPt = (int)$flujoPt['orden_id'];
    qa_prod_ejecutar_controles($ordenPt, $productoId, 1, $session);
    $termAnul = qa_prod_registrar_pt($session, $ordenPt, $productoId, 1, $depositoId);
    if ($termAnul > 0) {
        $rPt = qa_func_invoke_json('modules/produccion/productos_terminados/anular_producto_terminado.php', [], ['terminado_id' => $termAnul], $session);
        $existe = (bool)qa_func_scalar('SELECT 1 FROM producto_terminado WHERE terminado_id = :id', [':id' => $termAnul]);
        qa_func_check($results, 'func.pt.anular', 'Productos terminados: anulación sin calidad', ($rPt['ok'] ?? false) && !$existe, "terminado #$termAnul");
    }

    // Reposición REGISTRADO → anular
    $mpId = qa_prod_mp_default();
    qa_func_invoke('modules/produccion/pedido_materia_produccion/proses.php', ['act' => 'insert'], [
        'Guardar'            => '1',
        'ped_mat_prod_fecha' => $hoy,
        'deposito_id'        => (string)$depositoId,
        'items'              => json_encode([['id_materia_prima' => $mpId, 'cantidad' => 4]]),
    ], $session);
    $pedRep = (int)qa_func_scalar('SELECT id_pedido_mat_prod FROM pedido_materia_produccion ORDER BY id_pedido_mat_prod DESC LIMIT 1');
    qa_func_invoke('modules/produccion/reposicion_materia/proses.php', ['act' => 'insert'], [
        'Guardar'            => '1',
        'id_pedido_mat_prod' => (string)$pedRep,
        'reposicion_fecha'   => $hoy,
        'items'              => json_encode([['id_materia_prima' => $mpId, 'cantidad' => 2]]),
    ], $session);
    $repAnulId = (int)qa_func_scalar('SELECT reposicion_id FROM reposicion_materia WHERE id_pedido_mat_prod = :p ORDER BY reposicion_id DESC LIMIT 1', [':p' => $pedRep]);
    if ($repAnulId > 0) {
        $rRep = qa_func_invoke_json('modules/produccion/reposicion_materia/anular_reposicion.php', [], ['reposicion_id' => $repAnulId], $session);
        $estRep = strtoupper((string)qa_func_scalar('SELECT reposicion_estado FROM reposicion_materia WHERE reposicion_id = :id', [':id' => $repAnulId]));
        qa_func_check($results, 'func.reposicion.anular', 'Reposición materia: anulación REGISTRADO', ($rRep['ok'] ?? false) && $estRep === 'ANULADO', "reposición #$repAnulId");
    }

    $equipoId = (int)($ctx['equipo_id'] ?? 0);
    if ($equipoId > 0) {
        $rEq = qa_func_invoke('modules/produccion/equipos_produccion/anular_equipo.php', [], ['equipo_id' => (string)$equipoId], $session);
        $estEq = strtoupper((string)qa_func_scalar('SELECT equipo_estado FROM equipos_produccion WHERE equipo_id = :id', [':id' => $equipoId]));
        qa_func_check($results, 'func.equipo.anular', 'Equipos: anulación PENDIENTE', qa_prod_invoke_json_ok($rEq) && $estEq === 'ANULADO', "equipo #$equipoId");
    }

    $costoId = (int)($ctx['costo_id'] ?? 0);
    if ($costoId > 0) {
        $rCosto = qa_func_invoke_json('modules/produccion/costos_produccion/anular_costo.php', [], ['costo_id' => $costoId], $session);
        $estCosto = strtoupper((string)qa_func_scalar('SELECT costo_estado FROM costo_produccion WHERE costo_id = :id', [':id' => $costoId]));
        qa_func_check($results, 'func.costo.anular', 'Costos producción: anulación', ($rCosto['ok'] ?? false) && $estCosto === 'ANULADO', "costo #$costoId");
    }

    $perdProcId = (int)($ctx['perdida_proceso_id'] ?? 0);
    if ($perdProcId > 0) {
        $rPerd = qa_func_invoke_json('modules/produccion/perdidas/anular_perdida.php', [], ['perdidas_id' => $perdProcId], $session);
        $estPerd = strtoupper((string)qa_func_scalar('SELECT perdida_estado FROM perdidas WHERE perdidas_id = :id', [':id' => $perdProcId]));
        qa_func_check($results, 'func.perdida.anular', 'Pérdidas: anulación REGISTRADO', ($rPerd['ok'] ?? false) && $estPerd === 'ANULADO', "perdida #$perdProcId");
    }
}

function validar_produccion_anulaciones(array &$results, array &$ctx): void
{
    $session = qa_func_admin_session();
    $hoy = qa_func_hoy();
    $productoId = qa_prod_producto_default();
    $tipoPedido = (int)qa_func_scalar("SELECT id_tipo_pedido FROM tipo_pedido WHERE UPPER(TRIM(tipo_pedido_estado)) = 'ACTIVO' ORDER BY id_tipo_pedido LIMIT 1");

    // Pedido PENDIENTE sin orden → anular
    $pedAnulId = qa_func_next_id('pedido_produccion', 'id_pedido_produccion');
    qa_func_invoke('modules/produccion/pedido_produccion/proses.php', ['act' => 'insert'], [
        'Guardar'        => '1',
        'codigo'         => (string)$pedAnulId,
        'id_tipo_pedido' => (string)$tipoPedido,
        'productos'      => json_encode([['codigo' => $productoId, 'cantidad' => 1]]),
        'observaciones'  => 'QA anulación pedido',
    ], $session);
    $resAnul = qa_func_invoke_json('modules/produccion/pedido_produccion/anular_pedido.php', [], ['ped_id' => $pedAnulId], $session);
    $estAnul = strtoupper((string)qa_func_scalar('SELECT pedido_prod_estado FROM pedido_produccion WHERE id_pedido_produccion = :id', [':id' => $pedAnulId]));
    qa_func_check(
        $results,
        'func.pedido.anular',
        'Pedido producción: anulación PENDIENTE',
        ($resAnul['ok'] ?? false) && $estAnul === 'ANULADO',
        "pedido #$pedAnulId estado=$estAnul"
    );

    // Pedido MP → anular
    $depositoId = (int)qa_func_scalar('SELECT deposito_id FROM deposito ORDER BY deposito_id LIMIT 1');
    $mpId = qa_prod_mp_default();
    qa_func_invoke('modules/produccion/pedido_materia_produccion/proses.php', ['act' => 'insert'], [
        'Guardar'            => '1',
        'ped_mat_prod_fecha' => $hoy,
        'deposito_id'        => (string)$depositoId,
        'items'              => json_encode([['id_materia_prima' => $mpId, 'cantidad' => 2]]),
    ], $session);
    $pedMpAnul = (int)qa_func_scalar(
        "SELECT id_pedido_mat_prod FROM pedido_materia_produccion WHERE ped_mat_prod_estado = 'PENDIENTE' ORDER BY id_pedido_mat_prod DESC LIMIT 1"
    );
    if ($pedMpAnul > 0) {
        $resMp = qa_func_invoke_json('modules/produccion/pedido_materia_produccion/anular_pedido.php', [], ['id_pedido_mat_prod' => $pedMpAnul], $session);
        $estMp = strtoupper((string)qa_func_scalar('SELECT ped_mat_prod_estado FROM pedido_materia_produccion WHERE id_pedido_mat_prod = :id', [':id' => $pedMpAnul]));
        qa_func_check(
            $results,
            'func.pedido_mp.anular',
            'Pedido materia prima: anulación PENDIENTE',
            ($resMp['ok'] ?? false) && $estMp === 'ANULADO',
            "pedido MP #$pedMpAnul"
        );
    }
}
