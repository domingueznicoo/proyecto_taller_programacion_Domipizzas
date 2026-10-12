<?php
// Cerrar sesión revoca la cookie y el token del dispositivo actual.
session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !hash_equals($_SESSION['logout_csrf'] ?? '', $_POST['csrf'] ?? '')
    || empty($_SESSION['logout_csrf'])) {
    http_response_code(403);
    exit('Solicitud de cierre de sesión inválida.');
}
require_once __DIR__ . '/../config/remember_session.php';
try {
    require __DIR__ . '/../config/database.php';
    remember_revoke($pdo);
} catch (Throwable $e) {
    error_log('logout: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo revocar la sesión recordada. Volvé a intentar.');
}
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 3600, 'path' => $params['path'],
    'domain' => $params['domain'], 'secure' => $params['secure'],
    'httponly' => $params['httponly'], 'samesite' => 'Lax',
]);
session_destroy();
header('Location: ../login.html');
exit;
