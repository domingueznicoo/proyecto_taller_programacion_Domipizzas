<?php
// Genera informe PDF de pedidos de compra.
require_once __DIR__ . '/../../../config/bootstrap.php';
// Incluir la conexión a la base de datos y la clase para generar PDF
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/pedido_compras.php';

// Validación del parámetro
if (!isset($_GET['estado']) || $_GET['estado'] === '') {
    echo "<script>
            alert('No se recibió un estado válido para generar el informe.');
            window.location.href = 'view.php';
          </script>";
    exit();
}

// Fechas obligatorias
$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

$reFecha = '/^\d{4}-\d{2}-\d{2}$/';
if ($desde === '' || $hasta === '' || !preg_match($reFecha, $desde) || !preg_match($reFecha, $hasta)) {
    echo "<script>
            alert('El rango de fechas es obligatorio y debe tener el formato YYYY-MM-DD.');
            window.location.href = 'view.php';
          </script>";
    exit();
}
if ($hasta < $desde) {
    echo "<script>
            alert('La fecha \"Hasta\" no puede ser anterior a \"Desde\".');
            window.location.href = 'view.php';
          </script>";
    exit();
}

$estado   = trim($_GET['estado']);
$estadoUpper = mb_strtoupper($estado);
$esTotal  = in_array($estadoUpper, ['REPORTE TOTAL','TOTAL'], true);

try {
    // Conexión PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]);

    // --- SQL base (incluye ESTADO, FECHA y HORA) ---
    $sql = "
        SELECT 
            pc.id_pedido_compra                         AS pedido_id,
            u.username                                  AS usuario,
            mp.materia_prima_descripcion                AS producto,
            dp.cantidad_pedido                          AS cantidad,
            pc.pedido_estado                            AS estado,
            to_char(pc.pedido_fecha_emision,'YYYY-MM-DD') AS pedido_fecha,
            to_char(pc.pedido_fecha_emision,'HH24:MI:SS') AS pedido_hora
        FROM pedidos_compra pc
        JOIN pedido_detalle_compra dp ON pc.id_pedido_compra = dp.id_pedido_compra
        JOIN materia_prima mp         ON dp.id_materia_prima = mp.id_materia_prima
        JOIN usuarios u               ON pc.id_usuario       = u.id_usuario
        WHERE DATE(pc.pedido_fecha_emision) BETWEEN :desde AND :hasta
    ";

    // Condición solo si NO es "REPORTE TOTAL"
    if (!$esTotal) {
        $sql .= " AND pc.pedido_estado = :estado ";
    }

    $sql .= " ORDER BY pc.pedido_fecha_emision DESC, pc.id_pedido_compra DESC, mp.materia_prima_descripcion ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':desde', $desde, PDO::PARAM_STR);
    $stmt->bindParam(':hasta', $hasta, PDO::PARAM_STR);
    if (!$esTotal) {
        $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
    }
    $stmt->execute();

    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($pedidos) === 0) {
        header("Location: ../../comunes/reportes/view.php?alert=6"); // sin resultados
        exit;
    }

    // --- PDF ---
    $titulo = $esTotal ? 'INFORME DE PEDIDOS - TODOS LOS ESTADOS'
                       : 'INFORME DE PEDIDOS - ESTADO: ' . $estado;

    $pdf = new BasePDF('P', 'mm', 'A4', [
        'titulo'    => $titulo,
        'subtitulo' => 'Periodo: ' . $desde . ' al ' . $hasta,
    ]);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $anchos = [18, 30, 62, 16, 24, 20, 20];
    $aligns = ['C', 'L', 'L', 'C', 'C', 'C', 'C'];
    $pdf->tablaEncabezado($anchos, ['Pedido', 'Usuario', 'Materia prima', 'Cant.', 'Fecha', 'Hora', 'Estado'], $aligns);

    foreach ($pedidos as $row) {
        $pdf->tablaFila($anchos, [
            $row['pedido_id'],
            $row['usuario'],
            $row['producto'],
            $row['cantidad'],
            $row['pedido_fecha'],
            $row['pedido_hora'],
            $row['estado'],
        ], $aligns);
    }

    $pdf->pieDocumento();
    $nombreSalida = $esTotal ? 'Informe_Pedidos_Todos.pdf'
                             : 'Informe_Pedidos_' . preg_replace('/\s+/', '_', $estado) . '.pdf';
    $pdf->Output('I', $nombreSalida);

} catch (PDOException $e) {
    echo "Error al consultar los datos: " . $e->getMessage();
    exit;
}
