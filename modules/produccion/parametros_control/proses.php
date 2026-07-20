<?php
// Procesa altas, ediciones y anulaciones de parámetros de inspección (?act=).
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/permissions.php';
check_permission('REFERENCIALES');

function bitacora(PDO $pdo, int $idUsuario, string $accion, string $desc, ?int $id = null): void {
    $pdo->exec("SAVEPOINT sp_bit");
    try {
        $st = $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:u, 'ParametrosControl', :id, :acc, :d)
        ");
        $st->execute([':u' => $idUsuario, ':id' => $id, ':acc' => strtoupper($accion), ':d' => $desc]);
    } catch (Throwable $e) {
        $pdo->exec("ROLLBACK TO SAVEPOINT sp_bit");
        error_log("Bitácora falló: " . $e->getMessage());
    }
}

function normalize_desc(string $s): string {
    $s = trim($s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return mb_strtoupper($s, 'UTF-8');
}

function normalizarEstado(string $estado): string {
    $estado = strtoupper(trim($estado));
    return in_array($estado, ['ACTIVO', 'INACTIVO'], true) ? $estado : 'ACTIVO';
}

function validarProductoActivo(PDO $pdo, int $productoId): bool {
    $q = $pdo->prepare("
        SELECT 1 FROM productos
        WHERE producto_id = :id AND UPPER(TRIM(producto_estado)) = 'ACTIVO'
        LIMIT 1
    ");
    $q->execute([':id' => $productoId]);
    return (bool)$q->fetchColumn();
}

function existeDuplicadoActivo(PDO $pdo, int $productoId, string $descri, int $excludeId = 0): bool {
    $sql = "
        SELECT 1 FROM parametros_control
        WHERE producto_id = :prod
          AND UPPER(TRIM(parametro_descri)) = UPPER(TRIM(:descri))
          AND UPPER(TRIM(parametro_estado)) = 'ACTIVO'
    ";
    $params = [':prod' => $productoId, ':descri' => $descri];
    if ($excludeId > 0) {
        $sql .= " AND parametro_id <> :exclude";
        $params[':exclude'] = $excludeId;
    }
    $sql .= " LIMIT 1";
    $q = $pdo->prepare($sql);
    $q->execute($params);
    return (bool)$q->fetchColumn();
}

function validarInactivacionParametro(PDO $pdo, int $parametroId): array {
    $bloqueos = [];
    $q = $pdo->prepare("SELECT 1 FROM control_calidad_detalle WHERE parametro_id = :id LIMIT 1");
    $q->execute([':id' => $parametroId]);
    if ($q->fetchColumn()) {
        $bloqueos[] = 'Tiene registros en inspecciones de control de calidad.';
    }
    return ['puede' => empty($bloqueos), 'bloqueos' => $bloqueos];
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    if (empty($_SESSION['username'])) {
        header("Location: view.php?alert=4&msg=" . urlencode("Sesión inválida"));
        exit();
    }

    $qUsuario = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
    $qUsuario->execute([':u' => $_SESSION['username']]);
    $usuario = $qUsuario->fetch();
    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
    $idUsuario = (int)$usuario['id_usuario'];

    if (!isset($_GET['act'])) {
        header("Location: view.php?alert=4");
        exit();
    }

    $action = $_GET['act'];

    if ($action === 'get' && isset($_GET['id'])) {
        header('Content-Type: application/json; charset=UTF-8');
        if (ob_get_length()) { ob_clean(); }

        $id = (int)$_GET['id'];
        $q = $pdo->prepare("
            SELECT parametro_id, parametro_descri, producto_id, parametro_estado
            FROM parametros_control
            WHERE parametro_id = :id
            LIMIT 1
        ");
        $q->execute([':id' => $id]);
        $row = $q->fetch();
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Parámetro no encontrado']);
            exit;
        }
        $row['parametro_estado'] = normalizarEstado($row['parametro_estado'] ?? 'ACTIVO');
        echo json_encode(['success' => true, 'data' => $row]);
        exit;
    }

    if ($action === 'validate_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        if (ob_get_length()) { ob_clean(); }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }

        $validacion = validarInactivacionParametro($pdo, $id);
        echo json_encode([
            'success' => true,
            'puede_inactivar' => $validacion['puede'],
            'bloqueos' => $validacion['bloqueos'],
        ]);
        exit;
    }

    if ($action === 'insert' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        if (ob_get_length()) { ob_clean(); }

        $productoId = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
        $descri = normalize_desc($_POST['parametro_descri'] ?? '');
        $estado = normalizarEstado($_POST['parametro_estado'] ?? 'ACTIVO');

        if ($productoId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Debe seleccionar un producto.']);
            exit;
        }
        if ($descri === '') {
            echo json_encode(['success' => false, 'message' => 'La descripción es obligatoria.']);
            exit;
        }
        if (mb_strlen($descri) > 30) {
            echo json_encode(['success' => false, 'message' => 'La descripción no puede superar 30 caracteres.']);
            exit;
        }
        if (!validarProductoActivo($pdo, $productoId)) {
            echo json_encode(['success' => false, 'message' => 'El producto seleccionado no está activo.']);
            exit;
        }
        if ($estado === 'ACTIVO' && existeDuplicadoActivo($pdo, $productoId, $descri)) {
            echo json_encode([
                'success' => false,
                'message' => 'Ya existe un parámetro ACTIVO con la misma descripción para este producto.',
            ]);
            exit;
        }

        try {
            $pdo->beginTransaction();
            $q = $pdo->prepare("
                INSERT INTO parametros_control (parametro_descri, producto_id, id_usuario, parametro_estado)
                VALUES (:descri, :prod, :uid, :estado)
                RETURNING parametro_id
            ");
            $q->execute([
                ':descri' => $descri,
                ':prod' => $productoId,
                ':uid' => $idUsuario,
                ':estado' => $estado,
            ]);
            $parametroId = (int)$q->fetchColumn();

            $qProd = $pdo->prepare("SELECT producto_descri FROM productos WHERE producto_id = :id");
            $qProd->execute([':id' => $productoId]);
            $prodNombre = $qProd->fetchColumn() ?: "Producto #{$productoId}";

            bitacora($pdo, $idUsuario, 'ALTA', "Parámetro creado: {$descri} | Producto: {$prodNombre} (ID: {$parametroId})", $parametroId);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Parámetro registrado correctamente.']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            if (stripos($e->getMessage(), 'uq_parametros_producto_descri_activo') !== false) {
                echo json_encode(['success' => false, 'message' => 'Ya existe un parámetro ACTIVO con la misma descripción para este producto.']);
            } else {
                error_log("Error insert parametro: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error al registrar el parámetro.']);
            }
        }
        exit;
    }

    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        if (ob_get_length()) { ob_clean(); }

        $parametroId = isset($_POST['parametro_id']) ? (int)$_POST['parametro_id'] : 0;
        $productoId = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
        $descri = normalize_desc($_POST['parametro_descri'] ?? '');
        $estado = normalizarEstado($_POST['parametro_estado'] ?? 'ACTIVO');

        if ($parametroId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de parámetro inválido.']);
            exit;
        }
        if ($descri === '') {
            echo json_encode(['success' => false, 'message' => 'La descripción es obligatoria.']);
            exit;
        }
        if (mb_strlen($descri) > 30) {
            echo json_encode(['success' => false, 'message' => 'La descripción no puede superar 30 caracteres.']);
            exit;
        }

        $qActual = $pdo->prepare("SELECT producto_id FROM parametros_control WHERE parametro_id = :id LIMIT 1");
        $qActual->execute([':id' => $parametroId]);
        $productoId = (int)$qActual->fetchColumn();
        if ($productoId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Parámetro no encontrado.']);
            exit;
        }

        if ($estado === 'ACTIVO' && existeDuplicadoActivo($pdo, $productoId, $descri, $parametroId)) {
            echo json_encode([
                'success' => false,
                'message' => 'Ya existe otro parámetro ACTIVO con la misma descripción para este producto.',
            ]);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $q = $pdo->prepare("
                UPDATE parametros_control
                SET parametro_descri = :descri,
                    parametro_estado = :estado
                WHERE parametro_id = :id
            ");
            $q->execute([
                ':descri' => $descri,
                ':estado' => $estado,
                ':id' => $parametroId,
            ]);

            $qInfo = $pdo->prepare("
                SELECT p.producto_descri FROM parametros_control pc
                JOIN productos p ON p.producto_id = pc.producto_id
                WHERE pc.parametro_id = :id
            ");
            $qInfo->execute([':id' => $parametroId]);
            $prodNombre = $qInfo->fetchColumn() ?: "Producto";

            bitacora($pdo, $idUsuario, 'MODIFICACION', "Parámetro actualizado: {$descri} | Producto: {$prodNombre} (ID: {$parametroId})", $parametroId);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Parámetro modificado correctamente.']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            if (stripos($e->getMessage(), 'uq_parametros_producto_descri_activo') !== false) {
                echo json_encode(['success' => false, 'message' => 'Ya existe otro parámetro ACTIVO con la misma descripción para este producto.']);
            } else {
                error_log("Error update parametro: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error al actualizar el parámetro.']);
            }
        }
        exit;
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        if (ob_get_length()) { ob_clean(); }

        $parametroId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($parametroId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $qPar = $pdo->prepare("
                SELECT pc.parametro_descri, pc.parametro_estado, p.producto_descri
                FROM parametros_control pc
                JOIN productos p ON p.producto_id = pc.producto_id
                WHERE pc.parametro_id = :id
                LIMIT 1
            ");
            $qPar->execute([':id' => $parametroId]);
            $par = $qPar->fetch();

            if (!$par) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Parámetro no encontrado']);
                exit;
            }

            $estadoActual = normalizarEstado($par['parametro_estado'] ?? 'ACTIVO');
            if ($estadoActual !== 'ACTIVO') {
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'El parámetro ya está inactivo.', 'inactivado' => false]);
                exit;
            }

            $validacion = validarInactivacionParametro($pdo, $parametroId);
            if (!$validacion['puede']) {
                $pdo->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => 'No se puede inactivar el parámetro porque tiene inspecciones de calidad asociadas.',
                    'bloqueos' => $validacion['bloqueos'],
                    'can_force' => false,
                ]);
                exit;
            }

            $qUpdate = $pdo->prepare("UPDATE parametros_control SET parametro_estado = 'INACTIVO' WHERE parametro_id = :id");
            $qUpdate->execute([':id' => $parametroId]);

            bitacora(
                $pdo,
                $idUsuario,
                'INACTIVACION',
                "Parámetro #{$parametroId} ({$par['parametro_descri']}) inactivado | Producto: {$par['producto_descri']}",
                $parametroId
            );

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Parámetro inactivado correctamente.',
                'inactivado' => true,
            ]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error inactivar parametro: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    header("Location: view.php?alert=4");
    exit();

} catch (PDOException $e) {
    error_log("Error conexión parametros_control: " . $e->getMessage());
    header("Location: view.php?alert=4&msg=" . urlencode("Error de conexión a la base de datos."));
    exit();
} catch (Exception $e) {
    error_log("Error general parametros_control: " . $e->getMessage());
    header("Location: view.php?alert=4&msg=" . urlencode($e->getMessage()));
    exit();
}
