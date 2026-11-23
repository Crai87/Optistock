<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acceso denegado. Inicia sesión primero.');
}

// ============================================================
// public/generar_reporte_edad.php
// Reporte de antigüedad de armazones en inventario.
// Clasifica por tiempo desde created_at:
//   < 90 días  → Activo
//   < 150 días → Lento
//   < 399 días → Viejo
//   >= 399 días → Inventario Muerto
//
// CORRECCIÓN: parámetros unificados a named params (:b1, :estado)
// ============================================================

require_once __DIR__ . '/../libs/fpdf/fpdf.php';
require_once __DIR__ . '/../config/database.php';

class PDFEdad extends FPDF
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
        $this->Cell(0, 5, utf8_decode('Reporte de Antigüedad de Armazones'), 0, 1, 'C');
        $this->Ln(5);
        $this->SetFillColor(25, 135, 84);
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(25, 8, utf8_decode('CÓDIGO'),      1, 0, 'C', true);
        $this->Cell(50, 8, utf8_decode('DESCRIPCIÓN'), 1, 0, 'C', true);
        $this->Cell(30, 8, 'MARCA',                    1, 0, 'C', true);
        $this->Cell(35, 8, 'MODELO',                   1, 0, 'C', true);
        $this->Cell(20, 8, utf8_decode('DÍAS'),        1, 0, 'C', true);
        $this->Cell(35, 8, utf8_decode('CLASIFICACIÓN'), 1, 1, 'C', true);
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
$where  = 'WHERE 1=1';
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
    SELECT a.codigo, a.descripcion, a.modelo, a.estado, a.created_at,
           m.nombre AS nombre_marca
    FROM armazones a
    LEFT JOIN marcas_armazon m ON m.id = a.marca_id
    $where
    ORDER BY a.created_at ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$armazones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Generar PDF ───────────────────────────────────────────────
$pdf = new PDFEdad('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

$widths = [25, 50, 30, 35, 20, 35];

// Contadores por clasificación para resumen
$resumen = ['Activo' => 0, 'Lento' => 0, 'Viejo' => 0, 'Inventario Muerto' => 0];

if (empty($armazones)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, utf8_decode('No se encontraron armazones.'), 0, 1, 'C');
} else {
    $today = new DateTime();
    foreach ($armazones as $a) {
        $start = new DateTime($a['created_at']);
        $age   = $today->diff($start);
        $dias  = intval($age->format('%a'));

        if ($dias < 90)       $period = 'Activo';
        elseif ($dias < 182)  $period = 'Lento';
        elseif ($dias < 365)  $period = 'Viejo';
        else                  $period = 'Inventario Muerto';

        $resumen[$period]++;

        $pdf->Row([
            utf8_decode($a['codigo']),
            utf8_decode($a['descripcion'] ?? ''),
            utf8_decode($a['nombre_marca'] ?? 'N/A'),
            utf8_decode($a['modelo'] ?? ''),
            utf8_decode($dias . ' días'),
            utf8_decode($period),
        ], $widths);
    }

    // Resumen al final
    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, utf8_decode('Resumen:'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    foreach ($resumen as $clase => $cant) {
        if ($cant > 0) {
            $pdf->Cell(0, 5, utf8_decode("  $clase: $cant armazón(es)"), 0, 1, 'L');
        }
    }
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, utf8_decode('Total: ' . count($armazones) . ' armazones'), 0, 1, 'R');
}

$pdf->Output('I', 'Reporte_Inventario_Edad_' . date('Ymd') . '.pdf');