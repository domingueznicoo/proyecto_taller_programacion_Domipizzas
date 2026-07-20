<?php
/**
 * Helpers para pruebas funcionales del módulo Producción.
 */
declare(strict_types=1);

require_once __DIR__ . '/qa_bootstrap.php';

/** Producto y MP por defecto para corridas QA (PAPAS FRITAS + stock existente). */
function qa_prod_producto_default(): int
{
    return 5;
}

function qa_prod_mp_default(): int
{
    return 10;
}

function qa_prod_inspector_default(): int
{
    return 1;
}

function qa_prod_etapas_producto(int $productoId): array
{
    $st = qa_func_pdo()->prepare("
        SELECT ed.etapa_id, ed.etapa_secuencia
        FROM etapa_detalle_produccion ed
        JOIN etapa_produccion ep ON ep.etapa_id = ed.etapa_id
        WHERE ed.producto_id = :p AND UPPER(TRIM(ep.etapa_estado)) = 'ACTIVA'
        ORDER BY ed.etapa_secuencia ASC
    ");
    $st->execute([':p' => $productoId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function qa_prod_consumos_json(int $mpId, int $cantidad): string
{
    return json_encode([['id_materia_prima' => $mpId, 'cantidad' => $cantidad]]);
}

/**
 * Ejecuta control de producción por cada etapa activa del producto.
 */
function qa_prod_ejecutar_controles(int $ordenId, int $productoId, int $cantidad, array $session): bool
{
    $etapas = qa_prod_etapas_producto($productoId);
    $mp = qa_prod_mp_default();
    $inspector = qa_prod_inspector_default();
    $consumoPorUnidad = 1;

    foreach ($etapas as $idx => $et) {
        $etapaId = (int)$et['etapa_id'];
        $payload = [
            'Guardar'             => '1',
            'orden_id'            => (string)$ordenId,
            'producto_id'         => (string)$productoId,
            'etapa_id'            => (string)$etapaId,
            'cantidad_procesada'  => (string)$cantidad,
            'id_inspectores'      => (string)$inspector,
            'control_observacion' => 'QA control etapa ' . $etapaId,
            'consumos'            => '[]',
        ];
        if ($idx === 0) {
            $payload['consumos'] = qa_prod_consumos_json($mp, $cantidad * $consumoPorUnidad);
        }
        qa_func_invoke('modules/produccion/control_produccion/proses.php', ['act' => 'insert'], $payload, $session);
        $ok = (bool)qa_func_scalar("
            SELECT 1 FROM control_produccion
            WHERE orden_id = :o AND producto_id = :p AND etapa_id = :e
              AND UPPER(TRIM(control_estado)) = 'REGISTRADO'
            LIMIT 1
        ", [':o' => $ordenId, ':p' => $productoId, ':e' => $etapaId]);
        if (!$ok) {
            return false;
        }
    }
    return true;
}

function qa_prod_parametros_producto(int $productoId): array
{
    $st = qa_func_pdo()->prepare("
        SELECT parametro_id FROM parametros_control
        WHERE producto_id = :p AND UPPER(TRIM(parametro_estado)) = 'ACTIVO'
        ORDER BY parametro_id
    ");
    $st->execute([':p' => $productoId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function qa_prod_evaluaciones_json(int $productoId, int $cantidad, bool $cumple = true, ?int $rechazada = null): string
{
    $params = [];
    foreach (qa_prod_parametros_producto($productoId) as $paramId) {
        $params[] = [
            'parametro_id' => $paramId,
            'valor_medido' => 'QA',
            'cumple'       => $cumple,
        ];
    }
    $item = [
        'producto_id'            => $productoId,
        'cantidad_inspeccionada' => $cantidad,
        'parametros'             => $params,
    ];
    if ($rechazada !== null) {
        $item['cantidad_rechazada'] = max(0, min($rechazada, $cantidad));
    } elseif (!$cumple) {
        $item['calidad_cantidad'] = $cantidad;
    }
    return json_encode([$item]);
}

function qa_prod_informe_ok(string $movimiento, array $extra = []): array
{
    $desde = (string)qa_func_scalar('SELECT COALESCE(MIN(pedido_prod_fecha_emision)::date, CURRENT_DATE) FROM pedido_produccion');
    $hasta = (string)qa_func_scalar('SELECT COALESCE(MAX(pedido_prod_fecha_emision)::date, CURRENT_DATE) FROM pedido_produccion');
    $params = array_merge([
        'movimiento' => $movimiento,
        'estado'     => 'REPORTE TOTAL',
        'desde'      => $desde,
        'hasta'      => $hasta,
    ], $extra);
    return qa_func_informe_ok('modules/comunes/reportes/generador_produccion.php', $params);
}

function qa_prod_check(array &$results, string $id, string $nombre, bool $ok, string $detalle = ''): void
{
    if (function_exists('qa_check')) {
        qa_check($results, $id, $nombre, $ok, $detalle);
        return;
    }
    if ($ok) {
        $results['resumen']['ok']++;
    } else {
        $results['resumen']['fail']++;
    }
    $results['pruebas'][] = ['id' => $id, 'nombre' => $nombre, 'estado' => $ok ? 'OK' : 'FAIL', 'detalle' => $detalle];
}

function qa_prod_tipo_pedido_id(): int
{
    return (int)qa_func_scalar("
        SELECT id_tipo_pedido FROM tipo_pedido
        WHERE UPPER(TRIM(tipo_pedido_estado)) = 'ACTIVO'
        ORDER BY id_tipo_pedido LIMIT 1
    ");
}

function qa_prod_deposito_id(): int
{
    return (int)qa_func_scalar('SELECT deposito_id FROM deposito ORDER BY deposito_id LIMIT 1');
}

function qa_prod_personal_disponible(bool $excluirInspectores = true): int
{
    $sql = "
        SELECT p.id_personal FROM personal p
        WHERE UPPER(TRIM(p.personal_estado)) = 'ACTIVO'
          AND NOT EXISTS (
              SELECT 1 FROM trabajadores t
              WHERE t.id_personal = p.id_personal AND UPPER(TRIM(t.trabajador_estado)) = 'ACTIVO'
          )
    ";
    if ($excluirInspectores) {
        $sql .= "
          AND NOT EXISTS (
              SELECT 1 FROM inspectores i
              WHERE i.id_personal = p.id_personal AND UPPER(TRIM(i.inspector_estado)) = 'ACTIVO'
          )
        ";
    }
    $sql .= ' ORDER BY p.id_personal LIMIT 1';
    return (int)qa_func_scalar($sql);
}

function qa_prod_primer_etapa_id(int $productoId): int
{
    $etapas = qa_prod_etapas_producto($productoId);
    return $etapas ? (int)$etapas[0]['etapa_id'] : 0;
}

/** @return array{pedido_id:int,orden_id:int} */
function qa_prod_crear_pedido_orden(array $session, int $productoId, int $cantidad): array
{
    $hoy = qa_func_hoy();
    $pedId = qa_func_next_id('pedido_produccion', 'id_pedido_produccion');
    qa_func_invoke('modules/produccion/pedido_produccion/proses.php', ['act' => 'insert'], [
        'Guardar'        => '1',
        'codigo'         => (string)$pedId,
        'id_tipo_pedido' => (string)qa_prod_tipo_pedido_id(),
        'productos'      => json_encode([['codigo' => $productoId, 'cantidad' => $cantidad]]),
        'observaciones'  => 'QA flujo auxiliar',
    ], $session);
    qa_func_invoke('modules/produccion/orden_produccion/proses.php', ['act' => 'insert'], [
        'Guardar'                  => '1',
        'id_pedido_produccion'     => (string)$pedId,
        'orden_prod_fecha_entrega' => date('Y-m-d', strtotime($hoy . ' +7 days')),
    ], $session);
    $ordenId = (int)qa_func_scalar(
        'SELECT orden_id FROM orden_produccion WHERE id_pedido_produccion = :p ORDER BY orden_id DESC LIMIT 1',
        [':p' => $pedId]
    );
    return ['pedido_id' => $pedId, 'orden_id' => $ordenId];
}

function qa_prod_control_etapa(array $session, int $ordenId, int $productoId, int $etapaId, int $cantidad): bool
{
    $esPrimera = $etapaId === qa_prod_primer_etapa_id($productoId);
    $payload = [
        'Guardar'             => '1',
        'orden_id'            => (string)$ordenId,
        'producto_id'         => (string)$productoId,
        'etapa_id'            => (string)$etapaId,
        'cantidad_procesada'  => (string)$cantidad,
        'id_inspectores'      => (string)qa_prod_inspector_default(),
        'control_observacion' => 'QA control',
        'consumos'            => '[]',
    ];
    if ($esPrimera) {
        $payload['consumos'] = qa_prod_consumos_json(qa_prod_mp_default(), $cantidad);
    }
    qa_func_invoke('modules/produccion/control_produccion/proses.php', ['act' => 'insert'], $payload, $session);
    return (bool)qa_func_scalar("
        SELECT 1 FROM control_produccion
        WHERE orden_id = :o AND producto_id = :p AND etapa_id = :e
          AND UPPER(TRIM(control_estado)) = 'REGISTRADO'
        LIMIT 1
    ", [':o' => $ordenId, ':p' => $productoId, ':e' => $etapaId]);
}

function qa_prod_ultimo_control_id(int $ordenId, int $productoId): int
{
    return (int)qa_func_scalar("
        SELECT c.control_id
        FROM control_produccion c
        JOIN etapa_detalle_produccion ed ON ed.etapa_id = c.etapa_id AND ed.producto_id = c.producto_id
        WHERE c.orden_id = :o AND c.producto_id = :p
          AND UPPER(TRIM(c.control_estado)) = 'REGISTRADO'
        ORDER BY ed.etapa_secuencia DESC
        LIMIT 1
    ", [':o' => $ordenId, ':p' => $productoId]);
}

function qa_prod_primer_control_id(int $ordenId, int $productoId): int
{
    return (int)qa_func_scalar("
        SELECT c.control_id
        FROM control_produccion c
        JOIN etapa_detalle_produccion ed ON ed.etapa_id = c.etapa_id AND ed.producto_id = c.producto_id
        WHERE c.orden_id = :o AND c.producto_id = :p
          AND UPPER(TRIM(c.control_estado)) = 'REGISTRADO'
        ORDER BY ed.etapa_secuencia ASC
        LIMIT 1
    ", [':o' => $ordenId, ':p' => $productoId]);
}

function qa_prod_registrar_pt(array $session, int $ordenId, int $productoId, int $cantidad, int $depositoId): int
{
    $hoy = qa_func_hoy();
    qa_func_invoke('modules/produccion/productos_terminados/proses.php', ['act' => 'insert'], [
        'Guardar'         => '1',
        'orden_id'        => (string)$ordenId,
        'terminado_fecha' => $hoy,
        'items'           => json_encode([[
            'producto_id' => $productoId,
            'deposito_id' => $depositoId,
            'cantidad'    => $cantidad,
            'fecha_elab'  => $hoy,
            'fecha_venc'  => date('Y-m-d', strtotime($hoy . ' +30 days')),
        ]]),
    ], $session);
    return (int)qa_func_scalar(
        'SELECT terminado_id FROM producto_terminado WHERE orden_id = :o ORDER BY terminado_id DESC LIMIT 1',
        [':o' => $ordenId]
    );
}

function qa_prod_invoke_json_ok(array $result): bool
{
    if ($result['ok'] ?? false) {
        return true;
    }
    $out = json_decode(trim((string)($result['output'] ?? '')), true);
    return is_array($out) && !empty($out['success']);
}
