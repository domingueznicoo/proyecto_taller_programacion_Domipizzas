<?php
// Funciones compartidas del módulo pedidos de materia prima.
require_once __DIR__ . '/../../../config/bootstrap.php';
/**
 * Funciones compartidas — pedido de materia prima.
 */

function bitacoraPedMp(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void
{
    try {
        $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ")->execute([
            ':id_usuario' => $idUsuario,
            ':entidad' => 'pedido materia prima',
            ':id_registro' => $idRegistro,
            ':accion' => strtoupper($accion),
            ':descripcion' => $descripcion,
        ]);
    } catch (Throwable $e) {
        error_log('Bitácora pedido MP: ' . $e->getMessage());
    }
}

function resolverUsuarioSucursalPed(PDO $pdo): array
{
    $usuarioId = (int)($_SESSION['usua_id'] ?? $_SESSION['id_usuario'] ?? 0);
    $sucursalId = (int)($_SESSION['sucursal_id'] ?? $_SESSION['id_sucursal'] ?? 0);

    if ($usuarioId > 0 && $sucursalId === 0) {
        $q = $pdo->prepare('SELECT id_sucursal FROM usuarios WHERE id_usuario = :id LIMIT 1');
        $q->execute([':id' => $usuarioId]);
        $sucursalId = (int)$q->fetchColumn();
    }

    if ($usuarioId === 0) {
        $q = $pdo->prepare('SELECT id_usuario, id_sucursal FROM usuarios WHERE username = :u LIMIT 1');
        $q->execute([':u' => $_SESSION['username'] ?? '']);
        $usr = $q->fetch(PDO::FETCH_ASSOC);
        if ($usr) {
            $usuarioId = (int)$usr['id_usuario'];
            $sucursalId = (int)$usr['id_sucursal'];
        }
    }

    return [$usuarioId, $sucursalId];
}

function normalizarDetallePedMp(array $items): array
{
    $lineas = [];
    foreach ($items as $item) {
        $mpId = (int)($item['id_materia_prima'] ?? $item['codigo'] ?? 0);
        $cant = (int)($item['cantidad'] ?? $item['ped_mat_prod_cantidad'] ?? 0);
        if ($mpId <= 0 || $cant <= 0) {
            continue;
        }
        $lineas[$mpId] = ($lineas[$mpId] ?? 0) + $cant;
    }
    return $lineas;
}

function validarMateriasActivas(PDO $pdo, array $lineas): ?string
{
    if (empty($lineas)) {
        return 'Agregue al menos una materia prima con cantidad mayor a cero.';
    }
    $ids = array_keys($lineas);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("
        SELECT id_materia_prima, materia_prima_descripcion, materia_prima_estado
        FROM materia_prima WHERE id_materia_prima IN ($ph)
    ");
    $st->execute($ids);
    $rows = $st->fetchAll();
    if (count($rows) !== count($ids)) {
        return 'Una o más materias primas no existen.';
    }
    foreach ($rows as $row) {
        if (strtoupper(trim((string)$row['materia_prima_estado'])) !== 'ACTIVO') {
            return 'La materia prima "' . $row['materia_prima_descripcion'] . '" no está activa.';
        }
    }
    return null;
}

function pedidoTieneReposiciones(PDO $pdo, int $pedidoId): bool
{
    $stRep = $pdo->prepare("
        SELECT COUNT(*) FROM reposicion_materia
        WHERE id_pedido_mat_prod = :id AND UPPER(TRIM(reposicion_estado)) = 'REGISTRADO'
    ");
    $stRep->execute([':id' => $pedidoId]);
    if ((int)$stRep->fetchColumn() > 0) {
        return true;
    }
    $stDet = $pdo->prepare("
        SELECT COALESCE(SUM(cantidad_repuesta), 0)::int
        FROM pedido_materia_detalle_produccion WHERE id_pedido_mat_prod = :id
    ");
    $stDet->execute([':id' => $pedidoId]);
    return (int)$stDet->fetchColumn() > 0;
}

function pedidoEditable(PDO $pdo, int $pedidoId): array
{
    $st = $pdo->prepare("
        SELECT ped_mat_prod_estado FROM pedido_materia_produccion
        WHERE id_pedido_mat_prod = :id FOR UPDATE
    ");
    $st->execute([':id' => $pedidoId]);
    $estado = strtoupper(trim((string)$st->fetchColumn()));
    if ($estado === '' || $estado === 'ANULADO') {
        return ['ok' => false, 'error' => 'Pedido no encontrado o anulado.'];
    }
    if ($estado !== 'PENDIENTE') {
        return ['ok' => false, 'error' => 'Solo se puede modificar un pedido en estado PENDIENTE.'];
    }
    if (pedidoTieneReposiciones($pdo, $pedidoId)) {
        return ['ok' => false, 'error' => 'El pedido ya tiene reposiciones o cantidades repuestas.'];
    }
    return ['ok' => true];
}

function puedeAnularPedido(PDO $pdo, int $pedidoId): array
{
    $st = $pdo->prepare("
        SELECT ped_mat_prod_estado FROM pedido_materia_produccion
        WHERE id_pedido_mat_prod = :id
    ");
    $st->execute([':id' => $pedidoId]);
    $estado = strtoupper(trim((string)$st->fetchColumn()));
    if ($estado === '' || $estado === 'ANULADO') {
        return ['ok' => false, 'error' => 'Pedido no encontrado o ya anulado.'];
    }
    if (in_array($estado, ['PARCIAL', 'COMPLETADO'], true)) {
        return ['ok' => false, 'error' => 'No se puede anular: el pedido ya fue atendido.'];
    }
    if (pedidoTieneReposiciones($pdo, $pedidoId)) {
        return ['ok' => false, 'error' => 'Tiene reposiciones activas o cantidades repuestas.'];
    }
    return ['ok' => true];
}

function obtenerDetallePedido(PDO $pdo, int $pedidoId): array
{
    $st = $pdo->prepare("
        SELECT pd.id_materia_prima, mp.materia_prima_descripcion,
               pd.ped_mat_prod_cantidad, pd.cantidad_repuesta
        FROM pedido_materia_detalle_produccion pd
        JOIN materia_prima mp ON mp.id_materia_prima = pd.id_materia_prima
        WHERE pd.id_pedido_mat_prod = :id
        ORDER BY mp.materia_prima_descripcion
    ");
    $st->execute([':id' => $pedidoId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
