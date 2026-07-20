<?php
// AJAX: siguiente etapa de control de producción.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once __DIR__ . '/etapas_helper.php';
require_once realpath(__DIR__ . '/../equipos_produccion/equipos_helper.php');

header('Content-Type: application/json; charset=utf-8');

$ordenId = isset($_GET['orden_id']) ? (int)$_GET['orden_id'] : 0;
$productoId = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;

if ($ordenId <= 0 || $productoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $info = resolverSiguienteEtapa($pdo, $ordenId, $productoId);
    if (!$info['success']) {
        echo json_encode(['success' => false, 'error' => $info['error']]);
        exit;
    }

    if (!$info['completado'] && !empty($info['siguiente_etapa'])) {
        $sig = $info['siguiente_etapa'];
        $etapaId = (int)($sig['etapa_id'] ?? 0);
        $etapaNom = trim((string)($sig['etapa_nombre'] ?? ''));
        $errEquipo = validarEquipoParaControl($pdo, $ordenId, $etapaId, $etapaNom);
        if ($errEquipo) {
            echo json_encode([
                'success' => false,
                'error' => $errEquipo,
                'requiere_equipo' => true,
                'orden_id' => $ordenId,
                'etapa_id' => $etapaId,
                'etapa_nombre' => $etapaNom,
            ]);
            exit;
        }
    }

    $equipo = null;
    if (!$info['completado'] && !empty($info['siguiente_etapa'])) {
        $equipo = obtenerEquipoParaControl($pdo, $ordenId, (int)$info['siguiente_etapa']['etapa_id']);
    }

    echo json_encode([
        'success' => true,
        'orden_cantidad' => $info['orden_cantidad'],
        'completado' => $info['completado'],
        'siguiente_etapa' => $info['siguiente_etapa'],
        'cantidad_maxima' => $info['cantidad_maxima'],
        'es_ultima_etapa' => $info['es_ultima_etapa'],
        'requiere_consumo_mp' => $info['requiere_consumo_mp'],
        'resumen_etapas' => $info['resumen'],
        'equipo' => $equipo,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
