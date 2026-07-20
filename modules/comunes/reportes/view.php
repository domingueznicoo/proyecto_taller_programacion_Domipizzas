<?php
// Vista: listado y formulario de informes.
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();

if (empty($_SESSION['username'])) {
    echo "<script>alert('Token de sesión inválido'); window.location.href=loginUrl();</script>";
    exit;
}

require_once PROJECT_ROOT . '/config/database.php';
require_once __DIR__ . '/informes_helper.php';

if (!puedeAccederInformes()) {
    echo "<script>alert('Sin permiso para informes.'); window.location.href=indexUrl();</script>";
    exit;
}

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

$puedeCompras = puedeInformesCompras();
$puedeProduccion = puedeInformesProduccion();
$today = date('Y-m-d');

$page_title = 'Generador de Informes';
include PROJECT_ROOT . '/header.php';
?>

<div class="container-fluid py-3">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Generar Informes</h1>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filtros</h6>
        </div>
        <div class="card-body">
            <form id="form-informe" action="router_informes.php" method="GET" target="_blank" novalidate>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="movimiento">Movimiento</label>
                        <select id="movimiento" name="movimiento" class="form-control" required>
                            <option value="" selected disabled>Seleccione...</option>
                            <?php if ($puedeCompras): ?>
                            <optgroup label="Compras">
                                <option value="PEDIDO">Pedido de Compra</option>
                                <option value="PRESUPUESTO">Presupuesto de Compra</option>
                                <option value="ORDEN_COMPRA">Orden de Compra</option>
                                <option value="FACTURA">Factura de Compra</option>
                                <option value="NOTA_CREDITO">Nota de Crédito Compra</option>
                                <option value="NOTA_DEBITO">Nota de Débito Compra</option>
                                <option value="NOTA_REMISION">Nota de Remisión</option>
                                <option value="AJUSTES">Ajustes</option>
                                <option value="LIBRO_COMPRAS">Libro Compras</option>
                            </optgroup>
                            <?php endif; ?>
                            <?php if ($puedeProduccion): ?>
                            <optgroup label="Producción">
                                <option value="PEDIDO_PRODUCCION">Pedido de Producción</option>
                                <option value="ORDEN_PRODUCCION">Orden de Producción</option>
                                <option value="PEDIDO_MATERIA">Pedido de Materia Prima</option>
                                <option value="REPOSICION_MATERIA">Reposición de Materia Prima</option>
                                <option value="CONTROL_PRODUCCION">Control de Producción</option>
                                <option value="PRODUCTOS_TERMINADOS">Productos Terminados</option>
                                <option value="CONTROL_CALIDAD">Control de Calidad</option>
                                <option value="PERDIDAS">Pérdidas</option>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                        <div class="invalid-feedback">Seleccione un movimiento.</div>
                    </div>

                    <div class="form-group col-md-4" id="estado-group">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado" class="form-control" required>
                            <option value="" selected disabled>Seleccione...</option>
                        </select>
                        <div class="invalid-feedback">Seleccione un estado.</div>
                    </div>

                    <div class="form-group col-md-2">
                        <label for="desde">Desde</label>
                        <input type="date" id="desde" name="desde" class="form-control" required max="<?= htmlspecialchars($today) ?>">
                        <div class="invalid-feedback">Ingrese la fecha inicial.</div>
                    </div>

                    <div class="form-group col-md-2">
                        <label for="hasta">Hasta</label>
                        <input type="date" id="hasta" name="hasta" class="form-control" required max="<?= htmlspecialchars($today) ?>">
                        <div class="invalid-feedback">Ingrese la fecha final.</div>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-alt"></i> Generar informe
                    </button>
                </div>
            </form>
            <small class="text-muted d-block mt-3">
                El informe se abrirá en una nueva pestaña con los filtros seleccionados.
            </small>
        </div>
    </div>

    <?php
    if (!empty($_GET['alert'])) {
        $alertMessage = '';
        $alertClass = 'alert-success';
        switch ((int)$_GET['alert']) {
            case 4:
                $alertMessage = 'No se pudo realizar la operación.';
                $alertClass = 'alert-danger';
                break;
            case 6:
                $alertMessage = 'No se encontraron registros en el rango seleccionado.';
                $alertClass = 'alert-warning';
                break;
        }
        if ($alertMessage !== '') {
            echo "<div id='alert-message' class='alert $alertClass alert-dismissible fade show' role='alert'>
                    $alertMessage
                    <button type='button' class='close' data-dismiss='alert'><span>&times;</span></button>
                  </div>";
        }
    }
    ?>
</div>

<?php
$estadosJson = json_encode(estadosPorMovimientoInforme(), JSON_UNESCAPED_UNICODE);
$inline_js = <<<JS
setTimeout(function() {
    var alertMessage = document.getElementById('alert-message');
    if (alertMessage) alertMessage.style.display = 'none';
}, 3000);

document.addEventListener('DOMContentLoaded', () => {
    const movimientoSelect = document.getElementById('movimiento');
    const estadoSelect = document.getElementById('estado');
    const estadoGroup = document.getElementById('estado-group');
    const estadosPorMovimiento = {$estadosJson};
    const sinEstado = ['LIBRO_COMPRAS', 'PRODUCTOS_TERMINADOS'];

    const valorEstado = (label) => label === 'REPORTE TOTAL' ? 'TOTAL' : label;

    function limpiarEstadoSelect() {
        estadoSelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Seleccione...';
        placeholder.disabled = true;
        placeholder.selected = true;
        estadoSelect.appendChild(placeholder);
    }

    function poblarEstados(claveMovimiento) {
        limpiarEstadoSelect();
        (estadosPorMovimiento[claveMovimiento] || []).forEach(label => {
            const opt = document.createElement('option');
            opt.value = valorEstado(label);
            opt.textContent = label;
            estadoSelect.appendChild(opt);
        });
    }

    function actualizarEstadoSegunMovimiento() {
        const movimiento = movimientoSelect.value;
        const requiereEstado = movimiento && !sinEstado.includes(movimiento);

        if (requiereEstado) {
            if (estadoGroup) estadoGroup.classList.remove('d-none');
            estadoSelect.disabled = false;
            estadoSelect.required = true;
            poblarEstados(movimiento);
        } else {
            limpiarEstadoSelect();
            estadoSelect.disabled = true;
            estadoSelect.required = false;
            if (estadoGroup) estadoGroup.classList.toggle('d-none', sinEstado.includes(movimiento));
        }
    }

    movimientoSelect.addEventListener('change', actualizarEstadoSegunMovimiento);
    actualizarEstadoSegunMovimiento();
});

(function() {
    const form = document.getElementById('form-informe');
    const desde = document.getElementById('desde');
    const hasta = document.getElementById('hasta');
    const todayLocal = new Date(Date.now() - (new Date().getTimezoneOffset() * 60000)).toISOString().split('T')[0];

    const clampToToday = (input) => {
        if (input.value && input.value > todayLocal) input.value = todayLocal;
    };

    const syncHastaMin = () => {
        hasta.min = desde.value || '';
        if (hasta.value && desde.value && hasta.value < desde.value) hasta.value = desde.value;
    };

    [desde, hasta].forEach(input => { input.max = todayLocal; });
    desde.addEventListener('change', () => { clampToToday(desde); syncHastaMin(); });
    hasta.addEventListener('change', () => {
        clampToToday(hasta);
        if (desde.value && hasta.value && hasta.value < desde.value) hasta.value = desde.value;
    });

    form.addEventListener('submit', (e) => {
        if (!form.checkValidity()) {
            e.preventDefault();
            form.classList.add('was-validated');
            return;
        }
        if (desde.value && hasta.value && hasta.value < desde.value) {
            e.preventDefault();
            alert('La fecha Hasta no puede ser anterior a Desde.');
        }
    });

    syncHastaMin();
})();
JS;
include PROJECT_ROOT . '/footer.php';
