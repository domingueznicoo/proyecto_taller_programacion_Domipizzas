<?php
// Genera informe PDF de trabajadores.
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

    $where = [];
    if ($filtro_estado === 'INACTIVO') {
        $where[] = "t.trabajador_estado IN ('INACTIVO', 'ANULADO')";
    } elseif ($filtro_estado === 'ACTIVO') {
        $where[] = "t.trabajador_estado = 'ACTIVO'";
    }
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $subtitulo = 'Generado: ' . date('d/m/Y H:i:s');
    if ($filtro_estado) {
        $subtitulo .= ' | Estado: ' . $filtro_estado;
    }

    $pdf = new BasePDF('P', 'mm', 'A4', [
        'titulo'    => 'Reporte de Trabajadores',
        'subtitulo' => $subtitulo,
    ]);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $anchos = [12, 38, 28, 22, 22, 38, 20];
    $aligns = ['C', 'L', 'L', 'C', 'R', 'L', 'C'];
    $pdf->tablaEncabezado($anchos, ['Cód.', 'Personal', 'Rol', 'Turno', 'Costo/h', 'Etapa', 'Estado'], $aligns);

    $query = $pdo->prepare("
        SELECT
            t.trabajadores_id,
            t.trabajador_rol,
            t.trabajador_turno,
            t.trabajador_costo_hora,
            t.trabajador_estado,
            TRIM(per.personal_nombre || ' ' || per.personal_apellido) AS nombre_personal,
            COALESCE(ep.etapa_descri, '-') AS etapa_descri
        FROM trabajadores t
        JOIN personal per ON per.id_personal = t.id_personal
        LEFT JOIN etapa_produccion ep ON ep.etapa_id = t.id_etapa
        {$whereClause}
        ORDER BY t.trabajadores_id DESC
    ");
    $query->execute();

    $total = 0;
    $activos = 0;
    $inactivos = 0;

    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $estado = strtoupper(trim($row['trabajador_estado'] ?? 'ACTIVO'));
        if ($estado === 'ANULADO') {
            $estado = 'INACTIVO';
        }

        $pdf->tablaFila($anchos, [
            $row['trabajadores_id'],
            convertir(substr($row['nombre_personal'], 0, 22)),
            convertir(substr($row['trabajador_rol'] ?? '-', 0, 16)),
            convertir($row['trabajador_turno'] ?? '-'),
            number_format((float)$row['trabajador_costo_hora'], 0, ',', '.'),
            convertir(substr($row['etapa_descri'], 0, 20)),
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
    $pdf->filaDato('Total de Trabajadores:', (string)$total, 50);
    $pdf->filaDato('Activos:', (string)$activos, 50);
    $pdf->filaDato('Inactivos:', (string)$inactivos, 50);

    $pdf->pieDocumento();
    $pdf->Output('I', 'Reporte_Trabajadores_' . date('YmdHis') . '.pdf');
} catch (PDOException $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}
