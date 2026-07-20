<?php
// Procesa altas, ediciones y anulaciones de pérdidas de producción (?act=).
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();

if (!isset($_SESSION['id_usuario']) && isset($_SESSION['usua_id'])) {
    $_SESSION['id_usuario'] = $_SESSION['usua_id'];
}

require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/permissions.php';
require_once __DIR__ . '/perdidas_helper.php';

if (empty($_SESSION['username'])) {
    echo "<script>alert('Sesión inválida'); window.location.href=loginUrl();</script>";
    exit;
}

if (!check_permission('PERDIDAS', false)) {
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
        $tipoPerdidaId = (int)($_POST['tipo_perdida_id'] ?? 0);
        $fecha = trim((string)($_POST['perdida_fecha'] ?? date('Y-m-d')));
        $calidadId = (int)($_POST['calidad_id'] ?? 0);
        $controlId = (int)($_POST['control_id'] ?? 0);

        $items = [];
        if (!empty($_POST['items'])) {
            $tmp = json_decode($_POST['items'], true);
            if (is_array($tmp)) {
                $items = $tmp;
            }
        }
        $lineas = normalizarLineasPerdida($items);
        $usuarioId = resolverUsuarioPerdidas($pdo);
        $clase = clasificarTipoPerdida($pdo, $tipoPerdidaId);

        if ($usuarioId <= 0 || $tipoPerdidaId <= 0 || empty($lineas) || $clase === null) {
            header('Location: view.php?alert=4&msg=' . urlencode('Complete tipo, origen y al menos un ítem.'));
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Fecha inválida.'));
            exit;
        }

        $pdo->beginTransaction();

        if ($clase === 'calidad') {
            if ($calidadId <= 0 || $controlId > 0) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode('Seleccione control de calidad.'));
                exit;
            }
            $val = validarLineasCalidad($pdo, $calidadId, $lineas);
            if (!$val['ok']) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode($val['error']));
                exit;
            }
            $origenSql = [':calidad' => $calidadId, ':control' => null];
        } else {
            if ($controlId <= 0 || $calidadId > 0) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode('Seleccione control de producción.'));
                exit;
            }
            $val = validarLineasProceso($pdo, $controlId, $lineas);
            if (!$val['ok']) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode($val['error']));
                exit;
            }
            $origenSql = [':calidad' => null, ':control' => $controlId];
        }

        $ins = $pdo->prepare("
            INSERT INTO perdidas (
                perdida_estado, perdida_fecha, tipo_perdida_id, calidad_id, control_id, id_usuario
            ) VALUES (
                'REGISTRADO', :fecha, :tipo, :calidad, :control, :usuario
            )
            RETURNING perdidas_id
        ");
        $ins->execute(array_merge($origenSql, [
            ':fecha' => $fecha,
            ':tipo' => $tipoPerdidaId,
            ':usuario' => $usuarioId,
        ]));
        $perdidasId = (int)$ins->fetchColumn();

        $cabecera = [
            'calidad_id' => $clase === 'calidad' ? $calidadId : null,
            'control_id' => $clase === 'proceso' ? $controlId : null,
        ];
        $err = aplicarEfectosPerdida($pdo, $cabecera, $lineas);
        if ($err !== null) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode($err));
            exit;
        }

        $insDet = $pdo->prepare("
            INSERT INTO perdidas_detalle (perdidas_id, producto_id, perdida_cantidad, perdida_motivo)
            VALUES (:pid, :prod, :cant, :motivo)
        ");
        foreach ($lineas as $ln) {
            $insDet->execute([
                ':pid' => $perdidasId,
                ':prod' => $ln['producto_id'],
                ':cant' => $ln['cantidad'],
                ':motivo' => $ln['motivo'],
            ]);
        }

        $origenTxt = $clase === 'calidad' ? "CC #{$calidadId}" : "Control #{$controlId}";
        bitacoraPerdidas($pdo, $usuarioId, 'ALTA',
            "Pérdida #{$perdidasId} ({$origenTxt}), " . count($lineas) . ' ítem(s)',
            $perdidasId);

        $pdo->commit();
        header('Location: view.php?alert=1');
        exit;
    }

    if ($action === 'update' && isset($_POST['Guardar'])) {
        $perdidasId = (int)($_POST['perdidas_id'] ?? 0);
        $fecha = trim((string)($_POST['perdida_fecha'] ?? ''));
        $tipoPerdidaId = (int)($_POST['tipo_perdida_id'] ?? 0);

        $items = [];
        if (!empty($_POST['items'])) {
            $tmp = json_decode($_POST['items'], true);
            if (is_array($tmp)) {
                $items = $tmp;
            }
        }
        $lineas = normalizarLineasPerdida($items);
        $usuarioId = resolverUsuarioPerdidas($pdo);

        if ($perdidasId <= 0 || $tipoPerdidaId <= 0 || $usuarioId <= 0 || empty($lineas)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Datos incompletos.'));
            exit;
        }

        $pdo->beginTransaction();

        $st = $pdo->prepare("
            SELECT perdida_estado, calidad_id, control_id, tipo_perdida_id
            FROM perdidas WHERE perdidas_id = :id FOR UPDATE
        ");
        $st->execute([':id' => $perdidasId]);
        $cab = $st->fetch();

        if (!$cab || strtoupper(trim((string)$cab['perdida_estado'])) !== 'REGISTRADO') {
            $pdo->rollBack();
            header('Location: view.php?alert=5');
            exit;
        }

        $clase = clasificarTipoPerdida($pdo, $tipoPerdidaId);
        $claseOrig = !empty($cab['calidad_id']) ? 'calidad' : 'proceso';
        if ($clase !== $claseOrig) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('No puede cambiar el tipo de pérdida al editar.'));
            exit;
        }

        if ($clase === 'calidad') {
            $val = validarLineasCalidad($pdo, (int)$cab['calidad_id'], $lineas, $perdidasId);
        } else {
            $val = validarLineasProceso($pdo, (int)$cab['control_id'], $lineas, $perdidasId);
        }
        if (!$val['ok']) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode($val['error']));
            exit;
        }

        revertirEfectosPerdida($pdo, $perdidasId);

        $cabecera = [
            'calidad_id' => $cab['calidad_id'],
            'control_id' => $cab['control_id'],
        ];
        $err = aplicarEfectosPerdida($pdo, $cabecera, $lineas);
        if ($err !== null) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode($err));
            exit;
        }

        $pdo->prepare('DELETE FROM perdidas_detalle WHERE perdidas_id = :id')->execute([':id' => $perdidasId]);

        $insDet = $pdo->prepare("
            INSERT INTO perdidas_detalle (perdidas_id, producto_id, perdida_cantidad, perdida_motivo)
            VALUES (:pid, :prod, :cant, :motivo)
        ");
        foreach ($lineas as $ln) {
            $insDet->execute([
                ':pid' => $perdidasId,
                ':prod' => $ln['producto_id'],
                ':cant' => $ln['cantidad'],
                ':motivo' => $ln['motivo'],
            ]);
        }

        $pdo->prepare("
            UPDATE perdidas SET perdida_fecha = :f, tipo_perdida_id = :t WHERE perdidas_id = :id
        ")->execute([':f' => $fecha, ':t' => $tipoPerdidaId, ':id' => $perdidasId]);

        bitacoraPerdidas($pdo, $usuarioId, 'MODIFICACION',
            "Se actualiza pérdida #{$perdidasId} (cantidades/origen recalculados)", $perdidasId);

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
