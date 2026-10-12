<?php
// Procesa altas, ediciones y anulaciones de usuario (?act=).
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();

if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = loginUrl();
          </script>";
    exit();
}

$file = PROJECT_ROOT . '/config/database.php';
if (!$file || !file_exists($file)) {
    die("Error: No se pudo encontrar el archivo en la ruta $file");
}

require_once $file;
require_once PROJECT_ROOT . '/config/modulo_cargo_map.php';

function redirectWithAlert(int $code, string $message = ''): void {
    $location = "view.php?alert={$code}";
    if ($message !== '') {
        $location .= '&msg=' . urlencode($message);
    }
    header("Location: {$location}");
    exit();
}

$action = $_GET['act'] ?? '';

function toUpper(?string $value): string {
    return strtoupper(trim($value ?? ''));
}

function isValidPassword(string $password): bool {
    $len = strlen($password);
    return $len >= 8 && $len <= 15 && preg_match('/\d/', $password);
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    error_log("Usuarios::conexion -> " . $e->getMessage());
    redirectWithAlert(4);
}

try {
    if ($action === 'insert') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $moduloId = (int)($_POST['modulo_id'] ?? 0);
        $cargoId  = (int)($_POST['cargo_id'] ?? 0);
        $personalIdSeleccionado = !empty($_POST['personal_id']) ? (int)$_POST['personal_id'] : 0;

        // Validaciones básicas
        if ($username === '' || $password === '' || $moduloId <= 0 || $cargoId <= 0) {
            redirectWithAlert(4, 'Complete todos los campos obligatorios.');
        }
        
        if (strlen($username) > 30) {
            redirectWithAlert(4, 'El usuario excede el máximo permitido (30).');
        }

        // Validar que el username no exista
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE LOWER(username) = LOWER(:username)");
        $stmt->execute([':username' => $username]);
        if ($stmt->fetchColumn() > 0) {
            redirectWithAlert(4, 'El nombre de usuario ya existe.');
        }

        // Validar que el personal seleccionado no esté ya asociado a otro usuario
        if ($personalIdSeleccionado > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE personal_id = :personal_id");
            $stmt->execute([':personal_id' => $personalIdSeleccionado]);
            if ($stmt->fetchColumn() > 0) {
                redirectWithAlert(4, 'El personal seleccionado ya está asociado a otro usuario.');
            }
        }

        if (!isValidPassword($password)) {
            redirectWithAlert(4, 'La contraseña debe tener entre 8 y 15 caracteres e incluir al menos un número.');
        }

        // Validar que el cargo esté permitido para el módulo seleccionado
        if (!validarCargoParaModulo($pdo, $moduloId, $cargoId)) {
            redirectWithAlert(4, 'El cargo seleccionado no está permitido para el módulo elegido.');
        }

        $hash = hash('sha256', $password);
        
        // Validar que el personal seleccionado existe y está activo (si se seleccionó uno)
        $personalId = null;
        if ($personalIdSeleccionado > 0) {
            $stmtPersonal = $pdo->prepare("
                SELECT personal_id 
                FROM personal 
                WHERE personal_id = :id 
                AND personal_estado = 'ACTIVO'
                LIMIT 1
            ");
            $stmtPersonal->execute([':id' => $personalIdSeleccionado]);
            $personalData = $stmtPersonal->fetch();
            
            if ($personalData) {
                $personalId = $personalIdSeleccionado;
            } else {
                redirectWithAlert(4, 'El personal seleccionado no existe o no está activo.');
            }
        }

        $insert = $pdo->prepare("
            INSERT INTO usuario (username, usuario_contrasena, usuario_estado, modulo_id, cargo_id, personal_id)
            VALUES (:username, :password, 'ACTIVO', :modulo, :cargo, :personal_id)
        ");
        $insert->execute([
            ':username' => $username,
            ':password' => $hash,
            ':modulo' => $moduloId,
            ':cargo' => $cargoId,
            ':personal_id' => $personalId,
        ]);

        redirectWithAlert(1);
    } elseif ($action === 'update') {
        $id        = (int)($_POST['usuario_id'] ?? 0);
        $username  = trim($_POST['username'] ?? '');
        $password  = trim($_POST['password'] ?? '');
        $moduloId  = (int)($_POST['modulo_id'] ?? 0);
        $cargoId   = (int)($_POST['cargo_id'] ?? 0);
        $personalIdSeleccionado = !empty($_POST['personal_id']) ? (int)$_POST['personal_id'] : 0;

        // Validaciones básicas
        if ($id <= 0 || $username === '' || $moduloId <= 0 || $cargoId <= 0) {
            redirectWithAlert(4, 'Complete todos los campos obligatorios.');
        }
        
        if (strlen($username) > 30) {
            redirectWithAlert(4, 'El usuario excede el máximo permitido (30).');
        }

        // Validar que el username no exista en otro usuario
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE LOWER(username) = LOWER(:username) AND usuario_id <> :id");
        $stmt->execute([
            ':username' => $username,
            ':id'       => $id,
        ]);
        if ($stmt->fetchColumn() > 0) {
            redirectWithAlert(4, 'El nombre de usuario ya existe.');
        }

        // Validar que el personal seleccionado no esté ya asociado a otro usuario
        if ($personalIdSeleccionado > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE personal_id = :personal_id AND usuario_id <> :id");
            $stmt->execute([
                ':personal_id' => $personalIdSeleccionado,
                ':id' => $id
            ]);
            if ($stmt->fetchColumn() > 0) {
                redirectWithAlert(4, 'El personal seleccionado ya está asociado a otro usuario.');
            }
        }

        // Manejar personal en actualización
        // Primero obtener el personal_id actual del usuario para mantenerlo si no se selecciona otro
        $stmtUser = $pdo->prepare("SELECT personal_id FROM usuario WHERE usuario_id = :id LIMIT 1");
        $stmtUser->execute([':id' => $id]);
        $userData = $stmtUser->fetch();
        $personalIdActual = !empty($userData['personal_id']) ? (int)$userData['personal_id'] : null;
        
        $personalIdFinal = $personalIdActual; // Por defecto, mantener el actual
        
        if ($personalIdSeleccionado > 0) {
            // Se seleccionó un personal existente
            // Validar que existe y está activo
            $stmtPersonal = $pdo->prepare("
                SELECT personal_id 
                FROM personal 
                WHERE personal_id = :id 
                AND personal_estado = 'ACTIVO'
                LIMIT 1
            ");
            $stmtPersonal->execute([':id' => $personalIdSeleccionado]);
            $personalData = $stmtPersonal->fetch();
            
            if ($personalData) {
                $personalIdFinal = $personalIdSeleccionado;
            } else {
                redirectWithAlert(4, 'El personal seleccionado no existe o no está activo.');
            }
        } else {
            // Si se envió el campo pero está vacío (0), se quita la asociación
            // Si el campo tiene valor "-- Sin personal asociado --" (vacío), también se quita
            $personalIdFinal = null;
        }

        $fields = "
            username = :username,
            modulo_id = :modulo,
            cargo_id = :cargo,
            personal_id = :personal_id
        ";
        $params = [
            ':username' => $username,
            ':modulo' => $moduloId,
            ':cargo' => $cargoId,
            ':personal_id' => $personalIdFinal,
            ':id' => $id,
        ];

        if ($password !== '') {
            if (!isValidPassword($password)) {
                redirectWithAlert(4, 'La contraseña debe tener entre 8 y 15 caracteres e incluir al menos un número.');
            }
            $hash = hash('sha256', $password);
            $fields .= ", usuario_contrasena = :password";
            $params[':password'] = $hash;
        }

        // Validar que el cargo esté permitido para el módulo seleccionado
        if (!validarCargoParaModulo($pdo, $moduloId, $cargoId)) {
            redirectWithAlert(4, 'El cargo seleccionado no está permitido para el módulo elegido.');
        }

        $update = $pdo->prepare("UPDATE usuario SET {$fields} WHERE usuario_id = :id");
        $update->execute($params);

        // Al editar la cuenta actual, aplicar sus nuevos permisos en esta sesión.
        if ($update->rowCount() > 0 && $id === (int)($_SESSION['usuario_id'] ?? $_SESSION['id_usuario'] ?? 0)) {
            require_once PROJECT_ROOT . '/config/permissions.php';
            $cargoNombre = obtenerNombreCargo($pdo, $cargoId);
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $id;
            $_SESSION['id_usuario'] = $id;
            $_SESSION['usua_id'] = $id;
            $_SESSION['username'] = $username;
            $_SESSION['modulo_id'] = $moduloId;
            $_SESSION['id_cargo'] = $cargoId;
            $_SESSION['cargo_nombre'] = $cargoNombre;
            $_SESSION['message'] = 'Usuario actualizado. Se aplicaron tus nuevos permisos.';
            $_SESSION['message_type'] = 'success';
            header('Location: ' . indexUrl());
            exit();
        }

        redirectWithAlert(2);
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['usuario_id'] ?? $_POST['id_usuario'] ?? 0);
        $estado = $_POST['estado'] ?? '';
        if ($id <= 0 || !in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            redirectWithAlert(4, 'Solicitud inválida.');
        }
        $stmt = $pdo->prepare("UPDATE usuario SET usuario_estado = :estado WHERE usuario_id = :id");
        $stmt->execute([
            ':estado' => $estado,
            ':id'     => $id,
        ]);
        // Si la cuenta desactivada es la actual, finalizar su acceso inmediatamente.
        if ($stmt->rowCount() > 0 && $estado === 'INACTIVO'
            && $id === (int)($_SESSION['usuario_id'] ?? $_SESSION['id_usuario'] ?? 0)) {
            require_once PROJECT_ROOT . '/config/remember_session.php';
            try {
                remember_revoke($pdo);
            } catch (Throwable $e) {
                error_log('Usuarios::desactivar_sesion -> No se pudo revocar el token.');
                remember_cookie(null);
            }
            $_SESSION = [];
            $cookieParams = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $cookieParams['path'],
                'domain' => $cookieParams['domain'],
                'secure' => $cookieParams['secure'],
                'httponly' => $cookieParams['httponly'],
                'samesite' => 'Lax',
            ]);
            session_destroy();
            header('Location: ' . loginUrl());
            exit();
        }
        redirectWithAlert(3);
    } else {
        redirectWithAlert(4);
    }
} catch (PDOException $e) {
    $errorMsg = $e->getMessage();
    $errorCode = $e->getCode();
    $file = $e->getFile();
    $line = $e->getLine();
    
    error_log("Usuarios::accion -> Error: " . $errorMsg . " | Código: " . $errorCode . " | Archivo: " . $file . " | Línea: " . $line);
    
    // Mensajes de error más específicos según el código de error
    $userMessage = 'Error al procesar la solicitud. Verifique los datos ingresados.';
    
    // Detectar errores comunes de PostgreSQL
    if (stripos($errorMsg, 'column') !== false && stripos($errorMsg, 'does not exist') !== false) {
        $userMessage = 'Error: Una columna no existe. Verifique la estructura de la tabla usuario.';
    } elseif (stripos($errorMsg, 'null value') !== false && stripos($errorMsg, 'violates not-null constraint') !== false) {
        $userMessage = 'Error: Faltan datos obligatorios. Complete todos los campos requeridos.';
    } elseif (stripos($errorMsg, 'duplicate key') !== false || stripos($errorMsg, 'unique') !== false) {
        $userMessage = 'Error: Ya existe un usuario con ese nombre de usuario o el personal ya está asociado.';
    } elseif (stripos($errorMsg, 'foreign key') !== false) {
        if (stripos($errorMsg, 'cargo_id') !== false) {
            $userMessage = 'Error: El cargo seleccionado no es válido.';
        } elseif (stripos($errorMsg, 'modulo_id') !== false) {
            $userMessage = 'Error: El módulo seleccionado no es válido.';

        } elseif (stripos($errorMsg, 'personal_id') !== false) {
            $userMessage = 'Error: El personal seleccionado no es válido.';
        } else {
            $userMessage = 'Error: Los datos seleccionados no son válidos. Verifique módulo, cargo o personal.';
        }
    } elseif (stripos($errorMsg, 'syntax error') !== false) {
        $userMessage = 'Error: Error de sintaxis en la consulta SQL. Contacte al administrador.';
    }
    
    // TEMPORAL: Mostrar error completo para debug - DESACTIVAR EN PRODUCCIÓN
    // Mostrar el error completo para diagnóstico
    $userMessage = 'ERROR: ' . substr($errorMsg, 0, 300);
    if (strlen($errorMsg) > 300) {
        $userMessage .= '...';
    }
    
    redirectWithAlert(4, $userMessage);
} catch (Exception $e) {
    // Capturar cualquier otro tipo de error
    $errorMsg = $e->getMessage();
    $errorCode = $e->getCode();
    $file = $e->getFile();
    $line = $e->getLine();
    
    error_log("Usuarios::accion -> Error general: " . $errorMsg . " | Código: " . $errorCode . " | Archivo: " . $file . " | Línea: " . $line);
    
    $userMessage = 'Error inesperado: ' . substr($errorMsg, 0, 100);
    redirectWithAlert(4, $userMessage);
}
