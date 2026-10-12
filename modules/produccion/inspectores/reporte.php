<?php
// Genera informe PDF de inspectores.
require_once __DIR__ . '/../../../config/bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['username'])) {
    header('Location: ' . loginUrl());
    exit;
}
require_once PROJECT_ROOT . '/config/permissions.php';
check_permission('REFERENCIALES');
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
        $where[] = "inspectores.inspector_estado IN ('INACTIVO', 'ANULADO')";
    } elseif ($filtro_estado === 'ACTIVO') {
        $where[] = "inspectores.inspector_estado = 'ACTIVO'";
    }
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $subtitulo = 'Generado: ' . date('d/m/Y H:i:s');
    if ($filtro_estado) {
        $subtitulo .= ' | Estado: ' . $filtro_estado;
    }

    $pdf = new BasePDF('P', 'mm', 'A4', [
        'titulo'    => 'Reporte de Inspectores',
        'subtitulo' => $subtitulo,
    ]);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $anchos = [20, 25, 110, 25];
    $aligns = ['C', 'C', 'L', 'C'];
    $pdf->tablaEncabezado($anchos, ['Cód.', 'Trabajador', 'Nombre y apellido', 'Estado'], $aligns);

    $query = $pdo->prepare("
        SELECT inspectores.id_inspectores, inspectores.inspector_estado, trabajadores.trabajadores_id,
            personal.personal_nombre, personal.personal_apellido
        FROM inspectores
        JOIN trabajadores ON trabajadores.trabajadores_id = inspectores.trabajadores_id
        JOIN personal ON personal.personal_id = trabajadores.personal_id
        {$whereClause}
        ORDER BY inspectores.id_inspectores DESC
    ");
    $query->execute();

    $total = 0;
    $activos = 0;
    $inactivos = 0;

    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $estado = strtoupper(trim($row['inspector_estado'] ?? 'ACTIVO'));
        if ($estado === 'ANULADO') {
            $estado = 'INACTIVO';
        }

        $pdf->tablaFila($anchos, [
            $row['id_inspectores'],
            $row['trabajadores_id'],
            convertir(trim($row['personal_nombre'] . ' ' . $row['personal_apellido'])),
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
    $pdf->filaDato('Total de Inspectores:', (string)$total, 50);
    $pdf->filaDato('Activos:', (string)$activos, 50);
    $pdf->filaDato('Inactivos:', (string)$inactivos, 50);

    $pdf->pieDocumento();
    $pdf->Output('I', 'Reporte_Inspectores_' . date('YmdHis') . '.pdf');
} catch (PDOException $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}
