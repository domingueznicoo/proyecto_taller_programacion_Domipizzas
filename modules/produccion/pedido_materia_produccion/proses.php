<?php
// Procesa altas, ediciones y anulaciones de pedidos de materia prima (?act=).
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();

if (!isset($_SESSION['id_usuario']) && isset($_SESSION['usua_id'])) {
    $_SESSION['id_usuario'] = $_SESSION['usua_id'];
}
if (!isset($_SESSION['id_sucursal']) && isset($_SESSION['sucursal_id'])) {
    $_SESSION['id_sucursal'] = $_SESSION['sucursal_id'];
}

require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/permissions.php';
require_once __DIR__ . '/pedido_materia_helper.php';

if (empty($_SESSION['username'])) {
    echo "<script>alert('Sesión inválida'); window.location.href=loginUrl();</script>";
    exit;
}

if (!check_permission('PEDIDO_MATERIA_PRIMA', false)) {
    header('Location: view.php?alert=4');
    exit;
}

if (!isset($_GET['act'])) {
    header('Location: view.php?alert=4');
    exit;
}

$action = $_GET['act'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    if ($action === 'insert' && isset($_POST['Guardar'])) {
        $fecha = trim((string)($_POST['ped_mat_prod_fecha'] ?? date('Y-m-d')));
        $depositoId = (int)($_POST['deposito_id'] ?? 0);

        $items = [];
        if (!empty($_POST['items'])) {
            $tmp = json_decode($_POST['items'], true);
            if (is_array($tmp)) {
                $items = $tmp;
            }
        }
        $lineas = normalizarDetallePedMp($items);

        [$usuarioId, $sucursalId] = resolverUsuarioSucursalPed($pdo);
        if ($usuarioId <= 0 || $sucursalId <= 0 || $depositoId <= 0) {
            header('Location: view.php?alert=4&msg=' . urlencode('Complete usuario, sucursal y depósito destino.'));
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Fecha inválida.'));
            exit;
        }

        $errMp = validarMateriasActivas($pdo, $lineas);
        if ($errMp) {
            header('Location: view.php?alert=4&msg=' . urlencode($errMp));
            exit;
        }

        $stDep = $pdo->prepare('SELECT 1 FROM deposito WHERE deposito_id = :id');
        $stDep->execute([':id' => $depositoId]);
        if (!$stDep->fetchColumn()) {
            header('Location: view.php?alert=4&msg=' . urlencode('Depósito inválido.'));
            exit;
        }

        $pdo->beginTransaction();

        $ins = $pdo->prepare("
            INSERT INTO pedido_materia_produccion (
                ped_mat_prod_fecha, ped_mat_prod_estado, id_usuario, id_sucursal, deposito_id
            ) VALUES (:fecha, 'PENDIENTE', :usuario, :sucursal, :dep)
            RETURNING id_pedido_mat_prod
        ");
        $ins->execute([
            ':fecha' => $fecha,
            ':usuario' => $usuarioId,
            ':sucursal' => $sucursalId,
            ':dep' => $depositoId,
        ]);
        $pedidoId = (int)$ins->fetchColumn();

        $insDet = $pdo->prepare("
            INSERT INTO pedido_materia_detalle_produccion (
                id_pedido_mat_prod, id_materia_prima, ped_mat_prod_cantidad, cantidad_repuesta
            ) VALUES (:ped, :mp, :cant, 0)
        ");
        foreach ($lineas as $mpId => $cant) {
            $insDet->execute([':ped' => $pedidoId, ':mp' => $mpId, ':cant' => $cant]);
        }

        bitacoraPedMp($pdo, $usuarioId, 'ALTA',
            "Pedido MP #{$pedidoId} — depósito #{$depositoId}, " . count($lineas) . ' ítem(s)',
            $pedidoId);

        $pdo->commit();
        header('Location: view.php?alert=1');
        exit;
    }

    if ($action === 'update' && isset($_POST['Guardar'])) {
        $pedidoId = (int)($_POST['id_pedido_mat_prod'] ?? 0);
        $fecha = trim((string)($_POST['ped_mat_prod_fecha'] ?? ''));
        $depositoId = (int)($_POST['deposito_id'] ?? 0);

        $items = [];
        if (!empty($_POST['items'])) {
            $tmp = json_decode($_POST['items'], true);
            if (is_array($tmp)) {
                $items = $tmp;
            }
        }
        $lineas = normalizarDetallePedMp($items);

        [$usuarioId] = resolverUsuarioSucursalPed($pdo);
        if ($pedidoId <= 0 || $usuarioId <= 0 || $depositoId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Datos incompletos.'));
            exit;
        }

        $errMp = validarMateriasActivas($pdo, $lineas);
        if ($errMp) {
            header('Location: view.php?alert=4&msg=' . urlencode($errMp));
            exit;
        }

        $pdo->beginTransaction();

        $chk = pedidoEditable($pdo, $pedidoId);
        if (!$chk['ok']) {
            $pdo->rollBack();
            header('Location: view.php?alert=5&msg=' . urlencode($chk['error']));
            exit;
        }

        $stDep = $pdo->prepare('SELECT 1 FROM deposito WHERE deposito_id = :id');
        $stDep->execute([':id' => $depositoId]);
        if (!$stDep->fetchColumn()) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('Depósito inválido.'));
            exit;
        }

        $pdo->prepare("
            UPDATE pedido_materia_produccion
            SET ped_mat_prod_fecha = :f, deposito_id = :d
            WHERE id_pedido_mat_prod = :id
        ")->execute([':f' => $fecha, ':d' => $depositoId, ':id' => $pedidoId]);

        $pdo->prepare('DELETE FROM pedido_materia_detalle_produccion WHERE id_pedido_mat_prod = :id')
            ->execute([':id' => $pedidoId]);

        $insDet = $pdo->prepare("
            INSERT INTO pedido_materia_detalle_produccion (
                id_pedido_mat_prod, id_materia_prima, ped_mat_prod_cantidad, cantidad_repuesta
            ) VALUES (:ped, :mp, :cant, 0)
        ");
        foreach ($lineas as $mpId => $cant) {
            $insDet->execute([':ped' => $pedidoId, ':mp' => $mpId, ':cant' => $cant]);
        }

        bitacoraPedMp($pdo, $usuarioId, 'MODIFICACION',
            "Pedido MP #{$pedidoId} actualizado (cabecera y detalle)", $pedidoId);

        $pdo->commit();
        header('Location: view.php?alert=2');
        exit;
    }

    header('Location: view.php?alert=4');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: view.php?alert=4&msg=' . urlencode($e->getMessage()));
}
