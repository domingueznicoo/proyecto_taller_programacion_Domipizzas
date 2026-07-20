<?php
// Genera informe PDF de clientes.
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

    // Filtros opcionales
    $filtro_estado = isset($_GET['estado']) && $_GET['estado'] !== '' ? $_GET['estado'] : null;
    $filtro_tipo = isset($_GET['tipo_cliente']) && $_GET['tipo_cliente'] !== '' ? $_GET['tipo_cliente'] : null;

    $where = array();
    $params = array();
    
    if ($filtro_estado) {
        $where[] = "c.cliente_estado = :estado";
        $params[':estado'] = $filtro_estado;
    }
    if ($filtro_tipo) {
        $where[] = "c.tipo_cliente = :tipo_cliente";
        $params[':tipo_cliente'] = $filtro_tipo;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $subtitulo = 'Generado: ' . date('d/m/Y H:i:s');
    if ($filtro_estado) {
        $subtitulo .= ' | Estado: ' . $filtro_estado;
    }
    if ($filtro_tipo) {
        $subtitulo .= ' | Tipo: ' . $filtro_tipo;
    }

    $pdf = new BasePDF('P', 'mm', 'A4', [
        'titulo'    => 'Reporte de Clientes',
        'subtitulo' => $subtitulo,
    ]);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $anchos = [15, 50, 15, 25, 25, 40, 20];
    $aligns = ['C', 'L', 'C', 'C', 'C', 'L', 'C'];
    $pdf->tablaEncabezado($anchos, ['Código', 'Nombre/Razón Social', 'Tipo', 'RUC/CI', 'Teléfono', 'Email', 'Estado'], $aligns);

    $sql = "
        SELECT 
            c.id_cliente,
            c.cliente_nombre || ' ' || COALESCE(c.cliente_apellido, '') AS nombre_completo,
            COALESCE(c.tipo_cliente, 'PERSONA') AS tipo_cliente,
            c.cliente_ruc,
            COALESCE(c.cliente_ci, '') AS cliente_ci,
            COALESCE(c.cliente_telefono, '') AS cliente_telefono,
            COALESCE(c.cliente_email, '') AS cliente_email,
            c.cliente_estado
        FROM clientes c
        {$whereClause}
        ORDER BY c.id_cliente DESC
    ";

    $query = $pdo->prepare($sql);
    $query->execute($params);

    $totalClientes = 0;
    $activos = 0;
    $inactivos = 0;
    $personas = 0;
    $empresas = 0;

    while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
        $rucCi = !empty($data['cliente_ci']) ? $data['cliente_ci'] : $data['cliente_ruc'];
        $tipoCliente = strtoupper($data['tipo_cliente'] ?? 'PERSONA');
        
        $pdf->tablaFila($anchos, [
            $data['id_cliente'],
            convertir(substr($data['nombre_completo'], 0, 28)),
            convertir(substr($tipoCliente, 0, 6)),
            substr($rucCi, 0, 12),
            substr($data['cliente_telefono'], 0, 12),
            substr($data['cliente_email'], 0, 25),
            convertir($data['cliente_estado']),
        ], $aligns);
        
        $totalClientes++;
        if ($data['cliente_estado'] === 'ACTIVO') $activos++;
        else $inactivos++;
        if ($tipoCliente === 'PERSONA') $personas++;
        else $empresas++;
    }

    $pdf->Ln(3);
    $pdf->seccionTitulo('Resumen');
    $pdf->filaDato('Total de Clientes:', (string)$totalClientes, 50);
    $pdf->filaDato('Clientes Activos:', (string)$activos, 50);
    $pdf->filaDato('Clientes Inactivos:', (string)$inactivos, 50);
    $pdf->filaDato('Personas Físicas:', (string)$personas, 50);
    $pdf->filaDato('Empresas:', (string)$empresas, 50);

    $pdf->pieDocumento();
    $pdf->Output('I', 'Reporte_Clientes_' . date('YmdHis') . '.pdf');

} catch (PDOException $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}

