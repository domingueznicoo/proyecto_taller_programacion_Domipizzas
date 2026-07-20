<?php
// Funciones compartidas del módulo reposición de materia prima.
require_once __DIR__ . '/../../../config/bootstrap.php';
/**
 * Funciones compartidas — reposición de materia prima.
 */

function bitacoraRep(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void
{
    try {
        $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ")->execute([
            ':id_usuario' => $idUsuario,
            ':entidad' => 'reposicion materia prima',
            ':id_registro' => $idRegistro,
            ':accion' => strtoupper($accion),
            ':descripcion' => $descripcion,
        ]);
    } catch (Throwable $e) {
        error_log('Bitácora reposición: ' . $e->getMessage());
    }
}

function resolverUsuarioRep(PDO $pdo): int
{
    $usuarioId = (int)($_SESSION['usua_id'] ?? $_SESSION['id_usuario'] ?? 0);
    if ($usuarioId === 0) {
        $q = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1');
        $q->execute([':u' => $_SESSION['username'] ?? '']);
        $usuarioId = (int)$q->fetchColumn();
    }
    return $usuarioId;
}

function normalizarLineasRep(array $items): array
{
    $lineas = [];
    foreach ($items as $item) {
        $mpId = (int)($item['id_materia_prima'] ?? $item['codigo'] ?? 0);
        $cant = (int)($item['cantidad'] ?? $item['reposicion_cantidad'] ?? 0);
        if ($mpId <= 0 || $cant <= 0) {
            continue;
        }
        $lineas[$mpId] = ($lineas[$mpId] ?? 0) + $cant;
    }
    return $lineas;
}

function obtenerLineasReposicion(PDO $pdo, int $reposicionId): array
{
    $st = $pdo->prepare("
        SELECT rd.id_materia_prima, rd.reposicion_cantidad AS cantidad, mp.materia_prima_descripcion
        FROM reposicion_materia_detalle rd
        JOIN materia_prima mp ON mp.id_materia_prima = rd.id_materia_prima
        WHERE rd.reposicion_id = :id
        ORDER BY mp.materia_prima_descripcion
    ");
    $st->execute([':id' => $reposicionId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function stockMpEnDeposito(PDO $pdo, int $mpId, int $depositoId): int
{
    $st = $pdo->prepare("
        SELECT COALESCE(cantidad_existente, 0)::int
        FROM stock_materia_prima
        WHERE id_materia_prima = :mp AND deposito_id = :d
    ");
    $st->execute([':mp' => $mpId, ':d' => $depositoId]);
    return (int)$st->fetchColumn();
}

function incrementarStockMp(PDO $pdo, int $mpId, int $depositoId, int $cantidad, int $usuarioId): void
{
    $st = $pdo->prepare("
        SELECT id_stock FROM stock_materia_prima
        WHERE id_materia_prima = :mp AND deposito_id = :d
        FOR UPDATE
    ");
    $st->execute([':mp' => $mpId, ':d' => $depositoId]);
    $idStock = $st->fetchColumn();

    if ($idStock) {
        $pdo->prepare("
            UPDATE stock_materia_prima
            SET cantidad_existente = cantidad_existente + :cant
            WHERE id_materia_prima = :mp AND deposito_id = :d
        ")->execute([':cant' => $cantidad, ':mp' => $mpId, ':d' => $depositoId]);
    } else {
        $pdo->prepare("
            INSERT INTO stock_materia_prima (
                id_materia_prima, deposito_id, cantidad_existente,
                stock_cantidad_minima, stock_cantidad_maxima, id_usuario
            ) VALUES (:mp, :d, :cant, 0, 0, :u)
        ")->execute([':mp' => $mpId, ':d' => $depositoId, ':cant' => $cantidad, ':u' => $usuarioId]);
    }
}

function decrementarStockMp(PDO $pdo, int $mpId, int $depositoId, int $cantidad): bool
{
    if ($cantidad <= 0) {
        return true;
    }
    $st = $pdo->prepare("
        UPDATE stock_materia_prima
        SET cantidad_existente = cantidad_existente - :cant
        WHERE id_materia_prima = :mp AND deposito_id = :d AND cantidad_existente >= :cant
    ");
    $st->execute([':cant' => $cantidad, ':mp' => $mpId, ':d' => $depositoId]);
    return $st->rowCount() > 0;
}

function actualizarEstadoPedido(PDO $pdo, int $pedidoId): void
{
    $st = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            COUNT(*) FILTER (WHERE cantidad_repuesta >= ped_mat_prod_cantidad) AS completas
        FROM pedido_materia_detalle_produccion
        WHERE id_pedido_mat_prod = :id
    ");
    $st->execute([':id' => $pedidoId]);
    $row = $st->fetch();
    $total = (int)($row['total'] ?? 0);
    $completas = (int)($row['completas'] ?? 0);

    $nuevoEstado = 'PENDIENTE';
    if ($total > 0 && $completas >= $total) {
        $nuevoEstado = 'COMPLETADO';
    } else {
        $stRep = $pdo->prepare("
            SELECT COALESCE(SUM(cantidad_repuesta), 0)::int
            FROM pedido_materia_detalle_produccion WHERE id_pedido_mat_prod = :id
        ");
        $stRep->execute([':id' => $pedidoId]);
        if ((int)$stRep->fetchColumn() > 0) {
            $nuevoEstado = 'PARCIAL';
        }
    }

    $pdo->prepare("
        UPDATE pedido_materia_produccion SET ped_mat_prod_estado = :e
        WHERE id_pedido_mat_prod = :id AND UPPER(TRIM(ped_mat_prod_estado)) <> 'ANULADO'
    ")->execute([':e' => $nuevoEstado, ':id' => $pedidoId]);
}

function validarPedidoReposicion(PDO $pdo, int $pedidoId, ?int $excluirReposicionId = null): array
{
    $st = $pdo->prepare("
        SELECT deposito_id, ped_mat_prod_estado
        FROM pedido_materia_produccion
        WHERE id_pedido_mat_prod = :id
    ");
    $st->execute([':id' => $pedidoId]);
    $ped = $st->fetch();
    if (!$ped) {
        return ['ok' => false, 'error' => 'Pedido no encontrado.'];
    }
    $est = strtoupper(trim((string)$ped['ped_mat_prod_estado']));
    if ($est === 'ANULADO') {
        return ['ok' => false, 'error' => 'El pedido está anulado.'];
    }
    if ($excluirReposicionId === null && !in_array($est, ['PENDIENTE', 'PARCIAL'], true)) {
        return ['ok' => false, 'error' => 'El pedido no admite reposición en su estado actual.'];
    }
    return ['ok' => true, 'deposito_id' => (int)$ped['deposito_id']];
}

function pendientePedidoMp(PDO $pdo, int $pedidoId, int $mpId, ?int $excluirReposicionId = null): int
{
    $st = $pdo->prepare("
        SELECT ped_mat_prod_cantidad, cantidad_repuesta
        FROM pedido_materia_detalle_produccion
        WHERE id_pedido_mat_prod = :p AND id_materia_prima = :mp
    ");
    $st->execute([':p' => $pedidoId, ':mp' => $mpId]);
    $det = $st->fetch();
    if (!$det) {
        return 0;
    }
    $pend = (int)$det['ped_mat_prod_cantidad'] - (int)$det['cantidad_repuesta'];
    if ($excluirReposicionId !== null && $excluirReposicionId > 0) {
        $stRep = $pdo->prepare("
            SELECT COALESCE(reposicion_cantidad, 0)::int
            FROM reposicion_materia_detalle
            WHERE reposicion_id = :r AND id_materia_prima = :mp
        ");
        $stRep->execute([':r' => $excluirReposicionId, ':mp' => $mpId]);
        $pend += (int)$stRep->fetchColumn();
    }
    return max(0, $pend);
}

function validarLineasReposicion(PDO $pdo, int $pedidoId, array $lineas, ?int $excluirReposicionId = null): array
{
    $val = validarPedidoReposicion($pdo, $pedidoId, $excluirReposicionId);
    if (!$val['ok']) {
        return $val;
    }
    foreach ($lineas as $mpId => $cant) {
        $stDet = $pdo->prepare("
            SELECT 1 FROM pedido_materia_detalle_produccion
            WHERE id_pedido_mat_prod = :p AND id_materia_prima = :mp
        ");
        $stDet->execute([':p' => $pedidoId, ':mp' => $mpId]);
        if (!$stDet->fetchColumn()) {
            return ['ok' => false, 'error' => "Materia prima #{$mpId} no pertenece al pedido."];
        }
        $pend = pendientePedidoMp($pdo, $pedidoId, $mpId, $excluirReposicionId);
        if ($cant > $pend) {
            return ['ok' => false, 'error' => "Cantidad supera lo pendiente ({$pend}) para MP #{$mpId}."];
        }
    }
    return ['ok' => true, 'deposito_id' => (int)$val['deposito_id']];
}

function validarStockParaReduccion(PDO $pdo, int $depositoId, array $cantidadesAnteriores, array $cantidadesNuevas): ?string
{
    $todosMp = array_unique(array_merge(array_keys($cantidadesAnteriores), array_keys($cantidadesNuevas)));
    foreach ($todosMp as $mpId) {
        $old = (int)($cantidadesAnteriores[$mpId] ?? 0);
        $new = (int)($cantidadesNuevas[$mpId] ?? 0);
        $reduccion = $old - $new;
        if ($reduccion <= 0) {
            continue;
        }
        $stock = stockMpEnDeposito($pdo, $mpId, $depositoId);
        if ($stock < $reduccion) {
            return "Stock insuficiente para revertir MP #{$mpId} (disponible: {$stock}, requerido: {$reduccion}). "
                . 'Puede haber consumos posteriores en Control de Producción.';
        }
    }
    return null;
}

function revertirEfectosReposicion(PDO $pdo, int $reposicionId): void
{
    $st = $pdo->prepare("
        SELECT deposito_id, id_pedido_mat_prod
        FROM reposicion_materia WHERE reposicion_id = :id
    ");
    $st->execute([':id' => $reposicionId]);
    $cab = $st->fetch();
    if (!$cab) {
        return;
    }
    $pedidoId = (int)$cab['id_pedido_mat_prod'];
    $depositoId = (int)$cab['deposito_id'];

    $lineas = $pdo->prepare("
        SELECT id_materia_prima, reposicion_cantidad
        FROM reposicion_materia_detalle WHERE reposicion_id = :id
    ");
    $lineas->execute([':id' => $reposicionId]);

    foreach ($lineas->fetchAll() as $row) {
        $mpId = (int)$row['id_materia_prima'];
        $cant = (int)$row['reposicion_cantidad'];
        decrementarStockMp($pdo, $mpId, $depositoId, $cant);
        $pdo->prepare("
            UPDATE pedido_materia_detalle_produccion
            SET cantidad_repuesta = GREATEST(0, cantidad_repuesta - :cant)
            WHERE id_pedido_mat_prod = :p AND id_materia_prima = :mp
        ")->execute([':cant' => $cant, ':p' => $pedidoId, ':mp' => $mpId]);
    }
    if ($pedidoId > 0) {
        actualizarEstadoPedido($pdo, $pedidoId);
    }
}

function aplicarEfectosReposicion(PDO $pdo, int $pedidoId, int $depositoId, array $lineas, int $usuarioId): void
{
    $updDet = $pdo->prepare("
        UPDATE pedido_materia_detalle_produccion
        SET cantidad_repuesta = cantidad_repuesta + :cant
        WHERE id_pedido_mat_prod = :p AND id_materia_prima = :mp
    ");
    foreach ($lineas as $mpId => $cant) {
        incrementarStockMp($pdo, $mpId, $depositoId, $cant, $usuarioId);
        $updDet->execute([':cant' => $cant, ':p' => $pedidoId, ':mp' => $mpId]);
    }
    actualizarEstadoPedido($pdo, $pedidoId);
}

function puedeAnularReposicion(PDO $pdo, int $reposicionId): array
{
    $st = $pdo->prepare("
        SELECT reposicion_estado, deposito_id, id_pedido_mat_prod
        FROM reposicion_materia WHERE reposicion_id = :id
    ");
    $st->execute([':id' => $reposicionId]);
    $cab = $st->fetch();
    if (!$cab) {
        return ['ok' => false, 'error' => 'Reposición no encontrada.'];
    }
    if (strtoupper(trim((string)$cab['reposicion_estado'])) !== 'REGISTRADO') {
        return ['ok' => false, 'error' => 'Solo se anulan reposiciones en estado REGISTRADO.'];
    }

    $valPed = validarPedidoReposicion($pdo, (int)$cab['id_pedido_mat_prod'], $reposicionId);
    if (!$valPed['ok']) {
        return $valPed;
    }

    $depositoId = (int)$cab['deposito_id'];
    $lineas = obtenerLineasReposicion($pdo, $reposicionId);
    $map = [];
    foreach ($lineas as $ln) {
        $map[(int)$ln['id_materia_prima']] = (int)$ln['cantidad'];
    }
    $err = validarStockParaReduccion($pdo, $depositoId, $map, []);
    if ($err !== null) {
        return ['ok' => false, 'error' => $err];
    }

    return ['ok' => true, 'cabecera' => $cab];
}
