<?php
// Formulario de trabajadores (alta o edición).
require_once __DIR__ . '/../../../config/bootstrap.php';
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = loginUrl();
          </script>";
    exit();
}

require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/permissions.php';
check_permission('REFERENCIALES');

$isEdit = isset($_GET['form_trabajador']) && $_GET['form'] == 'edit' && isset($_GET['id']);
$trabajadorData = null;
$turnosPermitidos = ['MAÑANA', 'TARDE', 'NOCHE', 'ROTATIVO'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

if ($isEdit) {
    $query = $pdo->prepare("SELECT * FROM trabajadores WHERE trabajadores_id = :id");
    $query->execute([':id' => (int)$_GET['id']]);
    $trabajadorData = $query->fetch();

    if (!$trabajadorData) {
        echo "<script>alert('Trabajador no encontrado'); window.location.href='view.php';</script>";
        exit();
    }

    $estado = strtoupper(trim($trabajadorData['trabajador_estado'] ?? 'ACTIVO'));
    if ($estado === 'ANULADO') {
        $estado = 'INACTIVO';
    }
    $trabajadorData['trabajador_estado'] = $estado;
}

$sqlPersonal = "
    SELECT id_personal,
           TRIM(personal_nombre || ' ' || personal_apellido) AS nombre_completo
    FROM personal
    WHERE UPPER(TRIM(personal_estado)) = 'ACTIVO'
";
if ($isEdit) {
    $sqlPersonal .= " AND (
        id_personal NOT IN (
            SELECT id_personal FROM trabajadores
            WHERE UPPER(TRIM(trabajador_estado)) = 'ACTIVO'
              AND trabajadores_id <> :exclude_id
        )
        OR id_personal = :current_personal
    )";
    $stmtPersonal = $pdo->prepare($sqlPersonal . " ORDER BY personal_apellido, personal_nombre");
    $stmtPersonal->execute([
        ':exclude_id' => (int)$trabajadorData['trabajadores_id'],
        ':current_personal' => (int)$trabajadorData['id_personal'],
    ]);
    $listaPersonal = $stmtPersonal->fetchAll();

    $idsEnLista = array_column($listaPersonal, 'id_personal');
    if (!in_array((int)$trabajadorData['id_personal'], array_map('intval', $idsEnLista), true)) {
        $qActual = $pdo->prepare("
            SELECT id_personal,
                   TRIM(personal_nombre || ' ' || personal_apellido) AS nombre_completo
            FROM personal WHERE id_personal = :id
        ");
        $qActual->execute([':id' => (int)$trabajadorData['id_personal']]);
        $actual = $qActual->fetch();
        if ($actual) {
            $actual['nombre_completo'] .= ' (INACTIVO)';
            array_unshift($listaPersonal, $actual);
        }
    }
} else {
    $sqlPersonal .= " AND id_personal NOT IN (
        SELECT id_personal FROM trabajadores
        WHERE UPPER(TRIM(trabajador_estado)) = 'ACTIVO'
    )";
    $stmtPersonal = $pdo->query($sqlPersonal . " ORDER BY personal_apellido, personal_nombre");
    $listaPersonal = $stmtPersonal->fetchAll();
}

$listaEtapas = $pdo->query("
    SELECT etapa_id, etapa_descri
    FROM etapa_produccion
    WHERE UPPER(TRIM(etapa_estado)) = 'ACTIVA'
    ORDER BY etapa_descri ASC
")->fetchAll();
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= $isEdit ? 'Editar Trabajador' : 'Nuevo Trabajador' ?></h1>
        <a href="view.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?= $isEdit ? 'Editar' : 'Registrar' ?> Trabajador</h6>
        </div>
        <div class="card-body">
            <?php if (empty($listaPersonal) && !$isEdit): ?>
                <div class="alert alert-warning">
                    No hay personal activo disponible para asignar. Registre personal activo antes de crear trabajadores.
                </div>
            <?php endif; ?>

            <?php if (empty($listaEtapas)): ?>
                <div class="alert alert-warning">
                    No hay etapas de producción activas. Configure rutas en Etapas de producción antes de registrar trabajadores.
                </div>
            <?php endif; ?>

            <form action="proses.php?act=<?= $isEdit ? 'update' : 'insert' ?>" method="POST" id="formTrabajador">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="trabajadores_id" value="<?= htmlspecialchars($trabajadorData['trabajadores_id']) ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="id_personal">Personal <span class="text-danger">*</span></label>
                            <select name="id_personal" id="id_personal" class="form-control" required <?= (empty($listaPersonal) && !$isEdit) ? 'disabled' : '' ?>>
                                <option value="">Seleccione el personal</option>
                                <?php foreach ($listaPersonal as $p): ?>
                                    <?php
                                    $selected = ($isEdit && (int)$trabajadorData['id_personal'] === (int)$p['id_personal']) ? 'selected' : '';
                                    ?>
                                    <option value="<?= (int)$p['id_personal'] ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($p['nombre_completo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Solo personal activo no asignado a otro trabajador activo</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="trabajador_rol">Rol <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="trabajador_rol" name="trabajador_rol"
                                   value="<?= $isEdit ? htmlspecialchars($trabajadorData['trabajador_rol'] ?? '') : '' ?>"
                                   placeholder="Ej: Operario, Supervisor" required maxlength="50">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="trabajador_turno">Turno <span class="text-danger">*</span></label>
                            <select name="trabajador_turno" id="trabajador_turno" class="form-control" required>
                                <option value="">Seleccione turno</option>
                                <?php foreach ($turnosPermitidos as $turno): ?>
                                    <?php
                                    $sel = ($isEdit && ($trabajadorData['trabajador_turno'] ?? '') === $turno) ? 'selected' : '';
                                    ?>
                                    <option value="<?= $turno ?>" <?= $sel ?>><?= $turno ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="trabajador_costo_hora">Costo Hora (Gs.) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="trabajador_costo_hora" name="trabajador_costo_hora"
                                   value="<?= $isEdit ? htmlspecialchars($trabajadorData['trabajador_costo_hora'] ?? '0') : '0' ?>"
                                   min="0" step="1" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="id_etapa">Etapa de Producción Predeterminada <span class="text-danger">*</span></label>
                            <select name="id_etapa" id="id_etapa" class="form-control" required>
                                <option value="">Seleccione etapa</option>
                                <?php foreach ($listaEtapas as $etapa): ?>
                                    <?php
                                    $selEtapa = ($isEdit && (int)($trabajadorData['id_etapa'] ?? 0) === (int)$etapa['etapa_id']) ? 'selected' : '';
                                    ?>
                                    <option value="<?= (int)$etapa['etapa_id'] ?>" <?= $selEtapa ?>>
                                        <?= htmlspecialchars($etapa['etapa_descri']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <?php if ($isEdit): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="trabajador_estado">Estado</label>
                            <select name="trabajador_estado" id="trabajador_estado" class="form-control">
                                <option value="ACTIVO" <?= (($trabajadorData['trabajador_estado'] ?? 'ACTIVO') === 'ACTIVO') ? 'selected' : '' ?>>ACTIVO</option>
                                <option value="INACTIVO" <?= (($trabajadorData['trabajador_estado'] ?? '') === 'INACTIVO') ? 'selected' : '' ?>>INACTIVO</option>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary" name="Guardar" <?= ((empty($listaPersonal) && !$isEdit) || empty($listaEtapas)) ? 'disabled' : '' ?>>
                        <i class="fas fa-save"></i> Guardar
                    </button>
                    <a href="view.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
