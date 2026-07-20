<?php
/**
 * Validaciones QA — todos los submódulos de Producción.
 */
declare(strict_types=1);

function validar_produccion_completo(PDO $pdo, array &$results): void
{
    validar_produccion_estructura_codigo($results);
    validar_produccion_pedido($pdo, $results);
    validar_produccion_orden($pdo, $results);
    validar_produccion_control($pdo, $results);
    validar_produccion_productos_terminados($pdo, $results);
    validar_produccion_calidad($pdo, $results);
    validar_produccion_pedido_materia($pdo, $results);
    validar_produccion_reposicion($pdo, $results);
    validar_produccion_referenciales($pdo, $results);
    validar_produccion_cadena_documentos($pdo, $results);
    validar_produccion_perdidas_integridad($pdo, $results);
    validar_produccion_equipos_costos_integridad($pdo, $results);
}

function validar_produccion_estructura_codigo(array &$results): void
{
    $root = dirname(__DIR__) . '/modules/produccion';
    $modulos = [
        'pedido_produccion'        => ['view.php', 'form.php', 'proses.php'],
        'orden_produccion'         => ['view.php', 'form.php', 'proses.php'],
        'control_produccion'       => ['view.php', 'form.php', 'proses.php'],
        'productos_terminados'     => ['view.php', 'form.php', 'proses.php'],
        'control_calidad'          => ['view.php', 'form.php', 'proses.php'],
        'perdidas'                 => ['view.php', 'form.php', 'proses.php'],
        'pedido_materia_produccion'=> ['view.php', 'form.php', 'proses.php'],
        'reposicion_materia'       => ['view.php', 'form.php', 'proses.php'],
        'parametros_control'       => ['view.php', 'proses.php'],
        'etapa_produccion'         => ['view.php', 'form.php', 'proses.php'],
        'equipos_produccion'       => ['view.php', 'form.php', 'proses.php'],
        'costos_produccion'        => ['view.php', 'form.php', 'proses.php'],
        'inspectores'              => ['view.php', 'form.php', 'proses.php'],
        'trabajadores'             => ['view.php', 'form.php', 'proses.php'],
    ];

    $faltantes = [];
    foreach ($modulos as $mod => $archivos) {
        foreach ($archivos as $arch) {
            if (!is_file("$root/$mod/$arch")) {
                $faltantes[] = "$mod/$arch";
            }
        }
    }

    qa_check(
        $results,
        'produccion.estructura.archivos',
        'Estructura: archivos PHP de cada submódulo Producción',
        empty($faltantes),
        empty($faltantes) ? count($modulos) . ' submódulos con archivos base' : 'Faltan: ' . implode(', ', $faltantes)
    );

    $gen = dirname(__DIR__) . '/modules/comunes/reportes/generador_produccion.php';
    qa_check(
        $results,
        'produccion.estructura.informes',
        'Estructura: generador unificado de informes producción',
        is_file($gen),
        is_file($gen) ? 'generador_produccion.php presente' : 'Falta generador'
    );
}

function validar_produccion_pedido(PDO $pdo, array &$results): void
{
    $pedidos = $pdo->query("
        SELECT p.id_pedido_produccion, p.pedido_prod_estado,
               (SELECT COUNT(*) FROM pedido_detalle_produccion d WHERE d.id_pedido_produccion = p.id_pedido_produccion) AS items
        FROM pedido_produccion p
        ORDER BY p.id_pedido_produccion
    ")->fetchAll();

    $sinDetalle = [];
    foreach ($pedidos as $p) {
        if ((int)$p['items'] === 0 && strtoupper((string)$p['pedido_prod_estado']) !== 'ANULADO') {
            $sinDetalle[] = "#{$p['id_pedido_produccion']} estado={$p['pedido_prod_estado']}";
        }
    }

    $cantInvalida = $pdo->query("
        SELECT id_pedido_produccion, producto_id, cantidad_pedido
        FROM pedido_detalle_produccion WHERE cantidad_pedido <= 0
    ")->fetchAll();

    qa_check(
        $results,
        'produccion.pedido.detalle',
        'Pedido producción: todo pedido activo tiene al menos un ítem',
        empty($sinDetalle),
        empty($sinDetalle) ? count($pedidos) . ' pedido(s)' : implode('; ', $sinDetalle)
    );

    qa_check(
        $results,
        'produccion.pedido.cantidades',
        'Pedido producción: cantidades > 0 en detalle',
        empty($cantInvalida),
        empty($cantInvalida) ? 'OK' : count($cantInvalida) . ' línea(s) inválida(s)'
    );
}

function validar_produccion_orden(PDO $pdo, array &$results): void
{
    $huerfanas = $pdo->query("
        SELECT op.orden_id
        FROM orden_produccion op
        LEFT JOIN pedido_produccion pp ON pp.id_pedido_produccion = op.id_pedido_produccion
        WHERE pp.id_pedido_produccion IS NULL
    ")->fetchAll();

    $sinDetalle = $pdo->query("
        SELECT op.orden_id
        FROM orden_produccion op
        WHERE UPPER(TRIM(op.orden_prod_estado)) <> 'ANULADA'
          AND NOT EXISTS (SELECT 1 FROM orden_detalle_produccion od WHERE od.orden_id = op.orden_id)
    ")->fetchAll();

    qa_check(
        $results,
        'produccion.orden.pedido_fk',
        'Orden producción: toda orden referencia un pedido existente',
        empty($huerfanas),
        empty($huerfanas) ? 'FK pedido OK' : count($huerfanas) . ' orden(es) huérfana(s)'
    );

    qa_check(
        $results,
        'produccion.orden.detalle',
        'Orden producción: órdenes activas tienen detalle',
        empty($sinDetalle),
        empty($sinDetalle) ? 'OK' : count($sinDetalle) . ' sin detalle'
    );
}

function validar_produccion_control(PDO $pdo, array &$results): void
{
    $sinOrden = $pdo->query("
        SELECT c.control_id
        FROM control_produccion c
        LEFT JOIN orden_produccion op ON op.orden_id = c.orden_id
        WHERE op.orden_id IS NULL
    ")->fetchAll();

    $sinDetalle = $pdo->query("
        SELECT c.control_id
        FROM control_produccion c
        WHERE UPPER(TRIM(c.control_estado)) = 'REGISTRADO'
          AND NOT EXISTS (
              SELECT 1 FROM control_produccion_detalle cd WHERE cd.control_id = c.control_id
          )
    ")->fetchAll();

    qa_check(
        $results,
        'produccion.control.orden_fk',
        'Control producción: referencia orden válida',
        empty($sinOrden),
        empty($sinOrden) ? 'FK orden OK' : count($sinOrden) . ' control(es) huérfano(s)'
    );

    qa_check(
        $results,
        'produccion.control.detalle',
        'Control producción: controles REGISTRADO tienen detalle',
        empty($sinDetalle),
        empty($sinDetalle) ? 'OK' : count($sinDetalle) . ' sin detalle'
    );
}

function validar_produccion_productos_terminados(PDO $pdo, array &$results): void
{
    $sinOrden = $pdo->query("
        SELECT pt.terminado_id
        FROM producto_terminado pt
        LEFT JOIN orden_produccion op ON op.orden_id = pt.orden_id
        WHERE op.orden_id IS NULL
    ")->fetchAll();

    $sinDetalle = $pdo->query("
        SELECT pt.terminado_id
        FROM producto_terminado pt
        WHERE NOT EXISTS (
            SELECT 1 FROM productos_terminados_detalle ptd WHERE ptd.terminado_id = pt.terminado_id
        )
    ")->fetchAll();

    qa_check(
        $results,
        'produccion.pt.orden_fk',
        'Productos terminados: lote referencia orden válida',
        empty($sinOrden),
        empty($sinOrden) ? 'FK orden OK' : count($sinOrden) . ' lote(s) huérfano(s)'
    );

    qa_check(
        $results,
        'produccion.pt.detalle',
        'Productos terminados: todo lote tiene detalle',
        empty($sinDetalle),
        empty($sinDetalle) ? 'OK' : count($sinDetalle) . ' sin detalle'
    );
}

function validar_produccion_calidad(PDO $pdo, array &$results): void
{
    $sinPt = $pdo->query("
        SELECT cc.calidad_id
        FROM control_calidad_produccion cc
        LEFT JOIN producto_terminado pt ON pt.terminado_id = cc.terminado_id
        WHERE pt.terminado_id IS NULL
    ")->fetchAll();

    $sinDetalle = $pdo->query("
        SELECT cc.calidad_id
        FROM control_calidad_produccion cc
        WHERE UPPER(TRIM(cc.calidad_estado)) IN ('APROBADO', 'NO CONFORME')
          AND NOT EXISTS (
              SELECT 1 FROM control_calidad_detalle cd WHERE cd.calidad_id = cc.calidad_id
          )
    ")->fetchAll();

    qa_check(
        $results,
        'produccion.calidad.pt_fk',
        'Control calidad: referencia lote PT válido',
        empty($sinPt),
        empty($sinPt) ? 'FK terminado OK' : count($sinPt) . ' calidad(es) huérfana(s)'
    );

    qa_check(
        $results,
        'produccion.calidad.detalle',
        'Control calidad: inspecciones activas tienen detalle',
        empty($sinDetalle),
        empty($sinDetalle) ? 'OK' : count($sinDetalle) . ' sin detalle'
    );
}

function validar_produccion_pedido_materia(PDO $pdo, array &$results): void
{
    $sinDetalle = $pdo->query("
        SELECT pm.id_pedido_mat_prod
        FROM pedido_materia_produccion pm
        WHERE UPPER(TRIM(pm.ped_mat_prod_estado)) <> 'ANULADO'
          AND NOT EXISTS (
              SELECT 1 FROM pedido_materia_detalle_produccion pd WHERE pd.id_pedido_mat_prod = pm.id_pedido_mat_prod
          )
    ")->fetchAll();

    $repuestaMayor = $pdo->query("
        SELECT id_pedido_mat_prod, id_materia_prima, ped_mat_prod_cantidad, cantidad_repuesta
        FROM pedido_materia_detalle_produccion
        WHERE cantidad_repuesta > ped_mat_prod_cantidad
    ")->fetchAll();

    qa_check(
        $results,
        'produccion.pedido_mp.detalle',
        'Pedido materia prima: pedidos activos tienen detalle',
        empty($sinDetalle),
        empty($sinDetalle) ? 'OK' : count($sinDetalle) . ' sin detalle'
    );

    qa_check(
        $results,
        'produccion.pedido_mp.repuesta',
        'Pedido materia prima: cantidad repuesta ≤ solicitada',
        empty($repuestaMayor),
        empty($repuestaMayor) ? 'OK' : count($repuestaMayor) . ' línea(s) inconsistente(s)'
    );
}

function validar_produccion_reposicion(PDO $pdo, array &$results): void
{
    $sinPedido = $pdo->query("
        SELECT r.reposicion_id
        FROM reposicion_materia r
        LEFT JOIN pedido_materia_produccion pm ON pm.id_pedido_mat_prod = r.id_pedido_mat_prod
        WHERE pm.id_pedido_mat_prod IS NULL
    ")->fetchAll();

    $sinDetalle = $pdo->query("
        SELECT r.reposicion_id
        FROM reposicion_materia r
        WHERE UPPER(TRIM(r.reposicion_estado)) = 'REGISTRADO'
          AND NOT EXISTS (
              SELECT 1 FROM reposicion_materia_detalle rd WHERE rd.reposicion_id = r.reposicion_id
          )
    ")->fetchAll();

    qa_check(
        $results,
        'produccion.reposicion.pedido_fk',
        'Reposición: referencia pedido MP válido',
        empty($sinPedido),
        empty($sinPedido) ? 'FK pedido MP OK' : count($sinPedido) . ' reposición(es) huérfana(s)'
    );

    qa_check(
        $results,
        'produccion.reposicion.detalle',
        'Reposición: registros activos tienen detalle',
        empty($sinDetalle),
        empty($sinDetalle) ? 'OK' : count($sinDetalle) . ' sin detalle'
    );
}

function validar_produccion_referenciales(PDO $pdo, array &$results): void
{
    $productosActivos = (int)$pdo->query("
        SELECT COUNT(*) FROM productos WHERE UPPER(TRIM(producto_estado)) = 'ACTIVO'
    ")->fetchColumn();

    $conRuta = (int)$pdo->query("
        SELECT COUNT(DISTINCT ed.producto_id)
        FROM etapa_detalle_produccion ed
        JOIN etapa_produccion ep ON ep.etapa_id = ed.etapa_id
        JOIN productos p ON p.producto_id = ed.producto_id
        WHERE UPPER(TRIM(ep.etapa_estado)) = 'ACTIVA'
          AND UPPER(TRIM(p.producto_estado)) = 'ACTIVO'
    ")->fetchColumn();

    $inspectores = (int)$pdo->query("
        SELECT COUNT(*) FROM inspectores WHERE UPPER(TRIM(inspector_estado)) = 'ACTIVO'
    ")->fetchColumn();

    qa_check(
        $results,
        'produccion.ref.productos',
        'Referenciales: hay productos activos',
        $productosActivos > 0,
        "{$productosActivos} producto(s) activo(s)"
    );

    qa_check(
        $results,
        'produccion.ref.rutas',
        'Referenciales: productos activos con ruta de etapas',
        $conRuta > 0,
        "{$conRuta} producto(s) con ruta"
    );

    qa_check(
        $results,
        'produccion.ref.inspectores',
        'Referenciales: inspectores activos',
        $inspectores > 0,
        "{$inspectores} inspector(es)"
    );
}

function validar_produccion_cadena_documentos(PDO $pdo, array &$results): void
{
    $pedSinOrden = $pdo->query("
        SELECT pp.id_pedido_produccion
        FROM pedido_produccion pp
        WHERE UPPER(TRIM(pp.pedido_prod_estado)) = 'ASIGNADO'
          AND NOT EXISTS (
              SELECT 1 FROM orden_produccion op
              WHERE op.id_pedido_produccion = pp.id_pedido_produccion
                AND UPPER(TRIM(op.orden_prod_estado)) <> 'ANULADA'
          )
    ")->fetchAll();

    $ordenSinPedidoAsignado = $pdo->query("
        SELECT op.orden_id
        FROM orden_produccion op
        JOIN pedido_produccion pp ON pp.id_pedido_produccion = op.id_pedido_produccion
        WHERE UPPER(TRIM(op.orden_prod_estado)) NOT IN ('ANULADA')
          AND UPPER(TRIM(pp.pedido_prod_estado)) NOT IN ('ASIGNADO', 'ANULADO')
    ")->fetchAll();

    qa_check(
        $results,
        'produccion.cadena.pedido_orden',
        'Cadena: pedido ASIGNADO tiene orden activa',
        empty($pedSinOrden),
        empty($pedSinOrden) ? 'OK' : count($pedSinOrden) . ' pedido(s) ASIGNADO sin orden'
    );

    qa_check(
        $results,
        'produccion.cadena.orden_pedido',
        'Cadena: orden activa proviene de pedido ASIGNADO',
        empty($ordenSinPedidoAsignado),
        empty($ordenSinPedidoAsignado) ? 'OK' : count($ordenSinPedidoAsignado) . ' inconsistencia(s)'
    );
}

function validar_produccion_perdidas_integridad(PDO $pdo, array &$results): void
{
    $sinOrigen = $pdo->query("
        SELECT pe.perdidas_id
        FROM perdidas pe
        WHERE UPPER(TRIM(pe.perdida_estado)) = 'REGISTRADO'
          AND pe.control_id IS NULL AND pe.calidad_id IS NULL
    ")->fetchAll();

    $sinDetalle = $pdo->query("
        SELECT pe.perdidas_id
        FROM perdidas pe
        WHERE UPPER(TRIM(pe.perdida_estado)) = 'REGISTRADO'
          AND NOT EXISTS (SELECT 1 FROM perdidas_detalle pd WHERE pd.perdidas_id = pe.perdidas_id)
    ")->fetchAll();

    qa_check(
        $results,
        'produccion.perdidas.origen',
        'Pérdidas: registros activos tienen origen (control o calidad)',
        empty($sinOrigen),
        empty($sinOrigen) ? 'OK' : count($sinOrigen) . ' sin origen'
    );

    qa_check(
        $results,
        'produccion.perdidas.detalle',
        'Pérdidas: registros activos tienen detalle',
        empty($sinDetalle),
        empty($sinDetalle) ? 'OK' : count($sinDetalle) . ' sin detalle'
    );
}

function validar_produccion_equipos_costos_integridad(PDO $pdo, array &$results): void
{
    $equiposSinDet = $pdo->query("
        SELECT e.equipo_id
        FROM equipos_produccion e
        WHERE UPPER(TRIM(e.equipo_estado)) <> 'ANULADO'
          AND NOT EXISTS (SELECT 1 FROM equipo_detalle ed WHERE ed.equipo_id = e.equipo_id)
    ")->fetchAll();

    $costosSinDet = $pdo->query("
        SELECT c.costo_id
        FROM costo_produccion c
        WHERE UPPER(TRIM(c.costo_estado)) <> 'ANULADO'
          AND NOT EXISTS (SELECT 1 FROM costo_detalle_produccion cd WHERE cd.costo_id = c.costo_id)
    ")->fetchAll();

    qa_check(
        $results,
        'produccion.equipos.detalle',
        'Equipos: registros activos tienen integrantes',
        empty($equiposSinDet),
        empty($equiposSinDet) ? 'OK' : count($equiposSinDet) . ' sin detalle'
    );

    qa_check(
        $results,
        'produccion.costos.detalle',
        'Costos: registros activos tienen líneas de costeo',
        empty($costosSinDet),
        empty($costosSinDet) ? 'OK' : count($costosSinDet) . ' sin detalle'
    );
}
