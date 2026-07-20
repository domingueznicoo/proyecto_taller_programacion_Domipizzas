<?php
/**
 * Limpia datos transaccionales (Compras + Producción) para empezar pruebas desde cero.
 * NO modifica tablas referenciales (catálogos, usuarios, rutas, etc.).
 *
 * Uso:
 *   php tools/limpiar_datos_transaccionales.php              # simulación (dry-run)
 *   php tools/limpiar_datos_transaccionales.php --confirm    # ejecuta limpieza
 *   php tools/limpiar_datos_transaccionales.php --confirm --semilla-qa
 *
 * --semilla-qa  Deja stock mínimo de MP para poder correr la suite QA sin cargar compras antes.
 */
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/config/database.php';

$confirm = in_array('--confirm', $argv ?? [], true);
$semillaQa = in_array('--semilla-qa', $argv ?? [], true);
$asJson = in_array('--json', $argv ?? [], true);

/** Tablas referenciales que NO se tocan. */
const TABLAS_REFERENCIALES = [
    'cargos',
    'conductores',
    'deposito',
    'etapa_detalle_produccion',
    'etapa_produccion',
    'equipos_produccion',
    'equipo_detalle',
    'inspectores',
    'materia_prima',
    'modulos',
    'motivo',
    'parametros_control',
    'personal',
    'productos',
    'proveedor',
    'receta_produccion',
    'sucursales',
    'tipo_iva',
    'tipo_pedido',
    'tipo_perdida',
    'tipo_producto',
    'trabajadores',
    'unidad_medida',
    'usuarios',
    'vehiculos',
];

/**
 * Orden de borrado: hijos antes que padres (respeta FKs sin CASCADE).
 */
const TABLAS_TRANSACCIONALES = [
    // Producción — detalle
    'control_calidad_detalle',
    'perdidas_detalle',
    'control_produccion_consumo',
    'control_produccion_detalle',
    'productos_terminados_detalle',
    'reposicion_materia_detalle',
    'pedido_materia_detalle_produccion',
    'pedido_detalle_produccion',
    'orden_detalle_produccion',
    'costo_detalle_produccion',
    // Producción — cabecera
    'perdidas',
    'control_calidad_produccion',
    'control_produccion',
    'producto_terminado',
    'reposicion_materia',
    'pedido_materia_produccion',
    'costo_produccion',
    'orden_produccion',
    'pedido_produccion',
    // Compras — detalle
    'ajustes_detalle',
    'nota_detalle_compra',
    'nota_remision_detalle_compra',
    'factura_detalle_compra',
    'orden_detalle_compra',
    'presupuesto_detalle_compra',
    'pedido_detalle_compra',
    // Compras — cabecera y libro
    'iva_compra',
    'cuentas_pagar',
    'nota_compra',
    'nota_remision_compra',
    'factura_compra',
    'orden_de_compra',
    'presupuesto_compra',
    'pedidos_compra',
    'ajustes',
    // Auditoría
    'bitacora',
];

/** Secuencias a reiniciar (tablas con IDs autogenerados). */
const SECUENCIAS_REINICIO = [
    'ajuste_stock_id_ajuste_seq',
    'bitacora_id_bitacora_seq',
    'control_calidad_produccion_calidad_id_seq',
    'control_produccion_control_id_seq',
    'costo_detalle_produccion_costo_detalle_id_seq',
    'costo_produccion_costo_id_seq',
    'cuentas_pagar_id_cuenta_pagar_seq',
    'factura_compra_id_factura_compra_seq',
    'iva_compra_id_libro_compra_seq',
    'nota_compra_id_nota_compra_seq',
    'nota_remision_compra_id_nota_remision_seq',
    'orden_de_compra_id_orden_compra_seq',
    'orden_produccion_orden_id_seq',
    'pedido_materia_produccion_id_pedido_mat_prod_seq',
    'pedido_produccion_id_pedido_produccion_seq',
    'pedidos_compra_id_pedido_compra_seq',
    'perdidas_perdidas_id_seq',
    'presupuesto_compra_id_presupuesto_compra_seq',
    'producto_terminado_terminado_id_seq',
    'reposicion_materia_reposicion_id_seq',
];

/** Stock mínimo para suite QA (MP id => cantidad en depósito 1). */
const SEMILLA_STOCK_MP = [
    10 => 200,
    5  => 50,
];

function tabla_existe(PDO $pdo, string $tabla): bool
{
    $st = $pdo->prepare("
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = 'public' AND table_name = :t
        LIMIT 1
    ");
    $st->execute([':t' => $tabla]);
    return (bool)$st->fetchColumn();
}

function contar_filas(PDO $pdo, string $tabla): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM \"{$tabla}\"")->fetchColumn();
}

function secuencia_existe(PDO $pdo, string $seq): bool
{
    $st = $pdo->prepare('SELECT 1 FROM pg_class WHERE relkind = \'S\' AND relname = :s LIMIT 1');
    $st->execute([':s' => $seq]);
    return (bool)$st->fetchColumn();
}

function aplicar_semilla_stock(PDO $pdo): array
{
    $detalle = [];
    foreach (SEMILLA_STOCK_MP as $mpId => $cantidad) {
        $st = $pdo->prepare("
            UPDATE stock_materia_prima
            SET cantidad_existente = :cant
            WHERE id_materia_prima = :mp AND deposito_id = 1
        ");
        $st->execute([':cant' => $cantidad, ':mp' => $mpId]);
        if ($st->rowCount() > 0) {
            $detalle[] = "MP #{$mpId} depósito 1 = {$cantidad}";
            continue;
        }
        $chk = $pdo->prepare('SELECT 1 FROM materia_prima WHERE id_materia_prima = :id LIMIT 1');
        $chk->execute([':id' => $mpId]);
        if ($chk->fetchColumn()) {
            $pdo->prepare("
                INSERT INTO stock_materia_prima (id_materia_prima, stock_cantidad_minima, stock_cantidad_maxima, cantidad_existente, deposito_id, id_usuario)
                VALUES (:mp, 20, 500, :cant, 1, 1)
            ")->execute([':mp' => $mpId, ':cant' => $cantidad]);
            $detalle[] = "MP #{$mpId} depósito 1 creado = {$cantidad}";
        }
    }
    return $detalle;
}

$resultado = [
    'fecha' => date('Y-m-d H:i:s'),
    'database' => $database ?? '?',
    'modo' => $confirm ? 'ejecucion' : 'dry-run',
    'semilla_qa' => $semillaQa,
    'referenciales_intactos' => TABLAS_REFERENCIALES,
    'tablas' => [],
    'pre_limpieza' => [],
    'stock' => [],
    'secuencias' => [],
    'errores' => [],
];

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$database", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    if ($confirm && tabla_existe($pdo, 'equipos_produccion') && tabla_existe($pdo, 'equipo_detalle')) {
        $nEq = (int)$pdo->query('SELECT COUNT(*) FROM equipos_produccion WHERE orden_id IS NOT NULL')->fetchColumn();
        if ($nEq > 0) {
            $pdo->exec('DELETE FROM equipo_detalle WHERE equipo_id IN (SELECT equipo_id FROM equipos_produccion WHERE orden_id IS NOT NULL)');
            $pdo->exec('DELETE FROM equipos_produccion WHERE orden_id IS NOT NULL');
            $resultado['pre_limpieza'][] = "equipos vinculados a órdenes: {$nEq} eliminado(s)";
        }
    }

    foreach (TABLAS_TRANSACCIONALES as $tabla) {
        if (!tabla_existe($pdo, $tabla)) {
            $resultado['tablas'][] = ['tabla' => $tabla, 'estado' => 'omitida', 'filas' => 0, 'detalle' => 'no existe'];
            continue;
        }
        $antes = contar_filas($pdo, $tabla);
        if ($confirm) {
            $pdo->exec("DELETE FROM \"{$tabla}\"");
        }
        $resultado['tablas'][] = [
            'tabla' => $tabla,
            'estado' => $confirm ? 'limpiada' : 'pendiente',
            'filas' => $antes,
            'detalle' => $antes . ' fila(s)',
        ];
    }

    if ($confirm) {
        $pdo->exec('UPDATE stock_materia_prima SET cantidad_existente = 0');
        $pdo->exec('UPDATE stock_producto SET stock_prod_existente = 0');
        $resultado['stock'][] = 'stock_materia_prima.cantidad_existente → 0';
        $resultado['stock'][] = 'stock_producto.stock_prod_existente → 0';

        if ($semillaQa) {
            $resultado['stock'] = array_merge($resultado['stock'], aplicar_semilla_stock($pdo));
        }

        foreach (SECUENCIAS_REINICIO as $seq) {
            if (!secuencia_existe($pdo, $seq)) {
                $resultado['secuencias'][] = ['secuencia' => $seq, 'estado' => 'omitida'];
                continue;
            }
            $pdo->exec("SELECT setval('public.\"{$seq}\"', 1, false)");
            $resultado['secuencias'][] = ['secuencia' => $seq, 'estado' => 'reiniciada'];
        }
    } else {
        $resultado['stock'][] = 'pendiente: cantidades a 0' . ($semillaQa ? ' + semilla QA' : '');
        $resultado['secuencias'][] = ['secuencia' => '(todas)', 'estado' => 'pendiente reinicio'];
    }
} catch (Throwable $e) {
    $resultado['errores'][] = $e->getMessage();
}

if ($asJson) {
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(empty($resultado['errores']) ? 0 : 1);
}

echo PHP_EOL . '=== Limpieza datos transaccionales ===' . PHP_EOL;
echo 'BD: ' . $resultado['database'] . ' | ' . $resultado['fecha'] . PHP_EOL;
echo 'Modo: ' . ($confirm ? 'EJECUCIÓN' : 'SIMULACIÓN (agregue --confirm para aplicar)') . PHP_EOL;
if ($semillaQa) {
    echo 'Semilla QA: sí (stock MP mínimo para pruebas automatizadas)' . PHP_EOL;
}

echo PHP_EOL . '--- Referenciales NO tocados ---' . PHP_EOL;
echo implode(', ', TABLAS_REFERENCIALES) . PHP_EOL;

$totalFilas = 0;
echo PHP_EOL . '--- Tablas transaccionales ---' . PHP_EOL;
foreach ($resultado['tablas'] as $t) {
    $totalFilas += (int)$t['filas'];
    if ((int)$t['filas'] === 0 && $t['estado'] === 'omitida') {
        continue;
    }
    echo sprintf("  %-40s %6d filas  [%s]%s\n", $t['tabla'], $t['filas'], $t['estado'], $t['estado'] === 'omitida' ? ' (' . $t['detalle'] . ')' : '');
}

echo PHP_EOL . '--- Stock ---' . PHP_EOL;
foreach ($resultado['stock'] as $linea) {
    echo '  ' . $linea . PHP_EOL;
}

if (!empty($resultado['errores'])) {
    echo PHP_EOL . 'ERRORES:' . PHP_EOL;
    foreach ($resultado['errores'] as $err) {
        echo '  ' . $err . PHP_EOL;
    }
    exit(1);
}

echo PHP_EOL . sprintf('Total filas a eliminar: %d', $totalFilas) . PHP_EOL;
if (!$confirm) {
    echo PHP_EOL . 'Para aplicar: php tools/limpiar_datos_transaccionales.php --confirm' . PHP_EOL;
    echo 'Con stock QA:  php tools/limpiar_datos_transaccionales.php --confirm --semilla-qa' . PHP_EOL;
} else {
    echo PHP_EOL . 'Limpieza completada. Puede iniciar pruebas desde cero.' . PHP_EOL;
}

exit(0);
