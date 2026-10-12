<?php
// Valida usuario y crea la sesión.
$file = realpath("../config/database.php");

if (!$file || !file_exists($file)) {
    die("Error: No se pudo encontrar el archivo en la ruta $file");
}
require_once $file;
require_once __DIR__ . '/../config/bitacora_helper.php';
require_once __DIR__ . '/../config/remember_session.php';

// Obtener datos del formulario
$username = htmlspecialchars(trim($_POST['username'] ?? ''));
$password = hash('sha256', trim($_POST['password'] ?? ''));

try {
    // Crear conexión con PostgreSQL usando PDO (usa variables de ../config/database.php)
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Buscar usuario por nombre de usuario con información del cargo
    $stmt = $pdo->prepare("
        SELECT 
            u.usuario_id, 
            u.username, 
            u.usuario_contrasena, 
            u.usuario_estado, 
            u.modulo_id, 
            u.personal_id, 
            u.cargo_id,
            c.descrip_cargo
                FROM usuario u
                LEFT JOIN cargo c
                ON c.cargo_id = u.cargo_id
                AND c.estado_cargo = 'ACTIVO'
        WHERE u.username = :username
        LIMIT 1
    ");
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    session_start();
    // Inicializar estructura de intentos en sesión
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    if (!isset($_SESSION['login_attempts'][$username])) {
        $_SESSION['login_attempts'][$username] = 0;
    }

    // Verificar existencia del usuario
    if ($userData) {
        // Si el usuario está inactivo
        if ($userData['usuario_estado'] === 'INACTIVO') {
            bitacora_registrar($pdo, (int)$userData['usuario_id'], 'LOGIN', 'ACCESO_FALLIDO',
                'Usuario ingresado: ' . $username . '. Motivo: usuario inactivo.');
            $_SESSION['message'] = "El usuario está inactivo. Contacte al administrador.";
            $_SESSION['message_type'] = "error";
            header("Location: ../login.html");
            exit();
        }

        // Comparar contraseña (SHA-256)
        if ($userData['usuario_contrasena'] === $password) {
            // Reiniciar intentos fallidos (en sesión) al iniciar correctamente
            $_SESSION['login_attempts'][$username] = 0;

            // Obtener y normalizar el nombre del cargo
            require_once realpath('../config/permissions.php');
            $idCargo = (int)($userData['cargo_id'] ?? 0);
            $cargoNombre = '';
            
            if ($idCargo > 0) {
                $cargoNombre = obtenerNombreCargo($pdo, $idCargo);
            }

            session_regenerate_id(true);
            // Setear variables de sesión
            $_SESSION['usuario_id'] = (int)$userData['usuario_id'];
            $_SESSION['usua_id']      = (int)$userData['usuario_id'];
            $_SESSION['id_usuario']  = (int)$userData['usuario_id'];
            $_SESSION['username']   = $userData['username']; 
            $_SESSION['id_cargo']   = $idCargo;
            $_SESSION['cargo_nombre'] = $cargoNombre; // Nombre normalizado del cargo (ADMIN, JEFE_COMPRAS, etc.)
            $_SESSION['modulo_id']    = (int)$userData['modulo_id'];
            //$_SESSION['personal_id']  = isset($userData['personal_id']) ? (int)$userData['personal_id'] : null;

            bitacora_registrar($pdo, (int)$userData['usuario_id'], 'LOGIN', 'ACCESO_EXITOSO',
                'Usuario ingresado: ' . $username . '. Inicio de sesión exitoso.');
            try {
                if (($_POST['remember_session'] ?? '') === '1') {
                    remember_issue($pdo, (int)$userData['usuario_id']);
                } else {
                    remember_revoke($pdo);
                }
            } catch (Throwable $e) {
                remember_cookie(null);
                error_log('remember_login: ' . $e->getMessage());
            }

            $_SESSION['message'] = "¡Inicio de sesión exitoso!";
            $_SESSION['message_type'] = "success";

            header("Location: ../index.php");
            exit();
        } else {
            // Incrementar intentos fallidos (en sesión)
            $_SESSION['login_attempts'][$username]++;

            $intentos = (int)$_SESSION['login_attempts'][$username];

            if ($intentos >= 3) {
                // Desactivar usuario si falla 3 veces (sin columna intentos_fallidos en BD)
                $update = $pdo->prepare("UPDATE usuario SET usuario_estado = 'INACTIVO' WHERE username = :username");
                $update->bindParam(':username', $username, PDO::PARAM_STR);
                $update->execute();

                bitacora_registrar($pdo, (int)$userData['usuario_id'], 'LOGIN', 'CUENTA_BLOQUEADA',
                    'Usuario ingresado: ' . $username . '. Motivo: contraseña incorrecta; cuenta bloqueada al tercer intento.');
                $_SESSION['message'] = "Cuenta bloqueada por intentos fallidos. Contacte al administrador.";
                $_SESSION['message_type'] = "error";

                // Opcional: resetear el contador en sesión después del bloqueo
                $_SESSION['login_attempts'][$username] = 0;
            } else {
                bitacora_registrar($pdo, (int)$userData['usuario_id'], 'LOGIN', 'ACCESO_FALLIDO',
                    'Usuario ingresado: ' . $username . '. Motivo: contraseña incorrecta. Intento ' . $intentos . ' de 3.');
                $_SESSION['message'] = "Contraseña incorrecta. Intento $intentos de 3.";
                $_SESSION['message_type'] = "error";
            }

            header("Location: ../login.html");
            exit();
        }
    } else {
        // Usuario no existe
        bitacora_registrar($pdo, null, 'LOGIN', 'ACCESO_FALLIDO',
            'Usuario ingresado: ' . $username . '. Motivo: usuario inexistente.');
        $_SESSION['message'] = "Usuario no encontrado.";
        $_SESSION['message_type'] = "error";
        header("Location: ../login.html");
        exit();
    }

} catch (PDOException $e) {
    die("Error en la conexión: " . $e->getMessage());
}
