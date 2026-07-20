<?php
// AJAX: pedido info de reposición de materia prima.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$pedidoId = isset($_GET['id_pedido_mat_prod']) ? (int)$_GET['id_pedido_mat_prod'] : 0;
if ($pedidoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de pedido inválido']);
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
            p.id_pedido_mat_prod,
            p.ped_mat_prod_fecha,
            p.ped_mat_prod_estado,
            p.deposito_id,
            d.deposito_descri
        FROM pedido_materia_produccion p
        JOIN deposito d ON d.deposito_id = p.deposito_id
        WHERE p.id_pedido_mat_prod = :id
    ");
    $cab->execute([':id' => $pedidoId]);
    $pedido = $cab->fetch();
    if (!$pedido) {
        echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
        exit;
    }

    $excluir = isset($_GET['excluir_reposicion_id']) ? (int)$_GET['excluir_reposicion_id'] : 0;

    $est = strtoupper(trim((string)$pedido['ped_mat_prod_estado']));
    if ($excluir <= 0 && !in_array($est, ['PENDIENTE', 'PARCIAL'], true)) {
        echo json_encode([
            'success' => false,
            'error' => "Pedido en estado {$est}. Solo PENDIENTE o PARCIAL admiten reposición.",
        ]);
        exit;
    }
    if ($excluir > 0 && $est === 'ANULADO') {
        echo json_encode(['success' => false, 'error' => 'El pedido está anulado.']);
        exit;
    }

    $det = $pdo->prepare("
        SELECT
            pd.id_materia_prima,
            mp.materia_prima_descripcion,
            pd.ped_mat_prod_cantidad,
            pd.cantidad_repuesta,
            (pd.ped_mat_prod_cantidad - pd.cantidad_repuesta)::int AS cantidad_pendiente
        FROM pedido_materia_detalle_produccion pd
        JOIN materia_prima mp ON mp.id_materia_prima = pd.id_materia_prima
        WHERE pd.id_pedido_mat_prod = :id
        ORDER BY mp.materia_prima_descripcion
    ");
    $det->execute([':id' => $pedidoId]);

    $lineas = [];
    foreach ($det->fetchAll() as $row) {
        $mpId = (int)$row['id_materia_prima'];
        $pend = (int)$row['cantidad_pendiente'];
        if ($excluir > 0) {
            $stRep = $pdo->prepare("
                SELECT COALESCE(reposicion_cantidad, 0)::int
                FROM reposicion_materia_detalle
                WHERE reposicion_id = :r AND id_materia_prima = :mp
            ");
            $stRep->execute([':r' => $excluir, ':mp' => $mpId]);
            $actualRep = (int)$stRep->fetchColumn();
            $pend += $actualRep;
            $row['cantidad_actual_rep'] = $actualRep;
            $row['cantidad_repuesta'] = max(0, (int)$row['cantidad_repuesta'] - $actualRep);
        }
        if ($excluir <= 0 && $pend <= 0) {
            continue;
        }
        if ($excluir > 0 && $pend <= 0 && ($row['cantidad_actual_rep'] ?? 0) <= 0) {
            continue;
        }
        $row['cantidad_pendiente'] = max(0, $pend);
        $lineas[] = $row;
    }

    if (empty($lineas)) {
        echo json_encode(['success' => false, 'error' => 'El pedido no tiene cantidades pendientes de reponer.']);
        exit;
    }

    echo json_encode(['success' => true, 'pedido' => $pedido, 'lineas' => $lineas]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
