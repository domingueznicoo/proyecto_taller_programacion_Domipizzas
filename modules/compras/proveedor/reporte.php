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
        $where[] = "estado_proveedor IN ('INACTIVO', 'ANULADO')";
    } elseif ($filtro_estado === 'ACTIVO') {
        $where[] = "estado_proveedor = 'ACTIVO'";
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
        SELECT id_proveedor, razon_social, ruc_proveedor, telefono_proveedor, email_proveedor, estado_proveedor
        FROM proveedor
        {$whereClause}
        ORDER BY id_proveedor DESC
    ");
    $query->execute();

    $total = 0;
    $activos = 0;
    $inactivos = 0;

    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $estado = strtoupper(trim($row['estado_proveedor'] ?? 'ACTIVO'));
        if ($estado === 'ANULADO') {
            $estado = 'INACTIVO';
        }

        $pdf->tablaFila($anchos, [
            $row['id_proveedor'],
            convertir(substr($row['razon_social'], 0, 24)),
            convertir($row['ruc_proveedor'] ?? ''),
            convertir(substr($row['telefono_proveedor'] ?? '-', 0, 14)),
            convertir(substr($row['email_proveedor'] ?? '-', 0, 22)),
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
