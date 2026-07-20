<?php
require_once __DIR__ . '/fpdf185/fpdf.php';

if (!function_exists('convertir')) {
    function convertir($texto) {
        return mb_convert_encoding((string) $texto, 'ISO-8859-1', 'UTF-8');
    }
}

if (!class_exists('BasePDF', false)) {

class BasePDF extends FPDF
{
    private const COLOR_BRAND      = [220, 38, 38];
    private const COLOR_BRAND_DARK = [153, 27, 27];
    private const COLOR_TEXT       = [55, 65, 81];
    private const COLOR_MUTED      = [107, 114, 128];
    private const COLOR_BORDER     = [229, 231, 235];
    private const COLOR_HEAD_TABLE = [249, 250, 251];

    private $empresa;
    private $ubicacion;
    private $telefono;
    private $correo;
    private $titulo;
    private $subtitulo;

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4', $opts = [])
    {
        parent::__construct($orientation, $unit, $size);
        $defaultTitulo = defined('BASE_PDF_TITLE') ? BASE_PDF_TITLE : 'Informe';
        $this->empresa   = $opts['empresa']   ?? 'Emmanuels';
        $this->ubicacion = $opts['ubicacion'] ?? 'Ruta Departamental D027, San Lorenzo - Paraguay';
        $this->telefono  = $opts['telefono']  ?? '+595 972 957381';
        $this->correo    = $opts['correo']    ?? 'emmanuels@gmail.com';
        $this->titulo    = $opts['titulo']    ?? $defaultTitulo;
        $this->subtitulo = $opts['subtitulo'] ?? '';
        $this->SetAutoPageBreak(true, 20);
        $this->SetMargins(15, 15, 15);
    }

    private function fill(array $rgb): void { $this->SetFillColor($rgb[0], $rgb[1], $rgb[2]); }
    private function textColor(array $rgb): void { $this->SetTextColor($rgb[0], $rgb[1], $rgb[2]); }
    private function draw(array $rgb): void { $this->SetDrawColor($rgb[0], $rgb[1], $rgb[2]); }

    private function alinearColumna(int $i, int $total, ?array $aligns): string
    {
        if ($aligns !== null && isset($aligns[$i])) {
            return $aligns[$i];
        }
        return ($i === 0 || $i === $total - 1) ? 'C' : 'L';
    }

    function Header()
    {
        $w = 180;

        $this->SetFont('Arial', 'B', 16);
        $this->textColor(self::COLOR_BRAND_DARK);
        $this->Cell($w, 8, convertir($this->empresa), 0, 1, 'L');

        $this->SetFont('Arial', '', 8);
        $this->textColor(self::COLOR_MUTED);
        $this->Cell($w, 4, convertir($this->ubicacion), 0, 1, 'L');
        $this->Cell($w, 4, convertir('Tel: ' . $this->telefono . '  |  ' . $this->correo), 0, 1, 'L');

        $this->Ln(2);
        $this->draw(self::COLOR_BRAND);
        $this->SetLineWidth(0.6);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->SetLineWidth(0.2);
        $this->Ln(4);

        $this->SetFont('Arial', 'B', 12);
        $this->textColor(self::COLOR_TEXT);
        $this->Cell($w, 7, convertir($this->titulo), 0, 1, 'L');

        if ($this->subtitulo !== '') {
            $this->SetFont('Arial', '', 9);
            $this->textColor(self::COLOR_MUTED);
            $this->Cell($w, 5, convertir($this->subtitulo), 0, 1, 'L');
        }

        $this->Ln(3);
        $this->textColor(self::COLOR_TEXT);
        $this->draw(self::COLOR_BORDER);
    }

    function Footer()
    {
        $this->SetY(-14);
        $this->draw(self::COLOR_BORDER);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->SetFont('Arial', 'I', 8);
        $this->textColor(self::COLOR_MUTED);
        $this->Cell(90, 8, convertir($this->empresa), 0, 0, 'L');
        $this->Cell(0, 8, convertir('Pag. ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    public function seccionTitulo(string $texto): void
    {
        $this->Ln(2);
        $this->SetFont('Arial', 'B', 10);
        $this->textColor(self::COLOR_BRAND_DARK);
        $this->Cell(0, 6, convertir($texto), 0, 1, 'L');
        $this->draw(self::COLOR_BORDER);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(3);
        $this->textColor(self::COLOR_TEXT);
    }

    public function filaDato(string $etiqueta, string $valor, float $labelW = 38): void
    {
        $this->SetFont('Arial', '', 9);
        $this->textColor(self::COLOR_MUTED);
        $this->Cell($labelW, 6, convertir($etiqueta), 0, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->textColor(self::COLOR_TEXT);
        $this->Cell(0, 6, convertir($valor), 0, 1, 'L');
    }

    public function prepararEncabezadoTabla(int $fontSize = 9): void
    {
        $this->SetFont('Arial', 'B', $fontSize);
        $this->fill(self::COLOR_HEAD_TABLE);
        $this->textColor(self::COLOR_TEXT);
        $this->draw(self::COLOR_BORDER);
    }

    public function prepararFilaTabla(int $fontSize = 9): void
    {
        $this->SetFont('Arial', '', $fontSize);
        $this->fill([255, 255, 255]);
        $this->textColor(self::COLOR_TEXT);
        $this->draw(self::COLOR_BORDER);
    }

    public function lineaBajoEncabezadoTabla(): void
    {
        $this->draw(self::COLOR_BRAND);
        $this->SetLineWidth(0.4);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->SetLineWidth(0.2);
        $this->draw(self::COLOR_BORDER);
    }

    public function tablaEncabezado(array $anchos, array $titulos, ?array $aligns = null): void
    {
        $this->prepararEncabezadoTabla();

        $n = count($anchos);
        for ($i = 0; $i < $n; $i++) {
            $ln = ($i === $n - 1) ? 1 : 0;
            $align = $this->alinearColumna($i, $n, $aligns);
            $this->Cell($anchos[$i], 7, convertir($titulos[$i]), 1, $ln, $align, true);
        }

        $this->lineaBajoEncabezadoTabla();
    }

    public function tablaFila(array $anchos, array $valores, ?array $aligns = null): void
    {
        $this->prepararFilaTabla();

        $n = count($anchos);
        for ($i = 0; $i < $n; $i++) {
            $ln = ($i === $n - 1) ? 1 : 0;
            $align = $this->alinearColumna($i, $n, $aligns);
            $this->Cell($anchos[$i], 6.5, convertir((string) $valores[$i]), 1, $ln, $align);
        }
    }

    public function tablaTotal(array $anchos, string $etiqueta, string $valor): void
    {
        $this->SetFont('Arial', 'B', 9);
        $this->fill(self::COLOR_HEAD_TABLE);
        $this->textColor(self::COLOR_TEXT);
        $this->draw(self::COLOR_BORDER);

        $anchoEtiqueta = array_sum(array_slice($anchos, 0, -1));
        $anchoValor = $anchos[count($anchos) - 1];
        $this->Cell($anchoEtiqueta, 7, convertir($etiqueta), 1, 0, 'R', true);
        $this->Cell($anchoValor, 7, convertir($valor), 1, 1, 'C', true);
    }

    public function pieDocumento(string $texto = 'Documento generado por el sistema de gestion Emmanuels.'): void
    {
        $this->Ln(6);
        $this->SetFont('Arial', 'I', 8);
        $this->textColor(self::COLOR_MUTED);
        $this->Cell(0, 5, convertir($texto), 0, 1, 'C');
    }
}

}
