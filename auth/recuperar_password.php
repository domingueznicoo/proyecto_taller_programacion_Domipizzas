<?php
// Solicita al administrador la recuperación; no cambia contraseñas.
declare(strict_types=1);
use PHPMailer\PHPMailer\PHPMailer;

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function recovery_response(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

$_SESSION['recovery_csrf'] = $_SESSION['recovery_csrf'] ?? bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['csrf' => $_SESSION['recovery_csrf']]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    recovery_response(405, 'Método no permitido.');
}
$csrf = $_POST['csrf'] ?? '';
if (!is_string($csrf) || !hash_equals($_SESSION['recovery_csrf'], $csrf)) {
    recovery_response(403, 'Recargá la página y volvé a intentar.');
}
$username = $_POST['username'] ?? '';
if (!is_string($username)) {
    recovery_response(422, 'Ingresá un nombre de usuario válido.');
}
$username = trim($username);
if ($username === '' || preg_match('/[\x00-\x1F\x7F]/', $username)
    || !preg_match('/\A.{1,30}\z/us', $username)) {
    recovery_response(422, 'Ingresá un nombre de usuario de hasta 30 caracteres.');
}
if (time() - ($_SESSION['recovery_last_attempt'] ?? 0) < 60) {
    recovery_response(429, 'Esperá un minuto antes de enviar otra solicitud.');
}
$config = require __DIR__ . '/../config/mail.php';
if ($config['password'] === '') {
    recovery_response(503, 'El envío de correos todavía no está configurado. Contactá al WhatsApp 0972 957385.');
}
$_SESSION['recovery_last_attempt'] = time();
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->Port = $config['port'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['username'];
    $mail->Password = $config['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Timeout = 15;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($config['username'], $config['from_name']);
    $mail->addAddress($config['admin_email']);
    $mail->Subject = $config['recovery_subject'];
    $mail->isHTML(false);
    $mail->Body = str_replace('{username}', $username, $config['recovery_body']);
    $mail->send();
    recovery_response(200, 'Solicitud enviada al administrador. También podés contactar al WhatsApp 0972 957385.');
} catch (Throwable $e) {
    // No exponer credenciales ni detalles SMTP al navegador.
    error_log('DomiPizzas: falló el envío de una solicitud de recuperación.');
    recovery_response(503, 'No se pudo enviar la solicitud. Contactá al WhatsApp 0972 957385 o intentá más tarde.');
}
