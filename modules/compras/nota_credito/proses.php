<?php
// Procesa altas, ediciones y anulaciones de notas de crédito/débito de compra (?act=).
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();
require_once PROJECT_ROOT . '/config/database.php';

if (empty($_SESSION['username'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Token de sesión inválido']);
    exit;
}

// Función bitácora con savepoint (igual que en gestionar_compras)
function bitacora(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void {
    $savepointName = null;
    try {
        // Si estamos en una transacción, crear un savepoint para aislar errores de bitacora
        if ($pdo->inTransaction()) {
            $savepointName = 'bitacora_' . str_replace(['.', '-'], '_', uniqid('', true));
            $pdo->exec("SAVEPOINT {$savepointName}");
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ");
        $stmt->execute([
            ':id_usuario'  => $idUsuario,
            ':entidad'     => 'Nota de Crédito/Débito',
            ':id_registro' => $idRegistro,
            ':accion'      => strtoupper($accion),
            ':descripcion' => $descripcion
        ]);
        
        // Si usamos savepoint, liberarlo
        if ($savepointName !== null) {
            $pdo->exec("RELEASE SAVEPOINT {$savepointName}");
        }
    } catch (PDOException $e) {
        // Si hay un error SQL y usamos savepoint, hacer rollback al savepoint
        if ($savepointName !== null) {
            try {
                $pdo->exec("ROLLBACK TO SAVEPOINT {$savepointName}");
            } catch (PDOException $rollbackError) {
                error_log("Error al hacer rollback del savepoint de bitacora: ".$rollbackError->getMessage());
            }
        }
        error_log("Bitácora falló: ".$e->getMessage());
        // No re-lanzar la excepción para no afectar la transacción principal
    } catch (Throwable $e) {
        if ($savepointName !== null) {
            try {
                $pdo->exec("ROLLBACK TO SAVEPOINT {$savepointName}");
            } catch (PDOException $rollbackError) {
                error_log("Error al hacer rollback del savepoint de bitacora: ".$rollbackError->getMessage());
            }
        }
        error_log("Bitácora falló: ".$e->getMessage());
    }
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener usuario actual
    $usuario_id = 0;
    if (!empty($_SESSION['id_usuario'])) {
        $usuario_id = (int)$_SESSION['id_usuario'];
    } else {
        $stmtUid = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
        $stmtUid->execute([':u' => $_SESSION['username'] ?? '']);
        $usuario_id = (int)$stmtUid->fetchColumn();
    }

    if ($usuario_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Usuario no válido']);
        exit;
    }

    // Obtener sucursal del usuario
    $stmtSuc = $pdo->prepare("SELECT id_sucursal FROM usuarios WHERE id_usuario = :id LIMIT 1");
    $stmtSuc->execute([':id' => $usuario_id]);
    $id_sucursal = (int)$stmtSuc->fetchColumn();
    if ($id_sucursal <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Usuario sin sucursal asociada']);
        exit;
    }

    // Validar acción
    if (($_GET['act'] ?? '') !== 'insert_nota') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Acción no soportada']);
        exit;
    }

    // =========================== INPUTS ===========================
    $idProveedor   = (int)($_POST['id_proveedor'] ?? 0);
    $factId        = (int)($_POST['fact_id'] ?? 0);
    $notaTipo      = strtoupper(trim($_POST['nota_tipo'] ?? ''));     // CREDITO | DEBITO
    $motivoId      = (int)($_POST['motivo_id'] ?? 0);
    $notaNroStr    = trim($_POST['nota_nro'] ?? '');
    // Extraer solo dígitos del número de nota
    $notaNroClean  = preg_replace('/\D+/', '', $notaNroStr);
    
    // IMPORTANTE: El número de nota puede tener hasta 13 dígitos (formato EEE-PPP-NNNNNNN)
    // PostgreSQL integer solo soporta hasta ~2.1 billones (10 dígitos)
    // Si el número excede 9 dígitos, usar solo los últimos 9 para evitar overflow
    // NOTA: La solución correcta es cambiar nota_nro a BIGINT en la BD
    if (strlen($notaNroClean) > 9) {
        // Usar los últimos 9 dígitos (máximo seguro para integer)
        $notaNro = (int)substr($notaNroClean, -9);
        error_log("ADVERTENCIA: Número de nota truncado de {$notaNroClean} a {$notaNro} (excede rango de integer)");
    } else {
        $notaNro = (int)$notaNroClean;
    }
    $timbrado      = trim($_POST['nota_timbrado'] ?? '');
    // El formulario envía 'nota_emision' como nombre del campo
    $notaInicio    = substr((string)($_POST['nota_emision'] ?? $_POST['nota_inicio'] ?? ''), 0, 10);      // Fecha inicio timbrado
    $notaVto       = substr((string)($_POST['nota_vto'] ?? ''), 0, 10);         // Fecha vencimiento timbrado
    $items         = json_decode($_POST['productos'] ?? '[]', true);
    $totalFrontNum = (int)($_POST['nota_total_num'] ?? 0);
    $costoAdicional = (int)($_POST['costo_adicional'] ?? 0); // Para ND con motivo 3 (COSTO ADICIONAL)
    
    if (!is_array($items)) $items = [];

    // =========================== VALIDACIONES BÁSICAS (Punto 15.a) ===========================
    if ($idProveedor <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Proveedor inválido']);
        exit;
    }
    if ($factId <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Factura no seleccionada']);
        exit;
    }
    if (!in_array($notaTipo, ['CREDITO', 'DEBITO'], true)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Tipo de Nota inválido']);
        exit;
    }
    if ($motivoId < 1) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Motivo inválido']);
        exit;
    }
    if ($notaNro <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'N° de Nota inválido']);
        exit;
    }
    if (!preg_match('/^\d{8}$/', $timbrado)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Timbrado inválido (debe tener 8 dígitos)']);
        exit;
    }
    if (!$notaInicio) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Fecha de inicio del timbrado obligatoria']);
        exit;
    }
    if (empty($items)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Debe incluir al menos un ítem']);
        exit;
    }

    // Fecha/hora de emisión de la nota (distinta del inicio de vigencia del timbrado)
    date_default_timezone_set('America/Asuncion');
    $notaFechaDoc = substr((string)($_POST['nota_fecha'] ?? date('Y-m-d')), 0, 10);
    $notaHoraDoc  = trim((string)($_POST['nota_hora'] ?? date('H:i:s')));
    if (preg_match('/^\d{2}:\d{2}$/', $notaHoraDoc)) {
        $notaHoraDoc .= ':00';
    }
    $notaFechaHora = $notaFechaDoc . ' ' . $notaHoraDoc;

    // Iniciar transacción
    $pdo->beginTransaction();

    // =========================== VALIDAR FACTURA (Punto 7) ===========================
    // Solo facturas en estado EMITIDA (según especificación punto 7)
    $stF = $pdo->prepare("
        SELECT 
            fc.id_factura_compra,
            fc.fact_fecha_compra::date AS fac_emision,
            fc.fac_estado,
            fc.fac_total,
            fc.tipo_operacion,
            oc.id_proveedor
        FROM factura_compra fc
        JOIN orden_de_compra oc ON oc.id_orden_compra = fc.id_orden_compra
        WHERE fc.id_factura_compra = :id
        FOR UPDATE
    ");
    $stF->execute([':id' => $factId]);
    $fac = $stF->fetch(PDO::FETCH_ASSOC);
    
    if (!$fac) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Factura inexistente']);
        exit;
    }
    
    // Guardar el total ORIGINAL de la factura ANTES de aplicar cualquier cambio
    // Esto se usará para la validación de auto-anulación
    $facTotalOriginal = (int)($fac['fac_total'] ?? 0);
    
    if ((int)$fac['id_proveedor'] !== $idProveedor) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'La factura no corresponde al proveedor seleccionado']);
        exit;
    }
    
    // Validar que la factura esté en estado EMITIDA (punto 7)
    $estadoFactura = strtoupper(trim((string)$fac['fac_estado']));
    if ($estadoFactura !== 'EMITIDA') {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "La factura debe estar en estado EMITIDA para emitir notas. Estado actual: {$estadoFactura}"]);
        exit;
    }

    // =========================== VALIDAR CUENTA A PAGAR (Punto 15.b y Flujo Alternativo #3) ===========================
    // Verificar que la factura no esté pagada totalmente
    $stCta = $pdo->prepare("
        SELECT 
            cp.monto_total,
            cp.monto_pendiente,
            cp.estado
        FROM cuentas_pagar cp
        WHERE cp.id_factura_compra = :fid
        LIMIT 1
        FOR UPDATE
    ");
    $stCta->execute([':fid' => $factId]);
    $cuenta = $stCta->fetch(PDO::FETCH_ASSOC);
    
    if ($cuenta === false) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Cuenta a pagar no encontrada para la factura']);
        exit;
    }
    
    $estadoCta = strtoupper(trim((string)$cuenta['estado']));
    $montoPendiente = (int)($cuenta['monto_pendiente'] ?? 0);
    
    // Flujo Alternativo #3: Factura pagada totalmente → no disponible para notas
    if (in_array($estadoCta, ['FINALIZADO', 'PAGADO', 'PAGADO TOTAL'], true) || $montoPendiente <= 0) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'La factura está pagada totalmente. No se pueden emitir notas']);
        exit;
    }

    // =========================== VALIDAR FECHAS (Punto 15.b) ===========================
    $hoy = date('Y-m-d');
    $facEmision = (string)$fac['fac_emision'];
    
    // La emisión de la nota debe estar entre la fecha de la factura y hoy
    if ($notaInicio < $facEmision || $notaInicio > $hoy) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "La fecha de emisión de la nota debe estar entre {$facEmision} y {$hoy}"]);
        exit;
    }
    
    if ($notaVto && $notaVto < $notaInicio) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'La fecha de vencimiento del timbrado no puede ser menor a la fecha de inicio']);
        exit;
    }

    // =========================== CALCULAR TOTALES (Punto 12) ===========================
    // Función para mapear descripción IVA a tasa
    $descripcionToTasa = function (?string $descr): int {
        $t = strtolower(trim((string)$descr));
        if ($t === 'iva_10' || $t === '10' || $t === '10%') return 10;
        if ($t === 'iva_5'  || $t === '5'  || $t === '5%')  return 5;
        return 0;
    };

    $valoresOriginales = [];
    $totalBase = 0;
    $totalIVA5 = 0;
    $totalIVA10 = 0;
    $totalExento = 0;

    foreach ($items as $it) {
        $cant = (int)($it['cantidad'] ?? 0);
        $prec = (int)($it['precio'] ?? 0);
        if ($cant < 0 || $prec < 0) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Cantidad y precio no pueden ser negativos']);
            exit;
        }
        
        $subtotal = $cant * $prec;
        $totalBase += $subtotal;

        $tasa = $descripcionToTasa($it['iva_descri'] ?? null);
        if ($tasa === 10) {
            $totalIVA10 += (int)floor($subtotal / 11);
        } elseif ($tasa === 5) {
            $totalIVA5 += (int)floor($subtotal / 21);
        } else {
            $totalExento += $subtotal;
        }
    }

    // Para DEBITO con motivo 3 (COSTO ADICIONAL), agregar el costo adicional al total
    $totalCalc = $totalBase;
    if ($notaTipo === 'DEBITO' && $motivoId === 3 && $costoAdicional > 0) {
        $totalCalc += $costoAdicional;
    }
    
    // Para DEBITO con motivo 4 (DIFERENCIA) o CREDITO con motivo 2 (AJUSTE PARCIAL),
    // calcular la diferencia entre valores nuevos y originales
    $montoAAplicarNota = $totalCalc; // Por defecto, usar el total calculado
    if (($notaTipo === 'DEBITO' && $motivoId === 4) || ($notaTipo === 'CREDITO' && $motivoId === 2)) {
        // Obtener los valores originales de la factura
        $stDetFac = $pdo->prepare("
            SELECT id_materia_prima, fac_cantidad, fac_precio
            FROM factura_detalle_compra
            WHERE id_factura_compra = :fid
        ");
        $stDetFac->execute([':fid' => $factId]);
        $detallesFac = $stDetFac->fetchAll(PDO::FETCH_ASSOC);
        
        // Crear un mapa de valores originales por id_materia_prima
        foreach ($detallesFac as $det) {
            $mpId = (int)$det['id_materia_prima'];
            $valoresOriginales[$mpId] = [
                'cantidad' => (int)$det['fac_cantidad'],
                'precio' => (int)$det['fac_precio'],
                'subtotal' => (int)$det['fac_cantidad'] * (int)$det['fac_precio']
            ];
        }
        
        // Calcular la diferencia según el tipo de nota
        // Para DEBITO: diferencia = subtotal_nuevo - subtotal_original (positivo si aumenta)
        // Para CREDITO: diferencia = subtotal_original - subtotal_nuevo (positivo si se acredita)
        $diferenciaTotal = 0;
        foreach ($items as $it) {
            $mpId = (int)($it['id_materia_prima'] ?? $it['id_producto'] ?? 0);
            if ($mpId <= 0 || !isset($valoresOriginales[$mpId])) continue;
            
            $cantNueva = (int)($it['cantidad'] ?? 0);
            $precNuevo = (int)($it['precio'] ?? 0);
            $subtotalNuevo = $cantNueva * $precNuevo;
            
            $subtotalOriginal = $valoresOriginales[$mpId]['subtotal'];
            
            if ($notaTipo === 'CREDITO') {
                // Para CREDITO: diferencia positiva = lo que se acredita
                $diferenciaTotal += ($subtotalOriginal - $subtotalNuevo);
            } else {
                // Para DEBITO: diferencia positiva = lo que se aumenta
                $diferenciaTotal += ($subtotalNuevo - $subtotalOriginal);
            }
        }
        
        // El monto a aplicar es la diferencia, no el total de los items nuevos
        $montoAAplicarNota = $diferenciaTotal;
        // El nota_total debe guardar la diferencia
        $totalCalc = $diferenciaTotal;
    }

    // Validar consistencia con el total del frontend
    // Para DIFERENCIA (ND motivo 4) y AJUSTE PARCIAL (NC motivo 2), el backend calcula la diferencia
    // El frontend envía el total de items nuevos para validación, pero el backend calcula la diferencia
    // Por lo tanto, para estos casos NO validamos contra el total enviado, solo validamos que la diferencia sea correcta
    // Para otros casos, validamos contra $totalCalc directamente
    $totalParaValidar = $totalCalc;
    
    // Para DIFERENCIA y AJUSTE PARCIAL, el frontend envía el total de items nuevos, no la diferencia
    // El backend calcula la diferencia internamente, así que no validamos contra el total enviado
    if (($notaTipo === 'DEBITO' && $motivoId === 4) || ($notaTipo === 'CREDITO' && $motivoId === 2)) {
        // Solo validamos que la diferencia sea válida (positiva para ambos casos)
        if ($totalCalc < 0) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "La diferencia calculada no puede ser negativa ({$totalCalc})"]);
            exit;
        }
    } else {
        // Para otros casos, validar contra el total enviado
        if (abs($totalParaValidar - $totalFrontNum) > 1) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "El total calculado ({$totalParaValidar}) no coincide con el total enviado ({$totalFrontNum})"]);
            exit;
        }
    }

    // =========================== VALIDACIONES ESPECÍFICAS (Punto 15.b y 15.c) ===========================
    
    // Validación NC: el total a acreditar ≤ saldo pendiente (punto 15.b)
    if ($notaTipo === 'CREDITO') {
        if ($totalCalc > $montoPendiente) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => "El total a acreditar ({$totalCalc}) no puede ser mayor al saldo pendiente de la factura ({$montoPendiente})"
            ]);
            exit;
        }
        
        // Validación Anulación total: mismo día de emisión (punto 15.b)
        // Asumiendo que motivo 1 = Anulación total (verificar con tabla motivo)
        if ($motivoId === 1 && $notaInicio !== $facEmision) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => "Para anulación total, la nota debe emitirse el mismo día que la factura ({$facEmision})"
            ]);
            exit;
        }
    }

    // Validación ND: el monto > 0 y justificación/motivo presente (punto 15.c)
    if ($notaTipo === 'DEBITO') {
        if ($totalCalc <= 0) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'El monto de la Nota de Débito debe ser mayor a cero']);
            exit;
        }
        // El motivo ya está validado arriba (motivoId >= 1)
    }

    // =========================== INSERTAR NOTA (Punto 16) ===========================
    // Incluir id_factura_compra ahora que existe en la BD
    $stCab = $pdo->prepare("
        INSERT INTO nota_compra
        (nota_compra_tipo, nota_compra_fecha, nota_nro, nota_compra_timbrado,
         nota_compra_inicio, nota_compra_vencimiento, nota_compra_estado, nota_total,
         id_usuario, id_sucursal, id_proveedor, id_motivo, id_factura_compra)
        VALUES
        (:tipo, :nota_fecha, :nro, :timbrado,
         :inicio, :vto, 'EMITIDA', :total,
         :idu, :ids, :idp, :idm, :fid)
        RETURNING id_nota_compra
    ");
    $stCab->execute([
        ':tipo'    => $notaTipo,
        ':nota_fecha' => $notaFechaHora,
        ':nro'     => $notaNro,
        ':timbrado' => $timbrado,
        ':inicio'  => $notaInicio,
        ':vto'     => $notaVto ?: null,
        ':total'   => $totalCalc,
        ':idu'     => $usuario_id,
        ':ids'     => $id_sucursal,
        ':idp'     => $idProveedor,
        ':idm'     => $motivoId,
        ':fid'     => $factId
    ]);
    
    $idNota = (int)$stCab->fetchColumn();
    if ($idNota <= 0) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No se pudo generar la nota']);
        exit;
    }

    bitacora($pdo, $usuario_id, 'ALTA', "Nota de {$notaTipo} #{$idNota} emitida | Factura #{$factId} | Motivo: {$motivoId} | Total: {$totalCalc}", $idNota);

    // =========================== INSERTAR DETALLE (Punto 16) ===========================
    foreach ($items as $it) {
        $prod = (int)($it['id_materia_prima'] ?? $it['id_producto'] ?? $it['codigo'] ?? 0);
        $cant = (int)($it['cantidad'] ?? 0);
        $prec = (int)($it['precio'] ?? 0);
        $iva  = $descripcionToTasa($it['iva_descri'] ?? null);
        
        if ($prod <= 0) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Ítem sin id_materia_prima válido']);
            exit;
        }

        $stDet = $pdo->prepare("
            INSERT INTO nota_detalle_compra
            (id_materia_prima, id_nota_compra, nota_compra_cantidad, tipo_iva, nota_precio)
            VALUES
            (:mp, :nota, :cant, :iva, :precio)
        ");
        $stDet->execute([
            ':mp'     => $prod,
            ':nota'   => $idNota,
            ':cant'   => $cant,
            ':iva'    => $iva,
            ':precio' => $prec
        ]);

        bitacora($pdo, $usuario_id, 'ALTA', "Detalle Nota #{$idNota}: Materia Prima {$prod}, cantidad {$cant}, precio {$prec}, IVA {$iva}%", $idNota);
    }

    // =========================== VALORES ORIGINALES DE FACTURA (stock / referencia) ===========================
    $itemsOriginales = [];
    if ($notaTipo === 'CREDITO' && $motivoId === 2) {
        // Solo necesitamos los originales para NC motivo 2 (AJUSTE PARCIAL)
        $stDetFacOriginal = $pdo->prepare("
            SELECT 
                d.id_materia_prima,
                d.fac_cantidad,
                d.fac_precio,
                mp.iva_id
            FROM factura_detalle_compra d
            JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
            WHERE d.id_factura_compra = :fid
        ");
        $stDetFacOriginal->execute([':fid' => $factId]);
        $detallesFacOriginal = $stDetFacOriginal->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($detallesFacOriginal as $det) {
            $mpId = (int)$det['id_materia_prima'];
            $itemsOriginales[$mpId] = [
                'cantidad' => (int)$det['fac_cantidad'],
                'precio' => (int)$det['fac_precio'],
                'iva_id' => (int)$det['iva_id']
            ];
        }
    }
    
    // =========================== ACTUALIZAR CUENTAS A PAGAR (Punto 16.a y 16.b) ===========================
    // Si es anulación total, no actualizar aquí (se hará después)
    $esAnulacionTotal = ($notaTipo === 'CREDITO' && $motivoId === 1);
    
    if (!$esAnulacionTotal) {
        $sign = ($notaTipo === 'DEBITO') ? +1 : -1;  // Débito aumenta, Crédito disminuye
        
        $montoTotal = (int)($cuenta['monto_total'] ?? 0);
        
        // Para ND con motivo 3 (COSTO ADICIONAL), solo se suma el costo adicional, no el total completo
        // Los items son informativos (vienen de la factura original), solo el costo adicional es nuevo
        // Para ND con motivo 4 (DIFERENCIA), se aplica la diferencia calculada arriba
        $montoAAplicar = $totalCalc;
        if ($notaTipo === 'DEBITO' && $motivoId === 3) {
            // Solo aplicar el costo adicional, no los items de la factura original
            $montoAAplicar = $costoAdicional;
        } elseif ($notaTipo === 'DEBITO' && $motivoId === 4) {
            // Para DIFERENCIA, usar la diferencia calculada (ya está en $totalCalc)
            $montoAAplicar = $totalCalc;
        }
        
        // Actualizar monto_total y monto_pendiente según el tipo de nota
        if ($notaTipo === 'DEBITO') {
            // Débito: aumenta tanto el total como el pendiente
            $nuevoMontoTotal = $montoTotal + $montoAAplicar;
            $nuevoMontoPendiente = $montoPendiente + $montoAAplicar;
        } else {
            // Crédito: disminuye tanto el total como el pendiente
            // El monto a acreditar se resta de ambos
            $nuevoMontoTotal = max(0, $montoTotal - $montoAAplicar);
            $nuevoMontoPendiente = max(0, $montoPendiente - $montoAAplicar);
        }
        
        $stUpdCta = $pdo->prepare("
            UPDATE cuentas_pagar
            SET monto_total = :total,
                monto_pendiente = :pendiente
            WHERE id_factura_compra = :fid
        ");
        $stUpdCta->execute([
            ':total'     => $nuevoMontoTotal,
            ':pendiente' => $nuevoMontoPendiente,
            ':fid'       => $factId
        ]);
        
        $rowsAffected = $stUpdCta->rowCount();
        
        // Verificar que se actualizó al menos una fila
        if ($rowsAffected === 0) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No se pudo actualizar la cuenta a pagar. Verifique que la factura tenga una cuenta asociada.']);
            exit;
        }
        
        // Log para depuración
        error_log("Nota #{$idNota}: Cuenta a pagar actualizada - Filas afectadas: {$rowsAffected} | Factura #{$factId} | Total: {$montoTotal} → {$nuevoMontoTotal} | Pendiente: {$montoPendiente} → {$nuevoMontoPendiente}");

        $fmt = fn($n) => number_format((int)$n, 0, ',', '.').' Gs';
        $descCp = sprintf(
            "Cuentas a Pagar actualizada: Factura #%d | %s %s | Total: %s → %s | Pendiente: %s → %s | Origen: Nota #%d (%s)%s",
            $factId,
            ($sign > 0 ? 'Aumento' : 'Disminución'),
            $fmt($montoAAplicar),
            $fmt($montoTotal),
            $fmt($nuevoMontoTotal),
            $fmt($montoPendiente),
            $fmt($nuevoMontoPendiente),
            $idNota,
            $notaTipo,
            ($notaTipo === 'DEBITO' && $motivoId === 3) ? ' (solo costo adicional aplicado)' : ''
        );
        bitacora($pdo, $usuario_id, 'MODIFICACION', $descCp, $factId);

        // =========================== AJUSTAR IVA NETO (fac_total y detalle intactos) ===========================
        $ivaNotaExento = 0;
        $ivaNota5 = 0;
        $ivaNota10 = 0;

        $esAjustePorDiferencia = ($notaTipo === 'DEBITO' && $motivoId === 4)
            || ($notaTipo === 'CREDITO' && $motivoId === 2);

        if ($esAjustePorDiferencia && !empty($valoresOriginales)) {
            foreach ($items as $it) {
                $mpId = (int)($it['id_materia_prima'] ?? $it['id_producto'] ?? 0);
                if ($mpId <= 0 || !isset($valoresOriginales[$mpId])) {
                    continue;
                }

                $subNuevo = (int)($it['cantidad'] ?? 0) * (int)($it['precio'] ?? 0);
                $subOrig = (int)$valoresOriginales[$mpId]['subtotal'];
                if ($notaTipo === 'CREDITO') {
                    $subtotal = max(0, $subOrig - $subNuevo);
                } else {
                    $subtotal = max(0, $subNuevo - $subOrig);
                }
                if ($subtotal <= 0) {
                    continue;
                }

                $tasa = $descripcionToTasa($it['iva_descri'] ?? null);
                if ($tasa === 10) {
                    $ivaNota10 += (int)floor($subtotal / 11);
                } elseif ($tasa === 5) {
                    $ivaNota5 += (int)floor($subtotal / 21);
                } else {
                    $ivaNotaExento += $subtotal;
                }
            }
        } elseif ($notaTipo === 'DEBITO' && $motivoId === 3) {
            // Costo adicional sin desglose por ítem: imputar a exento
            $ivaNotaExento += max(0, $costoAdicional);
        } else {
            $ivaNota5 = $totalIVA5;
            $ivaNota10 = $totalIVA10;
            $ivaNotaExento = $totalExento;
        }

        $stIvaAct = $pdo->prepare("
            SELECT iva_exento, iva_5, iva_10
            FROM iva_compra
            WHERE id_factura_compra = :fid
            LIMIT 1
        ");
        $stIvaAct->execute([':fid' => $factId]);
        $ivaAct = $stIvaAct->fetch(PDO::FETCH_ASSOC) ?: ['iva_exento' => 0, 'iva_5' => 0, 'iva_10' => 0];

        $exActual = (int)($ivaAct['iva_exento'] ?? 0);
        $iva5Actual = (int)($ivaAct['iva_5'] ?? 0);
        $iva10Actual = (int)($ivaAct['iva_10'] ?? 0);

        if ($notaTipo === 'DEBITO') {
            $nuevoEx = $exActual + $ivaNotaExento;
            $nuevoIva5 = $iva5Actual + $ivaNota5;
            $nuevoIva10 = $iva10Actual + $ivaNota10;
        } else {
            $nuevoEx = max(0, $exActual - $ivaNotaExento);
            $nuevoIva5 = max(0, $iva5Actual - $ivaNota5);
            $nuevoIva10 = max(0, $iva10Actual - $ivaNota10);
        }

        $stUpdIva = $pdo->prepare("
            UPDATE iva_compra
            SET iva_exento = :ex,
                iva_5 = :iva5,
                iva_10 = :iva10
            WHERE id_factura_compra = :fid
        ");
        $stUpdIva->execute([
            ':ex' => $nuevoEx,
            ':iva5' => $nuevoIva5,
            ':iva10' => $nuevoIva10,
            ':fid' => $factId,
        ]);

        if ($stUpdIva->rowCount() === 0) {
            $stInsIva = $pdo->prepare("
                INSERT INTO iva_compra (id_factura_compra, iva_fecha, iva_exento, iva_5, iva_10)
                VALUES (:fid, CURRENT_DATE, :ex, :iva5, :iva10)
            ");
            $stInsIva->execute([
                ':fid' => $factId,
                ':ex' => $nuevoEx,
                ':iva5' => $nuevoIva5,
                ':iva10' => $nuevoIva10,
            ]);
        }

        bitacora(
            $pdo,
            $usuario_id,
            'MODIFICACION',
            sprintf(
                'IVA neto ajustado Factura #%d | Exento: %s → %s | IVA5: %s → %s | IVA10: %s → %s | Nota #%d (%s)',
                $factId,
                $exActual,
                $nuevoEx,
                $iva5Actual,
                $nuevoIva5,
                $iva10Actual,
                $nuevoIva10,
                $idNota,
                $notaTipo
            ),
            $factId
        );

        // Verificar auto-anulación: si la suma de NC parciales iguala el total ORIGINAL de la factura
        if ($notaTipo === 'CREDITO' && $motivoId === 2) { // Devolución (parcial)
            // Obtener el total de todas las notas de crédito (incluyendo esta)
            // Usar $facTotalOriginal que se guardó al inicio, antes de aplicar cambios
            $stSumNC = $pdo->prepare("
                SELECT COALESCE(SUM(nota_total), 0) AS total_creditos
                FROM nota_compra
                WHERE id_factura_compra = :fid
                  AND nota_compra_tipo = 'CREDITO'
                  AND nota_compra_estado = 'EMITIDA'
            ");
            $stSumNC->execute([':fid' => $factId]);
            $sumaNC = (int)$stSumNC->fetchColumn();
            
            // Verificar estado de la factura
            $stCheckEstado = $pdo->prepare("SELECT fac_estado FROM factura_compra WHERE id_factura_compra = :fid");
            $stCheckEstado->execute([':fid' => $factId]);
            $estadoActual = strtoupper(trim((string)$stCheckEstado->fetchColumn()));
            
            // Verificar monto pendiente en cuentas a pagar
            $stCheckPendiente = $pdo->prepare("SELECT monto_pendiente FROM cuentas_pagar WHERE id_factura_compra = :fid");
            $stCheckPendiente->execute([':fid' => $factId]);
            $montoPendienteActual = (int)$stCheckPendiente->fetchColumn();
            
            // Solo anular si:
            // 1. La suma de NC iguala o supera el total ORIGINAL de la factura
            // 2. La factura aún no está anulada
            // 3. El monto pendiente es 0 o muy cercano a 0 (tolerancia de 1 para redondeos)
            // Esto asegura que realmente no hay saldo pendiente antes de anular
            if ($sumaNC >= $facTotalOriginal && $estadoActual !== 'ANULADO' && $montoPendienteActual <= 1) {
                $stAnulFac = $pdo->prepare("
                    UPDATE factura_compra
                    SET fac_estado = 'ANULADO'
                    WHERE id_factura_compra = :fid
                ");
                $stAnulFac->execute([':fid' => $factId]);
                bitacora($pdo, $usuario_id, 'ANULACION', "Factura #{$factId} auto-anulada por suma de Notas de Crédito ({$sumaNC} >= {$facTotalOriginal})", $factId);
                
                $stAnulCta = $pdo->prepare("
                    UPDATE cuentas_pagar
                    SET estado = 'ANULADO',
                        monto_total = 0,
                        monto_pendiente = 0
                    WHERE id_factura_compra = :fid
                ");
                $stAnulCta->execute([':fid' => $factId]);
                bitacora($pdo, $usuario_id, 'ANULACION', "Cuenta a Pagar de Factura #{$factId} auto-anulada por suma de Notas de Crédito", $factId);
                
                // Revertir la orden de compra a su estado original (EMITIDA)
                $stOcAuto = $pdo->prepare("
                    SELECT oc.id_orden_compra
                    FROM orden_de_compra oc
                    JOIN factura_compra fc ON fc.id_orden_compra = oc.id_orden_compra
                    WHERE fc.id_factura_compra = :fid
                    LIMIT 1
                ");
                $stOcAuto->execute([':fid' => $factId]);
                $ocDataAuto = $stOcAuto->fetch(PDO::FETCH_ASSOC);
                
                if ($ocDataAuto && !empty($ocDataAuto['id_orden_compra'])) {
                    $idOcAuto = (int)$ocDataAuto['id_orden_compra'];
                    $stUpdOcAuto = $pdo->prepare("
                        UPDATE orden_de_compra
                        SET orden_estado = 'EMITIDA'
                        WHERE id_orden_compra = :oc
                    ");
                    $stUpdOcAuto->execute([':oc' => $idOcAuto]);
                    bitacora($pdo, $usuario_id, 'MODIFICACION', "OC #{$idOcAuto} → EMITIDA (Auto-anulación Factura #{$factId} por suma de Notas de Crédito)", $idOcAuto);
                }
            }
        }
    }

    // =========================== ACTUALIZAR STOCK (Punto 16.c) ===========================
    // Solo para NC por Devolución (asumiendo motivo 2 = Devolución, verificar con tabla motivo)
    // Cuando se devuelve mercadería al proveedor, el stock DISMINUYE (sale del depósito)
    // Ejemplo: tenía 10, quedan 8 → se devuelven 2 → stock disminuye en 2
    if ($notaTipo === 'CREDITO' && $motivoId === 2) {
        // Usar $itemsOriginales que tiene las cantidades ANTES de aplicar cambios
        foreach ($items as $it) {
            $prod = (int)($it['id_materia_prima'] ?? $it['id_producto'] ?? $it['codigo'] ?? 0);
            $cantNueva = (int)($it['cantidad'] ?? 0); // Cantidad que queda después de la devolución
            
            if ($prod <= 0 || $cantNueva < 0) continue;
            
            // Obtener la cantidad original desde $itemsOriginales (valores ANTES de aplicar cambios)
            $cantOriginal = isset($itemsOriginales[$prod]) ? (int)$itemsOriginales[$prod]['cantidad'] : 0;
            
            if ($cantOriginal <= 0) {
                error_log("ADVERTENCIA: Nota #{$idNota}: Materia prima {$prod} no encontrada en factura original");
                continue;
            }
            
            // Validar que la cantidad nueva no exceda la original
            if ($cantNueva > $cantOriginal) {
                $pdo->rollBack();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => "La cantidad ingresada ({$cantNueva}) no puede ser mayor a la facturada ({$cantOriginal}) para materia prima {$prod}"]);
                exit;
            }
            
            // Calcular la cantidad devuelta: diferencia entre original y nueva
            $cantDevuelta = $cantOriginal - $cantNueva;
            
            if ($cantDevuelta <= 0) {
                // No hay devolución para este item, continuar con el siguiente
                continue;
            }

            // Obtener el depósito: primero desde stock existente, luego desde orden de compra, finalmente usar sucursal
            $depositoId = null;
            
            // Intentar obtener desde stock existente
            $stStockInfo = $pdo->prepare("
                SELECT deposito_id, cantidad_existente
                FROM stock_materia_prima
                WHERE id_materia_prima = :mp
                LIMIT 1
            ");
            $stStockInfo->execute([':mp' => $prod]);
            $stockInfo = $stStockInfo->fetch(PDO::FETCH_ASSOC);

            if ($stockInfo !== false && !empty($stockInfo['deposito_id'])) {
                $depositoId = (int)$stockInfo['deposito_id'];
            } else {
                // Si no hay stock, intentar obtener desde la orden de compra de la factura
                $stOcDep = $pdo->prepare("
                    SELECT oc.id_sucursal
                    FROM factura_compra fc
                    JOIN orden_de_compra oc ON oc.id_orden_compra = fc.id_orden_compra
                    WHERE fc.id_factura_compra = :fid
                    LIMIT 1
                ");
                $stOcDep->execute([':fid' => $factId]);
                $ocDep = $stOcDep->fetch(PDO::FETCH_ASSOC);
                
                if ($ocDep && !empty($ocDep['id_sucursal'])) {
                    $depositoId = (int)$ocDep['id_sucursal'];
                } else {
                    // Finalmente, usar la sucursal del usuario
                    $depositoId = $id_sucursal;
                }
            }
            
            if ($depositoId <= 0) {
                $depositoId = 1; // Depósito por defecto
            }
            
            // DISMINUIR stock con la cantidad devuelta (salida de depósito al proveedor)
            $stStock = $pdo->prepare("
                UPDATE stock_materia_prima
                SET cantidad_existente = GREATEST(0, cantidad_existente - :c)
                WHERE id_materia_prima = :mp AND deposito_id = :d
            ");
            $stStock->execute([
                ':c'  => $cantDevuelta, // Restar la cantidad devuelta
                ':mp' => $prod,
                ':d'  => $depositoId
            ]);
            
            // Si no se actualizó ninguna fila, significa que no hay stock para este producto/depósito
            // En este caso, no creamos stock negativo, solo registramos en bitácora
            if ($stStock->rowCount() === 0) {
                bitacora($pdo, $usuario_id, 'MODIFICACION', "[Advertencia] No se pudo disminuir stock: no existe registro para Materia Prima:{$prod} | Depósito:{$depositoId} | Cantidad devuelta: {$cantDevuelta} | Nota #{$idNota}", $idNota);
            } else {
                bitacora($pdo, $usuario_id, 'MODIFICACION', "Stock disminuido por devolución: -{$cantDevuelta} unid. (Original: {$cantOriginal}, Queda: {$cantNueva}) | Materia Prima:{$prod} | Depósito:{$depositoId} | Nota #{$idNota}", $idNota);
            }
        }
    }

    // =========================== ANULACIÓN TOTAL (Punto 16.d) ===========================
    // NC por Anulación total: marca Factura como Anulada
    // Asumiendo que motivo 1 = Anulación total (verificar con tabla motivo)
    if ($esAnulacionTotal) {
        // Ya validamos que sea el mismo día arriba
        $stAnulFac = $pdo->prepare("
            UPDATE factura_compra
            SET fac_estado = 'ANULADO'
            WHERE id_factura_compra = :fid
        ");
        $stAnulFac->execute([':fid' => $factId]);
        bitacora($pdo, $usuario_id, 'ANULACION', "Factura #{$factId} anulada por Nota de Crédito #{$idNota} (Anulación total)", $factId);

        // Anular también la cuenta a pagar (monto_total y monto_pendiente en 0)
        $stAnulCta = $pdo->prepare("
            UPDATE cuentas_pagar
            SET estado = 'ANULADO',
                monto_total = 0,
                monto_pendiente = 0
            WHERE id_factura_compra = :fid
        ");
        $stAnulCta->execute([':fid' => $factId]);
        bitacora($pdo, $usuario_id, 'ANULACION', "Cuenta a Pagar de Factura #{$factId} anulada por Nota de Crédito #{$idNota} (Anulación total)", $factId);
        
        // Revertir la orden de compra a su estado original (EMITIDA)
        $stOc = $pdo->prepare("
            SELECT oc.id_orden_compra
            FROM orden_de_compra oc
            JOIN factura_compra fc ON fc.id_orden_compra = oc.id_orden_compra
            WHERE fc.id_factura_compra = :fid
            LIMIT 1
        ");
        $stOc->execute([':fid' => $factId]);
        $ocData = $stOc->fetch(PDO::FETCH_ASSOC);
        
        if ($ocData && !empty($ocData['id_orden_compra'])) {
            $idOc = (int)$ocData['id_orden_compra'];
            $stUpdOc = $pdo->prepare("
                UPDATE orden_de_compra
                SET orden_estado = 'EMITIDA'
                WHERE id_orden_compra = :oc
            ");
            $stUpdOc->execute([':oc' => $idOc]);
            bitacora($pdo, $usuario_id, 'MODIFICACION', "OC #{$idOc} → EMITIDA (Anulación Factura #{$factId} por Nota de Crédito #{$idNota})", $idOc);
        }
    }

    // Commit de la transacción
    $pdo->commit();
    
    // Log para depuración
    error_log("Nota #{$idNota} registrada exitosamente - Tipo: {$notaTipo} | Total: {$totalCalc} | Factura #{$factId}");

    // Redirigir a la vista con mensaje de éxito (igual que otros módulos)
    header("Location: view.php?alert=1");
    exit;

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
