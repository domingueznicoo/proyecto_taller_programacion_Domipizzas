<?php
// Formulario de control de producción (alta o edición).
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';

$dsn = "pgsql:host=$host;port=$port;dbname=$database;";
$pdoForm = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

date_default_timezone_set('America/Asuncion');
$fechaHoy = date('Y-m-d');
$horaHoy = date('H:i');

$userSesion = $_SESSION['username'] ?? '';
$qUser = $pdoForm->prepare("
    SELECT u.id_usuario, u.username, s.descripcion_sucursal
    FROM usuarios u
    JOIN sucursales s ON s.id_sucursal = u.id_sucursal
    WHERE u.username = :user LIMIT 1
");
$qUser->execute([':user' => $userSesion]);
$usr = $qUser->fetch();
if (!$usr) {
    die('Usuario de sesión no encontrado.');
}

$ordenes = $pdoForm->query("
    SELECT op.orden_id, op.id_pedido_produccion, op.orden_prod_estado
    FROM orden_produccion op
    WHERE op.orden_prod_estado IN ('PENDIENTE', 'EN_PROCESO')
    ORDER BY op.orden_id DESC
")->fetchAll();

$inspectores = $pdoForm->query("
    SELECT i.id_inspectores,
           TRIM(per.personal_nombre || ' ' || per.personal_apellido) AS nombre
    FROM inspectores i
    JOIN personal per ON per.id_personal = i.id_personal
    WHERE UPPER(TRIM(i.inspector_estado)) = 'ACTIVO'
    ORDER BY per.personal_apellido, per.personal_nombre
")->fetchAll();

if (isset($_GET['form_control'], $_GET['form']) && $_GET['form'] === 'add' && $_GET['form_control'] === 'add'):
$ordenPre = isset($_GET['orden_id']) ? (int)$_GET['orden_id'] : 0;
$productoPre = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;
$bloquearLinea = $ordenPre > 0 && $productoPre > 0;
$ordenLabel = '';
$productoLabel = '';
if ($bloquearLinea) {
    $stOrd = $pdoForm->prepare("
        SELECT op.orden_id, op.id_pedido_produccion, op.orden_prod_estado
        FROM orden_produccion op
        WHERE op.orden_id = :id
    ");
    $stOrd->execute([':id' => $ordenPre]);
    $ordRow = $stOrd->fetch();
    if ($ordRow) {
        $ordenLabel = 'OP #' . (int)$ordRow['orden_id']
            . ' — Pedido #' . (int)$ordRow['id_pedido_produccion']
            . ' (' . $ordRow['orden_prod_estado'] . ')';
    }
    $stProd = $pdoForm->prepare("
        SELECT p.producto_descri
        FROM productos p
        JOIN orden_detalle_produccion d ON d.producto_id = p.producto_id AND d.orden_id = :orden
        WHERE p.producto_id = :producto
    ");
    $stProd->execute([':orden' => $ordenPre, ':producto' => $productoPre]);
    $productoLabel = (string)($stProd->fetchColumn() ?: '');
    if ($ordenLabel === '' || $productoLabel === '') {
        header('Location: view.php?alert=4&msg=' . urlencode('La línea de producción seleccionada no es válida.'));
        exit;
    }
}
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-plus-circle"></i> Registrar control de producción</h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Control de producción</a></li>
    <li class="breadcrumb-item active">Nuevo</li>
  </ol>

  <div class="alert alert-info small mb-3">
    Las etapas avanzan en orden (Preparación → Cocción → Armado → Empaque).
    Antes de cada control debe existir un <strong>equipo de trabajo</strong> registrado para la OP y la etapa.
    El <strong>consumo de materia prima</strong> se registra solo en la primera etapa (Preparación).
    Solo en <strong>Empaque</strong> se descuenta el pendiente de la orden.
  </div>

  <div class="alert alert-warning d-none small mb-3" id="alert-sin-equipo" role="alert"></div>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=insert" method="POST" id="form-control-add">
      <div class="row mb-3">
        <div class="col-md-2"><label>Fecha</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($fechaHoy) ?>" readonly></div>
        <div class="col-md-2"><label>Hora</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($horaHoy) ?>" readonly></div>
        <div class="col-md-4"><label>Usuario</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['username']) ?>" readonly></div>
        <div class="col-md-4"><label>Sucursal</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['descripcion_sucursal']) ?>" readonly></div>
      </div>

      <div class="row mb-3">
        <?php if ($bloquearLinea): ?>
        <div class="col-md-4">
          <label>Orden de producción</label>
          <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($ordenLabel) ?>" readonly>
          <input type="hidden" name="orden_id" id="orden_id" value="<?= (int)$ordenPre ?>">
        </div>
        <div class="col-md-4">
          <label>Producto</label>
          <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($productoLabel) ?>" readonly>
          <input type="hidden" name="producto_id" id="producto_id" value="<?= (int)$productoPre ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <p class="small text-muted mb-2">Línea fijada desde el listado. Para otra OP o producto, use <a href="view.php">Volver al listado</a>.</p>
        </div>
        <?php else: ?>
        <div class="col-md-4">
          <label for="orden_id">Orden de producción <span class="text-danger">*</span></label>
          <select class="form-control" name="orden_id" id="orden_id" required>
            <option value="">Seleccione OP</option>
            <?php foreach ($ordenes as $o): ?>
            <option value="<?= (int)$o['orden_id'] ?>">
              OP #<?= (int)$o['orden_id'] ?> — Pedido #<?= (int)$o['id_pedido_produccion'] ?> (<?= htmlspecialchars($o['orden_prod_estado']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label for="producto_id">Producto <span class="text-danger">*</span></label>
          <select class="form-control" name="producto_id" id="producto_id" required disabled>
            <option value="">Seleccione orden primero</option>
          </select>
        </div>
        <?php endif; ?>
      </div>

      <div id="panel-ruta" class="d-none mb-3">
        <h6 class="font-weight-bold">Ruta de producción</h6>
        <div id="ruta-etapas" class="d-flex flex-wrap mb-3"></div>
        <div class="card border-primary mb-0">
          <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
              <div>
                <span class="text-muted small d-block">Siguiente paso en la línea</span>
                <h5 class="mb-0 text-primary" id="lbl-etapa-actual">—</h5>
              </div>
              <div class="text-right mt-2 mt-md-0">
                <span class="badge badge-light border" id="badge-max-etapa">Máx. — u.</span>
              </div>
            </div>
            <div id="panel-equipo-etapa" class="mt-3 pt-3 border-top d-none">
              <span class="text-muted small d-block mb-1"><i class="fas fa-users"></i> Equipo de trabajo asignado</span>
              <div id="equipo-etapa-body" class="small"></div>
            </div>
          </div>
        </div>
        <input type="hidden" name="etapa_id" id="etapa_id" value="">
      </div>

      <div class="row mb-3">
        <div class="col-md-3">
          <label for="cantidad_procesada">Cantidad procesada <span class="text-danger">*</span></label>
          <input type="number" class="form-control" name="cantidad_procesada" id="cantidad_procesada" min="1" step="1" required disabled>
          <small class="text-muted" id="hint-pendiente"></small>
        </div>
        <div class="col-md-5">
          <label for="id_inspectores">Inspector <span class="text-danger">*</span></label>
          <select class="form-control" name="id_inspectores" id="id_inspectores" required>
            <option value="">Seleccione inspector</option>
            <?php foreach ($inspectores as $insp): ?>
            <option value="<?= (int)$insp['id_inspectores'] ?>"><?= htmlspecialchars($insp['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label for="control_observacion">Observaciones</label>
          <textarea class="form-control" name="control_observacion" id="control_observacion" rows="2"></textarea>
        </div>
      </div>

      <div id="panel-consumos-mp" class="d-none">
      <h6 class="font-weight-bold">Consumos de materia prima</h6>
      <p class="small text-muted mb-2" id="receta-hint">
        Al elegir producto y cantidad procesada se precargará la receta. Puede ajustar cantidades, quitar líneas o agregar insumos extra.
      </p>
      <div class="row align-items-end mb-2">
        <div class="col-md-6">
          <label for="materia_sel">Materia prima</label>
          <select class="form-control" id="materia_sel" disabled>
            <option value="">Cargando...</option>
          </select>
        </div>
        <div class="col-md-4">
          <label for="cantidad_consumo">Cantidad</label>
          <input type="number" class="form-control" id="cantidad_consumo" min="1" step="1" disabled>
        </div>
        <div class="col-md-2">
          <button type="button" class="btn btn-primary w-100" id="btn-agregar-consumo" disabled>Agregar</button>
        </div>
      </div>

      <div class="table-responsive mb-3">
        <table class="table table-bordered table-sm" id="tabla-consumos">
          <thead class="table-dark"><tr><th>Materia prima</th><th>Cantidad</th><th>Acción</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
      </div>
      <p id="sin-consumos-etapa" class="small text-muted mb-3 d-none">
        En esta etapa no se registran consumos de materia prima (ya fueron descontados en Preparación).
      </p>
      <input type="hidden" name="consumos" id="consumos" value="[]">

      <div class="d-flex justify-content-end align-items-center flex-wrap">
        <button type="submit" name="Guardar" id="btn-guardar-etapa" class="btn btn-primary btn-lg shadow-sm mr-2 mb-2" disabled>
          <i class="fas fa-arrow-right"></i> Registrar etapa
        </button>
        <a href="view.php" class="btn btn-warning mb-2">Cancelar</a>
      </div>
    </form>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const PRE_ORDEN = <?= (int)$ordenPre ?>;
  const PRE_PRODUCTO = <?= (int)$productoPre ?>;
  const BLOQUEAR_LINEA = <?= $bloquearLinea ? 'true' : 'false' ?>;

  const selOrden = document.getElementById('orden_id');
  const selProducto = document.getElementById('producto_id');
  const hidEtapa = document.getElementById('etapa_id');
  const panelRuta = document.getElementById('panel-ruta');
  const rutaEtapas = document.getElementById('ruta-etapas');
  const lblEtapa = document.getElementById('lbl-etapa-actual');
  const badgeMax = document.getElementById('badge-max-etapa');
  const btnGuardarEtapa = document.getElementById('btn-guardar-etapa');
  const cantProc = document.getElementById('cantidad_procesada');
  const hintPend = document.getElementById('hint-pendiente');
  const selMateria = document.getElementById('materia_sel');
  const cantCons = document.getElementById('cantidad_consumo');
  const btnAgregar = document.getElementById('btn-agregar-consumo');
  const tbodyCons = document.querySelector('#tabla-consumos tbody');
  const hidCons = document.getElementById('consumos');
  const consumos = [];
  let pendienteMax = 0;
  let etapaLista = false;
  let materiasMap = {};
  const recetaHint = document.getElementById('receta-hint');
  const panelConsumosMp = document.getElementById('panel-consumos-mp');
  const sinConsumosEtapa = document.getElementById('sin-consumos-etapa');
  const alertSinEquipo = document.getElementById('alert-sin-equipo');
  const panelEquipoEtapa = document.getElementById('panel-equipo-etapa');
  const equipoEtapaBody = document.getElementById('equipo-etapa-body');
  let productoRecetaId = 0;
  let requiereConsumoMp = false;
  let equipoOk = false;

  function ocultarAlertaEquipo() {
    if (alertSinEquipo) {
      alertSinEquipo.classList.add('d-none');
      alertSinEquipo.innerHTML = '';
    }
  }

  function syncConsumos() {
    hidCons.value = JSON.stringify(consumos);
  }

  function ocultarPanelEquipo() {
    if (panelEquipoEtapa) panelEquipoEtapa.classList.add('d-none');
    if (equipoEtapaBody) equipoEtapaBody.innerHTML = '';
  }

  function badgeEstadoEquipo(est) {
    const e = String(est || '').toUpperCase();
    if (e === 'ACTIVO') return 'success';
    if (e === 'PENDIENTE') return 'warning';
    return 'secondary';
  }

  function renderEquipoEtapa(equipo) {
    if (!panelEquipoEtapa || !equipoEtapaBody || !equipo) {
      ocultarPanelEquipo();
      return;
    }
    const estado = equipo.equipo_estado || 'PENDIENTE';
    let html = '<div class="d-flex flex-wrap align-items-center mb-2">'
      + '<strong class="mr-2">' + (equipo.equipo_descri || 'Equipo #' + equipo.equipo_id) + '</strong>'
      + '<span class="badge badge-' + badgeEstadoEquipo(estado) + '">' + estado + '</span>'
      + '<span class="text-muted ml-2">(' + (equipo.num_trab || 0) + ' integrante(s))</span>'
      + '</div>';
    if (equipo.miembros && equipo.miembros.length) {
      html += '<ul class="mb-0 pl-3">';
      equipo.miembros.forEach(m => {
        const rol = m.trabajador_rol ? ' <span class="text-muted">(' + m.trabajador_rol + ')</span>' : '';
        html += '<li>' + m.nombre + ' — <em>' + m.tarea_rol + '</em>' + rol + '</li>';
      });
      html += '</ul>';
    }
    equipoEtapaBody.innerHTML = html;
    panelEquipoEtapa.classList.remove('d-none');
  }

  function mostrarAlertaEquipo(data) {
    ocultarPanelEquipo();
    if (!alertSinEquipo) return;
    const oid = data.orden_id || parseInt(selOrden?.value, 10) || PRE_ORDEN || 0;
    const etapaNom = data.etapa_nombre ? ' — ' + data.etapa_nombre : '';
    alertSinEquipo.innerHTML = '<strong><i class="fas fa-exclamation-triangle"></i> Sin equipo para esta etapa.</strong> '
      + (data.error || 'Registre un equipo de trabajo antes de controlar la producción.')
      + ' <a class="alert-link font-weight-bold" href="../equipos_produccion/view.php?form_equipo=add&amp;form=add&amp;orden_id='
      + oid + '">Cargar equipo' + etapaNom + '</a>.';
    alertSinEquipo.classList.remove('d-none');
  }

  function bloquearFormularioPorEquipo() {
    etapaLista = false;
    equipoOk = false;
    ocultarPanelEquipo();
    hidEtapa.value = '';
    cantProc.disabled = true;
    cantProc.value = '';
    cantProc.readOnly = false;
    cantProc.classList.remove('bg-light');
    panelRuta.classList.add('d-none');
    actualizarBotonEtapa(null, 0, false);
    aplicarModoConsumos(false);
    hintPend.textContent = '';
  }

  function cantidadEntera(valor) {
    const n = parseInt(String(valor), 10);
    return Number.isFinite(n) && n > 0 ? n : 0;
  }

  function renderConsumos() {
    tbodyCons.innerHTML = '';
    if (consumos.length === 0) {
      tbodyCons.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Sin consumos cargados</td></tr>';
      syncConsumos();
      return;
    }
    consumos.forEach((c, i) => {
      const tr = document.createElement('tr');
      const desdeReceta = c.cantidad_por_unidad != null ? ' <span class="badge badge-info">receta</span>' : '';
      tr.innerHTML = `<td>${c.nombre}${desdeReceta}</td>
        <td><input type="number" class="form-control form-control-sm inp-cant-cons" data-i="${i}"
             value="${cantidadEntera(c.cantidad)}" min="1" step="1"></td>
        <td><button type="button" class="btn btn-danger btn-sm btn-quitar-cons" data-i="${i}"><i class="fas fa-times"></i></button></td>`;
      tbodyCons.appendChild(tr);
    });
    syncConsumos();
  }

  function aplicarModoConsumos(requiere) {
    requiereConsumoMp = !!requiere;
    if (requiereConsumoMp) {
      panelConsumosMp.classList.remove('d-none');
      sinConsumosEtapa.classList.add('d-none');
      renderConsumos();
    } else {
      panelConsumosMp.classList.add('d-none');
      sinConsumosEtapa.classList.remove('d-none');
      productoRecetaId = 0;
      consumos.length = 0;
      syncConsumos();
    }
  }

  function cargarReceta(productoId) {
    if (!productoId || !requiereConsumoMp) return;
    const mult = parseInt(cantProc.value, 10) || 1;
    fetch('get_receta.php?producto_id=' + productoId + '&cantidad=' + mult)
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          recetaHint.textContent = data.error || 'No se pudo cargar la receta.';
          return;
        }
        if (!data.tiene_receta || !data.items.length) {
          consumos.length = 0;
          renderConsumos();
          recetaHint.textContent = data.mensaje || 'Sin receta: agregue MP manualmente.';
          recetaHint.className = 'small text-warning mb-2';
          return;
        }
        consumos.length = 0;
        data.items.forEach(item => {
          consumos.push({
            id_materia_prima: item.id_materia_prima,
            nombre: item.materia_prima_descripcion,
            cantidad: cantidadEntera(item.cantidad_sugerida),
            cantidad_por_unidad: cantidadEntera(item.cantidad_por_unidad),
          });
        });
        renderConsumos();
        recetaHint.textContent = 'Receta cargada para ' + mult + ' unidad(es). Ajuste cantidades si corresponde.';
        recetaHint.className = 'small text-success mb-2';
      })
      .catch(() => {
        recetaHint.textContent = 'Error al cargar la receta.';
        recetaHint.className = 'small text-danger mb-2';
      });
  }

  function recalcularRecetaPorCantidad() {
    const mult = parseInt(cantProc.value, 10) || 0;
    if (!mult || !productoRecetaId) return;
    let cambio = false;
    consumos.forEach(c => {
      if (c.cantidad_por_unidad != null) {
        c.cantidad = cantidadEntera(c.cantidad_por_unidad) * mult;
        cambio = true;
      }
    });
    if (cambio) {
      renderConsumos();
      recetaHint.textContent = 'Cantidades recalculadas según receta × ' + mult + ' unidad(es).';
      recetaHint.className = 'small text-success mb-2';
    }
  }

  fetch('get_materias_stock.php').then(r => r.json()).then(data => {
    selMateria.innerHTML = '<option value="">Seleccione materia prima</option>';
    if (data.success && data.materias) {
      data.materias.forEach(m => {
        materiasMap[m.id_materia_prima] = m;
        const opt = document.createElement('option');
        opt.value = m.id_materia_prima;
        opt.textContent = m.materia_prima_descripcion + ' (stock: ' + m.stock_total + ')';
        opt.dataset.stock = m.stock_total;
        selMateria.appendChild(opt);
      });
      selMateria.disabled = false;
      cantCons.disabled = false;
      btnAgregar.disabled = false;
    }
  });

  if (!BLOQUEAR_LINEA && selOrden) {
  selOrden.addEventListener('change', () => {
    const id = parseInt(selOrden.value, 10);
    selProducto.innerHTML = '<option value="">Cargando...</option>';
    selProducto.disabled = true;
    panelRuta.classList.add('d-none');
    hidEtapa.value = '';
    lblEtapa.textContent = '—';
    badgeMax.textContent = 'Máx. — u.';
    actualizarBotonEtapa(null, 0, false);
    rutaEtapas.innerHTML = '';
    cantProc.disabled = true;
    etapaLista = false;
    cantProc.value = '';
    hintPend.textContent = '';
    productoRecetaId = 0;
    consumos.length = 0;
    renderConsumos();
    if (!id) {
      selProducto.innerHTML = '<option value="">Seleccione orden primero</option>';
      return;
    }
    fetch('get_orden_info.php?orden_id=' + id).then(r => r.json()).then(data => {
      selProducto.innerHTML = '<option value="">Seleccione producto</option>';
      if (!data.success) {
        alert(data.error || 'Error');
        return;
      }
      data.productos.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.producto_id;
        const sig = p.siguiente_etapa_nombre ? ' → ' + p.siguiente_etapa_nombre : '';
        opt.textContent = p.producto_descri + sig + ' (máx. ' + p.cantidad_max_etapa + ')';
        opt.dataset.maxEtapa = p.cantidad_max_etapa;
        selProducto.appendChild(opt);
      });
      selProducto.disabled = false;
    });
  });
  }

  function renderRuta(resumen, siguiente) {
    rutaEtapas.innerHTML = '';
    (resumen || []).forEach(e => {
      const badge = document.createElement('span');
      const done = e.cantidad_disponible === 0 && e.cantidad_procesada > 0;
      const active = siguiente && parseInt(siguiente.etapa_id, 10) === parseInt(e.etapa_id, 10);
      badge.className = 'badge mr-1 mb-1 ' + (active ? 'badge-primary' : (done ? 'badge-success' : 'badge-secondary'));
      badge.textContent = e.etapa_secuencia + '. ' + e.etapa_nombre + ' (' + e.cantidad_procesada + '/' + (e.cantidad_procesada + e.cantidad_disponible) + ')';
      rutaEtapas.appendChild(badge);
    });
  }

  function actualizarBotonEtapa(nombreEtapa, maxCant, esUltima) {
    if (!nombreEtapa) {
      btnGuardarEtapa.disabled = true;
      btnGuardarEtapa.innerHTML = '<i class="fas fa-arrow-right"></i> Registrar etapa';
      return;
    }
    btnGuardarEtapa.disabled = false;
    const extra = esUltima ? ' (empaque)' : '';
    btnGuardarEtapa.innerHTML = '<i class="fas fa-arrow-right"></i> Registrar: ' + nombreEtapa + extra;
  }

  function cargarSiguienteEtapa() {
    const oid = parseInt(selOrden.value, 10);
    const pid = parseInt(selProducto.value, 10);
    panelRuta.classList.add('d-none');
    ocultarAlertaEquipo();
    ocultarPanelEquipo();
    equipoOk = false;
    hidEtapa.value = '';
    etapaLista = false;
    cantProc.disabled = true;
    cantProc.readOnly = false;
    cantProc.classList.remove('bg-light');
    cantProc.value = '';
    actualizarBotonEtapa(null, 0, false);
    sinConsumosEtapa.classList.add('d-none');
    panelConsumosMp.classList.add('d-none');
    if (!oid || !pid) return;
    fetch('get_siguiente_etapa.php?orden_id=' + oid + '&producto_id=' + pid)
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          if (data.requiere_equipo) {
            mostrarAlertaEquipo(data);
            bloquearFormularioPorEquipo();
            return;
          }
          alert(data.error || 'Error');
          bloquearFormularioPorEquipo();
          return;
        }
        ocultarAlertaEquipo();
        equipoOk = true;
        if (data.completado) {
          alert('Todas las etapas están completas. Registre productos terminados.');
          if (!BLOQUEAR_LINEA && selProducto && selProducto.tagName === 'SELECT') {
            selProducto.value = '';
          }
          return;
        }
        const sig = data.siguiente_etapa;
        pendienteMax = parseInt(data.cantidad_maxima, 10) || 0;
        hidEtapa.value = sig.etapa_id;
        const label = sig.etapa_secuencia + '. ' + sig.etapa_nombre;
        lblEtapa.textContent = label;
        badgeMax.textContent = 'Máx. ' + pendienteMax + ' u.';
        renderRuta(data.resumen_etapas, sig);
        panelRuta.classList.remove('d-none');
        cantProc.max = pendienteMax;
        cantProc.disabled = false;
        etapaLista = true;
        if (!data.requiere_consumo_mp && pendienteMax > 0) {
          cantProc.value = String(pendienteMax);
          cantProc.readOnly = true;
          cantProc.classList.add('bg-light');
        } else {
          cantProc.readOnly = false;
          cantProc.classList.remove('bg-light');
        }
        actualizarBotonEtapa(sig.etapa_nombre, pendienteMax, data.es_ultima_etapa);
        const ultima = data.es_ultima_etapa ? ' — última etapa: actualiza pendiente OP' : '';
        const heredada = !data.requiere_consumo_mp && pendienteMax > 0
          ? ' Cantidad heredada de la etapa anterior.'
          : '';
        hintPend.textContent = 'Cantidad máxima en esta etapa: ' + pendienteMax + ultima + heredada;
        renderEquipoEtapa(data.equipo || null);
        aplicarModoConsumos(!!data.requiere_consumo_mp);
        if (data.requiere_consumo_mp) {
          const pid = parseInt(selProducto.value, 10);
          productoRecetaId = pid;
          cargarReceta(pid);
        }
      })
      .catch(() => {
        alert('No se pudo cargar la información de la etapa.');
      });
  }

  async function aplicarPreseleccion() {
    if (BLOQUEAR_LINEA) {
      cargarSiguienteEtapa();
      return;
    }
    if (!PRE_ORDEN || !selOrden) return;
    selOrden.value = String(PRE_ORDEN);
    const r = await fetch('get_orden_info.php?orden_id=' + PRE_ORDEN);
    const data = await r.json();
    if (!data.success) return;
    selProducto.innerHTML = '<option value="">Seleccione producto</option>';
    data.productos.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.producto_id;
      const sig = p.siguiente_etapa_nombre ? ' → ' + p.siguiente_etapa_nombre : '';
      opt.textContent = p.producto_descri + sig + ' (máx. ' + p.cantidad_max_etapa + ')';
      selProducto.appendChild(opt);
    });
    selProducto.disabled = false;
    if (PRE_PRODUCTO) {
      selProducto.value = String(PRE_PRODUCTO);
      cargarSiguienteEtapa();
    }
  }

  aplicarPreseleccion();

  if (!BLOQUEAR_LINEA && selProducto) {
  selProducto.addEventListener('change', () => {
    const pid = parseInt(selProducto.value, 10);
    pendienteMax = 0;
    hintPend.textContent = '';
    cantProc.value = '';
    consumos.length = 0;
    renderConsumos();
    if (!pid) {
      panelRuta.classList.add('d-none');
      aplicarModoConsumos(false);
      return;
    }
    cargarSiguienteEtapa();
  });
  }

  cantProc.addEventListener('input', () => {
    if (productoRecetaId && consumos.some(c => c.cantidad_por_unidad != null)) {
      recalcularRecetaPorCantidad();
    } else if (productoRecetaId && parseInt(cantProc.value, 10) > 0) {
      cargarReceta(productoRecetaId);
    }
  });

  tbodyCons.addEventListener('input', e => {
    const inp = e.target.closest('.inp-cant-cons');
    if (!inp) return;
    const idx = parseInt(inp.dataset.i, 10);
    const val = cantidadEntera(inp.value);
    if (consumos[idx] && val > 0) {
      consumos[idx].cantidad = val;
      delete consumos[idx].cantidad_por_unidad;
      syncConsumos();
    }
  });

  btnAgregar.addEventListener('click', () => {
    const mid = parseInt(selMateria.value, 10);
    const cant = cantidadEntera(cantCons.value);
    const m = materiasMap[mid];
    if (!mid || !cant) {
      alert('Seleccione materia prima y una cantidad entera mayor a cero.');
      return;
    }
    if (cant > parseInt(m.stock_total, 10)) {
      alert('Cantidad supera el stock disponible (' + m.stock_total + ').');
      return;
    }
    const exist = consumos.findIndex(c => c.id_materia_prima === mid);
    if (exist >= 0) {
      consumos[exist].cantidad = cantidadEntera(consumos[exist].cantidad) + cant;
      delete consumos[exist].cantidad_por_unidad;
    } else {
      consumos.push({ id_materia_prima: mid, cantidad: cant, nombre: m.materia_prima_descripcion });
    }
    renderConsumos();
    cantCons.value = '';
  });

  tbodyCons.addEventListener('click', e => {
    const btn = e.target.closest('.btn-quitar-cons');
    if (!btn) return;
    consumos.splice(parseInt(btn.dataset.i, 10), 1);
    renderConsumos();
  });

  document.getElementById('form-control-add').addEventListener('submit', e => {
    if (!equipoOk || !etapaLista || !hidEtapa.value) {
      e.preventDefault();
      if (alertSinEquipo && !alertSinEquipo.classList.contains('d-none')) {
        alertSinEquipo.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } else {
        alert('Seleccione orden y producto. Debe existir un equipo de trabajo para la etapa antes de controlar.');
      }
      return;
    }
    if (requiereConsumoMp && consumos.length === 0) {
      e.preventDefault();
      alert('Agregue al menos un consumo de materia prima en la etapa de preparación.');
      return;
    }
    const cant = parseInt(cantProc.value, 10);
    if (!cant || cant <= 0) {
      e.preventDefault();
      alert('Ingrese la cantidad procesada.');
      return;
    }
    if (cant > pendienteMax) {
      e.preventDefault();
      alert('La cantidad supera el máximo de esta etapa (' + pendienteMax + ').');
    }
  });
});
</script>

<?php
elseif (isset($_GET['form_control'], $_GET['form'], $_GET['control_id']) && $_GET['form'] === 'edit'):
    $controlId = (int)$_GET['control_id'];
    $st = $pdoForm->prepare("
        SELECT c.*, cd.control_cantidad
        FROM control_produccion c
        JOIN control_produccion_detalle cd ON cd.control_id = c.control_id
        WHERE c.control_id = :id
    ");
    $st->execute([':id' => $controlId]);
    $cab = $st->fetch();
    if (!$cab || strtoupper($cab['control_estado']) !== 'REGISTRADO') {
        header('Location: view.php?alert=5');
        exit;
    }
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-edit"></i> Editar control #<?= $controlId ?></h1>
  <p class="text-muted">Solo puede modificar inspector y observaciones. Cantidades y consumos no son editables.</p>
  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=update" method="POST">
      <input type="hidden" name="control_id" value="<?= $controlId ?>">
      <div class="row mb-3">
        <div class="col-md-4">
          <label>Orden N°</label>
          <input type="text" class="form-control" value="<?= (int)$cab['orden_id'] ?>" readonly>
        </div>
        <div class="col-md-4">
          <label for="id_inspectores">Inspector <span class="text-danger">*</span></label>
          <select class="form-control" name="id_inspectores" id="id_inspectores" required>
            <?php foreach ($inspectores as $insp): ?>
            <option value="<?= (int)$insp['id_inspectores'] ?>"<?= (int)$cab['id_inspectores'] === (int)$insp['id_inspectores'] ? ' selected' : '' ?>>
              <?= htmlspecialchars($insp['nombre']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label for="control_observacion">Observaciones</label>
          <textarea class="form-control" name="control_observacion" rows="2"><?= htmlspecialchars($cab['control_observacion'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="d-flex justify-content-end">
        <button type="submit" name="Guardar" class="btn btn-success me-2">Guardar</button>
        <a href="view.php" class="btn btn-warning">Cancelar</a>
      </div>
    </form>
  </div></div>
</div>
<?php else: ?>
<script>window.location.href = 'view.php';</script>
<?php endif; ?>
