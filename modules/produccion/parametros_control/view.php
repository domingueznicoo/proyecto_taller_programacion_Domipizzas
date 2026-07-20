<?php
// Vista: listado y formulario de parámetros de inspección.
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();
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

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $query = $pdo->prepare("SELECT 1 FROM usuarios WHERE username = :username");
    $query->execute([':username' => $_SESSION['username']]);
    if (!$query->fetchColumn()) {
        session_destroy();
        header('Location: ' . loginUrl());
        exit();
    }

    $productosActivos = $pdo->query("
        SELECT producto_id, producto_descri
        FROM productos
        WHERE UPPER(TRIM(producto_estado)) = 'ACTIVO'
        ORDER BY producto_descri ASC
    ")->fetchAll();
} catch (PDOException $e) {
    die("Error en la conexión a la base de datos: " . $e->getMessage());
}

$page_title = 'Parámetros de Control de Calidad';
$extra_css = ['vendor/datatables/dataTables.bootstrap4.min.css'];
$extra_js_plugins = [
    'vendor/datatables/jquery.dataTables.min.js',
    'vendor/datatables/dataTables.bootstrap4.min.js'
];

include PROJECT_ROOT . '/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Parámetros de Control de Calidad</h1>
    <div>
        <button type="button" class="btn btn-primary btn-sm shadow-sm" id="btnNuevo" <?= empty($productosActivos) ? 'disabled' : '' ?>>
            <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo Parámetro
        </button>
        <button type="button" class="btn btn-warning btn-sm shadow-sm" data-toggle="modal" data-target="#modalReporte">
            <i class="fas fa-print fa-sm text-white-50"></i> Imprimir Reporte
        </button>
    </div>
</div>

<?php if (empty($productosActivos)): ?>
<div class="alert alert-warning">
    No hay productos activos registrados. Debe existir al menos un producto activo para asociar parámetros de control.
</div>
<?php endif; ?>

<div id="alertContainer"></div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Lista de Parámetros</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        $sql = "
                            SELECT
                                pc.parametro_id,
                                pc.parametro_descri,
                                pc.parametro_estado,
                                p.producto_descri
                            FROM parametros_control pc
                            JOIN productos p ON p.producto_id = pc.producto_id
                            ORDER BY pc.parametro_id DESC
                        ";
                        foreach ($pdo->query($sql) as $data) {
                            $estado = strtoupper(trim($data['parametro_estado'] ?? 'ACTIVO'));
                            $estadoClass = ($estado === 'ACTIVO') ? 'badge-success' : 'badge-warning';

                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($data['parametro_id']) . "</td>";
                            echo "<td>" . htmlspecialchars($data['producto_descri']) . "</td>";
                            echo "<td>" . htmlspecialchars($data['parametro_descri']) . "</td>";
                            echo "<td><span class='badge {$estadoClass}'>" . htmlspecialchars($estado) . "</span></td>";
                            echo "<td>";
                            echo "<button type='button' class='btn btn-warning btn-sm btn-editar' data-id='{$data['parametro_id']}' title='Editar'>";
                            echo "<i class='fas fa-edit'></i></button> ";
                            if ($estado === 'ACTIVO') {
                                echo "<button type='button' class='btn btn-danger btn-sm btn-eliminar' data-id='{$data['parametro_id']}' data-descripcion='" . htmlspecialchars($data['parametro_descri'], ENT_QUOTES) . "' title='Inactivar'>";
                                echo "<i class='fas fa-ban'></i></button>";
                            }
                            echo "</td>";
                            echo "</tr>";
                        }
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='5'>Error al consultar los datos: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal formulario -->
<div class="modal fade" id="formModal" tabindex="-1" role="dialog" aria-labelledby="formModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="formModalLabel">Nuevo Parámetro</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formParametro">
                <div class="modal-body">
                    <input type="hidden" id="parametro_id" name="parametro_id" value="">
                    <input type="hidden" id="form_mode" value="insert">

                    <div class="form-group">
                        <label for="producto_id">Producto <span class="text-danger">*</span></label>
                        <select class="form-control" id="producto_id" name="producto_id" required>
                            <option value="">Seleccione un producto</option>
                            <?php foreach ($productosActivos as $prod): ?>
                                <option value="<?= (int)$prod['producto_id'] ?>">
                                    <?= htmlspecialchars($prod['producto_descri']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="parametro_descri">Descripción del parámetro <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="parametro_descri" name="parametro_descri"
                               maxlength="30" required placeholder="Ej: TEMPERATURA, PH, PESO">
                        <small class="form-text text-muted">Máximo 30 caracteres</small>
                    </div>

                    <div class="form-group">
                        <label for="parametro_estado">Estado <span class="text-danger">*</span></label>
                        <select class="form-control" id="parametro_estado" name="parametro_estado" required>
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                        </select>
                    </div>

                    <div id="formError" class="alert alert-danger d-none mb-0"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal inactivación -->
<div class="modal fade" id="eliminarModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Inactivación</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro de que desea <strong>inactivar</strong> el parámetro <strong id="eliminarParamDesc"></strong>?</p>
                <div id="eliminarInfo" class="alert alert-info">
                    <small id="eliminarInfoText"></small>
                </div>
                <div id="eliminarBloqueos" class="alert alert-danger d-none">
                    <strong>No se puede inactivar:</strong>
                    <ul id="eliminarBloqueosList" class="mb-0 pl-3"></ul>
                </div>
                <p class="text-muted mb-0"><small>El parámetro se marcará como INACTIVO. No se elimina físicamente del sistema.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmEliminarBtn">Inactivar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal reporte -->
<div class="modal fade" id="modalReporte" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Opciones de Reporte</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="reporte.php" method="GET" target="_blank">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="filtro_estado_reporte">Estado</label>
                        <select name="estado" id="filtro_estado_reporte" class="form-control">
                            <option value="">Todos</option>
                            <option value="ACTIVO">Activos</option>
                            <option value="INACTIVO">Inactivos</option>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label for="filtro_producto_reporte">Producto</label>
                        <select name="producto_id" id="filtro_producto_reporte" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach ($productosActivos as $prod): ?>
                                <option value="<?= (int)$prod['producto_id'] ?>">
                                    <?= htmlspecialchars($prod['producto_descri']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Generar Reporte</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include PROJECT_ROOT . '/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#dataTable').DataTable({
        language: { url: "<?= $BASE_PATH ?>vendor/datatables/Spanish.json" },
        order: [[0, "desc"]]
    });

    var parametroIdEliminar = null;

    function mostrarAlerta(msg, tipo) {
        var cls = tipo === 'success' ? 'alert-success' : 'alert-danger';
        $('#alertContainer').html(
            '<div class="alert ' + cls + ' alert-dismissible fade show" role="alert">' +
            msg + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>'
        );
        setTimeout(function() { $('#alertContainer').empty(); }, 6000);
    }

    function limpiarFormModal() {
        $('#formParametro')[0].reset();
        $('#parametro_id').val('');
        $('#form_mode').val('insert');
        $('#formModalLabel').text('Nuevo Parámetro');
        $('#formError').addClass('d-none').text('');
        $('#producto_id').prop('disabled', false);
    }

    $('#btnNuevo').on('click', function() {
        limpiarFormModal();
        $('#formModal').modal('show');
    });

    $('#formModal').on('hidden.bs.modal', function() {
        limpiarFormModal();
    });

    $('#dataTable').on('click', '.btn-editar', function() {
        var id = $(this).data('id');
        limpiarFormModal();
        $('#form_mode').val('update');
        $('#formModalLabel').text('Editar Parámetro');
        $('#parametro_id').val(id);

        $.getJSON('proses.php?act=get&id=' + id, function(response) {
            if (!response.success) {
                alert(response.message || 'No se pudo cargar el parámetro');
                return;
            }
            var d = response.data;
            $('#producto_id').val(d.producto_id).prop('disabled', true);
            $('#parametro_descri').val(d.parametro_descri);
            $('#parametro_estado').val(d.parametro_estado);
            $('#formModal').modal('show');
        }).fail(function() {
            alert('Error de comunicación al cargar el parámetro.');
        });
    });

    $('#formParametro').on('submit', function(e) {
        e.preventDefault();
        var mode = $('#form_mode').val();
        var url = mode === 'update' ? 'proses.php?act=update' : 'proses.php?act=insert';
        var data = {
            producto_id: $('#producto_id').val(),
            parametro_descri: $('#parametro_descri').val(),
            parametro_estado: $('#parametro_estado').val()
        };
        if (mode === 'update') {
            data.parametro_id = $('#parametro_id').val();
            data.producto_id = $('#producto_id').val();
        }

        $('#btnGuardar').prop('disabled', true);
        $('#formError').addClass('d-none');

        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#formModal').modal('hide');
                    mostrarAlerta(response.message, 'success');
                    setTimeout(function() { window.location.reload(); }, 800);
                } else {
                    $('#formError').removeClass('d-none').text(response.message || 'Error al guardar');
                }
            },
            error: function() {
                $('#formError').removeClass('d-none').text('Error de comunicación. Verifique la conexión e intente nuevamente.');
            },
            complete: function() {
                $('#btnGuardar').prop('disabled', false);
            }
        });
    });

    $('#dataTable').on('click', '.btn-eliminar', function() {
        parametroIdEliminar = $(this).data('id');
        var descripcion = $(this).data('descripcion');

        $('#eliminarParamDesc').text(descripcion);
        $('#eliminarBloqueos').addClass('d-none');
        $('#eliminarBloqueosList').empty();
        $('#confirmEliminarBtn').removeClass('d-none').prop('disabled', false);

        $.ajax({
            url: 'proses.php?act=validate_delete',
            type: 'POST',
            data: { id: parametroIdEliminar },
            dataType: 'json',
            success: function(response) {
                if (!response.success) {
                    $('#eliminarInfoText').text('No se pudo validar el parámetro.');
                    $('#eliminarModal').modal('show');
                    return;
                }
                if (response.puede_inactivar) {
                    $('#eliminarInfo').removeClass('alert-danger').addClass('alert-info');
                    $('#eliminarInfoText').text('El parámetro no tiene inspecciones asociadas. Puede inactivarse.');
                } else {
                    $('#eliminarInfo').removeClass('alert-info').addClass('alert-danger');
                    $('#eliminarInfoText').text('El parámetro tiene inspecciones de calidad asociadas y no puede inactivarse.');
                    $('#eliminarBloqueosList').empty();
                    (response.bloqueos || []).forEach(function(b) {
                        $('#eliminarBloqueosList').append('<li>' + b + '</li>');
                    });
                    $('#eliminarBloqueos').removeClass('d-none');
                    $('#confirmEliminarBtn').prop('disabled', true);
                }
                $('#eliminarModal').modal('show');
            },
            error: function() {
                $('#eliminarInfoText').text('Error de conexión al validar. Intente nuevamente.');
                $('#eliminarModal').modal('show');
            }
        });
    });

    $('#confirmEliminarBtn').on('click', function() {
        if (!parametroIdEliminar) return;
        $(this).prop('disabled', true);

        $.ajax({
            url: 'proses.php?act=delete',
            type: 'POST',
            data: { id: parametroIdEliminar },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#eliminarModal').modal('hide');
                    mostrarAlerta(response.message, 'success');
                    setTimeout(function() { window.location.reload(); }, 800);
                } else {
                    alert(response.message || 'No se pudo inactivar el parámetro');
                    if (response.bloqueos && response.bloqueos.length) {
                        $('#eliminarBloqueosList').empty();
                        response.bloqueos.forEach(function(b) {
                            $('#eliminarBloqueosList').append('<li>' + b + '</li>');
                        });
                        $('#eliminarBloqueos').removeClass('d-none');
                    }
                    $('#confirmEliminarBtn').prop('disabled', true);
                }
            },
            error: function() {
                alert('Error de comunicación al inactivar.');
                $('#confirmEliminarBtn').prop('disabled', false);
            }
        });
    });
});
</script>
