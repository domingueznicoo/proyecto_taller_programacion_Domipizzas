<?php
// Calcula rutas relativas (BASE_PATH, login) según profundidad del script.
/**
 * Rutas base del proyecto — soporta módulos anidados (modules/compras/..., etc.).
 */

function computeBasePath(): string
{
    if (!defined('PROJECT_ROOT')) {
        define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
    }

    $scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? '';
    if ($scriptFile === '' || !is_file($scriptFile)) {
        $scriptFile = $_SERVER['DOCUMENT_ROOT'] . ($_SERVER['SCRIPT_NAME'] ?? '');
    }

    $dir = str_replace('\\', '/', dirname(realpath($scriptFile) ?: $scriptFile));
    $root = str_replace('\\', '/', PROJECT_ROOT);

    if ($dir === $root || strpos($dir, $root) !== 0) {
        return '';
    }

    $relative = trim(substr($dir, strlen($root)), '/');
    if ($relative === '') {
        return '';
    }

    $depth = substr_count($relative, '/') + 1;
    return str_repeat('../', $depth);
}

function loginUrl(): string
{
    return computeBasePath() . 'login.html';
}

function indexUrl(): string
{
    return computeBasePath() . 'index.php';
}

/** @deprecated Usar computeBasePath() */
function getBasePath(): string
{
    return computeBasePath();
}

// URL relativa al panel central de informes (desde generar_informe.php en submódulos Compras).
function informesComprasViewUrl(): string
{
    return '../../comunes/reportes/view.php';
}

$BASE_PATH = computeBasePath();
