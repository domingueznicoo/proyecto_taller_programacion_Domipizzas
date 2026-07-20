<?php
// Formulario de control de calidad (alta o edición).
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

$lotes = $pdoForm->query("
    SELECT pt.terminado_id, pt.orden_id, pt.terminado_fecha
    FROM producto_terminado pt
    WHERE NOT EXISTS (
        SELECT 1 FROM control_calidad_produccion cc
        WHERE cc.terminado_id = pt.terminado_id
          AND UPPER(TRIM(cc.calidad_estado)) <> 'ANULADO'
    )
    ORDER BY pt.terminado_id DESC
")->fetchAll();

$inspectores = $pdoForm->query("
    SELECT i.id_inspectores,
           TRIM(per.personal_nombre || ' ' || per.personal_apellido) AS nombre
    FROM inspectores i
    JOIN personal per ON per.id_personal = i.id_personal
    WHERE UPPER(TRIM(i.inspector_estado)) = 'ACTIVO'
    ORDER BY per.personal_apellido, per.personal_nombre
")->fetchAll();

$modoEdit = isset($_GET['form_calidad'], $_GET['form'], $_GET['calidad_id'])
    && $_GET['form_calidad'] === 'edit' && $_GET['form'] === 'edit';
$calidadEdit = $modoEdit ? (int)$_GET['calidad_id'] : 0;
$calData = null;
if ($modoEdit && $calidadEdit > 0) {
    $st = $pdoForm->prepare("
        SELECT cc.calidad_id, cc.terminado_id, cc.calidad_fecha, cc.calidad_estado,
               cc.id_inspectores,
               (SELECT COUNT(*) FROM perdidas pe WHERE pe.calidad_id = cc.calidad_id) AS tiene_perdidas
        FROM control_calidad_produccion cc
        WHERE cc.calidad_id = :id
    ");
    $st->execute([':id' => $calidadEdit]);
    $calData = $st->fetch();
    if (!$calData || strtoupper(trim((string)$calData['calidad_estado'])) === 'ANULADO') {
        die('Registro no disponible para edición.');
    }
}

if (!$modoEdit && isset($_GET['form_calidad'], $_GET['form']) && $_GET['form_calidad'] === 'add' && $_GET['form'] === 'add'):
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-clipboard-check"></i> Control de calidad</h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Control de calidad</a></li>
    <li class="breadcrumb-item active">Nuevo</li>
  </ol>

  <div class="alert alert-info small">
    Inspeccione un <strong>lote de productos terminados</strong> (post-Empaque). Indique cuántas unidades
    inspeccionó y cuántas <strong>rechazó</strong> (el resto se considera conforme). Si hay rechazos,
    registre la <strong>merma</strong> en el módulo de Pérdidas.
  </div>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=insert" method="POST" id="form-calidad-add">
      <div class="row mb-3">
        <div class="col-md-2"><label>Fecha inspección</label>
          <input type="date" class="form-control" name="calidad_fecha" value="<?= htmlspecialchars($fechaHoy) ?>" required></div>
        <div class="col-md-2"><label>Hora</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($horaHoy) ?>" readonly></div>
        <div class="col-md-4"><label>Usuario</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['username']) ?>" readonly></div>
        <div class="col-md-4"><label>Inspector <span class="text-danger">*</span></label>
          <select class="form-control" name="id_inspectores" required>
            <option value="">Seleccione</option>
            <?php foreach ($inspectores as $insp): ?>
            <option value="<?= (int)$insp['id_inspectores'] ?>"><?= htmlspecialchars($insp['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-6">
          <label for="terminado_id">Lote PT (productos terminados) <span class="text-danger">*</span></label>
          <select class="form-control" name="terminado_id" id="terminado_id" required>
            <option value="">Seleccione lote</option>
            <?php foreach ($lotes as $l): ?>
            <option value="<?= (int)$l['terminado_id'] ?>">
              PT #<?= (int)$l['terminado_id'] ?> — OP #<?= (int)$l['orden_id'] ?> (<?= htmlspecialchars(substr((string)$l['terminado_fecha'], 0, 10)) ?>)
            </option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($lotes)): ?>
          <small class="text-danger">No hay lotes PT pendientes de inspección.</small>
          <?php endif; ?>
        </div>
      </div>

      <div id="panel-eval" class="d-none">
        <h6 class="font-weight-bold">Evaluación por producto y parámetro</h6>
        <div id="eval-container"></div>
      </div>

      <input type="hidden" name="evaluaciones" id="evaluaciones" value="[]">

      <div class="d-flex justify-content-end mt-3">
        <button type="submit" name="Guardar" class="btn btn-success" id="btn-guardar" disabled>Registrar inspección</button>
        <a href="view.php" class="btn btn-warning ml-2">Cancelar</a>
      </div>
    </form>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const selLote = document.getElementById('terminado_id');
  const panel = document.getElementById('panel-eval');
  const container = document.getElementById('eval-container');
  const hid = document.getElementById('evaluaciones');
  const btnGuardar = document.getElementById('btn-guardar');

  let loteData = null;

  const syncCantidadesProducto = (pid, maxLote) => {
    const inpIns = document.querySelector(`[data-inspeccionada="${pid}"]`);
    const inpRech = document.querySelector(`[data-rechazada="${pid}"]`);
    const lblConf = document.querySelector(`[data-conforme="${pid}"]`);
    if (!inpIns || !inpRech) return;
    let ins = parseInt(inpIns.value, 10);
    let rech = parseInt(inpRech.value, 10);
    if (isNaN(ins) || ins < 1) ins = 1;
    if (ins > maxLote) ins = maxLote;
    if (isNaN(rech) || rech < 0) rech = 0;
    if (rech > ins) rech = ins;
    inpIns.value = ins;
    inpRech.value = rech;
    if (lblConf) lblConf.textContent = String(ins - rech);
  };

  const syncEvaluaciones = () => {
    if (!loteData) { hid.value = '[]'; btnGuardar.disabled = true; return; }
    const evals = [];
    let valido = true;
    loteData.productos.forEach(prod => {
      const pid = prod.producto_id;
      const maxLote = parseInt(prod.terminado_cantidad, 10) || 0;
      syncCantidadesProducto(pid, maxLote);
      const inpIns = document.querySelector(`[data-inspeccionada="${pid}"]`);
      const inpRech = document.querySelector(`[data-rechazada="${pid}"]`);
      const cantIns = parseInt(inpIns?.value || '0', 10);
      const cantRech = parseInt(inpRech?.value || '0', 10);
      const params = [];
      let algunoNoCumple = false;
      (loteData.parametros[pid] || []).forEach(par => {
        const valor = document.querySelector(`[data-valor="${pid}-${par.parametro_id}"]`);
        const cumple = document.querySelector(`[data-cumple="${pid}-${par.parametro_id}"]`);
        if (!valor || !cumple) return;
        if (!cumple.checked) algunoNoCumple = true;
        params.push({
          parametro_id: par.parametro_id,
          valor_medido: valor.value.trim(),
          cumple: cumple.checked
        });
      });
      if (algunoNoCumple && cantRech <= 0) valido = false;
      if (params.length) {
        evals.push({
          producto_id: pid,
          cantidad_inspeccionada: cantIns,
          cantidad_rechazada: cantRech,
          parametros: params
        });
      }
    });
    hid.value = JSON.stringify(evals);
    btnGuardar.disabled = evals.length === 0 || !valido;
  };

  selLote.addEventListener('change', async () => {
    container.innerHTML = '';
    loteData = null;
    const id = selLote.value;
    if (!id) { panel.classList.add('d-none'); syncEvaluaciones(); return; }
    try {
      const r = await fetch('get_terminado_info.php?terminado_id=' + id);
      const data = await r.json();
      if (!data.success) { alert(data.error || 'Error'); panel.classList.add('d-none'); return; }
      loteData = data;
      panel.classList.remove('d-none');
      let html = `<p class="small text-muted">OP #${data.lote.orden_id} — indique cantidades y marque si cada parámetro cumple en la muestra inspeccionada.</p>`;
      data.productos.forEach(prod => {
        const cant = parseInt(prod.terminado_cantidad, 10) || 0;
        html += `<div class="card mb-3"><div class="card-header py-2"><strong>${prod.producto_descri}</strong>
          <span class="badge badge-secondary ml-2">Cant. lote: ${cant}</span>
          <span class="text-muted small ml-2">${prod.deposito_descri || ''}</span></div><div class="card-body p-2">`;
        html += `<div class="row mb-2 small">
          <div class="col-md-3"><label class="mb-0">Cant. inspeccionada</label>
            <input type="number" class="form-control form-control-sm" min="1" max="${cant}" value="${cant}"
              data-inspeccionada="${prod.producto_id}"></div>
          <div class="col-md-3"><label class="mb-0">Unidades rechazadas</label>
            <input type="number" class="form-control form-control-sm" min="0" max="${cant}" value="0"
              data-rechazada="${prod.producto_id}"></div>
          <div class="col-md-3"><label class="mb-0">Conformes (inspección)</label>
            <p class="form-control-plaintext py-1 font-weight-bold mb-0" data-conforme="${prod.producto_id}">${cant}</p></div>
        </div>`;
        html += '<table class="table table-sm table-bordered mb-0"><thead><tr><th>Parámetro</th><th>Valor medido</th><th>Cumple</th></tr></thead><tbody>';
        (data.parametros[prod.producto_id] || []).forEach(par => {
          html += `<tr>
            <td>${par.parametro_descri}</td>
            <td><input type="text" class="form-control form-control-sm" maxlength="100"
              data-valor="${prod.producto_id}-${par.parametro_id}" placeholder="Opcional"></td>
            <td class="text-center"><input type="checkbox" class="form-check-input"
              data-cumple="${prod.producto_id}-${par.parametro_id}" checked></td></tr>`;
        });
        html += '</tbody></table></div></div>';
      });
      container.innerHTML = html;
      container.querySelectorAll('input').forEach(inp => inp.addEventListener('change', syncEvaluaciones));
      container.querySelectorAll('input').forEach(inp => inp.addEventListener('input', syncEvaluaciones));
      syncEvaluaciones();
    } catch (e) {
      alert('Error de conexión');
    }
  });

  document.getElementById('form-calidad-add').addEventListener('submit', e => {
    syncEvaluaciones();
    const evals = JSON.parse(hid.value || '[]');
    if (evals.length === 0) {
      e.preventDefault();
      alert('Complete la evaluación de parámetros.');
      return;
    }
    const faltaRechazo = evals.some(ev => {
      const noCumple = (ev.parametros || []).some(p => !p.cumple);
      return noCumple && (parseInt(ev.cantidad_rechazada, 10) || 0) <= 0;
    });
    if (faltaRechazo) {
      e.preventDefault();
      alert('Si algún parámetro no cumple, indique al menos 1 unidad rechazada.');
    }
  });
});
</script>

<?php elseif ($modoEdit && $calData): ?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800">Editar control #<?= (int)$calData['calidad_id'] ?></h1>
  <div class="card shadow mb-4"><div class="card-body">
    <p><strong>Veredicto:</strong>
      <span class="badge <?= strtoupper($calData['calidad_estado']) === 'APROBADO' ? 'badge-success' : 'badge-danger' ?>">
        <?= htmlspecialchars($calData['calidad_estado']) ?>
      </span>
      — Lote PT #<?= (int)$calData['terminado_id'] ?>
    </p>
    <?php if ((int)$calData['tiene_perdidas'] > 0): ?>
    <div class="alert alert-warning">Tiene pérdidas vinculadas: solo puede ajustar fecha e inspector.</div>
    <?php endif; ?>
    <p class="text-muted small">Los parámetros medidos no se modifican aquí; anule y vuelva a registrar si fue un error.</p>
    <form action="proses.php?act=update" method="POST">
      <input type="hidden" name="calidad_id" value="<?= (int)$calData['calidad_id'] ?>">
      <div class="row mb-3">
        <div class="col-md-4">
          <label>Fecha inspección</label>
          <input type="date" class="form-control" name="calidad_fecha"
            value="<?= htmlspecialchars(substr((string)$calData['calidad_fecha'], 0, 10)) ?>" required>
        </div>
        <div class="col-md-6">
          <label>Inspector</label>
          <select class="form-control" name="id_inspectores" required>
            <?php foreach ($inspectores as $insp): ?>
            <option value="<?= (int)$insp['id_inspectores'] ?>"
              <?= (int)$calData['id_inspectores'] === (int)$insp['id_inspectores'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($insp['nombre']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <button type="submit" name="Guardar" class="btn btn-success">Guardar</button>
      <a href="view.php" class="btn btn-warning ml-2">Cancelar</a>
    </form>
  </div></div>
</div>
<?php endif; ?>
