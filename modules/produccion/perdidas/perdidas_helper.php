<?php
// Funciones compartidas del módulo pérdidas de producción.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once realpath(__DIR__ . '/../control_produccion/etapas_helper.php');

const TIPO_MERMA_CALIDAD = 1;
const TIPO_PERDIDA_PROCESO = 2;

function bitacoraPerdidas(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void
{
    try {
        $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ")->execute([
            ':id_usuario' => $idUsuario,
            ':entidad' => 'perdidas',
            ':id_registro' => $idRegistro,
            ':accion' => strtoupper($accion),
            ':descripcion' => $descripcion,
        ]);
    } catch (Throwable $e) {
        error_log('Bitácora pérdidas: ' . $e->getMessage());
    }
}

// Obtiene id_usuario desde la sesión (compatibilidad con claves antiguas).
function resolverUsuarioPerdidas(PDO $pdo): int
{
    $usuarioId = (int)($_SESSION['usua_id'] ?? $_SESSION['id_usuario'] ?? 0);
    if ($usuarioId === 0) {
        $q = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1');
        $q->execute([':u' => $_SESSION['username'] ?? '']);
        $usuarioId = (int)$q->fetchColumn();
    }
    return $usuarioId;
}

// Indica si el tipo corresponde a merma por calidad (rechazo en inspección).
function esTipoMermaCalidad(int $tipoId): bool
{
    return $tipoId === TIPO_MERMA_CALIDAD;
}

// Indica si el tipo corresponde a pérdida durante el proceso productivo.
function esTipoPerdidaProceso(int $tipoId): bool
{
    return $tipoId === TIPO_PERDIDA_PROCESO;
}

function clasificarTipoPerdida(PDO $pdo, int $tipoId): ?string
{
    if (esTipoMermaCalidad($tipoId)) {
        return 'calidad';
    }
    if (esTipoPerdidaProceso($tipoId)) {
        return 'proceso';
    }
    $st = $pdo->prepare('SELECT LOWER(tipo_perdida_descri) FROM tipo_perdida WHERE tipo_perdida_id = :id');
    $st->execute([':id' => $tipoId]);
    $desc = (string)$st->fetchColumn();
    if (str_contains($desc, 'calidad') || str_contains($desc, 'merma')) {
        return 'calidad';
    }
    if (str_contains($desc, 'proceso')) {
        return 'proceso';
    }
    return null;
}

// Normaliza y valida las líneas del detalle antes de guardar.
function normalizarLineasPerdida(array $items): array
{
    $lineas = [];
    foreach ($items as $item) {
        $productoId = (int)($item['producto_id'] ?? 0);
        $cantidad = (int)($item['cantidad'] ?? $item['perdida_cantidad'] ?? 0);
        $motivo = trim((string)($item['motivo'] ?? $item['perdida_motivo'] ?? ''));
        $depositoId = (int)($item['deposito_id'] ?? 0);
        if ($productoId <= 0 || $cantidad <= 0 || $motivo === '') {
            continue;
        }
        if (strlen($motivo) > 30) {
            $motivo = substr($motivo, 0, 30);
        }
        if (!isset($lineas[$productoId])) {
            $lineas[$productoId] = [
                'producto_id' => $productoId,
                'cantidad' => 0,
                'motivo' => $motivo,
                'deposito_id' => $depositoId,
            ];
        }
        $lineas[$productoId]['cantidad'] += $cantidad;
        $lineas[$productoId]['motivo'] = $motivo;
        if ($depositoId > 0) {
            $lineas[$productoId]['deposito_id'] = $depositoId;
        }
    }
    return array_values($lineas);
}

function cantidadPerdidaRegistrada(PDO $pdo, string $origen, int $origenId, int $productoId, ?int $excluirPerdidasId = null): int
{
    if ($origen === 'calidad') {
        $sql = "
            SELECT COALESCE(SUM(pd.perdida_cantidad), 0)::int
            FROM perdidas pe
            JOIN perdidas_detalle pd ON pd.perdidas_id = pe.perdidas_id
            WHERE pe.calidad_id = :oid AND pd.producto_id = :p
              AND UPPER(TRIM(pe.perdida_estado)) = 'REGISTRADO'
        ";
    } else {
        $sql = "
            SELECT COALESCE(SUM(pd.perdida_cantidad), 0)::int
            FROM perdidas pe
            JOIN perdidas_detalle pd ON pd.perdidas_id = pe.perdidas_id
            WHERE pe.control_id = :oid AND pd.producto_id = :p
              AND UPPER(TRIM(pe.perdida_estado)) = 'REGISTRADO'
        ";
    }
    if ($excluirPerdidasId !== null && $excluirPerdidasId > 0) {
        $sql .= ' AND pe.perdidas_id <> :excl';
    }
    $st = $pdo->prepare($sql);
    $params = [':oid' => $origenId, ':p' => $productoId];
    if ($excluirPerdidasId !== null && $excluirPerdidasId > 0) {
        $params[':excl'] = $excluirPerdidasId;
    }
    $st->execute($params);
    return (int)$st->fetchColumn();
}

function cantidadRechazadaCalidad(PDO $pdo, int $calidadId, int $productoId): int
{
    $st = $pdo->prepare("
        SELECT COALESCE(MAX(cd.calidad_cantidad), 0)::int
        FROM control_calidad_detalle cd
        WHERE cd.calidad_id = :c AND cd.producto_id = :p
          AND UPPER(TRIM(cd.calidad_estado)) = 'NO CONFORME'
    ");
    $st->execute([':c' => $calidadId, ':p' => $productoId]);
    return (int)$st->fetchColumn();
}

function cantidadProcesadaControl(PDO $pdo, int $controlId, int $productoId): int
{
    $st = $pdo->prepare("
        SELECT COALESCE(SUM(cd.control_cantidad), 0)::int
        FROM control_produccion_detalle cd
        WHERE cd.control_id = :c AND cd.producto_id = :p
    ");
    $st->execute([':c' => $controlId, ':p' => $productoId]);
    return (int)$st->fetchColumn();
}

function controlEsUltimaEtapa(PDO $pdo, int $controlId): bool
{
    $st = $pdo->prepare("
        SELECT c.orden_id, c.producto_id, c.etapa_id
        FROM control_produccion c
        WHERE c.control_id = :id
    ");
    $st->execute([':id' => $controlId]);
    $row = $st->fetch();
    if (!$row) {
        return false;
    }
    $ruta = obtenerRutaEtapas($pdo, (int)$row['producto_id']);
    return esEtapaUltima($ruta, (int)$row['etapa_id']);
}

function actualizarEstadoOrdenPerdidas(PDO $pdo, int $ordenId): void
{
    $st = $pdo->prepare('SELECT orden_prod_estado FROM orden_produccion WHERE orden_id = :id');
    $st->execute([':id' => $ordenId]);
    $estado = strtoupper(trim((string)$st->fetchColumn()));

    $pend = $pdo->prepare('SELECT COALESCE(SUM(cantidad_pendiente), 0) FROM orden_detalle_produccion WHERE orden_id = :id');
    $pend->execute([':id' => $ordenId]);
    $totalPend = (int)$pend->fetchColumn();

    if ($totalPend <= 0) {
        $pdo->prepare("UPDATE orden_produccion SET orden_prod_estado = 'TERMINADA' WHERE orden_id = :id")
            ->execute([':id' => $ordenId]);
    } elseif (in_array($estado, ['PENDIENTE', 'TERMINADA'], true)) {
        $pdo->prepare("UPDATE orden_produccion SET orden_prod_estado = 'EN_PROCESO' WHERE orden_id = :id")
            ->execute([':id' => $ordenId]);
    }
}

function descontarStock(PDO $pdo, int $productoId, int $depositoId, int $cantidad): bool
{
    if ($depositoId <= 0 || $cantidad <= 0) {
        return false;
    }
    $st = $pdo->prepare("
        UPDATE stock_producto
        SET stock_prod_existente = GREATEST(0, stock_prod_existente - :cant)
        WHERE producto_id = :p AND deposito_id = :d AND stock_prod_existente >= :cant
    ");
    $st->execute([':cant' => $cantidad, ':p' => $productoId, ':d' => $depositoId]);
    return $st->rowCount() > 0;
}

function reponerStock(PDO $pdo, int $productoId, int $depositoId, int $cantidad): void
{
    if ($cantidad <= 0 || $depositoId <= 0) {
        return;
    }
    $st = $pdo->prepare("
        SELECT id_stock_productos FROM stock_producto
        WHERE producto_id = :p AND deposito_id = :d
    ");
    $st->execute([':p' => $productoId, ':d' => $depositoId]);
    if ($st->fetchColumn()) {
        $pdo->prepare("
            UPDATE stock_producto
            SET stock_prod_existente = stock_prod_existente + :cant
            WHERE producto_id = :p AND deposito_id = :d
        ")->execute([':cant' => $cantidad, ':p' => $productoId, ':d' => $depositoId]);
    }
}

function resolverDepositoCalidad(PDO $pdo, int $terminadoId, int $productoId): int
{
    $st = $pdo->prepare("
        SELECT deposito_id FROM productos_terminados_detalle
        WHERE terminado_id = :t AND producto_id = :p
    ");
    $st->execute([':t' => $terminadoId, ':p' => $productoId]);
    return (int)$st->fetchColumn();
}

function aplicarEfectoProceso(PDO $pdo, int $controlId, int $productoId, int $cantidad): void
{
    $st = $pdo->prepare("
        SELECT orden_id, producto_id FROM control_produccion WHERE control_id = :id
    ");
    $st->execute([':id' => $controlId]);
    $ctrl = $st->fetch();
    if (!$ctrl || $cantidad <= 0) {
        return;
    }
    if (!controlEsUltimaEtapa($pdo, $controlId)) {
        return;
    }
    $pdo->prepare("
        UPDATE orden_detalle_produccion
        SET cantidad_pendiente = LEAST(
            orden_prod_cantidad,
            COALESCE(cantidad_pendiente, 0) + :cant
        )
        WHERE orden_id = :o AND producto_id = :p
    ")->execute([
        ':cant' => $cantidad,
        ':o' => (int)$ctrl['orden_id'],
        ':p' => $productoId,
    ]);
    actualizarEstadoOrdenPerdidas($pdo, (int)$ctrl['orden_id']);
}

function revertirEfectoProceso(PDO $pdo, int $controlId, int $productoId, int $cantidad): void
{
    $st = $pdo->prepare("SELECT orden_id FROM control_produccion WHERE control_id = :id");
    $st->execute([':id' => $controlId]);
    $ordenId = (int)$st->fetchColumn();
    if ($ordenId <= 0 || $cantidad <= 0 || !controlEsUltimaEtapa($pdo, $controlId)) {
        return;
    }
    $pdo->prepare("
        UPDATE orden_detalle_produccion
        SET cantidad_pendiente = GREATEST(0, COALESCE(cantidad_pendiente, 0) - :cant)
        WHERE orden_id = :o AND producto_id = :p
    ")->execute([':cant' => $cantidad, ':o' => $ordenId, ':p' => $productoId]);
    actualizarEstadoOrdenPerdidas($pdo, $ordenId);
}

function aplicarEfectoCalidad(PDO $pdo, int $terminadoId, int $productoId, int $depositoId, int $cantidad): bool
{
    if ($depositoId <= 0) {
        $depositoId = resolverDepositoCalidad($pdo, $terminadoId, $productoId);
    }
    return descontarStock($pdo, $productoId, $depositoId, $cantidad);
}

function revertirEfectoCalidad(PDO $pdo, int $terminadoId, int $productoId, int $depositoId, int $cantidad): void
{
    if ($depositoId <= 0) {
        $depositoId = resolverDepositoCalidad($pdo, $terminadoId, $productoId);
    }
    reponerStock($pdo, $productoId, $depositoId, $cantidad);
}

function validarOrigenCalidad(PDO $pdo, int $calidadId, ?int $excluirPerdidasId = null): array
{
    $st = $pdo->prepare("
        SELECT calidad_estado, terminado_id
        FROM control_calidad_produccion WHERE calidad_id = :id
    ");
    $st->execute([':id' => $calidadId]);
    $cal = $st->fetch();
    if (!$cal) {
        return ['ok' => false, 'error' => 'Control de calidad no encontrado.'];
    }
    if (strtoupper(trim((string)$cal['calidad_estado'])) !== 'NO CONFORME') {
        return ['ok' => false, 'error' => 'Solo inspecciones NO CONFORME admiten merma.'];
    }
    $stDup = $pdo->prepare("
        SELECT perdidas_id FROM perdidas
        WHERE calidad_id = :id AND UPPER(TRIM(perdida_estado)) = 'REGISTRADO'
    ");
    $stDup->execute([':id' => $calidadId]);
    $dup = $stDup->fetchColumn();
    if ($dup && ($excluirPerdidasId === null || (int)$dup !== $excluirPerdidasId)) {
        return ['ok' => false, 'error' => "Ya existe pérdida #{$dup} para este control de calidad."];
    }
    return ['ok' => true, 'terminado_id' => (int)$cal['terminado_id']];
}

function validarOrigenProceso(PDO $pdo, int $controlId, ?int $excluirPerdidasId = null): array
{
    $st = $pdo->prepare("
        SELECT c.control_estado, c.orden_id, c.producto_id, op.orden_prod_estado
        FROM control_produccion c
        JOIN orden_produccion op ON op.orden_id = c.orden_id
        WHERE c.control_id = :id
    ");
    $st->execute([':id' => $controlId]);
    $ctrl = $st->fetch();
    if (!$ctrl) {
        return ['ok' => false, 'error' => 'Control de producción no encontrado.'];
    }
    if (strtoupper(trim((string)$ctrl['control_estado'])) !== 'REGISTRADO') {
        return ['ok' => false, 'error' => 'El control de producción no está activo.'];
    }
    $estOrden = strtoupper(trim((string)$ctrl['orden_prod_estado']));
    if (in_array($estOrden, ['ANULADA', 'ANULADO'], true)) {
        return ['ok' => false, 'error' => 'La orden de producción está anulada.'];
    }
    $stPt = $pdo->prepare('SELECT COUNT(*) FROM producto_terminado WHERE orden_id = :o');
    $stPt->execute([':o' => $ctrl['orden_id']]);
    if ((int)$stPt->fetchColumn() > 0) {
        return ['ok' => false, 'error' => 'La OP ya tiene productos terminados; no se registran pérdidas en proceso.'];
    }
    $stDup = $pdo->prepare("
        SELECT perdidas_id FROM perdidas
        WHERE control_id = :id AND UPPER(TRIM(perdida_estado)) = 'REGISTRADO'
    ");
    $stDup->execute([':id' => $controlId]);
    $dup = $stDup->fetchColumn();
    if ($dup && ($excluirPerdidasId === null || (int)$dup !== $excluirPerdidasId)) {
        return ['ok' => false, 'error' => "Ya existe pérdida #{$dup} para este control de producción."];
    }
    return [
        'ok' => true,
        'orden_id' => (int)$ctrl['orden_id'],
        'producto_id' => (int)$ctrl['producto_id'],
    ];
}

function validarLineasCalidad(PDO $pdo, int $calidadId, array $lineas, ?int $excluirPerdidasId = null): array
{
    $val = validarOrigenCalidad($pdo, $calidadId, $excluirPerdidasId);
    if (!$val['ok']) {
        return $val;
    }
    $terminadoId = (int)$val['terminado_id'];
    foreach ($lineas as &$ln) {
        $pid = (int)$ln['producto_id'];
        $cant = (int)$ln['cantidad'];
        $rechazado = cantidadRechazadaCalidad($pdo, $calidadId, $pid);
        if ($rechazado <= 0) {
            return ['ok' => false, 'error' => "El producto #{$pid} no está rechazado en este control."];
        }
        $yaReg = cantidadPerdidaRegistrada($pdo, 'calidad', $calidadId, $pid, $excluirPerdidasId);
        $max = $rechazado - $yaReg;
        if ($cant > $max) {
            return ['ok' => false, 'error' => "Cantidad supera rechazado disponible ({$max}) para producto #{$pid}."];
        }
        $dep = (int)($ln['deposito_id'] ?? 0);
        if ($dep <= 0) {
            $dep = resolverDepositoCalidad($pdo, $terminadoId, $pid);
        }
        if ($dep <= 0) {
            return ['ok' => false, 'error' => "Sin depósito definido para producto #{$pid} en el lote PT."];
        }
        $ln['deposito_id'] = $dep;
    }
    unset($ln);
    return ['ok' => true, 'terminado_id' => $terminadoId];
}

function validarLineasProceso(PDO $pdo, int $controlId, array $lineas, ?int $excluirPerdidasId = null): array
{
    $val = validarOrigenProceso($pdo, $controlId, $excluirPerdidasId);
    if (!$val['ok']) {
        return $val;
    }
    foreach ($lineas as $ln) {
        $pid = (int)$ln['producto_id'];
        $cant = (int)$ln['cantidad'];
        $procesado = cantidadProcesadaControl($pdo, $controlId, $pid);
        if ($procesado <= 0) {
            return ['ok' => false, 'error' => "El producto #{$pid} no pertenece al control."];
        }
        $yaReg = cantidadPerdidaRegistrada($pdo, 'proceso', $controlId, $pid, $excluirPerdidasId);
        $max = $procesado - $yaReg;
        if ($cant > $max) {
            return ['ok' => false, 'error' => "Cantidad supera pendiente de pérdida ({$max}) en el control."];
        }
    }
    return ['ok' => true, 'orden_id' => (int)$val['orden_id']];
}

function puedeAnularPerdida(PDO $pdo, int $perdidasId): array
{
    $st = $pdo->prepare("
        SELECT pe.perdida_estado, pe.calidad_id, pe.control_id
        FROM perdidas pe WHERE pe.perdidas_id = :id
    ");
    $st->execute([':id' => $perdidasId]);
    $row = $st->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'Registro no encontrado.'];
    }
    if (strtoupper(trim((string)$row['perdida_estado'])) !== 'REGISTRADO') {
        return ['ok' => false, 'error' => 'Solo se anulan pérdidas en estado REGISTRADO.'];
    }
    if (!empty($row['calidad_id'])) {
        $stCc = $pdo->prepare("SELECT calidad_estado FROM control_calidad_produccion WHERE calidad_id = :id");
        $stCc->execute([':id' => $row['calidad_id']]);
        $est = strtoupper(trim((string)$stCc->fetchColumn()));
        if ($est === 'ANULADO') {
            return ['ok' => false, 'error' => 'El control de calidad origen está anulado.'];
        }
    }
    if (!empty($row['control_id'])) {
        $val = validarOrigenProceso($pdo, (int)$row['control_id'], $perdidasId);
        if (!$val['ok']) {
            return $val;
        }
    }
    return ['ok' => true, 'cabecera' => $row];
}

function revertirEfectosPerdida(PDO $pdo, int $perdidasId): void
{
    $st = $pdo->prepare("
        SELECT pe.calidad_id, pe.control_id, cc.terminado_id
        FROM perdidas pe
        LEFT JOIN control_calidad_produccion cc ON cc.calidad_id = pe.calidad_id
        WHERE pe.perdidas_id = :id
    ");
    $st->execute([':id' => $perdidasId]);
    $cab = $st->fetch();
    if (!$cab) {
        return;
    }

    $lineas = $pdo->prepare("
        SELECT pd.producto_id, pd.perdida_cantidad
        FROM perdidas_detalle pd WHERE pd.perdidas_id = :id
    ");
    $lineas->execute([':id' => $perdidasId]);

    foreach ($lineas->fetchAll() as $ln) {
        $pid = (int)$ln['producto_id'];
        $cant = (int)$ln['perdida_cantidad'];
        if (!empty($cab['calidad_id'])) {
            $dep = resolverDepositoCalidad($pdo, (int)$cab['terminado_id'], $pid);
            revertirEfectoCalidad($pdo, (int)$cab['terminado_id'], $pid, $dep, $cant);
        } elseif (!empty($cab['control_id'])) {
            revertirEfectoProceso($pdo, (int)$cab['control_id'], $pid, $cant);
        }
    }
}

function aplicarEfectosPerdida(PDO $pdo, array $cabecera, array $lineas): ?string
{
    if (!empty($cabecera['calidad_id'])) {
        $st = $pdo->prepare('SELECT terminado_id FROM control_calidad_produccion WHERE calidad_id = :id');
        $st->execute([':id' => $cabecera['calidad_id']]);
        $terminadoId = (int)$st->fetchColumn();
        foreach ($lineas as $ln) {
            $dep = (int)($ln['deposito_id'] ?? 0);
            if (!aplicarEfectoCalidad($pdo, $terminadoId, (int)$ln['producto_id'], $dep, (int)$ln['cantidad'])) {
                return "Stock insuficiente para producto #{$ln['producto_id']}.";
            }
        }
    } elseif (!empty($cabecera['control_id'])) {
        foreach ($lineas as $ln) {
            aplicarEfectoProceso($pdo, (int)$cabecera['control_id'], (int)$ln['producto_id'], (int)$ln['cantidad']);
        }
    }
    return null;
}

function obtenerLineasDetallePerdida(PDO $pdo, int $perdidasId): array
{
    $st = $pdo->prepare("
        SELECT pd.producto_id, pd.perdida_cantidad AS cantidad, pd.perdida_motivo AS motivo,
               p.producto_descri
        FROM perdidas_detalle pd
        JOIN productos p ON p.producto_id = pd.producto_id
        WHERE pd.perdidas_id = :id
        ORDER BY p.producto_descri
    ");
    $st->execute([':id' => $perdidasId]);
    return $st->fetchAll();
}
