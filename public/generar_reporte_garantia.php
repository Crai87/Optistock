<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acceso denegado. Inicia sesión primero.');
}

/**
 * generar_reporte_garantia.php
 * ============================
 * Genera un PDF con los datos de una garantía específica usando FPDF.
 *
 * UBICACIÓN: /public/generar_reporte_garantia.php
 *
 * ¿POR QUÉ ESTÁ EN /public/?
 * Sigue el mismo patrón que generar_reporte.php. Los archivos de generación
 * de PDF se ubican en /public/ porque se abren directamente en una nueva
 * pestaña del navegador (window.open()). No pasan por index.php.
 *
 * ¿CÓMO FUNCIONA?
 * 1. Recibe el ID de la garantía vía GET (?id=N).
 * 2. Consulta la BD para obtener toda la información del folio con JOINs.
 * 3. Genera un PDF profesional con:
 *    - Logo y encabezado de la clínica
 *    - Datos del folio (código, armazón, marca, proveedor, fechas, estado)
 *    - Motivo reportado
 *    - Notas de resolución (si existen)
 * 4. Envía el PDF al navegador para visualizar o descargar.
 *
 * PARÁMETROS GET:
 * - id (obligatorio: ID numérico del folio de garantía)
 */

// ── 1. Importar dependencias ────────────────────────────────────────────────
// FPDF está en /libs/fpdf/. Se sube un nivel desde /public/ con '/../'.
// database.php proporciona la conexión PDO ($pdo).
require_once __DIR__ . '/../libs/fpdf/fpdf.php';
require_once __DIR__ . '/../config/database.php';

// ── 2. Clase PDF personalizada ──────────────────────────────────────────────
// Se extiende FPDF para definir Header y Footer personalizados.
// Esto asegura que cada página tenga el mismo encabezado y pie.
class PDF extends FPDF
{

    /**
     * Header() — Se ejecuta automáticamente al inicio de cada página.
     * Dibuja el logo, el título de la clínica y el subtítulo del reporte.
     */
    function Header()
    {
        // Logo — Se verifica que exista el archivo antes de intentar cargarlo
        if (file_exists('assets/img/Logo.png')) {
            $this->Image('assets/img/Logo.png', 10, 8, 22);
        }

        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode('CLÍNICA DE OPTOMETRÍA - OPTISTOCK'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 5, utf8_decode('Reporte de Garantía'), 0, 1, 'C');
        $this->Ln(10);
    }

    /**
     * Footer() — Se ejecuta automáticamente al final de cada página.
     * Muestra el número de página.
     */
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// ── 3. Validar parámetro ID ─────────────────────────────────────────────────
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    die('ID de garantía no proporcionado o inválido.');
}

// ── 4. Consultar datos de la garantía ───────────────────────────────────────
// Se hace JOIN con armazones, marcas_armazon y proveedores para obtener
// los nombres en lugar de solo los IDs.
$stmt = $pdo->prepare("
    SELECT
        fg.*,
        a.modelo   AS nombre_armazon,
        a.codigo   AS codigo_armazon,
        ma.nombre  AS nombre_marca,
        p.nombre   AS nombre_proveedor
    FROM folios_garantia fg
    LEFT JOIN armazones a      ON a.id  = fg.armazon_id
    LEFT JOIN marcas_armazon ma ON ma.id = a.marca_id
    LEFT JOIN proveedores p    ON p.id  = fg.proveedor_id
    WHERE fg.id = :id
");
$stmt->execute(['id' => $id]);
$g = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$g) {
    die('Folio de garantía no encontrado.');
}

// ── 5. Mapear estados a etiquetas legibles ──────────────────────────────────
$etiquetas_estado = [
    'abierto' => 'Abierto',
    'enviado_proveedor' => 'Enviado a proveedor',
    'resuelto' => 'Resuelto',
    'rechazado' => 'Rechazado',
];

$estado_texto = $etiquetas_estado[$g['estado']] ?? $g['estado'];

// ── 6. Generar el PDF ───────────────────────────────────────────────────────
$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();

// ── 6a. Sección: Datos del folio ────────────────────────────────────────────
// Se usa SetFillColor para crear una barra de color como encabezado de sección.
$pdf->SetFillColor(25, 135, 84); // Verde institucional
$pdf->SetTextColor(255);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, utf8_decode(' Datos del Folio de Garantía'), 0, 1, 'L', true);
$pdf->SetTextColor(0);
$pdf->Ln(4);

// ── 6b. Tabla de datos ──────────────────────────────────────────────────────
// Cada fila tiene una etiqueta (gris) y un valor (blanco).
$pdf->SetFont('Arial', 'B', 10);
$campos = [
    'Folio' => $g['folio'],
    'Armazón' => ($g['nombre_armazon'] ?? 'N/A') . ' - ' . ($g['nombre_marca'] ?? 'N/A'),
    'Código armazón' => $g['codigo_armazon'] ?? 'N/A',
    'Proveedor' => $g['nombre_proveedor'] ?? 'Sin asignar',
    'Estado' => $estado_texto,
    'Fecha de apertura' => $g['fecha_apertura'] ?? '-',
    'Fecha de resolución' => $g['fecha_resolucion'] ?? '-',
];

$pdf->SetFillColor(240, 240, 240);
$i = 0;
foreach ($campos as $label => $valor) {
    $fill = ($i % 2 === 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(55, 7, utf8_decode($label . ':'), 1, 0, 'L', $fill);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 7, utf8_decode($valor), 1, 1, 'L', $fill);
    $i++;
}

$pdf->Ln(6);

// ── 6c. Sección: Motivo reportado ───────────────────────────────────────────
$pdf->SetFillColor(25, 135, 84);
$pdf->SetTextColor(255);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, utf8_decode(' Motivo Reportado'), 0, 1, 'L', true);
$pdf->SetTextColor(0);
$pdf->Ln(3);

$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 6, utf8_decode($g['motivo'] ?? 'Sin motivo registrado.'));

// ── 6d. Sección: Resolución (solo si existe) ────────────────────────────────
if (!empty($g['resolucion'])) {
    $pdf->Ln(4);
    $pdf->SetFillColor(25, 135, 84);
    $pdf->SetTextColor(255);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, utf8_decode(' Resolución / Notas'), 0, 1, 'L', true);
    $pdf->SetTextColor(0);
    $pdf->Ln(3);

    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 6, utf8_decode($g['resolucion']));
}

// ── 6e. Pie con fecha de generación ─────────────────────────────────────────
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(0, 6, utf8_decode('Reporte generado el: ' . date('d/m/Y H:i:s')), 0, 1, 'R');

// ── 7. Enviar el PDF al navegador ───────────────────────────────────────────
// 'I' = Inline: muestra en el navegador. 'D' = forzar descarga.
$pdf->Output('I', 'Garantia_' . $g['folio'] . '.pdf');