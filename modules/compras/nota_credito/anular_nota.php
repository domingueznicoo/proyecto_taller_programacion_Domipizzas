<?php
// Anula una nota de crédito/débito EMITIDA y revierte cuenta, IVA y stock (especificación punto 17).
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Token de sesión inválido']);
    exit;
}

require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/bitacora_helper.php';

$rawBody = defined('QA_JSON_PAYLOAD') ? QA_JSON_PAYLOAD : file_get_contents('php://input');
$input = json_decode($rawBody ?: '{}', true);
if (!is_array($input)) {
    $input = [];
}
$notaId = isset($input['nota_id']) ? (int)$input['nota_id'] : (int)($_POST['nota_id'] ?? 0);

if ($notaId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de nota inválido']);
    exit;
}

function nc_tasa_iva(?string $tipoIva): int
{
    $t = strtolower(trim((string)$tipoIva));
    if ($t === '10' || $t === 'iva_10') {
        return 10;
    }
    if ($t === '5' || $t === 'iva_5') {
        return 5;
    }
    return 0;
}

function nc_calcular_iva_nota(array $detalle, string $notaTipo, int $motivoId, int $factId, PDO $pdo): array
{
    $iva5 = $iva10 = $ex = 0;
    $esAjuste = ($notaTipo === 'DEBITO' && $motivoId === 4) || ($notaTipo === 'CREDITO' && $motivoId === 2);

    if ($esAjuste) {
        $stFac = $pdo->prepare('SELECT id_materia_prima, fac_cantidad, fac_precio FROM factura_detalle_compra WHERE id_factura_compra = :f');
        $stFac->execute([':f' => $factId]);
        $orig = [];
        foreach ($stFac->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $mp = (int)$r['id_materia_prima'];
            $orig[$mp] = (int)$r['fac_cantidad'] * (int)$r['fac_precio'];
        }
        foreach ($detalle as $ln) {
            $mp = (int)$ln['id_materia_prima'];
            if (!isset($orig[$mp])) {
                continue;
            }
            $subN = (int)$ln['nota_compra_cantidad'] * (int)$ln['nota_precio'];
            $subO = $orig[$mp];
            $subtotal = $notaTipo === 'CREDITO' ? max(0, $subO - $subN) : max(0, $subN - $subO);
            if ($subtotal <= 0) {
                continue;
            }
            $tasa = nc_tasa_iva($ln['tipo_iva'] ?? null);
            if ($tasa === 10) {
                $iva10 += (int)floor($subtotal / 11);
            } elseif ($tasa === 5) {
                $iva5 += (int)floor($subtotal / 21);
            } else {
                $ex += $subtotal;
            }
        }
    } elseif ($notaTipo === 'DEBITO' && $motivoId === 3) {
        $ex = (int)($detalle[0]['nota_total_nota'] ?? 0);
    } else {
        foreach ($detalle as $ln) {
            $sub = (int)$ln['nota_compra_cantidad'] * (int)$ln['nota_precio'];
            $tasa = nc_tasa_iva($ln['tipo_iva'] ?? null);
            if ($tasa === 10) {
                $iva10 += (int)floor($sub / 11);
            } elseif ($tasa === 5) {
                $iva5 += (int)floor($sub / 21);
            } else {
                $ex += $sub;
            }
        }
    }

    return ['iva_5' => $iva5, 'iva_10' => $iva10, 'iva_exento' => $ex];
}

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$database", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    bitacora_ensure_anulacion_accion($pdo);

    $usuarioId = (int)($_SESSION['id_usuario'] ?? 0);
    if ($usuarioId <= 0) {
        $stU = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1');
        $stU->execute([':u' => $_SESSION['username']]);
        $usuarioId = (int)$stU->fetchColumn();
    }
    if ($usuarioId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Usuario no válido']);
        exit;
    }

    $pdo->beginTransaction();

    $stNota = $pdo->prepare("
        SELECT id_nota_compra, nota_compra_tipo, nota_compra_estado, nota_total,
               id_motivo, id_factura_compra, id_proveedor
        FROM nota_compra
        WHERE id_nota_compra = :id
        FOR UPDATE
    ");
    $stNota->execute([':id' => $notaId]);
    $nota = $stNota->fetch(PDO::FETCH_ASSOC);

    if (!$nota) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Nota no encontrada']);
        exit;
    }

    $estado = strtoupper(trim((string)$nota['nota_compra_estado']));
    if ($estado === 'ANULADO') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'La nota ya está anulada']);
        exit;
    }
    if ($estado !== 'EMITIDA') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => "Solo se pueden anular notas EMITIDAS. Estado actual: {$estado}"]);
        exit;
    }

    $notaTipo = strtoupper(trim((string)$nota['nota_compra_tipo']));
    $motivoId = (int)$nota['id_motivo'];
    $factId = (int)$nota['id_factura_compra'];
    $montoNota = (int)$nota['nota_total'];
    $esAnulacionTotal = ($notaTipo === 'CREDITO' && $motivoId === 1);

    $stFac = $pdo->prepare("
        SELECT fac_estado, fac_total, tipo_operacion, id_orden_compra
        FROM factura_compra
        WHERE id_factura_compra = :id
        FOR UPDATE
    ");
    $stFac->execute([':id' => $factId]);
    $fac = $stFac->fetch(PDO::FETCH_ASSOC);
    if (!$fac) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Factura asociada no encontrada']);
        exit;
    }

    $stDet = $pdo->prepare("
        SELECT nd.id_materia_prima, nd.nota_compra_cantidad, nd.nota_precio, nd.tipo_iva, nc.nota_total AS nota_total_nota
        FROM nota_detalle_compra nd
        JOIN nota_compra nc ON nc.id_nota_compra = nd.id_nota_compra
        WHERE nd.id_nota_compra = :id
    ");
    $stDet->execute([':id' => $notaId]);
    $detalle = $stDet->fetchAll(PDO::FETCH_ASSOC);

    if (!$esAnulacionTotal) {
        $stCta = $pdo->prepare('SELECT monto_total, monto_pendiente, estado FROM cuentas_pagar WHERE id_factura_compra = :f FOR UPDATE');
        $stCta->execute([':f' => $factId]);
        $cta = $stCta->fetch(PDO::FETCH_ASSOC);
        if (!$cta) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Cuenta a pagar no encontrada']);
            exit;
        }

        $montoTotal = (int)$cta['monto_total'];
        $montoPend = (int)$cta['monto_pendiente'];
        $montoAplicar = $montoNota;

        if ($notaTipo === 'DEBITO') {
            $nuevoTotal = max(0, $montoTotal - $montoAplicar);
            $nuevoPend = max(0, $montoPend - $montoAplicar);
        } else {
            $nuevoTotal = $montoTotal + $montoAplicar;
            $nuevoPend = $montoPend + $montoAplicar;
        }

        $pdo->prepare('UPDATE cuentas_pagar SET monto_total = :t, monto_pendiente = :p WHERE id_factura_compra = :f')
            ->execute([':t' => $nuevoTotal, ':p' => $nuevoPend, ':f' => $factId]);

        bitacora_registrar(
            $pdo,
            $usuarioId,
            'Nota de Crédito/Débito',
            'MODIFICACION',
            "Cuenta a pagar revertida por anulación Nota #{$notaId} | Factura #{$factId} | Total: {$montoTotal} → {$nuevoTotal}",
            $factId
        );
    }

    $ivaNota = nc_calcular_iva_nota($detalle, $notaTipo, $motivoId, $factId, $pdo);
    $stIva = $pdo->prepare('SELECT iva_exento, iva_5, iva_10 FROM iva_compra WHERE id_factura_compra = :f LIMIT 1');
    $stIva->execute([':f' => $factId]);
    $ivaAct = $stIva->fetch(PDO::FETCH_ASSOC) ?: ['iva_exento' => 0, 'iva_5' => 0, 'iva_10' => 0];

    if ($notaTipo === 'DEBITO') {
        $nuevoEx = max(0, (int)$ivaAct['iva_exento'] - (int)$ivaNota['iva_exento']);
        $nuevo5 = max(0, (int)$ivaAct['iva_5'] - (int)$ivaNota['iva_5']);
        $nuevo10 = max(0, (int)$ivaAct['iva_10'] - (int)$ivaNota['iva_10']);
    } else {
        $nuevoEx = (int)$ivaAct['iva_exento'] + (int)$ivaNota['iva_exento'];
        $nuevo5 = (int)$ivaAct['iva_5'] + (int)$ivaNota['iva_5'];
        $nuevo10 = (int)$ivaAct['iva_10'] + (int)$ivaNota['iva_10'];
    }

    $pdo->prepare('UPDATE iva_compra SET iva_exento = :e, iva_5 = :i5, iva_10 = :i10 WHERE id_factura_compra = :f')
        ->execute([':e' => $nuevoEx, ':i5' => $nuevo5, ':i10' => $nuevo10, ':f' => $factId]);

    if ($notaTipo === 'CREDITO' && $motivoId === 2) {
        $stFacDet = $pdo->prepare('SELECT id_materia_prima, fac_cantidad FROM factura_detalle_compra WHERE id_factura_compra = :f');
        $stFacDet->execute([':f' => $factId]);
        $facCant = [];
        foreach ($stFacDet->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $facCant[(int)$r['id_materia_prima']] = (int)$r['fac_cantidad'];
        }

        foreach ($detalle as $ln) {
            $mp = (int)$ln['id_materia_prima'];
            $cantNota = (int)$ln['nota_compra_cantidad'];
            $cantOrig = $facCant[$mp] ?? 0;
            $devuelta = max(0, $cantOrig - $cantNota);
            if ($devuelta <= 0) {
                continue;
            }

            $depId = (int)$pdo->query("SELECT deposito_id FROM stock_materia_prima WHERE id_materia_prima = {$mp} LIMIT 1")->fetchColumn();
            if ($depId <= 0) {
                $depId = (int)$pdo->query('SELECT deposito_id FROM deposito LIMIT 1')->fetchColumn() ?: 1;
            }

            $pdo->prepare('
                UPDATE stock_materia_prima
                SET cantidad_existente = cantidad_existente + :c
                WHERE id_materia_prima = :mp AND deposito_id = :d
            ')->execute([':c' => $devuelta, ':mp' => $mp, ':d' => $depId]);

            bitacora_registrar(
                $pdo,
                $usuarioId,
                'Nota de Crédito/Débito',
                'MODIFICACION',
                "Stock revertido +{$devuelta} unid. por anulación NC devolución | MP {$mp} | Nota #{$notaId}",
                $notaId
            );
        }
    }

    $facEstado = strtoupper(trim((string)$fac['fac_estado']));
    if ($esAnulacionTotal || $facEstado === 'ANULADO') {
        $facTotal = (int)$fac['fac_total'];
        $tipoOp = strtoupper(trim((string)($fac['tipo_operacion'] ?? 'CONTADO')));

        $pdo->prepare("UPDATE factura_compra SET fac_estado = 'EMITIDA' WHERE id_factura_compra = :f")
            ->execute([':f' => $factId]);

        $stSum = $pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN nota_compra_tipo = 'CREDITO' THEN nota_total ELSE 0 END), 0) AS nc,
                COALESCE(SUM(CASE WHEN nota_compra_tipo = 'DEBITO' THEN nota_total ELSE 0 END), 0) AS nd
            FROM nota_compra
            WHERE id_factura_compra = :f
              AND UPPER(nota_compra_estado) = 'EMITIDA'
              AND id_nota_compra <> :n
        ");
        $stSum->execute([':f' => $factId, ':n' => $notaId]);
        $sums = $stSum->fetch(PDO::FETCH_ASSOC);
        $netoCta = max(0, $facTotal - (int)($sums['nc'] ?? 0) + (int)($sums['nd'] ?? 0));

        if ($tipoOp === 'CONTADO') {
            $estadoCta = 'FINALIZADO';
            $pend = 0;
        } else {
            $estadoCta = 'PENDIENTE';
            $pend = $netoCta;
        }

        $pdo->prepare("
            UPDATE cuentas_pagar
            SET estado = :e, monto_total = :t, monto_pendiente = :p
            WHERE id_factura_compra = :f
        ")->execute([':e' => $estadoCta, ':t' => $netoCta, ':p' => $pend, ':f' => $factId]);

        $ocId = (int)($fac['id_orden_compra'] ?? 0);
        if ($ocId > 0) {
            $pdo->prepare("UPDATE orden_de_compra SET orden_estado = 'FACTURADA' WHERE id_orden_compra = :oc")
                ->execute([':oc' => $ocId]);
        }

        bitacora_registrar($pdo, $usuarioId, 'Gestionar compra / Factura', 'MODIFICACION', "Factura #{$factId} restaurada a EMITIDA por anulación Nota #{$notaId}", $factId);
    }

    $pdo->prepare("UPDATE nota_compra SET nota_compra_estado = 'ANULADO' WHERE id_nota_compra = :id")
        ->execute([':id' => $notaId]);

    bitacora_registrar(
        $pdo,
        $usuarioId,
        'Nota de Crédito/Débito',
        'ANULACION',
        "Nota {$notaTipo} #{$notaId} anulada | Factura #{$factId} | Monto revertido: {$montoNota}",
        $notaId
    );

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Nota anulada correctamente']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
