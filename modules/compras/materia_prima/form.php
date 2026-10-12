<?php
// Formulario de materias primas (alta o edición).
require_once __DIR__ . '/../../../config/bootstrap.php';
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = loginUrl();
          </script>";
    exit();
}

require_once PROJECT_ROOT . '/config/database.php';

// Incluir sistema de permisos y verificar acceso
require_once PROJECT_ROOT . '/config/permissions.php';
check_permission(['COMPRAS', 'REFERENCIALES']);

$isEdit = isset($_GET['form_materia_prima']) && ($_GET['form'] ?? '') == 'edit' && isset($_GET['id']);
$materiaPrimaData = null;

if ($isEdit) {
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        $query = $pdo->prepare("
            SELECT mp.*
            FROM materia_prima mp
            WHERE mp.mp_id = :id
        ");
        $query->execute([':id' => (int)$_GET['id']]);
        $materiaPrimaData = $query->fetch();
        
        if (!$materiaPrimaData) {
            echo "<script>alert('Materia prima no encontrada'); window.location.href='view.php';</script>";
            exit();
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= $isEdit ? 'Editar Materia Prima' : 'Nueva Materia Prima' ?></h1>
        <a href="view.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?= $isEdit ? 'Editar' : 'Registrar' ?> Materia Prima</h6>
        </div>
        <div class="card-body">
            <form action="proses.php?act=<?= $isEdit ? 'update' : 'insert' ?>" method="POST" id="formMateriaPrima">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="mp_id" value="<?= htmlspecialchars($materiaPrimaData['mp_id']) ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="mp_descripcion">Descripción <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="mp_descripcion" name="mp_descripcion" 
                                   value="<?= $isEdit ? htmlspecialchars($materiaPrimaData['mp_descripcion'] ?? '') : '' ?>" 
                                   placeholder="Ingrese descripción de la materia prima" required maxlength="30">
                            <small class="form-text text-muted">Descripción de la materia prima (máximo 30 caracteres)</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="unidad_medida_id">Unidad de Medida <span class="text-danger">*</span></label>
                            <select name="unidad_medida_id" id="unidad_medida_id" class="form-control" required>
                                <option value="">Seleccione una unidad de medida</option>
                                <?php
                                try {
                                    $query_um = $pdo->query("SELECT unidad_medida_id, unidad_medida_nombre FROM unidad_medida ORDER BY unidad_medida_nombre ASC");
                                    while ($um = $query_um->fetch()) {
                                        $selected = ($isEdit && ($materiaPrimaData['unidad_medida_id'] ?? null) == $um['unidad_medida_id']) ? 'selected' : '';
                                        echo "<option value=\"{$um['unidad_medida_id']}\" {$selected}>{$um['unidad_medida_nombre']}</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option value=''>Error al cargar unidades</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="tipo_iva_id">Tipo de IVA <span class="text-danger">*</span></label>
                            <select name="tipo_iva_id" id="tipo_iva_id" class="form-control" required>
                                <option value="">Seleccione el tipo de IVA</option>
                                <?php
                                try {
                                    $query_iva = $pdo->query("SELECT tipo_iva_id, tipo_iva_nombre FROM tipo_iva ORDER BY tipo_iva_id ASC");
                                    while ($iva = $query_iva->fetch()) {
                                        $selected = ($isEdit && ($materiaPrimaData['tipo_iva_id'] ?? null) == $iva['tipo_iva_id']) ? 'selected' : '';
                                        echo "<option value=\"{$iva['tipo_iva_id']}\" {$selected}>{$iva['tipo_iva_nombre']}</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option value=''>Error al cargar tipos de IVA</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="deposito_id">Depósito <span class="text-danger">*</span></label>
                            <select name="deposito_id" id="deposito_id" class="form-control" required>
                                <option value="">Seleccione un depósito</option>
                                <?php
                                try {
                                    $query_dep = $pdo->query("SELECT deposito_id, deposito_nombre FROM deposito ORDER BY deposito_nombre ASC");
                                    while ($dep = $query_dep->fetch()) {
                                        $selected = '';
                                        if ($isEdit) {
                                            $depositoId = $materiaPrimaData['deposito_id'] ?? null;
                                            $selected = ($depositoId == $dep['deposito_id']) ? 'selected' : '';
                                        }
                                        echo "<option value=\"{$dep['deposito_id']}\" {$selected}>{$dep['deposito_nombre']}</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option value=''>Error al cargar depósitos</option>";
                                }
                                ?>
                            </select>
                            <small class="form-text text-muted">Depósito donde se gestionará el stock de la materia prima</small>
                        </div>
                    </div>

                    <?php if ($isEdit): ?>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="mp_estado">Estado</label>
                            <select name="mp_estado" id="mp_estado" class="form-control">
                                <option value="ACTIVO" <?= (($materiaPrimaData['mp_estado'] ?? 'ACTIVO') === 'ACTIVO') ? 'selected' : '' ?>>ACTIVO</option>
                                <option value="INACTIVO" <?= (($materiaPrimaData['mp_estado'] ?? '') === 'INACTIVO') ? 'selected' : '' ?>>INACTIVO</option>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="marca_id">Marca <span class="text-danger">*</span></label>
                        <select name="marca_id" id="marca_id" class="form-control" required>
                            <option value="">Seleccione una marca</option>
                            <?php foreach ($pdo->query("SELECT marca_id, marca_descrip FROM marca ORDER BY marca_descrip") as $marca): ?>
                                <option value="<?= (int)$marca['marca_id'] ?>" <?= $isEdit && (int)$materiaPrimaData['marca_id'] === (int)$marca['marca_id'] ? 'selected' : '' ?>><?= htmlspecialchars($marca['marca_descrip']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="mp_precio_referencia">Precio de referencia <span class="text-danger">*</span></label>
                        <input type="number" name="mp_precio_referencia" id="mp_precio_referencia" class="form-control"
                            min="0" step="0.01" required value="<?= $isEdit ? htmlspecialchars($materiaPrimaData['mp_precio_referencia']) : '' ?>">
                    </div>
                </div>

                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary" name="Guardar">
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

