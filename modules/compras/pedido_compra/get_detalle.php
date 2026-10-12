<?php
const PEDIDO_JSON = true;
require_once __DIR__ . '/pedido_helper.php';
header('Content-Type: application/json; charset=UTF-8');
try {
    $pdo = pedidoConexion();
    pedidoUsuario($pdo);
    if (isset($_GET['pedido_mp_id'])) {
        $detalle = pedidoOrigen($pdo, pedidoEnteroPositivo($_GET['pedido_mp_id']));
    } else {
        $id = pedidoEnteroPositivo($_GET['ped_id'] ?? null);
        pedidoCabecera($pdo, $id);
        $detalle = pedidoDetalle($pdo, $id);
    }
    echo json_encode(['success' => true, 'detalle' => $detalle], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Detalle pedido compra: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e instanceof PDOException ? 'No se pudo consultar el detalle.' : $e->getMessage()]);
}
