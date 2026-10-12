<?php
require_once __DIR__ . '/pedido_helper.php';
try {
    $pdo = pedidoConexion();
    pedidoUsuario($pdo);
    require_once PROJECT_ROOT . '/reporte/pedido_compras.php';
    $id = pedidoEnteroPositivo($_GET['ped_id'] ?? null);
    $pedido = pedidoCabecera($pdo, $id);
    $q = $pdo->prepare('SELECT username FROM usuario WHERE usuario_id = :id');
    $q->execute([':id' => $pedido['usuario_id']]);
    $username = (string)$q->fetchColumn();
    $detalles = pedidoDetalle($pdo, $id);
    $pdf = new BasePDF('P', 'mm', 'A4', ['titulo' => 'Pedido de Compra', 'subtitulo' => 'Pedido N° ' . $pedido['pedido_nro']]);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->seccionTitulo('Datos del pedido');
    $pdf->filaDato('N° Pedido:', $pedido['pedido_nro'], 35);
    $pdf->filaDato('Fecha:', $pedido['pedido_fecha'], 35);
    $pdf->filaDato('Usuario:', $username, 35);
    $pdf->filaDato('Estado:', $pedido['pedido_estado'], 35);
    $pdf->filaDato('Pedido de MP:', $pedido['pedido_mp_id'] === null ? 'Carga manual' : '#' . $pedido['pedido_mp_id'], 35);
    if ($pedido['pedido_observacion'] !== '') $pdf->filaDato('Observaciones:', $pedido['pedido_observacion'], 35);
    $pdf->seccionTitulo('Detalle de materias primas');
    $anchos = [18, 98, 34, 30];
    $pdf->tablaEncabezado($anchos, ['Cod.', 'Materia prima', 'Unidad', 'Cantidad']);
    foreach ($detalles as $fila) {
        $pdf->tablaFila($anchos, [$fila['mp_id'], $fila['mp_descripcion'], $fila['unidad_medida_nombre'] ?? '-', number_format((int)$fila['detalle_cantidad'], 0, ',', '.')]);
    }
    $pdf->pieDocumento();
    $pdf->Output('I', 'Pedido_Compra_' . $id . '.pdf');
} catch (Throwable $e) {
    error_log('Reporte pedido compra: ' . $e->getMessage());
    http_response_code(400);
    echo htmlspecialchars($e instanceof PDOException ? 'No se pudo generar el reporte.' : $e->getMessage(), ENT_QUOTES, 'UTF-8');
}
