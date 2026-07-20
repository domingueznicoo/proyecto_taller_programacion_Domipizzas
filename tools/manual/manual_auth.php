<?php
/**
 * Inicio de sesión local solo para capturas del manual (Playwright).
 * Restringido a 127.0.0.1 / ::1 — no usar en producción expuesta.
 */
declare(strict_types=1);

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Acceso denegado: solo localhost.';
    exit;
}

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/qa/qa_bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

try {
    $session = qa_func_admin_session();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo iniciar sesión QA: ' . $e->getMessage();
    exit;
}

foreach ($session as $key => $value) {
    $_SESSION[$key] = $value;
}

$next = (string)($_GET['next'] ?? 'index.php');
$next = str_replace('\\', '/', $next);
if ($next === '' || str_contains($next, '://') || str_starts_with($next, '//') || str_contains($next, '..')) {
    $next = 'index.php';
}

header('Location: /' . ltrim($next, '/'));
exit;
