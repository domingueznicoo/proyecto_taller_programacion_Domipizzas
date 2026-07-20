<?php
// AJAX: stock de ajustes de stock.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';

// Obtener los parámetros de la URL
$productoId = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : null;
$depositoId = isset($_GET['deposito_id']) ? (int)$_GET['deposito_id'] : null;

if (!$productoId || !$depositoId) {
    echo json_encode(['error' => 'Faltan parámetros']);
    exit;
}

try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para obtener el stock existencia
    $query = $pdo->prepare(
        "SELECT COALESCE(cantidad_existente, 0) AS stock_existencia
        FROM stock_materia_prima
        WHERE id_materia_prima = :materia_prima AND deposito_id = :deposito"
    );
    $query->bindParam(':materia_prima', $productoId, PDO::PARAM_INT);
    $query->bindParam(':deposito', $depositoId, PDO::PARAM_INT);
    $query->execute();

    $result = $query->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode(['stock_existencia' => $result['stock_existencia']]);
    } else {
        echo json_encode(['stock_existencia' => 0]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error en la consulta: ' . $e->getMessage()]);
}
?>
