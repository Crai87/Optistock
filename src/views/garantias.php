<?php
/**
 * garantias.php
 * =============
 * Vista principal de Garantías — Armazones.
 *
 * UBICACIÓN: /src/views/garantias.php
 *
 * ¿CÓMO FUNCIONA ESTA PÁGINA?
 * Este archivo se carga a través de index.php (?view=garantias). index.php
 * ya se encarga de iniciar la sesión y verificar permisos, por lo que aquí
 * solo necesitamos una verificación de sesión como segunda capa de seguridad.
 *
 * ESTRUCTURA:
 * 1. BLOQUE PHP — Consultas a la BD, filtros, estadísticas.
 * 2. HTML — Estructura visual (header, filtros, tabla, modal, panel lateral).
 * 3. JS MÍNIMO — Solo para interacciones de UI que no pueden hacerse con PHP:
 *    menú hamburguesa, abrir/cerrar modal, panel lateral, llamadas fetch().
 *
 * PATRÓN SEGUIDO: Igual que inventario.php y admin.php
 */

// ── 1. VERIFICACIÓN DE SESIÓN ───────────────────────────────────────────────
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /optometria-inventario/public/index.php?view=login');
    exit;
}

// ── 2. CONEXIÓN A BASE DE DATOS ─────────────────────────────────────────────
require_once __DIR__ . '/../../config/database.php';

// ── Permisos granulares ──────────────────────────────────────
$es_admin_g    = isset($_SESSION['rol']) && (int)$_SESSION['rol'] === 1;
$pg            = $_SESSION['permisos'] ?? [];
$puede_agregar_g = $es_admin_g || in_array('Agregar garantias', $pg);
$puede_editar_g  = $es_admin_g || in_array('Editar garantias',  $pg);
$ver_todos_g     = isset($_GET['ver_todos']) && $_GET['ver_todos'] == '1';
$hay_filtro_g    = !empty($_GET['buscar']) || !empty($_GET['proveedor']) || !empty($_GET['estado']);

// ── 3. CONSULTAR FOLIOS DE GARANTÍA ─────────────────────────
$garantias = [];
if ($hay_filtro_g || $ver_todos_g) {
try {
    $query = "
        SELECT
            fg.id,
            fg.folio,
            fg.motivo,
            fg.estado,
            fg.fecha_apertura,
            fg.fecha_resolucion,
            fg.resolucion,
            fg.armazon_id,
            fg.proveedor_id,
            a.modelo       AS nombre_armazon,
            a.codigo       AS codigo_armazon,
            ma.nombre      AS nombre_marca,
            p.nombre       AS nombre_proveedor
        FROM folios_garantia fg
        LEFT JOIN armazones a       ON a.id  = fg.armazon_id
        LEFT JOIN marcas_armazon ma ON ma.id = a.marca_id
        LEFT JOIN proveedores p     ON p.id  = fg.proveedor_id
        WHERE 1=1
    ";
    $params = [];
    if (!empty($_GET['buscar'])) {
        $busqueda = '%' . $_GET['buscar'] . '%';
        $query .= " AND (fg.folio LIKE :b1 OR a.modelo LIKE :b2 OR fg.motivo LIKE :b3 OR p.nombre LIKE :b4)";
        $params['b1'] = $busqueda;
        $params['b2'] = $busqueda;
        $params['b3'] = $busqueda;
        $params['b4'] = $busqueda;
    }
    if (!empty($_GET['proveedor'])) {
        $query .= " AND fg.proveedor_id = :prov";
        $params['prov'] = intval($_GET['proveedor']);
    }
    $estados_validos_filtro = ['abierto', 'enviado_proveedor', 'resuelto', 'rechazado'];
    if (!empty($_GET['estado']) && in_array($_GET['estado'], $estados_validos_filtro)) {
        $query .= " AND fg.estado = :estado_filtro";
        $params['estado_filtro'] = $_GET['estado'];
    }
    $query .= " ORDER BY fg.fecha_apertura DESC, fg.id DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $garantias = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
catch (PDOException $e) {
    $garantias = [];
}
} // fin if hay_filtro_g || ver_todos_g

// ── 4. CALCULAR ESTADÍSTICAS ────────────────────────────────────────────────
$cnt_abierto = count(array_filter($garantias, function ($g) {
    return $g['estado'] === 'abierto'; }));
$cnt_enviado = count(array_filter($garantias, function ($g) {
    return $g['estado'] === 'enviado_proveedor'; }));
$cnt_resuelto = count(array_filter($garantias, function ($g) {
    return $g['estado'] === 'resuelto'; }));
$cnt_rechazado = count(array_filter($garantias, function ($g) {
    return $g['estado'] === 'rechazado'; }));

// ── 5. CONSULTAR ARMAZONES PARA EL SELECT DEL MODAL ────────────────────────
try {
    $stmtArm = $pdo->query("
        SELECT a.id, a.modelo, ma.nombre AS marca
        FROM armazones a
        LEFT JOIN marcas_armazon ma ON ma.id = a.marca_id
        ORDER BY a.modelo
    ");
    $armazones = $stmtArm->fetchAll(PDO::FETCH_ASSOC);
}
catch (PDOException $e) {
    $armazones = [];
}

// ── 6. CONSULTAR PROVEEDORES PARA LOS SELECT ───────────────────────────────
try {
    $stmtProv = $pdo->query("SELECT id, nombre FROM proveedores ORDER BY nombre");
    $proveedores = $stmtProv->fetchAll(PDO::FETCH_ASSOC);
}
catch (PDOException $e) {
    $proveedores = [];
}

// ── 7. GENERAR SIGUIENTE FOLIO AUTOMÁTICAMENTE ─────────────────────────────
$anio = date('Y');
try {
    $stmtFolio = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED)) AS max_num
        FROM folios_garantia
        WHERE folio LIKE :prefijo
    ");
    $stmtFolio->execute(['prefijo' => "GRT-{$anio}-%"]);
    $rowFolio = $stmtFolio->fetch();
    $sigNum = ($rowFolio && $rowFolio['max_num']) ? intval($rowFolio['max_num']) + 1 : 1;
}
catch (PDOException $e) {
    $sigNum = 1;
}
$siguienteFolio = "GRT-{$anio}-" . str_pad($sigNum, 5, '0', STR_PAD_LEFT);

// ── 8. MAPAS DE ETIQUETAS Y CLASES CSS POR ESTADO ──────────────────────────
$etiquetas_estado = [
    'abierto' => 'Abierto',
    'enviado_proveedor' => 'Con proveedor',
    'resuelto' => 'Resuelto',
    'rechazado' => 'Rechazado',
];
$clases_estado = [
    'abierto' => 'abierto',
    'enviado_proveedor' => 'enviado',
    'resuelto' => 'resuelto',
    'rechazado' => 'rechazado',
];

// ── 9. FUNCIÓN AUXILIAR: FORMATEAR FECHA ────────────────────────────────────
$meses_es = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
function formatearFecha($fecha, $meses)
{
    if (!$fecha)
        return '—';
    $ts = strtotime($fecha);
    if ($ts === false)
        return '—';
    return date('d', $ts) . ' ' . $meses[intval(date('m', $ts)) - 1] . ' ' . date('Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Garantías — Armazones</title>
<link rel="stylesheet" href="/optometria-inventario/public/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="/optometria-inventario/public/assets/css/bootstrap-icons.css">
<link rel="stylesheet" href="/optometria-inventario/public/assets/css/materiales.css">
<link rel="stylesheet" href="/optometria-inventario/public/assets/css/administrador.css">
<link rel="stylesheet" href="/optometria-inventario/public/assets/css/garantias.css">
<style>
    .estado-badge {
        display: inline-block;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 20px;
        text-transform: capitalize;
    }
    .estado-badge.abierto   { background: #e8f5e9; color: #2e7d32; }
    .estado-badge.enviado   { background: #f3e5f5; color: #6a1b9a; }
    .estado-badge.resuelto  { background: #e3f2fd; color: #1565c0; }
    .estado-badge.rechazado { background: #fce4ec; color: #c62828; }
</style>
</head>
<body>

<!-- HEADER -->
<header class="site-header">
    <div class="header-logo">
        <a href="/optometria-inventario/public/index.php?view=inventario">
            <img src="/optometria-inventario/public/assets/img/Logo.png" alt="Logo Clínica" class="logo-img">
            <span class="logo-text">Clínica Optometría</span>
        </a>
    </div>
    <button class="hamburger" id="menuToggle" aria-label="Abrir menú">
        <span></span><span></span><span></span>
    </button>
    <nav class="mobile-menu" id="mobileMenu">
        <ul>
            <li><a href="/optometria-inventario/public/index.php?view=inventario">Inventario de Armazones</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=Inventario_materiales">Inventario de Equipos</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=garantias" class=active>Garantías</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=admin">Usuarios</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=logout">Salir</a></li>
        </ul>
    </nav>
</header>

<!-- CONTENIDO PRINCIPAL -->
<div class="container py-5">

    <!-- TÍTULO + BOTÓN NUEVO FOLIO -->
    <div class="page-title-row">
        <div>
            <h2 class="page-title"><i class="bi bi-shield-check me-2"></i>Garantías — Armazones</h2>
            <p class="page-subtitle">Gestiona los folios de garantía abiertos con proveedores</p>
        </div>
        <?php if ($puede_agregar_g): ?>
        <button class="btn btn-guardar px-4" onclick="abrirModal()">＋ Nuevo Folio</button>
        <?php endif; ?>
    </div>

    <!-- TARJETAS DE ESTADÍSTICAS -->
    <div class="stats-row">
        <div class="stat-card" style="--stat-color: #2e7d32">
            <div class="stat-label">Abiertos</div>
            <div class="stat-value"><?php echo $cnt_abierto; ?></div>
            <div class="stat-hint">folios pendientes</div>
        </div>
        <div class="stat-card" style="--stat-color: #6a1b9a">
            <div class="stat-label">Con proveedor</div>
            <div class="stat-value"><?php echo $cnt_enviado; ?></div>
            <div class="stat-hint">enviados</div>
        </div>
        <div class="stat-card" style="--stat-color: #1565c0">
            <div class="stat-label">Resueltos</div>
            <div class="stat-value"><?php echo $cnt_resuelto; ?></div>
            <div class="stat-hint">garantías cerradas</div>
        </div>
        <div class="stat-card" style="--stat-color: #c62828">
            <div class="stat-label">Rechazados</div>
            <div class="stat-value"><?php echo $cnt_rechazado; ?></div>
            <div class="stat-hint">no procedentes</div>
        </div>
    </div>

    <!-- BARRA DE FILTROS -->
    <form method="GET" action="/optometria-inventario/public/index.php">
        <input type="hidden" name="view" value="garantias">
        <div class="filters-bar">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" name="buscar" class="form-control custom-input"
                       placeholder="Buscar por folio, armazón, motivo…"
                       value="<?php echo htmlspecialchars($_GET['buscar'] ?? ''); ?>">
            </div>
            <select class="filter-select" name="proveedor" onchange="this.form.submit()">
                <option value="">Todos los proveedores</option>
                <?php foreach ($proveedores as $prov): ?>
                    <option value="<?php echo $prov['id']; ?>"
                        <?php echo(isset($_GET['proveedor']) && $_GET['proveedor'] == $prov['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($prov['nombre']); ?>
                    </option>
                <?php
endforeach; ?>
            </select>
            <select class="filter-select" name="estado" onchange="this.form.submit()">
                <option value="">Todos los estados</option>
                <option value="abierto"            <?php echo (($_GET['estado'] ?? '') === 'abierto')            ? 'selected' : ''; ?>>Abierto</option>
                <option value="enviado_proveedor"  <?php echo (($_GET['estado'] ?? '') === 'enviado_proveedor')  ? 'selected' : ''; ?>>Con proveedor</option>
                <option value="resuelto"           <?php echo (($_GET['estado'] ?? '') === 'resuelto')           ? 'selected' : ''; ?>>Resuelto</option>
                <option value="rechazado"          <?php echo (($_GET['estado'] ?? '') === 'rechazado')          ? 'selected' : ''; ?>>Rechazado</option>
            </select>
            <button type="submit" class="btn btn-guardar btn-sm px-3">Buscar</button>
            <?php if ($hay_filtro_g || $ver_todos_g): ?>
                <a href="/optometria-inventario/public/index.php?view=garantias" class="btn btn-outline-secondary btn-sm">Limpiar</a>
            <?php endif; ?>
            <!-- Ver todos -->
        
            <?php if (!$ver_todos_g && !$hay_filtro_g): ?>
                <a href="?view=garantias&ver_todos=1" class="btn btn-outline-secondary flex-shrink-0">
                    <i class="bi bi-list-ul me-1"></i>Ver todos los folios
                </a>
            <?php elseif ($ver_todos_g): ?>
                <a href="?view=garantias" class="btn btn-success flex-shrink-0">
                    <i class="bi bi-x-lg me-1"></i>Ocultar lista
                </a>
            <?php endif; ?>
        </div>
        
        
    </form>

    <!-- TABLA DE FOLIOS -->
    <div class="grt-table-wrap">
        <div class="grt-table-head">
            <span class="grt-table-title">Folios registrados</span>
            <span class="grt-table-count">Mostrando <?php echo count($garantias); ?> registros</span>
        </div>
        <div class="table-responsive">
            <table class="grt-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Armazón</th>
                        <th>Motivo</th>
                        <th>Proveedor</th>
                        <th>Apertura</th>
                        <th>Resolución</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="grtTableBody">
                    <?php if (empty($garantias)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:40px; color:#999;">
                                <?php if ($hay_filtro_g || $ver_todos_g): ?>
                                No se encontraron folios con ese criterio.
                            <?php else: ?>
                                Usa el buscador, los filtros o "Ver todos los folios" para ver registros.
                            <?php endif; ?>
                            </td>
                        </tr>
                    <?php
else: ?>
                        <?php foreach ($garantias as $g):
        $claseEstado = $clases_estado[$g['estado']] ?? 'abierto';
        $fechaAp = formatearFecha($g['fecha_apertura'], $meses_es);
        $fechaRes = formatearFecha($g['fecha_resolucion'], $meses_es);
        $safeFolio = htmlspecialchars($g['folio'] ?? '', ENT_QUOTES);
        $safeArmazon = htmlspecialchars($g['nombre_armazon'] ?? 'N/A', ENT_QUOTES);
        $safeMarca = htmlspecialchars($g['nombre_marca'] ?? '—', ENT_QUOTES);
        $safeMotivo = htmlspecialchars($g['motivo'] ?? '', ENT_QUOTES);
        $safeProvNom = htmlspecialchars($g['nombre_proveedor'] ?? '—', ENT_QUOTES);
        // Escapar comillas simples para JS
        $jsMotivo = str_replace(["'", "\n", "\r"], ["\\'", " ", ""], $g['motivo'] ?? '');
        $jsResolucion = str_replace(["'", "\n", "\r"], ["\\'", " ", ""], $g['resolucion'] ?? '');
?>
                        <tr data-estado="<?php echo $claseEstado; ?>"
                            data-proveedor="<?php echo $safeProvNom; ?>"
                            onclick="abrirDetalle(<?php echo $g['id']; ?>, '<?php echo $safeFolio; ?>', '<?php echo $safeArmazon; ?>', '<?php echo $safeMarca; ?>', '<?php echo $jsMotivo; ?>', '<?php echo $g['estado']; ?>', '<?php echo $safeProvNom; ?>', '<?php echo $fechaAp; ?>', '<?php echo $fechaRes; ?>', '<?php echo $jsResolucion; ?>')">
                            <td><span class="folio-code"><?php echo $safeFolio; ?></span></td>
                            <td>
                                <div class="armazon-model"><?php echo $safeArmazon; ?></div>
                                <div class="armazon-brand"><?php echo $safeMarca; ?></div>
                            </td>
                            <td><div class="motivo-cell"><?php echo htmlspecialchars(mb_strimwidth($g['motivo'] ?? '', 0, 50, '…')); ?></div></td>
                            <td><span class="proveedor-tag"><?php echo $safeProvNom; ?></span></td>
                            <td class="fecha-cell"><?php echo $fechaAp; ?></td>
                            <td class="fecha-cell"><?php echo $fechaRes; ?></td>
                            <td>
                                <?php if ($puede_editar_g): ?>
                                <div class="action-btns" onclick="event.stopPropagation()">
                                    <button class="btn-accion editar" title="Editar"
                                        onclick="abrirEditar(<?php echo $g['id']; ?>, <?php echo $g['armazon_id'] ?? 'null'; ?>, '<?php echo $jsMotivo; ?>', <?php echo $g['proveedor_id'] ?? 'null'; ?>, '<?php echo $g['fecha_apertura']; ?>', '<?php echo $g['fecha_resolucion'] ?? ''; ?>', '<?php echo $jsResolucion; ?>')">✏️</button>
                                    <button class="btn-accion eliminar" title="Eliminar"
                                        onclick="eliminarFolio(<?php echo $g['id']; ?>)">🗑️</button>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php
    endforeach; ?>
                    <?php
endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /container -->


<!-- MODAL — NUEVO / EDITAR FOLIO -->
<div class="modal-overlay" id="modalNuevo" onclick="handleModalClick(event)">
    <div class="modal-box">

        <div class="modal-header-bar">
            <h5 id="modalTitulo">Nuevo Folio de Garantía</h5>
            <button class="modal-close-btn" onclick="cerrarModal()">✕</button>
        </div>

        <div class="modal-body-inner">
            <p class="section-label mb-3">Datos del folio</p>

            <!-- Campo oculto para ID (0=nuevo, >0=edición) -->
            <input type="hidden" id="mId" value="0">

            <div class="form-grid-2">

                <div class="mb-3">
                    <label class="form-label">Folio <span class="req">*</span></label>
                    <input type="text" id="mFolio" class="form-control custom-input"
                           value="<?php echo htmlspecialchars($siguienteFolio); ?>" readonly
                           style="color:#2e7d32;font-family:'Courier New',monospace;font-weight:700">
                </div>

                <div class="mb-3 full">
                    <label class="form-label">Armazón <span class="req">*</span></label>
                    <select class="form-select custom-input" id="mArmazon">
                        <option value="">— Seleccionar armazón —</option>
                        <?php foreach ($armazones as $arm): ?>
                            <option value="<?php echo $arm['id']; ?>">
                                <?php echo htmlspecialchars($arm['modelo'] ?? 'Sin modelo'); ?> — <?php echo htmlspecialchars($arm['marca'] ?? 'Sin marca'); ?>
                            </option>
                        <?php
endforeach; ?>
                    </select>
                </div>

                <div class="mb-3 full">
                    <label class="form-label">Motivo de garantía <span class="req">*</span></label>
                    <textarea id="mMotivo" class="form-control custom-input" rows="3"
                              placeholder="Describe el defecto o problema observado en el armazón…"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Proveedor</label>
                    <select class="form-select custom-input" id="mProveedor">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ($proveedores as $prov): ?>
                            <option value="<?php echo $prov['id']; ?>"><?php echo htmlspecialchars($prov['nombre']); ?></option>
                        <?php
endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Fecha de apertura <span class="req">*</span></label>
                    <input type="date" id="mFechaApertura" class="form-control custom-input">
                </div>

                <div class="mb-3">
                    <label class="form-label">Fecha de resolución</label>
                    <input type="date" id="mFechaResolucion" class="form-control custom-input">
                </div>

                <div class="mb-0 full">
                    <label class="form-label">Resolución / Notas</label>
                    <textarea id="mResolucion" class="form-control custom-input" rows="2"
                              placeholder="Dejar vacío si el caso sigue abierto…"></textarea>
                </div>

            </div>
        </div>

        <div class="modal-footer-bar">
            <button class="btn btn-outline-secondary" onclick="cerrarModal()">Cancelar</button>
            <button class="btn btn-guardar px-4" id="btnGuardarFolio" onclick="guardarFolio()">Guardar Folio</button>
        </div>

    </div>
</div>


<!-- PANEL LATERAL — DETALLE DE FOLIO -->
<div class="detail-overlay" id="detailOverlay" onclick="handleDetailClick(event)">
    <div class="detail-panel">

        <div class="detail-top">
            <div>
                <div class="detail-folio-label" id="dFolio">GRT-2026-00001</div>
                <div class="detail-armazon-name" id="dArmazon">Armazón</div>
            </div>
            <button class="modal-close-btn" onclick="cerrarDetalle()">✕</button>
        </div>

        <div class="detail-body">

            <div class="detail-section-title">Información del folio</div>
            <div class="detail-grid mb-4">
                <div>
                    <div class="detail-item-label">Estado</div>
                    <div class="detail-item-value" id="dEstadoBadge">
                        <span class="estado-badge abierto">Abierto</span>
                    </div>
                </div>
                <div>
                    <div class="detail-item-label">Proveedor</div>
                    <div class="detail-item-value">
                        <span class="proveedor-tag" id="dProveedor">—</span>
                    </div>
                </div>
                <div>
                    <div class="detail-item-label">Fecha apertura</div>
                    <div class="detail-item-value fecha-cell" id="dApertura">—</div>
                </div>
                <div>
                    <div class="detail-item-label">Fecha resolución</div>
                    <div class="detail-item-value fecha-cell" id="dResolucion">—</div>
                </div>
                <div>
                    <div class="detail-item-label">Marca</div>
                    <div class="detail-item-value" id="dMarca">—</div>
                </div>
            </div>

            <div class="detail-section-title">Motivo reportado</div>
            <div class="detail-motivo-box" id="dMotivo">—</div>

            <div class="detail-section-title">Resolución / Notas</div>
            <div class="detail-motivo-box" id="dNotasResolucion" style="margin-bottom:20px;">—</div>

            <div class="detail-section-title">Historial</div>
            <div class="timeline">
                <div class="tl-item">
                    <div class="tl-dot">📋</div>
                    <div>
                        <div class="tl-event">Folio abierto</div>
                        <div class="tl-date" id="dTlApertura">— Sistema</div>
                    </div>
                </div>
                <div class="tl-item" id="dTlEnviado" style="display:none">
                    <div class="tl-dot">📦</div>
                    <div>
                        <div class="tl-event">Enviado a proveedor</div>
                        <div class="tl-date">Pendiente de confirmación</div>
                    </div>
                </div>
                <div class="tl-item" id="dTlFinal" style="display:none">
                    <div class="tl-dot" id="dTlFinalDot">✅</div>
                    <div>
                        <div class="tl-event" id="dTlFinalTexto">Caso resuelto</div>
                        <div class="tl-date" id="dTlFinalFecha">—</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Botones de acción -->
        <div class="detail-actions" id="detailActions">
            <?php if ($puede_editar_g): ?>
            <button class="btn-accion-proveedor btn-full" id="btnEnviarProv" onclick="enviarAProveedor()"> Enviar a proveedor</button>
            <button class="btn-accion-resolver btn-full" id="btnResolver" onclick="marcarResuelto()"> Marcar como resuelto</button>
            <button class="btn-accion-rechazar btn-full" id="btnRechazar" onclick="marcarRechazado()"> Marcar como rechazado</button>
            <?php endif; ?>
        </div>

    </div>
</div>


<!-- Toast -->
<div class="toast-notif" id="toast"></div>


<!-- SCRIPTS — Solo JS necesario para UI interactiva -->
<script>
// ═══════════════════════════════════════════════════════════════════════════
// MENÚ HAMBURGUESA — Toggle del menú responsive
// ═══════════════════════════════════════════════════════════════════════════
var menuToggle = document.getElementById("menuToggle");
var mobileMenu = document.getElementById("mobileMenu");

menuToggle.addEventListener("click", function(e) {
    e.stopPropagation();
    mobileMenu.classList.toggle("show");
    menuToggle.classList.toggle("open");
});
document.addEventListener("click", function(e) {
    if (!menuToggle.contains(e.target) && !mobileMenu.contains(e.target)) {
        mobileMenu.classList.remove("show");
        menuToggle.classList.remove("open");
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// FECHA DE HOY EN EL MODAL — Se precarga la fecha actual del navegador.
// ═══════════════════════════════════════════════════════════════════════════
document.getElementById("mFechaApertura").valueAsDate = new Date();

// ═══════════════════════════════════════════════════════════════════════════
// MODAL — ABRIR / CERRAR / LIMPIAR
// Se reutiliza el mismo modal para crear y editar folios.
// ═══════════════════════════════════════════════════════════════════════════

/**
 * abrirModal() — Abre el modal en modo "nuevo folio".
 * Resetea el formulario y muestra el siguiente folio auto-generado.
 */
function abrirModal() {
    document.getElementById("mId").value = "0";
    document.getElementById("modalTitulo").textContent = "Nuevo Folio de Garantía";
    document.getElementById("btnGuardarFolio").textContent = "Guardar Folio";
    document.getElementById("mFolio").value = "<?php echo htmlspecialchars($siguienteFolio); ?>";
    limpiarCamposModal();
    document.getElementById("modalNuevo").classList.add("open");
}

/**
 * cerrarModal() — Cierra el modal y limpia campos.
 */
function cerrarModal() {
    document.getElementById("modalNuevo").classList.remove("open");
}

/**
 * handleModalClick() — Cierra el modal si se hace click en el overlay.
 */
function handleModalClick(e) {
    if (e.target.id === "modalNuevo") cerrarModal();
}

/**
 * limpiarCamposModal() — Resetea los campos del formulario.
 */
function limpiarCamposModal() {
    document.getElementById("mArmazon").value = "";
    document.getElementById("mMotivo").value = "";
    document.getElementById("mProveedor").value = "";
    document.getElementById("mResolucion").value = "";
    document.getElementById("mFechaResolucion").value = "";
    document.getElementById("mFechaApertura").valueAsDate = new Date();
}

// ═══════════════════════════════════════════════════════════════════════════
// abrirEditar() — Abre el modal en modo edición con datos precargados.
// Se llama desde el botón ✏️ de cada fila.
//
// Parámetros:
//   id            - ID del folio en la BD
//   armazonId     - ID del armazón
//   motivo        - Texto del motivo
//   proveedorId   - ID del proveedor (puede ser null)
//   fechaApertura - Formato YYYY-MM-DD
//   fechaResol    - Formato YYYY-MM-DD o vacío
//   resolucion    - Texto de notas
// ═══════════════════════════════════════════════════════════════════════════
function abrirEditar(id, armazonId, motivo, proveedorId, fechaApertura, fechaResol, resolucion) {
    document.getElementById("mId").value = id;
    document.getElementById("modalTitulo").textContent = "Editar Folio de Garantía";
    document.getElementById("btnGuardarFolio").textContent = "Actualizar Folio";
    document.getElementById("mArmazon").value = armazonId || "";
    document.getElementById("mMotivo").value = motivo || "";
    document.getElementById("mProveedor").value = proveedorId || "";
    document.getElementById("mFechaApertura").value = fechaApertura || "";
    document.getElementById("mFechaResolucion").value = fechaResol || "";
    document.getElementById("mResolucion").value = resolucion || "";
    document.getElementById("modalNuevo").classList.add("open");
}

// ═══════════════════════════════════════════════════════════════════════════
// guardarFolio() — Envía datos del modal al backend vía fetch().
// Llama a guardar_garantia.php para crear (INSERT) o editar (UPDATE).
// Si mId es "0" → crea nuevo. Si mId > 0 → edita existente.
// Después de éxito, recarga la página para mostrar los cambios.
// ═══════════════════════════════════════════════════════════════════════════
function guardarFolio() {
    var armazon  = document.getElementById("mArmazon").value;
    var motivo   = document.getElementById("mMotivo").value.trim();
    var apertura = document.getElementById("mFechaApertura").value;

    if (!armazon || !motivo || !apertura) {
        showToast("⚠️ Completa los campos obligatorios");
        return;
    }

    var fd = new FormData();
    fd.append("id",               document.getElementById("mId").value);
    fd.append("armazon_id",       armazon);
    fd.append("motivo",           motivo);
    fd.append("proveedor_id",     document.getElementById("mProveedor").value);
    fd.append("fecha_apertura",   apertura);
    fd.append("fecha_resolucion", document.getElementById("mFechaResolucion").value);
    fd.append("resolucion",       document.getElementById("mResolucion").value);

    var btn = document.getElementById("btnGuardarFolio");
    btn.disabled = true;
    btn.textContent = "Guardando…";

    fetch("/optometria-inventario/public/index.php?view=guardar_garantia", {
        method: "POST",
        body: fd
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.ok) {
            showToast("✅ " + data.msg);
            cerrarModal();
            setTimeout(function() { location.reload(); }, 800);
        } else {
            showToast("❌ " + data.msg);
        }
    })
    .catch(function() {
        showToast("❌ Error de conexión");
    })
    .finally(function() {
        btn.disabled = false;
        btn.textContent = "Guardar Folio";
    });
}

// ═══════════════════════════════════════════════════════════════════════════
// eliminarFolio() — Elimina un folio de la BD.
// Muestra confirmación, luego llama a eliminar_garantia.php vía fetch.
// ═══════════════════════════════════════════════════════════════════════════
function eliminarFolio(id) {
    if (!confirm("¿Estás seguro de que deseas eliminar este folio de garantía?")) return;

    var fd = new FormData();
    fd.append("id", id);

    fetch("/optometria-inventario/public/index.php?view=eliminar_garantia", {
        method: "POST",
        body: fd
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.ok) {
            showToast("🗑️ " + data.msg);
            setTimeout(function() { location.reload(); }, 800);
        } else {
            showToast("❌ " + data.msg);
        }
    })
    .catch(function() {
        showToast("❌ Error de conexión");
    });
}

// ═══════════════════════════════════════════════════════════════════════════
// PANEL LATERAL — DETALLE DE FOLIO
// ═══════════════════════════════════════════════════════════════════════════

// ID del folio actualmente abierto en el detalle (para acciones de estado)
var detalleIdActual = 0;

// Mapas de etiquetas y clases CSS para estados
var etiquetasEstado = {
    abierto:            "Abierto",
    enviado_proveedor:  "Con proveedor",
    resuelto:           "Resuelto",
    rechazado:          "Rechazado"
};
var clasesEstado = {
    abierto:            "abierto",
    enviado_proveedor:  "enviado",
    resuelto:           "resuelto",
    rechazado:          "rechazado"
};

/**
 * abrirDetalle() — Abre el panel lateral con la info del folio.
 * Se llama al hacer click en una fila de la tabla.
 *
 * Parámetros: id, folio, armazon, marca, motivo, estado, proveedor,
 *             apertura, resolucion, notas
 */
function abrirDetalle(id, folio, armazon, marca, motivo, estado, proveedor, apertura, resolucion, notas) {
    detalleIdActual = id;

    document.getElementById("dFolio").textContent = folio;
    document.getElementById("dArmazon").textContent = armazon;
    document.getElementById("dMarca").textContent = marca;
    document.getElementById("dMotivo").textContent = motivo;
    document.getElementById("dProveedor").textContent = proveedor;
    document.getElementById("dApertura").textContent = apertura;
    document.getElementById("dResolucion").textContent = resolucion;
    document.getElementById("dTlApertura").textContent = apertura + " — Sistema";
    document.getElementById("dNotasResolucion").textContent = notas || "Sin notas de resolución.";

    // Badge de estado
    var claseCSS = clasesEstado[estado] || "abierto";
    document.getElementById("dEstadoBadge").innerHTML =
        '<span class="estado-badge ' + claseCSS + '">' + (etiquetasEstado[estado] || estado) + '</span>';

    // Timeline
    var enviado = document.getElementById("dTlEnviado");
    var final_  = document.getElementById("dTlFinal");

    enviado.style.display = (estado === "enviado_proveedor" || estado === "resuelto" || estado === "rechazado") ? "flex" : "none";

    if (resolucion && resolucion !== "—") {
        final_.style.display = "flex";
        document.getElementById("dTlFinalDot").textContent = estado === "rechazado" ? "❌" : "✅";
        document.getElementById("dTlFinalTexto").textContent = estado === "rechazado" ? "Rechazado por proveedor" : "Caso resuelto";
        document.getElementById("dTlFinalFecha").textContent = resolucion;
    } else {
        final_.style.display = "none";
    }

    // Mostrar/ocultar botones según estado
    var btnEnviar  = document.getElementById("btnEnviarProv");
    var btnResolver = document.getElementById("btnResolver");
    var btnRechazar = document.getElementById("btnRechazar");

    if (estado === "resuelto" || estado === "rechazado") {
        btnEnviar.style.display = "none";
        btnResolver.style.display = "none";
        btnRechazar.style.display = "none";
    } else if (estado === "enviado_proveedor") {
        btnEnviar.style.display = "none";
        btnResolver.style.display = "";
        btnRechazar.style.display = "";
    } else {
        btnEnviar.style.display = "";
        btnResolver.style.display = "";
        btnRechazar.style.display = "";
    }

    document.getElementById("detailOverlay").classList.add("open");
}

/**
 * cerrarDetalle() — Cierra el panel lateral.
 */
function cerrarDetalle() {
    document.getElementById("detailOverlay").classList.remove("open");
}

/**
 * handleDetailClick() — Cierra el detalle al click en overlay.
 */
function handleDetailClick(e) {
    if (e.target.id === "detailOverlay") cerrarDetalle();
}

// ═══════════════════════════════════════════════════════════════════════════
// ACCIONES DE ESTADO — Enviar/Resolver/Rechazar
// Llaman a estado_garantia.php vía fetch() con el nuevo estado.
// ═══════════════════════════════════════════════════════════════════════════

/**
 * cambiarEstado() — Función genérica para cambiar estado de un folio.
 * Se reutiliza en los tres botones de acción.
 *
 * @param {string} nuevoEstado - Estado a establecer
 * @param {string} confirmMsg  - Mensaje de confirmación
 */
function cambiarEstado(nuevoEstado, confirmMsg) {
    if (!confirm(confirmMsg)) return;

    var fd = new FormData();
    fd.append("id", detalleIdActual);
    fd.append("estado", nuevoEstado);

    fetch("/optometria-inventario/public/index.php?view=estado_garantia", {
        method: "POST",
        body: fd
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.ok) {
            showToast("✅ " + data.msg);
            cerrarDetalle();
            setTimeout(function() { location.reload(); }, 800);
        } else {
            showToast("❌ " + data.msg);
        }
    })
    .catch(function() {
        showToast("❌ Error de conexión");
    });
}

/**
 * enviarAProveedor() — Cambia estado a 'enviado_proveedor' y genera PDF.
 * El PDF se abre en nueva pestaña (igual que en inventario.php).
 */
function enviarAProveedor() {
    if (!confirm("¿Enviar este folio al proveedor? Se generará el reporte PDF.")) return;

    // Generar PDF en nueva pestaña
    window.open('/optometria-inventario/public/generar_reporte_garantia.php?id=' + detalleIdActual, '_blank');

    // Cambiar estado en BD
    var fd = new FormData();
    fd.append("id", detalleIdActual);
    fd.append("estado", "enviado_proveedor");

    fetch("/optometria-inventario/public/index.php?view=estado_garantia", {
        method: "POST",
        body: fd
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.ok) {
            showToast("📦 Enviado a proveedor — Reporte generado");
            cerrarDetalle();
            setTimeout(function() { location.reload(); }, 1200);
        } else {
            showToast("❌ " + data.msg);
        }
    })
    .catch(function() {
        showToast("❌ Error de conexión");
    });
}

/**
 * marcarResuelto() — Cambia estado a 'resuelto'.
 */
function marcarResuelto() {
    cambiarEstado("resuelto", "¿Marcar este folio como resuelto?");
}

/**
 * marcarRechazado() — Cambia estado a 'rechazado'.
 */
function marcarRechazado() {
    cambiarEstado("rechazado", "¿Marcar este folio como rechazado por el proveedor?");
}

// ═══════════════════════════════════════════════════════════════════════════
// TOAST — Notificación emergente temporal.
// ═══════════════════════════════════════════════════════════════════════════
function showToast(msg) {
    var t = document.getElementById("toast");
    t.textContent = msg;
    t.classList.add("show");
    setTimeout(function() { t.classList.remove("show"); }, 3000);
}
</script>
</body>
</html>