<?php
const PEDIDO_JSON = true;
require_once __DIR__ . '/pedido_helper.php';
header('Content-Type: application/json; charset=UTF-8');
$pdo = null;
try {
    $pdo = pedidoConexion();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método no permitido.');
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $id = pedidoEnteroPositivo($input['ped_id'] ?? null);
    pedidoAnular($pdo, $id, pedidoUsuario($pdo));
    echo json_encode(['success' => true, 'message' => 'Pedido anulado correctamente.']);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Anular pedido compra: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e instanceof PDOException ? 'No se pudo anular el pedido.' : $e->getMessage()]);
}
