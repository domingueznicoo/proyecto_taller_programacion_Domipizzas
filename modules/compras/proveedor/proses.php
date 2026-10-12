<?php
// Procesa altas, ediciones y anulaciones de proveedores (?act=).
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/permissions.php';
check_permission(['COMPRAS', 'REFERENCIALES']);

function bitacora(PDO $pdo, int $idUsuario, string $accion, string $desc, ?int $id = null): void {
    $pdo->exec("SAVEPOINT sp_bit");
    try {
        $sql = "
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:u, 'Proveedor', :id, :acc, :d)
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':u' => $idUsuario, ':id' => $id, ':acc' => strtoupper($accion), ':d' => $desc]);
    } catch (Throwable $e) {
        $pdo->exec("ROLLBACK TO SAVEPOINT sp_bit");
        error_log("Bitácora falló: " . $e->getMessage());
    }
}

function normalize_desc(string $s): string {
    $s = trim($s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return mb_strtoupper($s, 'UTF-8');
}

function normalize_email(string $s): string {
    return trim($s);
}

function validarRucFormato(string $ruc): bool {
    $rucLimpio = preg_replace('/\s/', '', $ruc);
    return (bool)preg_match('/^(\d{6,7}-\d{1}|\d{8,9})$/', $rucLimpio);
}

function normalizarEstadoProveedor(string $estado): string {
    $estado = strtoupper(trim($estado));
    if ($estado === 'ANULADO') {
        return 'INACTIVO';
    }
    return in_array($estado, ['ACTIVO', 'INACTIVO'], true) ? $estado : 'ACTIVO';
}

/**
 * Verifica vínculos del proveedor en compras.
 * Retorna ['puede' => bool, 'bloqueos' => string[]]
 */
function validarInactivacionProveedor(PDO $pdo, int $idProveedor): array {
    $bloqueos = [];

    $vinculos = [
        'presupuesto_compra' => 'presupuestos de compra',
        'orden_compra' => 'órdenes de compra',
        'nota_compra' => 'notas de crédito/débito de compra',
        'nota_remision_compra' => 'notas de remisión de compra',
        'cuentas_pagar' => 'cuentas a pagar / facturas de compra',
    ];

    foreach ($vinculos as $tabla => $descripcion) {
        $q = $pdo->prepare("SELECT 1 FROM {$tabla} WHERE prov_id = :id LIMIT 1");
        $q->execute([':id' => $idProveedor]);
        if ($q->fetchColumn()) {
            $bloqueos[] = "Tiene registros en {$descripcion}.";
        }
    }

    return [
        'puede' => empty($bloqueos),
        'bloqueos' => $bloqueos,
    ];
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

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

    if (!isset($_GET['act'])) {
        header("Location: view.php?alert=4");
        exit();
    }

    $action = $_GET['act'];

    if ($action === 'activate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        if (ob_get_length()) { ob_clean(); }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }
        try {
            $pdo->beginTransaction();
            $consulta = $pdo->prepare("SELECT prov_razon_social, prov_estado FROM proveedor WHERE prov_id = :id FOR UPDATE");
            $consulta->execute([':id' => $id]);
            $proveedor = $consulta->fetch();
            if (!$proveedor) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
                exit;
            }
            if (strtoupper(trim($proveedor['prov_estado'])) !== 'ACTIVO') {
                $consulta = $pdo->prepare("UPDATE proveedor SET prov_estado = 'ACTIVO' WHERE prov_id = :id");
                $consulta->execute([':id' => $id]);
                bitacora($pdo, $idUsuario, 'MODIFICACION', "Proveedor reactivado (INACTIVO → ACTIVO): {$proveedor['prov_razon_social']} (ID: {$id})", $id);
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Proveedor activado correctamente.']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Error activar proveedor: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'No se pudo activar el proveedor.']);
        }
        exit;
    }

    if ($action === 'validate_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        if (ob_get_length()) { ob_clean(); }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }

        $validacion = validarInactivacionProveedor($pdo, $id);
        echo json_encode([
            'success' => true,
            'puede_inactivar' => $validacion['puede'],
            'bloqueos' => $validacion['bloqueos'],
        ]);
        exit;
    }

    if ($action === 'insert' && isset($_POST['Guardar'])) {
        $prov_razon_social = normalize_desc($_POST['prov_razon_social'] ?? '');
        $ruc = normalize_desc($_POST['prov_ruc'] ?? '');
        $telefono = normalize_desc($_POST['prov_telefono'] ?? '');
        $direccion = normalize_desc($_POST['prov_direccion'] ?? '');
        $email = normalize_email($_POST['prov_email'] ?? '');

        if (empty($prov_razon_social)) {
            header("Location: view.php?alert=4&msg=" . urlencode("La razón social es obligatoria."));
            exit();
        }
        if (empty($ruc)) {
            header("Location: view.php?alert=4&msg=" . urlencode("El RUC es obligatorio."));
            exit();
        }
        if (!validarRucFormato($ruc)) {
            header("Location: view.php?alert=4&msg=" . urlencode("El RUC tiene formato inválido. Use NNNNNNN-N o NNNNNNNN."));
            exit();
        }
        if (empty($telefono)) {
            header("Location: view.php?alert=4&msg=" . urlencode("El teléfono es obligatorio."));
            exit();
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header("Location: view.php?alert=4&msg=" . urlencode("El correo electrónico no es válido."));
            exit();
        }

        $stmt = $pdo->prepare("SELECT 1 FROM proveedor WHERE UPPER(prov_razon_social) = UPPER(:rs) LIMIT 1");
        $stmt->execute([':rs' => $prov_razon_social]);
        if ($stmt->fetchColumn()) {
            header("Location: view.php?alert=5");
            exit();
        }

        $stmt = $pdo->prepare("SELECT 1 FROM proveedor WHERE UPPER(prov_ruc) = UPPER(:ruc) LIMIT 1");
        $stmt->execute([':ruc' => $ruc]);
        if ($stmt->fetchColumn()) {
            header("Location: view.php?alert=5");
            exit();
        }

        try {
            $pdo->beginTransaction();

            $q = $pdo->prepare("
                INSERT INTO proveedor
                    (prov_estado, prov_razon_social, prov_ruc, prov_telefono, prov_direccion, prov_email, usuario_id)
                VALUES
                    ('ACTIVO', :rs, :ruc, :tel, :dir, :email, :id_usuario)
                RETURNING prov_id
            ");
            $q->execute([
                ':rs' => $prov_razon_social,
                ':ruc' => $ruc,
                ':tel' => $telefono,
                ':dir' => $direccion,
                ':email' => $email,
                ':id_usuario' => $idUsuario,
            ]);
            $proveedorId = (int)$q->fetchColumn();

            bitacora($pdo, $idUsuario, 'ALTA', "Proveedor creado: {$prov_razon_social} (ID: {$proveedorId})", $proveedorId);

            $pdo->commit();
            header("Location: view.php?alert=1");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error insert proveedor: " . $e->getMessage());
            header("Location: view.php?alert=4&msg=" . urlencode("Error al registrar el proveedor."));
            exit();
        }
    }

    if ($action === 'update' && isset($_POST['Guardar'])) {
        $prov_id = isset($_POST['prov_id']) ? (int)$_POST['prov_id'] : 0;
        $prov_razon_social = normalize_desc($_POST['prov_razon_social'] ?? '');
        $ruc = normalize_desc($_POST['prov_ruc'] ?? '');
        $telefono = normalize_desc($_POST['prov_telefono'] ?? '');
        $direccion = normalize_desc($_POST['prov_direccion'] ?? '');
        $email = normalize_email($_POST['prov_email'] ?? '');
        $estado = normalizarEstadoProveedor($_POST['prov_estado'] ?? 'ACTIVO');

        if ($prov_id <= 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("ID de proveedor inválido."));
            exit();
        }
        if (empty($prov_razon_social) || empty($ruc) || empty($telefono)) {
            header("Location: view.php?alert=4&msg=" . urlencode("Razón social, RUC y teléfono son obligatorios."));
            exit();
        }
        if (!validarRucFormato($ruc)) {
            header("Location: view.php?alert=4&msg=" . urlencode("El RUC tiene formato inválido."));
            exit();
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header("Location: view.php?alert=4&msg=" . urlencode("El correo electrónico no es válido."));
            exit();
        }

        $checks = [
            ['sql' => "SELECT 1 FROM proveedor WHERE UPPER(prov_razon_social)=UPPER(:v) AND prov_id <> :id LIMIT 1", 'param' => $prov_razon_social],
            ['sql' => "SELECT 1 FROM proveedor WHERE UPPER(prov_ruc)=UPPER(:v) AND prov_id <> :id LIMIT 1", 'param' => $ruc],
        ];
        foreach ($checks as $check) {
            $stmt = $pdo->prepare($check['sql']);
            $stmt->execute([':v' => $check['param'], ':id' => $prov_id]);
            if ($stmt->fetchColumn()) {
                header("Location: view.php?alert=5");
                exit();
            }
        }

        try {
            $pdo->beginTransaction();

            $q = $pdo->prepare("
                UPDATE proveedor
                SET prov_razon_social = :rs,
                    prov_ruc = :ruc,
                    prov_telefono = :tel,
                    prov_direccion = :dir,
                    prov_email = :email,
                    prov_estado = :estado
                WHERE prov_id = :id
            ");
            $q->execute([
                ':rs' => $prov_razon_social,
                ':ruc' => $ruc,
                ':tel' => $telefono,
                ':dir' => $direccion,
                ':email' => $email,
                ':estado' => $estado,
                ':id' => $prov_id,
            ]);

            bitacora($pdo, $idUsuario, 'MODIFICACION', "Proveedor actualizado: {$prov_razon_social} (ID: {$prov_id})", $prov_id);

            $pdo->commit();
            header("Location: view.php?alert=2");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error update proveedor: " . $e->getMessage());
            header("Location: view.php?alert=4&msg=" . urlencode("Error al actualizar el proveedor."));
            exit();
        }
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        if (ob_get_length()) { ob_clean(); }

        $prov_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $force = isset($_POST['force']) && (int)$_POST['force'] === 1;

        if ($prov_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $qProv = $pdo->prepare("SELECT prov_razon_social, prov_estado FROM proveedor WHERE prov_id = :id LIMIT 1");
            $qProv->execute([':id' => $prov_id]);
            $prov = $qProv->fetch();

            if (!$prov) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
                exit;
            }

            $estadoActual = normalizarEstadoProveedor($prov['prov_estado'] ?? 'ACTIVO');
            if ($estadoActual !== 'ACTIVO') {
                $pdo->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'El proveedor ya está inactivo.',
                    'inactivado' => false,
                ]);
                exit;
            }

            $validacion = validarInactivacionProveedor($pdo, $prov_id);
            if (!$validacion['puede'] && !$force) {
                $pdo->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => 'El proveedor tiene registros asociados en compras.',
                    'bloqueos' => $validacion['bloqueos'],
                    'can_force' => true,
                ]);
                exit;
            }

            $qUpdate = $pdo->prepare("UPDATE proveedor SET prov_estado = 'INACTIVO' WHERE prov_id = :id");
            $qUpdate->execute([':id' => $prov_id]);

            $descBitacora = "Proveedor #{$prov_id} ({$prov['prov_razon_social']}) inactivado";
            if (!$validacion['puede']) {
                $descBitacora .= ' (con vínculos: ' . implode(' ', $validacion['bloqueos']) . ')';
            }
            bitacora($pdo, $idUsuario, 'INACTIVACION', $descBitacora, $prov_id);

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => $force && !$validacion['puede']
                    ? 'Proveedor inactivado (con registros asociados en compras).'
                    : 'Proveedor inactivado correctamente.',
                'inactivado' => true,
            ]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error inactivar proveedor: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    header("Location: view.php?alert=4");
    exit();

} catch (PDOException $e) {
    error_log("Error conexión proveedor: " . $e->getMessage());
    header("Location: view.php?alert=4");
    exit();
} catch (Exception $e) {
    error_log("Error general proveedor: " . $e->getMessage());
    header("Location: view.php?alert=4&msg=" . urlencode($e->getMessage()));
    exit();
}
