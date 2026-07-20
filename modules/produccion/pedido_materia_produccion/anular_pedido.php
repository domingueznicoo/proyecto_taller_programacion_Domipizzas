<?php
// Anula un registro de pedidos de materia prima si cumple validaciones.
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/permissions.php';
require_once __DIR__ . '/pedido_materia_helper.php';

if (empty($_SESSION['username']) || !check_permission('PEDIDO_MATERIA_PRIMA', false)) {
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit;
}

$rawBody = defined('QA_JSON_PAYLOAD') ? QA_JSON_PAYLOAD : file_get_contents('php://input');
$input = json_decode($rawBody, true);
$pedidoId = (int)($input['id_pedido_mat_prod'] ?? 0);
if ($pedidoId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    [$usuarioId] = resolverUsuarioSucursalPed($pdo);

    $pdo->beginTransaction();

    $st = $pdo->prepare("
        SELECT id_pedido_mat_prod FROM pedido_materia_produccion
        WHERE id_pedido_mat_prod = :id FOR UPDATE
    ");
    $st->execute([':id' => $pedidoId]);
    if (!$st->fetchColumn()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado.']);
        exit;
    }

    $val = puedeAnularPedido($pdo, $pedidoId);
    if (!$val['ok']) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $val['error']]);
        exit;
    }

    $pdo->prepare("
        UPDATE pedido_materia_produccion SET ped_mat_prod_estado = 'ANULADO'
        WHERE id_pedido_mat_prod = :id
    ")->execute([':id' => $pedidoId]);

    bitacoraPedMp($pdo, $usuarioId, 'ANULACION',
        "Anulación pedido MP #{$pedidoId}", $pedidoId);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
