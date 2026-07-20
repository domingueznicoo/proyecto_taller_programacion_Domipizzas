<?php
// Formulario de pérdidas de producción (alta o edición).
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once __DIR__ . '/perdidas_helper.php';

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
    SELECT u.username, s.descripcion_sucursal FROM usuarios u
    JOIN sucursales s ON s.id_sucursal = u.id_sucursal
    WHERE u.username = :user LIMIT 1
");
$qUser->execute([':user' => $userSesion]);
$usr = $qUser->fetch();

$tiposPerdida = $pdoForm->query("
    SELECT tipo_perdida_id, tipo_perdida_descri FROM tipo_perdida ORDER BY tipo_perdida_id
")->fetchAll();
if (empty($tiposPerdida)) {
    $tiposPerdida = [
        ['tipo_perdida_id' => TIPO_MERMA_CALIDAD, 'tipo_perdida_descri' => 'Merma por calidad'],
        ['tipo_perdida_id' => TIPO_PERDIDA_PROCESO, 'tipo_perdida_descri' => 'Pérdida en proceso'],
    ];
}

$calidades = $pdoForm->query("
    SELECT cc.calidad_id, cc.terminado_id, pt.orden_id, cc.calidad_fecha
    FROM control_calidad_produccion cc
    JOIN producto_terminado pt ON pt.terminado_id = cc.terminado_id
    WHERE UPPER(TRIM(cc.calidad_estado)) = 'NO CONFORME'
      AND NOT EXISTS (
          SELECT 1 FROM perdidas pe
          WHERE pe.calidad_id = cc.calidad_id
            AND UPPER(TRIM(pe.perdida_estado)) = 'REGISTRADO'
      )
    ORDER BY cc.calidad_id DESC
")->fetchAll();

$controles = $pdoForm->query("
    SELECT c.control_id, c.orden_id, c.control_fecha, cd.control_cantidad,
           p.producto_descri, cd.control_descri AS etapa_nombre
    FROM control_produccion c
    JOIN control_produccion_detalle cd ON cd.control_id = c.control_id AND cd.producto_id = c.producto_id
    JOIN productos p ON p.producto_id = c.producto_id
    JOIN orden_produccion op ON op.orden_id = c.orden_id
    WHERE UPPER(TRIM(c.control_estado)) = 'REGISTRADO'
      AND UPPER(TRIM(op.orden_prod_estado)) NOT IN ('ANULADA', 'ANULADO')
      AND NOT EXISTS (SELECT 1 FROM producto_terminado pt WHERE pt.orden_id = c.orden_id)
      AND NOT EXISTS (
          SELECT 1 FROM perdidas pe
          WHERE pe.control_id = c.control_id
            AND UPPER(TRIM(pe.perdida_estado)) = 'REGISTRADO'
      )
    ORDER BY c.control_id DESC
")->fetchAll();

$calidadPreseleccion = isset($_GET['calidad_id']) ? (int)$_GET['calidad_id'] : 0;
$controlPreseleccion = isset($_GET['control_id']) ? (int)$_GET['control_id'] : 0;
$tipoPreseleccion = $calidadPreseleccion > 0 ? TIPO_MERMA_CALIDAD : ($controlPreseleccion > 0 ? TIPO_PERDIDA_PROCESO : TIPO_MERMA_CALIDAD);

$modoEdit = isset($_GET['form_perdida'], $_GET['form'], $_GET['perdidas_id'])
    && $_GET['form_perdida'] === 'edit' && $_GET['form'] === 'edit';
$perdidaEdit = $modoEdit ? (int)$_GET['perdidas_id'] : 0;
$perData = null;
$detalleEdit = [];

if ($modoEdit && $perdidaEdit > 0) {
    $st = $pdoForm->prepare("
        SELECT perdidas_id, perdida_fecha, perdida_estado, tipo_perdida_id, calidad_id, control_id
        FROM perdidas WHERE perdidas_id = :id
    ");
    $st->execute([':id' => $perdidaEdit]);
    $perData = $st->fetch();
    if (!$perData || strtoupper(trim((string)$perData['perdida_estado'])) !== 'REGISTRADO') {
        die('Registro no disponible para edición.');
    }
    $detalleEdit = obtenerLineasDetallePerdida($pdoForm, $perdidaEdit);
}

$esAdd = !$modoEdit && isset($_GET['form_perdida'], $_GET['form'])
    && $_GET['form_perdida'] === 'add' && $_GET['form'] === 'add';

if ($esAdd || ($modoEdit && $perData)):
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800">
    <i class="fas fa-trash-alt"></i>
    <?= $modoEdit ? 'Editar pérdida #' . (int)$perData['perdidas_id'] : 'Registrar pérdida' ?>
  </h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Pérdidas</a></li>
    <li class="breadcrumb-item active"><?= $modoEdit ? 'Editar' : 'Nuevo' ?></li>
  </ol>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=<?= $modoEdit ? 'update' : 'insert' ?>" method="POST" id="form-perdida">
      <?php if ($modoEdit): ?>
      <input type="hidden" name="perdidas_id" value="<?= (int)$perData['perdidas_id'] ?>">
      <?php endif; ?>

      <div class="row mb-3">
        <div class="col-md-2">
          <label>Fecha</label>
          <input type="date" class="form-control" name="perdida_fecha"
            value="<?= htmlspecialchars(substr((string)($modoEdit ? $perData['perdida_fecha'] : $fechaHoy), 0, 10)) ?>" required>
        </div>
        <div class="col-md-2">
          <label>Hora</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($horaHoy) ?>" readonly>
        </div>
        <div class="col-md-3">
          <label>Usuario</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['username'] ?? '') ?>" readonly>
        </div>
        <div class="col-md-3">
          <label>Sucursal</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['descripcion_sucursal'] ?? '') ?>" readonly>
        </div>
        <div class="col-md-2">
          <label>Tipo de pérdida</label>
          <select class="form-control" name="tipo_perdida_id" id="tipo_perdida_id" required <?= $modoEdit ? 'disabled' : '' ?>>
            <?php foreach ($tiposPerdida as $t): ?>
            <?php
              $tid = (int)$t['tipo_perdida_id'];
              $sel = $modoEdit
                  ? (int)$perData['tipo_perdida_id'] === $tid
                  : $tipoPreseleccion === $tid;
              $origenTipo = clasificarTipoPerdida($pdoForm, $tid) ?? 'calidad';
            ?>
            <option value="<?= $tid ?>" data-origen="<?= htmlspecialchars($origenTipo) ?>" <?= $sel ? 'selected' : '' ?>>
              <?= htmlspecialchars($t['tipo_perdida_descri']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php if ($modoEdit): ?>
          <input type="hidden" name="tipo_perdida_id" value="<?= (int)$perData['tipo_perdida_id'] ?>">
          <?php endif; ?>
        </div>
      </div>

      <div id="panel-calidad" class="row mb-3 <?= ($modoEdit && empty($perData['calidad_id'])) ? 'd-none' : '' ?>">
        <div class="col-md-8">
          <label for="calidad_id">Control de calidad (NO CONFORME) <span class="text-danger">*</span></label>
          <?php if ($modoEdit && !empty($perData['calidad_id'])): ?>
          <input type="text" class="form-control" value="CC #<?= (int)$perData['calidad_id'] ?>" readonly>
          <input type="hidden" name="calidad_id" value="<?= (int)$perData['calidad_id'] ?>">
          <?php else: ?>
          <select class="form-control" name="calidad_id" id="calidad_id">
            <option value="">Seleccione inspección</option>
            <?php foreach ($calidades as $c): ?>
            <option value="<?= (int)$c['calidad_id'] ?>"
              <?= $calidadPreseleccion === (int)$c['calidad_id'] ? 'selected' : '' ?>>
              CC #<?= (int)$c['calidad_id'] ?> — Lote PT #<?= (int)$c['terminado_id'] ?> / OP #<?= (int)$c['orden_id'] ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($calidades)): ?>
          <small class="text-muted">No hay controles NO CONFORME pendientes de merma.</small>
          <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <div id="panel-control" class="row mb-3 d-none">
        <div class="col-md-8">
          <label for="control_id">Control de producción <span class="text-danger">*</span></label>
          <?php if ($modoEdit && !empty($perData['control_id'])): ?>
          <input type="text" class="form-control" value="Control #<?= (int)$perData['control_id'] ?>" readonly>
          <input type="hidden" name="control_id" value="<?= (int)$perData['control_id'] ?>">
          <?php else: ?>
          <select class="form-control" name="control_id" id="control_id">
            <option value="">Seleccione control</option>
            <?php foreach ($controles as $c): ?>
            <option value="<?= (int)$c['control_id'] ?>"
              <?= $controlPreseleccion === (int)$c['control_id'] ? 'selected' : '' ?>>
              Ctrl #<?= (int)$c['control_id'] ?> — OP #<?= (int)$c['orden_id'] ?>
              / <?= htmlspecialchars($c['producto_descri']) ?> (<?= (int)$c['control_cantidad'] ?> u.)
              — <?= htmlspecialchars($c['etapa_nombre']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($controles)): ?>
          <small class="text-muted">No hay controles de producción disponibles para pérdida en proceso.</small>
          <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <div id="panel-items" class="d-none">
        <p class="small text-muted" id="hint-origen"></p>
        <div class="row align-items-end mb-2 bg-light border rounded p-3">
          <div class="col-md-4">
            <label for="producto_sel">Producto</label>
            <select class="form-control" id="producto_sel" disabled><option value="">—</option></select>
          </div>
          <div class="col-md-2">
            <label for="cantidad_per">Cantidad</label>
            <input type="number" class="form-control" id="cantidad_per" min="1" step="1" disabled>
          </div>
          <div class="col-md-4">
            <label for="motivo_per">Observación (máx. 30)</label>
            <input type="text" class="form-control" id="motivo_per" maxlength="30" disabled
              placeholder="Motivo de la pérdida">
          </div>
          <div class="col-md-2">
            <button type="button" class="btn btn-primary w-100" id="btn-agregar" disabled>Agregar ítem</button>
          </div>
        </div>
        <table class="table table-bordered table-sm" id="tabla-items">
          <thead class="table-dark">
            <tr><th>Producto</th><th>Cant.</th><th>Observación</th><th></th></tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <input type="hidden" name="items" id="items" value="[]">

      <div class="d-flex justify-content-end mt-3">
        <button type="submit" name="Guardar" class="btn btn-success" id="btn-guardar" disabled>Guardar</button>
        <a href="view.php" class="btn btn-warning ml-2">Cancelar</a>
      </div>
    </form>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const TIPO_CALIDAD = <?= (int)TIPO_MERMA_CALIDAD ?>;
  const TIPO_PROCESO = <?= (int)TIPO_PERDIDA_PROCESO ?>;
  const modoEdit = <?= $modoEdit ? 'true' : 'false' ?>;
  const excluirId = <?= $modoEdit ? (int)$perData['perdidas_id'] : 0 ?>;

  const selTipo = document.getElementById('tipo_perdida_id');
  const panelCal = document.getElementById('panel-calidad');
  const panelCtrl = document.getElementById('panel-control');
  const selCal = document.getElementById('calidad_id');
  const selCtrl = document.getElementById('control_id');
  const panel = document.getElementById('panel-items');
  const hint = document.getElementById('hint-origen');
  const selProd = document.getElementById('producto_sel');
  const inpCant = document.getElementById('cantidad_per');
  const inpMot = document.getElementById('motivo_per');
  const btnAdd = document.getElementById('btn-agregar');
  const btnGuardar = document.getElementById('btn-guardar');
  const tbody = document.querySelector('#tabla-items tbody');
  const hid = document.getElementById('items');

  let productos = [];
  let items = <?= json_encode(array_map(static function ($r) {
      return [
          'producto_id' => (int)$r['producto_id'],
          'nombre' => $r['producto_descri'],
          'cantidad' => (int)$r['cantidad'],
          'motivo' => $r['motivo'],
          'deposito_id' => 0,
      ];
  }, $detalleEdit), JSON_UNESCAPED_UNICODE) ?>;

  const esCalidad = () => {
    if (modoEdit) return <?= !empty($perData['calidad_id']) ? 'true' : 'false' ?>;
    const opt = selTipo?.selectedOptions[0];
    return opt?.dataset.origen === 'calidad';
  };

  const sync = () => {
    hid.value = JSON.stringify(items.map(it => ({
      producto_id: it.producto_id,
      cantidad: it.cantidad,
      motivo: it.motivo,
      deposito_id: it.deposito_id || 0
    })));
    btnGuardar.disabled = items.length === 0;
  };

  const renderTabla = () => {
    tbody.innerHTML = '';
    items.forEach((it, idx) => {
      const tr = document.createElement('tr');
      if (modoEdit) {
        tr.innerHTML = `
          <td>${it.nombre}</td>
          <td><input type="number" class="form-control form-control-sm cant-edit" data-i="${idx}"
            min="1" max="${it.max || 9999}" value="${it.cantidad}"></td>
          <td><input type="text" class="form-control form-control-sm mot-edit" data-i="${idx}"
            maxlength="30" value="${it.motivo.replace(/"/g, '&quot;')}"></td>
          <td><button type="button" class="btn btn-danger btn-sm btn-quitar" data-i="${idx}">Quitar</button></td>`;
      } else {
        tr.innerHTML = `<td>${it.nombre}</td><td class="text-right">${it.cantidad}</td><td>${it.motivo}</td>
          <td><button type="button" class="btn btn-danger btn-sm btn-quitar" data-i="${idx}">Quitar</button></td>`;
      }
      tbody.appendChild(tr);
    });
    tbody.querySelectorAll('.btn-quitar').forEach(b => {
      b.addEventListener('click', () => {
        items.splice(parseInt(b.dataset.i, 10), 1);
        renderTabla();
        actualizarProductos();
        sync();
      });
    });
    tbody.querySelectorAll('.cant-edit').forEach(inp => {
      inp.addEventListener('change', () => {
        const i = parseInt(inp.dataset.i, 10);
        const max = parseInt(items[i].max, 10) || 9999;
        let v = parseInt(inp.value, 10) || 0;
        if (v <= 0) v = 1;
        if (v > max) { alert('Máximo: ' + max); v = max; inp.value = v; }
        items[i].cantidad = v;
        sync();
      });
    });
    tbody.querySelectorAll('.mot-edit').forEach(inp => {
      inp.addEventListener('change', () => {
        items[parseInt(inp.dataset.i, 10)].motivo = inp.value.trim();
        sync();
      });
    });
    sync();
  };

  const actualizarProductos = () => {
    selProd.innerHTML = '<option value="">Seleccione</option>';
    productos.forEach(p => {
      if (items.some(x => x.producto_id === p.producto_id)) return;
      const opt = document.createElement('option');
      opt.value = p.producto_id;
      opt.textContent = `${p.producto_descri} (máx. ${p.cantidad_disponible})`;
      opt.dataset.max = p.cantidad_disponible;
      opt.dataset.nombre = p.producto_descri;
      opt.dataset.dep = p.deposito_id || '';
      selProd.appendChild(opt);
    });
    const hay = selProd.options.length > 1;
    selProd.disabled = !hay;
    inpCant.disabled = !hay;
    inpMot.disabled = !hay;
    btnAdd.disabled = !hay;
  };

  const togglePaneles = () => {
    if (modoEdit) return;
    const cal = esCalidad();
    panelCal.classList.toggle('d-none', !cal);
    panelCtrl.classList.toggle('d-none', cal);
    if (cal) {
      if (selCtrl) selCtrl.removeAttribute('required');
      if (selCal) selCal.setAttribute('required', 'required');
    } else {
      if (selCal) selCal.removeAttribute('required');
      if (selCtrl) selCtrl.setAttribute('required', 'required');
    }
    items = [];
    productos = [];
    renderTabla();
    panel.classList.add('d-none');
  };

  const cargarOrigen = async () => {
    items = modoEdit ? items : [];
    productos = [];
    if (!modoEdit) renderTabla();

    let url = '';
    if (esCalidad()) {
      const id = modoEdit
        ? <?= (int)($perData['calidad_id'] ?? 0) ?>
        : (selCal?.value || '');
      if (!id) { panel.classList.add('d-none'); return; }
      url = 'get_calidad_info.php?calidad_id=' + id + (excluirId ? '&excluir_perdidas_id=' + excluirId : '');
    } else {
      const id = modoEdit
        ? <?= (int)($perData['control_id'] ?? 0) ?>
        : (selCtrl?.value || '');
      if (!id) { panel.classList.add('d-none'); return; }
      url = 'get_control_info.php?control_id=' + id + (excluirId ? '&excluir_perdidas_id=' + excluirId : '');
    }

    try {
      const r = await fetch(url);
      const data = await r.json();
      if (!data.success) { if (!modoEdit) alert(data.error); panel.classList.add('d-none'); return; }
      productos = (data.productos || []).map(p => ({
        producto_id: parseInt(p.producto_id, 10),
        producto_descri: p.producto_descri,
        cantidad_disponible: parseInt(p.cantidad_disponible, 10),
        deposito_id: parseInt(p.deposito_id, 10) || 0
      }));
      hint.textContent = data.hint || '';
      panel.classList.remove('d-none');

      if (modoEdit && items.length) {
        items = items.map(it => {
          const p = productos.find(x => x.producto_id === it.producto_id);
          const extra = excluirId ? it.cantidad : 0;
          return {
            ...it,
            max: (p ? p.cantidad_disponible : it.cantidad) + extra,
            deposito_id: p?.deposito_id || 0
          };
        });
      }
      actualizarProductos();
      renderTabla();
    } catch (e) { alert('Error de conexión'); }
  };

  if (!modoEdit && selTipo) {
    selTipo.addEventListener('change', () => { togglePaneles(); cargarOrigen(); });
    togglePaneles();
  } else if (modoEdit) {
    panelCal.classList.toggle('d-none', !<?= !empty($perData['calidad_id']) ? 'true' : 'false' ?>);
    panelCtrl.classList.toggle('d-none', !<?= !empty($perData['control_id']) ? 'true' : 'false' ?>);
  }

  if (selCal) selCal.addEventListener('change', cargarOrigen);
  if (selCtrl) selCtrl.addEventListener('change', cargarOrigen);

  if (selProd) selProd.addEventListener('change', () => {
    const opt = selProd.selectedOptions[0];
    if (opt?.value) inpCant.max = parseInt(opt.dataset.max, 10) || 1;
  });

  if (btnAdd) btnAdd.addEventListener('click', () => {
    const opt = selProd.selectedOptions[0];
    if (!opt?.value) return;
    const cant = parseInt(inpCant.value, 10) || 0;
    const max = parseInt(opt.dataset.max, 10) || 0;
    const motivo = inpMot.value.trim();
    if (cant <= 0 || cant > max) { alert('Cantidad inválida. Máx: ' + max); return; }
    if (!motivo) { alert('Indique la observación.'); return; }
    items.push({
      producto_id: parseInt(opt.value, 10),
      nombre: opt.dataset.nombre,
      cantidad: cant,
      motivo: motivo,
      deposito_id: parseInt(opt.dataset.dep, 10) || 0,
      max: max
    });
    renderTabla();
    actualizarProductos();
    selProd.value = '';
    inpCant.value = '';
    inpMot.value = '';
  });

  document.getElementById('form-perdida').addEventListener('submit', e => {
    sync();
    if (JSON.parse(hid.value || '[]').length === 0) {
      e.preventDefault();
      alert('Agregue al menos un ítem.');
    }
  });

  if (modoEdit || selCal?.value || selCtrl?.value) cargarOrigen();
  else if (!modoEdit) togglePaneles();
});
</script>
<?php endif; ?>
