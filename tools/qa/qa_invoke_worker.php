<?php
/**
 * Worker aislado: ejecuta un proses.php con sesión simulada (como el navegador).
 */
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

if ($argc < 3) {
    fwrite(STDERR, "Usage: qa_invoke_worker.php <moduleRelPath> <base64payload>\n");
    exit(2);
}

$moduleRelPath = $argv[1];
$payload = json_decode(base64_decode($argv[2], true) ?: '', true);
if (!is_array($payload)) {
    echo json_encode(['ok' => false, 'error' => 'Payload inválido']);
    exit(1);
}

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

$_GET = $payload['get'] ?? [];
$_POST = $payload['post'] ?? [];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'localhost';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = $payload['session'] ?? [];

if (!empty($payload['json_body'])) {
    $jb = $payload['json_body'];
    define('QA_JSON_PAYLOAD', is_string($jb) ? $jb : json_encode($jb, JSON_UNESCAPED_UNICODE));
}

$target = PROJECT_ROOT . '/' . ltrim(str_replace('\\', '/', $moduleRelPath), '/');
if (!is_file($target)) {
    echo json_encode(['ok' => false, 'error' => "No existe $target"]);
    exit(1);
}

ob_start();
$thrown = null;
try {
    include $target;
} catch (Throwable $e) {
    $thrown = $e->getMessage();
}
$output = ob_get_clean();
$trimmed = trim($output);

$ok = $thrown === null;
$jsonOut = null;
if ($trimmed !== '') {
    if (preg_match('/\b(die|Error al|Error en|inválid|invalido|no se pudo|Token de sesión)\b/i', $trimmed)) {
        $ok = false;
    }
    $jsonOut = json_decode($trimmed, true);
    if (is_array($jsonOut) && array_key_exists('success', $jsonOut)) {
        $ok = (bool)$jsonOut['success'];
    }
}

echo json_encode([
    'ok'      => $ok,
    'output'  => mb_substr($output, 0, 4000),
    'message' => is_array($jsonOut ?? null) ? ($jsonOut['message'] ?? null) : null,
    'error'   => $thrown,
], JSON_UNESCAPED_UNICODE);
