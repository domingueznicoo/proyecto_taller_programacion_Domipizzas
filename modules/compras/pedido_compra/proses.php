<?php
require_once __DIR__ . '/pedido_helper.php';
$pdo = null;
try {
    $pdo = pedidoConexion();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método no permitido.');
    $usuario = pedidoUsuario($pdo);
    $accion = $_GET['act'] ?? '';
    if ($accion === 'anular') {
        pedidoAnular($pdo, pedidoEnteroPositivo($_POST['pedido_id'] ?? null), $usuario);
        header('Location: view.php?alert=3');
        exit;
    }
    if (!in_array($accion, ['insert', 'update'], true)) throw new RuntimeException('Acción inválida.');
    pedidoGuardar($pdo, $usuario, $accion, $_POST);
    header('Location: view.php?alert=' . ($accion === 'insert' ? 1 : 2));
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Pedido compra: ' . $e->getMessage());
    $mensaje = $e instanceof PDOException ? 'No se pudo guardar el pedido. Revise el registro de errores del servidor.' : $e->getMessage();
    header('Location: view.php?alert=4&msg=' . urlencode($mensaje));
}
