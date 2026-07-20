<?php
/**
 * Worker: genera reporte PDF y valida salida.
 */
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

if ($argc < 3) {
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

if (!empty($payload['session']) && is_array($payload['session'])) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION = $payload['session'];
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

$isPdf = str_starts_with($output, '%PDF');
$ok = $thrown === null && strlen($output) > 200 && ($isPdf || !preg_match('/\b(die|Error|No se encontraron)\b/i', $output));

echo json_encode([
    'ok' => $ok,
    'bytes' => strlen($output),
    'is_pdf' => $isPdf,
    'error' => $thrown,
], JSON_UNESCAPED_UNICODE);
