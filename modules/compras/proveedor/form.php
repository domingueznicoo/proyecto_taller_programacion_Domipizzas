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

$isEdit = isset($_GET['form_proveedor']) && $_GET['form'] == 'edit' && isset($_GET['id']);
$proveedorData = null;

if ($isEdit) {
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $query = $pdo->prepare("SELECT * FROM proveedor WHERE id_proveedor = :id");
        $query->execute([':id' => (int)$_GET['id']]);
        $proveedorData = $query->fetch();

        if (!$proveedorData) {
            echo "<script>alert('Proveedor no encontrado'); window.location.href='view.php';</script>";
            exit();
        }

        $estado = strtoupper(trim($proveedorData['estado_proveedor'] ?? 'ACTIVO'));
        if ($estado === 'ANULADO') {
            $estado = 'INACTIVO';
        }
        $proveedorData['estado_proveedor'] = $estado;
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
                    <input type="hidden" name="id_proveedor" value="<?= htmlspecialchars($proveedorData['id_proveedor']) ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="razon_social">Razón Social <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="razon_social" name="razon_social"
                                   value="<?= $isEdit ? htmlspecialchars($proveedorData['razon_social'] ?? '') : '' ?>"
                                   placeholder="Ingrese la razón social" required maxlength="150">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="ruc_proveedor">RUC <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ruc_proveedor" name="ruc_proveedor"
                                   value="<?= $isEdit ? htmlspecialchars($proveedorData['ruc_proveedor'] ?? '') : '' ?>"
                                   placeholder="Ej: 5574408-1" required maxlength="20"
                                   pattern="^(\d{6,7}-\d{1}|\d{8,9})$"
                                   title="Formato: NNNNNNN-N o NNNNNNNN">
                            <small class="form-text text-muted">Formato paraguayo: NNNNNNN-N o NNNNNNNN</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="telefono_proveedor">Teléfono <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="telefono_proveedor" name="telefono_proveedor"
                                   value="<?= $isEdit ? htmlspecialchars($proveedorData['telefono_proveedor'] ?? '') : '' ?>"
                                   placeholder="Ingrese el teléfono" required maxlength="30"
                                   pattern="^[0-9+\-\s]+$"
                                   title="Solo números, espacios, + y guiones">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="direccion_proveedor">Dirección</label>
                            <input type="text" class="form-control" id="direccion_proveedor" name="direccion_proveedor"
                                   value="<?= $isEdit ? htmlspecialchars($proveedorData['direccion_proveedor'] ?? '') : '' ?>"
                                   placeholder="Ingrese la dirección" maxlength="200">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email_proveedor">Correo Electrónico</label>
                            <input type="email" class="form-control" id="email_proveedor" name="email_proveedor"
                                   value="<?= $isEdit ? htmlspecialchars($proveedorData['email_proveedor'] ?? '') : '' ?>"
                                   placeholder="correo@ejemplo.com" maxlength="120">
                        </div>
                    </div>
                </div>

                <?php if ($isEdit): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="estado_proveedor">Estado</label>
                            <select name="estado_proveedor" id="estado_proveedor" class="form-control">
                                <option value="ACTIVO" <?= (($proveedorData['estado_proveedor'] ?? 'ACTIVO') === 'ACTIVO') ? 'selected' : '' ?>>ACTIVO</option>
                                <option value="INACTIVO" <?= (($proveedorData['estado_proveedor'] ?? '') === 'INACTIVO') ? 'selected' : '' ?>>INACTIVO</option>
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
