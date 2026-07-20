<?php
require __DIR__ . '/../config/database.php';

$sqlFile = __DIR__ . '/../backup bd/migracion_fechas_timestamp_compras.sql';
$sql = file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "No se pudo leer el archivo SQL.\n");
    exit(1);
}

try {
    $pdo->exec($sql);
    echo "Migracion aplicada correctamente.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
