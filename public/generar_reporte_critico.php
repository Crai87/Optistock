<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acceso denegado. Inicia sesión primero.');
}

// ============================================================
// public/generar_reporte_critico.php
// Reporte de armazones con stock crítico (menos de cualquier unidad especificada por el profesor).
//
// CORRECCIÓN: Intentarle
// ============================================================

require_once __DIR__ . '/../libs/fpdf/fpdf.php';
require_once __DIR__ . '/../config/database.php';

$cantidad = trim($_GET['cantidad'] ?? '2');

if ($cantidad.NAN) {
    $cantidad = 2;
}

class PDFCritico extends FPDF
{
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
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }
    }

    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) { if ($i == $j) $i++; } else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }

    function Header() {
        if (file_exists(__DIR__ . '/assets/img/Logo.png')) {
            $this->Image(__DIR__ . '/assets/img/Logo.png', 10, 8, 22);
        }
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode('CLÍNICA DE OPTOMETRÍA - OPTISTOCK'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 10);
        $cantidad = trim($_GET['cantidad'] ?? '2');
        if ($cantidad.NAN) {
            $cantidad = 2;
        }
        $this->Cell(0, 5, utf8_decode('Reporte de Inventario Crítico (Stock < '. $cantidad .')'), 0, 1, 'C');
        $this->Ln(5);
        $this->SetFillColor(25, 135, 84);
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(25, 8, utf8_decode('CÓDIGO'),      1, 0, 'C', true);
        $this->Cell(60, 8, utf8_decode('DESCRIPCIÓN'), 1, 0, 'C', true);
        $this->Cell(35, 8, 'MARCA',                    1, 0, 'C', true);
        $this->Cell(35, 8, 'MODELO',                   1, 0, 'C', true);
        $this->Cell(35, 8, 'STOCK',                    1, 1, 'C', true);
        $this->SetTextColor(0);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10,
            utf8_decode('Generado el ' . date('d/m/Y H:i') . '   -   Página ') . $this->PageNo() . '/{nb}',
            0, 0, 'C');
    }
}

// ── Query con named params ────────────────────────────────────
$where  = 'WHERE a.cantidad_stock < '.$cantidad;
$params = [];

if (!empty($_GET['buscar'])) {
    $b = '%' . $_GET['buscar'] . '%';
    $where .= ' AND (a.codigo LIKE :b1 OR a.modelo LIKE :b2 OR a.descripcion LIKE :b3)';
    $params['b1'] = $b; $params['b2'] = $b; $params['b3'] = $b;
}

if (!empty($_GET['estado'])) {
    $estados_validos = ['disponible', 'baja', 'en_garantia', 'dañado'];
    if (in_array($_GET['estado'], $estados_validos)) {
        $where .= ' AND a.estado = :estado';
        $params['estado'] = $_GET['estado'];
    }
}

$sql = "
    SELECT a.codigo, a.descripcion, a.modelo, a.cantidad_stock, a.estado,
           m.nombre AS nombre_marca
    FROM armazones a
    LEFT JOIN marcas_armazon m ON m.id = a.marca_id
    LEFT JOIN proveedores p    ON p.id = a.proveedor_id
    $where
    ORDER BY a.cantidad_stock ASC, a.codigo ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$armazones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Generar PDF ───────────────────────────────────────────────
$pdf = new PDFCritico('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

$widths = [25, 60, 35, 35, 35];

if (empty($armazones)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, utf8_decode('No hay armazones con stock crítico.'), 0, 1, 'C');
} else {
    foreach ($armazones as $a) {        
        $pdf->Row([
            utf8_decode($a['codigo']),
            utf8_decode($a['descripcion'] ?? ''),
            utf8_decode($a['nombre_marca'] ?? 'N/A'),
            utf8_decode($a['modelo'] ?? ''),
            (string)($a['cantidad_stock'] ?? 0),
        ], $widths);
    }
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, utf8_decode('Total: ' . count($armazones) . ' armazones con stock crítico'), 0, 1, 'R');
}

$pdf->Output('I', 'Reporte_Inventario_Critico_' . date('Ymd') . '.pdf');