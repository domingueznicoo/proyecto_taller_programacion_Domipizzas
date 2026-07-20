<?php
/**
 * Validación automática del sistema vs reglas de negocio y documentación de tesis.
 *
 * Uso:
 *   php tools/qa_validar_sistema.php --modulo=compras
 *   php tools/qa_validar_sistema.php --modulo=produccion
 *   php tools/qa_validar_sistema.php --modulo=compras --json
 *   php tools/qa_run_compras_funcional.php   # inserta datos vía proses.php + integridad
 *   php tools/qa_run_produccion_funcional.php
 */
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/tools/qa_compras_modulos.php';
require_once $projectRoot . '/tools/qa_produccion_modulos.php';

$modulo = 'compras';
$asJson = false;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--modulo=')) {
        $modulo = substr($arg, 9);
    }
    if ($arg === '--json') {
        $asJson = true;
    }
}

$results = [
    'fecha' => date('Y-m-d H:i:s'),
    'database' => $database ?? '?',
    'modulo' => $modulo,
    'resumen' => ['ok' => 0, 'fail' => 0, 'warn' => 0],
    'pruebas' => [],
];

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

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    qa_check($results, 'db.conexion', 'Conexión PostgreSQL', true, "BD: $database");

    if ($modulo === 'compras' || $modulo === 'all') {
        validar_compras_completo($pdo, $results);
    }
    if ($modulo === 'produccion' || $modulo === 'all') {
        validar_produccion_completo($pdo, $results);
    }
} catch (Throwable $e) {
    qa_check($results, 'db.error', 'Conexión / ejecución', false, $e->getMessage());
}

if ($asJson) {
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($results['resumen']['fail'] > 0 ? 1 : 0);
}

$grupos = [
    'estructura' => 'Estructura del código',
    'pedido' => '1. Pedido de compra',
    'presupuesto' => '2. Presupuesto de compra',
    'oc' => '3. Orden de compra',
    'factura' => '4. Gestionar compras (Factura)',
    'cuentas_pagar' => '5. Cuentas a pagar',
    'nota' => '6. Nota crédito / débito',
    'nr' => '7. Nota de remisión',
    'ajustes' => '8. Ajustes de stock',
    'proveedor' => '9. Proveedores',
    'materia_prima' => '10. Materia prima',
    'libro' => '11. Libro de compras',
    'cadena' => 'Cadena documental',
    'db' => 'Base de datos',
];

echo PHP_EOL . "=== QA Emmanuels — Módulo Compras (completo) ===" . PHP_EOL;
echo "Base de datos: {$results['database']}" . PHP_EOL;
echo "Fecha: {$results['fecha']}" . PHP_EOL;

$porGrupo = [];
foreach ($results['pruebas'] as $p) {
    $parts = explode('.', $p['id']);
    $key = $parts[1] ?? 'otros';
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

echo PHP_EOL . "Resumen: {$results['resumen']['ok']} OK, {$results['resumen']['warn']} advertencias, {$results['resumen']['fail']} fallos" . PHP_EOL;
exit($results['resumen']['fail'] > 0 ? 1 : 0);
