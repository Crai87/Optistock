<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acceso denegado. Inicia sesión primero.');
}

// ============================================================
// public/generar_reporte_marca.php
// Reporte de armazones agrupados por marca.
//
// CORRECCIONES:
//   - STOCK muestra únicamente armazones con estado 'disponible'
//   - VALOR TOTAL se calcula solo sobre los armazones disponibles
//   - Se agrega columna TOTAL (todos los estados) para referencia
//   - Columnas: MARCA | DISPONIBLES | STOCK DISPONIBLE | VALOR DISPONIBLES | TOTAL ARMAZONES
// ============================================================

require_once __DIR__ . '/../libs/fpdf/fpdf.php';
require_once __DIR__ . '/../config/database.php';

class PDFMarca extends FPDF {

    function Row($data, $widths) {
        $nb = 0;
        for ($i = 0; $i < count($data); $i++) {
            $nb = max($nb, $this->NbLines($widths[$i], $data[$i]));
        }
        $h = 6 * $nb;
        $this->CheckPageBreak($h);
        for ($i = 0; $i < count($data); $i++) {
            $w = $widths[$i];
            $x = $this->GetX(); $y = $this->GetY();
            $this->Rect($x, $y, $w, $h);
            $this->MultiCell($w, 6, $data[$i], 0);
            $this->SetXY($x + $w, $y);
        }
        $this->Ln($h);
    }

    function CheckPageBreak($h) {
        if ($this->GetY() + $h > $this->PageBreakTrigger)
            $this->AddPage($this->CurOrientation);
    }

    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb-1] == "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") { $i++; $sep=-1; $j=$i; $l=0; $nl++; continue; }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) { if ($i == $j) $i++; } else $i = $sep + 1;
                $sep=-1; $j=$i; $l=0; $nl++;
            } else $i++;
        }
        return $nl;
    }

    function Header() {
        if (file_exists(__DIR__ . '/assets/img/Logo.png'))
            $this->Image(__DIR__ . '/assets/img/Logo.png', 10, 8, 22);

        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode('CLÍNICA DE OPTOMETRÍA - OPTISTOCK'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 5, utf8_decode('Reporte de Armazones por Marca'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100);
        $this->Cell(0, 4, utf8_decode('Stock y valor calculados únicamente sobre armazones disponibles'), 0, 1, 'C');
        $this->SetTextColor(0);
        $this->Ln(3);

        $this->SetFillColor(25, 135, 84);
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(50, 8, 'MARCA',                  1, 0, 'C', true);
        $this->Cell(35, 8, 'DISPONIBLES',             1, 0, 'C', true);
        $this->Cell(35, 8, 'STOCK DISPONIBLE',        1, 0, 'C', true);
        $this->Cell(45, 8, 'VALOR DISPONIBLES',       1, 0, 'C', true);
        $this->Cell(25, 8, 'TOTAL',                   1, 1, 'C', true);
        $this->SetTextColor(0);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10,
            utf8_decode('Generado el ' . date('d/m/Y H:i') . '   —   Página ') . $this->PageNo() . '/{nb}',
            0, 0, 'C');
    }
}

// ── Query: agrupa por marca, calcula disponibles y totales ───
$where  = 'WHERE 1=1';
$params = [];

if (!empty($_GET['buscar'])) {
    $b = '%' . $_GET['buscar'] . '%';
    $where .= ' AND (a.codigo LIKE :b1 OR a.modelo LIKE :b2 OR a.descripcion LIKE :b3 OR m.nombre LIKE :b4)';
    $params['b1'] = $b; $params['b2'] = $b; $params['b3'] = $b; $params['b4'] = $b;
}

$sql = "
    SELECT
        m.nombre AS MARCA,
        -- Solo armazones disponibles
        COUNT(CASE WHEN a.estado = 'disponible' THEN 1 END)                        AS DISPONIBLES,
        COALESCE(SUM(CASE WHEN a.estado = 'disponible' THEN a.cantidad_stock END),0) AS STOCK_DISP,
        COALESCE(SUM(CASE WHEN a.estado = 'disponible' THEN a.costo * a.cantidad_stock END),0) AS VALOR_DISP,
        -- Total de todos los estados
        COUNT(a.id)                                                                 AS TOTAL
    FROM armazones a
    INNER JOIN marcas_armazon m ON m.id = a.marca_id
    $where
    GROUP BY m.id, m.nombre
    ORDER BY m.nombre ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Generar PDF ───────────────────────────────────────────────
$pdf = new PDFMarca('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

$widths = [50, 35, 35, 45, 25];

if (empty($filas)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, utf8_decode('No se encontraron registros.'), 0, 1, 'C');
} else {
    foreach ($filas as $i => $f) {
        $fill = ($i % 2 === 0);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Row([
            utf8_decode($f['MARCA']),
            utf8_decode((string)$f['DISPONIBLES']),
            utf8_decode((string)$f['STOCK_DISP']),
            utf8_decode('$' . number_format((float)$f['VALOR_DISP'], 2)),
            utf8_decode((string)$f['TOTAL']),
        ], $widths);
    }

    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 9);
    $totalDisp  = array_sum(array_column($filas, 'DISPONIBLES'));
    $totalValor = array_sum(array_column($filas, 'VALOR_DISP'));
    $totalTodos = array_sum(array_column($filas, 'TOTAL'));
    $pdf->Cell(0, 6,
        utf8_decode("Disponibles: $totalDisp   |   Valor: \$" . number_format($totalValor, 2) . "   |   Total armazones: $totalTodos"),
        0, 1, 'R');
}

$pdf->Output('I', 'Reporte_Marca_' . date('Ymd') . '.pdf');