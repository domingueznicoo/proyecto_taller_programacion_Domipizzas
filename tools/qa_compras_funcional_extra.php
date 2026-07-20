<?php
/**
 * Escenarios ampliados — permisos, referenciales, anulaciones, ND, informes, ediciones.
 */
declare(strict_types=1);

function validar_compras_funcional_extendido(array &$results, array &$ctx = []): void
{
    validar_compras_permisos_roles($results);
    validar_compras_referenciales($results, $ctx);
    validar_compras_oc_observaciones($results);
    validar_compras_informes_rango($results);
    validar_compras_ediciones($results, $ctx);
    validar_compras_anulaciones($results, $ctx);
    validar_compras_nota_debito($results, $ctx);
    validar_compras_anular_nota($results, $ctx);
    validar_compras_informe_router($results);
}

function validar_compras_permisos_roles(array &$results): void
{
    $admin = qa_func_session_for_username('admin');
    $enc = qa_func_session_for_username('enc_compras');
    $jefe = qa_func_session_for_username('jefe_compras');
    $jefeProd = qa_func_session_for_username('jefe_prod');

    qa_func_check($results, 'func.perm.admin_pedido', 'Permisos: admin → PEDIDO_COMPRAS', qa_func_has_permission($admin, 'PEDIDO_COMPRAS'), 'admin');
    qa_func_check($results, 'func.perm.admin_libro', 'Permisos: admin → LIBRO_COMPRAS', qa_func_has_permission($admin, 'LIBRO_COMPRAS'), 'admin');
    qa_func_check($results, 'func.perm.admin_referenciales', 'Permisos: admin → REFERENCIALES', qa_func_has_permission($admin, 'REFERENCIALES'), 'admin');

    qa_func_check($results, 'func.perm.enc_pedido', 'Permisos: enc_compras → PEDIDO_COMPRAS', qa_func_has_permission($enc, 'PEDIDO_COMPRAS'), 'enc_compras');
    qa_func_check($results, 'func.perm.enc_sin_libro', 'Permisos: enc_compras NO → LIBRO_COMPRAS', !qa_func_has_permission($enc, 'LIBRO_COMPRAS'), 'bloqueado');
    qa_func_check($results, 'func.perm.enc_sin_referenciales', 'Permisos: enc_compras NO → REFERENCIALES', !qa_func_has_permission($enc, 'REFERENCIALES'), 'bloqueado');

    qa_func_check($results, 'func.perm.jefe_libro', 'Permisos: jefe_compras → LIBRO_COMPRAS', qa_func_has_permission($jefe, 'LIBRO_COMPRAS'), 'jefe_compras');
    qa_func_check($results, 'func.perm.jefe_reportes', 'Permisos: jefe_compras → REPORTES_COMPRAS', qa_func_has_permission($jefe, 'REPORTES_COMPRAS'), 'jefe_compras');

    qa_func_check($results, 'func.perm.prod_sin_compras', 'Permisos: jefe_prod NO → PEDIDO_COMPRAS', !qa_func_has_permission($jefeProd, 'PEDIDO_COMPRAS'), 'aislamiento módulos');
}

function validar_compras_referenciales(array &$results, array &$ctx): void
{
    $session = qa_func_admin_session();
    $suf = (int)date('His');
    $unidad = (int)qa_func_scalar('SELECT id_unidad FROM unidad_medida LIMIT 1');
    $iva = (int)qa_func_scalar('SELECT iva_id FROM tipo_iva LIMIT 1');
    $dep = (int)qa_func_scalar('SELECT deposito_id FROM deposito LIMIT 1');

    // ── Proveedor: alta ──
    $rsProv = "QA PROVEEDOR $suf";
    $ruc = sprintf('%07d-0', 1000000 + ($suf % 8999999));
    qa_func_invoke('modules/compras/proveedor/proses.php', ['act' => 'insert'], [
        'Guardar'            => '1',
        'razon_social'       => $rsProv,
        'ruc_proveedor'      => $ruc,
        'telefono_proveedor' => '0981000000',
        'direccion_proveedor'=> 'QA test',
        'email_proveedor'    => "qa{$suf}@test.local",
    ], $session);
    $provId = (int)qa_func_scalar('SELECT id_proveedor FROM proveedor WHERE ruc_proveedor = :r', [':r' => $ruc]);
    qa_func_check($results, 'func.proveedor.insert', 'Proveedor: alta vía proses.php', $provId > 0, "proveedor #$provId");

    if ($provId > 0) {
        qa_func_invoke('modules/compras/proveedor/proses.php', ['act' => 'update'], [
            'Guardar'            => '1',
            'id_proveedor'       => (string)$provId,
            'razon_social'       => $rsProv,
            'ruc_proveedor'      => $ruc,
            'telefono_proveedor' => '0981999888',
            'direccion_proveedor'=> 'QA editado',
            'email_proveedor'    => '',
            'estado_proveedor'   => 'ACTIVO',
        ], $session);
        $tel = qa_func_scalar('SELECT telefono_proveedor FROM proveedor WHERE id_proveedor = :id', [':id' => $provId]);
        qa_func_check($results, 'func.proveedor.update', 'Proveedor: edición', $tel === '0981999888', "tel=$tel");

        qa_func_invoke('modules/compras/proveedor/proses.php', ['act' => 'delete'], [
            'id' => (string)$provId,
        ], $session);
        $estProv = strtoupper((string)qa_func_scalar('SELECT estado_proveedor FROM proveedor WHERE id_proveedor = :id', [':id' => $provId]));
        qa_func_check($results, 'func.proveedor.inactivar', 'Proveedor: inactivación lógica sin vínculos', $estProv === 'INACTIVO', $estProv);
    }

    // Rechazo RUC inválido
    qa_func_invoke('modules/compras/proveedor/proses.php', ['act' => 'insert'], [
        'Guardar'       => '1',
        'razon_social'  => "QA RUC INVALIDO $suf",
        'ruc_proveedor' => 'ABC',
        'telefono_proveedor' => '0981111111',
    ], $session);
    $sinRucInvalido = !(bool)qa_func_scalar("SELECT 1 FROM proveedor WHERE razon_social = :r", [':r' => "QA RUC INVALIDO $suf"]);
    qa_func_check($results, 'func.proveedor.rechazo_ruc', 'Proveedor: rechazo RUC inválido', $sinRucInvalido, 'no insertado');

    // ── Materia prima: alta / edición / inactivación ──
    $mpDesc = "QA MP $suf";
    qa_func_invoke('modules/compras/materia_prima/proses.php', ['act' => 'insert'], [
        'Guardar'                    => '1',
        'materia_prima_descripcion'  => $mpDesc,
        'id_unidad'                  => (string)$unidad,
        'iva_id'                     => (string)$iva,
        'deposito_predeterminado_id' => (string)$dep,
    ], $session);
    $mpId = (int)qa_func_scalar('SELECT id_materia_prima FROM materia_prima WHERE materia_prima_descripcion = :d', [':d' => $mpDesc]);
    qa_func_check($results, 'func.materia_prima.insert', 'Materia prima: alta con depósito/stock 0', $mpId > 0, "mp #$mpId");
    $ctx['qa_mp_id'] = $mpId;

    if ($mpId > 0) {
        $mpDesc2 = "QA MP EDIT $suf";
        qa_func_invoke('modules/compras/materia_prima/proses.php', ['act' => 'update'], [
            'Guardar'                    => '1',
            'id_materia_prima'           => (string)$mpId,
            'materia_prima_descripcion'  => $mpDesc2,
            'id_unidad'                  => (string)$unidad,
            'iva_id'                     => (string)$iva,
            'deposito_predeterminado_id' => (string)$dep,
            'materia_prima_estado'       => 'ACTIVO',
        ], $session);
        $descGuard = qa_func_scalar('SELECT materia_prima_descripcion FROM materia_prima WHERE id_materia_prima = :id', [':id' => $mpId]);
        qa_func_check($results, 'func.materia_prima.update', 'Materia prima: edición', $descGuard === $mpDesc2, $descGuard);

        qa_func_invoke('modules/compras/materia_prima/proses.php', ['act' => 'delete'], [
            'id' => (string)$mpId,
        ], $session);
        $estMp = strtoupper((string)qa_func_scalar('SELECT materia_prima_estado FROM materia_prima WHERE id_materia_prima = :id', [':id' => $mpId]));
        qa_func_check($results, 'func.materia_prima.inactivar', 'Materia prima: inactivación sin vínculos', $estMp === 'INACTIVO', $estMp);

        $stockOk = (bool)qa_func_scalar('SELECT 1 FROM stock_materia_prima WHERE id_materia_prima = :id AND deposito_id = :d', [':id' => $mpId, ':d' => $dep]);
        qa_func_check($results, 'func.materia_prima.stock_creado', 'Materia prima: registro stock al alta', $stockOk, "dep #$dep");
    }
}

function validar_compras_oc_observaciones(array &$results): void
{
    $session = qa_func_admin_session();
    $obs1 = 'QA OC observaciones ' . date('His');
    $obs2 = $obs1 . ' editado';

    $pedId = qa_func_next_id('pedidos_compra', 'id_pedido_compra');
    qa_func_invoke('modules/compras/pedido_compra/proses.php', ['act' => 'insert'], [
        'Guardar' => '1', 'codigo' => (string)$pedId,
        'productos' => json_encode([['codigo' => 9, 'cantidad' => 2]]),
    ], $session);

    $preId = qa_func_next_id('presupuesto_compra', 'id_presupuesto_compra');
    $total = 2 * 5000;
    qa_func_invoke('modules/compras/presupuesto/proses.php', ['act' => 'insert'], [
        'Guardar' => '1', 'codigo' => (string)$preId, 'pedido' => (string)$pedId,
        'proveedor' => '12', 'total_importe' => (string)$total,
        'productos' => json_encode([['codigo' => 9, 'cantidad' => 2, 'precio' => 5000, 'descuento' => 0]]),
    ], $session);

    $ocId = qa_func_next_id('orden_de_compra', 'id_orden_compra');
    qa_func_invoke('modules/compras/orden_compra/proses.php', ['act' => 'insert'], [
        'Guardar' => '1', 'oc_codigo' => (string)$ocId, 'presupuesto' => (string)$preId,
        'proveedor' => '12', 'oc_sucursal' => (string)$session['id_sucursal'],
        'oc_usuario' => (string)$session['id_usuario'], 'orden_condicion' => 'CONTADO',
        'total_importe' => (string)$total, 'oc_observaciones' => $obs1,
        'productos' => json_encode([['codigo' => 9, 'cantidad' => 2, 'precio' => 5000]]),
    ], $session);

    $guard1 = (string)qa_func_scalar('SELECT COALESCE(orden_observaciones, \'\') FROM orden_de_compra WHERE id_orden_compra = :id', [':id' => $ocId]);
    qa_func_check($results, 'func.oc.observaciones.insert', 'OC: guardar observaciones al alta', $guard1 === $obs1, substr($guard1, 0, 40));

    qa_func_invoke('modules/compras/orden_compra/proses.php', ['act' => 'update'], [
        'Guardar' => '1', 'oc_codigo' => (string)$ocId,
        'orden_condicion' => 'CONTADO', 'oc_observaciones' => $obs2,
    ], $session);

    $guard2 = (string)qa_func_scalar('SELECT COALESCE(orden_observaciones, \'\') FROM orden_de_compra WHERE id_orden_compra = :id', [':id' => $ocId]);
    qa_func_check($results, 'func.oc.observaciones.update', 'OC: edición de observaciones en EMITIDA', $guard2 === $obs2, substr($guard2, 0, 40));
}

function validar_compras_informes_rango(array &$results): void
{
    $desde = (string)qa_func_scalar("SELECT COALESCE(MIN(pedido_fecha_emision)::date, CURRENT_DATE) FROM pedidos_compra");
    $hasta = (string)qa_func_scalar("SELECT COALESCE(MAX(pedido_fecha_emision)::date, CURRENT_DATE) FROM pedidos_compra");
    $params = ['estado' => 'REPORTE TOTAL', 'desde' => $desde, 'hasta' => $hasta];

    $informes = [
        ['func.informe.pedido', 'Informe rango: pedidos de compra', 'modules/compras/pedido_compra/generar_informe.php'],
        ['func.informe.presupuesto', 'Informe rango: presupuestos', 'modules/compras/presupuesto/generar_informe.php'],
        ['func.informe.oc', 'Informe rango: órdenes de compra', 'modules/compras/orden_compra/generar_informe.php'],
        ['func.informe.factura', 'Informe rango: facturas compra', 'modules/compras/gestionar_compras/generar_informe.php'],
        ['func.informe.notas', 'Informe rango: notas crédito/débito', 'modules/compras/nota_credito/generar_informe.php'],
        ['func.informe.nr', 'Informe rango: notas de remisión', 'modules/compras/nota_remision/generar_informe.php'],
        ['func.informe.ajustes', 'Informe rango: ajustes de stock', 'modules/compras/ajustes/generar_informe.php'],
    ];

    foreach ($informes as [$id, $nombre, $path]) {
        $rep = qa_func_informe_ok($path, $params);
        $det = ($rep['is_pdf'] ?? false) ? (($rep['bytes'] ?? 0) . ' bytes PDF') : (($rep['sin_datos'] ?? false) ? 'sin filas (generador OK)' : 'falló');
        qa_func_check($results, $id, $nombre, $rep['ok'] ?? false, $det);
    }

    // Rechazo fechas inválidas
    $bad = qa_func_report_ok('modules/compras/pedido_compra/generar_informe.php', [
        'estado' => 'PENDIENTE', 'desde' => '2026-12-01', 'hasta' => '2026-01-01',
    ]);
    qa_func_check($results, 'func.informe.rechazo_fechas', 'Informe: rechazo Desde > Hasta', !($bad['is_pdf'] ?? false), 'sin PDF');
}

function validar_compras_ediciones(array &$results, array &$ctx): void
{
    $session = qa_func_admin_session();
    $hoy = qa_func_hoy();

    // Pedido PENDIENTE → editar cantidad
    $pedId = qa_func_next_id('pedidos_compra', 'id_pedido_compra');
    qa_func_invoke('modules/compras/pedido_compra/proses.php', ['act' => 'insert'], [
        'Guardar' => '1', 'codigo' => (string)$pedId,
        'productos' => json_encode([['codigo' => 9, 'cantidad' => 4]]),
    ], $session);
    $ultMod = qa_func_scalar('SELECT pedido_ultima_modificacion FROM pedidos_compra WHERE id_pedido_compra = :id', [':id' => $pedId]);
    qa_func_invoke('modules/compras/pedido_compra/proses.php', ['act' => 'update'], [
        'Guardar' => '1',
        'pedido_id' => (string)$pedId,
        'pedido_ultima_modificacion' => (string)$ultMod,
        'cantidad' => ['9' => '7'],
        'observaciones' => 'QA edición cantidades',
    ], $session);
    $cant = (int)qa_func_scalar('SELECT cantidad_pedido FROM pedido_detalle_compra WHERE id_pedido_compra = :p AND id_materia_prima = 9', [':p' => $pedId]);
    qa_func_check($results, 'func.pedido.update', 'Pedido: edición cantidades en PENDIENTE', $cant === 7, "cant=$cant");

    // Ajuste: insertar (entrada/sobrante) y editar
    $depId = (int)qa_func_scalar('SELECT deposito_id FROM deposito LIMIT 1');
    $motAjuste = (int)qa_func_scalar("SELECT id_motivo FROM motivo WHERE categoria_motivo = 'AJUSTE' AND motivo_descripcion ILIKE 'sobrante' LIMIT 1");
    if ($motAjuste <= 0) {
        $motAjuste = (int)qa_func_scalar("SELECT id_motivo FROM motivo WHERE categoria_motivo = 'AJUSTE' LIMIT 1");
    }
    $mpAjuste = 10;
    $ajAntes = (int)qa_func_scalar('SELECT COALESCE(MAX(id_ajuste), 0) FROM ajustes');
    $rAjIns = qa_func_invoke('modules/compras/ajustes/proses.php', ['act' => 'insert'], [
        'deposito_id' => (string)$depId,
        'ajuste_motivo_id' => (string)$motAjuste,
        'ajuste_fecha' => $hoy,
        'tipo_regularizacion' => '',
        'detalle_json' => json_encode([['id_materia_prima' => $mpAjuste, 'cantidad' => 1]]),
    ], $session);
    $ajId = (int)qa_func_scalar('SELECT id_ajuste FROM ajustes WHERE id_ajuste > :a ORDER BY id_ajuste ASC LIMIT 1', [':a' => $ajAntes]);
    if ($ajId <= 0) {
        qa_func_check($results, 'func.ajuste.update', 'Ajustes: edición de cantidades', false, 'no se pudo crear ajuste QA: ' . ($rAjIns['output'] ?? ''));
    } else {
        qa_func_invoke('modules/compras/ajustes/proses.php', ['act' => 'update'], [
            'id_ajuste' => (string)$ajId,
            'deposito_id' => (string)$depId,
            'ajuste_motivo_id' => (string)$motAjuste,
            'ajuste_fecha' => $hoy,
            'tipo_regularizacion' => '',
            'detalle_json' => json_encode([['id_materia_prima' => $mpAjuste, 'cantidad' => 3]]),
        ], $session);
        $cantAj = (int)qa_func_scalar('SELECT ajuste_cantidad FROM ajustes_detalle WHERE id_ajuste = :id LIMIT 1', [':id' => $ajId]);
        qa_func_check($results, 'func.ajuste.update', 'Ajustes: edición de cantidades', $cantAj === 3, "ajuste #$ajId cant=$cantAj");
        $ctx['qa_ajuste_editable_id'] = $ajId;
    }
}

function validar_compras_anulaciones(array &$results, array &$ctx): void
{
    $session = qa_func_admin_session();
    $hoy = qa_func_hoy();
    $suf = (int)date('His');

    // Presupuesto EMITIDO sin OC → anular OK
    $pedPre = qa_func_next_id('pedidos_compra', 'id_pedido_compra');
    qa_func_invoke('modules/compras/pedido_compra/proses.php', ['act' => 'insert'], [
        'Guardar' => '1', 'codigo' => (string)$pedPre,
        'productos' => json_encode([['codigo' => 9, 'cantidad' => 3]]),
    ], $session);
    $preUi = qa_func_next_id('presupuesto_compra', 'id_presupuesto_compra');
    $totalPre = 3 * 5000;
    qa_func_invoke('modules/compras/presupuesto/proses.php', ['act' => 'insert'], [
        'Guardar' => '1', 'codigo' => (string)$preUi, 'pedido' => (string)$pedPre,
        'proveedor' => '12', 'total_importe' => (string)$totalPre,
        'productos' => json_encode([['codigo' => 9, 'cantidad' => 3, 'precio' => 5000, 'descuento' => 0]]),
    ], $session);
    $preId = (int)qa_func_scalar('SELECT id_presupuesto_compra FROM presupuesto_compra WHERE id_pedido_compra = :p ORDER BY 1 DESC LIMIT 1', [':p' => $pedPre]);
    qa_func_invoke('modules/compras/presupuesto/proses.php', ['act' => 'anular', 'pre_id' => $preId], [], $session);
    $preAnul = strtoupper((string)qa_func_scalar('SELECT presu_estado FROM presupuesto_compra WHERE id_presupuesto_compra = :id', [':id' => $preId])) === 'ANULADO';
    qa_func_check($results, 'func.presupuesto.anular', 'Presupuesto: anulación EMITIDO sin OC', $preAnul, "pre #$preId");

    // OC EMITIDA sin NR/factura → anular OK
    $pedOc = qa_func_next_id('pedidos_compra', 'id_pedido_compra');
    qa_func_invoke('modules/compras/pedido_compra/proses.php', ['act' => 'insert'], [
        'Guardar' => '1', 'codigo' => (string)$pedOc,
        'productos' => json_encode([['codigo' => 9, 'cantidad' => 2]]),
    ], $session);
    qa_func_invoke('modules/compras/presupuesto/proses.php', ['act' => 'insert'], [
        'Guardar' => '1', 'codigo' => (string)($preUi + 1), 'pedido' => (string)$pedOc,
        'proveedor' => '12', 'total_importe' => '10000',
        'productos' => json_encode([['codigo' => 9, 'cantidad' => 2, 'precio' => 5000, 'descuento' => 0]]),
    ], $session);
    $preOc = (int)qa_func_scalar('SELECT id_presupuesto_compra FROM presupuesto_compra WHERE id_pedido_compra = :p ORDER BY 1 DESC LIMIT 1', [':p' => $pedOc]);
    $ocId = qa_func_next_id('orden_de_compra', 'id_orden_compra');
    qa_func_invoke('modules/compras/orden_compra/proses.php', ['act' => 'insert'], [
        'Guardar' => '1', 'oc_codigo' => (string)$ocId, 'presupuesto' => (string)$preOc,
        'proveedor' => '12', 'oc_sucursal' => (string)$session['id_sucursal'],
        'oc_usuario' => (string)$session['id_usuario'], 'orden_condicion' => 'CONTADO',
        'total_importe' => '10000',
        'productos' => json_encode([['codigo' => 9, 'cantidad' => 2, 'precio' => 5000]]),
    ], $session);
    qa_func_invoke('modules/compras/orden_compra/proses.php', ['act' => 'anular', 'orden_id' => $ocId], [], $session);
    $ocAnul = strtoupper((string)qa_func_scalar('SELECT orden_estado FROM orden_de_compra WHERE id_orden_compra = :id', [':id' => $ocId])) === 'ANULADO';
    qa_func_check($results, 'func.oc.anular', 'OC: anulación EMITIDA sin NR/factura', $ocAnul, "OC #$ocId");

    // NR sin factura → anular OK
    $pedNr = qa_func_next_id('pedidos_compra', 'id_pedido_compra');
    qa_func_invoke('modules/compras/pedido_compra/proses.php', ['act' => 'insert'], [
        'Guardar' => '1', 'codigo' => (string)$pedNr,
        'productos' => json_encode([['codigo' => 9, 'cantidad' => 2]]),
    ], $session);
    qa_func_invoke('modules/compras/presupuesto/proses.php', ['act' => 'insert'], [
        'Guardar' => '1', 'codigo' => (string)($preUi + 2), 'pedido' => (string)$pedNr,
        'proveedor' => '12', 'total_importe' => '10000',
        'productos' => json_encode([['codigo' => 9, 'cantidad' => 2, 'precio' => 5000, 'descuento' => 0]]),
    ], $session);
    $preNr = (int)qa_func_scalar('SELECT id_presupuesto_compra FROM presupuesto_compra WHERE id_pedido_compra = :p ORDER BY 1 DESC LIMIT 1', [':p' => $pedNr]);
    $ocNr = qa_func_next_id('orden_de_compra', 'id_orden_compra');
    qa_func_invoke('modules/compras/orden_compra/proses.php', ['act' => 'insert'], [
        'Guardar' => '1', 'oc_codigo' => (string)$ocNr, 'presupuesto' => (string)$preNr,
        'proveedor' => '12', 'oc_sucursal' => (string)$session['id_sucursal'],
        'oc_usuario' => (string)$session['id_usuario'], 'orden_condicion' => 'CONTADO',
        'total_importe' => '10000',
        'productos' => json_encode([['codigo' => 9, 'cantidad' => 2, 'precio' => 5000]]),
    ], $session);
    $fechaOc = (string)qa_func_scalar('SELECT orden_fecha::date FROM orden_de_compra WHERE id_orden_compra = :id', [':id' => $ocNr]);
    $fechaNr = $fechaOc > $hoy ? $hoy : ($fechaOc ?: $hoy);
    $cond = (int)qa_func_scalar('SELECT conductor_id FROM conductores LIMIT 1');
    $veh = (int)qa_func_scalar('SELECT vehiculo_id FROM vehiculos LIMIT 1');
    qa_func_invoke('modules/compras/nota_remision/proses.php', ['act' => 'insert_nr'], [
        'id_orden_compra' => (string)$ocNr, 'id_proveedor' => '12',
        'conductor_id' => (string)$cond, 'vehiculo_id' => (string)$veh,
        'nota_remision_nro' => sprintf('005-005-%07d', 5000000 + ($suf % 4000000)),
        'fecha_remision' => $fechaNr, 'hora_remision' => '09:00:00',
        'productos' => json_encode([['id_materia_prima' => 9, 'cantidad' => 2]]),
    ], $session);
    $nrSola = (int)qa_func_scalar('SELECT id_nota_remision FROM nota_remision_compra WHERE id_orden_compra = :oc ORDER BY 1 DESC LIMIT 1', [':oc' => $ocNr]);
    qa_func_invoke('modules/compras/nota_remision/proses.php', ['act' => 'anular_nr', 'id_nota_remision' => $nrSola], [], $session);
    $nrAnul = strtoupper((string)qa_func_scalar('SELECT nota_estado FROM nota_remision_compra WHERE id_nota_remision = :id', [':id' => $nrSola])) === 'ANULADO';
    qa_func_check($results, 'func.nr.anular', 'NR: anulación sin factura conciliada', $nrAnul, "NR #$nrSola");

    // NR conciliada → rechazo anulación
    if (!empty($ctx['nr_id'])) {
        $rRech = qa_func_invoke('modules/compras/nota_remision/proses.php', [
            'act' => 'anular_nr', 'id_nota_remision' => (int)$ctx['nr_id'],
        ], [], $session);
        $sigueEmitida = strtoupper((string)qa_func_scalar('SELECT nota_estado FROM nota_remision_compra WHERE id_nota_remision = :id', [':id' => $ctx['nr_id']])) === 'EMITIDA';
        qa_func_check($results, 'func.nr.rechazo_anular', 'NR: rechazo anular si conciliada con factura', $sigueEmitida || ($rRech['ok'] ?? true) === false, 'NR vinculada');
    }

    // Factura PENDIENTE → anular OK
    $pedFac = qa_func_next_id('pedidos_compra', 'id_pedido_compra');
    qa_func_invoke('modules/compras/pedido_compra/proses.php', ['act' => 'insert'], [
        'Guardar' => '1', 'codigo' => (string)$pedFac,
        'productos' => json_encode([['codigo' => 9, 'cantidad' => 1]]),
    ], $session);
    qa_func_invoke('modules/compras/presupuesto/proses.php', ['act' => 'insert'], [
        'Guardar' => '1', 'codigo' => (string)($preUi + 3), 'pedido' => (string)$pedFac,
        'proveedor' => '12', 'total_importe' => '5000',
        'productos' => json_encode([['codigo' => 9, 'cantidad' => 1, 'precio' => 5000, 'descuento' => 0]]),
    ], $session);
    $preFac = (int)qa_func_scalar('SELECT id_presupuesto_compra FROM presupuesto_compra WHERE id_pedido_compra = :p ORDER BY 1 DESC LIMIT 1', [':p' => $pedFac]);
    $ocFac = qa_func_next_id('orden_de_compra', 'id_orden_compra');
    qa_func_invoke('modules/compras/orden_compra/proses.php', ['act' => 'insert'], [
        'Guardar' => '1', 'oc_codigo' => (string)$ocFac, 'presupuesto' => (string)$preFac,
        'proveedor' => '12', 'oc_sucursal' => (string)$session['id_sucursal'],
        'oc_usuario' => (string)$session['id_usuario'], 'orden_condicion' => 'CONTADO',
        'total_importe' => '5000',
        'productos' => json_encode([['codigo' => 9, 'cantidad' => 1, 'precio' => 5000]]),
    ], $session);
    $nroPend = sprintf('006-006-%07d', 6000000 + ($suf % 3000000));
    qa_func_invoke('modules/compras/gestionar_compras/proses.php', ['act' => 'insert'], [
        'fecha' => $hoy, 'hora' => '14:00:00', 'fecha_emision_factura' => $hoy,
        'orden_compra' => (string)$ocFac, 'numero_factura' => $nroPend, 'timbrado' => '66778899',
        'orden_condicion' => 'CONTADO', 'total_importe' => '5000', 'total_con_interes' => '5000',
        'productos' => json_encode([['codigo' => 9, 'cantidad' => 1, 'precio' => 5000]]),
    ], $session);
    $facPend = (int)qa_func_scalar('SELECT id_factura_compra FROM factura_compra WHERE id_orden_compra = :oc ORDER BY 1 DESC LIMIT 1', [':oc' => $ocFac]);
    $rAnFac = qa_func_invoke_json('modules/compras/gestionar_compras/anular_factura.php', [], ['factura_id' => $facPend], $session);
    $facAnul = strtoupper((string)qa_func_scalar('SELECT fac_estado FROM factura_compra WHERE id_factura_compra = :id', [':id' => $facPend])) === 'ANULADO';
    qa_func_check($results, 'func.factura.anular_pendiente', 'Factura: anulación en PENDIENTE', $facAnul || ($rAnFac['ok'] ?? false), "fac #$facPend");

    // Factura EMITIDA → rechazo anulación
    if (!empty($ctx['fac_contado_id'])) {
        $rRechFac = qa_func_invoke_json('modules/compras/gestionar_compras/anular_factura.php', [], [
            'factura_id' => (int)$ctx['fac_contado_id'],
        ], $session);
        $sigueEmitida = strtoupper((string)qa_func_scalar('SELECT fac_estado FROM factura_compra WHERE id_factura_compra = :id', [':id' => $ctx['fac_contado_id']])) === 'EMITIDA';
        qa_func_check($results, 'func.factura.rechazo_anular', 'Factura: rechazo anular EMITIDA', $sigueEmitida, $rRechFac['message'] ?? 'EMITIDA');
    }

    // Ajuste → anular
    if (!empty($ctx['qa_ajuste_editable_id'])) {
        $ajId = (int)$ctx['qa_ajuste_editable_id'];
        qa_func_invoke('modules/compras/ajustes/proses.php', ['act' => 'anular', 'ajuste_id' => $ajId], [], $session);
        $ajAnul = strtoupper((string)qa_func_scalar('SELECT ajuste_estado FROM ajustes WHERE id_ajuste = :id', [':id' => $ajId])) === 'ANULADO';
        qa_func_check($results, 'func.ajuste.anular', 'Ajustes: anulación con reversión stock', $ajAnul, "ajuste #$ajId");
    }

    // Presupuesto con OC → rechazo anulación
    if (!empty($ctx['presupuesto_contado_id'])) {
        qa_func_invoke('modules/compras/presupuesto/proses.php', [
            'act' => 'anular', 'pre_id' => (int)$ctx['presupuesto_contado_id'],
        ], [], $session);
        $noAnul = strtoupper((string)qa_func_scalar('SELECT presu_estado FROM presupuesto_compra WHERE id_presupuesto_compra = :id', [
            ':id' => $ctx['presupuesto_contado_id'],
        ])) !== 'ANULADO';
        qa_func_check($results, 'func.presupuesto.rechazo_anular', 'Presupuesto: rechazo anular con OC', $noAnul, 'pre con OC');
    }
}

function validar_compras_nota_debito(array &$results, array &$ctx): void
{
    $facId = (int)($ctx['fac_credito_id'] ?? 0);
    if ($facId <= 0) {
        qa_func_check($results, 'func.nota_debito.insert', 'Nota débito: alta sobre CREDITO', false, 'sin factura crédito');
        return;
    }

    $session = qa_func_admin_session();
    $hoy = qa_func_hoy();
    $suf = (int)date('His');
    $prov = (int)qa_func_scalar('
        SELECT oc.id_proveedor FROM factura_compra f
        JOIN orden_de_compra oc ON oc.id_orden_compra = f.id_orden_compra
        WHERE f.id_factura_compra = :id', [':id' => $facId]);

    qa_func_pdo()->prepare("UPDATE factura_compra SET fact_fecha_compra = :f::timestamp WHERE id_factura_compra = :id")
        ->execute([':f' => $hoy . ' 15:00:00', ':id' => $facId]);

    $motivoNd = 4; // DIFERENCIA (nota débito)
    $det = qa_func_pdo()->query("SELECT id_materia_prima, fac_cantidad, fac_precio FROM factura_detalle_compra WHERE id_factura_compra = $facId LIMIT 1")->fetch();
    $precioNd = (int)$det['fac_precio'] + 500;
    $items = [[
        'id_materia_prima' => (int)$det['id_materia_prima'],
        'cantidad' => (int)$det['fac_cantidad'],
        'precio' => $precioNd,
    ]];
    $diff = ((int)$det['fac_cantidad'] * $precioNd) - ((int)$det['fac_cantidad'] * (int)$det['fac_precio']);

    $yaNd = (bool)qa_func_scalar("SELECT 1 FROM nota_compra WHERE id_factura_compra = :f AND nota_compra_tipo = 'DEBITO' LIMIT 1", [':f' => $facId]);
    if (!$yaNd) {
        qa_func_invoke('modules/compras/nota_credito/proses.php', ['act' => 'insert_nota'], [
            'id_proveedor'  => (string)$prov,
            'fact_id'       => (string)$facId,
            'nota_tipo'     => 'DEBITO',
            'motivo_id'     => (string)$motivoNd,
            'nota_nro'      => (string)(400000 + $suf),
            'nota_timbrado' => '77889900',
            'nota_emision'  => $hoy,
            'nota_vto'      => date('Y-m-d', strtotime($hoy . ' +1 year')),
            'nota_fecha'    => $hoy,
            'nota_hora'     => '16:00:00',
            'productos'     => json_encode($items),
            'nota_total_num'=> (string)max(1, $diff),
        ], $session);
    }

    $ndOk = (bool)qa_func_scalar("SELECT 1 FROM nota_compra WHERE id_factura_compra = :f AND nota_compra_tipo = 'DEBITO'", [':f' => $facId]);
    qa_func_check($results, 'func.nota_debito.insert', 'Nota débito: alta sobre factura CREDITO', $ndOk, "factura #$facId");
}

function validar_compras_anular_nota(array &$results, array &$ctx): void
{
    $facId = (int)($ctx['fac_credito_id'] ?? 0);
    if ($facId <= 0) {
        qa_func_check($results, 'func.nota.anular', 'Nota crédito: anulación EMITIDA', false, 'sin factura crédito');
        return;
    }

    $session = qa_func_admin_session();
    $hoy = qa_func_hoy();
    $suf = (int)date('His');

    require_once dirname(__DIR__) . '/config/bitacora_helper.php';
    bitacora_ensure_anulacion_accion(qa_func_pdo());

    $det = qa_func_row('SELECT id_materia_prima, fac_cantidad, fac_precio FROM factura_detalle_compra WHERE id_factura_compra = :f LIMIT 1', [':f' => $facId]);
    if (!$det) {
        qa_func_check($results, 'func.nota.anular', 'Nota crédito: anulación EMITIDA', false, 'sin detalle factura');
        return;
    }

    $prov = (int)qa_func_scalar('
        SELECT oc.id_proveedor FROM factura_compra f
        JOIN orden_de_compra oc ON oc.id_orden_compra = f.id_orden_compra
        WHERE f.id_factura_compra = :id', [':id' => $facId]);

    $cantFac = (int)$det['fac_cantidad'];
    $cantNc = max(1, $cantFac - 1);
    $precio = (int)$det['fac_precio'];
    $totalNc = max(1000, ($cantFac - $cantNc) * $precio);

    $ctaAntes = (int)qa_func_scalar('SELECT monto_total FROM cuentas_pagar WHERE id_factura_compra = :f', [':f' => $facId]);

    qa_func_invoke('modules/compras/nota_credito/proses.php', ['act' => 'insert_nota'], [
        'id_proveedor'   => (string)$prov,
        'fact_id'        => (string)$facId,
        'nota_tipo'      => 'CREDITO',
        'motivo_id'      => '2',
        'nota_nro'       => (string)(500000 + $suf),
        'nota_timbrado'  => '88990011',
        'nota_emision'   => $hoy,
        'nota_vto'       => date('Y-m-d', strtotime($hoy . ' +1 year')),
        'nota_fecha'     => $hoy,
        'nota_hora'      => '17:00:00',
        'productos'      => json_encode([[
            'id_materia_prima' => (int)$det['id_materia_prima'],
            'cantidad' => $cantNc,
            'precio' => $precio,
        ]]),
        'nota_total_num' => (string)$totalNc,
    ], $session);

    $notaId = (int)qa_func_scalar("
        SELECT id_nota_compra FROM nota_compra
        WHERE id_factura_compra = :f AND nota_nro = :nro
        ORDER BY id_nota_compra DESC LIMIT 1
    ", [':f' => $facId, ':nro' => (string)(500000 + $suf)]);

    if ($notaId <= 0) {
        qa_func_check($results, 'func.nota.anular', 'Nota crédito: anulación EMITIDA', false, 'no se creó NC de prueba');
        return;
    }

    $ctaTrasNc = (int)qa_func_scalar('SELECT monto_total FROM cuentas_pagar WHERE id_factura_compra = :f', [':f' => $facId]);

    $rAn = qa_func_invoke_json('modules/compras/nota_credito/anular_nota.php', [], ['nota_id' => $notaId], $session);
    $estNota = strtoupper((string)qa_func_scalar('SELECT nota_compra_estado FROM nota_compra WHERE id_nota_compra = :id', [':id' => $notaId]));
    $ctaTrasAn = (int)qa_func_scalar('SELECT monto_total FROM cuentas_pagar WHERE id_factura_compra = :f', [':f' => $facId]);
    $bitOk = (bool)qa_func_scalar("
        SELECT 1 FROM bitacora
        WHERE id_registro = :id AND accion_realizada = 'ANULACION'
        LIMIT 1
    ", [':id' => $notaId]);

    $revertida = ($estNota === 'ANULADO') && ($ctaTrasAn >= $ctaTrasNc);
    qa_func_check(
        $results,
        'func.nota.anular',
        'Nota crédito: anulación EMITIDA',
        ($rAn['ok'] ?? false) && $revertida,
        "nota #$notaId estado=$estNota cta {$ctaTrasNc}→{$ctaTrasAn}"
    );
    qa_func_check($results, 'func.bitacora.anulacion', 'Bitácora: acción ANULACION registrada', $bitOk, "nota #$notaId");
}

function validar_compras_informe_router(array &$results): void
{
    $desde = (string)qa_func_scalar("SELECT COALESCE(MIN(nota_compra_fecha)::date, CURRENT_DATE) FROM nota_compra");
    $hasta = (string)qa_func_scalar("SELECT COALESCE(MAX(nota_compra_fecha)::date, CURRENT_DATE) FROM nota_compra");

    $repNd = qa_func_informe_ok('modules/compras/nota_credito/generar_informe.php', [
        'estado' => 'REPORTE TOTAL',
        'desde'  => $desde,
        'hasta'  => $hasta,
        'tipo'   => 'DEBITO',
    ]);
    qa_func_check(
        $results,
        'func.informe.nota_debito',
        'Informe rango: notas débito (filtro tipo)',
        $repNd['ok'] ?? false,
        ($repNd['is_pdf'] ?? false) ? (($repNd['bytes'] ?? 0) . ' bytes PDF') : 'sin filas OK'
    );

    $def = (string)qa_func_scalar("
        SELECT pg_get_constraintdef(oid)
        FROM pg_constraint WHERE conname = 'ck_bitacora_accion' LIMIT 1
    ");
    qa_func_check(
        $results,
        'func.bitacora.schema',
        'Bitácora: constraint admite ANULACION',
        stripos($def, 'ANULACION') !== false,
        $def !== '' ? 'OK' : 'sin constraint'
    );
}
