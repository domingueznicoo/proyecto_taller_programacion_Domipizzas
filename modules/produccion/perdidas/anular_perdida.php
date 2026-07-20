<?php
// Anula un registro de pérdidas de producción si cumple validaciones.
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
    exit;
}

require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/permissions.php';
require_once __DIR__ . '/perdidas_helper.php';

if (!check_permission('PERDIDAS', false)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

$rawBody = defined('QA_JSON_PAYLOAD') ? QA_JSON_PAYLOAD : file_get_contents('php://input');
$input = json_decode($rawBody, true);
$perdidasId = isset($input['perdidas_id']) ? (int)$input['perdidas_id'] : 0;

if ($perdidasId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $usuarioId = resolverUsuarioPerdidas($pdo);

    $pdo->beginTransaction();

    $st = $pdo->prepare("SELECT perdidas_id FROM perdidas WHERE perdidas_id = :id FOR UPDATE");
    $st->execute([':id' => $perdidasId]);
    if (!$st->fetchColumn()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
        exit;
    }

    $val = puedeAnularPerdida($pdo, $perdidasId);
    if (!$val['ok']) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $val['error']]);
        exit;
    }

    revertirEfectosPerdida($pdo, $perdidasId);

    $pdo->prepare("UPDATE perdidas SET perdida_estado = 'ANULADO' WHERE perdidas_id = :id")
        ->execute([':id' => $perdidasId]);

    bitacoraPerdidas($pdo, $usuarioId, 'INACTIVACION',
        "Se anula pérdida #{$perdidasId} y se revierten efectos sobre origen/stock",
        $perdidasId);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Pérdida anulada correctamente']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
