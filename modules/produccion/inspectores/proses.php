<?php
// Procesa altas, ediciones y anulaciones de inspectores (?act=).
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/permissions.php';
check_permission('REFERENCIALES');

// Redirige al listado con código de alerta.
function redirectInspectores(int $alert, string $msg = ''): void
{
    $url = 'view.php?alert=' . $alert;
    if ($msg !== '') {
        $url .= '&msg=' . urlencode($msg);
    }
    header('Location: ' . $url);
    exit;
}

// Guarda en bitácora cambios de inspectores.
function bitacoraInspector(PDO $pdo, int $idUsuario, string $accion, string $desc, ?int $id): void
{
    try {
        $st = $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:u, 'Inspectores', :id, :acc, :d)
        ");
        $st->execute([
            ':u' => $idUsuario,
            ':id' => $id,
            ':acc' => strtoupper($accion),
            ':d' => $desc,
        ]);
    } catch (Throwable $e) {
        error_log('Bitácora inspectores: ' . $e->getMessage());
    }
}

$act = $_GET['act'] ?? $_POST['act'] ?? '';
$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    redirectInspectores(4, $e->getMessage());
}

if ($act === 'insert') {
    // Alta de inspector: vincula personal activo
    $idPersonal = (int)($_POST['id_personal'] ?? 0);
    if ($idPersonal <= 0) {
        redirectInspectores(4, 'Debe seleccionar personal.');
    }
    $dup = $pdo->prepare("
        SELECT 1 FROM inspectores
        WHERE id_personal = :p AND UPPER(TRIM(inspector_estado)) = 'ACTIVO'
    ");
    $dup->execute([':p' => $idPersonal]);
    if ($dup->fetchColumn()) {
        redirectInspectores(5);
    }
    $st = $pdo->prepare("
        INSERT INTO inspectores (id_personal, inspector_estado, id_usuario)
        VALUES (:p, 'ACTIVO', :u) RETURNING id_inspectores
    ");
    $st->execute([':p' => $idPersonal, ':u' => $idUsuario]);
    $newId = (int)$st->fetchColumn();
    bitacoraInspector($pdo, $idUsuario, 'INSERCION', "Inspector #{$newId} registrado", $newId);
    redirectInspectores(1);
}

if ($act === 'update') {
    // Edición de personal y estado del inspector
    $id = (int)($_POST['id_inspectores'] ?? 0);
    $idPersonal = (int)($_POST['id_personal'] ?? 0);
    $estado = strtoupper(trim($_POST['inspector_estado'] ?? 'ACTIVO'));
    if ($id <= 0 || $idPersonal <= 0) {
        redirectInspectores(4, 'Datos incompletos.');
    }
    if (!in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
        redirectInspectores(4, 'Estado inválido.');
    }
    if ($estado === 'ACTIVO') {
        $dup = $pdo->prepare("
            SELECT 1 FROM inspectores
            WHERE id_personal = :p AND UPPER(TRIM(inspector_estado)) = 'ACTIVO'
              AND id_inspectores <> :id
        ");
        $dup->execute([':p' => $idPersonal, ':id' => $id]);
        if ($dup->fetchColumn()) {
            redirectInspectores(5);
        }
    }
    $st = $pdo->prepare("
        UPDATE inspectores
        SET id_personal = :p, inspector_estado = :e
        WHERE id_inspectores = :id
    ");
    $st->execute([':p' => $idPersonal, ':e' => $estado, ':id' => $id]);
    bitacoraInspector($pdo, $idUsuario, 'MODIFICACION', "Inspector #{$id} actualizado", $id);
    redirectInspectores(2);
}

if ($act === 'inactivate') {
    // Inactiva inspector si no tiene controles de calidad asociados
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        redirectInspectores(4);
    }
    $uso = $pdo->prepare('SELECT 1 FROM control_calidad_produccion WHERE id_inspectores = :id LIMIT 1');
    $uso->execute([':id' => $id]);
    if ($uso->fetchColumn()) {
        redirectInspectores(4, 'No se puede inactivar: tiene inspecciones de calidad asociadas.');
    }
    $st = $pdo->prepare("UPDATE inspectores SET inspector_estado = 'INACTIVO' WHERE id_inspectores = :id");
    $st->execute([':id' => $id]);
    bitacoraInspector($pdo, $idUsuario, 'MODIFICACION', "Inspector #{$id} inactivado", $id);
    redirectInspectores(3);
}

redirectInspectores(4);
