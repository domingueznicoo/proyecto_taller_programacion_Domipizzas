<?php
// Formulario de proveedores (alta o edición).
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
check_permission(['COMPRAS', 'REFERENCIALES']);

$isEdit = isset($_GET['form_proveedor']) && ($_GET['form'] ?? '') == 'edit' && isset($_GET['id']);
$proveedorData = null;

if ($isEdit) {
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $query = $pdo->prepare("SELECT * FROM proveedor WHERE prov_id = :id");
        $query->execute([':id' => (int)$_GET['id']]);
        $proveedorData = $query->fetch();

        if (!$proveedorData) {
            echo "<script>alert('Proveedor no encontrado'); window.location.href='view.php';</script>";
            exit();
        }

        $estado = strtoupper(trim($proveedorData['prov_estado'] ?? 'ACTIVO'));
        if ($estado === 'ANULADO') {
            $estado = 'INACTIVO';
        }
        $proveedorData['prov_estado'] = $estado;
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= $isEdit ? 'Editar Proveedor' : 'Nuevo Proveedor' ?></h1>
        <a href="view.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?= $isEdit ? 'Editar' : 'Registrar' ?> Proveedor</h6>
        </div>
        <div class="card-body">
            <form action="proses.php?act=<?= $isEdit ? 'update' : 'insert' ?>" method="POST" id="formProveedor">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="prov_id" value="<?= htmlspecialchars($proveedorData['prov_id']) ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="prov_razon_social">Razón Social <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="prov_razon_social" name="prov_razon_social"
                                   value="<?= $isEdit ? htmlspecialchars($proveedorData['prov_razon_social'] ?? '') : '' ?>"
                                   placeholder="Ingrese la razón social" required maxlength="150">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="prov_ruc">RUC <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="prov_ruc" name="prov_ruc"
                                   value="<?= $isEdit ? htmlspecialchars($proveedorData['prov_ruc'] ?? '') : '' ?>"
                                   placeholder="Ej: 5574408-1" required maxlength="20"
                                   pattern="^(\d{6,7}-\d{1}|\d{8,9})$"
                                   title="Formato: NNNNNNN-N o NNNNNNNN">
                            <small class="form-text text-muted">Formato paraguayo: NNNNNNN-N o NNNNNNNN</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="prov_telefono">Teléfono <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="prov_telefono" name="prov_telefono"
                                   value="<?= $isEdit ? htmlspecialchars($proveedorData['prov_telefono'] ?? '') : '' ?>"
                                   placeholder="Ingrese el teléfono" required maxlength="30"
                                   pattern="^[0-9+\-\s]+$"
                                   title="Solo números, espacios, + y guiones">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="prov_direccion">Dirección</label>
                            <input type="text" class="form-control" id="prov_direccion" name="prov_direccion"
                                   value="<?= $isEdit ? htmlspecialchars($proveedorData['prov_direccion'] ?? '') : '' ?>"
                                   placeholder="Ingrese la dirección" maxlength="200">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="prov_email">Correo Electrónico</label>
                            <input type="email" class="form-control" id="prov_email" name="prov_email"
                                   value="<?= $isEdit ? htmlspecialchars($proveedorData['prov_email'] ?? '') : '' ?>"
                                   placeholder="correo@ejemplo.com" maxlength="120">
                        </div>
                    </div>
                </div>

                <?php if ($isEdit): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="prov_estado">Estado</label>
                            <select name="prov_estado" id="prov_estado" class="form-control">
                                <option value="ACTIVO" <?= (($proveedorData['prov_estado'] ?? 'ACTIVO') === 'ACTIVO') ? 'selected' : '' ?>>ACTIVO</option>
                                <option value="INACTIVO" <?= (($proveedorData['prov_estado'] ?? '') === 'INACTIVO') ? 'selected' : '' ?>>INACTIVO</option>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

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
