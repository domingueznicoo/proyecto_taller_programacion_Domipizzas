<?php
require_once __DIR__ . '/pedido_helper.php';
try {
    $pdo = pedidoConexion();
    pedidoUsuario($pdo);
    require_once PROJECT_ROOT . '/reporte/pedido_compras.php';
    $desde = trim($_GET['desde'] ?? '');
    $hasta = trim($_GET['hasta'] ?? '');
    foreach ([$desde, $hasta] as $fecha) {
        $valor = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if (!$valor || $valor->format('Y-m-d') !== $fecha) throw new RuntimeException('Ingrese fechas válidas desde y hasta.');
    }
    if ($hasta < $desde) throw new RuntimeException('Hasta no puede ser anterior a Desde.');
    $estado = strtoupper(trim($_GET['estado'] ?? 'TOTAL'));
    $total = in_array($estado, ['TOTAL', 'REPORTE TOTAL'], true);
    if (!$total && !in_array($estado, ['PENDIENTE', 'PROCESADO', 'ANULADO', 'APROBADO'], true)) throw new RuntimeException('Estado de reporte inválido.');
    $sql = 'SELECT pedido_compra.pedido_nro, pedido_compra.pedido_fecha, pedido_compra.pedido_estado,
        usuario.username, materia_prima.mp_descripcion, pedido_detalle_compra.detalle_cantidad
        FROM pedido_compra
        JOIN usuario ON usuario.usuario_id = pedido_compra.usuario_id
        JOIN pedido_detalle_compra ON pedido_detalle_compra.pedido_id = pedido_compra.pedido_id
        JOIN materia_prima ON materia_prima.mp_id = pedido_detalle_compra.mp_id
        WHERE pedido_compra.pedido_fecha BETWEEN :desde AND :hasta';
    $parametros = [':desde' => $desde, ':hasta' => $hasta];
    if (!$total) { $sql .= ' AND UPPER(TRIM(pedido_compra.pedido_estado)) = :estado'; $parametros[':estado'] = $estado; }
    $sql .= ' ORDER BY pedido_compra.pedido_id DESC, materia_prima.mp_descripcion';
    $q = $pdo->prepare($sql); $q->execute($parametros);
    $pdf = new BasePDF('P', 'mm', 'A4', ['titulo' => 'Informe de Pedidos de Compra', 'subtitulo' => $desde . ' al ' . $hasta . ' | ' . $estado]);
    $pdf->AliasNbPages(); $pdf->AddPage();
    $anchos = [18, 30, 65, 17, 25, 25];
    $pdf->tablaEncabezado($anchos, ['Pedido', 'Usuario', 'Materia prima', 'Cant.', 'Fecha', 'Estado']);
    $filas = $q->fetchAll();
    foreach ($filas as $fila) $pdf->tablaFila($anchos, [$fila['pedido_nro'], $fila['username'], $fila['mp_descripcion'], $fila['detalle_cantidad'], $fila['pedido_fecha'], $fila['pedido_estado']]);
    if (!$filas) $pdf->filaDato('Resultado:', 'Sin pedidos para los filtros seleccionados.');
    $pdf->pieDocumento(); $pdf->Output('I', 'Informe_Pedidos_Compra.pdf');
} catch (Throwable $e) {
    error_log('Informe pedidos compra: ' . $e->getMessage());
    http_response_code(400);
    echo htmlspecialchars($e instanceof PDOException ? 'No se pudo generar el informe.' : $e->getMessage(), ENT_QUOTES, 'UTF-8');
}
