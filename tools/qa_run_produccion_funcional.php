<?php
/**
 * Ejecuta pruebas funcionales de Producción (inserta datos vía proses.php) + validación de integridad.
 *
 * Uso:
 *   php tools/qa_run_produccion_funcional.php
 *   php tools/qa_run_produccion_funcional.php --solo-integridad
 */
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/tools/qa_produccion_modulos.php';
require_once $projectRoot . '/tools/qa_produccion_funcional.php';

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
    'modulo'   => 'produccion-funcional',
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
        validar_produccion_funcional($results, $ctx);
    }

    echo PHP_EOL . '>>> Fase 2: Validación de integridad post-datos' . PHP_EOL;
    validar_produccion_completo($pdo, $results);
} catch (Throwable $e) {
    qa_check($results, 'db.error', 'Ejecución QA funcional', false, $e->getMessage());
}

echo PHP_EOL . "=== QA Funcional Producción ===" . PHP_EOL;
echo "BD: {$results['database']} | {$results['fecha']}" . PHP_EOL;

$grupos = [
    'func'       => 'Pruebas funcionales — flujo principal',
    'perm'       => 'Permisos por rol',
    'informe'    => 'Informes por rango',
    'trabajador' => 'Referenciales — trabajadores',
    'inspector'  => 'Referenciales — inspectores',
    'parametro'  => 'Referenciales — parámetros',
    'equipo'     => 'Equipos de trabajo',
    'costo'      => 'Costos producción',
    'perdida'    => 'Pérdidas',
    'estructura' => 'Estructura',
    'pedido'     => 'Pedido producción',
    'orden'      => 'Orden producción',
    'control'    => 'Control producción',
    'pt'         => 'Productos terminados',
    'calidad'    => 'Control calidad',
    'pedido_mp'  => 'Pedido materia prima',
    'reposicion' => 'Reposición',
    'ref'        => 'Referenciales',
    'cadena'     => 'Cadena documental',
    'perdidas'   => 'Integridad pérdidas',
    'equipos'    => 'Integridad equipos',
    'costos'     => 'Integridad costos',
];

$porGrupo = [];
foreach ($results['pruebas'] as $p) {
    $key = match (true) {
        str_starts_with($p['id'], 'func.perm.') => 'perm',
        str_starts_with($p['id'], 'func.informe.') => 'informe',
        str_starts_with($p['id'], 'func.trabajador.') => 'trabajador',
        str_starts_with($p['id'], 'func.inspector.') => 'inspector',
        str_starts_with($p['id'], 'func.parametro.') => 'parametro',
        str_starts_with($p['id'], 'func.equipo.') => 'equipo',
        str_starts_with($p['id'], 'func.costo.') => 'costo',
        str_starts_with($p['id'], 'func.perdida.') => 'perdida',
        str_starts_with($p['id'], 'func.') => 'func',
        default => (explode('.', $p['id'])[1] ?? 'otros'),
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
