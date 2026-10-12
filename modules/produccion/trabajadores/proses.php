<?php
// Procesa altas, ediciones y anulaciones de trabajadores (?act=).
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/permissions.php';
check_permission('REFERENCIALES');



function bitacora(PDO $pdo, int $idUsuario, string $accion, string $desc, ?int $id = null): void {
    $pdo->exec("SAVEPOINT sp_bit");
    try {
        $sql = "
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:u, 'Trabajadores', :id, :acc, :d)
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':u' => $idUsuario, ':id' => $id, ':acc' => strtoupper($accion), ':d' => $desc]);
    } catch (Throwable $e) {
        $pdo->exec("ROLLBACK TO SAVEPOINT sp_bit");
        error_log("Bitácora falló: " . $e->getMessage());
        throw $e;
    }
}

function normalize_text(string $s): string {
    $s = trim($s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return mb_strtoupper($s, 'UTF-8');
}

function normalizarEstadoTrabajador(string $estado): string {
    $estado = strtoupper(trim($estado));
    if ($estado === 'ANULADO') {
        return 'INACTIVO';
    }
    return in_array($estado, ['ACTIVO', 'INACTIVO'], true) ? $estado : 'ACTIVO';
}

function validarInactivacionTrabajador(PDO $pdo, int $trabajadorId): array {
    $bloqueos = [];

    $vinculos = [
        'equipos_detalle' => 'equipos de trabajo por etapa',
        'inspectores' => 'inspectores de producción',
    ];

    foreach ($vinculos as $tabla => $descripcion) {
        $q = $pdo->prepare("SELECT 1 FROM {$tabla} WHERE trabajadores_id = :id LIMIT 1");
        $q->execute([':id' => $trabajadorId]);
        if ($q->fetchColumn()) {
            $bloqueos[] = "Tiene registros en {$descripcion}.";
        }
    }

    return [
        'puede' => empty($bloqueos),
        'bloqueos' => $bloqueos,
    ];
}

function validarPersonalActivo(PDO $pdo, int $idPersonal): bool {
    $q = $pdo->prepare("
        SELECT 1 FROM personal
        WHERE personal_id = :id AND UPPER(TRIM(personal_estado)) = 'ACTIVO'
        LIMIT 1
    ");
    $q->execute([':id' => $idPersonal]);
    return (bool)$q->fetchColumn();
}

function validarPersonalParaTrabajador(PDO $pdo, int $idPersonal, int $trabajadorId = 0): bool {
    if (validarPersonalActivo($pdo, $idPersonal)) {
        return true;
    }
    if ($trabajadorId > 0) {
        $q = $pdo->prepare("
            SELECT 1 FROM trabajadores
            WHERE trabajadores_id = :id AND personal_id = :personal
            LIMIT 1
        ");
        $q->execute([':id' => $trabajadorId, ':personal' => $idPersonal]);
        return (bool)$q->fetchColumn();
    }
    return false;
}

function validarCatalogosTrabajador(PDO $pdo, int $rolId, int $turnoId): bool {
    $q = $pdo->prepare("SELECT 1 FROM rol WHERE rol_id = :id AND UPPER(TRIM(rol_estado)) = 'ACTIVO'");
    $q->execute([':id' => $rolId]);
    if (!$q->fetchColumn()) return false;
    $q = $pdo->prepare("SELECT 1 FROM turno WHERE turno_id = :id AND UPPER(TRIM(turno_estado)) = 'ACTIVO'");
    $q->execute([':id' => $turnoId]);
    return (bool)$q->fetchColumn();
}

function personalYaAsignado(PDO $pdo, int $idPersonal, int $excludeId = 0): bool {
    $sql = "
        SELECT 1 FROM trabajadores
        WHERE personal_id = :personal
          AND UPPER(TRIM(trabajador_estado)) = 'ACTIVO'
    ";
    $params = [':personal' => $idPersonal];
    if ($excludeId > 0) {
        $sql .= " AND trabajadores_id <> :exclude";
        $params[':exclude'] = $excludeId;
    }
    $sql .= " LIMIT 1";
    $q = $pdo->prepare($sql);
    $q->execute($params);
    return (bool)$q->fetchColumn();
}

function validarCamposTrabajador(array $post, bool $isUpdate = false): array {
    $costo = trim((string)($post['trabajador_costo_hora'] ?? ''));
    if (!preg_match('/^\d{1,10}$/D', $costo) || (float)$costo > 2147483647) {
        return ['ok' => false, 'msg' => 'Ingrese un costo por hora entero entre 0 y 2147483647, sin decimales.'];
    }
    $personalId = (int)($post['personal_id'] ?? 0);
    $rolId = (int)($post['rol_id'] ?? 0);
    $turnoId = (int)($post['turno_id'] ?? 0);
    if ($personalId <= 0 || $rolId <= 0 || $turnoId <= 0) {
        return ['ok' => false, 'msg' => 'Seleccione personal, rol y turno.'];
    }
    return [
        'ok' => true, 'trabajador_costo_hora' => $costo, 'personal_id' => $personalId, 'rol_id' => $rolId, 'turno_id' => $turnoId,
        'estado' => $isUpdate ? normalizarEstadoTrabajador($post['trabajador_estado'] ?? 'ACTIVO') : 'ACTIVO',
    ];
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

    $qUsuario = $pdo->prepare("SELECT usuario_id FROM usuario WHERE username = :u LIMIT 1");
    $qUsuario->execute([':u' => $_SESSION['username']]);
    $usuario = $qUsuario->fetch();

    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
    $idUsuario = (int)$usuario['usuario_id'];

    if (!isset($_GET['act'])) {
        header("Location: view.php?alert=4");
        exit();
    }

    $action = $_GET['act'];

    if ($action === 'activate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        $id = (int)($_POST['id'] ?? 0);
        try {
            if ($id <= 0) throw new Exception('ID inválido.');
            $pdo->beginTransaction();
            $q = $pdo->prepare("SELECT * FROM trabajadores WHERE trabajadores_id = :id FOR UPDATE");
            $q->execute([':id' => $id]);
            $trabajador = $q->fetch();
            if (!$trabajador) throw new Exception('Trabajador no encontrado.');
            if (normalizarEstadoTrabajador($trabajador['trabajador_estado']) !== 'ACTIVO') {
                if (!validarPersonalActivo($pdo, (int)$trabajador['personal_id']) ||
                    personalYaAsignado($pdo, (int)$trabajador['personal_id'], $id) ||
                    !validarCatalogosTrabajador($pdo, (int)$trabajador['rol_id'], (int)$trabajador['turno_id'])) {
                    throw new Exception('Revise que el personal, rol y turno estén activos y que el personal no esté asignado a otro trabajador activo.');
                }
                $q = $pdo->prepare("UPDATE trabajadores SET trabajador_estado = 'ACTIVO' WHERE trabajadores_id = :id");
                $q->execute([':id' => $id]);
                bitacora($pdo, $idUsuario, 'MODIFICACION', "Trabajador #{$id} reactivado (INACTIVO → ACTIVO)", $id);
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Trabajador activado correctamente.']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Error activar trabajador: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e instanceof PDOException ? 'No se pudo activar el trabajador.' : $e->getMessage()]);
        }
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

        $validacion = validarInactivacionTrabajador($pdo, $id);
        echo json_encode([
            'success' => true,
            'puede_inactivar' => $validacion['puede'],
            'bloqueos' => $validacion['bloqueos'],
        ]);
        exit;
    }

    if ($action === 'insert' && isset($_POST['Guardar'])) {
        $campos = validarCamposTrabajador($_POST, false);
        if (!$campos['ok']) {
            header("Location: view.php?alert=4&msg=" . urlencode($campos['msg']));
            exit();
        }

        if (!validarPersonalActivo($pdo, $campos['personal_id'])) {
            header("Location: view.php?alert=4&msg=" . urlencode("El personal seleccionado no existe o no está activo."));
            exit();
        }
        if (personalYaAsignado($pdo, $campos['personal_id'])) {
            header("Location: view.php?alert=5");
            exit();
        }
        if (!validarCatalogosTrabajador($pdo, $campos['rol_id'], $campos['turno_id'])) {
            header("Location: view.php?alert=4&msg=" . urlencode("El rol o turno seleccionado no existe o no está activo."));
            exit();
        }

        try {
            $pdo->beginTransaction();

            $q = $pdo->prepare("
                INSERT INTO trabajadores
                    (usuario_id, personal_id, trabajador_estado, rol_id, turno_id, trabajador_costo_hora)
                VALUES
                    (:uid, :personal, 'ACTIVO', :rol, :turno, :costo)
                RETURNING trabajadores_id
            ");
            $q->execute([
                ':uid' => $idUsuario,
                ':personal' => $campos['personal_id'],
                ':rol' => $campos['rol_id'],
                ':turno' => $campos['turno_id'],
                ':costo' => $campos['trabajador_costo_hora'],
            ]);
            $trabajadorId = (int)$q->fetchColumn();

            $qNombre = $pdo->prepare("
                SELECT personal_nombre, personal_apellido
                FROM personal WHERE personal_id = :id
            ");
            $qNombre->execute([':id' => $campos['personal_id']]);
            $personal = $qNombre->fetch();
            $nombre = trim(($personal['personal_nombre'] ?? '') . ' ' . ($personal['personal_apellido'] ?? ''));

            bitacora($pdo, $idUsuario, 'ALTA', "Trabajador creado: {$nombre} (ID: {$trabajadorId})", $trabajadorId);

            $pdo->commit();
            header("Location: view.php?alert=1");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error insert trabajador: " . $e->getMessage());
            header("Location: view.php?alert=4&msg=" . urlencode("Error al registrar el trabajador."));
            exit();
        }
    }

    if ($action === 'update' && isset($_POST['Guardar'])) {
        $trabajadorId = isset($_POST['trabajadores_id']) ? (int)$_POST['trabajadores_id'] : 0;
        if ($trabajadorId <= 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("ID de trabajador inválido."));
            exit();
        }

        $campos = validarCamposTrabajador($_POST, true);
        if (!$campos['ok']) {
            header("Location: view.php?alert=4&msg=" . urlencode($campos['msg']));
            exit();
        }

        if (!validarPersonalParaTrabajador($pdo, $campos['personal_id'], $trabajadorId)) {
            header("Location: view.php?alert=4&msg=" . urlencode("El personal seleccionado no es válido o no está activo."));
            exit();
        }
        if ($campos['estado'] === 'ACTIVO' && personalYaAsignado($pdo, $campos['personal_id'], $trabajadorId)) {
            header("Location: view.php?alert=5");
            exit();
        }
        if (!validarCatalogosTrabajador($pdo, $campos['rol_id'], $campos['turno_id'])) {
            header("Location: view.php?alert=4&msg=" . urlencode("El rol o turno seleccionado no existe o no está activo."));
            exit();
        }

        try {
            $pdo->beginTransaction();

            $q = $pdo->prepare("
                UPDATE trabajadores
                SET personal_id = :personal,
                    rol_id = :rol,
                    turno_id = :turno,
                    trabajador_costo_hora = :costo,
                    trabajador_estado = :estado
                WHERE trabajadores_id = :id
            ");
            $q->execute([
                ':personal' => $campos['personal_id'],
                ':rol' => $campos['rol_id'],
                ':turno' => $campos['turno_id'],
                ':costo' => $campos['trabajador_costo_hora'],
                ':estado' => $campos['estado'],
                ':id' => $trabajadorId,
            ]);

            $qNombre = $pdo->prepare("
                SELECT personal_nombre, personal_apellido
                FROM personal WHERE personal_id = :id
            ");
            $qNombre->execute([':id' => $campos['personal_id']]);
            $personal = $qNombre->fetch();
            $nombre = trim(($personal['personal_nombre'] ?? '') . ' ' . ($personal['personal_apellido'] ?? ''));

            bitacora($pdo, $idUsuario, 'MODIFICACION', "Trabajador actualizado: {$nombre} (ID: {$trabajadorId})", $trabajadorId);

            $pdo->commit();
            header("Location: view.php?alert=2");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error update trabajador: " . $e->getMessage());
            header("Location: view.php?alert=4&msg=" . urlencode("Error al actualizar el trabajador."));
            exit();
        }
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        if (ob_get_length()) { ob_clean(); }

        $trabajadorId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $force = isset($_POST['force']) && (int)$_POST['force'] === 1;

        if ($trabajadorId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $qTrab = $pdo->prepare("
                SELECT trabajadores.trabajador_estado,
                       personal.personal_nombre, personal.personal_apellido
                FROM trabajadores
                JOIN personal ON personal.personal_id = trabajadores.personal_id
                WHERE trabajadores.trabajadores_id = :id
                LIMIT 1
            ");
            $qTrab->execute([':id' => $trabajadorId]);
            $trab = $qTrab->fetch();

            if (!$trab) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Trabajador no encontrado']);
                exit;
            }

            $estadoActual = normalizarEstadoTrabajador($trab['trabajador_estado'] ?? 'ACTIVO');
            if ($estadoActual !== 'ACTIVO') {
                $pdo->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'El trabajador ya está inactivo.',
                    'inactivado' => false,
                ]);
                exit;
            }

            $validacion = validarInactivacionTrabajador($pdo, $trabajadorId);
            if (!$validacion['puede'] && !$force) {
                $pdo->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => 'El trabajador tiene registros asociados en producción.',
                    'bloqueos' => $validacion['bloqueos'],
                    'can_force' => true,
                ]);
                exit;
            }

            $qUpdate = $pdo->prepare("UPDATE trabajadores SET trabajador_estado = 'INACTIVO' WHERE trabajadores_id = :id");
            $qUpdate->execute([':id' => $trabajadorId]);

            $descBitacora = "Trabajador #{$trabajadorId} ({$trab['personal_nombre']} {$trab['personal_apellido']}) inactivado";
            if (!$validacion['puede']) {
                $descBitacora .= ' (con vínculos: ' . implode(' ', $validacion['bloqueos']) . ')';
            }
            bitacora($pdo, $idUsuario, 'INACTIVACION', $descBitacora, $trabajadorId);

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => $force && !$validacion['puede']
                    ? 'Trabajador inactivado (con registros asociados en producción).'
                    : 'Trabajador inactivado correctamente.',
                'inactivado' => true,
            ]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error inactivar trabajador: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    header("Location: view.php?alert=4");
    exit();

} catch (PDOException $e) {
    error_log("Error conexión trabajadores: " . $e->getMessage());
    header("Location: view.php?alert=4");
    exit();
} catch (Exception $e) {
    error_log("Error general trabajadores: " . $e->getMessage());
    header("Location: view.php?alert=4&msg=" . urlencode($e->getMessage()));
    exit();
}
