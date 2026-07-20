<?php
// AJAX: detalle de pérdidas de producción.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once __DIR__ . '/perdidas_helper.php';

header('Content-Type: application/json; charset=utf-8');

$perdidasId = isset($_GET['perdidas_id']) ? (int)$_GET['perdidas_id'] : 0;
if ($perdidasId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
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
            pe.perdidas_id,
            pe.perdida_estado,
            pe.perdida_fecha,
            pe.calidad_id,
            pe.control_id,
            pe.tipo_perdida_id,
            tp.tipo_perdida_descri,
            u.username,
            cc.terminado_id,
            pt.orden_id AS orden_pt,
            cp.orden_id AS orden_control
        FROM perdidas pe
        JOIN tipo_perdida tp ON tp.tipo_perdida_id = pe.tipo_perdida_id
        JOIN usuarios u ON u.id_usuario = pe.id_usuario
        LEFT JOIN control_calidad_produccion cc ON cc.calidad_id = pe.calidad_id
        LEFT JOIN producto_terminado pt ON pt.terminado_id = cc.terminado_id
        LEFT JOIN control_produccion cp ON cp.control_id = pe.control_id
        WHERE pe.perdidas_id = :id
        LIMIT 1
    ");
    $cab->execute([':id' => $perdidasId]);
    $cabecera = $cab->fetch();

    if (!$cabecera) {
        echo json_encode(['success' => false, 'error' => 'Registro no encontrado']);
        exit;
    }

    $detalle = obtenerLineasDetallePerdida($pdo, $perdidasId);
    $detalle = array_map(static function ($r) {
        return [
            'producto_descri' => $r['producto_descri'],
            'perdida_cantidad' => (int)$r['cantidad'],
            'perdida_motivo' => $r['motivo'],
        ];
    }, $detalle);

    echo json_encode([
        'success' => true,
        'cabecera' => $cabecera,
        'detalle' => $detalle,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
