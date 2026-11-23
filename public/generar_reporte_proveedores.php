<?php
// ============================================================
// public/generar_reporte_proveedores.php
// Genera PDF con todos los proveedores registrados en el sistema.
// Se abre en nueva pestaña desde admin.php (catalogs tab).
// ============================================================

require_once __DIR__ . '/../libs/fpdf/fpdf.php';
require_once __DIR__ . '/../config/database.php';

class PDFProveedores extends FPDF
{
    function Header()
    {
        if (file_exists(__DIR__ . '/assets/img/Logo.png')) {
            $this->Image(__DIR__ . '/assets/img/Logo.png', 10, 8, 22);
        }
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode('CLÍNICA DE OPTOMETRÍA - OPTISTOCK'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 5, utf8_decode('Reporte de Proveedores Registrados'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100);
        $this->Cell(0, 4, utf8_decode('Generado el ' . date('d/m/Y H:i')), 0, 1, 'C');
        $this->SetTextColor(0);
        $this->Ln(3);

        // Cabecera de tabla
        $this->SetFillColor(25, 135, 84);
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(8,  8, '#',                        1, 0, 'C', true);
        $this->Cell(60, 8, 'NOMBRE',                   1, 0, 'C', true);
        $this->Cell(35, 8, utf8_decode('TELÉFONO'),    1, 0, 'C', true);
        $this->Cell(65, 8, 'EMAIL',                    1, 0, 'C', true);
        $this->Cell(22, 8, 'ARMAZONES',                1, 0, 'C', true);
        $this->Cell(22, 8, utf8_decode('GARANTÍAS'),   1, 1, 'C', true);
        $this->SetTextColor(0);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10,
            utf8_decode('Página ') . $this->PageNo() . '/{nb}',
            0, 0, 'C');
    }
}

// ── Consultar proveedores con conteos ───────────────────────
$sql = "
    SELECT p.id, p.nombre, p.telefono, p.email,
           COUNT(DISTINCT a.id)  AS total_armazones,
           COUNT(DISTINCT fg.id) AS total_garantias
    FROM proveedores p
    LEFT JOIN armazones a       ON a.proveedor_id  = p.id
    LEFT JOIN folios_garantia fg ON fg.proveedor_id = p.id
    GROUP BY p.id
    ORDER BY p.nombre ASC
";
$proveedores = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// ── Generar PDF ──────────────────────────────────────────────
$pdf = new PDFProveedores('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

if (empty($proveedores)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, utf8_decode('No hay proveedores registrados en el sistema.'), 0, 1, 'C');
} else {
    foreach ($proveedores as $i => $p) {
        $fill = ($i % 2 === 0);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell(8,  6, (string)($i + 1),                                         1, 0, 'C', $fill);
        $pdf->Cell(60, 6, utf8_decode(mb_strimwidth($p['nombre'],   0, 40, '…')),   1, 0, 'L', $fill);
        $pdf->Cell(35, 6, utf8_decode($p['telefono'] ?? '—'),                       1, 0, 'C', $fill);
        $pdf->Cell(65, 6, utf8_decode(mb_strimwidth($p['email'] ?? '—', 0, 45, '…')), 1, 0, 'L', $fill);
        $pdf->Cell(22, 6, (string)$p['total_armazones'],                             1, 0, 'C', $fill);
        $pdf->Cell(22, 6, (string)$p['total_garantias'],                             1, 1, 'C', $fill);
    }

    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, utf8_decode('Total de proveedores: ' . count($proveedores)), 0, 1, 'R');
}

$pdf->Output('I', 'Reporte_Proveedores_' . date('Ymd') . '.pdf');