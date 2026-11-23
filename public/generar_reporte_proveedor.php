<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acceso denegado. Inicia sesión primero.');
}

// ============================================================
// public/generar_reporte_proveedor.php
// Reporte de armazones agrupados por proveedor.
//
// CORRECCIONES:
//   - Muestra los armazones vinculados a cada proveedor
//     con su cantidad de armazones (registros) y valor total
//   - Armazones sin proveedor se agrupan en una fila aparte
//     al final del reporte en lugar de omitirse
//   - Columnas: PROVEEDOR | ARMAZONES | STOCK TOTAL | VALOR TOTAL
// ============================================================

require_once __DIR__ . '/../libs/fpdf/fpdf.php';
require_once __DIR__ . '/../config/database.php';

class PDFProveedor extends FPDF {

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
        $this->Cell(0, 5, utf8_decode('Reporte de Armazones por Proveedor'), 0, 1, 'C');
        $this->Ln(5);

        $this->SetFillColor(25, 135, 84);
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(70, 8, 'PROVEEDOR',         1, 0, 'C', true);
        $this->Cell(40, 8, 'ARMAZONES',         1, 0, 'C', true);
        $this->Cell(40, 8, 'STOCK TOTAL',       1, 0, 'C', true);
        $this->Cell(40, 8, 'VALOR TOTAL',       1, 1, 'C', true);
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

// ── Armazones CON proveedor ───────────────────────────────────
$where  = 'WHERE a.proveedor_id IS NOT NULL';
$params = [];

if (!empty($_GET['buscar'])) {
    $b = '%' . $_GET['buscar'] . '%';
    $where .= ' AND (a.codigo LIKE :b1 OR a.modelo LIKE :b2 OR a.descripcion LIKE :b3 OR p.nombre LIKE :b4)';
    $params['b1'] = $b; $params['b2'] = $b; $params['b3'] = $b; $params['b4'] = $b;
}
if (!empty($_GET['estado'])) {
    $estados_validos = ['disponible','baja','en_garantia','dañado','donado','inventario_muerto'];
    if (in_array($_GET['estado'], $estados_validos)) {
        $where .= ' AND a.estado = :estado';
        $params['estado'] = $_GET['estado'];
    }
}

$sql = "
    SELECT
        p.nombre                          AS PROVEEDOR,
        COUNT(a.id)                       AS ARMAZONES,
        COALESCE(SUM(a.cantidad_stock),0) AS STOCK,
        COALESCE(SUM(a.costo * a.cantidad_stock), 0) AS VALOR
    FROM armazones a
    INNER JOIN proveedores p ON p.id = a.proveedor_id
    $where
    GROUP BY p.id, p.nombre
    ORDER BY p.nombre ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Armazones SIN proveedor ───────────────────────────────────
$whereSin = 'WHERE a.proveedor_id IS NULL';
$paramsSin = [];
if (!empty($_GET['estado'])) {
    $estados_validos = ['disponible','baja','en_garantia','dañado','donado','inventario_muerto'];
    if (in_array($_GET['estado'], $estados_validos)) {
        $whereSin .= ' AND a.estado = :estado';
        $paramsSin['estado'] = $_GET['estado'];
    }
}
$stmtSin = $pdo->prepare("
    SELECT COUNT(a.id) AS ARMAZONES,
           COALESCE(SUM(a.cantidad_stock),0) AS STOCK,
           COALESCE(SUM(a.costo * a.cantidad_stock), 0) AS VALOR
    FROM armazones a
    $whereSin
");
$stmtSin->execute($paramsSin);
$sinProveedor = $stmtSin->fetch(PDO::FETCH_ASSOC);

// ── Generar PDF ───────────────────────────────────────────────
$pdf = new PDFProveedor('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

$widths = [70, 40, 40, 40];

if (empty($filas) && (int)$sinProveedor['ARMAZONES'] === 0) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, utf8_decode('No se encontraron registros.'), 0, 1, 'C');
} else {
    foreach ($filas as $i => $f) {
        $fill = ($i % 2 === 0);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Row([
            utf8_decode($f['PROVEEDOR']),
            utf8_decode((string)$f['ARMAZONES']),
            utf8_decode((string)$f['STOCK']),
            utf8_decode('$' . number_format((float)$f['VALOR'], 2)),
        ], $widths);
    }

    // Fila de armazones sin proveedor
    if ((int)$sinProveedor['ARMAZONES'] > 0) {
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->Row([
            utf8_decode('Sin proveedor asignado'),
            utf8_decode((string)$sinProveedor['ARMAZONES']),
            utf8_decode((string)$sinProveedor['STOCK']),
            utf8_decode('$' . number_format((float)$sinProveedor['VALOR'], 2)),
        ], $widths);
        $pdf->SetFont('Arial', '', 9);
    }

    // Totales
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 9);
    $totalArmazones = array_sum(array_column($filas, 'ARMAZONES')) + (int)$sinProveedor['ARMAZONES'];
    $totalValor     = array_sum(array_column($filas, 'VALOR'))     + (float)$sinProveedor['VALOR'];
    $pdf->Cell(0, 6,
        utf8_decode('Total de armazones: ' . $totalArmazones . '   |   Valor total: $' . number_format($totalValor, 2)),
        0, 1, 'R');
}

$pdf->Output('I', 'Reporte_Proveedor_' . date('Ymd') . '.pdf');