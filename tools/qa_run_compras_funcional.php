<?php
/**
 * Ejecuta pruebas funcionales de Compras (inserta datos vía proses.php) + validación de integridad.
 *
 * Uso:
 *   php tools/qa_run_compras_funcional.php
 *   php tools/qa_run_compras_funcional.php --solo-integridad
 */
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/tools/qa_compras_modulos.php';
require_once $projectRoot . '/tools/qa_compras_funcional.php';
require_once $projectRoot . '/tools/qa_compras_funcional_extra.php';

if (!function_exists('qa_check')) {
    function qa_check(array &$results, string $id, string $nombre, bool $ok, string $detalle = '', string $nivel = 'fail'): void
    {
        $status = $ok ? 'OK' : strtoupper($nivel);
        if ($ok) {
            $results['resumen']['ok']++;
        } elseif ($nivel === 'warn') {
            $results['resumen']['warn']++;
        } else {
            $results['resumen']['fail']++;
        }
        $results['pruebas'][] = [
            'id' => $id,
            'nombre' => $nombre,
            'estado' => $status,
            'detalle' => $detalle,
        ];
    }
}

$soloIntegridad = in_array('--solo-integridad', $argv ?? [], true);

$results = [
    'fecha'    => date('Y-m-d H:i:s'),
    'database' => $database ?? '?',
    'modulo'   => 'compras-funcional',
    'resumen'  => ['ok' => 0, 'fail' => 0, 'warn' => 0],
    'pruebas'  => [],
];

$ctx = [];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    qa_check($results, 'db.conexion', 'Conexión PostgreSQL', true, "BD: $database");

    if (!$soloIntegridad) {
        echo PHP_EOL . '>>> Fase 1: Pruebas funcionales (proses.php del sistema)' . PHP_EOL;
        validar_compras_funcional($results, $ctx);
    }

    echo PHP_EOL . '>>> Fase 2: Validación de integridad post-datos' . PHP_EOL;
    validar_compras_completo($pdo, $results);
} catch (Throwable $e) {
    qa_check($results, 'db.error', 'Ejecución QA funcional', false, $e->getMessage());
}

echo PHP_EOL . "=== QA Funcional Compras ===" . PHP_EOL;
echo "BD: {$results['database']} | {$results['fecha']}" . PHP_EOL;

$grupos = [
    'func'     => 'Pruebas funcionales — flujo principal',
    'perm'     => 'Permisos por rol',
    'proveedor'=> 'Proveedor (referencial)',
    'materia_prima' => 'Materia prima (referencial)',
    'informe'  => 'Informes por rango',
    'bitacora' => 'Bitácora',
    'nota'     => 'Notas (anulación)',
    'estructura' => 'Estructura',
    'schema'   => 'Esquema BD',
    'pedido'   => 'Pedido',
    'presupuesto' => 'Presupuesto',
    'oc'       => 'OC',
    'factura'  => 'Factura',
    'cuentas_pagar' => 'Cuentas pagar',
    'nota'     => 'Notas',
    'nr'       => 'NR',
    'ajustes'  => 'Ajustes',
    'libro'    => 'Libro',
    'cadena'   => 'Cadena',
    'db'       => 'BD',
];

$porGrupo = [];
foreach ($results['pruebas'] as $p) {
    $parts = explode('.', $p['id']);
    $key = match (true) {
        str_starts_with($p['id'], 'func.oc.') => 'oc',
        str_starts_with($p['id'], 'func.perm.') => 'perm',
        str_starts_with($p['id'], 'func.proveedor.') => 'proveedor',
        str_starts_with($p['id'], 'func.materia_prima.') => 'materia_prima',
        str_starts_with($p['id'], 'func.informe.') => 'informe',
        str_starts_with($p['id'], 'func.bitacora.') => 'bitacora',
        str_starts_with($p['id'], 'func.nota.') => 'nota',
        str_starts_with($p['id'], 'func.') => 'func',
        default => ($parts[1] ?? 'otros'),
    };
    $porGrupo[$key][] = $p;
}

foreach ($grupos as $key => $titulo) {
    if (empty($porGrupo[$key])) {
        continue;
    }
    echo PHP_EOL . "--- $titulo ---" . PHP_EOL;
    foreach ($porGrupo[$key] as $p) {
        $icon = $p['estado'] === 'OK' ? '[OK]  ' : ($p['estado'] === 'WARN' ? '[WARN]' : '[FAIL]');
        echo "{$icon} {$p['nombre']}" . PHP_EOL;
        if ($p['detalle'] !== '') {
            echo "       {$p['detalle']}" . PHP_EOL;
        }
    }
}

if (!empty($ctx)) {
    echo PHP_EOL . 'IDs generados: ' . json_encode($ctx, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

echo PHP_EOL . "Resumen: {$results['resumen']['ok']} OK, {$results['resumen']['warn']} advertencias, {$results['resumen']['fail']} fallos" . PHP_EOL;
exit($results['resumen']['fail'] > 0 ? 1 : 0);
