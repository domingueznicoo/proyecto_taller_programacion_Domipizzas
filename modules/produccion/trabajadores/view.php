<?php
// Vista: listado y formulario de trabajadores.
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

$username = $_SESSION['username'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $query = $pdo->prepare("SELECT * FROM usuarios WHERE username = :username");
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
} catch (PDOException $e) {
    die("Error en la conexión a la base de datos: " . $e->getMessage());
}

$page_title = 'Mantener Trabajadores';
$extra_css = ['vendor/datatables/dataTables.bootstrap4.min.css'];
$extra_js_plugins = [
    'vendor/datatables/jquery.dataTables.min.js',
    'vendor/datatables/dataTables.bootstrap4.min.js'
];

include PROJECT_ROOT . '/header.php';

$mostrarListado = !(isset($_GET['form_trabajador']) && ($_GET['form'] === 'add' || $_GET['form'] === 'edit'));
?>

<?php if ($mostrarListado): ?>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Mantener Trabajadores</h1>
        <div>
            <a href="?form_trabajador=add&form=add" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo Trabajador
            </a>
            <button type="button" class="btn btn-warning btn-sm shadow-sm" data-toggle="modal" data-target="#modalReporte">
                <i class="fas fa-print fa-sm text-white-50"></i> Imprimir Reporte
            </button>
        </div>
    </div>

    <?php
    if (!empty($_GET['alert'])) {
        $alertMap = [
            1 => ['msg' => 'Trabajador registrado correctamente.', 'class' => 'alert-success'],
            2 => ['msg' => 'Trabajador modificado correctamente.', 'class' => 'alert-success'],
            3 => ['msg' => 'Trabajador inactivado correctamente.', 'class' => 'alert-success'],
            4 => ['msg' => 'No se pudo realizar la operación.', 'class' => 'alert-danger'],
            5 => ['msg' => 'Ya existe un trabajador activo para ese personal.', 'class' => 'alert-danger'],
        ];
        $alertMessage = '';
        $alertClass = 'alert-danger';

        if (isset($alertMap[$_GET['alert']])) {
            $data = $alertMap[$_GET['alert']];
            $alertMessage = $data['msg'];
            $alertClass = $data['class'];
        }

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
            <h6 class="m-0 font-weight-bold text-primary">Lista de Trabajadores</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Personal</th>
                            <th>Rol</th>
                            <th>Turno</th>
                            <th>Costo Hora</th>
                            <th>Etapa Predeterminada</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $sql = "
                                SELECT
                                    t.trabajadores_id,
                                    t.trabajador_rol,
                                    t.trabajador_turno,
                                    t.trabajador_costo_hora,
                                    t.trabajador_estado,
                                    TRIM(per.personal_nombre || ' ' || per.personal_apellido) AS nombre_personal,
                                    COALESCE(ep.etapa_descri, 'Sin etapa') AS etapa_descri
                                FROM trabajadores t
                                JOIN personal per ON per.id_personal = t.id_personal
                                LEFT JOIN etapa_produccion ep ON ep.etapa_id = t.id_etapa
                                ORDER BY t.trabajadores_id DESC
                            ";
                            foreach ($pdo->query($sql) as $data) {
                                $estado = strtoupper(trim($data['trabajador_estado'] ?? 'ACTIVO'));
                                if ($estado === 'ANULADO') {
                                    $estado = 'INACTIVO';
                                }
                                $estadoClass = ($estado === 'ACTIVO') ? 'badge-success' : 'badge-warning';

                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($data['trabajadores_id']) . "</td>";
                                echo "<td>" . htmlspecialchars($data['nombre_personal']) . "</td>";
                                echo "<td>" . htmlspecialchars($data['trabajador_rol'] ?? '') . "</td>";
                                echo "<td>" . htmlspecialchars($data['trabajador_turno'] ?? '') . "</td>";
                                echo "<td>" . number_format((float)$data['trabajador_costo_hora'], 0, ',', '.') . "</td>";
                                echo "<td>" . htmlspecialchars($data['etapa_descri']) . "</td>";
                                echo "<td><span class='badge {$estadoClass}'>" . htmlspecialchars($estado) . "</span></td>";
                                echo "<td>";
                                echo "<a href='?form_trabajador=edit&form=edit&id={$data['trabajadores_id']}' class='btn btn-warning btn-sm' title='Editar'>";
                                echo "<i class='fas fa-edit'></i></a> ";
                                if ($estado === 'ACTIVO') {
                                    echo "<button type='button' class='btn btn-danger btn-sm btn-inactivar' data-id='{$data['trabajadores_id']}' data-descripcion='" . htmlspecialchars($data['nombre_personal'], ENT_QUOTES) . "' title='Inactivar'>";
                                    echo "<i class='fas fa-ban'></i></button>";
                                }
                                echo "</td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='8'>Error al consultar los datos: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

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
                    <p>¿Está seguro de que desea <strong>inactivar</strong> al trabajador <strong id="eliminarTrabajadorDesc"></strong>?</p>
                    <div id="eliminarInfo" class="alert alert-info">
                        <small id="eliminarInfoText"></small>
                    </div>
                    <div id="eliminarBloqueos" class="alert alert-warning d-none">
                        <strong>El trabajador tiene registros asociados:</strong>
                        <ul id="eliminarBloqueosList" class="mb-0 pl-3"></ul>
                    </div>
                    <p class="text-warning mb-0"><strong>Nota:</strong> El trabajador se marcará como INACTIVO. No se eliminará físicamente del sistema.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmEliminarBtn">Inactivar</button>
                    <button type="button" class="btn btn-warning d-none" id="confirmForceEliminarBtn">Inactivar de todos modos</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalReporte" tabindex="-1" role="dialog" aria-labelledby="modalReporteLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalReporteLabel">Opciones de Reporte</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Generar Reporte</button>
                    </div>
                </form>
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

    var trabajadorIdActual = null;

    function ejecutarInactivacion(force) {
        $.ajax({
            url: 'proses.php?act=delete',
            type: 'POST',
            data: { id: trabajadorIdActual, force: force ? 1 : 0 },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    window.location.reload();
                    return;
                }

                if (response.can_force && response.bloqueos && response.bloqueos.length) {
                    $('#eliminarBloqueosList').empty();
                    response.bloqueos.forEach(function(bloqueo) {
                        $('#eliminarBloqueosList').append('<li>' + bloqueo + '</li>');
                    });
                    $('#eliminarBloqueos').removeClass('d-none');
                    $('#confirmEliminarBtn').addClass('d-none');
                    $('#confirmForceEliminarBtn').removeClass('d-none');
                    return;
                }

                alert('Error: ' + (response.message || 'No se pudo inactivar el trabajador'));
            },
            error: function(xhr, status, error) {
                alert('Error de comunicación: ' + error);
            }
        });
    }

    $('#dataTable').on('click', '.btn-inactivar', function() {
        trabajadorIdActual = $(this).data('id');
        var descripcion = $(this).data('descripcion');

        $('#eliminarTrabajadorDesc').text(descripcion);
        $('#eliminarBloqueos').addClass('d-none');
        $('#eliminarBloqueosList').empty();
        $('#confirmEliminarBtn').removeClass('d-none');
        $('#confirmForceEliminarBtn').addClass('d-none');

        $.ajax({
            url: 'proses.php?act=validate_delete',
            type: 'POST',
            data: { id: trabajadorIdActual },
            dataType: 'json',
            success: function(response) {
                if (!response.success) {
                    $('#eliminarInfoText').text('No se pudo validar el trabajador.');
                    $('#eliminarModal').modal('show');
                    return;
                }

                if (response.puede_inactivar) {
                    $('#eliminarInfo').removeClass('alert-warning').addClass('alert-info');
                    $('#eliminarInfoText').text('El trabajador no tiene vínculos en producción. Puede inactivarse sin restricciones.');
                } else {
                    $('#eliminarInfo').removeClass('alert-info').addClass('alert-warning');
                    $('#eliminarInfoText').text('El trabajador tiene registros asociados. Revise el detalle antes de continuar.');
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
                $('#eliminarInfoText').text('No se pudo validar el trabajador.');
                $('#eliminarModal').modal('show');
            }
        });
    });

    $('#confirmEliminarBtn').on('click', function() {
        ejecutarInactivacion(false);
    });

    $('#confirmForceEliminarBtn').on('click', function() {
        if (confirm('¿Confirma inactivar el trabajador aunque tenga equipos o costos de producción asociados?')) {
            ejecutarInactivacion(true);
        }
    });
});
</script>
