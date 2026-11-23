<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acceso denegado. Inicia sesión primero.');
}

// ============================================================
// public/generar_reporte.php
// Genera PDF del inventario de armazones con filtros.
//
// CORRECCIONES:
//   - Todos los parámetros son named (:b1, :estado) — antes
//     mezclaba ? con named params lo que rompía PDO con dos filtros
//   - Búsqueda ahora incluye nombre_marca igual que inventario.php
//   - Se agregaron columnas: Área, Material, Costo
//   - Título del filtro aplicado en el encabezado del PDF
// ============================================================

require_once __DIR__ . '/../libs/fpdf/fpdf.php';
require_once __DIR__ . '/../config/database.php';

$buscar = trim($_GET['buscar'] ?? '');
$estado = trim($_GET['estado'] ?? '');

$estados_validos = ['disponible', 'baja', 'en_garantia', 'dañado', 'donado', 'inventario_muerto'];
if ($estado && !in_array($estado, $estados_validos)) {
    $estado = '';
}

class PDF extends FPDF
{
    public $filtroTexto = '';

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

    function Header()
    {
        if (file_exists(__DIR__ . '/assets/img/Logo.png')) {
            $this->Image(__DIR__ . '/assets/img/Logo.png', 10, 8, 22);
        }
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode('CLÍNICA DE OPTOMETRÍA - OPTISTOCK'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 5, utf8_decode('Reporte de Inventario de Armazones'), 0, 1, 'C');
        if ($this->filtroTexto) {
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(100);
            $this->Cell(0, 5, utf8_decode('Filtros: ' . $this->filtroTexto), 0, 1, 'C');
            $this->SetTextColor(0);
        }
        $this->Ln(4);
        $this->SetFillColor(25, 135, 84);
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(22, 8, utf8_decode('CÓDIGO'),      1, 0, 'C', true);
        $this->Cell(20, 8, utf8_decode('ÁREA'),        1, 0, 'C', true);
        $this->Cell(28, 8, 'MARCA',                    1, 0, 'C', true);
        $this->Cell(28, 8, 'MODELO',                   1, 0, 'C', true);
        $this->Cell(22, 8, 'MATERIAL',                 1, 0, 'C', true);
        $this->Cell(50, 8, utf8_decode('DESCRIPCIÓN'), 1, 0, 'C', true);
        $this->Cell(18, 8, 'COSTO',                    1, 0, 'C', true);
        $this->Cell(17, 8, 'STOCK',                    1, 0, 'C', true);
        $this->Cell(27, 8, 'ESTADO',                   1, 1, 'C', true);
        $this->SetTextColor(0);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10,
            utf8_decode('Generado el ' . date('d/m/Y H:i') . '   —   Página ') . $this->PageNo() . '/{nb}',
            0, 0, 'C');
    }
}

$where  = 'WHERE 1=1';
$params = [];

if ($buscar !== '') {
    $b = '%' . $buscar . '%';
    $where .= ' AND (
        a.codigo      LIKE :b1 OR
        a.descripcion LIKE :b2 OR
        a.area        LIKE :b3 OR
        m.nombre      LIKE :b4 OR
        a.material    LIKE :b5 OR
        a.modelo      LIKE :b6 OR
        p.nombre      LIKE :b7 OR
        CAST(a.costo AS CHAR) LIKE :b8
    )';
    $params['b1'] = $b; $params['b2'] = $b;
    $params['b3'] = $b; $params['b4'] = $b;
    $params['b5'] = $b; $params['b6'] = $b;
    $params['b7'] = $b; $params['b8'] = $b;
}

if ($estado !== '') {
    $where .= ' AND a.estado = :estado';
    $params['estado'] = $estado;
}

$sql = "
    SELECT a.codigo, a.area, a.descripcion, a.material, a.modelo,
           a.cantidad_stock, a.costo, a.estado,
           m.nombre AS nombre_marca
    FROM armazones a
    LEFT JOIN marcas_armazon m ON m.id = a.marca_id
    LEFT JOIN proveedores p    ON p.id = a.proveedor_id
    $where
    ORDER BY a.codigo ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$armazones = $stmt->fetchAll(PDO::FETCH_ASSOC);

$partesFiltro = [];
if ($buscar) $partesFiltro[] = 'Búsqueda: "' . $buscar . '"';
if ($estado) {
    $etiquetaFiltro = match($estado) {
        'inventario_muerto' => 'Inventario muerto',
        'en_garantia'       => 'En garantía',
        default             => ucfirst(str_replace('_', ' ', $estado)),
    };
    $partesFiltro[] = 'Estado: ' . $etiquetaFiltro;
}
$filtroTexto = $partesFiltro ? implode(' | ', $partesFiltro) : 'Todos los registros';

$pdf = new PDF('L', 'mm', 'A4');
$pdf->filtroTexto = $filtroTexto;
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

$widths = [22, 20, 28, 28, 22, 50, 18, 17, 27];


if (empty($armazones)) {
    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, utf8_decode('No se encontraron registros para los filtros aplicados.'), 0, 1, 'C');
} else {
    foreach ($armazones as $i => $a) {
        $fill = ($i % 2 === 0);
        $pdf->SetFillColor(245, 245, 245);
        $etiquetaEstado = match(strtolower($a['estado'] ?? '')) {
            'inventario_muerto' => 'Inv. Muerto',
            'en_garantia'       => 'En Garantia',
            default             => ucfirst(str_replace('_', ' ', $a['estado'] ?? '')),
        };
        $pdf->Row([
            utf8_decode($a['codigo']),
            utf8_decode($a['area'] ?? ''),
            utf8_decode($a['nombre_marca'] ?? 'N/A'),
            utf8_decode($a['modelo'] ?? ''),
            utf8_decode($a['material'] ?? ''),
            utf8_decode($a['descripcion'] ?? ''),
            utf8_decode("$".$a['costo'] ?? '0'),
            utf8_decode($a['cantidad_stock'] ?? ''),
            utf8_decode($etiquetaEstado),
        ], $widths);
    }
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(0, 6, utf8_decode('Total de registros: ' . count($armazones)), 0, 1, 'R');
}

$pdf->Output('I', 'Reporte_Inventario_' . date('Ymd') . '.pdf');