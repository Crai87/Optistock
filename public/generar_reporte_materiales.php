<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acceso denegado. Inicia sesión primero.');
}

// ============================================================
// public/generar_reporte_materiales.php
// Genera PDF del inventario de equipos clínicos o inmobiliario.
// Se abre en nueva pestaña desde Inventario_materiales.php.
//
// Parámetros GET:
//   tab    = 'equipos' | 'inmobiliario'
//   buscar = texto de búsqueda (opcional)
//   estado = filtro de estado (opcional)
// ============================================================

require_once __DIR__ . '/../libs/fpdf/fpdf.php';
require_once __DIR__ . '/../config/database.php';

$tab    = $_GET['tab']    ?? 'equipos';
$buscar = trim($_GET['buscar'] ?? '');
$estado = trim($_GET['estado'] ?? '');

if (!in_array($tab, ['equipos', 'inmobiliario'])) $tab = 'equipos';

// ── Clase PDF ────────────────────────────────────────────────
class PDF extends FPDF
{
    public $filtroTexto  = '';
    public $subtitulo    = '';

    function Header()
    {
        if (file_exists(__DIR__ . '/assets/img/Logo.png')) {
            $this->Image(__DIR__ . '/assets/img/Logo.png', 10, 8, 22);
        }
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode('CLÍNICA DE OPTOMETRÍA - OPTISTOCK'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 5, utf8_decode($this->subtitulo), 0, 1, 'C');
        if ($this->filtroTexto) {
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(100);
            $this->Cell(0, 5, utf8_decode('Filtros: ' . $this->filtroTexto), 0, 1, 'C');
            $this->SetTextColor(0);
        }
        $this->Ln(4);
        $this->cabeceraTabla();
    }

    function cabeceraTabla() {}  // se sobreescribe según el tipo

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10,
            utf8_decode('Generado el ' . date('d/m/Y H:i') . '   -   Página ') . $this->PageNo() . '/{nb}',
            0, 0, 'C');
    }
}

// ── Subclase para equipos ────────────────────────────────────
class PDFEquipos extends PDF
{
    function cabeceraTabla()
    {
        $this->SetFillColor(25, 135, 84);
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(25, 8, utf8_decode('CÓDIGO'),    1, 0, 'C', true);
        $this->Cell(55, 8, 'NOMBRE',                 1, 0, 'C', true);
        $this->Cell(30, 8, 'MARCA',                  1, 0, 'C', true);
        $this->Cell(30, 8, 'MODELO',                 1, 0, 'C', true);
        $this->Cell(20, 8, 'TOTAL',                  1, 0, 'C', true);
        $this->Cell(20, 8, 'SERVICIO',               1, 0, 'C', true);
        $this->Cell(20, 8, 'MANT.',                  1, 0, 'C', true);
        $this->Cell(27, 8, 'ESTADO',                 1, 1, 'C', true);
        $this->SetTextColor(0);
    }
}

// ── Subclase para inmobiliario ───────────────────────────────
class PDFInmobiliario extends PDF
{
    function cabeceraTabla()
    {
        $this->SetFillColor(25, 135, 84);
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(28, 8, utf8_decode('CÓDIGO'),    1, 0, 'C', true);
        $this->Cell(65, 8, 'NOMBRE',                 1, 0, 'C', true);
        $this->Cell(40, 8, utf8_decode('CATEGORÍA'), 1, 0, 'C', true);
        $this->Cell(20, 8, 'CANTIDAD',               1, 0, 'C', true);
        $this->Cell(35, 8, utf8_decode('UBICACIÓN'), 1, 0, 'C', true);
        $this->Cell(39, 8, 'ESTADO',                 1, 1, 'C', true);
        $this->SetTextColor(0);
    }
}

// ── Filtros de texto descriptivo ─────────────────────────────
$partesFiltro = [];
if ($buscar) $partesFiltro[] = 'Búsqueda: "' . $buscar . '"';
if ($estado) $partesFiltro[] = 'Estado: ' . ucfirst(str_replace('_', ' ', $estado));
$filtroTexto = $partesFiltro ? implode(' | ', $partesFiltro) : 'Todos los registros';

// ════════════════════════════════════════════════════════════
// REPORTE EQUIPOS CLÍNICOS
// ════════════════════════════════════════════════════════════
if ($tab === 'equipos') {

    $where  = 'WHERE 1=1';
    $params = [];

    if ($buscar !== '') {
        $b = '%' . $buscar . '%';
        $where .= ' AND (
            ec.codigo                             LIKE :b1 OR
            ec.nombre                             LIKE :b2 OR
            me.nombre                             LIKE :b3 OR
            ec.modelo                             LIKE :b4 OR
            CAST(ec.cantidad_servicio      AS CHAR) LIKE :b5 OR
            CAST(ec.cantidad_mantenimiento AS CHAR) LIKE :b6 OR
            ec.estado                             LIKE :b7
        )';
        $params['b1'] = $b; $params['b2'] = $b;
        $params['b3'] = $b; $params['b4'] = $b;
        $params['b5'] = $b; $params['b6'] = $b;
        $params['b7'] = $b;
    }
    if ($estado !== '') {
        $estados_eq = ['activo','en_mantenimiento','baja'];
        if (in_array($estado, $estados_eq)) {
            $where .= ' AND ec.estado = :estado';
            $params['estado'] = $estado;
        }
    }

    $sql = "
        SELECT ec.codigo, ec.nombre, ec.descripcion, ec.modelo,
               ec.cantidad_total, ec.cantidad_servicio, ec.cantidad_mantenimiento,
               ec.estado,
               COALESCE(me.nombre,'S/M') AS marca
        FROM equipos_clinicos ec
        LEFT JOIN marcas_equipos me ON me.id = ec.marca_id
        $where
        ORDER BY ec.nombre ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new PDFEquipos('L', 'mm', 'A4');
    $pdf->subtitulo   = 'Reporte de Equipos Clínicos';
    $pdf->filtroTexto = $filtroTexto;
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 8);

    if (empty($registros)) {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 10, utf8_decode('No se encontraron registros.'), 0, 1, 'C');
    } else {
        foreach ($registros as $i => $r) {
            $fill = ($i % 2 === 0);
            $pdf->SetFillColor(245, 245, 245);
            $estadoLabel = match($r['estado']) {
                'activo'           => 'Activo',
                'en_mantenimiento' => 'En mantenimiento',
                'baja'             => 'Baja',
                default            => $r['estado'],
            };
            $pdf->Cell(25, 6, utf8_decode($r['codigo']),                              1, 0, 'L', $fill);
            $pdf->Cell(55, 6, utf8_decode(mb_strimwidth($r['nombre'], 0, 40, '…')),   1, 0, 'L', $fill);
            $pdf->Cell(30, 6, utf8_decode($r['marca']),                               1, 0, 'L', $fill);
            $pdf->Cell(30, 6, utf8_decode($r['modelo'] ?? '—'),                       1, 0, 'L', $fill);
            $pdf->Cell(20, 6, (string)$r['cantidad_total'],                            1, 0, 'C', $fill);
            $pdf->Cell(20, 6, (string)$r['cantidad_servicio'],                         1, 0, 'C', $fill);
            $pdf->Cell(20, 6, (string)$r['cantidad_mantenimiento'],                    1, 0, 'C', $fill);
            $pdf->Cell(27, 6, utf8_decode($estadoLabel),                              1, 1, 'C', $fill);
        }
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(0, 6, utf8_decode('Total de registros: ' . count($registros)), 0, 1, 'R');
    }

    $pdf->Output('I', 'Reporte_Equipos_' . date('Ymd') . '.pdf');
    exit;
}

// ════════════════════════════════════════════════════════════
// REPORTE INMOBILIARIO
// ════════════════════════════════════════════════════════════
$where  = 'WHERE 1=1';
$params = [];

if ($buscar !== '') {
    $b = '%' . $buscar . '%';
    $where .= ' AND (
        i.codigo    LIKE :b1 OR
        i.nombre    LIKE :b2 OR
        ci.nombre   LIKE :b3 OR
        i.estado    LIKE :b4 OR
        i.ubicacion LIKE :b5
    )';
    $params['b1'] = $b; $params['b2'] = $b;
    $params['b3'] = $b; $params['b4'] = $b;
    $params['b5'] = $b;
}
if ($estado !== '') {
    $estados_in = ['activo','en_espera','en_reparacion','baja'];
    if (in_array($estado, $estados_in)) {
        $where .= ' AND i.estado = :estado';
        $params['estado'] = $estado;
    }
}

$sql = "
    SELECT i.codigo, i.nombre, i.descripcion, i.cantidad,
           i.estado, i.ubicacion,
           COALESCE(ci.nombre,'Sin categoría') AS categoria
    FROM inmobiliario i
    LEFT JOIN categorias_inmobiliario ci ON ci.id = i.categoria_id
    $where
    ORDER BY ci.nombre ASC, i.nombre ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pdf = new PDFInmobiliario('L', 'mm', 'A4');
$pdf->subtitulo   = utf8_decode('Reporte de Inmobiliario');
$pdf->filtroTexto = $filtroTexto;
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

if (empty($registros)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, utf8_decode('No se encontraron registros.'), 0, 1, 'C');
} else {
    foreach ($registros as $i => $r) {
        $fill = ($i % 2 === 0);
        $pdf->SetFillColor(245, 245, 245);
        $estadoLabel = match($r['estado']) {
            'activo'        => 'Activo',
            'en_espera'     => 'En espera',
            'en_reparacion' => 'En reparación',
            'baja'          => 'Baja',
            default         => $r['estado'],
        };
        $pdf->Cell(28, 6, utf8_decode($r['codigo']),                                1, 0, 'L', $fill);
        $pdf->Cell(65, 6, utf8_decode(mb_strimwidth($r['nombre'], 0, 50, '…')),     1, 0, 'L', $fill);
        $pdf->Cell(40, 6, utf8_decode($r['categoria']),                             1, 0, 'L', $fill);
        $pdf->Cell(20, 6, (string)$r['cantidad'],                                   1, 0, 'C', $fill);
        $pdf->Cell(35, 6, utf8_decode(mb_strimwidth($r['ubicacion'] ?? '—', 0, 25, '…')), 1, 0, 'L', $fill);
        $pdf->Cell(39, 6, utf8_decode($estadoLabel),                               1, 1, 'C', $fill);
    }
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(0, 6, utf8_decode('Total de registros: ' . count($registros)), 0, 1, 'R');
}

$pdf->Output('I', 'Reporte_Inmobiliario_' . date('Ymd') . '.pdf');