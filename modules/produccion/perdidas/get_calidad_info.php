<?php
// AJAX: calidad info de pérdidas de producción.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once __DIR__ . '/perdidas_helper.php';

header('Content-Type: application/json; charset=utf-8');

$calidadId = isset($_GET['calidad_id']) ? (int)$_GET['calidad_id'] : 0;
$excluir = isset($_GET['excluir_perdidas_id']) ? (int)$_GET['excluir_perdidas_id'] : null;

if ($calidadId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de control de calidad inválido']);
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
            cc.calidad_id,
            cc.calidad_estado,
            cc.terminado_id,
            pt.orden_id,
            to_char(cc.calidad_fecha, 'YYYY-MM-DD') AS calidad_fecha
        FROM control_calidad_produccion cc
        JOIN producto_terminado pt ON pt.terminado_id = cc.terminado_id
        WHERE cc.calidad_id = :id
        LIMIT 1
    ");
    $cab->execute([':id' => $calidadId]);
    $calidad = $cab->fetch();

    if (!$calidad) {
        echo json_encode(['success' => false, 'error' => 'Control de calidad no encontrado']);
        exit;
    }

    $val = validarOrigenCalidad($pdo, $calidadId, $excluir > 0 ? $excluir : null);
    if (!$val['ok']) {
        echo json_encode(['success' => false, 'error' => $val['error']]);
        exit;
    }

    $det = $pdo->prepare("
        SELECT
            cd.producto_id,
            p.producto_descri,
            MAX(cd.calidad_cantidad)::int AS cantidad_rechazada,
            ptd.deposito_id,
            d.deposito_descri
        FROM control_calidad_detalle cd
        JOIN productos p ON p.producto_id = cd.producto_id
        JOIN control_calidad_produccion cc ON cc.calidad_id = cd.calidad_id
        JOIN productos_terminados_detalle ptd
            ON ptd.terminado_id = cc.terminado_id AND ptd.producto_id = cd.producto_id
        LEFT JOIN deposito d ON d.deposito_id = ptd.deposito_id
        WHERE cd.calidad_id = :id
          AND UPPER(TRIM(cd.calidad_estado)) = 'NO CONFORME'
        GROUP BY cd.producto_id, p.producto_descri, ptd.deposito_id, d.deposito_descri
        ORDER BY p.producto_descri
    ");
    $det->execute([':id' => $calidadId]);

    $productos = [];
    foreach ($det->fetchAll() as $row) {
        $pid = (int)$row['producto_id'];
        $rechazado = (int)$row['cantidad_rechazada'];
        $yaReg = cantidadPerdidaRegistrada($pdo, 'calidad', $calidadId, $pid, $excluir > 0 ? $excluir : null);
        $disp = max(0, $rechazado - $yaReg);
        if ($disp > 0) {
            $row['cantidad_disponible'] = $disp;
            $row['cantidad_rechazada'] = $rechazado;
            $productos[] = $row;
        }
    }

    if (empty($productos)) {
        echo json_encode(['success' => false, 'error' => 'No hay productos rechazados disponibles para merma.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'calidad' => $calidad,
        'productos' => $productos,
        'hint' => 'Baja definitiva: se descontará stock del depósito del lote PT.',
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
