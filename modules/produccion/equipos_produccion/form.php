<?php
// Formulario de equipos de trabajo (alta o edición).
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once __DIR__ . '/equipos_helper.php';

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
    SELECT u.username, s.descripcion_sucursal
    FROM usuarios u
    JOIN sucursales s ON s.id_sucursal = u.id_sucursal
    WHERE u.username = :user LIMIT 1
");
$qUser->execute([':user' => $userSesion]);
$usr = $qUser->fetch();
if (!$usr) {
    die('Usuario no encontrado.');
}

$ordenes = $pdoForm->query("
    SELECT op.orden_id, op.orden_prod_estado, op.id_pedido_produccion
    FROM orden_produccion op
    WHERE op.orden_prod_estado IN ('PENDIENTE', 'EN_PROCESO')
    ORDER BY op.orden_id DESC
")->fetchAll();

$modoEdit = isset($_GET['form_equipo'], $_GET['form'], $_GET['equipo_id'])
    && $_GET['form_equipo'] === 'edit' && $_GET['form'] === 'edit';
$equipoEditId = $modoEdit ? (int)$_GET['equipo_id'] : 0;
$equipoData = null;
$miembrosInit = [];
$trabajadoresEdit = [];

if ($modoEdit && $equipoEditId > 0) {
    $equipoData = cargarCabeceraEquipo($pdoForm, $equipoEditId);
    if (!$equipoData || strtoupper(trim((string)$equipoData['equipo_estado'])) !== 'PENDIENTE') {
        header('Location: view.php?alert=5');
        exit;
    }
    $stOp = $pdoForm->prepare('SELECT orden_prod_estado FROM orden_produccion WHERE orden_id = :id');
    $stOp->execute([':id' => (int)$equipoData['orden_id']]);
    if (!opPermiteEdicionEquipo((string)$stOp->fetchColumn())) {
        header('Location: view.php?alert=5&msg=' . urlencode('La OP no permite editar equipos.'));
        exit;
    }
    $miembrosInit = cargarDetalleEquipo($pdoForm, $equipoEditId);
    $etapaIdEdit = (int)($equipoData['etapa_id'] ?? 0);
    $sqlTrabEdit = "
        SELECT t.trabajadores_id,
               TRIM(per.personal_nombre || ' ' || per.personal_apellido) AS nombre,
               t.trabajador_rol, t.trabajador_turno, t.id_etapa
        FROM trabajadores t
        JOIN personal per ON per.id_personal = t.id_personal
        WHERE UPPER(TRIM(t.trabajador_estado)) = 'ACTIVO'
    ";
    if ($etapaIdEdit > 0) {
        $sqlTrabEdit .= ' AND t.id_etapa = :eid';
        $stTrab = $pdoForm->prepare($sqlTrabEdit . ' ORDER BY per.personal_apellido, per.personal_nombre');
        $stTrab->execute([':eid' => $etapaIdEdit]);
        $trabajadoresEdit = $stTrab->fetchAll();
    } else {
        $trabajadoresEdit = $pdoForm->query($sqlTrabEdit . ' ORDER BY per.personal_apellido, per.personal_nombre')->fetchAll();
    }
}

$ordenPre = isset($_GET['orden_id']) ? (int)$_GET['orden_id'] : 0;
$jsMiembros = json_encode(array_map(static function ($m) {
    return [
        'trabajadores_id' => (int)$m['trabajadores_id'],
        'nombre' => $m['nombre'],
        'tarea_rol' => $m['tarea_rol'],
    ];
}, $miembrosInit));

$titulo = $modoEdit ? 'Editar equipo' : 'Nuevo equipo';

if (!$modoEdit && isset($_GET['form_equipo'], $_GET['form']) && $_GET['form_equipo'] === 'add' && $_GET['form'] === 'add'):
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-users"></i> <?= htmlspecialchars($titulo) ?></h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Equipos de trabajo</a></li>
    <li class="breadcrumb-item active">Nuevo</li>
  </ol>

  <?php if (empty($ordenes)): ?>
  <div class="alert alert-warning">No hay órdenes de producción PENDIENTE o EN_PROCESO.</div>
  <a href="view.php" class="btn btn-secondary">Volver</a>
  <?php else: ?>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=insert" method="POST" id="form-equipo">
      <div class="row mb-3">
        <div class="col-md-2"><label>Fecha</label>
          <input type="date" class="form-control" name="equipo_fecha" value="<?= htmlspecialchars($fechaHoy) ?>" required></div>
        <div class="col-md-2"><label>Hora</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($horaHoy) ?>" readonly></div>
        <div class="col-md-3"><label>Usuario</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['username']) ?>" readonly></div>
        <div class="col-md-5"><label>Sucursal</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['descripcion_sucursal']) ?>" readonly></div>
      </div>

      <div class="row mb-3">
        <div class="col-md-4">
          <label for="orden_id">Orden de producción <span class="text-danger">*</span></label>
          <select class="form-control" name="orden_id" id="orden_id" required>
            <option value="">Seleccione OP</option>
            <?php foreach ($ordenes as $o): ?>
            <option value="<?= (int)$o['orden_id'] ?>"<?= $ordenPre === (int)$o['orden_id'] ? ' selected' : '' ?>>
              OP #<?= (int)$o['orden_id'] ?> — Pedido #<?= (int)$o['id_pedido_produccion'] ?> (<?= htmlspecialchars($o['orden_prod_estado']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label for="etapa_id">Etapa <span class="text-danger">*</span></label>
          <select class="form-control" name="etapa_id" id="etapa_id" required disabled>
            <option value="">Seleccione OP primero</option>
          </select>
        </div>
        <div class="col-md-4">
          <label for="equipo_descri">Nombre del equipo</label>
          <input type="text" class="form-control" name="equipo_descri" id="equipo_descri" maxlength="30" placeholder="Auto si vacío">
        </div>
      </div>

      <div id="panel-op" class="d-none mb-2">
        <p class="small text-muted mb-2" id="hint-op"></p>
      </div>

      <h6 class="font-weight-bold">Trabajadores del equipo</h6>
      <div class="alert alert-warning small py-2">
        Cada equipo corresponde a <strong>una sola etapa</strong> de la OP.
        Si necesita personal en Preparación, Cocción, Armado y Empaque, registre <strong>un equipo por etapa</strong>.
      </div>
      <div class="row align-items-end mb-2">
        <div class="col-md-5">
          <label for="sel_trab">Trabajador</label>
          <select class="form-control" id="sel_trab" disabled><option value="">Seleccione OP</option></select></div>
        <div class="col-md-4">
          <label for="inp_rol">Rol / tarea</label>
          <input type="text" class="form-control" id="inp_rol" maxlength="100" readonly disabled>
        </div>
        <div class="col-md-3">
          <button type="button" class="btn btn-outline-primary btn-block" id="btn-add-trab" disabled><i class="fas fa-plus"></i> Agregar integrante</button>
        </div>
      </div>

      <div class="table-responsive mb-3">
        <table class="table table-bordered table-sm" id="tabla-miembros">
          <thead class="table-dark"><tr><th>Trabajador</th><th>Rol / etapa</th><th style="width:50px"></th></tr></thead>
          <tbody></tbody>
        </table>
      </div>

      <input type="hidden" name="miembros" id="miembros-json" value="[]">

      <div class="d-flex justify-content-end">
        <button type="submit" name="Guardar" class="btn btn-success mr-2" id="btn-guardar" disabled>Guardar equipo</button>
        <a href="view.php" class="btn btn-warning">Cancelar</a>
      </div>
    </form>
  </div></div>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const selOp = document.getElementById('orden_id');
  const selEtapa = document.getElementById('etapa_id');
  const selTrab = document.getElementById('sel_trab');
  const inpRol = document.getElementById('inp_rol');
  const btnAdd = document.getElementById('btn-add-trab');
  const tbody = document.querySelector('#tabla-miembros tbody');
  const hid = document.getElementById('miembros-json');
  const btnGuardar = document.getElementById('btn-guardar');
  const form = document.getElementById('form-equipo');
  const hint = document.getElementById('hint-op');
  const panel = document.getElementById('panel-op');
  if (!selOp || !form) return;

  let miembros = [];
  let trabajadores = [];
  let etapasMap = {};
  let etapaActual = '';
  let etapaSelAnterior = '';

  const sync = () => {
    hid.value = JSON.stringify(miembros);
    btnGuardar.disabled = miembros.length === 0 || !selEtapa.value;
  };

  const poblarTrabajadores = (etapaId) => {
    selTrab.innerHTML = '<option value="">Seleccione trabajador</option>';
    if (!etapaId) {
      selTrab.disabled = true;
      return;
    }
    const eid = parseInt(etapaId, 10);
    const lista = trabajadores.filter(t => parseInt(t.id_etapa, 10) === eid);
    lista.forEach(t => {
      const opt = document.createElement('option');
      opt.value = t.trabajadores_id;
      opt.textContent = t.nombre + (t.trabajador_rol ? ' (' + t.trabajador_rol + ')' : '');
      selTrab.appendChild(opt);
    });
    selTrab.disabled = lista.length === 0;
    const base = hint.dataset.base || '';
    if (base) {
      hint.textContent = lista.length === 0
        ? base + ' — No hay trabajadores activos para esta etapa.'
        : base;
    }
  };

  const render = () => {
    tbody.innerHTML = '';
    miembros.forEach((m, idx) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${m.nombre}</td><td>${m.tarea_rol}</td>
        <td><button type="button" class="btn btn-danger btn-sm btn-del" data-idx="${idx}"><i class="fas fa-trash"></i></button></td>`;
      tbody.appendChild(tr);
    });
    tbody.querySelectorAll('.btn-del').forEach(b => {
      b.addEventListener('click', () => { miembros.splice(parseInt(b.dataset.idx, 10), 1); render(); sync(); });
    });
    sync();
  };

  const cargarOp = async (oid) => {
    selEtapa.innerHTML = '<option value="">Cargando...</option>';
    selEtapa.disabled = true;
    selTrab.innerHTML = '<option value="">Seleccione etapa primero</option>';
    selTrab.disabled = true;
    inpRol.value = '';
    inpRol.disabled = true;
    btnAdd.disabled = true;
    panel.classList.add('d-none');
    miembros = [];
    etapasMap = {};
    render();

    if (!oid) return;
    try {
      const r = await fetch('get_orden_info.php?orden_id=' + oid);
      const data = await r.json();
      if (!data.success) { alert(data.error || 'Error'); return; }

      selEtapa.innerHTML = '<option value="">Seleccione etapa</option>';
      (data.etapas || []).forEach(e => {
        etapasMap[e.etapa_id] = e.etapa_nombre;
        const opt = document.createElement('option');
        opt.value = e.etapa_id;
        opt.textContent = e.etapa_nombre + ' (sec. ' + e.etapa_secuencia + ') — ' + e.producto_descripcion;
        selEtapa.appendChild(opt);
      });
      selEtapa.disabled = (data.etapas || []).length === 0;

      trabajadores = data.trabajadores || [];
      selTrab.innerHTML = '<option value="">Seleccione etapa primero</option>';
      selTrab.disabled = true;

      hint.dataset.base = 'Productos en la OP: ' + ((data.productos || []).map(p => p.producto_descripcion).join(', ') || '—');
      hint.textContent = hint.dataset.base;
      panel.classList.remove('d-none');
      if (selEtapa.value) {
        etapaActual = etapasMap[selEtapa.value] || '';
        inpRol.value = etapaActual;
        inpRol.disabled = !etapaActual;
        poblarTrabajadores(selEtapa.value);
        btnAdd.disabled = !selEtapa.value;
      }
    } catch (e) { alert('Error de conexión'); }
  };

  selOp.addEventListener('change', () => cargarOp(selOp.value));
  selEtapa.addEventListener('change', () => {
    const nueva = selEtapa.value;
    if (miembros.length > 0 && nueva !== etapaSelAnterior) {
      if (!confirm('Cada equipo es para una sola etapa. Si cambia la etapa se quitarán los integrantes ya agregados. ¿Continuar?')) {
        selEtapa.value = etapaSelAnterior;
        return;
      }
      miembros = [];
      render();
    }
    etapaSelAnterior = nueva;
    etapaActual = etapasMap[nueva] || '';
    inpRol.value = etapaActual;
    inpRol.disabled = !nueva;
    poblarTrabajadores(nueva);
    btnAdd.disabled = !nueva || selTrab.disabled;
    sync();
  });

  btnAdd.addEventListener('click', () => {
    const tid = parseInt(selTrab.value, 10);
    const eid = parseInt(selEtapa.value, 10);
    const rol = etapaActual;
    if (!tid || !rol || !eid) { alert('Seleccione etapa y trabajador.'); return; }
    if (miembros.some(m => m.trabajadores_id === tid)) { alert('Ese trabajador ya está en el equipo.'); return; }
    const t = trabajadores.find(x => parseInt(x.trabajadores_id, 10) === tid);
    if (!t || parseInt(t.id_etapa, 10) !== eid) {
      alert('El trabajador no pertenece a la etapa seleccionada.');
      return;
    }
    miembros.push({ trabajadores_id: tid, nombre: t.nombre, tarea_rol: rol, etapa_id: eid });
    render();
  });

  form.addEventListener('submit', e => {
    if (miembros.length === 0) { e.preventDefault(); alert('Agregue al menos un trabajador.'); return; }
    const eid = parseInt(selEtapa.value, 10);
    const fuera = miembros.filter(m => {
      const t = trabajadores.find(x => parseInt(x.trabajadores_id, 10) === m.trabajadores_id);
      return !t || parseInt(t.id_etapa, 10) !== eid;
    });
    if (fuera.length) {
      e.preventDefault();
      alert('Hay integrantes de otra etapa en la lista. Cada equipo es para una sola etapa: quite los que no correspondan o cree un equipo por etapa.');
      return;
    }
    sync();
  });

  if (selOp.value) cargarOp(selOp.value);
  etapaSelAnterior = selEtapa.value;
});
</script>

<?php
elseif ($modoEdit && $equipoData):
    $etapaLabel = trim((string)($equipoData['etapa_nombre'] ?? ''));
    if ($etapaLabel === '' && !empty($miembrosInit)) {
        $etapaLabel = (string)$miembrosInit[0]['tarea_rol'];
    }
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-edit"></i> Editar equipo #<?= (int)$equipoEditId ?></h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Equipos de trabajo</a></li>
    <li class="breadcrumb-item active">Editar</li>
  </ol>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=update" method="POST" id="form-equipo-edit">
      <input type="hidden" name="equipo_id" value="<?= (int)$equipoEditId ?>">

      <div class="row mb-3">
        <div class="col-md-2"><label>Fecha</label>
          <input type="date" class="form-control" name="equipo_fecha" value="<?= htmlspecialchars($equipoData['equipo_fecha']) ?>" required></div>
        <div class="col-md-2"><label>Estado</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($equipoData['equipo_estado']) ?>" readonly></div>
        <div class="col-md-2"><label>OP</label>
          <input type="text" class="form-control" value="#<?= (int)$equipoData['orden_id'] ?>" readonly></div>
        <div class="col-md-3"><label>Etapa</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($etapaLabel) ?>" readonly></div>
        <div class="col-md-3"><label>Nombre del equipo</label>
          <input type="text" class="form-control" name="equipo_descri" maxlength="30" value="<?= htmlspecialchars($equipoData['equipo_descri']) ?>" required></div>
      </div>

      <h6 class="font-weight-bold">Integrantes</h6>
      <div class="row align-items-end mb-2">
        <div class="col-md-5">
          <label for="sel_trab_edit">Agregar trabajador</label>
          <select class="form-control" id="sel_trab_edit">
            <option value="">Seleccione trabajador</option>
            <?php foreach ($trabajadoresEdit as $t): ?>
            <option value="<?= (int)$t['trabajadores_id'] ?>"><?= htmlspecialchars($t['nombre']) ?><?= !empty($t['trabajador_rol']) ? ' (' . htmlspecialchars($t['trabajador_rol']) . ')' : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label for="inp_rol_edit">Rol / tarea</label>
          <input type="text" class="form-control" id="inp_rol_edit" maxlength="100" value="<?= htmlspecialchars($etapaLabel) ?>" readonly>
        </div>
        <div class="col-md-3">
          <button type="button" class="btn btn-outline-primary btn-block" id="btn-add-edit"><i class="fas fa-plus"></i> Agregar integrante</button>
        </div>
      </div>

      <div class="table-responsive mb-3">
        <table class="table table-bordered table-sm" id="tabla-edit">
          <thead class="table-dark"><tr><th>Trabajador</th><th>Rol / etapa</th><th style="width:50px"></th></tr></thead>
          <tbody id="tbody-edit"></tbody>
        </table>
      </div>
      <input type="hidden" name="miembros" id="miembros-json" value="[]">

      <div class="d-flex justify-content-end">
        <button type="submit" name="Guardar" class="btn btn-success mr-2">Guardar cambios</button>
        <a href="view.php" class="btn btn-warning">Cancelar</a>
      </div>
    </form>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const miembros = <?= $jsMiembros ?>;
  const tbody = document.getElementById('tbody-edit');
  const hid = document.getElementById('miembros-json');
  const form = document.getElementById('form-equipo-edit');
  const selTrab = document.getElementById('sel_trab_edit');
  const inpRol = document.getElementById('inp_rol_edit');
  const etapaDefault = <?= json_encode($etapaLabel) ?>;

  const sync = () => { hid.value = JSON.stringify(miembros); };

  const render = () => {
    tbody.innerHTML = '';
    miembros.forEach((m, idx) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${m.nombre}</td>
        <td>${m.tarea_rol}</td>
        <td><button type="button" class="btn btn-danger btn-sm btn-del" data-idx="${idx}"><i class="fas fa-trash"></i></button></td>`;
      tbody.appendChild(tr);
    });
    tbody.querySelectorAll('.btn-del').forEach(b => {
      b.addEventListener('click', () => {
        miembros.splice(parseInt(b.dataset.idx, 10), 1);
        render();
        sync();
      });
    });
    sync();
  };

  document.getElementById('btn-add-edit').addEventListener('click', () => {
    const tid = parseInt(selTrab.value, 10);
    const rol = etapaDefault;
    const nombre = selTrab.options[selTrab.selectedIndex]?.text || '';
    if (!tid || !rol) { alert('Seleccione trabajador.'); return; }
    if (miembros.some(m => m.trabajadores_id === tid)) { alert('Trabajador duplicado.'); return; }
    miembros.push({ trabajadores_id: tid, nombre, tarea_rol: rol });
    selTrab.value = '';
    render();
  });

  form.addEventListener('submit', e => {
    if (miembros.length === 0) { e.preventDefault(); alert('Debe haber al menos un integrante.'); return; }
    sync();
  });

  render();
});
</script>
<?php else: ?>
<script>window.location.href = 'view.php';</script>
<?php endif; ?>
