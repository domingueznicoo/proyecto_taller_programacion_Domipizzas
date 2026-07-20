<?php
/**
 * Esquema y registro de bitácora — acción ANULACION según casos de uso de anulación documental.
 */
declare(strict_types=1);

function bitacora_ensure_anulacion_accion(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $row = $pdo->query("
            SELECT pg_get_constraintdef(oid) AS def
            FROM pg_constraint
            WHERE conname = 'ck_bitacora_accion'
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        if ($row && stripos((string)$row['def'], 'ANULACION') !== false) {
            return;
        }

        $pdo->exec('ALTER TABLE bitacora DROP CONSTRAINT IF EXISTS ck_bitacora_accion');
        $pdo->exec("
            ALTER TABLE bitacora ADD CONSTRAINT ck_bitacora_accion CHECK (
                accion_realizada IN ('ALTA', 'MODIFICACION', 'ELIMINACION', 'INACTIVACION', 'ANULACION')
            )
        ");
    } catch (Throwable $e) {
        error_log('bitacora_ensure_anulacion_accion: ' . $e->getMessage());
    }
}

function bitacora_registrar(
    PDO $pdo,
    int $idUsuario,
    string $entidad,
    string $accion,
    string $descripcion,
    ?int $idRegistro = null
): void {
    bitacora_ensure_anulacion_accion($pdo);

    $savepoint = null;
    try {
        if ($pdo->inTransaction()) {
            $savepoint = 'bitacora_sp_' . str_replace(['.', '-'], '_', uniqid('', true));
            $pdo->exec("SAVEPOINT {$savepoint}");
        }

        $accion = strtoupper(trim($accion));
        if ($accion === 'ADVERTENCIA') {
            $accion = 'MODIFICACION';
            $descripcion = '[Advertencia] ' . $descripcion;
        }

        $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:u, :e, :r, :a, :d)
        ")->execute([
            ':u' => $idUsuario,
            ':e' => $entidad,
            ':r' => $idRegistro,
            ':a' => $accion,
            ':d' => $descripcion,
        ]);

        if ($savepoint !== null) {
            $pdo->exec("RELEASE SAVEPOINT {$savepoint}");
        }
    } catch (Throwable $e) {
        if ($savepoint !== null) {
            try {
                $pdo->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
                $pdo->exec("RELEASE SAVEPOINT {$savepoint}");
            } catch (Throwable $inner) {
                error_log('bitacora_registrar rollback: ' . $inner->getMessage());
            }
        }
        error_log('bitacora_registrar: ' . $e->getMessage());
    }
}
