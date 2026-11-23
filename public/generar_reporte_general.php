<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acceso denegado. Inicia sesión primero.');
}

// ============================================================
// public/generar_reporte_general.php
// Reporte resumen general del inventario de armazones.
//
// CORRECCIÓN:
//   - Las columnas UNITS (count = total modelos/productos distintos)
//     y PRODUCTS (sum stock = total unidades físicas) estaban
//     invertidas en la query original.
// ============================================================

require_once __DIR__ . '/../libs/fpdf/fpdf.php';
require_once __DIR__ . '/../config/database.php';

class PDFGeneral extends FPDF {

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
        $this->Cell(0, 5, utf8_decode('Reporte Resumen General de Armazones'), 0, 1, 'C');
        $this->Ln(5);

        $this->SetFillColor(25, 135, 84);
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(60, 8, 'TOTAL PRODUCTOS',  1, 0, 'C', true);
        $this->Cell(60, 8, 'TOTAL UNIDADES',   1, 0, 'C', true);
        $this->Cell(60, 8, 'VALOR INVENTARIO', 1, 1, 'C', true);
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

// ── Query con params nombrados ────────────────────────────────
// TOTAL PRODUCTOS = COUNT(id)  → cantidad de registros (modelos distintos)
// TOTAL UNIDADES  = SUM(cantidad_stock) → unidades físicas en inventario
// VALOR           = SUM(costo * cantidad_stock)
$where  = 'WHERE 1=1';
$params = [];

if (!empty($_GET['buscar'])) {
    $b = '%' . $_GET['buscar'] . '%';
    $where .= ' AND (codigo LIKE :b1 OR modelo LIKE :b2 OR descripcion LIKE :b3)';
    $params['b1'] = $b; $params['b2'] = $b; $params['b3'] = $b;
}
if (!empty($_GET['estado'])) {
    $where .= ' AND LOWER(estado) = :estado';
    $params['estado'] = strtolower($_GET['estado']);
}

$sql = "SELECT
    COUNT(id)                      AS TOTAL_PRODUCTOS,
    COALESCE(SUM(cantidad_stock),0) AS TOTAL_UNIDADES,
    COALESCE(SUM(costo * cantidad_stock), 0) AS TOTAL_VALOR
FROM armazones $where";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$pdf = new PDFGeneral('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

$widths = [60, 60, 60];

$pdf->Row([
    utf8_decode((string)$row['TOTAL_PRODUCTOS']),
    utf8_decode((string)$row['TOTAL_UNIDADES']),
    utf8_decode('$' . number_format((float)$row['TOTAL_VALOR'], 2)),
], $widths);

$pdf->Output('I', 'Reporte_General_' . date('Ymd') . '.pdf');