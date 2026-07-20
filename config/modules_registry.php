<?php
// Mapa de carpetas de módulos y helper moduloUrl().
/**
 * Registro central de módulos por dominio (Compras, Producción, Ventas, Comunes).
 */

const MODULO_DOMINIO_COMPRAS = 'compras';
const MODULO_DOMINIO_PRODUCCION = 'produccion';
const MODULO_DOMINIO_VENTAS = 'ventas';
const MODULO_DOMINIO_COMUNES = 'comunes';

/** @return array<string, string> carpeta => dominio/carpeta */
function obtenerMapaModulos(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $compras = [
        'pedido_compra', 'presupuesto', 'orden_compra', 'gestionar_compras',
        'nota_credito', 'nota_remision', 'ajustes', 'libro_compras',
        'proveedor', 'materia_prima',
    ];
    $produccion = [
        'pedido_produccion', 'orden_produccion', 'control_produccion', 'etapa_produccion',
        'equipos_produccion', 'productos_terminados', 'control_calidad', 'perdidas',
        'pedido_materia_produccion', 'reposicion_materia', 'costos_produccion',
        'trabajadores', 'parametros_control', 'inspectores',
    ];
    $ventas = [
        'pedido_venta', 'presupuesto_venta', 'gestionar_ventas', 'cobranzas',
        'nota_credito_venta', 'libro_ventas', 'apertura_cierre_caja',
    ];
    $comunes = [
        'reportes', 'usuario', 'reset_password', 'perfil',
        'stock', 'producto', 'clientes', 'deposito', 'ciudad', 'departamento',
        'tipo_producto', 'u_medida',
    ];

    $map = [];
    foreach ($compras as $m) {
        $map[$m] = MODULO_DOMINIO_COMPRAS . '/' . $m;
    }
    foreach ($produccion as $m) {
        $map[$m] = MODULO_DOMINIO_PRODUCCION . '/' . $m;
    }
    foreach ($ventas as $m) {
        $map[$m] = MODULO_DOMINIO_VENTAS . '/' . $m;
    }
    foreach ($comunes as $m) {
        $map[$m] = MODULO_DOMINIO_COMUNES . '/' . $m;
    }

    return $map;
}

function moduloCarpetaResolver(string $carpeta): string
{
    static $alias = [
        'inspeccion' => 'parametros_control',
        'marca' => 'tipo_producto',
    ];
    return $alias[$carpeta] ?? $carpeta;
}

function moduloRutaRelativa(string $carpeta): string
{
    $map = obtenerMapaModulos();
    $carpeta = moduloCarpetaResolver($carpeta);
    return $map[$carpeta] ?? (MODULO_DOMINIO_COMUNES . '/' . $carpeta);
}

function moduloUrl(string $carpeta, string $archivo = 'view.php'): string
{
    $base = $GLOBALS['BASE_PATH'] ?? computeBasePath();
    return $base . 'modules/' . moduloRutaRelativa($carpeta) . '/' . ltrim($archivo, '/');
}

function moduloRutaAbsoluta(string $carpeta, string $archivo = ''): string
{
    $rel = moduloRutaRelativa($carpeta);
    $path = PROJECT_ROOT . '/modules/' . $rel;
    return $archivo !== '' ? $path . '/' . ltrim($archivo, '/') : $path;
}

/** Rutas relativas desde modules/comunes/reportes/ hacia otro módulo */
function moduloRutaDesde(string $desdeDominioCarpeta, string $carpetaDestino, string $archivo = 'generar_informe.php'): string
{
    $dest = moduloRutaRelativa($carpetaDestino);
    $depth = substr_count($desdeDominioCarpeta, '/') + 1;
    $prefix = str_repeat('../', $depth);
    return $prefix . '../' . $dest . '/' . ltrim($archivo, '/');
}
