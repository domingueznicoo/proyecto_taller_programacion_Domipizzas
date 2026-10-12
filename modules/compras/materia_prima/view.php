<?php
// Vista: listado y formulario de materias primas.
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

// Incluir sistema de permisos y verificar acceso
require_once PROJECT_ROOT . '/config/permissions.php';
check_permission(['COMPRAS', 'REFERENCIALES']);

$username = $_SESSION['username'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $query = $pdo->prepare("SELECT * FROM usuario WHERE username = :username");
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->execute();
    $auth_user = $query->fetch();
    if (!$auth_user) {
        session_destroy();
        echo "<script>
                alert('Usuario no encontrado, serás redirigido al inicio de sesión');
                window.location.href = loginUrl();
              </script>";
        exit();
    }
    $permisoAcceso = (int)($auth_user['cargo_id'] ?? 0);
} catch (PDOException $e) {
    die("Error en la conexión a la base de datos: " . $e->getMessage());
}

$page_title = 'Mantener Materia Prima';
$extra_css = [
    'vendor/datatables/dataTables.bootstrap4.min.css'
];
$extra_js_plugins = [
    'vendor/datatables/jquery.dataTables.min.js',
    'vendor/datatables/dataTables.bootstrap4.min.js'
];

// Incluir header común (ya maneja permisos dinámicamente)
include PROJECT_ROOT . '/header.php';

$mostrarListado = !(isset($_GET['form_materia_prima']) && (($_GET['form'] ?? '') === 'add' || ($_GET['form'] ?? '') === 'edit'));
?>

<!-- Contenido específico del módulo -->
<?php if ($mostrarListado): ?>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Mantener Materia Prima</h1>
        <div>
            <a href="?form_materia_prima=add&form=add" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> Nueva Materia Prima
            </a>
            <a href="reporte.php" target="_blank" rel="noopener" class="btn btn-warning btn-sm shadow-sm" title="Reporte" aria-label="Reporte">
                <i class="fas fa-print fa-sm" aria-hidden="true"></i> Reporte
            </a>

  </div>
</div>

    <?php
    if (!empty($_GET['alert'])) {
        $alertMap = [
            1 => ['msg'=>'Materia prima registrada correctamente.','class'=>'alert-success'],
            2 => ['msg'=>'Materia prima modificada correctamente.','class'=>'alert-success'],
            3 => ['msg'=>'Materia prima inactivada correctamente.','class'=>'alert-success'],
            4 => ['msg'=>'No se pudo realizar la operación.','class'=>'alert-danger'],
            8 => ['msg'=>'La materia prima ha sido marcada como INACTIVA.','class'=>'alert-warning'],
        ];
        $alertMessage = '';
        $alertClass = 'alert-danger';
        
        if (isset($alertMap[$_GET['alert']])) {
            $data = $alertMap[$_GET['alert']];
            $alertMessage = $data['msg'];
            $alertClass = $data['class'];
        }
        
        // Si hay un mensaje personalizado en la URL, usarlo
        if (!empty($_GET['msg'])) {
            $alertMessage = htmlspecialchars(urldecode($_GET['msg']));
            $alertClass = 'alert-danger';
        }
        
        if ($alertMessage !== '') {
            echo "<div id='alert-message' class='alert {$alertClass} alert-dismissible fade show' role='alert'>
                    {$alertMessage}
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'>
                      <span aria-hidden='true'>&times;</span>
                    </button>
                  </div>";
        }
    }
    ?>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Lista de Materias Primas</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Descripción</th>
                            <th>Unidad de Medida</th>
                            <th>Tipo IVA</th>
                            <th>Depósito</th>
                            <th>Marca</th>
                            <th>Precio de referencia</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                        try {
                            $sql = "
                                SELECT mp.mp_id, mp.mp_descripcion, mp.mp_estado,
                                       um.unidad_medida_nombre, ti.tipo_iva_nombre, d.deposito_nombre,
                                       m.marca_descrip, mp.mp_precio_referencia,
                                       COALESCE((SELECT SUM(stock_cantidad) FROM stock_materia_prima
                                                 WHERE mp_id = mp.mp_id), 0) AS stock_total
                                FROM materia_prima mp
                                LEFT JOIN unidad_medida um ON mp.unidad_medida_id = um.unidad_medida_id
                                LEFT JOIN tipo_iva ti ON mp.tipo_iva_id = ti.tipo_iva_id
                                LEFT JOIN deposito d ON d.deposito_id = mp.deposito_id
                                LEFT JOIN marca m ON m.marca_id = mp.marca_id
                                ORDER BY mp.mp_id DESC
                            ";
                            foreach ($pdo->query($sql) as $data) {
                                $estadoClass = '';
                                $estado = strtoupper(trim($data['mp_estado'] ?? 'ACTIVO'));
                                if ($estado === 'ACTIVO') $estadoClass = 'badge-success';
                                elseif ($estado === 'INACTIVO') $estadoClass = 'badge-warning';
                                else $estadoClass = 'badge-danger';

                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($data['mp_id']) . "</td>";
                                echo "<td>" . htmlspecialchars($data['mp_descripcion']) . "</td>";
                                echo "<td>" . htmlspecialchars($data['unidad_medida_nombre'] ?? 'Sin unidad') . "</td>";
                                echo "<td>" . htmlspecialchars($data['tipo_iva_nombre']) . "</td>";
                                echo "<td>" . htmlspecialchars($data['deposito_nombre']) . "</td>";
                                echo "<td>" . htmlspecialchars($data['marca_descrip'] ?? '-') . "</td>";
                                echo "<td>" . htmlspecialchars($data['mp_precio_referencia']) . "</td>";
                                echo "<td><span class='badge {$estadoClass}'>" . htmlspecialchars($estado) . "</span></td>";
                                echo "<td>";
                                echo "<a href='?form_materia_prima=edit&form=edit&id={$data['mp_id']}' class='btn btn-warning btn-sm' title='Editar' aria-label='Editar'>";
                                echo "<i class='fas fa-edit'></i></a> ";
                                if ($estado === 'ACTIVO') {
                                    echo "<button type='button' class='btn btn-danger btn-sm btn-eliminar' data-id='{$data['mp_id']}' data-descripcion='" . htmlspecialchars($data['mp_descripcion'], ENT_QUOTES) . "' data-stock='{$data['stock_total']}' title='Desactivar' aria-label='Desactivar'><i class='fas fa-ban'></i></button> ";
                                } else {
                                    echo "<button type='button' class='btn btn-success btn-sm btn-activar' data-id='{$data['mp_id']}' title='Activar' aria-label='Activar'><i class='fas fa-check'></i></button> ";
                                }
                                echo "</td>";
                                echo "</tr>";
                            }
                                        } catch (PDOException $e) {
                            echo "<tr><td colspan='9'>Error al consultar los datos: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

    <!-- Modal de Inactivación -->
    <div class="modal fade" id="eliminarModal" tabindex="-1" role="dialog" aria-labelledby="eliminarModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eliminarModalLabel">Confirmar Inactivación</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro de que desea <strong>inactivar</strong> la materia prima <strong id="eliminarMateriaPrimaDesc"></strong>?</p>
                    <div id="eliminarInfo" class="alert alert-info">
                        <small id="eliminarInfoText"></small>
                    </div>
                    <div id="eliminarBloqueos" class="alert alert-warning d-none">
                        <strong>No se recomienda inactivar:</strong>
                        <ul id="eliminarBloqueosList" class="mb-0 pl-3"></ul>
                    </div>
                    <p class="text-warning mb-0"><strong>Nota:</strong> La materia prima se marcará como INACTIVA y no se podrá usar en nuevas operaciones, pero los registros se mantendrán en el sistema.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmEliminarBtn">Inactivar</button>
                    <button type="button" class="btn btn-warning d-none" id="confirmForceEliminarBtn">Inactivar de todos modos</button>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <?php include 'form.php'; ?>
<?php endif; ?>

<?php include PROJECT_ROOT . '/footer.php'; ?>

    <script>
$(document).ready(function() {
    $('#dataTable').DataTable({
        "language": {
            "url": "<?= $BASE_PATH ?>vendor/datatables/Spanish.json"
        },
        "order": [[0, "desc"]]
    });

    var materiaPrimaIdActual = null;
    var tieneBloqueos = false;

    function ejecutarInactivacion(force) {
        $.ajax({
            url: 'proses.php?act=delete',
            type: 'POST',
            data: { id: materiaPrimaIdActual, force: force ? 1 : 0 },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    window.location.reload();
                    return;
                }

                if (response.can_force && response.bloqueos && response.bloqueos.length) {
                    tieneBloqueos = true;
                    $('#eliminarBloqueosList').empty();
                    response.bloqueos.forEach(function(bloqueo) {
                        $('#eliminarBloqueosList').append('<li>' + bloqueo + '</li>');
                    });
                    $('#eliminarBloqueos').removeClass('d-none');
                    $('#confirmEliminarBtn').addClass('d-none');
                    $('#confirmForceEliminarBtn').removeClass('d-none');
                    return;
                }

                alert('Error: ' + (response.message || 'No se pudo inactivar la materia prima'));
            },
            error: function(xhr, status, error) {
                alert('Error de comunicación: ' + error);
            }
        });
    }

    $('#dataTable').on('click', '.btn-activar', function() {
        if (!confirm('¿Confirma activar esta materia prima?')) return;
        var button = $(this);
        button.prop('disabled', true);
        $.ajax({
            url: 'proses.php?act=toggle_estado',
            type: 'POST',
            data: { id: button.data('id'), estado: 'ACTIVO' },
            dataType: 'json',
            success: function(response) {
                if (response.ok) {
                    window.location.reload();
                } else {
                    alert(response.msg || 'No se pudo activar la materia prima.');
                }
            },
            error: function() { alert('No se pudo confirmar la activación.'); },
            complete: function() { button.prop('disabled', false); }
        });
    });

    $('#dataTable').on('click', '.btn-eliminar', function() {
        materiaPrimaIdActual = $(this).data('id');
        var descripcion = $(this).data('descripcion');
        var stock = parseFloat($(this).data('stock')) || 0;
        tieneBloqueos = false;

        $('#eliminarMateriaPrimaDesc').text(descripcion);
        $('#eliminarBloqueos').addClass('d-none');
        $('#eliminarBloqueosList').empty();
        $('#confirmEliminarBtn').removeClass('d-none');
        $('#confirmForceEliminarBtn').addClass('d-none');

        $.ajax({
            url: 'proses.php?act=validate_delete',
            type: 'POST',
            data: { id: materiaPrimaIdActual },
            dataType: 'json',
            success: function(response) {
                if (!response.success) {
                    $('#eliminarInfoText').text('No se pudo validar la materia prima.');
                    $('#eliminarModal').modal('show');
                    return;
                }

                if (response.puede_inactivar) {
                    $('#eliminarInfo').removeClass('alert-warning').addClass('alert-info');
                    $('#eliminarInfoText').text('La materia prima no tiene stock ni vínculos. Puede inactivarse sin restricciones.');
                } else {
                    tieneBloqueos = true;
                    $('#eliminarInfo').removeClass('alert-info').addClass('alert-warning');
                    $('#eliminarInfoText').text('La materia prima tiene restricciones. Revise el detalle antes de continuar.');
                    $('#eliminarBloqueosList').empty();
                    (response.bloqueos || []).forEach(function(bloqueo) {
                        $('#eliminarBloqueosList').append('<li>' + bloqueo + '</li>');
                    });
                    $('#eliminarBloqueos').removeClass('d-none');
                    $('#confirmEliminarBtn').addClass('d-none');
                    $('#confirmForceEliminarBtn').removeClass('d-none');
                }

                $('#eliminarModal').modal('show');
            },
            error: function() {
                var infoText = stock > 0
                    ? 'La materia prima tiene stock de ' + stock.toLocaleString('es-PY') + ' unidades.'
                    : 'La materia prima no tiene stock.';
                $('#eliminarInfoText').text(infoText);
                $('#eliminarModal').modal('show');
            }
        });
    });

    $('#confirmEliminarBtn').on('click', function() {
        ejecutarInactivacion(false);
    });

    $('#confirmForceEliminarBtn').on('click', function() {
        if (confirm('¿Confirma inactivar la materia prima aunque tenga stock o vínculos registrados?')) {
            ejecutarInactivacion(true);
        }
    });
});
    </script>

