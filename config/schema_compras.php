<?php
/**
 * Ajustes de esquema Compras — alineación con especificación (observaciones en OC, etc.).
 */
declare(strict_types=1);

function compras_ensure_schema(PDO $pdo): void
{
    compras_ensure_orden_observaciones($pdo);
}

function compras_ensure_orden_observaciones(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $exists = (bool)$pdo->query("
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'orden_de_compra'
              AND column_name = 'orden_observaciones'
            LIMIT 1
        ")->fetchColumn();

        if ($exists) {
            return;
        }

        $pdo->exec("ALTER TABLE orden_de_compra ADD COLUMN orden_observaciones TEXT");
        $pdo->exec("
            COMMENT ON COLUMN orden_de_compra.orden_observaciones IS
            'Observaciones o condiciones adicionales de la orden de compra'
        ");
    } catch (Throwable $e) {
        error_log('compras_ensure_orden_observaciones: ' . $e->getMessage());
    }
}
