<?php
/** Repara IVA y fac_iva de facturas según descuentos del presupuesto. Solo datos existentes. */
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/modules/compras/helpers/compras_calculos.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$onlyId = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--factura=')) {
        $onlyId = (int)substr($arg, 10);
    }
}

$pdo = new PDO("pgsql:host=$host;port=$port;dbname=$database", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$sql = "SELECT id_factura_compra FROM factura_compra WHERE UPPER(fac_estado) <> 'ANULADO'";
if ($onlyId) {
    $sql .= ' AND id_factura_compra = ' . (int)$onlyId;
}
$ids = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

foreach ($ids as $factId) {
    $factId = (int)$factId;
    $calc = compras_calcular_total_factura_neto($pdo, $factId);
    $iva5 = 0;
    $iva10 = 0;
    $ex = 0;

    foreach ($calc['lineas'] as $ln) {
        $stIva = $pdo->prepare("SELECT ti.iva_descri FROM materia_prima mp LEFT JOIN tipo_iva ti ON ti.iva_id = mp.iva_id WHERE mp.id_materia_prima = :id");
        $stIva->execute([':id' => $ln['id_materia_prima']]);
        $descri = (string)$stIva->fetchColumn();
        $div = compras_iva_divisor_por_descri($descri);
        if ($div === 21) {
            $iva5 += $ln['iva'];
        } elseif ($div === 11) {
            $iva10 += $ln['iva'];
        } else {
            $ex += $ln['iva'];
        }
    }

    echo "Factura #$factId: total={$calc['total']} IVA5=$iva5 IVA10=$iva10\n";

    if ($dryRun) {
        continue;
    }

    $pdo->beginTransaction();
    try {
        foreach ($calc['lineas'] as $ln) {
            $st = $pdo->prepare("
                UPDATE factura_detalle_compra
                SET fac_iva = :iva
                WHERE id_factura_compra = :f AND id_materia_prima = :mp
            ");
            $st->execute([':iva' => $ln['iva'], ':f' => $factId, ':mp' => $ln['id_materia_prima']]);
        }

        $pdo->prepare("DELETE FROM iva_compra WHERE id_factura_compra = :f")->execute([':f' => $factId]);
        $fecha = $pdo->prepare("SELECT fact_fecha_compra::date FROM factura_compra WHERE id_factura_compra = :f");
        $fecha->execute([':f' => $factId]);
        $fec = $fecha->fetchColumn();

        $pdo->prepare("
            INSERT INTO iva_compra (id_factura_compra, iva_fecha, iva_exento, iva_5, iva_10)
            VALUES (:f, :fecha, :ex, :iva5, :iva10)
        ")->execute([
            ':f' => $factId,
            ':fecha' => $fec,
            ':ex' => $ex,
            ':iva5' => $iva5,
            ':iva10' => $iva10,
        ]);

        $pdo->prepare("UPDATE factura_compra SET fac_total = :t WHERE id_factura_compra = :f")
            ->execute([':t' => $calc['total'], ':f' => $factId]);

        $pdo->prepare("
            UPDATE cuentas_pagar SET monto_total = :t, monto_pendiente = CASE WHEN estado = 'FINALIZADO' THEN 0 ELSE :t END
            WHERE id_factura_compra = :f
        ")->execute([':t' => $calc['total'], ':f' => $factId]);

        $pdo->commit();
        echo "  -> reparada\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "  -> ERROR: {$e->getMessage()}\n";
    }
}

echo "Listo.\n";
