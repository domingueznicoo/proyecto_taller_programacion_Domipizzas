<?php
// Fragmento del formulario incluido dentro de la barra lateral y cabecera comunes.
require_once __DIR__ . '/pedido_helper.php';
$isEdit = ($_GET['form'] ?? '') === 'edit';
$pedido = null;
$detalle = [];
try {
    $pdo = pedidoConexion();
    pedidoUsuario($pdo);
    if ($isEdit) {
        $pedido = pedidoCabecera($pdo, pedidoEnteroPositivo($_GET['ped_id'] ?? null));
        if (strtoupper(trim($pedido['pedido_estado'])) !== 'PENDIENTE') throw new RuntimeException('Solo se pueden editar pedidos PENDIENTES.');
        pedidoSinDocumentosPosteriores($pdo, (int)$pedido['pedido_id']);
        $detalle = pedidoDetalle($pdo, (int)$pedido['pedido_id']);
    }
    $materias = $pdo->query("SELECT materia_prima.mp_id, materia_prima.mp_descripcion, unidad_medida.unidad_medida_nombre
        FROM materia_prima LEFT JOIN unidad_medida ON unidad_medida.unidad_medida_id = materia_prima.unidad_medida_id
        WHERE UPPER(TRIM(materia_prima.mp_estado)) = 'ACTIVO' ORDER BY materia_prima.mp_descripcion")->fetchAll();
    $origenes = $pdo->query("SELECT pedido_mp_id, pedido_mp_nro, pedido_mp_fecha FROM pedido_materia_prima
        WHERE UPPER(TRIM(pedido_mp_estado)) = 'PENDIENTE' AND NOT EXISTS (
            SELECT 1 FROM pedido_compra WHERE pedido_compra.pedido_mp_id = pedido_materia_prima.pedido_mp_id
            AND UPPER(TRIM(pedido_compra.pedido_estado)) <> 'ANULADO') ORDER BY pedido_mp_id DESC")->fetchAll();
    if (!$isEdit) $proximoNumero = pedidoProximoNumero($pdo);
} catch (Throwable $e) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($e instanceof PDOException ? 'No se pudo cargar el formulario.' : $e->getMessage()) . '</div>';
    return;
}
$h = fn($valor) => htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?= $isEdit ? 'Editar Pedido de Compra' : 'Nuevo Pedido de Compra' ?></h1>
    <a href="view.php" class="btn btn-secondary btn-sm">Cerrar</a>
</div>
<div class="card shadow mb-4"><div class="card-body">
<form action="proses.php?act=<?= $isEdit ? 'update' : 'insert' ?>" method="POST" id="formPedido">
    <?php if ($isEdit): ?>
    <input type="hidden" name="pedido_id" value="<?= (int)$pedido['pedido_id'] ?>">
    <input type="hidden" name="version" value="<?= $h(pedidoVersion($pedido, $detalle)) ?>">
    <?php endif; ?>
    <div class="row">
        <div class="col-md-3 form-group"><label>N° Pedido</label><input class="form-control" readonly value="<?= $h($pedido['pedido_nro'] ?? $proximoNumero) ?>">
            <?php if (!$isEdit): ?><small class="form-text text-muted">Número estimado; se confirma al guardar.</small><?php endif; ?>
        </div>
        <div class="col-md-3 form-group"><label>Fecha</label><input class="form-control" readonly value="<?= $h($pedido['pedido_fecha'] ?? date('Y-m-d')) ?>"></div>
        <div class="col-md-3 form-group"><label>Hora actual</label><input class="form-control" readonly value="<?= date('H:i') ?>"></div>
        <div class="col-md-3 form-group"><label>Usuario actual</label><input class="form-control" readonly value="<?= $h($_SESSION['username']) ?>"></div>
    </div>
    <?php if (!$isEdit): ?>
    <div class="form-group"><label for="pedido_mp_id">Pedido de Materia Prima (opcional)</label>
        <select name="pedido_mp_id" id="pedido_mp_id" class="form-control">
            <option value="">Carga manual</option>
            <?php foreach ($origenes as $origen): ?><option value="<?= (int)$origen['pedido_mp_id'] ?>"><?= $h($origen['pedido_mp_nro'] . ' — ' . $origen['pedido_mp_fecha']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php elseif ($pedido['pedido_mp_id'] !== null): ?>
    <p>Pedido de Materia Prima vinculado: #<?= (int)$pedido['pedido_mp_id'] ?></p>
    <?php endif; ?>
    <div class="form-group"><label for="pedido_observacion">Observaciones (opcional)</label>
        <textarea name="pedido_observacion" id="pedido_observacion" class="form-control" rows="2"><?= $h($pedido['pedido_observacion'] ?? '') ?></textarea>
    </div>
    <div class="row align-items-end">
        <div class="col-md-7 form-group"><label for="mp_id">Materia prima</label>
            <select id="mp_id" class="form-control"><option value="">Seleccione...</option>
            <?php foreach ($materias as $materia): ?><option value="<?= (int)$materia['mp_id'] ?>"><?= $h($materia['mp_descripcion'] . ($materia['unidad_medida_nombre'] ? ' (' . $materia['unidad_medida_nombre'] . ')' : '')) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 form-group"><label for="detalle_cantidad">Cantidad</label><input type="number" id="detalle_cantidad" min="1" max="2147483647" step="1" class="form-control"></div>
        <div class="col-md-2 form-group"><button type="button" id="agregarItem" class="btn btn-primary btn-block">Agregar ítem</button></div>
    </div>
    <div id="errorPedido" class="alert alert-danger d-none" role="alert"></div>
    <div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Código</th><th>Materia prima</th><th>Cantidad</th><th>Acción</th></tr></thead><tbody id="itemsPedido"></tbody></table></div>
    <input type="hidden" name="productos" id="productosPedido">
    <button type="submit" class="btn btn-primary" id="guardarPedido" name="Guardar">Guardar</button>
    <button type="button" class="btn btn-secondary" id="cancelarPedido">Cancelar</button>
    <a href="view.php" class="btn btn-secondary">Cerrar</a>
</form>
</div></div>
<script>
(function() {
    const inicial = <?= json_encode($detalle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    let items = inicial.map(item => ({mp_id: Number(item.mp_id), detalle_cantidad: Number(item.detalle_cantidad), mp_descripcion: item.mp_descripcion}));
    let cargando = false;
    let solicitud = 0;
    const cuerpo = document.getElementById('itemsPedido');
    const origen = document.getElementById('pedido_mp_id');
    const error = document.getElementById('errorPedido');
    const guardar = document.getElementById('guardarPedido');
    function mensaje(texto) { error.textContent = texto; error.classList.toggle('d-none', !texto); }
    function cantidadValida(valor) { return /^\d+$/.test(String(valor)) && Number(valor) > 0 && Number(valor) <= 2147483647; }
    function render() {
        cuerpo.replaceChildren();
        items.forEach((item, indice) => {
            const fila = document.createElement('tr');
            [item.mp_id, item.mp_descripcion].forEach(valor => { const celda = document.createElement('td'); celda.textContent = valor; fila.appendChild(celda); });
            const cantidad = document.createElement('input');
            cantidad.type = 'number'; cantidad.min = '1'; cantidad.max = '2147483647'; cantidad.step = '1'; cantidad.required = true;
            cantidad.className = 'form-control'; cantidad.value = item.detalle_cantidad;
            cantidad.addEventListener('input', () => { item.detalle_cantidad = cantidad.value; });
            const celda = document.createElement('td'); celda.appendChild(cantidad); fila.appendChild(celda);
            const accion = document.createElement('td'); const quitar = document.createElement('button');
            quitar.type = 'button'; quitar.className = 'btn btn-danger btn-sm'; quitar.title = 'Quitar'; quitar.textContent = 'Quitar';
            quitar.addEventListener('click', () => { items.splice(indice, 1); render(); });
            accion.appendChild(quitar); fila.appendChild(accion); cuerpo.appendChild(fila);
        });
    }
    document.getElementById('agregarItem').addEventListener('click', () => {
        if (cargando) return;
        const seleccion = document.getElementById('mp_id');
        const cantidad = document.getElementById('detalle_cantidad');
        if (!seleccion.value || !cantidadValida(cantidad.value)) { mensaje('Seleccione materia prima y una cantidad entera mayor a cero.'); return; }
        const existente = items.find(item => item.mp_id === Number(seleccion.value));
        if (existente) {
            const total = Number(existente.detalle_cantidad) + Number(cantidad.value);
            if (!cantidadValida(existente.detalle_cantidad) || total > 2147483647) { mensaje('Revise la cantidad consolidada.'); return; }
            existente.detalle_cantidad = total;
        } else items.push({mp_id: Number(seleccion.value), detalle_cantidad: Number(cantidad.value), mp_descripcion: seleccion.options[seleccion.selectedIndex].text});
        cantidad.value = ''; mensaje(''); render();
    });
    if (origen) origen.addEventListener('change', async () => {
        const actual = ++solicitud;
        if (!origen.value) { cargando = false; guardar.disabled = false; items = []; render(); mensaje(''); return; }
        cargando = true; guardar.disabled = true; mensaje('');
        try {
            const respuesta = await fetch('get_detalle.php?pedido_mp_id=' + encodeURIComponent(origen.value));
            const datos = await respuesta.json();
            if (actual !== solicitud) return;
            if (!datos.success) throw new Error(datos.message || 'No se pudo cargar el pedido origen.');
            items = datos.detalle.map(item => ({mp_id: Number(item.mp_id), detalle_cantidad: Number(item.detalle_cantidad), mp_descripcion: item.mp_descripcion}));
            render();
        } catch (e) {
            if (actual !== solicitud) return;
            origen.value = ''; items = []; render(); mensaje(e.message);
        } finally { if (actual === solicitud) { cargando = false; guardar.disabled = false; } }
    });
    document.getElementById('cancelarPedido').addEventListener('click', () => {
        if (!confirm('¿Limpiar los ítems y observaciones sin guardar?')) return;
        solicitud++; cargando = false; guardar.disabled = false; items = [];
        if (origen) origen.value = '';
        document.getElementById('pedido_observacion').value = '';
        document.getElementById('mp_id').value = ''; document.getElementById('detalle_cantidad').value = '';
        mensaje(''); render();
    });
    document.getElementById('formPedido').addEventListener('submit', event => {
        if (cargando || !items.length || items.some(item => !cantidadValida(item.detalle_cantidad))) {
            event.preventDefault(); mensaje('Agregue al menos un ítem con cantidad entera mayor a cero.'); return;
        }
        document.getElementById('productosPedido').value = JSON.stringify(items.map(item => ({mp_id: item.mp_id, detalle_cantidad: Number(item.detalle_cantidad)})));
        guardar.disabled = true;
    });
    render();
})();
</script>
