<?php
// Formulario de usuarios (alta o edición).
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();

if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = loginUrl();
          </script>";
    exit();
}

$file = PROJECT_ROOT . '/config/database.php';
if (!$file || !file_exists($file)) {
    die("Error: No se pudo encontrar el archivo en la ruta $file");
}

require_once $file;

// Incluir sistema de permisos y verificar acceso
require_once PROJECT_ROOT . '/config/permissions.php';
check_permission('ADMINISTRACION'); // Solo ADMIN puede gestionar usuarios

$action = $_GET['action'] ?? 'add';
$idUsuario = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$usuario = [
    'username'     => '',
    'modulo_id'    => '',
    'cargo_id'     => '',
    'personal_id'  => '',
    'usuario_estado' => 'ACTIVO',
];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    if ($action === 'edit') {
        if ($idUsuario <= 0) {
            header("Location: view.php?alert=4");
            exit();
        }
        // Obtener datos del usuario y del personal asociado si existe
        $stmt = $pdo->prepare("
            SELECT 
                u.*,
                p.personal_nombre,
                p.personal_apellido,
                p.personal_ci,
                p.personal_telefono
            FROM usuario u
            LEFT JOIN personal p ON p.personal_id = u.personal_id
            WHERE u.usuario_id = :id 
            LIMIT 1
        ");
        $stmt->execute([':id' => $idUsuario]);
        $data = $stmt->fetch();
        if (!$data) {
            header("Location: view.php?alert=4");
            exit();
        }
        $usuario = array_merge($usuario, $data);
        // Mapear datos de personal a variables para el formulario
        $usuario['personal_nombre'] = $data['personal_nombre'] ?? '';
        $usuario['personal_apellido'] = $data['personal_apellido'] ?? '';
        $usuario['personal_ci'] = $data['personal_ci'] ?? '';
        $usuario['personal_telefono'] = $data['personal_telefono'] ?? '';
    }

    $modulos = $pdo->query("SELECT modulo_id, modulo_nombre FROM modulos ORDER BY modulo_nombre ASC")->fetchAll();
    $cargos = $pdo->query("SELECT cargo_id, descrip_cargo FROM cargo WHERE estado_cargo = 'ACTIVO' ORDER BY descrip_cargo ASC")->fetchAll();
    $personalList = $pdo->query("
        SELECT 
            personal_id, 
            personal_nombre, personal_apellido, personal_ci
        FROM personal 
        WHERE personal_estado = 'ACTIVO' 
        ORDER BY personal_nombre, personal_apellido ASC
    ")->fetchAll();

} catch (PDOException $e) {
    die("Error en la conexión a la base de datos: " . $e->getMessage());
}

$formAction = $action === 'edit' ? "proses.php?act=update" : "proses.php?act=insert";
$titulo = $action === 'edit' ? 'Editar Usuario' : 'Nuevo Usuario';
$page_title = $titulo;
include PROJECT_ROOT . '/header.php';
?>
    <div class="container-fluid py-4">
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="m-0 font-weight-bold" style="color: #dc2626;"><?php echo $titulo; ?></h5>
                <a href="view.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
            <div class="card-body">
                <form action="<?php echo $formAction; ?>" method="POST" autocomplete="off">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="usuario_id" value="<?php echo (int)$idUsuario; ?>">
                    <?php endif; ?>
                    <h6 class="font-weight-bold mb-3" style="color: #dc2626;"><i class="fas fa-user mr-2"></i>Datos del usuario</h6>
                    <div class="row">
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-6">
                        <div class="form-group">
                            <label for="username">Nombre de usuario *</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($usuario['username'] ?? ''); ?>" placeholder="Ingresá el nombre de usuario" required autocomplete="off" maxlength="30">
                        </div>
                        <div class="form-group">
                            <label for="password"><?php echo $action === 'edit' ? 'Nueva contraseña' : 'Contraseña *'; ?></label>
                            <input type="password" class="form-control" id="password" name="password" <?php echo $action === 'add' ? 'required' : ''; ?> placeholder="<?php echo $action === 'edit' ? 'Dejar en blanco para mantener' : ''; ?>" autocomplete="new-password" minlength="8" maxlength="15" pattern="^(?=.*\d).+$">
                            <small class="form-text text-muted">Mínimo 8 caracteres e incluir al menos un número.</small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirmar contraseña<?php echo $action === 'add' ? ' *' : ''; ?></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                autocomplete="new-password" placeholder="Repetí la contraseña" <?php echo $action === 'add' ? 'required' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label for="usuario_estado">Estado actual</label>
                            <input id="usuario_estado" class="form-control" type="text" value="<?php echo htmlspecialchars($usuario['usuario_estado']); ?>" readonly>
                            <small class="form-text text-muted">El estado se cambia desde el listado de usuarios.</small>
                        </div>
                                </div>
                                <div class="col-md-6">
                        <div class="form-group">
                            <label for="personal_id">Personal (Opcional)</label>
                            <select id="personal_id" name="personal_id" class="form-control">
                                <option value="">-- Sin personal asociado --</option>
                                <?php foreach ($personalList as $personal): ?>
                                    <option value="<?php echo (int)$personal['personal_id']; ?>" <?php echo (isset($usuario['personal_id']) && $usuario['personal_id'] == $personal['personal_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($personal['personal_nombre'] . ' ' . $personal['personal_apellido'] . ' (' . $personal['personal_ci'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">
                                Seleccione un personal existente para asociarlo al usuario. 
                                <?php if ($action === 'add'): ?>
                                    Si no selecciona ninguno, el usuario se creará sin personal asociado.
                                <?php else: ?>
                                    Los datos personales (nombre, apellido, CI, teléfono) se obtienen del personal asociado.
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="modulo_id">Módulo *</label>
                            <select id="modulo_id" name="modulo_id" class="form-control" required>
                                <option value="" disabled <?php echo $usuario['modulo_id'] === '' ? 'selected' : ''; ?>>Seleccione...</option>
                                <?php foreach ($modulos as $modulo): ?>
                                    <option value="<?php echo (int)$modulo['modulo_id']; ?>" <?php echo $usuario['modulo_id'] == $modulo['modulo_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($modulo['modulo_nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="cargo_id">Cargo *</label>
                            <select id="cargo_id" name="cargo_id" class="form-control" required>
                                <option value="" disabled <?php echo $usuario['cargo_id'] === '' ? 'selected' : ''; ?>>Seleccione...</option>
                                <?php foreach ($cargos as $cargo): ?>
                                    <option value="<?php echo (int)$cargo['cargo_id']; ?>" <?php echo $usuario['cargo_id'] == $cargo['cargo_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cargo['descrip_cargo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <aside class="p-3 rounded mb-3" style="background: #fff1f2; border: 1px solid #fecdd3; color: #991b1b;">
                                <h6 class="font-weight-bold"><i class="fas fa-info-circle mr-1"></i>Importante</h6>
                                <ul class="small pl-3 mb-0">
                                    <li>Los campos con * son obligatorios.</li>
                                    <li>Personal es opcional y debe estar activo.</li>
                                    <li>El cargo depende del módulo seleccionado.</li>
                                    <li>La contraseña debe tener entre 8 y 15 caracteres e incluir un número.</li>
                                </ul>
                            </aside>
                        </div>
                    </div>

                    <?php if ($action === 'edit' && !empty($usuario['personal_id'])): ?>
                        <div class="alert alert-info">
                            <strong>Personal asociado:</strong><br>
                            <strong>Nombre:</strong> <?php echo htmlspecialchars($usuario['personal_nombre'] ?? ''); ?> <?php echo htmlspecialchars($usuario['personal_apellido'] ?? ''); ?><br>
                            <strong>Cédula:</strong> <?php echo htmlspecialchars($usuario['personal_ci'] ?? ''); ?><br>
                            <strong>Teléfono:</strong> <?php echo htmlspecialchars($usuario['personal_telefono'] ?? ''); ?>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-end border-top pt-3 mt-3">
                    <a href="view.php" class="btn btn-secondary mr-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php
$inline_js = 'var cargoActual = ' . json_encode((int)$usuario['cargo_id']) . ';' . PHP_EOL . <<<'JS'
        $(document).ready(function() {
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            function validarConfirmacion() {
                confirmInput.required = passwordInput.required || passwordInput.value !== '';
                confirmInput.setCustomValidity(passwordInput.value === confirmInput.value
                    ? '' : 'Las contraseñas no coinciden.');
            }
            passwordInput.addEventListener('input', validarConfirmacion);
            confirmInput.addEventListener('input', validarConfirmacion);
            confirmInput.form.addEventListener('submit', function(event) {
                validarConfirmacion();
                if (!confirmInput.form.reportValidity()) event.preventDefault();
            });
            var $moduloSelect = $('#modulo_id');
            var $cargoSelect = $('#cargo_id');
            
            // Función para cargar cargos según el módulo
            function cargarCargosPorModulo(moduloId) {
                if (!moduloId || moduloId === '') {
                    $cargoSelect.html('<option value="" disabled selected>Primero seleccione un módulo</option>');
                    $cargoSelect.prop('disabled', true);
                    return;
                }
                
                $cargoSelect.prop('disabled', true);
                $cargoSelect.html('<option value="">Cargando...</option>');
                
                $.ajax({
                    url: 'get_cargos_por_modulo.php',
                    method: 'GET',
                    data: { modulo_id: moduloId },
                    dataType: 'json',
                    success: function(response) {
                        $cargoSelect.html('<option value="" disabled>Seleccione un cargo</option>');
                        
                        if (response.cargos && response.cargos.length > 0) {
                            $.each(response.cargos, function(index, cargo) {
                                var selected = (cargo.cargo_id == cargoActual) ? 'selected' : '';
                                $cargoSelect.append(
                                    $('<option></option>')
                                        .attr('value', cargo.cargo_id)
                                        .attr('selected', selected)
                                        .text(cargo.descrip_cargo)
                                );
                            });
                            
                            // Si había un cargo seleccionado y está en la lista, mantenerlo
                            if (cargoActual > 0) {
                                $cargoSelect.val(cargoActual);
                            }
                            
                            $cargoSelect.prop('disabled', false);
                        } else {
                            $cargoSelect.html('<option value="" disabled>No hay cargos disponibles para este módulo</option>');
                            $cargoSelect.prop('disabled', true);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al cargar cargos:', error);
                        $cargoSelect.html('<option value="" disabled>Error al cargar cargos</option>');
                        $cargoSelect.prop('disabled', true);
                    }
                });
            }
            
            // Cargar cargos al cambiar el módulo
            $moduloSelect.on('change', function() {
                var moduloId = $(this).val();
                cargoActual = 0; // Reset cargo actual al cambiar módulo
                cargarCargosPorModulo(moduloId);
            });
            
            // Si ya hay un módulo seleccionado (modo edición), cargar sus cargos al inicio
            var moduloSeleccionado = $moduloSelect.val();
            if (moduloSeleccionado && moduloSeleccionado !== '') {
                cargarCargosPorModulo(moduloSeleccionado);
            } else {
                $cargoSelect.prop('disabled', true);
            }
        });
JS;
include PROJECT_ROOT . '/footer.php';
?>

