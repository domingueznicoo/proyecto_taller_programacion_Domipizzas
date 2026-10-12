<?php
// Genera informe PDF de trabajadores.
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
        $where[] = "trabajadores.trabajador_estado IN ('INACTIVO', 'ANULADO')";
    } elseif ($filtro_estado === 'ACTIVO') {
        $where[] = "trabajadores.trabajador_estado = 'ACTIVO'";
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

    $anchos = [12, 53, 35, 30, 30, 20];
    $aligns = ['C', 'L', 'L', 'L', 'R', 'C'];
    $pdf->tablaEncabezado($anchos, ['Cód.', 'Personal', 'Rol', 'Turno', 'Costo/h (Gs.)', 'Estado'], $aligns);

    $query = $pdo->prepare("
        SELECT trabajadores.trabajadores_id, trabajadores.trabajador_estado, trabajadores.trabajador_costo_hora,
            personal.personal_nombre, personal.personal_apellido,
            rol.rol_descripcion, turno.turno_descripcion
        FROM trabajadores
        JOIN personal ON personal.personal_id = trabajadores.personal_id
        LEFT JOIN rol ON rol.rol_id = trabajadores.rol_id
        LEFT JOIN turno ON turno.turno_id = trabajadores.turno_id
        {$whereClause}
        ORDER BY trabajadores.trabajadores_id DESC
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
            convertir(substr(trim($row['personal_nombre'] . ' ' . $row['personal_apellido']), 0, 22)),
            convertir(substr($row['rol_descripcion'] ?? '-', 0, 16)),
            convertir($row['turno_descripcion'] ?? '-'),
            number_format((float)$row['trabajador_costo_hora'], 0, ',', '.'),
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
