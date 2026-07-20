<?php
// Procesa altas, ediciones y anulaciones de trabajadores (?act=).
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/permissions.php';
check_permission('REFERENCIALES');

const TURNOS_PERMITIDOS = ['MAÑANA', 'TARDE', 'NOCHE', 'ROTATIVO'];

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
        'equipo_detalle' => 'equipos de trabajo por etapa',
        'costo_detalle_produccion' => 'costos de producción (mano de obra)',
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
        WHERE id_personal = :id AND UPPER(TRIM(personal_estado)) = 'ACTIVO'
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
            WHERE trabajadores_id = :id AND id_personal = :personal
            LIMIT 1
        ");
        $q->execute([':id' => $trabajadorId, ':personal' => $idPersonal]);
        return (bool)$q->fetchColumn();
    }
    return false;
}

function validarEtapaActiva(PDO $pdo, int $idEtapa): bool {
    $q = $pdo->prepare("
        SELECT 1 FROM etapa_produccion
        WHERE etapa_id = :id AND UPPER(TRIM(etapa_estado)) = 'ACTIVA'
        LIMIT 1
    ");
    $q->execute([':id' => $idEtapa]);
    return (bool)$q->fetchColumn();
}

function personalYaAsignado(PDO $pdo, int $idPersonal, int $excludeId = 0): bool {
    $sql = "
        SELECT 1 FROM trabajadores
        WHERE id_personal = :personal
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
    $idPersonal = isset($post['id_personal']) ? (int)$post['id_personal'] : 0;
    $rol = normalize_text($post['trabajador_rol'] ?? '');
    $turno = normalize_text($post['trabajador_turno'] ?? '');
    $costo = isset($post['trabajador_costo_hora']) ? (float)$post['trabajador_costo_hora'] : -1;
    $idEtapa = isset($post['id_etapa']) ? (int)$post['id_etapa'] : 0;

    if ($idPersonal <= 0) {
        return ['ok' => false, 'msg' => 'Debe seleccionar el personal.'];
    }
    if ($rol === '') {
        return ['ok' => false, 'msg' => 'El rol es obligatorio.'];
    }
    if ($turno === '' || !in_array($turno, TURNOS_PERMITIDOS, true)) {
        return ['ok' => false, 'msg' => 'Debe seleccionar un turno válido.'];
    }
    if ($costo < 0) {
        return ['ok' => false, 'msg' => 'El costo hora debe ser mayor o igual a cero.'];
    }
    if ($idEtapa <= 0) {
        return ['ok' => false, 'msg' => 'Debe seleccionar la etapa de producción predeterminada.'];
    }

    return [
        'ok' => true,
        'id_personal' => $idPersonal,
        'rol' => $rol,
        'turno' => $turno,
        'costo' => $costo,
        'id_etapa' => $idEtapa,
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

        if (!validarPersonalActivo($pdo, $campos['id_personal'])) {
            header("Location: view.php?alert=4&msg=" . urlencode("El personal seleccionado no existe o no está activo."));
            exit();
        }
        if (personalYaAsignado($pdo, $campos['id_personal'])) {
            header("Location: view.php?alert=5");
            exit();
        }
        if (!validarEtapaActiva($pdo, $campos['id_etapa'])) {
            header("Location: view.php?alert=4&msg=" . urlencode("La etapa seleccionada no es válida o no está activa."));
            exit();
        }

        try {
            $pdo->beginTransaction();

            $q = $pdo->prepare("
                INSERT INTO trabajadores
                    (id_usuario, id_personal, trabajador_estado, trabajador_rol, trabajador_turno, trabajador_costo_hora, id_etapa)
                VALUES
                    (:uid, :personal, 'ACTIVO', :rol, :turno, :costo, :etapa)
                RETURNING trabajadores_id
            ");
            $q->execute([
                ':uid' => $idUsuario,
                ':personal' => $campos['id_personal'],
                ':rol' => $campos['rol'],
                ':turno' => $campos['turno'],
                ':costo' => $campos['costo'],
                ':etapa' => $campos['id_etapa'],
            ]);
            $trabajadorId = (int)$q->fetchColumn();

            $qNombre = $pdo->prepare("
                SELECT TRIM(personal_nombre || ' ' || personal_apellido) AS nombre
                FROM personal WHERE id_personal = :id
            ");
            $qNombre->execute([':id' => $campos['id_personal']]);
            $nombre = $qNombre->fetchColumn() ?: "Personal #{$campos['id_personal']}";

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

        if (!validarPersonalParaTrabajador($pdo, $campos['id_personal'], $trabajadorId)) {
            header("Location: view.php?alert=4&msg=" . urlencode("El personal seleccionado no es válido o no está activo."));
            exit();
        }
        if ($campos['estado'] === 'ACTIVO' && personalYaAsignado($pdo, $campos['id_personal'], $trabajadorId)) {
            header("Location: view.php?alert=5");
            exit();
        }
        if (!validarEtapaActiva($pdo, $campos['id_etapa'])) {
            header("Location: view.php?alert=4&msg=" . urlencode("La etapa seleccionada no es válida o no está activa."));
            exit();
        }

        try {
            $pdo->beginTransaction();

            $q = $pdo->prepare("
                UPDATE trabajadores
                SET id_personal = :personal,
                    trabajador_rol = :rol,
                    trabajador_turno = :turno,
                    trabajador_costo_hora = :costo,
                    id_etapa = :etapa,
                    trabajador_estado = :estado
                WHERE trabajadores_id = :id
            ");
            $q->execute([
                ':personal' => $campos['id_personal'],
                ':rol' => $campos['rol'],
                ':turno' => $campos['turno'],
                ':costo' => $campos['costo'],
                ':etapa' => $campos['id_etapa'],
                ':estado' => $campos['estado'],
                ':id' => $trabajadorId,
            ]);

            $qNombre = $pdo->prepare("
                SELECT TRIM(personal_nombre || ' ' || personal_apellido) AS nombre
                FROM personal WHERE id_personal = :id
            ");
            $qNombre->execute([':id' => $campos['id_personal']]);
            $nombre = $qNombre->fetchColumn() ?: "Personal #{$campos['id_personal']}";

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
                SELECT t.trabajador_estado,
                       TRIM(p.personal_nombre || ' ' || p.personal_apellido) AS nombre
                FROM trabajadores t
                JOIN personal p ON p.id_personal = t.id_personal
                WHERE t.trabajadores_id = :id
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

            $descBitacora = "Trabajador #{$trabajadorId} ({$trab['nombre']}) inactivado";
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
