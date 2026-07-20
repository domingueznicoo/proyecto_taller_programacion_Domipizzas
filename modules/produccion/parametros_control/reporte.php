<?php
// Genera informe PDF de parámetros de inspección.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/pedido_ventas.php';

function convertir($text) {
    return mb_strtoupper($text, 'UTF-8');
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $filtro_estado = isset($_GET['estado']) && $_GET['estado'] !== '' ? strtoupper(trim($_GET['estado'])) : null;
    $filtro_producto = isset($_GET['producto_id']) && $_GET['producto_id'] !== '' ? (int)$_GET['producto_id'] : null;

    $where = [];
    $params = [];

    if ($filtro_estado === 'INACTIVO') {
        $where[] = "pc.parametro_estado = 'INACTIVO'";
    } elseif ($filtro_estado === 'ACTIVO') {
        $where[] = "pc.parametro_estado = 'ACTIVO'";
    }
    if ($filtro_producto) {
        $where[] = "pc.producto_id = :prod";
        $params[':prod'] = $filtro_producto;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $subtitulo = 'Generado: ' . date('d/m/Y H:i:s');
    if ($filtro_estado) {
        $subtitulo .= ' | Estado: ' . $filtro_estado;
    }
    if ($filtro_producto) {
        $subtitulo .= ' | Producto ID: ' . $filtro_producto;
    }

    $pdf = new BasePDF('P', 'mm', 'A4', [
        'titulo'    => 'Parámetros de Control de Calidad',
        'subtitulo' => $subtitulo,
    ]);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $anchos = [15, 55, 55, 25];
    $aligns = ['C', 'L', 'L', 'C'];
    $pdf->tablaEncabezado($anchos, ['Cód.', 'Producto', 'Descripción', 'Estado'], $aligns);

    $query = $pdo->prepare("
        SELECT pc.parametro_id, pc.parametro_descri, pc.parametro_estado, p.producto_descri
        FROM parametros_control pc
        JOIN productos p ON p.producto_id = pc.producto_id
        {$whereClause}
        ORDER BY p.producto_descri, pc.parametro_descri
    ");
    $query->execute($params);

    $total = 0;
    $activos = 0;
    $inactivos = 0;

    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $estado = strtoupper(trim($row['parametro_estado'] ?? 'ACTIVO'));

        $pdf->tablaFila($anchos, [
            $row['parametro_id'],
            convertir(substr($row['producto_descri'], 0, 28)),
            convertir(substr($row['parametro_descri'], 0, 28)),
            convertir($estado),
        ], $aligns);

        $total++;
        if ($estado === 'ACTIVO') {
            $activos++;
        } else {
            $inactivos++;
        }
    }

    $pdf->Ln(3);
    $pdf->seccionTitulo('Resumen');
    $pdf->filaDato('Total:', (string)$total, 50);
    $pdf->filaDato('Activos:', (string)$activos, 50);
    $pdf->filaDato('Inactivos:', (string)$inactivos, 50);

    $pdf->pieDocumento();
    $pdf->Output('I', 'Reporte_ParametrosControl_' . date('YmdHis') . '.pdf');
} catch (PDOException $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}
