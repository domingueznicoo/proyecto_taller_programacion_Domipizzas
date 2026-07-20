<?php
// Anula un registro de reposición de materia prima si cumple validaciones.
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/permissions.php';
require_once __DIR__ . '/reposicion_helper.php';

if (empty($_SESSION['username']) || !check_permission('REPOSICION_MATERIA', false)) {
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit;
}

$rawBody = defined('QA_JSON_PAYLOAD') ? QA_JSON_PAYLOAD : file_get_contents('php://input');
$input = json_decode($rawBody, true);
$reposicionId = (int)($input['reposicion_id'] ?? 0);
if ($reposicionId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $usuarioId = resolverUsuarioRep($pdo);

    $pdo->beginTransaction();

    $st = $pdo->prepare("SELECT reposicion_id FROM reposicion_materia WHERE reposicion_id = :id FOR UPDATE");
    $st->execute([':id' => $reposicionId]);
    if (!$st->fetchColumn()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Reposición no encontrada']);
        exit;
    }

    $val = puedeAnularReposicion($pdo, $reposicionId);
    if (!$val['ok']) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $val['error']]);
        exit;
    }

    revertirEfectosReposicion($pdo, $reposicionId);

    $pdo->prepare("UPDATE reposicion_materia SET reposicion_estado = 'ANULADO' WHERE reposicion_id = :id")
        ->execute([':id' => $reposicionId]);

    bitacoraRep($pdo, $usuarioId, 'ANULACION',
        "Anulación reposición #{$reposicionId}; stock y pedido revertidos", $reposicionId);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
