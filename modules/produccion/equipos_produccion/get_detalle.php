<?php
// AJAX: detalle de equipos de trabajo.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once __DIR__ . '/equipos_helper.php';

header('Content-Type: application/json; charset=utf-8');

$equipoId = isset($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : 0;
if ($equipoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $cols = 'e.equipo_id, e.equipo_descri, e.equipo_estado, e.equipo_fecha, e.orden_id,
               op.orden_prod_estado, op.id_pedido_produccion, u.username';
    if (columnaEquipoExiste($pdo, 'etapa_nombre')) {
        $cols .= ', e.etapa_nombre';
    }
    $st = $pdo->prepare("
        SELECT {$cols}
        FROM equipos_produccion e
        JOIN orden_produccion op ON op.orden_id = e.orden_id
        JOIN usuarios u ON u.id_usuario = e.id_usuario
        WHERE e.equipo_id = :id
    ");
    $st->execute([':id' => $equipoId]);
    $cab = $st->fetch();
    if (!$cab) {
        echo json_encode(['success' => false, 'error' => 'Equipo no encontrado']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'equipo' => $cab,
        'miembros' => cargarDetalleEquipo($pdo, $equipoId),
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
