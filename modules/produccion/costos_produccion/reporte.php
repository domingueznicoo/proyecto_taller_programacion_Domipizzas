<?php
// Informe PDF de costos de producción (3 páginas: MP, MO, CIF).
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();

if (empty($_SESSION['username'])) {
    die('Sesión inválida.');
}

require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/permissions.php';
require_once PROJECT_ROOT . '/reporte/pedido_ventas.php';

if (!check_permission('COSTOS_PRODUCCION', false)) {
    die('Sin permiso para generar el informe.');
}

$costoId = isset($_GET['costo_id']) ? (int)$_GET['costo_id'] : 0;
if ($costoId <= 0) {
    die('ID de costeo inválido.');
}

function fmtGs(int $monto): string
{
    return 'Gs. ' . number_format($monto, 0, ',', '.');
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $cab = $pdo->prepare("
        SELECT
            c.costo_id,
            c.costo_fecha,
            c.costo_estado,
            c.costo_total,
            c.orden_id,
            u.username,
            op.orden_prod_estado
        FROM costo_produccion c
        JOIN usuarios u ON u.id_usuario = c.id_usuario
        LEFT JOIN orden_produccion op ON op.orden_id = c.orden_id
        WHERE c.costo_id = :id
        LIMIT 1
    ");
    $cab->execute([':id' => $costoId]);
    $cabecera = $cab->fetch();
    if (!$cabecera) {
        die('Costeo no encontrado.');
    }

    $st = $pdo->prepare("
        SELECT
            cd.costo_tipo,
            cd.costo_cantidad,
            cd.costo_precio,
            cd.costo_concepto,
            mp.materia_prima_descripcion,
            TRIM(per.personal_nombre || ' ' || per.personal_apellido) AS trabajador_nombre
        FROM costo_detalle_produccion cd
        LEFT JOIN materia_prima mp ON mp.id_materia_prima = cd.id_materia_prima
        LEFT JOIN trabajadores t ON t.trabajadores_id = cd.trabajadores_id
        LEFT JOIN personal per ON per.id_personal = t.id_personal
        WHERE cd.costo_id = :id
        ORDER BY cd.costo_tipo, cd.costo_detalle_id
    ");
    $st->execute([':id' => $costoId]);

    $lineasMp = [];
    $lineasMo = [];
    $lineasCif = [];
    $subMp = 0;
    $subMo = 0;
    $subCif = 0;

    foreach ($st->fetchAll() as $row) {
        $cant = (int)$row['costo_cantidad'];
        $precio = (int)$row['costo_precio'];
        $sub = $cant * $precio;
        $tipo = strtoupper(trim((string)$row['costo_tipo']));

        if ($tipo === 'MP') {
            $lineasMp[] = [
                'descripcion' => $row['materia_prima_descripcion'] ?? '-',
                'cantidad' => $cant,
                'precio' => $precio,
                'subtotal' => $sub,
            ];
            $subMp += $sub;
        } elseif ($tipo === 'MO') {
            $lineasMo[] = [
                'descripcion' => $row['trabajador_nombre'] ?? '-',
                'cantidad' => $cant,
                'precio' => $precio,
                'subtotal' => $sub,
            ];
            $subMo += $sub;
        } else {
            $lineasCif[] = [
                'descripcion' => $row['costo_concepto'] ?? '-',
                'cantidad' => $cant,
                'precio' => $precio,
                'subtotal' => $sub,
            ];
            $subCif += $sub;
        }
    }

    $opId = (int)$cabecera['orden_id'];
    $pdf = new BasePDF('P', 'mm', 'A4', [
        'titulo'    => 'Informe de Costos de Producción',
        'subtitulo' => 'Costeo N° ' . $costoId . ' Orden de producción #' . $opId,
    ]);
    $pdf->AliasNbPages();

    // —— Página 1: Materia prima ——
    $pdf->AddPage();
    $pdf->seccionTitulo('Datos del Costo');
    $pdf->filaDato('Costo N°:', (string)$cabecera['costo_id'], 40);
    $pdf->filaDato('Orden de producción:', '#' . $opId, 40);
    $pdf->filaDato('Fecha:', substr((string)$cabecera['costo_fecha'], 0, 10), 40);
    $pdf->filaDato('Estado:', (string)$cabecera['costo_estado'], 40);
    $pdf->filaDato('Estado Orden Producción:', (string)($cabecera['orden_prod_estado'] ?? '-'), 40);
    $pdf->filaDato('Usuario:', (string)$cabecera['username'], 40);
    $pdf->Ln(2);

    $pdf->seccionTitulo('Página 1: Materia prima (MP)');
    $anchosMp = [75, 25, 35, 45];
    if (!empty($lineasMp)) {
        $pdf->tablaEncabezado($anchosMp, ['Materia prima', 'Cant.', 'Precio unit.', 'Subtotal'], ['L', 'C', 'R', 'R']);
        foreach ($lineasMp as $ln) {
            $pdf->tablaFila($anchosMp, [
                $ln['descripcion'],
                (string)$ln['cantidad'],
                fmtGs($ln['precio']),
                fmtGs($ln['subtotal']),
            ], ['L', 'C', 'R', 'R']);
        }
        $pdf->tablaTotal($anchosMp, 'Subtotal Materia Prima:', fmtGs($subMp));
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 8, convertir('Sin líneas de materia prima registradas.'), 0, 1, 'L');
    }

    // —— Página 2: Mano de obra ——
    $pdf->AddPage();
    $pdf->seccionTitulo('Página 2: Mano de obra (MO)');
    $anchosMo = [75, 25, 35, 45];
    if (!empty($lineasMo)) {
        $pdf->tablaEncabezado($anchosMo, ['Trabajador', 'Horas', 'Gs/hora', 'Subtotal'], ['L', 'C', 'R', 'R']);
        foreach ($lineasMo as $ln) {
            $pdf->tablaFila($anchosMo, [
                $ln['descripcion'],
                (string)$ln['cantidad'],
                fmtGs($ln['precio']),
                fmtGs($ln['subtotal']),
            ], ['L', 'C', 'R', 'R']);
        }
        $pdf->tablaTotal($anchosMo, 'Subtotal Mano de Obra:', fmtGs($subMo));
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 8, convertir('Sin líneas de mano de obra registradas.'), 0, 1, 'L');
    }

    // —— Página 3: CIF / gastos administrativos ——
    $pdf->AddPage();
    $pdf->seccionTitulo('Página 3: Gastos administrativos (CIF)');
    $anchosCif = [110, 70];
    if (!empty($lineasCif)) {
        $pdf->tablaEncabezado($anchosCif, ['Concepto', 'Monto'], ['L', 'R']);
        foreach ($lineasCif as $ln) {
            $pdf->tablaFila($anchosCif, [
                $ln['descripcion'],
                fmtGs($ln['subtotal']),
            ], ['L', 'R']);
        }
        $pdf->tablaTotal($anchosCif, 'Subtotal CIF:', fmtGs($subCif));
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 8, convertir('Sin gastos administrativos (CIF) registrados.'), 0, 1, 'L');
    }

    $pdf->Ln(6);
    $pdf->seccionTitulo('Resumen general del costeo');
    $anchosRes = [90, 90];
    $pdf->tablaEncabezado($anchosRes, ['Concepto', 'Monto'], ['L', 'R']);
    $pdf->tablaFila($anchosRes, ['Materia prima (MP)', fmtGs($subMp)], ['L', 'R']);
    $pdf->tablaFila($anchosRes, ['Mano de obra (MO)', fmtGs($subMo)], ['L', 'R']);
    $pdf->tablaFila($anchosRes, ['Gastos administrativos (CIF)', fmtGs($subCif)], ['L', 'R']);
    $pdf->tablaTotal($anchosRes, 'COSTO TOTAL:', fmtGs((int)$cabecera['costo_total']));

    $pdf->pieDocumento();
    $pdf->Output('I', 'Costos_Produccion_' . $costoId . '.pdf');
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}
