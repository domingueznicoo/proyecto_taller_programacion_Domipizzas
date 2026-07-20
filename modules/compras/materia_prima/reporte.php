<?php
// Genera informe PDF de materias primas.
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

    $filtro_estado = isset($_GET['estado']) && $_GET['estado'] !== '' ? $_GET['estado'] : null;
    $filtro_iva = isset($_GET['iva_id']) && $_GET['iva_id'] !== '' ? (int)$_GET['iva_id'] : null;

    $where = [];
    $params = [];

    if ($filtro_estado) {
        $where[] = "mp.materia_prima_estado = :estado";
        $params[':estado'] = $filtro_estado;
    }
    if ($filtro_iva) {
        $where[] = "mp.iva_id = :iva_id";
        $params[':iva_id'] = $filtro_iva;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $subtitulo = 'Generado: ' . date('d/m/Y H:i:s');
    if ($filtro_estado) {
        $subtitulo .= ' | Estado: ' . $filtro_estado;
    }
    if ($filtro_iva) {
        $subtitulo .= ' | IVA ID: ' . $filtro_iva;
    }

    $pdf = new BasePDF('P', 'mm', 'A4', [
        'titulo'    => 'Reporte de Materias Primas',
        'subtitulo' => $subtitulo,
    ]);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $anchos = [15, 55, 25, 25, 35, 20, 15];
    $aligns = ['C', 'L', 'C', 'C', 'L', 'R', 'C'];
    $pdf->tablaEncabezado($anchos, ['Cód.', 'Descripción', 'Unidad', 'IVA', 'Depósito', 'Stock', 'Estado'], $aligns);

    $sql = "
        SELECT
            mp.id_materia_prima,
            mp.materia_prima_descripcion,
            mp.materia_prima_estado,
            um.unidad_descri,
            COALESCE(ti.iva_descri, 'Sin IVA') AS iva_descri,
            COALESCE(d.deposito_descri, 'Sin depósito') AS deposito_descri,
            COALESCE(SUM(smp.cantidad_existente), 0) AS stock_total
        FROM materia_prima mp
        LEFT JOIN unidad_medida um ON mp.id_unidad = um.id_unidad
        LEFT JOIN tipo_iva ti ON mp.iva_id = ti.iva_id
        LEFT JOIN stock_materia_prima smp ON smp.id_materia_prima = mp.id_materia_prima
        LEFT JOIN deposito d ON d.deposito_id = (
            SELECT deposito_id FROM stock_materia_prima
            WHERE id_materia_prima = mp.id_materia_prima
            ORDER BY cantidad_existente DESC
            LIMIT 1
        )
        {$whereClause}
        GROUP BY mp.id_materia_prima, mp.materia_prima_descripcion, mp.materia_prima_estado,
                 um.unidad_descri, ti.iva_descri, d.deposito_descri
        ORDER BY mp.id_materia_prima DESC
    ";

    $query = $pdo->prepare($sql);
    $query->execute($params);

    $total = 0;
    $activos = 0;
    $inactivos = 0;

    while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
        $pdf->tablaFila($anchos, [
            $data['id_materia_prima'],
            convertir(substr($data['materia_prima_descripcion'], 0, 28)),
            convertir(substr($data['unidad_descri'] ?? '-', 0, 12)),
            convertir(substr($data['iva_descri'], 0, 12)),
            convertir(substr($data['deposito_descri'], 0, 18)),
            number_format((float)$data['stock_total'], 0, ',', '.'),
            convertir($data['materia_prima_estado']),
        ], $aligns);

        $total++;
        if (strtoupper($data['materia_prima_estado']) === 'ACTIVO') {
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
