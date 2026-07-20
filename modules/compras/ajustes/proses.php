<?php
// Procesa altas, ediciones y anulaciones de ajustes de stock (?act=).
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();

if (empty($_SESSION['username'])) {
  http_response_code(401);
  die("Sesión expirada, vuelva a iniciar sesión.");
}

require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/permissions.php';
if (!check_permission('AJUSTES', false)) {
  http_response_code(403);
  die("Acceso denegado.");
}

$dsn = "pgsql:host=$host;port=$port;dbname=$database;";

if (!function_exists('bitacora')) {
  function bitacora(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void {
    $useSavepoint = false;
    try {
      if ($pdo->inTransaction()) {
        $useSavepoint = true;
        $pdo->exec('SAVEPOINT bitacora_sp');
      }
      $stmt = $pdo->prepare("
        INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
        VALUES (:usuario, :entidad, :registro, :accion, :descripcion)
      ");
      $stmt->execute([
        ':usuario'     => $idUsuario,
        ':entidad'     => 'Ajustes de inventario',
        ':registro'    => $idRegistro,
        ':accion'      => strtoupper($accion),
        ':descripcion' => $descripcion
      ]);
      if ($useSavepoint) {
        $pdo->exec('RELEASE SAVEPOINT bitacora_sp');
      }
    } catch (Throwable $e) {
      if ($useSavepoint) {
        try { $pdo->exec('ROLLBACK TO SAVEPOINT bitacora_sp'); } catch (Throwable $inner) {}
        try { $pdo->exec('RELEASE SAVEPOINT bitacora_sp'); } catch (Throwable $inner) {}
      }
      error_log("Bitácora ajustes falló: " . $e->getMessage());
    }
  }
}

if (!function_exists('inferTipoDesdeDescripcion')) {
  function inferTipoDesdeDescripcion(string $descripcion): string {
    $texto = strtoupper(trim($descripcion));
    $mapEntradas = ['SOBRANTE'];
    $mapSalidas  = ['FALTANTE', 'MERMA', 'PRODUCTO DAÑADO', 'PRODUCTO DANADO'];

    if (strpos($texto, 'REGULARIZACION') !== false || strpos($texto, 'REGULARIZACIÓN') !== false) {
      return 'SALIDA';
    }

    foreach ($mapEntradas as $needle) {
      if (strpos($texto, $needle) !== false) return 'ENTRADA';
    }
    foreach ($mapSalidas as $needle) {
      if (strpos($texto, $needle) !== false) return 'SALIDA';
    }
    return 'SALIDA';
  }
}

function ajustesTieneTipoMovimiento(PDO $pdo): bool {
  static $cache = null;
  if ($cache !== null) return $cache;
  try {
    $checkCol = $pdo->query("
      SELECT COUNT(*)
      FROM information_schema.columns
      WHERE table_schema = 'public'
        AND table_name = 'ajustes'
        AND column_name = 'tipo_movimiento'
    ");
    $cache = ($checkCol->fetchColumn() > 0);
  } catch (Throwable $e) {
    $cache = false;
  }
  return $cache;
}

function esMotivoRegularizacion(string $motivoDescripcion): bool {
  $texto = strtoupper(trim($motivoDescripcion));
  return strpos($texto, 'REGULARIZACION') !== false || strpos($texto, 'REGULARIZACIÓN') !== false;
}

function resolverTipoMovimiento(string $motivoDescripcion, ?string $tipoRegularizacion = null): string {
  if (esMotivoRegularizacion($motivoDescripcion)) {
    $tipo = strtoupper(trim((string)$tipoRegularizacion));
    return ($tipo === 'ENTRADA') ? 'ENTRADA' : 'SALIDA';
  }
  return inferTipoDesdeDescripcion($motivoDescripcion);
}

function factorDesdeTipo(string $tipoMovimiento): int {
  return strtoupper(trim($tipoMovimiento)) === 'ENTRADA' ? 1 : -1;
}

function obtenerTipoMovimientoAjuste(PDO $pdo, int $ajusteId, string $motivoDescripcion): string {
  if (ajustesTieneTipoMovimiento($pdo)) {
    $st = $pdo->prepare("SELECT tipo_movimiento FROM ajustes WHERE id_ajuste = :id LIMIT 1");
    $st->execute([':id' => $ajusteId]);
    $tipo = strtoupper(trim((string)$st->fetchColumn()));
    if ($tipo === 'ENTRADA' || $tipo === 'SALIDA') {
      return $tipo;
    }
  }
  return inferTipoDesdeDescripcion($motivoDescripcion);
}

/**
 * Aplica delta de stock. Si es ENTRADA y no existe fila, la crea.
 * @return int id_stock afectado
 */
function aplicarDeltaStock(
  PDO $pdo,
  int $idMateriaPrima,
  int $depositoId,
  int $cantidad,
  int $factor,
  int $idUsuario
): int {
  if ($cantidad <= 0) {
    throw new Exception("Cantidad de ajuste inválida.");
  }

  $selStock = $pdo->prepare("
    SELECT id_stock, cantidad_existente
    FROM stock_materia_prima
    WHERE id_materia_prima = :mp AND deposito_id = :dep
    FOR UPDATE
  ");
  $selStock->execute([':mp' => $idMateriaPrima, ':dep' => $depositoId]);
  $stockRow = $selStock->fetch();

  if (!$stockRow) {
    if ($factor < 0) {
      throw new Exception("No existe stock cargado para la materia prima $idMateriaPrima en el depósito seleccionado.");
    }
    $insStock = $pdo->prepare("
      INSERT INTO stock_materia_prima
        (id_materia_prima, deposito_id, cantidad_existente, stock_cantidad_minima, stock_cantidad_maxima, id_usuario)
      VALUES (:mp, :dep, :c, 0, 0, :u)
      RETURNING id_stock
    ");
    $insStock->execute([
      ':mp' => $idMateriaPrima,
      ':dep' => $depositoId,
      ':c' => $cantidad,
      ':u' => $idUsuario,
    ]);
    return (int)$insStock->fetchColumn();
  }

  $idStock = (int)$stockRow['id_stock'];
  $stockActual = (int)$stockRow['cantidad_existente'];
  $nuevoStock = $stockActual + ($cantidad * $factor);
  if ($nuevoStock < 0) {
    throw new Exception("Stock insuficiente para la materia prima $idMateriaPrima. Disponible: $stockActual, solicitado: $cantidad.");
  }

  $updStock = $pdo->prepare("UPDATE stock_materia_prima SET cantidad_existente = :cant WHERE id_stock = :id");
  $updStock->execute([':cant' => $nuevoStock, ':id' => $idStock]);
  return $idStock;
}

$accion = $_GET['act'] ?? '';

try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  error_log("Conexión fallida en ajustes: " . $e->getMessage());
  die("No se pudo conectar a la base de datos.");
}

try {
  if ($accion === 'insert') {
    insertarAjuste($pdo);
  } elseif ($accion === 'update') {
    actualizarAjuste($pdo);
  } elseif ($accion === 'anular') {
    anularAjuste($pdo);
  } else {
    http_response_code(400);
    echo "Acción inválida.";
  }
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  error_log("Ajustes error: " . $e->getMessage());
  $msg = urlencode($e->getMessage());
  header("Location: view.php?alert=4&msg={$msg}");
  exit;
}

function obtenerIdUsuario(PDO $pdo): int {
  $id = (int)($_SESSION['id_usuario'] ?? 0);
  if ($id > 0) {
    return $id;
  }
  $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :username LIMIT 1");
  $stmt->execute([':username' => $_SESSION['username']]);
  $id = (int)$stmt->fetchColumn();
  if ($id <= 0) {
    throw new Exception("No se pudo identificar al usuario en sesión.");
  }
  $_SESSION['id_usuario'] = $id;
  return $id;
}

function sanitizarItems(string $json): array {
  if ($json === '') {
    return [];
  }
  $items = json_decode($json, true);
  if (json_last_error() !== JSON_ERROR_NONE || !is_array($items)) {
    throw new Exception("Formato de detalle inválido.");
  }
  $limpios = [];
  foreach ($items as $item) {
    $mp = (int)($item['id_materia_prima'] ?? $item['id_producto'] ?? 0);
    $cant = (int)($item['cantidad'] ?? 0);
    if ($mp <= 0 || $cant <= 0) {
      throw new Exception("Los ítems contienen datos no válidos.");
    }
    $limpios[] = [
      'id_materia_prima' => $mp,
      'cantidad' => $cant,
    ];
  }
  return $limpios;
}

function insertarAjuste(PDO $pdo): void {
  $depositoId = (int)($_POST['deposito_id'] ?? 0);
  $motivoId   = (int)($_POST['ajuste_motivo_id'] ?? 0);
  $fechaIn    = trim($_POST['ajuste_fecha'] ?? '');
  $detalleRaw = $_POST['detalle_json'] ?? '[]';
  $tipoRegularizacion = trim($_POST['tipo_regularizacion'] ?? '');

  if ($depositoId <= 0) {
    throw new Exception("Debe seleccionar un depósito.");
  }
  if ($motivoId <= 0) {
    throw new Exception("Debe seleccionar un motivo.");
  }
  $fecha = DateTimeImmutable::createFromFormat('Y-m-d', $fechaIn) ?: new DateTimeImmutable('today');

  $items = sanitizarItems($detalleRaw);
  if (count($items) === 0) {
    throw new Exception("Debe agregar al menos un ítem al ajuste.");
  }

  $stmtMotivo = $pdo->prepare("SELECT motivo_descripcion FROM motivo WHERE id_motivo = :id");
  $stmtMotivo->execute([':id' => $motivoId]);
  $motivo = $stmtMotivo->fetch();
  if (!$motivo) {
    throw new Exception("Motivo de ajuste inválido.");
  }

  $tipoMovimiento = resolverTipoMovimiento($motivo['motivo_descripcion'], $tipoRegularizacion);
  $factor = factorDesdeTipo($tipoMovimiento);

  $idUsuario = obtenerIdUsuario($pdo);

  $pdo->beginTransaction();

  if (ajustesTieneTipoMovimiento($pdo)) {
    $stmtCab = $pdo->prepare("
      INSERT INTO ajustes (ajuste_fecha, ajuste_estado, id_usuario, deposito_id, tipo_movimiento)
      VALUES (:fecha, 'EMITIDO', :usuario, :deposito, :tipo)
      RETURNING id_ajuste
    ");
    $stmtCab->execute([
      ':fecha'   => $fecha->format('Y-m-d'),
      ':usuario' => $idUsuario,
      ':deposito'=> $depositoId,
      ':tipo'    => $tipoMovimiento,
    ]);
  } else {
    $stmtCab = $pdo->prepare("
      INSERT INTO ajustes (ajuste_fecha, ajuste_estado, id_usuario, deposito_id)
      VALUES (:fecha, 'EMITIDO', :usuario, :deposito)
      RETURNING id_ajuste
    ");
    $stmtCab->execute([
      ':fecha'   => $fecha->format('Y-m-d'),
      ':usuario' => $idUsuario,
      ':deposito'=> $depositoId,
    ]);
  }

  $idAjuste = (int)$stmtCab->fetchColumn();
  if ($idAjuste <= 0) {
    throw new Exception("No se pudo generar el encabezado del ajuste.");
  }

  $insDet = $pdo->prepare("
    INSERT INTO ajustes_detalle (id_ajuste, id_materia_prima, id_stock, ajuste_cantidad, id_motivo)
    VALUES (:ajuste, :materia_prima, :stock, :cantidad, :motivo)
  ");

  foreach ($items as $item) {
    $mpId = $item['id_materia_prima'];
    $cantidad = $item['cantidad'];

    $idStock = aplicarDeltaStock($pdo, $mpId, $depositoId, $cantidad, $factor, $idUsuario);

    $insDet->execute([
      ':ajuste'  => $idAjuste,
      ':materia_prima'=> $mpId,
      ':stock'   => $idStock,
      ':cantidad'=> $cantidad,
      ':motivo'  => $motivoId
    ]);

    bitacora($pdo, $idUsuario, 'MODIFICACION', "Stock materia prima:$mpId dep:$depositoId ajustado en ".($cantidad * $factor)." unidades (tipo:$tipoMovimiento)", $idAjuste);
  }

  bitacora(
    $pdo,
    $idUsuario,
    'ALTA',
    "Ajuste #$idAjuste generado. Motivo: {$motivo['motivo_descripcion']} | Tipo: $tipoMovimiento | Depósito: $depositoId",
    $idAjuste
  );

  $pdo->commit();
  header("Location: view.php?alert=1");
  exit;
}

function actualizarAjuste(PDO $pdo): void {
  $ajusteId = (int)($_POST['id_ajuste'] ?? 0);
  $depositoId = (int)($_POST['deposito_id'] ?? 0);
  $motivoId = (int)($_POST['ajuste_motivo_id'] ?? 0);
  $fechaIn = trim($_POST['ajuste_fecha'] ?? '');
  $detalleRaw = $_POST['detalle_json'] ?? '[]';
  $tipoRegularizacion = trim($_POST['tipo_regularizacion'] ?? '');

  if ($ajusteId <= 0) {
    throw new Exception("ID de ajuste inválido.");
  }
  if ($depositoId <= 0) {
    throw new Exception("Debe seleccionar un depósito.");
  }
  if ($motivoId <= 0) {
    throw new Exception("Debe seleccionar un motivo.");
  }
  $fecha = DateTimeImmutable::createFromFormat('Y-m-d', $fechaIn) ?: new DateTimeImmutable('today');

  $items = sanitizarItems($detalleRaw);
  if (count($items) === 0) {
    throw new Exception("Debe agregar al menos un ítem al ajuste.");
  }

  $idUsuario = obtenerIdUsuario($pdo);

  $pdo->beginTransaction();

  $stmtCab = $pdo->prepare("
    SELECT ajuste_estado, deposito_id
    FROM ajustes
    WHERE id_ajuste = :id
    FOR UPDATE
  ");
  $stmtCab->execute([':id' => $ajusteId]);
  $cabecera = $stmtCab->fetch();

  if (!$cabecera) {
    throw new Exception("Ajuste no encontrado.");
  }

  $estado = strtoupper(trim($cabecera['ajuste_estado']));
  if ($estado !== 'EMITIDO') {
    throw new Exception("Solo se pueden editar ajustes en estado EMITIDO.");
  }

  $stmtMotivo = $pdo->prepare("SELECT motivo_descripcion FROM motivo WHERE id_motivo = :id");
  $stmtMotivo->execute([':id' => $motivoId]);
  $motivo = $stmtMotivo->fetch();
  if (!$motivo) {
    throw new Exception("Motivo de ajuste inválido.");
  }

  $tipoMovimiento = resolverTipoMovimiento($motivo['motivo_descripcion'], $tipoRegularizacion);
  $factor = factorDesdeTipo($tipoMovimiento);

  $stmtDetActual = $pdo->prepare("
    SELECT id_materia_prima, id_stock, ajuste_cantidad
    FROM ajustes_detalle
    WHERE id_ajuste = :id
  ");
  $stmtDetActual->execute([':id' => $ajusteId]);
  $detalleActual = $stmtDetActual->fetchAll();
  $mapActual = [];
  foreach ($detalleActual as $det) {
    $mapActual[(int)$det['id_materia_prima']] = [
      'id_stock' => (int)$det['id_stock'],
      'cantidad' => (int)$det['ajuste_cantidad']
    ];
  }

  $stmtMotivoOrig = $pdo->prepare("SELECT DISTINCT id_motivo FROM ajustes_detalle WHERE id_ajuste = :id LIMIT 1");
  $stmtMotivoOrig->execute([':id' => $ajusteId]);
  $motivoIdOrig = (int)$stmtMotivoOrig->fetchColumn();
  if ($motivoIdOrig > 0) {
    $stmtMotivoDescOrig = $pdo->prepare("SELECT motivo_descripcion FROM motivo WHERE id_motivo = :id");
    $stmtMotivoDescOrig->execute([':id' => $motivoIdOrig]);
    $motivoOrig = $stmtMotivoDescOrig->fetch();
    if ($motivoOrig) {
      $tipoOriginal = obtenerTipoMovimientoAjuste($pdo, $ajusteId, $motivoOrig['motivo_descripcion']);
      $factorReversion = factorDesdeTipo($tipoOriginal) * -1;

      foreach ($mapActual as $mpId => $det) {
        aplicarDeltaStock($pdo, $mpId, (int)$cabecera['deposito_id'], $det['cantidad'], $factorReversion, $idUsuario);
      }
    }
  }

  $stmtDelDet = $pdo->prepare("DELETE FROM ajustes_detalle WHERE id_ajuste = :id");
  $stmtDelDet->execute([':id' => $ajusteId]);

  if (ajustesTieneTipoMovimiento($pdo)) {
    $stmtUpdCab = $pdo->prepare("
      UPDATE ajustes
      SET ajuste_fecha = :fecha, deposito_id = :deposito, tipo_movimiento = :tipo
      WHERE id_ajuste = :id
    ");
    $stmtUpdCab->execute([
      ':fecha' => $fecha->format('Y-m-d'),
      ':deposito' => $depositoId,
      ':tipo' => $tipoMovimiento,
      ':id' => $ajusteId
    ]);
  } else {
    $stmtUpdCab = $pdo->prepare("
      UPDATE ajustes
      SET ajuste_fecha = :fecha, deposito_id = :deposito
      WHERE id_ajuste = :id
    ");
    $stmtUpdCab->execute([
      ':fecha' => $fecha->format('Y-m-d'),
      ':deposito' => $depositoId,
      ':id' => $ajusteId
    ]);
  }

  $insDet = $pdo->prepare("
    INSERT INTO ajustes_detalle (id_ajuste, id_materia_prima, id_stock, ajuste_cantidad, id_motivo)
    VALUES (:ajuste, :materia_prima, :stock, :cantidad, :motivo)
  ");

  foreach ($items as $item) {
    $mpId = $item['id_materia_prima'];
    $cantidad = $item['cantidad'];

    $idStock = aplicarDeltaStock($pdo, $mpId, $depositoId, $cantidad, $factor, $idUsuario);

    $insDet->execute([
      ':ajuste' => $ajusteId,
      ':materia_prima' => $mpId,
      ':stock' => $idStock,
      ':cantidad' => $cantidad,
      ':motivo' => $motivoId
    ]);

    bitacora($pdo, $idUsuario, 'MODIFICACION', "Stock materia prima:$mpId dep:$depositoId ajustado en ".($cantidad * $factor)." unidades (tipo:$tipoMovimiento)", $ajusteId);
  }

  bitacora(
    $pdo,
    $idUsuario,
    'MODIFICACION',
    "Ajuste #$ajusteId editado. Motivo: {$motivo['motivo_descripcion']} | Tipo: $tipoMovimiento | Depósito: $depositoId",
    $ajusteId
  );

  $pdo->commit();
  header("Location: view.php?alert=2");
  exit;
}

function anularAjuste(PDO $pdo): void {
  $ajusteId = isset($_GET['ajuste_id']) ? (int)$_GET['ajuste_id'] : 0;
  if ($ajusteId <= 0) {
    throw new Exception("Identificador de ajuste inválido.");
  }

  $idUsuario = obtenerIdUsuario($pdo);

  $pdo->beginTransaction();

  $stmtCab = $pdo->prepare("
    SELECT ajuste_estado, deposito_id
    FROM ajustes
    WHERE id_ajuste = :id
    FOR UPDATE
  ");
  $stmtCab->execute([':id' => $ajusteId]);
  $cabecera = $stmtCab->fetch();
  if (!$cabecera) {
    throw new Exception("El ajuste indicado no existe.");
  }
  if (strtoupper($cabecera['ajuste_estado']) === 'ANULADO') {
    $pdo->rollBack();
    header("Location: view.php?alert=5");
    exit;
  }

  // Obtener motivo desde el detalle
  $stmtMotivo = $pdo->prepare("SELECT DISTINCT id_motivo FROM ajustes_detalle WHERE id_ajuste = :id LIMIT 1");
  $stmtMotivo->execute([':id' => $ajusteId]);
  $motivoId = (int)$stmtMotivo->fetchColumn();
  if ($motivoId <= 0) {
    throw new Exception("No se encontró el motivo del ajuste para revertir.");
  }
  $stmtMotivoDesc = $pdo->prepare("SELECT motivo_descripcion FROM motivo WHERE id_motivo = :id");
  $stmtMotivoDesc->execute([':id' => $motivoId]);
  $motivo = $stmtMotivoDesc->fetch();
  if (!$motivo) {
    throw new Exception("No se encontró la descripción del motivo del ajuste.");
  }
  $tipoOriginal = obtenerTipoMovimientoAjuste($pdo, $ajusteId, $motivo['motivo_descripcion']);
  $factorReversion = factorDesdeTipo($tipoOriginal) * -1;

  $stmtDet = $pdo->prepare("
    SELECT id_materia_prima, id_stock, ajuste_cantidad
    FROM ajustes_detalle
    WHERE id_ajuste = :id
    FOR UPDATE
  ");
  $stmtDet->execute([':id' => $ajusteId]);
  $detalles = $stmtDet->fetchAll();
  if (empty($detalles)) {
    throw new Exception("El ajuste no posee detalle para revertir.");
  }

  foreach ($detalles as $det) {
    $mpId = (int)$det['id_materia_prima'];
    $cant = (int)$det['ajuste_cantidad'];
    aplicarDeltaStock($pdo, $mpId, (int)$cabecera['deposito_id'], $cant, $factorReversion, $idUsuario);
    bitacora($pdo, $idUsuario, 'MODIFICACION', "Reversión stock materia prima:$mpId dep:{$cabecera['deposito_id']} ajuste#$ajusteId => ".($cant * $factorReversion)." unidades", $ajusteId);
  }

  $stmtAnular = $pdo->prepare("UPDATE ajustes SET ajuste_estado = 'ANULADO' WHERE id_ajuste = :id");
  $stmtAnular->execute([':id' => $ajusteId]);

    bitacora($pdo, $idUsuario, 'ANULACION', "Ajuste #$ajusteId anulado. Motivo original: {$motivo['motivo_descripcion']}.", $ajusteId);

  $pdo->commit();
  header("Location: view.php?alert=3");
  exit;
}
