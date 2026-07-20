<?php
// Procesa altas, ediciones y anulaciones de equipos de trabajo (?act=).
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
require_once __DIR__ . '/equipos_helper.php';

if (empty($_SESSION['username'])) {
    echo "<script>alert('Sesión inválida'); window.location.href=loginUrl();</script>";
    exit;
}

if (!check_permission('EQUIPOS_PRODUCCION', false)) {
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

    [$usuarioId, $sucursalId] = resolverUsuarioEquipo($pdo);
    $fechaHoy = trim((string)($_POST['equipo_fecha'] ?? date('Y-m-d')));

    if ($action === 'insert' && isset($_POST['Guardar'])) {
        $ordenId = (int)($_POST['orden_id'] ?? 0);
        $descri = trim((string)($_POST['equipo_descri'] ?? ''));
        $etapaId = (int)($_POST['etapa_id'] ?? 0);
        $payload = json_decode($_POST['miembros'] ?? '[]', true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $miembros = parseMiembrosEquipo($payload);

        if ($usuarioId <= 0 || $ordenId <= 0 || $etapaId <= 0 || empty($miembros)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Complete OP, etapa y al menos un trabajador.'));
            exit;
        }

        $pdo->beginTransaction();

        $stOp = $pdo->prepare('SELECT orden_prod_estado FROM orden_produccion WHERE orden_id = :id FOR UPDATE');
        $stOp->execute([':id' => $ordenId]);
        $estOp = strtoupper(trim((string)$stOp->fetchColumn()));
        if (!opPermiteAltaEquipo($estOp)) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('La OP no está disponible para asignar equipos.'));
            exit;
        }

        $etapa = resolverEtapaOrden($pdo, $ordenId, $etapaId);
        if (!$etapa) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('Etapa no válida para los productos de esta OP.'));
            exit;
        }
        $etapaNombre = trim((string)$etapa['etapa_nombre']);

        if ($descri === '') {
            $descri = sprintf('Eq. OP-%d / %s', $ordenId, substr($etapaNombre, 0, 15));
        }
        $descri = substr($descri, 0, 30);

        $errTrab = validarTrabajadoresActivos($pdo, $miembros);
        if ($errTrab) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode($errTrab));
            exit;
        }

        $errEtapa = validarTrabajadoresEtapa($pdo, $miembros, $etapaId, $etapaNombre);
        if ($errEtapa) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode($errEtapa));
            exit;
        }

        $cols = 'equipo_descri, id_usuario, equipo_estado, orden_id, equipo_fecha, id_sucursal';
        $vals = ':d, :u, :est, :o, :f, :s';
        $params = [
            ':d' => $descri,
            ':u' => $usuarioId,
            ':est' => 'PENDIENTE',
            ':o' => $ordenId,
            ':f' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHoy) ? $fechaHoy : date('Y-m-d'),
            ':s' => $sucursalId > 0 ? $sucursalId : null,
        ];
        if (columnaEquipoExiste($pdo, 'etapa_id')) {
            $cols .= ', etapa_id, etapa_nombre';
            $vals .= ', :eid, :enom';
            $params[':eid'] = $etapaId;
            $params[':enom'] = substr($etapaNombre, 0, 50);
        }

        $ins = $pdo->prepare("INSERT INTO equipos_produccion ({$cols}) VALUES ({$vals}) RETURNING equipo_id");
        $ins->execute($params);
        $equipoId = (int)$ins->fetchColumn();

        $insDet = $pdo->prepare("
            INSERT INTO equipo_detalle (equipo_id, trabajadores_id, tarea_rol)
            VALUES (:eid, :tid, :rol)
        ");
        foreach ($miembros as $m) {
            $insDet->execute([
                ':eid' => $equipoId,
                ':tid' => $m['trabajadores_id'],
                ':rol' => substr($etapaNombre, 0, 100),
            ]);
        }

        bitacoraEquipo($pdo, $usuarioId, 'ALTA',
            "Equipo #{$equipoId} OP #{$ordenId} etapa {$etapaNombre} (" . count($miembros) . ' trab.)', $equipoId);

        $pdo->commit();
        header('Location: view.php?alert=1');
        exit;
    }

    if ($action === 'update' && isset($_POST['Guardar'])) {
        $equipoId = (int)($_POST['equipo_id'] ?? 0);
        $descri = trim((string)($_POST['equipo_descri'] ?? ''));
        $payload = json_decode($_POST['miembros'] ?? '[]', true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $miembros = parseMiembrosEquipo($payload);

        if ($equipoId <= 0 || $usuarioId <= 0 || $descri === '' || empty($miembros)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Complete nombre y al menos un integrante.'));
            exit;
        }

        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT equipo_estado, orden_id'
            . (columnaEquipoExiste($pdo, 'etapa_id') ? ', etapa_id, etapa_nombre' : '')
            . ' FROM equipos_produccion WHERE equipo_id = :id FOR UPDATE');
        $st->execute([':id' => $equipoId]);
        $cab = $st->fetch();
        if (!$cab || strtoupper(trim((string)$cab['equipo_estado'])) !== 'PENDIENTE') {
            $pdo->rollBack();
            header('Location: view.php?alert=5&msg=' . urlencode('Solo se pueden editar equipos en estado PENDIENTE.'));
            exit;
        }

        $etapaEquipoId = (int)($cab['etapa_id'] ?? 0);
        $etapaNombreEquipo = trim((string)($cab['etapa_nombre'] ?? ''));

        $stOp = $pdo->prepare('SELECT orden_prod_estado FROM orden_produccion WHERE orden_id = :id');
        $stOp->execute([':id' => (int)$cab['orden_id']]);
        $estOp = strtoupper(trim((string)$stOp->fetchColumn()));
        if (!opPermiteEdicionEquipo($estOp)) {
            $pdo->rollBack();
            header('Location: view.php?alert=5&msg=' . urlencode(
                "No se puede editar: la OP está en estado {$estOp}."
            ));
            exit;
        }

        $errTrab = validarTrabajadoresActivos($pdo, $miembros);
        if ($errTrab) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode($errTrab));
            exit;
        }

        if ($etapaEquipoId > 0) {
            $errEtapa = validarTrabajadoresEtapa($pdo, $miembros, $etapaEquipoId, $etapaNombreEquipo);
            if ($errEtapa) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode($errEtapa));
                exit;
            }
        }

        $pdo->prepare('UPDATE equipos_produccion SET equipo_descri = :d, equipo_fecha = :f WHERE equipo_id = :id')
            ->execute([
                ':d' => substr($descri, 0, 30),
                ':f' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHoy) ? $fechaHoy : date('Y-m-d'),
                ':id' => $equipoId,
            ]);

        $pdo->prepare('DELETE FROM equipo_detalle WHERE equipo_id = :id')->execute([':id' => $equipoId]);
        $insDet = $pdo->prepare("INSERT INTO equipo_detalle (equipo_id, trabajadores_id, tarea_rol) VALUES (:eid, :tid, :rol)");
        $rolDetalle = $etapaNombreEquipo !== '' ? substr($etapaNombreEquipo, 0, 100) : '';
        foreach ($miembros as $m) {
            $insDet->execute([
                ':eid' => $equipoId,
                ':tid' => $m['trabajadores_id'],
                ':rol' => $rolDetalle !== '' ? $rolDetalle : $m['tarea_rol'],
            ]);
        }

        bitacoraEquipo($pdo, $usuarioId, 'MODIFICACION', "Equipo #{$equipoId} actualizado", $equipoId);

        $pdo->commit();
        header('Location: view.php?alert=2');
        exit;
    }

    header('Location: view.php?alert=4');
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('equipos_produccion/proses.php: ' . $e->getMessage());
    header('Location: view.php?alert=4&msg=' . urlencode('Error: ' . $e->getMessage()));
    exit;
}
