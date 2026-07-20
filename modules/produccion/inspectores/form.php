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

$sqlPersonal = "
    SELECT id_personal, TRIM(personal_nombre || ' ' || personal_apellido) AS nombre_completo
    FROM personal
    WHERE UPPER(TRIM(personal_estado)) = 'ACTIVO'
";
if ($isEdit) {
    $sqlPersonal .= " AND (
        id_personal NOT IN (
            SELECT id_personal FROM inspectores
            WHERE UPPER(TRIM(inspector_estado)) = 'ACTIVO'
              AND id_inspectores <> :exclude
        ) OR id_personal = :current
    )";
    $stP = $pdo->prepare($sqlPersonal . ' ORDER BY personal_apellido, personal_nombre');
    $stP->execute([
        ':exclude' => (int)$inspectorData['id_inspectores'],
        ':current' => (int)$inspectorData['id_personal'],
    ]);
} else {
    $sqlPersonal .= " AND id_personal NOT IN (
        SELECT id_personal FROM inspectores WHERE UPPER(TRIM(inspector_estado)) = 'ACTIVO'
    )";
    $stP = $pdo->query($sqlPersonal . ' ORDER BY personal_apellido, personal_nombre');
}
$listaPersonal = $stP->fetchAll();
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
                    <label for="id_personal">Personal <span class="text-danger">*</span></label>
                    <select name="id_personal" id="id_personal" class="form-control" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($listaPersonal as $p): ?>
                        <option value="<?= (int)$p['id_personal'] ?>"
                            <?= $isEdit && (int)$inspectorData['id_personal'] === (int)$p['id_personal'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nombre_completo']) ?>
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
                <button type="submit" class="btn btn-primary">Guardar</button>
                <a href="view.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>
