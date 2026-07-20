<?php
// Carga PROJECT_ROOT, rutas y registro de módulos.
/**
 * Bootstrap del proyecto — carga rutas y constantes desde cualquier profundidad de módulo.
 */
if (defined('APP_BOOTSTRAPPED')) {
    return;
}
define('APP_BOOTSTRAPPED', true);

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
}

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/modules_registry.php';

if (!isset($GLOBALS['BASE_PATH'])) {
    $GLOBALS['BASE_PATH'] = computeBasePath();
}
$BASE_PATH = $GLOBALS['BASE_PATH'];
