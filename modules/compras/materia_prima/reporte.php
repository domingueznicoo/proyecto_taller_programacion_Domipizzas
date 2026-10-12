<?php
// Genera informe PDF de materias primas.
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/reporte/base_pdf.php';

function convertir($text) {
    return mb_strtoupper($text, 'UTF-8');
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $filtro_estado = isset($_GET['estado']) && $_GET['estado'] !== '' ? $_GET['estado'] : null;
    $filtro_iva = isset($_GET['tipo_iva_id']) && $_GET['tipo_iva_id'] !== '' ? (int)$_GET['tipo_iva_id'] : null;

    $where = [];
    $params = [];

    if ($filtro_estado) {
        $where[] = "mp.mp_estado = :estado";
        $params[':estado'] = $filtro_estado;
    }
    if ($filtro_iva) {
        $where[] = "mp.tipo_iva_id = :tipo_iva_id";
        $params[':tipo_iva_id'] = $filtro_iva;
    }

    if (isset($_GET['mp_id'])) {
        $mpId = filter_var($_GET['mp_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($mpId === false) {
            http_response_code(400);
            exit('Código de materia prima inválido.');
        }
        $where[] = 'mp.mp_id = :mp_id';
        $params[':mp_id'] = $mpId;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $subtitulo = 'Generado: ' . date('d/m/Y H:i:s');
    if ($filtro_estado) {
        $subtitulo .= ' | Estado: ' . $filtro_estado;
    }
    if ($filtro_iva) {
        $subtitulo .= ' | IVA ID: ' . $filtro_iva;
    }

    $pdf = new BasePDF('L', 'mm', 'A4', [
        'titulo'    => 'Reporte de Materias Primas',
        'subtitulo' => $subtitulo,
    ]);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $anchos = [12, 50, 25, 20, 40, 30, 30, 25, 25];
    $aligns = ['C', 'L', 'C', 'C', 'L', 'L', 'R', 'R', 'C'];
    $pdf->tablaEncabezado($anchos, ['Cód.', 'Descripción', 'Unidad', 'IVA', 'Depósito', 'Marca', 'Precio ref.', 'Stock', 'Estado'], $aligns);

    $sql = "
        SELECT mp.mp_id, mp.mp_descripcion, mp.mp_estado,
               um.unidad_medida_nombre, ti.tipo_iva_nombre, d.deposito_nombre,
               m.marca_descrip, mp.mp_precio_referencia,
               COALESCE((SELECT SUM(stock_cantidad) FROM stock_materia_prima
                         WHERE mp_id = mp.mp_id), 0) AS stock_total
        FROM materia_prima mp
        LEFT JOIN unidad_medida um ON mp.unidad_medida_id = um.unidad_medida_id
        LEFT JOIN tipo_iva ti ON mp.tipo_iva_id = ti.tipo_iva_id
        LEFT JOIN deposito d ON d.deposito_id = mp.deposito_id
        LEFT JOIN marca m ON m.marca_id = mp.marca_id
        {$whereClause}
        ORDER BY mp.mp_id DESC
    ";

    $query = $pdo->prepare($sql);
    $query->execute($params);

    $total = 0;
    $activos = 0;
    $inactivos = 0;

    while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
        $pdf->tablaFila($anchos, [
            $data['mp_id'],
            convertir(substr($data['mp_descripcion'], 0, 28)),
            convertir(substr($data['unidad_medida_nombre'] ?? '-', 0, 12)),
            convertir(substr($data['tipo_iva_nombre'] ?? '-', 0, 12)),
            convertir(substr($data['deposito_nombre'] ?? '-', 0, 18)),
            convertir(substr($data['marca_descrip'] ?? '-', 0, 18)),
            $data['mp_precio_referencia'],
            number_format((float)$data['stock_total'], 0, ',', '.'),
            convertir($data['mp_estado']),
        ], $aligns);

        $total++;
        if (strtoupper($data['mp_estado']) === 'ACTIVO') {
            $activos++;
        } else {
            $inactivos++;
        }
    }

    $pdf->Ln(3);
    $pdf->seccionTitulo('Resumen');
    $pdf->filaDato('Total de Materias Primas:', (string)$total, 50);
    $pdf->filaDato('Activas:', (string)$activos, 50);
    $pdf->filaDato('Inactivas:', (string)$inactivos, 50);

    $pdf->pieDocumento();
    $pdf->Output('I', 'Reporte_MateriasPrimas_' . date('YmdHis') . '.pdf');

} catch (PDOException $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}
