<?php
// Funciones compartidas del caso de uso de pedidos de compra.
function pedidoEnteroPositivo($valor): int {
    if (!is_scalar($valor) || !preg_match('/^[0-9]+$/D', (string)$valor) ||
        (float)$valor < 1 || (float)$valor > 2147483647) {
        throw new InvalidArgumentException('Las materias primas y cantidades deben ser números enteros positivos.');
    }
    return (int)$valor;
}

function pedidoNormalizarDetalle(array $filas): array {
    if (!$filas) throw new InvalidArgumentException('El pedido debe contener al menos una materia prima.');
    $detalle = [];
    foreach ($filas as $fila) {
        if (!is_array($fila)) throw new InvalidArgumentException('Detalle inválido.');
        $id = pedidoEnteroPositivo($fila['mp_id'] ?? null);
        $cantidad = pedidoEnteroPositivo($fila['detalle_cantidad'] ?? null);
        $total = ($detalle[$id] ?? 0) + $cantidad;
        if ($total > 2147483647) throw new InvalidArgumentException('La cantidad consolidada supera el límite permitido.');
        $detalle[$id] = $total;
    }
    ksort($detalle);
    return $detalle;
}

function pedidoConexion(): PDO {
    require_once __DIR__ . '/../../../config/bootstrap.php';
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['username'])) {
        if (defined('PEDIDO_JSON')) {
            http_response_code(401);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => 'Sesión inválida.']);
        } else {
            header('Location: ' . loginUrl());
        }
        exit;
    }
    ob_start();
    require PROJECT_ROOT . '/config/database.php';
    ob_end_clean();
    require_once PROJECT_ROOT . '/config/permissions.php';
    check_permission('PEDIDO_COMPRAS');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    date_default_timezone_set('America/Asuncion');
    $pdo->exec("SET TIME ZONE 'America/Asuncion'");
    return $pdo;
}

function pedidoUsuario(PDO $pdo): int {
    $q = $pdo->prepare("SELECT usuario_id FROM usuario WHERE username = :username AND UPPER(TRIM(usuario_estado)) = 'ACTIVO'");
    $q->execute([':username' => $_SESSION['username']]);
    $id = $q->fetchColumn();
    if (!$id) throw new RuntimeException('El usuario no existe o está inactivo.');
    return (int)$id;
}

function pedidoCabecera(PDO $pdo, int $id, bool $bloquear = false): array {
    $q = $pdo->prepare('SELECT * FROM pedido_compra WHERE pedido_id = :id' . ($bloquear ? ' FOR UPDATE' : ''));
    $q->execute([':id' => $id]);
    $pedido = $q->fetch();
    if (!$pedido) throw new RuntimeException('Pedido no encontrado.');
    return $pedido;
}

function pedidoDetalle(PDO $pdo, int $id): array {
    $q = $pdo->prepare('SELECT pedido_detalle_compra.mp_id, pedido_detalle_compra.detalle_cantidad,
        materia_prima.mp_descripcion, unidad_medida.unidad_medida_nombre
        FROM pedido_detalle_compra
        JOIN materia_prima ON materia_prima.mp_id = pedido_detalle_compra.mp_id
        LEFT JOIN unidad_medida ON unidad_medida.unidad_medida_id = materia_prima.unidad_medida_id
        WHERE pedido_detalle_compra.pedido_id = :id ORDER BY pedido_detalle_compra.mp_id');
    $q->execute([':id' => $id]);
    return $q->fetchAll();
}

function pedidoVersion(array $cabecera, array $detalle): string {
    // Detecta cambios sin exigir una columna de última modificación.
    $filas = array_map(fn($fila) => [(int)$fila['mp_id'], (int)$fila['detalle_cantidad']], $detalle);
    return hash('sha256', json_encode([$cabecera, $filas], JSON_UNESCAPED_UNICODE));
}

function pedidoOrigen(PDO $pdo, int $id, bool $bloquear = false): array {
    $q = $pdo->prepare('SELECT * FROM pedido_materia_prima WHERE pedido_mp_id = :id' . ($bloquear ? ' FOR UPDATE' : ''));
    $q->execute([':id' => $id]);
    $origen = $q->fetch();
    if (!$origen || strtoupper(trim($origen['pedido_mp_estado'])) !== 'PENDIENTE') {
        throw new RuntimeException('El Pedido de Materia Prima ya no está PENDIENTE o no existe.');
    }
    $q = $pdo->prepare("SELECT 1 FROM pedido_compra WHERE pedido_mp_id = :id AND UPPER(TRIM(pedido_estado)) <> 'ANULADO' LIMIT 1");
    $q->execute([':id' => $id]);
    if ($q->fetchColumn()) throw new RuntimeException('El Pedido de Materia Prima ya está vinculado a una compra vigente.');
    $q = $pdo->prepare('SELECT pedido_detalle_materia_prima.mp_id, pedido_detalle_materia_prima.det_cantidad_solicitada,
        materia_prima.mp_descripcion, materia_prima.mp_estado, unidad_medida.unidad_medida_nombre
        FROM pedido_detalle_materia_prima
        JOIN materia_prima ON materia_prima.mp_id = pedido_detalle_materia_prima.mp_id
        LEFT JOIN unidad_medida ON unidad_medida.unidad_medida_id = materia_prima.unidad_medida_id
        WHERE pedido_detalle_materia_prima.pedido_mp_id = :id ORDER BY pedido_detalle_materia_prima.mp_id');
    $q->execute([':id' => $id]);
    $detalle = [];
    foreach ($q->fetchAll() as $fila) {
        if (strtoupper(trim($fila['mp_estado'])) !== 'ACTIVO') throw new RuntimeException('El pedido origen contiene materias primas inactivas.');
        $fila['detalle_cantidad'] = pedidoEnteroPositivo($fila['det_cantidad_solicitada']);
        $detalle[] = $fila;
    }
    if (!$detalle) throw new RuntimeException('El Pedido de Materia Prima no tiene ítems.');
    return $detalle;
}

function pedidoAuditar(PDO $pdo, int $usuario, int $id, string $accion, string $descripcion): void {
    $q = $pdo->prepare("INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
        VALUES (:usuario, 'pedido compra', :id, :accion, :descripcion)");
    $q->execute([':usuario' => $usuario, ':id' => $id, ':accion' => $accion, ':descripcion' => $descripcion]);
}

function pedidoSinDocumentosPosteriores(PDO $pdo, int $id): void {
    // Toda orden y factura del esquema se vincula al pedido a través de presupuesto_compra.
    $q = $pdo->prepare('SELECT presu_nro FROM presupuesto_compra WHERE pedido_id = :id LIMIT 1');
    $q->execute([':id' => $id]);
    $numero = $q->fetchColumn();
    if ($numero !== false) throw new RuntimeException('El pedido tiene un presupuesto asociado (' . $numero . ') y no puede editarse ni anularse.');
}

function pedidoAnular(PDO $pdo, int $id, int $usuario): void {
    $pdo->beginTransaction();
    $pedido = pedidoCabecera($pdo, $id, true);
    if (strtoupper(trim($pedido['pedido_estado'])) !== 'PENDIENTE') throw new RuntimeException('Solo se pueden anular pedidos PENDIENTES.');
    pedidoSinDocumentosPosteriores($pdo, $id);
    $q = $pdo->prepare("UPDATE pedido_compra SET pedido_estado = 'ANULADO' WHERE pedido_id = :id");
    $q->execute([':id' => $id]);
    if ($pedido['pedido_mp_id'] !== null) {
        $q = $pdo->prepare("UPDATE pedido_materia_prima SET pedido_mp_estado = 'PENDIENTE'
            WHERE pedido_mp_id = :id AND UPPER(TRIM(pedido_mp_estado)) = 'PROCESADO'");
        $q->execute([':id' => $pedido['pedido_mp_id']]);
    }
    pedidoAuditar($pdo, $usuario, $id, 'ANULACION', 'Pedido de Compra #' . $id . ' anulado; origen: ' . ($pedido['pedido_mp_id'] ?? 'manual'));
    $pdo->commit();
}

function pedidoNumero(int $id): string {
    return 'PC-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
}

function pedidoProximoNumero(PDO $pdo): string {
    // Consulta la secuencia sin avanzarla; el valor es orientativo hasta guardar.
    $id = $pdo->query("SELECT COALESCE(
            pg_sequence_last_value(seqrelid) + seqincrement, seqstart)
        FROM pg_sequence
        WHERE seqrelid = pg_get_serial_sequence('pedido_compra', 'pedido_id')::regclass")->fetchColumn();
    if ($id === false) throw new RuntimeException('No se encontró la secuencia del pedido.');
    return pedidoNumero((int)$id);
}

function pedidoGuardar(PDO $pdo, int $usuario, string $accion, array $post): int {
    if (!in_array($accion, ['insert', 'update'], true)) throw new RuntimeException('Acción inválida.');
    $filas = json_decode($post['productos'] ?? '', true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($filas)) throw new RuntimeException('Detalle inválido.');
    $detalle = pedidoNormalizarDetalle($filas);
    $observacion = trim($post['pedido_observacion'] ?? '');
    $pdo->beginTransaction();
    $origenId = null;
    $antes = [];
    if ($accion === 'update') {
        $id = pedidoEnteroPositivo($post['pedido_id'] ?? null);
        $pedido = pedidoCabecera($pdo, $id, true);
        if (strtoupper(trim($pedido['pedido_estado'])) !== 'PENDIENTE') throw new RuntimeException('Solo se pueden editar pedidos PENDIENTES.');
        pedidoSinDocumentosPosteriores($pdo, $id);
        $antes = pedidoDetalle($pdo, $id);
        if (!hash_equals(pedidoVersion($pedido, $antes), (string)($post['version'] ?? ''))) {
            throw new RuntimeException('El pedido fue modificado. Recargue el formulario antes de guardar.');
        }
    } else {
        $origenId = ($post['pedido_mp_id'] ?? '') !== '' ? pedidoEnteroPositivo($post['pedido_mp_id']) : null;
        if ($origenId !== null) pedidoOrigen($pdo, $origenId, true);
    }
    // Todas las materias primas se validan en servidor y se bloquean durante el guardado.
    $consulta = $pdo->prepare("SELECT mp_estado FROM materia_prima WHERE mp_id = :id FOR SHARE");
    foreach ($detalle as $mpId => $cantidad) {
        $consulta->execute([':id' => $mpId]);
        $estado = $consulta->fetchColumn();
        if ($estado === false || strtoupper(trim($estado)) !== 'ACTIVO') throw new RuntimeException('Una materia prima seleccionada no existe o está inactiva.');
    }
    if ($accion === 'insert') {
        $id = (int)$pdo->query("SELECT nextval(pg_get_serial_sequence('pedido_compra', 'pedido_id'))")->fetchColumn();
        $consulta = $pdo->prepare("INSERT INTO pedido_compra
            (pedido_id, pedido_nro, pedido_fecha, pedido_estado, usuario_id, pedido_observacion, pedido_mp_id)
            VALUES (:id, :numero, CURRENT_DATE, 'PENDIENTE', :usuario, :observacion, :origen)");
        $consulta->execute([':id' => $id, ':numero' => pedidoNumero($id), ':usuario' => $usuario, ':observacion' => $observacion, ':origen' => $origenId]);
    } else {
        $consulta = $pdo->prepare('UPDATE pedido_compra SET pedido_observacion = :observacion WHERE pedido_id = :id');
        $consulta->execute([':observacion' => $observacion, ':id' => $id]);
        $consulta = $pdo->prepare('DELETE FROM pedido_detalle_compra WHERE pedido_id = :id');
        $consulta->execute([':id' => $id]);
    }
    $consulta = $pdo->prepare('INSERT INTO pedido_detalle_compra (pedido_id, mp_id, detalle_cantidad) VALUES (:pedido, :mp, :cantidad)');
    foreach ($detalle as $mpId => $cantidad) $consulta->execute([':pedido' => $id, ':mp' => $mpId, ':cantidad' => $cantidad]);
    if ($origenId !== null) {
        $consulta = $pdo->prepare("UPDATE pedido_materia_prima SET pedido_mp_estado = 'PROCESADO' WHERE pedido_mp_id = :id");
        $consulta->execute([':id' => $origenId]);
    }
    if ($accion === 'insert') {
        $origen = 'Carga manual';
        if ($origenId !== null) {
            $consulta = $pdo->prepare('SELECT pedido_mp_nro FROM pedido_materia_prima WHERE pedido_mp_id = :id');
            $consulta->execute([':id' => $origenId]);
            $origen = 'Pedido de Materia Prima ' . $consulta->fetchColumn() . ' (ID: ' . $origenId . ')';
        }
        $lineas = [
            'Se registró el Pedido de Compra ' . pedidoNumero($id) . ' (ID: ' . $id . ') en estado PENDIENTE.',
            'Origen: ' . $origen . '.',
            'Materias primas solicitadas:',
        ];
        // Usa los nombres y cantidades realmente guardados, no etiquetas enviadas por el navegador.
        foreach (pedidoDetalle($pdo, $id) as $fila) {
            $unidad = trim((string)($fila['unidad_medida_nombre'] ?? ''));
            $lineas[] = '- ' . $fila['mp_descripcion'] . ' (código: ' . $fila['mp_id'] . '): '
                . number_format((int)$fila['detalle_cantidad'], 0, ',', '.')
                . ($unidad !== '' ? ' ' . $unidad : ' (sin unidad de medida)') . '.';
        }
        $lineas[] = 'Observaciones: ' . ($observacion !== '' ? $observacion : 'Sin observaciones.');
        $descripcion = implode("\n", $lineas);
    } else {
        $descripcion = pedidoDescripcionModificacion($pedido, $antes, pedidoDetalle($pdo, $id), $observacion);
    }
    pedidoAuditar($pdo, $usuario, $id, $accion === 'insert' ? 'ALTA' : 'MODIFICACION', $descripcion);
    $pdo->commit();
    return $id;
}

function pedidoDescripcionModificacion(array $pedido, array $antes, array $despues, string $observacion): string {
    $anteriores = array_column($antes, null, 'mp_id');
    $actuales = array_column($despues, null, 'mp_id');
    $cambios = [];
    $etiqueta = fn(array $fila): string => $fila['mp_descripcion'] . ' (código: ' . $fila['mp_id'] . ')';
    $cantidad = function(array $fila): string {
        $unidad = trim((string)($fila['unidad_medida_nombre'] ?? ''));
        return number_format((int)$fila['detalle_cantidad'], 0, ',', '.')
            . ($unidad !== '' ? ' ' . $unidad : ' (sin unidad de medida)');
    };
    foreach ($actuales as $mpId => $fila) {
        if (!isset($anteriores[$mpId])) {
            $cambios[] = '- Agregada: ' . $etiqueta($fila) . ', cantidad: ' . $cantidad($fila) . '.';
        } elseif ((int)$anteriores[$mpId]['detalle_cantidad'] !== (int)$fila['detalle_cantidad']) {
            $cambios[] = '- Cantidad modificada: ' . $etiqueta($fila) . ', de '
                . $cantidad($anteriores[$mpId]) . ' a ' . $cantidad($fila) . '.';
        }
    }
    foreach ($anteriores as $mpId => $fila) {
        if (!isset($actuales[$mpId])) $cambios[] = '- Quitada: ' . $etiqueta($fila) . ', cantidad anterior: ' . $cantidad($fila) . '.';
    }
    if ($pedido['pedido_observacion'] !== $observacion) {
        $cambios[] = 'Observaciones modificadas: de "' . ($pedido['pedido_observacion'] !== '' ? $pedido['pedido_observacion'] : 'Sin observaciones')
            . '" a "' . ($observacion !== '' ? $observacion : 'Sin observaciones') . '".';
    }
    if (!$cambios) $cambios[] = 'Se guardó el pedido sin cambios en materias primas, cantidades u observaciones.';
    array_unshift($cambios, 'Se modificó el Pedido de Compra ' . $pedido['pedido_nro']
        . ' (ID: ' . $pedido['pedido_id'] . '). Estado: ' . $pedido['pedido_estado'] . '.');
    return implode("\n", $cambios);
}
