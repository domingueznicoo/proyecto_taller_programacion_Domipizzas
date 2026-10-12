<?php
// Altas, edición y cambios de estado de inspectores.
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();
if (empty($_SESSION['username'])) {
    header('Location: ' . loginUrl());
    exit;
}
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/permissions.php';
check_permission('REFERENCIALES');

function redirectInspectores(int $alert, string $msg = ''): void {
    header('Location: view.php?alert=' . $alert . ($msg !== '' ? '&msg=' . urlencode($msg) : ''));
    exit;
}

function bitacoraInspector(PDO $pdo, int $idUsuario, string $accion, string $desc, int $id): void {
    $st = $pdo->prepare("INSERT INTO bitacora
        (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
        VALUES (:u, 'Inspectores', :id, :acc, :d)");
    $st->execute([':u' => $idUsuario, ':id' => $id, ':acc' => $accion, ':d' => $desc]);
}

function validarTrabajadorInspector(PDO $pdo, int $trabajadorId, int $inspectorId, bool $activo): void {
    $st = $pdo->prepare("SELECT trabajadores.trabajador_estado, personal.personal_estado
        FROM trabajadores JOIN personal ON personal.personal_id = trabajadores.personal_id
        WHERE trabajadores.trabajadores_id = :id FOR UPDATE OF trabajadores");
    $st->execute([':id' => $trabajadorId]);
    $trabajador = $st->fetch();
    if (!$trabajador) throw new Exception('El trabajador seleccionado no existe.');
    if (!$activo) return;
    if (strtoupper(trim($trabajador['trabajador_estado'])) !== 'ACTIVO' ||
        strtoupper(trim($trabajador['personal_estado'])) !== 'ACTIVO') {
        throw new Exception('El trabajador y su personal deben estar activos.');
    }
    $st = $pdo->prepare("SELECT 1 FROM inspectores WHERE trabajadores_id = :trabajador
        AND UPPER(TRIM(inspector_estado)) = 'ACTIVO' AND id_inspectores <> :id LIMIT 1");
    $st->execute([':trabajador' => $trabajadorId, ':id' => $inspectorId]);
    if ($st->fetchColumn()) throw new Exception('Ya existe un inspector activo para ese trabajador.');
}

function validarInactivacionInspector(PDO $pdo, int $id): void {
    foreach (['control_detalle_produccion', 'mermas_detalle'] as $tabla) {
        $st = $pdo->prepare("SELECT 1 FROM {$tabla} WHERE id_inspectores = :id LIMIT 1");
        $st->execute([':id' => $id]);
        if ($st->fetchColumn()) throw new Exception('No se puede desactivar: tiene controles de producción o mermas asociados.');
    }
}

$pdo = null;
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirectInspectores(4, 'Método no permitido.');
    $act = $_GET['act'] ?? '';
    if (!in_array($act, ['insert', 'update', 'activate', 'inactivate'], true)) redirectInspectores(4);
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$database;", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $st = $pdo->prepare('SELECT usuario_id FROM usuario WHERE username = :username');
    $st->execute([':username' => $_SESSION['username']]);
    $idUsuario = (int)$st->fetchColumn();
    if ($idUsuario <= 0) throw new Exception('Usuario de sesión no encontrado.');
    $pdo->beginTransaction();
    $id = (int)($_POST['id_inspectores'] ?? 0);
    $anterior = null;
    if ($act !== 'insert') {
        $st = $pdo->prepare('SELECT * FROM inspectores WHERE id_inspectores = :id FOR UPDATE');
        $st->execute([':id' => $id]);
        $anterior = $st->fetch();
        if (!$anterior) throw new Exception('Inspector no encontrado.');
    }
    $trabajadorId = (int)($_POST['trabajadores_id'] ?? ($anterior['trabajadores_id'] ?? 0));
    $estado = $act === 'insert' || $act === 'activate' ? 'ACTIVO' :
        ($act === 'inactivate' ? 'INACTIVO' : strtoupper(trim($_POST['inspector_estado'] ?? '')));
    if (!in_array($estado, ['ACTIVO', 'INACTIVO'], true)) throw new Exception('Estado inválido.');
    validarTrabajadorInspector($pdo, $trabajadorId, $id, $estado === 'ACTIVO');
    $estadoAnterior = strtoupper(trim($anterior['inspector_estado'] ?? ''));
    if ($estado === 'INACTIVO' && $estadoAnterior === 'ACTIVO') validarInactivacionInspector($pdo, $id);
    if ($act === 'insert') {
        $st = $pdo->prepare("INSERT INTO inspectores (trabajadores_id, inspector_estado, usuario_id)
            VALUES (:trabajador, 'ACTIVO', :usuario) RETURNING id_inspectores");
        $st->execute([':trabajador' => $trabajadorId, ':usuario' => $idUsuario]);
        $id = (int)$st->fetchColumn();
        bitacoraInspector($pdo, $idUsuario, 'ALTA', "Inspector #{$id} registrado; trabajador #{$trabajadorId}", $id);
    } else {
        $st = $pdo->prepare('UPDATE inspectores SET trabajadores_id = :trabajador, inspector_estado = :estado WHERE id_inspectores = :id');
        $st->execute([':trabajador' => $trabajadorId, ':estado' => $estado, ':id' => $id]);
        if ($act === 'update' || $estadoAnterior !== $estado) {
            $accion = $estadoAnterior === 'ACTIVO' && $estado === 'INACTIVO' ? 'INACTIVACION' : 'MODIFICACION';
            $descripcion = $estadoAnterior !== 'ACTIVO' && $estado === 'ACTIVO' ? 'reactivado' :
                ($accion === 'INACTIVACION' ? 'desactivado' : 'actualizado');
            bitacoraInspector($pdo, $idUsuario, $accion, "Inspector #{$id} {$descripcion}; trabajador #{$trabajadorId}", $id);
        }
    }
    $pdo->commit();
    redirectInspectores($act === 'insert' ? 1 : ($act === 'inactivate' ? 3 : ($act === 'activate' ? 6 : 2)));
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Inspectores: ' . $e->getMessage());
    redirectInspectores(4, $e instanceof PDOException ? 'No se pudo guardar el inspector. Revise el registro de errores del servidor.' : $e->getMessage());
}
