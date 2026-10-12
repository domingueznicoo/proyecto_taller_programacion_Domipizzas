<?php
// Genera informe PDF de proveedores.
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
        $where[] = "prov_estado IN ('INACTIVO', 'ANULADO')";
    } elseif ($filtro_estado === 'ACTIVO') {
        $where[] = "prov_estado = 'ACTIVO'";
    }
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $subtitulo = 'Generado: ' . date('d/m/Y H:i:s');
    if ($filtro_estado) {
        $subtitulo .= ' | Estado: ' . $filtro_estado;
    }

    $pdf = new BasePDF('P', 'mm', 'A4', [
        'titulo'    => 'Reporte de Proveedores',
        'subtitulo' => $subtitulo,
    ]);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $anchos = [15, 45, 25, 30, 40, 25];
    $aligns = ['C', 'L', 'C', 'C', 'L', 'C'];
    $pdf->tablaEncabezado($anchos, ['Cód.', 'Razón Social', 'RUC', 'Teléfono', 'Correo', 'Estado'], $aligns);

    $query = $pdo->prepare("
        SELECT prov_id, prov_razon_social, prov_ruc, prov_telefono, prov_email, prov_estado
        FROM proveedor
        {$whereClause}
        ORDER BY prov_id DESC
    ");
    $query->execute();

    $total = 0;
    $activos = 0;
    $inactivos = 0;

    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $estado = strtoupper(trim($row['prov_estado'] ?? 'ACTIVO'));
        if ($estado === 'ANULADO') {
            $estado = 'INACTIVO';
        }

        $pdf->tablaFila($anchos, [
            $row['prov_id'],
            convertir(substr($row['prov_razon_social'], 0, 24)),
            convertir($row['prov_ruc'] ?? ''),
            convertir(substr($row['prov_telefono'] ?? '-', 0, 14)),
            convertir(substr($row['prov_email'] ?? '-', 0, 22)),
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
    $pdf->filaDato('Total de Proveedores:', (string)$total, 50);
    $pdf->filaDato('Activos:', (string)$activos, 50);
    $pdf->filaDato('Inactivos:', (string)$inactivos, 50);

    $pdf->pieDocumento();
    $pdf->Output('I', 'Reporte_Proveedores_' . date('YmdHis') . '.pdf');
} catch (PDOException $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}
