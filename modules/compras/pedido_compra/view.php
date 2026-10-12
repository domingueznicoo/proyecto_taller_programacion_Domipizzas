<?php
require_once __DIR__ . '/pedido_helper.php';
try {
    $pdo = pedidoConexion();
    pedidoUsuario($pdo);
    $mostrarListado = !in_array($_GET['form'] ?? '', ['add', 'edit'], true);
    $pedidos = $mostrarListado ? $pdo->query('SELECT pedido_compra.*, usuario.username
        FROM pedido_compra JOIN usuario ON usuario.usuario_id = pedido_compra.usuario_id
        ORDER BY pedido_compra.pedido_id DESC')->fetchAll() : [];
} catch (Throwable $e) {
    error_log('Listado pedidos compra: ' . $e->getMessage());
    die('No se pudo cargar la lista de pedidos.');
}
$page_title = 'Pedidos de Compras';
$extra_css = ['vendor/datatables/dataTables.bootstrap4.min.css'];
$extra_js_plugins = ['vendor/datatables/jquery.dataTables.min.js', 'vendor/datatables/dataTables.bootstrap4.min.js'];
include PROJECT_ROOT . '/header.php';
$h = fn($valor) => htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
?>
<?php if ($mostrarListado): ?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Pedidos de Compras</h1>
    <a href="?form_pedido_compra=add&form=add" class="btn btn-primary btn-sm shadow-sm"><i class="fas fa-plus"></i> Nuevo Pedido</a>
</div>
<?php
$mensajes = [1 => 'Pedido registrado correctamente.', 2 => 'Pedido modificado correctamente.', 3 => 'Pedido anulado correctamente.', 4 => 'No se pudo realizar la operación.'];
$alerta = (int)($_GET['alert'] ?? 0);
if (isset($mensajes[$alerta])): ?>
<div id="mensajeResultadoPedido" class="alert <?= $alerta === 4 ? 'alert-danger' : 'alert-success' ?> alert-dismissible fade show" role="alert">
    <?= $h($_GET['msg'] ?? $mensajes[$alerta]) ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>
<div class="card shadow mb-4"><div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Lista de Pedidos de Compra</h6></div>
<div class="card-body"><div class="table-responsive">
<table class="table table-bordered" id="dataTable" width="100%"><thead><tr>
    <th>N° Pedido</th><th>Fecha</th><th>Usuario</th><th>Pedido de Materia Prima</th><th>Observaciones</th><th>Estado</th><th>Acciones</th>
</tr></thead><tbody>
<?php foreach ($pedidos as $pedido): $id = (int)$pedido['pedido_id']; $pendiente = strtoupper(trim($pedido['pedido_estado'])) === 'PENDIENTE'; ?>
<tr>
    <td><?= $h($pedido['pedido_nro']) ?></td><td><?= $h($pedido['pedido_fecha']) ?></td><td><?= $h($pedido['username']) ?></td>
    <td><?= $pedido['pedido_mp_id'] === null ? 'Manual' : '#' . (int)$pedido['pedido_mp_id'] ?></td>
    <td><?= $h($pedido['pedido_observacion']) ?></td>
    <td><span class="badge <?= $pendiente ? 'badge-warning' : (strtoupper(trim($pedido['pedido_estado'])) === 'ANULADO' ? 'badge-danger' : 'badge-success') ?>"><?= $h($pedido['pedido_estado']) ?></span></td>
    <td class="text-nowrap">
        <button type="button" class="btn btn-info btn-sm btn-detalle" data-id="<?= $id ?>" title="Ver detalle"><i class="fas fa-eye"></i></button>
        <?php if ($pendiente): ?>
        <a class="btn btn-warning btn-sm" href="?form_pedido_compra=edit&form=edit&ped_id=<?= $id ?>" title="Editar"><i class="fas fa-edit"></i></a>
        <button type="button" class="btn btn-danger btn-sm btn-anular" data-id="<?= $id ?>" data-numero="<?= $h($pedido['pedido_nro']) ?>" title="Anular"><i class="fas fa-ban"></i></button>
        <?php endif; ?>
        <a class="btn btn-secondary btn-sm" href="reporte.php?ped_id=<?= $id ?>" target="_blank" title="Reporte"><i class="fas fa-print"></i></a>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div></div>
<?php else: include __DIR__ . '/form.php'; endif; ?>
<div class="modal fade" id="detalleModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Detalle del Pedido</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
<div class="modal-body"><table class="table table-bordered"><thead><tr><th>Materia prima</th><th>Unidad</th><th>Cantidad</th></tr></thead><tbody id="detallePedidoBody"></tbody></table></div>
</div></div></div>
<div class="modal fade" id="anularModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Confirmar anulación</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
<div class="modal-body"><p id="anularPedidoInfo"></p><div class="alert alert-danger d-none" id="errorAnular"></div></div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button><button type="button" class="btn btn-danger" id="confirmarAnularBtn">Anular</button></div>
</div></div></div>
<?php
$inline_js = <<<'JS'
$(function() {
    if ($('#mensajeResultadoPedido').length) {
        setTimeout(function() { $('#mensajeResultadoPedido').alert('close'); }, 3000);
    }
    if ($('#dataTable').length) $('#dataTable').DataTable({language: {url: '../../../vendor/datatables/Spanish.json'}, order: [[0, 'desc']]});
    $('#dataTable').on('click', '.btn-detalle', async function() {
        const cuerpo = document.getElementById('detallePedidoBody');
        cuerpo.replaceChildren();
        $('#detalleModal').modal('show');
        try {
            const respuesta = await fetch('get_detalle.php?ped_id=' + encodeURIComponent($(this).data('id')));
            const datos = await respuesta.json();
            if (!datos.success) throw new Error(datos.message || 'No se pudo consultar el detalle.');
            datos.detalle.forEach(item => {
                const fila = document.createElement('tr');
                [item.mp_descripcion, item.unidad_medida_nombre || '-', item.detalle_cantidad].forEach(valor => {
                    const celda = document.createElement('td'); celda.textContent = valor; fila.appendChild(celda);
                });
                cuerpo.appendChild(fila);
            });
        } catch (e) {
            const fila = document.createElement('tr'); const celda = document.createElement('td');
            celda.colSpan = 3; celda.textContent = e.message; fila.appendChild(celda); cuerpo.appendChild(fila);
        }
    });
    let pedidoId = 0;
    $('#dataTable').on('click', '.btn-anular', function() {
        pedidoId = $(this).data('id');
        $('#anularPedidoInfo').text('¿Anular el Pedido N° ' + $(this).data('numero') + '?');
        $('#errorAnular').addClass('d-none').text('');
        $('#confirmarAnularBtn').prop('disabled', false);
        $('#anularModal').modal('show');
    });
    $('#confirmarAnularBtn').on('click', async function() {
        const boton = $(this); boton.prop('disabled', true);
        try {
            const respuesta = await fetch('anular_pedido.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ped_id: pedidoId})});
            const datos = await respuesta.json();
            if (!datos.success) throw new Error(datos.message || 'No se pudo anular.');
            location.href = 'view.php?alert=3';
        } catch (e) { $('#errorAnular').removeClass('d-none').text(e.message); boton.prop('disabled', false); }
    });
});
JS;
include PROJECT_ROOT . '/footer.php';
