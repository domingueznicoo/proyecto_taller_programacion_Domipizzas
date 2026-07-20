<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
// Incluir la conexión a la base de datos y la clase para generar PDF
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/pedido_ventas.php'; // Clase BasePDF

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

function convertir($texto) {
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

try {
    // Conexión PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]);

    // SQL base para pedidos de venta
    $sql = "
        SELECT 
            pv.id_pedido_venta                         AS pedido_id,
            c.cliente_nombre || ' ' || c.cliente_apellido AS cliente,
            u.username                                  AS usuario,
            p.producto_descri                          AS producto,
            d.cantidad_pedido                          AS cantidad,
            d.pedido_precio_total                      AS subtotal,
            pv.pedido_estado                            AS estado,
            to_char(pv.pedido_fecha,'YYYY-MM-DD')      AS pedido_fecha
        FROM pedido_venta pv
        JOIN detalle_pedido_venta d ON pv.id_pedido_venta = d.id_pedido_venta
        JOIN productos p              ON d.producto_id      = p.producto_id
        JOIN clientes c                ON pv.id_cliente     = c.id_cliente
        JOIN usuarios u                ON pv.id_usuario     = u.id_usuario
        WHERE DATE(pv.pedido_fecha) BETWEEN :desde AND :hasta
    ";

    // Condición solo si NO es "REPORTE TOTAL"
    if (!$esTotal) {
        $sql .= " AND pv.pedido_estado = :estado ";
    }

    $sql .= " ORDER BY pv.pedido_fecha DESC, pv.id_pedido_venta DESC, p.producto_descri ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':desde', $desde, PDO::PARAM_STR);
    $stmt->bindParam(':hasta', $hasta, PDO::PARAM_STR);
    if (!$esTotal) {
        $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
    }
    $stmt->execute();

    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($pedidos) === 0) {
        echo "<script>
                alert('No se encontraron pedidos para el rango de fechas y estado seleccionado.');
                window.location.href = 'view.php';
              </script>";
        exit;
    }

    // PDF
    $titulo = $esTotal ? 'INFORME DE PEDIDOS DE VENTA - TODOS LOS ESTADOS'
                       : 'INFORME DE PEDIDOS DE VENTA - ESTADO: ' . $estado;

    $pdf = new BasePDF('P', 'mm', 'A4', [
        'titulo'    => $titulo,
        'subtitulo' => 'Periodo: ' . $desde . ' al ' . $hasta,
    ]);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $anchos = [20, 50, 50, 20, 25, 25, 20];
    $aligns = ['C', 'L', 'L', 'C', 'R', 'C', 'C'];
    $pdf->tablaEncabezado($anchos, ['Pedido', 'Cliente', 'Producto', 'Cant.', 'Subtotal', 'Fecha', 'Estado'], $aligns);

    $totalGeneral = 0;
    foreach ($pedidos as $row) {
        $pdf->tablaFila($anchos, [
            $row['pedido_id'],
            $row['cliente'],
            $row['producto'],
            $row['cantidad'],
            number_format($row['subtotal'], 0, ',', '.'),
            $row['pedido_fecha'],
            $row['estado'],
        ], $aligns);
        $totalGeneral += $row['subtotal'];
    }

    $pdf->tablaTotal($anchos, 'TOTAL GENERAL', number_format($totalGeneral, 0, ',', '.'));

    $pdf->pieDocumento();

    // Nombre de salida
    $nombreSalida = $esTotal ? 'Informe_Pedidos_Venta_Todos.pdf'
                             : 'Informe_Pedidos_Venta_' . preg_replace('/\s+/', '_', $estado) . '.pdf';
    $pdf->Output('I', $nombreSalida);

} catch (PDOException $e) {
    echo "Error al consultar los datos: " . $e->getMessage();
    exit;
}
?>

