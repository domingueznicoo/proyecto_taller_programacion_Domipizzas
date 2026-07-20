<?php
// AJAX: stock productos de ajustes de stock.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['deposito_id']) || empty($_GET['deposito_id'])) {
  echo json_encode(['error' => 'Depósito no especificado']);
  exit;
}

$depositoId = (int)$_GET['deposito_id'];

try {
  $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // Todas las materias primas activas; stock 0 si aún no hay fila en el depósito (sobrante/regularización +)
  $stmt = $pdo->prepare("
    SELECT 
      smp.id_stock,
      mp.id_materia_prima,
      mp.materia_prima_descripcion,
      COALESCE(smp.cantidad_existente, 0) AS stock_actual,
      d.deposito_descri
    FROM materia_prima mp
    CROSS JOIN deposito d
    LEFT JOIN stock_materia_prima smp
      ON smp.id_materia_prima = mp.id_materia_prima
     AND smp.deposito_id = d.deposito_id
    WHERE mp.materia_prima_estado = 'ACTIVO'
      AND d.deposito_id = :deposito_id
    ORDER BY mp.materia_prima_descripcion ASC
  ");

  $stmt->execute([':deposito_id' => $depositoId]);
  $productos = $stmt->fetchAll();

  echo json_encode(['success' => true, 'productos' => $productos]);
} catch (PDOException $e) {
  echo json_encode(['error' => 'Error al obtener productos: ' . $e->getMessage()]);
}
