<?php
// Genera informe PDF de productos.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/pedido_ventas.php'; // Clase BasePDF

function convertir($text) {
    return mb_strtoupper($text, 'UTF-8');
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $filtro_estado = isset($_GET['estado']) && $_GET['estado'] !== '' ? $_GET['estado'] : null;
    $filtro_iva = isset($_GET['iva_id']) && $_GET['iva_id'] !== '' ? (int)$_GET['iva_id'] : null;
    $filtro_tipo = isset($_GET['tipo_producto']) && $_GET['tipo_producto'] !== '' ? (int)$_GET['tipo_producto'] : null;

    $subtitulo = 'Generado: ' . date('d/m/Y H:i:s');
    if ($filtro_estado) {
        $subtitulo .= ' | Estado: ' . $filtro_estado;
    }
    if ($filtro_iva) {
        $subtitulo .= ' | IVA ID: ' . $filtro_iva;
    }
    if ($filtro_tipo) {
        $subtitulo .= ' | Tipo ID: ' . $filtro_tipo;
    }

    $pdf = new BasePDF('P', 'mm', 'A4', [
        'titulo'    => 'Reporte de Productos',
        'subtitulo' => $subtitulo,
    ]);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $where = [];
    $params = [];
    
    if ($filtro_estado) {
        $where[] = "p.producto_estado = :estado";
        $params[':estado'] = $filtro_estado;
    }
    if ($filtro_iva) {
        $where[] = "p.iva_id = :iva_id";
        $params[':iva_id'] = $filtro_iva;
    }
    if ($filtro_tipo) {
        $where[] = "p.id_tipo_producto = :tipo_producto";
        $params[':tipo_producto'] = $filtro_tipo;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $anchos = [18, 55, 25, 30, 20, 25, 17];
    $aligns = ['C', 'L', 'C', 'R', 'C', 'C', 'C'];
    $pdf->tablaEncabezado($anchos, ['Código', 'Descripción', 'Unidad', 'Precio', 'IVA', 'Tipo', 'Estado'], $aligns);

    // Verificar si existe la tabla tipo_producto
    $checkTipoProducto = $pdo->query("
        SELECT 1 FROM information_schema.tables 
        WHERE table_name = 'tipo_producto' 
        LIMIT 1
    ");
    $existeTipoProducto = $checkTipoProducto->rowCount() > 0;
    
    $joinTipoProducto = '';
    $selectTipoProducto = "'Sin tipo' AS tipo_producto";
    
    if ($existeTipoProducto) {
        $cols = $pdo->query("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'tipo_producto'
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('cod_tipo_prod', $cols) && in_array('t_p_descrip', $cols)) {
            $joinTipoProducto = 'LEFT JOIN tipo_producto tp ON tp.cod_tipo_prod = p.id_tipo_producto';
            $selectTipoProducto = "COALESCE(tp.t_p_descrip, 'Sin tipo') AS tipo_producto";
        }
    }
    
    $sql = "
        SELECT 
            p.producto_id,
            p.producto_descri,
            p.producto_precio,
            p.producto_estado,
            um.unidad_descri,
            COALESCE(ti.iva_descri, 'Sin IVA') AS iva_descri,
            {$selectTipoProducto}
        FROM productos p
        JOIN unidad_medida um ON p.id_unidad = um.id_unidad
        LEFT JOIN tipo_iva ti ON p.iva_id = ti.iva_id
        {$joinTipoProducto}
        {$whereClause}
        ORDER BY p.producto_id DESC
    ";

    $query = $pdo->prepare($sql);
    $query->execute($params);

    $totalProductos = 0;
    $totalPrecio = 0;
    $activos = 0;
    $inactivos = 0;

    while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
        $pdf->tablaFila($anchos, [
            $data['producto_id'],
            convertir(substr($data['producto_descri'], 0, 28)),
            convertir(substr($data['unidad_descri'], 0, 12)),
            number_format($data['producto_precio'], 0, ',', '.'),
            convertir(substr($data['iva_descri'], 0, 10)),
            convertir(substr($data['tipo_producto'], 0, 12)),
            convertir($data['producto_estado']),
        ], $aligns);
        
        $totalProductos++;
        $totalPrecio += $data['producto_precio'];
        if ($data['producto_estado'] === 'ACTIVO') $activos++;
        else $inactivos++;
    }

    $pdf->Ln(3);
    $pdf->seccionTitulo('Resumen');
    $pdf->filaDato('Total de Productos:', (string)$totalProductos, 50);
    $pdf->filaDato('Productos Activos:', (string)$activos, 50);
    $pdf->filaDato('Productos Inactivos:', (string)$inactivos, 50);
    $pdf->filaDato('Precio Promedio:', number_format($totalProductos > 0 ? $totalPrecio / $totalProductos : 0, 0, ',', '.') . ' Gs.', 50);

    $pdf->pieDocumento();
    $pdf->Output('I', 'Reporte_Productos_' . date('YmdHis') . '.pdf');

} catch (PDOException $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}

