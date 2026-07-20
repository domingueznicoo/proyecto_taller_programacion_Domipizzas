<?php
// Vista: listado y formulario de inspectores.
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();
if (empty($_SESSION['username'])) {
    echo "<script>alert('Token de sesión inválido'); window.location.href = loginUrl();</script>";
    exit();
}

require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/permissions.php';
check_permission('REFERENCIALES');

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $q = $pdo->prepare('SELECT 1 FROM usuarios WHERE username = :u');
    $q->execute([':u' => $_SESSION['username']]);
    if (!$q->fetchColumn()) {
        session_destroy();
        header('Location: ' . loginUrl());
        exit;
    }
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}

$page_title = 'Mantener Inspectores';
$extra_css = ['vendor/datatables/dataTables.bootstrap4.min.css'];
$extra_js_plugins = [
    'vendor/datatables/jquery.dataTables.min.js',
    'vendor/datatables/dataTables.bootstrap4.min.js',
];
include PROJECT_ROOT . '/header.php';

$mostrarListado = !(isset($_GET['form_inspector']) && in_array($_GET['form'] ?? '', ['add', 'edit'], true));
?>

<?php if ($mostrarListado): ?>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Mantener Inspectores</h1>
        <a href="?form_inspector=add&form=add" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo Inspector
        </a>
    </div>

    <?php
    $alertMap = [
        1 => ['msg' => 'Inspector registrado correctamente.', 'class' => 'alert-success'],
        2 => ['msg' => 'Inspector modificado correctamente.', 'class' => 'alert-success'],
        3 => ['msg' => 'Inspector inactivado correctamente.', 'class' => 'alert-success'],
        4 => ['msg' => 'No se pudo realizar la operación.', 'class' => 'alert-danger'],
        5 => ['msg' => 'Ya existe un inspector activo para ese personal.', 'class' => 'alert-danger'],
    ];
    if (!empty($_GET['alert']) && isset($alertMap[(int)$_GET['alert']])) {
        $d = $alertMap[(int)$_GET['alert']];
        echo "<div class='alert {$d['class']} alert-dismissible fade show'>{$d['msg']}
              <button type='button' class='close' data-dismiss='alert'><span>&times;</span></button></div>";
    }
    ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Lista de Inspectores</h6></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Personal</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "
                            SELECT i.id_inspectores, i.inspector_estado,
                                   TRIM(p.personal_nombre || ' ' || p.personal_apellido) AS nombre_personal
                            FROM inspectores i
                            JOIN personal p ON p.id_personal = i.id_personal
                            ORDER BY i.id_inspectores DESC
                        ";
                        foreach ($pdo->query($sql) as $row) {
                            $estado = strtoupper(trim($row['inspector_estado'] ?? 'ACTIVO'));
                            $cls = $estado === 'ACTIVO' ? 'badge-success' : 'badge-warning';
                            $id = (int)$row['id_inspectores'];
                            echo '<tr>';
                            echo '<td>' . $id . '</td>';
                            echo '<td>' . htmlspecialchars($row['nombre_personal']) . '</td>';
                            echo "<td><span class='badge {$cls}'>" . htmlspecialchars($estado) . '</span></td>';
                            echo '<td>';
                            echo "<a href='?form_inspector=edit&form=edit&id={$id}' class='btn btn-warning btn-sm' title='Editar'><i class='fas fa-edit'></i></a> ";
                            if ($estado === 'ACTIVO') {
                                echo "<button type='button' class='btn btn-danger btn-sm btn-inactivar' data-id='{$id}' data-nombre='" . htmlspecialchars($row['nombre_personal'], ENT_QUOTES) . "'><i class='fas fa-ban'></i></button>";
                            }
                            echo '</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalInactivar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Inactivar inspector</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p>¿Inactivar al inspector <strong id="nombreInspector"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmInactivar">Inactivar</button>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php include __DIR__ . '/form.php'; ?>
<?php endif; ?>

<?php
$inline_js = <<<'JS'
$(document).ready(function() {
  if ($('#dataTable').length) {
    $('#dataTable').DataTable({ language: { url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json' } });
  }
  let inspectorId = 0;
  $('.btn-inactivar').on('click', function() {
    inspectorId = $(this).data('id');
    $('#nombreInspector').text($(this).data('nombre'));
    $('#modalInactivar').modal('show');
  });
  $('#btnConfirmInactivar').on('click', function() {
    if (!inspectorId) return;
    window.location.href = 'proses.php?act=inactivate&id=' + inspectorId;
  });
});
JS;
include PROJECT_ROOT . '/footer.php';
