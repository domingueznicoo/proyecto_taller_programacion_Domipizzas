<?php
// Verifica sesión antes de exportar libro de compras.
require_once __DIR__ . '/../../../config/bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['username'])) {
    http_response_code(401);
    die('Sesión expirada. Inicie sesión nuevamente.');
}
require_once PROJECT_ROOT . '/config/permissions.php';
if (!check_permission('LIBRO_COMPRAS', false)) {
    http_response_code(403);
    die('Acceso denegado. No tiene permiso para consultar el Libro de Compras.');
}
