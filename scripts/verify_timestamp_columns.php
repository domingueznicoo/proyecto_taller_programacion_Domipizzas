<?php
require __DIR__ . '/../config/database.php';
$cols = $pdo->query("
    SELECT table_name, column_name, data_type
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND column_name IN ('presu_fecha','pedido_fecha_emision','orden_fecha','fact_fecha_compra')
    ORDER BY table_name
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['table_name'] . '.' . $c['column_name'] . ' => ' . $c['data_type'] . PHP_EOL;
}
echo PHP_EOL . 'Presupuestos:' . PHP_EOL;
foreach ($pdo->query('SELECT id_presupuesto_compra, presu_fecha FROM presupuesto_compra ORDER BY 1') as $row) {
    echo $row['id_presupuesto_compra'] . ': ' . $row['presu_fecha'] . PHP_EOL;
}
