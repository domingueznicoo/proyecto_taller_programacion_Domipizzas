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
        'orden_de_compra' => 'órdenes de compra',
        'nota_compra' => 'notas de crédito/débito de compra',
        'nota_remision_compra' => 'notas de remisión de compra',
        'cuentas_pagar' => 'cuentas a pagar / facturas de compra',
    ];

    foreach ($vinculos as $tabla => $descripcion) {
        $q = $pdo->prepare("SELECT 1 FROM {$tabla} WHERE id_proveedor = :id LIMIT 1");
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

    $qUsuario = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
    $qUsuario->execute([':u' => $_SESSION['username']]);
    $usuario = $qUsuario->fetch();

    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
    $idUsuario = (int)$usuario['id_usuario'];

    if (!isset($_GET['act'])) {
        header("Location: view.php?alert=4");
        exit();
    }

    $action = $_GET['act'];

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
        $razon_social = normalize_desc($_POST['razon_social'] ?? '');
        $ruc = normalize_desc($_POST['ruc_proveedor'] ?? '');
        $telefono = normalize_desc($_POST['telefono_proveedor'] ?? '');
        $direccion = normalize_desc($_POST['direccion_proveedor'] ?? '');
        $email = normalize_email($_POST['email_proveedor'] ?? '');

        if (empty($razon_social)) {
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

        $stmt = $pdo->prepare("SELECT 1 FROM proveedor WHERE UPPER(razon_social) = UPPER(:rs) LIMIT 1");
        $stmt->execute([':rs' => $razon_social]);
        if ($stmt->fetchColumn()) {
            header("Location: view.php?alert=5");
            exit();
        }

        $stmt = $pdo->prepare("SELECT 1 FROM proveedor WHERE UPPER(ruc_proveedor) = UPPER(:ruc) LIMIT 1");
        $stmt->execute([':ruc' => $ruc]);
        if ($stmt->fetchColumn()) {
            header("Location: view.php?alert=5");
            exit();
        }

        try {
            $pdo->beginTransaction();

            $q = $pdo->prepare("
                INSERT INTO proveedor
                    (estado_proveedor, razon_social, ruc_proveedor, telefono_proveedor, direccion_proveedor, email_proveedor, id_usuario)
                VALUES
                    ('ACTIVO', :rs, :ruc, :tel, :dir, :email, :id_usuario)
                RETURNING id_proveedor
            ");
            $q->execute([
                ':rs' => $razon_social,
                ':ruc' => $ruc,
                ':tel' => $telefono,
                ':dir' => $direccion !== '' ? $direccion : null,
                ':email' => $email !== '' ? $email : null,
                ':id_usuario' => $idUsuario,
            ]);
            $proveedorId = (int)$q->fetchColumn();

            bitacora($pdo, $idUsuario, 'ALTA', "Proveedor creado: {$razon_social} (ID: {$proveedorId})", $proveedorId);

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
        $id_proveedor = isset($_POST['id_proveedor']) ? (int)$_POST['id_proveedor'] : 0;
        $razon_social = normalize_desc($_POST['razon_social'] ?? '');
        $ruc = normalize_desc($_POST['ruc_proveedor'] ?? '');
        $telefono = normalize_desc($_POST['telefono_proveedor'] ?? '');
        $direccion = normalize_desc($_POST['direccion_proveedor'] ?? '');
        $email = normalize_email($_POST['email_proveedor'] ?? '');
        $estado = normalizarEstadoProveedor($_POST['estado_proveedor'] ?? 'ACTIVO');

        if ($id_proveedor <= 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("ID de proveedor inválido."));
            exit();
        }
        if (empty($razon_social) || empty($ruc) || empty($telefono)) {
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
            ['sql' => "SELECT 1 FROM proveedor WHERE UPPER(razon_social)=UPPER(:v) AND id_proveedor <> :id LIMIT 1", 'param' => $razon_social],
            ['sql' => "SELECT 1 FROM proveedor WHERE UPPER(ruc_proveedor)=UPPER(:v) AND id_proveedor <> :id LIMIT 1", 'param' => $ruc],
        ];
        foreach ($checks as $check) {
            $stmt = $pdo->prepare($check['sql']);
            $stmt->execute([':v' => $check['param'], ':id' => $id_proveedor]);
            if ($stmt->fetchColumn()) {
                header("Location: view.php?alert=5");
                exit();
            }
        }

        try {
            $pdo->beginTransaction();

            $q = $pdo->prepare("
                UPDATE proveedor
                SET razon_social = :rs,
                    ruc_proveedor = :ruc,
                    telefono_proveedor = :tel,
                    direccion_proveedor = :dir,
                    email_proveedor = :email,
                    estado_proveedor = :estado
                WHERE id_proveedor = :id
            ");
            $q->execute([
                ':rs' => $razon_social,
                ':ruc' => $ruc,
                ':tel' => $telefono,
                ':dir' => $direccion !== '' ? $direccion : null,
                ':email' => $email !== '' ? $email : null,
                ':estado' => $estado,
                ':id' => $id_proveedor,
            ]);

            bitacora($pdo, $idUsuario, 'MODIFICACION', "Proveedor actualizado: {$razon_social} (ID: {$id_proveedor})", $id_proveedor);

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

        $id_proveedor = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $force = isset($_POST['force']) && (int)$_POST['force'] === 1;

        if ($id_proveedor <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $qProv = $pdo->prepare("SELECT razon_social, estado_proveedor FROM proveedor WHERE id_proveedor = :id LIMIT 1");
            $qProv->execute([':id' => $id_proveedor]);
            $prov = $qProv->fetch();

            if (!$prov) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
                exit;
            }

            $estadoActual = normalizarEstadoProveedor($prov['estado_proveedor'] ?? 'ACTIVO');
            if ($estadoActual !== 'ACTIVO') {
                $pdo->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'El proveedor ya está inactivo.',
                    'inactivado' => false,
                ]);
                exit;
            }

            $validacion = validarInactivacionProveedor($pdo, $id_proveedor);
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

            $qUpdate = $pdo->prepare("UPDATE proveedor SET estado_proveedor = 'INACTIVO' WHERE id_proveedor = :id");
            $qUpdate->execute([':id' => $id_proveedor]);

            $descBitacora = "Proveedor #{$id_proveedor} ({$prov['razon_social']}) inactivado";
            if (!$validacion['puede']) {
                $descBitacora .= ' (con vínculos: ' . implode(' ', $validacion['bloqueos']) . ')';
            }
            bitacora($pdo, $idUsuario, 'INACTIVACION', $descBitacora, $id_proveedor);

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
