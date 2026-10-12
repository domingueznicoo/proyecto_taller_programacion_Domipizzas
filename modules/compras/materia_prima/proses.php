<?php
// Procesa altas, ediciones y anulaciones de materias primas (?act=).
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/permissions.php';
check_permission(['COMPRAS', 'REFERENCIALES']);

// NO establecer Content-Type aquí porque algunas acciones hacen redirects HTML
// Se establecerá solo cuando sea necesario para respuestas JSON

// Función bitácora
function bitacora(PDO $pdo, int $idUsuario, string $accion, string $desc, ?int $id = null): void {
    $pdo->exec("SAVEPOINT sp_bit");
    try {
        $sql = "
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:u, 'MateriaPrima', :id, :acc, :d)
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':u' => $idUsuario, ':id' => $id, ':acc' => strtoupper($accion), ':d' => $desc]);
    } catch (Throwable $e) {
        $pdo->exec("ROLLBACK TO SAVEPOINT sp_bit");
        error_log("Bitácora falló: " . $e->getMessage());
    }
}

// Normalizar descripción
function normalize_desc(string $s): string {
    $s = trim($s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return mb_strtoupper($s, 'UTF-8');
}

/**
 * Verifica si la materia prima puede inactivarse sin conflictos.
 * Retorna ['puede' => bool, 'stock' => float, 'bloqueos' => string[]]
 */
function validarInactivacionMateriaPrima(PDO $pdo, int $materiaPrimaId): array {
    $bloqueos = [];

    $qStock = $pdo->prepare("
        SELECT COALESCE(SUM(stock_cantidad), 0) AS stock_total
        FROM stock_materia_prima
        WHERE mp_id = :id
    ");
    $qStock->execute([':id' => $materiaPrimaId]);
    $stock = (float)$qStock->fetchColumn();

    if ($stock > 0) {
        $bloqueos[] = 'La materia prima tiene stock de ' . number_format($stock, 0, ',', '.') . ' unidades.';
    }

    $vinculos = [
        'ajustes_detalle' => 'movimientos de ajuste de inventario',
        'pedido_detalle_compra' => 'pedidos de compra',
        'presupuesto_detalle_compra' => 'presupuestos de compra',
        'orden_detalle_compra' => 'órdenes de compra',
        'factura_detalle_compra' => 'facturas de compra',
        'nota_detalle_compra' => 'notas de crédito/débito de compra',
        'nota_remision_detalle_compra' => 'notas de remisión de compra',
        'pedido_detalle_materia_prima' => 'pedidos de materia prima de producción',
        'receta_detalle' => 'recetas',
    ];

    foreach ($vinculos as $tabla => $descripcion) {
        $q = $pdo->prepare("SELECT 1 FROM {$tabla} WHERE mp_id = :id LIMIT 1");
        $q->execute([':id' => $materiaPrimaId]);
        if ($q->fetchColumn()) {
            $bloqueos[] = "Tiene registros en {$descripcion}.";
        }
    }

    return [
        'puede' => empty($bloqueos),
        'stock' => $stock,
        'bloqueos' => $bloqueos,
    ];
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Obtener usuario
    if (empty($_SESSION['username'])) {
        header("Location: view.php?alert=4&msg=" . urlencode("Sesión inválida"));
        exit();
    }

    $qUsuario = $pdo->prepare("SELECT usuario_id FROM usuario WHERE username = :u LIMIT 1");
    $qUsuario->execute([':u' => $_SESSION['username']]);
    $usuario = $qUsuario->fetch();
    
    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
    $idUsuario = (int)$usuario['usuario_id'];

    // Toggle estado (AJAX)
    if (isset($_GET['act']) && $_GET['act'] === 'toggle_estado' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        if (ob_get_length()) { ob_clean(); }
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $estado = isset($_POST['estado']) ? strtoupper(trim($_POST['estado'])) : '';

        if ($estado === 'ANULAR' || $estado === 'ANULADA') {
            $estado = 'INACTIVO';
        }

        if (!$id || !in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            echo json_encode(['ok' => false, 'msg' => 'Parámetros inválidos']);
            exit;
        }

        try {
            $pdo->beginTransaction();
            
            $q = $pdo->prepare("UPDATE materia_prima SET mp_estado = :estado WHERE mp_id = :id");
            $q->execute([':estado' => $estado, ':id' => $id]);
            
            bitacora($pdo, $idUsuario, 'MODIFICACION', "Estado de la materia prima #{$id} cambiado a {$estado}", $id);
            
            $pdo->commit();
            echo json_encode(['ok' => true, 'estado' => $estado]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'msg' => 'DB: ' . $e->getMessage()]);
        }
        exit;
    }

    // Detectar acción
    if (!isset($_GET['act'])) {
        header("Location: view.php?alert=4");
        exit();
    }

    $action = $_GET['act'];

    // INSERT
    if ($action == 'insert' && isset($_POST['Guardar'])) {
        $descripcion = normalize_desc($_POST['mp_descripcion'] ?? '');
        $unidad_medida_id = isset($_POST['unidad_medida_id']) ? (int)$_POST['unidad_medida_id'] : 0;
        $tipo_iva_id = isset($_POST['tipo_iva_id']) ? (int)$_POST['tipo_iva_id'] : null;
        $deposito_id = isset($_POST['deposito_id']) && $_POST['deposito_id'] !== '' ? (int)$_POST['deposito_id'] : 0;

        $marcaId = (int)($_POST['marca_id'] ?? 0);
        $precioReferencia = trim($_POST['mp_precio_referencia'] ?? '');
        if ($marcaId <= 0 || !preg_match('/\A\d+(?:\.\d{1,2})?\z/', $precioReferencia)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Seleccioná una marca y un precio de referencia válido.'));
            exit();
        }
        if (mb_strlen($descripcion, 'UTF-8') > 30) {
            header('Location: view.php?alert=4&msg=' . urlencode('La descripción admite hasta 30 caracteres.'));
            exit();
        }

        // Validaciones
        if (empty($descripcion)) {
            header("Location: view.php?alert=4&msg=" . urlencode("La descripción es obligatoria."));
            exit();
        }
        if ($unidad_medida_id <= 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("Debe seleccionar una unidad de medida."));
            exit();
        }
        if ($tipo_iva_id === null || $tipo_iva_id <= 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("Debe seleccionar un tipo de IVA."));
            exit();
        }
        if ($deposito_id <= 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("Debe seleccionar un depósito."));
            exit();
        }

        try {
            $pdo->beginTransaction();

            // Insertar materia prima (solo con las columnas que existen)
            $query = $pdo->prepare("
                INSERT INTO materia_prima (mp_descripcion, mp_estado, unidad_medida_id, tipo_iva_id, usuario_id, marca_id, mp_precio_referencia, deposito_id)
                VALUES (:descri, 'ACTIVO', :unidad_medida_id, :tipo_iva_id, :id_usuario, :marca_id, :precio, :deposito_id)
                RETURNING mp_id
            ");
            $query->execute([
                ':descri' => $descripcion,
                ':unidad_medida_id' => $unidad_medida_id,
                ':tipo_iva_id' => $tipo_iva_id,
                ':id_usuario' => $idUsuario,
                ':marca_id' => $marcaId,
                ':precio' => $precioReferencia,
                ':deposito_id' => $deposito_id
            ]);
            $materiaPrimaId = (int)$query->fetchColumn();

            // Crear registro de stock inicial en el depósito predeterminado
            $pdo->exec("SAVEPOINT sp_stock");
            try {
                $checkStock = $pdo->prepare("
                    SELECT 1 FROM stock_materia_prima 
                    WHERE mp_id = :materia_prima_id AND deposito_id = :deposito_id
                    LIMIT 1
                ");
                $checkStock->execute([
                    ':materia_prima_id' => $materiaPrimaId,
                    ':deposito_id' => $deposito_id
                ]);
                
                if (!$checkStock->fetchColumn()) {
                    $queryStock = $pdo->prepare("
                        INSERT INTO stock_materia_prima (mp_id, deposito_id, stock_cantidad, usuario_id)
                        VALUES (:materia_prima_id, :deposito_id, 0, :id_usuario)
                    ");
                    $queryStock->execute([
                        ':materia_prima_id' => $materiaPrimaId,
                        ':deposito_id' => $deposito_id,
                        ':id_usuario' => $idUsuario
                    ]);
                }
                $pdo->exec("RELEASE SAVEPOINT sp_stock");
            } catch (PDOException $e) {
                $pdo->exec("ROLLBACK TO SAVEPOINT sp_stock");
                throw $e;
            }

            bitacora($pdo, $idUsuario, 'ALTA', "Materia prima creada: {$descripcion} (ID: {$materiaPrimaId})", $materiaPrimaId);

            $pdo->commit();
            header("Location: view.php?alert=1");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errorMsg = $e->getMessage();
            error_log("Error insert materia_prima: " . $errorMsg);
            
            // Mensaje de error más específico
            $userMessage = 'Error al crear la materia prima. Verifique los datos ingresados.';
            
            // Detectar errores comunes de PostgreSQL
            if (stripos($errorMsg, 'column') !== false && stripos($errorMsg, 'does not exist') !== false) {
                $userMessage = 'Error: Una columna no existe. Verifique la estructura de la tabla materia_prima.';
            } elseif (stripos($errorMsg, 'null value') !== false && stripos($errorMsg, 'violates not-null constraint') !== false) {
                $userMessage = 'Error: Faltan datos obligatorios. Complete todos los campos requeridos.';
            } elseif (stripos($errorMsg, 'foreign key') !== false) {
                if (stripos($errorMsg, 'unidad_medida_id') !== false) {
                    $userMessage = 'Error: La unidad de medida seleccionada no es válida.';
                } elseif (stripos($errorMsg, 'tipo_iva_id') !== false) {
                    $userMessage = 'Error: El tipo de IVA seleccionado no es válido.';
                } else {
                    $userMessage = 'Error de relación de datos. Verifique las selecciones.';
                }
            }
            
            // Detectar errores de transacción abortada
            if (stripos($errorMsg, 'transaction is aborted') !== false || stripos($errorMsg, '25P02') !== false) {
                $userMessage = 'Error: Ocurrió un problema durante el proceso. Por favor, verifique que todos los datos sean correctos e intente nuevamente.';
            }
            
            // Mostrar el error real para debugging (temporalmente)
            // TODO: Remover después de identificar el problema
            if (empty($userMessage) || $userMessage === 'Error al crear la materia prima. Verifique los datos ingresados.') {
                $userMessage = 'Error: ' . htmlspecialchars($errorMsg);
            }
            
            header("Location: view.php?alert=4&msg=" . urlencode($userMessage));
            exit();
        }
    }

    // UPDATE
    elseif ($action == 'update' && isset($_POST['Guardar'])) {
        $materia_prima_id = isset($_POST['mp_id']) ? (int)$_POST['mp_id'] : 0;
        $descripcion = normalize_desc($_POST['mp_descripcion'] ?? '');
        $unidad_medida_id = isset($_POST['unidad_medida_id']) ? (int)$_POST['unidad_medida_id'] : 0;
        $tipo_iva_id = isset($_POST['tipo_iva_id']) ? (int)$_POST['tipo_iva_id'] : null;
        $deposito_id = isset($_POST['deposito_id']) && $_POST['deposito_id'] !== '' ? (int)$_POST['deposito_id'] : 0;
        $estado = isset($_POST['mp_estado']) ? strtoupper(trim($_POST['mp_estado'])) : 'ACTIVO';

        $marcaId = (int)($_POST['marca_id'] ?? 0);
        $precioReferencia = trim($_POST['mp_precio_referencia'] ?? '');
        if ($marcaId <= 0 || !preg_match('/\A\d+(?:\.\d{1,2})?\z/', $precioReferencia)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Seleccioná una marca y un precio de referencia válido.'));
            exit();
        }
        if (mb_strlen($descripcion, 'UTF-8') > 30) {
            header('Location: view.php?alert=4&msg=' . urlencode('La descripción admite hasta 30 caracteres.'));
            exit();
        }

        if (!in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            header('Location: view.php?alert=4');
            exit();
        }

        if ($materia_prima_id <= 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("ID de materia prima inválido."));
            exit();
        }
        if (empty($descripcion)) {
            header("Location: view.php?alert=4&msg=" . urlencode("La descripción es obligatoria."));
            exit();
        }
        if ($unidad_medida_id <= 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("Debe seleccionar una unidad de medida."));
            exit();
        }
        if ($tipo_iva_id === null || $tipo_iva_id <= 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("Debe seleccionar un tipo de IVA."));
            exit();
        }
        if ($deposito_id <= 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("Debe seleccionar un depósito."));
            exit();
        }

        try {
            $pdo->beginTransaction();

            // Actualizar materia prima (solo con las columnas que existen)
            $query = $pdo->prepare("
                UPDATE materia_prima 
                SET mp_descripcion = :descri, 
                    mp_estado = :estado,
                    unidad_medida_id = :unidad_medida_id, 
                    tipo_iva_id = :tipo_iva_id,
                    marca_id = :marca_id,
                    mp_precio_referencia = :precio,
                    deposito_id = :deposito_id
                WHERE mp_id = :id
            ");
            $query->execute([
                ':descri' => $descripcion,
                ':estado' => $estado,
                ':unidad_medida_id' => $unidad_medida_id,
                ':tipo_iva_id' => $tipo_iva_id,
                ':id' => $materia_prima_id,
                ':marca_id' => $marcaId,
                ':precio' => $precioReferencia,
                ':deposito_id' => $deposito_id
            ]);

            // Asegurar registro de stock en el depósito predeterminado
            $pdo->exec("SAVEPOINT sp_stock");
            try {
                $qStock = $pdo->prepare("
                    SELECT 1 FROM stock_materia_prima 
                    WHERE mp_id = :materia_prima_id AND deposito_id = :deposito_id
                    LIMIT 1
                ");
                $qStock->execute([':materia_prima_id' => $materia_prima_id, ':deposito_id' => $deposito_id]);
                if (!$qStock->fetchColumn()) {
                    $qInsertStock = $pdo->prepare("
                        INSERT INTO stock_materia_prima (mp_id, deposito_id, stock_cantidad, usuario_id)
                        VALUES (:materia_prima_id, :deposito_id, 0, :id_usuario)
                        ON CONFLICT DO NOTHING
                    ");
                    $qInsertStock->execute([
                        ':materia_prima_id' => $materia_prima_id,
                        ':deposito_id' => $deposito_id,
                        ':id_usuario' => $idUsuario
                    ]);
                }
                $pdo->exec("RELEASE SAVEPOINT sp_stock");
            } catch (PDOException $e) {
                $pdo->exec("ROLLBACK TO SAVEPOINT sp_stock");
                throw $e;
            }

            bitacora($pdo, $idUsuario, 'MODIFICACION', "Materia prima actualizada: {$descripcion} (ID: {$materia_prima_id})", $materia_prima_id);

            $pdo->commit();
            header("Location: view.php?alert=2");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errorMsg = $e->getMessage();
            error_log("Error update materia_prima: " . $errorMsg);
            
            // Mensaje de error más específico
            $userMessage = 'Error al actualizar la materia prima. Verifique los datos ingresados.';
            
            // Detectar errores comunes de PostgreSQL
            if (stripos($errorMsg, 'column') !== false && stripos($errorMsg, 'does not exist') !== false) {
                $userMessage = 'Error: Una columna no existe. Verifique la estructura de la tabla materia_prima.';
            } elseif (stripos($errorMsg, 'null value') !== false && stripos($errorMsg, 'violates not-null constraint') !== false) {
                $userMessage = 'Error: Faltan datos obligatorios. Complete todos los campos requeridos.';
            } elseif (stripos($errorMsg, 'foreign key') !== false) {
                if (stripos($errorMsg, 'unidad_medida_id') !== false) {
                    $userMessage = 'Error: La unidad de medida seleccionada no es válida.';
                } elseif (stripos($errorMsg, 'tipo_iva_id') !== false) {
                    $userMessage = 'Error: El tipo de IVA seleccionado no es válido.';
                } else {
                    $userMessage = 'Error de relación de datos. Verifique las selecciones.';
                }
            }
            
            // Detectar errores de transacción abortada
            if (stripos($errorMsg, 'transaction is aborted') !== false || stripos($errorMsg, '25P02') !== false) {
                $userMessage = 'Error: Ocurrió un problema durante el proceso. Por favor, verifique que todos los datos sean correctos e intente nuevamente.';
            }
            
            // Mostrar el error real para debugging (temporalmente)
            // TODO: Remover después de identificar el problema
            if (empty($userMessage) || $userMessage === 'Error al actualizar la materia prima. Verifique los datos ingresados.') {
                $userMessage = 'Error: ' . htmlspecialchars($errorMsg);
            }
            
            header("Location: view.php?alert=4&msg=" . urlencode($userMessage));
            exit();
        }
    }

    // Validar posibilidad de inactivación (AJAX)
    elseif ($action == 'validate_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        if (ob_get_length()) { ob_clean(); }

        $materia_prima_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($materia_prima_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }

        $validacion = validarInactivacionMateriaPrima($pdo, $materia_prima_id);
        echo json_encode([
            'success' => true,
            'puede_inactivar' => $validacion['puede'],
            'stock' => $validacion['stock'],
            'bloqueos' => $validacion['bloqueos'],
        ]);
        exit;
    }

    // DELETE (inactivar lógicamente)
    elseif ($action == 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        if (ob_get_length()) { ob_clean(); }
        
        $materia_prima_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $force = isset($_POST['force']) && (int)$_POST['force'] === 1;

        if ($materia_prima_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $qMp = $pdo->prepare("SELECT mp_descripcion, mp_estado FROM materia_prima WHERE mp_id = :id LIMIT 1");
            $qMp->execute([':id' => $materia_prima_id]);
            $mp = $qMp->fetch();
            
            if (!$mp) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Materia prima no encontrada']);
                exit;
            }

            $estadoActual = strtoupper(trim($mp['mp_estado'] ?? 'ACTIVO'));
            if ($estadoActual !== 'ACTIVO') {
                $pdo->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'La materia prima ya está inactiva.',
                    'inactivado' => false
                ]);
                exit;
            }

            $validacion = validarInactivacionMateriaPrima($pdo, $materia_prima_id);
            if (!$validacion['puede'] && !$force) {
                $pdo->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => 'No se puede inactivar la materia prima porque tiene vínculos o stock.',
                    'bloqueos' => $validacion['bloqueos'],
                    'can_force' => true,
                ]);
                exit;
            }

            $qUpdate = $pdo->prepare("UPDATE materia_prima SET mp_estado = 'INACTIVO' WHERE mp_id = :id");
            $qUpdate->execute([':id' => $materia_prima_id]);

            $descBitacora = "Materia prima #{$materia_prima_id} ({$mp['mp_descripcion']}) inactivada";
            if (!$validacion['puede']) {
                $descBitacora .= ' (forzada: ' . implode(' ', $validacion['bloqueos']) . ')';
            }
            bitacora($pdo, $idUsuario, 'INACTIVACION', $descBitacora, $materia_prima_id);
            
            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => $force && !$validacion['puede']
                    ? 'Materia prima inactivada (con vínculos/stock existentes).'
                    : 'Materia prima inactivada correctamente.',
                'inactivado' => true
            ]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error delete materia_prima: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    else {
        header("Location: view.php?alert=4");
        exit();
    }

} catch (PDOException $e) {
    error_log("Error conexión: " . $e->getMessage());
    header("Location: view.php?alert=4");
    exit();
} catch (Exception $e) {
    error_log("Error general: " . $e->getMessage());
    header("Location: view.php?alert=4&msg=" . urlencode($e->getMessage()));
    exit();
}

