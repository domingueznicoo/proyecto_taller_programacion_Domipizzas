<?php
// AJAX: control info de pérdidas de producción.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once __DIR__ . '/perdidas_helper.php';

header('Content-Type: application/json; charset=utf-8');

$controlId = isset($_GET['control_id']) ? (int)$_GET['control_id'] : 0;
$excluir = isset($_GET['excluir_perdidas_id']) ? (int)$_GET['excluir_perdidas_id'] : null;

if ($controlId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de control inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $cab = $pdo->prepare("
        SELECT
            c.control_id,
            c.control_fecha,
            c.control_estado,
            c.orden_id,
            c.producto_id,
            c.etapa_id,
            cd.control_cantidad,
            cd.control_descri AS etapa_nombre,
            p.producto_descri
        FROM control_produccion c
        JOIN control_produccion_detalle cd ON cd.control_id = c.control_id AND cd.producto_id = c.producto_id
        JOIN productos p ON p.producto_id = c.producto_id
        WHERE c.control_id = :id
        LIMIT 1
    ");
    $cab->execute([':id' => $controlId]);
    $control = $cab->fetch();

    if (!$control) {
        echo json_encode(['success' => false, 'error' => 'Control de producción no encontrado']);
        exit;
    }

    if ($excluir === null || $excluir <= 0) {
        $val = validarOrigenProceso($pdo, $controlId);
        if (!$val['ok']) {
            echo json_encode(['success' => false, 'error' => $val['error']]);
            exit;
        }
    } else {
        $stEst = $pdo->prepare("SELECT control_estado FROM control_produccion WHERE control_id = :id");
        $stEst->execute([':id' => $controlId]);
        if (strtoupper(trim((string)$stEst->fetchColumn())) !== 'REGISTRADO') {
            echo json_encode(['success' => false, 'error' => 'Control de producción no activo.']);
            exit;
        }
    }

    $pid = (int)$control['producto_id'];
    $procesado = cantidadProcesadaControl($pdo, $controlId, $pid);
    $yaReg = cantidadPerdidaRegistrada($pdo, 'proceso', $controlId, $pid, $excluir > 0 ? $excluir : null);
    $disponible = max(0, $procesado - $yaReg);

    if ($disponible <= 0) {
        echo json_encode(['success' => false, 'error' => 'No hay unidades pendientes de marcar como pérdida en este control.']);
        exit;
    }

    $esUltima = controlEsUltimaEtapa($pdo, $controlId);

    echo json_encode([
        'success' => true,
        'control' => $control,
        'productos' => [[
            'producto_id' => $pid,
            'producto_descri' => $control['producto_descri'],
            'cantidad_disponible' => $disponible,
            'cantidad_procesada' => $procesado,
        ]],
        'es_ultima_etapa' => $esUltima,
        'hint' => $esUltima
            ? 'Pérdida en última etapa: se reintegrará cantidad pendiente en la OP.'
            : 'Unidades no recuperables del proceso (no llegan a PT).',
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
