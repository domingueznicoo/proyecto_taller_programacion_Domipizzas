<?php
// Formulario de inspectores (alta o edición).
require_once __DIR__ . '/../../../config/bootstrap.php';
if (empty($_SESSION['username'])) {
    echo "<script>alert('Sesión inválida'); window.location.href = loginUrl();</script>";
    exit();
}
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/permissions.php';
check_permission('REFERENCIALES');

$isEdit = isset($_GET['form_inspector'], $_GET['form'], $_GET['id'])
    && $_GET['form'] === 'edit'
    && (int)$_GET['id'] > 0;

$inspectorData = null;
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}

if ($isEdit) {
    $st = $pdo->prepare('SELECT * FROM inspectores WHERE id_inspectores = :id');
    $st->execute([':id' => (int)$_GET['id']]);
    $inspectorData = $st->fetch();
    if (!$inspectorData) {
        echo "<script>alert('Inspector no encontrado'); window.location.href='view.php';</script>";
        exit();
    }
}

$consulta = $pdo->prepare("SELECT trabajadores.trabajadores_id, trabajadores.trabajador_estado,
        personal.personal_nombre, personal.personal_apellido, personal.personal_estado
    FROM trabajadores JOIN personal ON personal.personal_id = trabajadores.personal_id
    WHERE (UPPER(TRIM(trabajadores.trabajador_estado)) = 'ACTIVO'
        AND UPPER(TRIM(personal.personal_estado)) = 'ACTIVO'
        AND NOT EXISTS (SELECT 1 FROM inspectores WHERE inspectores.trabajadores_id = trabajadores.trabajadores_id
            AND UPPER(TRIM(inspectores.inspector_estado)) = 'ACTIVO' AND inspectores.id_inspectores <> :exclude))
        OR trabajadores.trabajadores_id = :actual
    ORDER BY personal.personal_apellido, personal.personal_nombre");
$consulta->execute([
    ':exclude' => (int)($inspectorData['id_inspectores'] ?? 0),
    ':actual' => (int)($inspectorData['trabajadores_id'] ?? 0),
]);
$listaTrabajadores = $consulta->fetchAll();
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= $isEdit ? 'Editar Inspector' : 'Nuevo Inspector' ?></h1>
        <a href="view.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>
    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="proses.php?act=<?= $isEdit ? 'update' : 'insert' ?>" method="POST">
                <?php if ($isEdit): ?>
                <input type="hidden" name="id_inspectores" value="<?= (int)$inspectorData['id_inspectores'] ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="trabajadores_id">Trabajador <span class="text-danger">*</span></label>
                    <select name="trabajadores_id" id="trabajadores_id" class="form-control" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($listaTrabajadores as $p): ?>
                        <option value="<?= (int)$p['trabajadores_id'] ?>"
                            <?= $isEdit && (int)$inspectorData['trabajadores_id'] === (int)$p['trabajadores_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(trim($p['personal_nombre'] . ' ' . $p['personal_apellido'])) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($isEdit): ?>
                <div class="form-group">
                    <label for="inspector_estado">Estado</label>
                    <select name="inspector_estado" id="inspector_estado" class="form-control">
                        <?php
                        $est = strtoupper(trim($inspectorData['inspector_estado'] ?? 'ACTIVO'));
                        foreach (['ACTIVO', 'INACTIVO'] as $op) {
                            $sel = $est === $op ? 'selected' : '';
                            echo "<option value='{$op}' {$sel}>{$op}</option>";
                        }
                        ?>
                    </select>
                </div>
                <?php endif; ?>
                <?php if (!$listaTrabajadores): ?>
                <div class="alert alert-warning">No hay trabajadores activos disponibles. Registre uno antes de crear el inspector.</div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary" <?= !$listaTrabajadores ? 'disabled' : '' ?>>Guardar</button>
                <a href="view.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>
