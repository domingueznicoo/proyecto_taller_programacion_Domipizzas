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

$isEdit = isset($_GET['form_trabajador']) && ($_GET['form'] ?? '') == 'edit' && isset($_GET['id']);
$trabajadorData = null;


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
    SELECT personal_id,
           personal_nombre, personal_apellido
    FROM personal
    WHERE UPPER(TRIM(personal_estado)) = 'ACTIVO'
";
if ($isEdit) {
    $sqlPersonal .= " AND (
        personal_id NOT IN (
            SELECT personal_id FROM trabajadores
            WHERE UPPER(TRIM(trabajador_estado)) = 'ACTIVO'
              AND trabajadores_id <> :exclude_id
        )
        OR personal_id = :current_personal
    )";
    $stmtPersonal = $pdo->prepare($sqlPersonal . " ORDER BY personal_apellido, personal_nombre");
    $stmtPersonal->execute([
        ':exclude_id' => (int)$trabajadorData['trabajadores_id'],
        ':current_personal' => (int)$trabajadorData['personal_id'],
    ]);
    $listaPersonal = $stmtPersonal->fetchAll();

    $idsEnLista = array_column($listaPersonal, 'personal_id');
    if (!in_array((int)$trabajadorData['personal_id'], array_map('intval', $idsEnLista), true)) {
        $qActual = $pdo->prepare("
            SELECT personal_id,
                   personal_nombre, personal_apellido
            FROM personal WHERE personal_id = :id
        ");
        $qActual->execute([':id' => (int)$trabajadorData['personal_id']]);
        $actual = $qActual->fetch();
        if ($actual) {
            $actual['personal_apellido'] .= ' (INACTIVO)';
            array_unshift($listaPersonal, $actual);
        }
    }
} else {
    $sqlPersonal .= " AND personal_id NOT IN (
        SELECT personal_id FROM trabajadores
        WHERE UPPER(TRIM(trabajador_estado)) = 'ACTIVO'
    )";
    $stmtPersonal = $pdo->query($sqlPersonal . " ORDER BY personal_apellido, personal_nombre");
    $listaPersonal = $stmtPersonal->fetchAll();
}

$catalogos = [];
foreach (['rol', 'turno'] as $tabla) {
    $idActual = (int)($trabajadorData[$tabla . '_id'] ?? 0);
    $consulta = $pdo->prepare("SELECT {$tabla}_id, {$tabla}_descripcion, {$tabla}_estado FROM {$tabla}
        WHERE UPPER(TRIM({$tabla}_estado)) = 'ACTIVO' OR {$tabla}_id = :actual
        ORDER BY {$tabla}_descripcion");
    $consulta->execute([':actual' => $idActual]);
    $catalogos[$tabla] = $consulta->fetchAll();
}
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

            <?php if (empty($catalogos['rol']) || empty($catalogos['turno'])): ?>
                <div class="alert alert-warning">Debe registrar roles y turnos activos antes de guardar trabajadores.</div>
            <?php endif; ?>

            <form action="proses.php?act=<?= $isEdit ? 'update' : 'insert' ?>" method="POST" id="formTrabajador">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="trabajadores_id" value="<?= htmlspecialchars($trabajadorData['trabajadores_id']) ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="personal_id">Personal <span class="text-danger">*</span></label>
                            <select name="personal_id" id="personal_id" class="form-control" required <?= (empty($listaPersonal) && !$isEdit) ? 'disabled' : '' ?>>
                                <option value="">Seleccione el personal</option>
                                <?php foreach ($listaPersonal as $p): ?>
                                    <?php
                                    $selected = ($isEdit && (int)$trabajadorData['personal_id'] === (int)$p['personal_id']) ? 'selected' : '';
                                    ?>
                                    <option value="<?= (int)$p['personal_id'] ?>" <?= $selected ?>>
                                        <?= htmlspecialchars(trim($p['personal_nombre'] . ' ' . $p['personal_apellido'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Solo personal activo no asignado a otro trabajador activo</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <?php foreach (['rol' => 'Rol', 'turno' => 'Turno'] as $tabla => $etiqueta): ?>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="<?= $tabla ?>_id"><?= $etiqueta ?> <span class="text-danger">*</span></label>
                            <select name="<?= $tabla ?>_id" id="<?= $tabla ?>_id" class="form-control" required>
                                <option value="">Seleccione <?= strtolower($etiqueta) ?></option>
                                <?php foreach ($catalogos[$tabla] as $opcion): ?>
                                    <option value="<?= (int)$opcion[$tabla . '_id'] ?>"
                                        <?= $isEdit && (int)$trabajadorData[$tabla . '_id'] === (int)$opcion[$tabla . '_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($opcion[$tabla . '_descripcion']) ?><?= strtoupper(trim($opcion[$tabla . '_estado'])) !== 'ACTIVO' ? ' (INACTIVO)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="trabajador_costo_hora">Costo por hora (Gs.) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="trabajador_costo_hora" id="trabajador_costo_hora"
                                   min="0" max="2147483647" step="1" required
                                   value="<?= htmlspecialchars($trabajadorData['trabajador_costo_hora'] ?? '0') ?>">
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
                    <button type="submit" class="btn btn-primary" name="Guardar" <?= ((empty($listaPersonal) && !$isEdit) || empty($catalogos['rol']) || empty($catalogos['turno'])) ? 'disabled' : '' ?>>
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
