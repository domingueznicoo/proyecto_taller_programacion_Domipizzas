<?php
require_once __DIR__ . '/fpdf185/fpdf.php';

if (!function_exists('fpy_convertir')) {
    function fpy_convertir($texto) {
        return mb_convert_encoding((string) $texto, 'ISO-8859-1', 'UTF-8');
    }
}

if (!function_exists('fpy_gs')) {
    function fpy_gs($n) {
        return number_format((float) $n, 0, ',', '.');
    }
}

if (!class_exists('FacturaParaguayaPDF', false)) {

class FacturaParaguayaPDF extends FPDF
{
    private const COLOR_BRAND  = [220, 38, 38];
    private const COLOR_TEXT   = [31, 41, 55];
    private const COLOR_MUTED  = [107, 114, 128];
    private const COLOR_BORDER = [209, 213, 219];
    private const COLOR_FILL   = [249, 250, 251];

    private string $empresaNombre;
    private string $empresaRuc;
    private string $empresaDir;
    private string $empresaTel;

    public function __construct(array $empresa = [])
    {
        parent::__construct('P', 'mm', 'A4');
        $this->empresaNombre = $empresa['nombre'] ?? 'Emmanuels';
        $this->empresaRuc    = $empresa['ruc']    ?? '';
        $this->empresaDir    = $empresa['dir']    ?? 'Ruta Departamental D027, San Lorenzo - Paraguay';
        $this->empresaTel    = $empresa['tel']    ?? '+595 972 957381';
        $this->SetAutoPageBreak(true, 18);
        $this->SetMargins(15, 12, 15);
    }

    function Header() {}
    function Footer()
    {
        $this->SetY(-12);
        $this->SetDrawColor(...self::COLOR_BORDER);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(...self::COLOR_MUTED);
        $this->Cell(130, 5, fpy_convertir('Documento interno — Registro de compra en sistema Emmanuels'), 0, 0, 'L');
        $this->Cell(0, 5, fpy_convertir('Pág. ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    private function setText(): void
    {
        $this->SetTextColor(...self::COLOR_TEXT);
    }

    private function setMuted(): void
    {
        $this->SetTextColor(...self::COLOR_MUTED);
    }

    private function setBrand(): void
    {
        $this->SetTextColor(...self::COLOR_BRAND);
    }

    private function border(): void
    {
        $this->SetDrawColor(...self::COLOR_BORDER);
        $this->SetLineWidth(0.2);
    }

    /** Cabecera estilo factura paraguaya: emisor (proveedor) + recuadro timbrado */
    public function cabeceraFactura(array $emisor, array $doc, array $receptor): void
    {
        $x0 = 15;
        $y0 = 12;
        $anchoPag = 180;

        // --- Emisor (proveedor que emitió la factura) ---
        $this->SetXY($x0, $y0);
        $this->SetFont('Arial', 'B', 11);
        $this->setBrand();
        $this->MultiCell(108, 5, fpy_convertir($emisor['razon'] ?? ''), 0, 'L');

        $this->SetFont('Arial', '', 8);
        $this->setText();
        $this->SetX($x0);
        $this->Cell(108, 4, fpy_convertir('RUC: ' . ($emisor['ruc'] ?? '—')), 0, 1, 'L');
        if (!empty($emisor['direccion'])) {
            $this->SetX($x0);
            $this->Cell(108, 4, fpy_convertir($emisor['direccion']), 0, 1, 'L');
        }
        if (!empty($emisor['telefono'])) {
            $this->SetX($x0);
            $this->Cell(108, 4, fpy_convertir('Tel.: ' . $emisor['telefono']), 0, 1, 'L');
        }
        if (!empty($emisor['email'])) {
            $this->SetX($x0);
            $this->Cell(108, 4, fpy_convertir($emisor['email']), 0, 1, 'L');
        }

        // --- Recuadro documento (derecha) ---
        $boxX = 128;
        $boxY = $y0;
        $boxW = 67;
        $boxH = 38;

        $this->border();
        $this->SetDrawColor(...self::COLOR_BRAND);
        $this->SetLineWidth(0.5);
        $this->Rect($boxX, $boxY, $boxW, $boxH);
        $this->SetLineWidth(0.2);
        $this->border();

        $this->SetFillColor(...self::COLOR_BRAND);
        $this->Rect($boxX, $boxY, $boxW, 8, 'F');
        $this->SetXY($boxX, $boxY + 1);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($boxW, 6, fpy_convertir('FACTURA'), 0, 1, 'C');

        $this->setText();
        $this->SetFont('Arial', '', 7.5);
        $this->SetX($boxX + 2);
        $this->Cell($boxW - 4, 4, fpy_convertir('Timbrado N° ' . ($doc['timbrado'] ?? '—')), 0, 1, 'L');
        $this->SetX($boxX + 2);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell($boxW - 4, 5, fpy_convertir('N° ' . ($doc['numero'] ?? '—')), 0, 1, 'L');
        $this->SetFont('Arial', '', 7.5);
        $this->SetX($boxX + 2);
        $this->Cell($boxW - 4, 4, fpy_convertir('Fecha de emisión: ' . ($doc['fecha'] ?? '—')), 0, 1, 'L');
        $this->SetX($boxX + 2);
        $this->Cell($boxW - 4, 4, fpy_convertir('Condición: ' . ($doc['condicion'] ?? 'CONTADO')), 0, 1, 'L');
        if (!empty($doc['plazo']) && strtoupper($doc['condicion'] ?? '') === 'CREDITO') {
            $this->SetX($boxX + 2);
            $this->Cell($boxW - 4, 4, fpy_convertir('Plazo: ' . $doc['plazo']), 0, 1, 'L');
        }

        $this->Ln(4);

        // --- Datos del comprador / receptor ---
        $yRec = max($this->GetY(), $boxY + $boxH + 2);
        $this->SetY($yRec);
        $this->border();
        $this->SetFillColor(...self::COLOR_FILL);
        $this->SetFont('Arial', 'B', 8);
        $this->setBrand();
        $this->Cell($anchoPag, 5, fpy_convertir('  DATOS DEL COMPRADOR'), 1, 1, 'L', true);

        $this->SetFont('Arial', '', 8);
        $this->setText();
        $this->Cell(22, 5, fpy_convertir('Razón Social:'), 'L', 0, 'L');
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(88, 5, fpy_convertir($receptor['razon'] ?? $this->empresaNombre), 0, 0, 'L');
        $this->SetFont('Arial', '', 8);
        $this->Cell(12, 5, fpy_convertir('RUC:'), 0, 0, 'L');
        $this->Cell(58, 5, fpy_convertir($receptor['ruc'] ?: '—'), 'R', 1, 'L');

        $this->Cell(22, 5, fpy_convertir('Dirección:'), 'LB', 0, 'L');
        $this->Cell(70, 5, fpy_convertir($receptor['direccion'] ?? $this->empresaDir), 0, 0, 'L');
        $this->Cell(18, 5, fpy_convertir('Sucursal:'), 0, 0, 'L');
        $this->Cell(70, 5, fpy_convertir($receptor['sucursal'] ?? '—'), 'RB', 1, 'L');

        if (!empty($doc['oc'])) {
            $this->Cell(22, 5, fpy_convertir('OC N°:'), 'LB', 0, 'L');
            $this->Cell(40, 5, fpy_convertir((string) $doc['oc']), 0, 0, 'L');
            if (!empty($doc['nr'])) {
                $this->Cell(18, 5, fpy_convertir('NR N°:'), 0, 0, 'L');
                $this->Cell(30, 5, fpy_convertir((string) $doc['nr']), 0, 0, 'L');
            }
            $this->Cell(0, 5, '', 'RB', 1);
        }

        $this->Ln(3);
    }

    /** Tabla detalle con columnas de valor de venta por tipo de IVA */
    public function tablaDetalle(array $filas): void
    {
        $anchos = [11, 54, 22, 18, 19, 28, 28];
        $headers = [
            'Cant.',
            'Descripción',
            'P. Unit.',
            'Desc.',
            'Exentas',
            'Grav. 5%',
            'Grav. 10%',
        ];
        $aligns = ['C', 'L', 'R', 'R', 'R', 'R', 'R'];

        $this->SetFont('Arial', 'B', 7);
        $this->SetFillColor(...self::COLOR_FILL);
        $this->setText();
        $this->border();

        foreach ($headers as $i => $h) {
            $ln = ($i === count($headers) - 1) ? 1 : 0;
            $this->Cell($anchos[$i], 6, fpy_convertir($h), 1, $ln, $aligns[$i], true);
        }

        $this->SetDrawColor(...self::COLOR_BRAND);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->border();

        $this->SetFont('Arial', '', 7.5);
        foreach ($filas as $f) {
            $vals = [
                $f['cant'],
                $f['producto'],
                $f['precio'],
                $f['descuento'],
                $f['exentas'],
                $f['grav5'],
                $f['grav10'],
            ];
            $h = 5.5;
            if (strlen($f['producto']) > 42) {
                $h = 8;
            }
            foreach ($vals as $i => $v) {
                $ln = ($i === count($vals) - 1) ? 1 : 0;
                $this->Cell($anchos[$i], $h, fpy_convertir((string) $v), 1, $ln, $aligns[$i]);
            }
        }

        $this->Ln(2);
    }

    /** Bloque de subtotales, liquidación IVA y total a pagar */
    public function bloqueTotales(array $t): void
    {
        $xLabel = 108;
        $wLabel = 52;
        $wVal   = 35;
        $h      = 5;

        $this->SetFont('Arial', '', 8);
        $this->setText();
        $this->border();

        $fila = function (string $label, string $valor, bool $bold = false) use ($xLabel, $wLabel, $wVal, $h) {
            $this->SetX($xLabel);
            $this->SetFont('Arial', $bold ? 'B' : '', 8);
            $this->Cell($wLabel, $h, fpy_convertir($label), 0, 0, 'R');
            $this->Cell($wVal, $h, fpy_convertir($valor), 0, 1, 'R');
        };

        if (($t['sub_exentas'] ?? 0) > 0) {
            $fila('Subtotal Exentas:', fpy_gs($t['sub_exentas']) . ' Gs');
        }
        if (($t['sub_grav5'] ?? 0) > 0) {
            $fila('Subtotal Gravadas 5%:', fpy_gs($t['sub_grav5']) . ' Gs');
        }
        if (($t['sub_grav10'] ?? 0) > 0) {
            $fila('Subtotal Gravadas 10%:', fpy_gs($t['sub_grav10']) . ' Gs');
        }
        if (($t['descuento'] ?? 0) > 0) {
            $fila('Descuento total:', '-' . fpy_gs($t['descuento']) . ' Gs');
        }

        $this->Ln(1);
        $this->SetDrawColor(...self::COLOR_BRAND);
        $this->Line($xLabel, $this->GetY(), 195, $this->GetY());
        $this->border();
        $this->Ln(1);

        $fila('TOTAL A PAGAR:', fpy_gs($t['total']) . ' Gs', true);

        $this->Ln(3);
        $this->SetX($xLabel);
        $this->SetFont('Arial', 'B', 8);
        $this->setBrand();
        $this->Cell($wLabel + $wVal, 5, fpy_convertir('LIQUIDACIÓN DEL IVA'), 0, 1, 'R');

        $this->setText();
        $this->SetFont('Arial', '', 8);
        if (($t['iva5'] ?? 0) > 0) {
            $fila('IVA 5%:', fpy_gs($t['iva5']) . ' Gs');
            $fila('Base imponible 5%:', fpy_gs($t['base5'] ?? 0) . ' Gs');
        }
        if (($t['iva10'] ?? 0) > 0) {
            $fila('IVA 10%:', fpy_gs($t['iva10']) . ' Gs');
            $fila('Base imponible 10%:', fpy_gs($t['base10'] ?? 0) . ' Gs');
        }
        if (($t['iva_exento'] ?? 0) > 0) {
            $fila('Operaciones exentas:', fpy_gs($t['iva_exento']) . ' Gs');
        }

        $this->Ln(1);
        $fila('TOTAL IVA:', fpy_gs($t['iva_total']) . ' Gs', true);

        if (!empty($t['nota'])) {
            $this->Ln(4);
            $this->SetFont('Arial', 'I', 7);
            $this->setMuted();
            $this->MultiCell(180, 4, fpy_convertir($t['nota']), 0, 'L');
        }
    }
}

}
