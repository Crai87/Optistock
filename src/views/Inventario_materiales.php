<?php
// ============================================================
// src/views/Inventario_materiales.php
// ============================================================

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /optometria-inventario/public/index.php?view=login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

// ── Permisos granulares ──────────────────────────────────────
$es_admin_mat      = isset($_SESSION['rol']) && (int)$_SESSION['rol'] === 1;
$pm                = $_SESSION['permisos'] ?? [];
$puede_agregar_mat = $es_admin_mat || in_array('Agregar equipos',            $pm);
$puede_editar_mat  = $es_admin_mat || in_array('Editar equipos',             $pm);
$puede_reporte_mat = $es_admin_mat || in_array('Generar reporte equipos',    $pm);

// ── Auto-corrección de estado según cantidades reales ────────
// Se ejecuta en cada carga de la vista para mantener coherencia
// entre las cantidades ingresadas y el estado almacenado en BD.
// Cubre casos donde el registro quedó desincronizado.
try {
    // Si servicio > 0 → activo
    $pdo->exec("
        UPDATE equipos_clinicos
        SET estado = 'activo'
        WHERE cantidad_servicio > 0
          AND estado != 'activo'
    ");
    // Si servicio = 0 y mantenimiento > 0 → en_mantenimiento
    $pdo->exec("
        UPDATE equipos_clinicos
        SET estado = 'en_mantenimiento'
        WHERE cantidad_servicio = 0
          AND cantidad_mantenimiento > 0
          AND estado != 'en_mantenimiento'
    ");
    // Si servicio = 0 y mantenimiento = 0 → baja
    $pdo->exec("
        UPDATE equipos_clinicos
        SET estado = 'baja'
        WHERE cantidad_servicio = 0
          AND cantidad_mantenimiento = 0
          AND estado != 'baja'
    ");
} catch (PDOException $e) {
    // Ignorar silenciosamente si hay error
}

$API = '/optometria-inventario/src/views/api/api_materiales.php';

$tab = $_GET['tab'] ?? 'equipos';
if (!in_array($tab, ['equipos', 'inmobiliario'])) $tab = 'equipos';

$por_pagina    = 20;
$pagina        = max(1, intval($_GET['pagina'] ?? 1));
$offset        = ($pagina - 1) * $por_pagina;
$buscar        = trim($_GET['buscar'] ?? '');
$estado_filtro = trim($_GET['estado'] ?? '');
$msg_ok        = !empty($_GET['ok'])    ? 'Operación realizada correctamente.' : '';
$msg_error     = !empty($_GET['error']) ? htmlspecialchars($_GET['error'])     : '';
$hay_filtro    = !empty($buscar) || !empty($estado_filtro);
$ver_todos_mat = isset($_GET['ver_todos']) && $_GET['ver_todos'] == '1';

$equipos = []; $inmobiliario = [];
$total_eq = 0; $total_in = 0;
$total_pag_eq = 1; $total_pag_in = 1;

if ($hay_filtro || $ver_todos_mat) {
    $where_eq  = 'WHERE 1=1';
    $params_eq = [];
    if ($buscar !== '') {
        $b = '%' . $buscar . '%';
        $where_eq .= ' AND (ec.codigo LIKE :b1 OR ec.nombre LIKE :b2 OR me.nombre LIKE :b3 OR ec.modelo LIKE :b4 OR CAST(ec.cantidad_servicio AS CHAR) LIKE :b5 OR CAST(ec.cantidad_mantenimiento AS CHAR) LIKE :b6 OR ec.estado LIKE :b7)';
        $params_eq['b1']=$b; $params_eq['b2']=$b; $params_eq['b3']=$b; $params_eq['b4']=$b;
        $params_eq['b5']=$b; $params_eq['b6']=$b; $params_eq['b7']=$b;
    }
    if ($estado_filtro && $tab === 'equipos') {
        // El filtro 'en_mantenimiento' debe mostrar equipos que tengan
        // AL MENOS UNA unidad en mantenimiento, aunque su estado general
        // sea 'activo' (porque todavía tienen unidades en servicio).
        // El filtro 'activo' excluye los que tienen todas en mantenimiento.
        if ($estado_filtro === 'en_mantenimiento') {
            $where_eq .= ' AND ec.cantidad_mantenimiento > 0';
        } elseif ($estado_filtro === 'activo') {
            $where_eq .= ' AND ec.cantidad_servicio > 0';
        } elseif ($estado_filtro === 'baja') {
            $where_eq .= ' AND (ec.cantidad_mantenimiento + ec.cantidad_servicio) < ec.cantidad_total';
        } else {
            $where_eq .= ' AND ec.estado = :estado';
            $params_eq['estado'] = $estado_filtro;
        }
    }
    $stmtCntEq = $pdo->prepare("SELECT COUNT(*) FROM equipos_clinicos ec LEFT JOIN marcas_equipos me ON me.id = ec.marca_id $where_eq");
    $stmtCntEq->execute($params_eq);
    $total_eq     = (int)$stmtCntEq->fetchColumn();
    $total_pag_eq = max(1, ceil($total_eq / $por_pagina));
    $stmtEq = $pdo->prepare("SELECT ec.id, ec.codigo, ec.nombre, ec.descripcion, ec.modelo, ec.cantidad_total, ec.cantidad_servicio, ec.cantidad_mantenimiento, ec.estado, ec.imagen_url, COALESCE(me.nombre,'S/M') AS marca FROM equipos_clinicos ec LEFT JOIN marcas_equipos me ON me.id = ec.marca_id $where_eq ORDER BY ec.created_at DESC, ec.id DESC LIMIT :lim OFFSET :off");
    foreach ($params_eq as $k => $v) $stmtEq->bindValue($k, $v);
    $stmtEq->bindValue('lim', $por_pagina, PDO::PARAM_INT);
    $stmtEq->bindValue('off', $offset,     PDO::PARAM_INT);
    $stmtEq->execute();
    $equipos = $stmtEq->fetchAll();

    $where_in  = 'WHERE 1=1';
    $params_in = [];
    if ($buscar !== '') {
        $b = '%' . $buscar . '%';
        $where_in .= ' AND (i.codigo LIKE :b1 OR i.nombre LIKE :b2 OR ci.nombre LIKE :b3 OR i.estado LIKE :b4 OR i.ubicacion LIKE :b5)';
        $params_in['b1']=$b; $params_in['b2']=$b; $params_in['b3']=$b;
        $params_in['b4']=$b; $params_in['b5']=$b;
    }
    if ($estado_filtro && $tab === 'inmobiliario') {
        $where_in .= ' AND i.estado = :estado';
        $params_in['estado'] = $estado_filtro;
    }
    $stmtCntIn = $pdo->prepare("SELECT COUNT(*) FROM inmobiliario i LEFT JOIN categorias_inmobiliario ci ON ci.id = i.categoria_id $where_in");
    $stmtCntIn->execute($params_in);
    $total_in     = (int)$stmtCntIn->fetchColumn();
    $total_pag_in = max(1, ceil($total_in / $por_pagina));
    $stmtIn = $pdo->prepare("SELECT i.id, i.codigo, i.nombre, i.descripcion, i.cantidad, i.estado, i.ubicacion, i.imagen_url, COALESCE(ci.nombre,'Sin categoría') AS categoria FROM inmobiliario i LEFT JOIN categorias_inmobiliario ci ON ci.id = i.categoria_id $where_in ORDER BY i.created_at DESC, i.id DESC LIMIT :lim OFFSET :off");
    foreach ($params_in as $k => $v) $stmtIn->bindValue($k, $v);
    $stmtIn->bindValue('lim', $por_pagina, PDO::PARAM_INT);
    $stmtIn->bindValue('off', $offset,     PDO::PARAM_INT);
    $stmtIn->execute();
    $inmobiliario = $stmtIn->fetchAll();
}

function urlPagMat($pag) {
    $p = $_GET; $p['pagina'] = $pag; $p['view'] = 'Inventario_materiales';
    return '/optometria-inventario/public/index.php?' . http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario de Equipos — OptiStock</title>
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/bootstrap-icons.css">
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/materiales.css">
    <style>
        .fila-expandible { cursor: pointer; }
        .fila-expandible:hover { background: #f0faf4; }
        .detalle-fila { display: none; background: #f8f9fa; }
        .detalle-fila.abierto { display: table-row; }
        .detalle-foto { width:140px;height:140px;object-fit:cover;border-radius:8px;border:1px solid #dee2e6; }
        .detalle-foto-placeholder { width:140px;height:140px;background:#e9ecef;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#adb5bd;font-size:2.5rem; }
        .pill-activo           { background:#e8f5e9; color:#2e7d32; }
        .pill-en_mantenimiento { background:#fff3e0; color:#e65100; }
        .pill-baja             { background:#fce4ec; color:#c62828; }
        .pill-en_espera        { background:#f3e5f5; color:#6a1b9a; }
        .pill-en_reparacion    { background:#fff8e1; color:#f57f17; }
        .pill { display:inline-block;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:20px; }
        .expand-icon { transition:transform .2s;display:inline-block; }
        .expand-icon.abierto { transform:rotate(90deg); }
        #equipoEstadoInfo { min-height:38px; line-height:1.8; font-weight:600; }
    </style>
</head>
<body class="bg-light">

<header class="site-header">
    <div class="header-logo">
        <a href="/optometria-inventario/public/index.php?view=inventario">
            <img src="/optometria-inventario/public/assets/img/Logo.png" alt="Logo" class="logo-img">
            <span class="logo-text">Clínica Optometría</span>
        </a>
    </div>
    <button class="hamburger" id="menuToggle" aria-label="Abrir menú">
        <span></span><span></span><span></span>
    </button>
    <nav class="mobile-menu" id="mobileMenu">
        <ul>
            <li><a href="/optometria-inventario/public/index.php?view=inventario">Inventario de Armazones</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=Inventario_materiales" class="active">Inventario de Equipos</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=garantias">Garantías</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=admin">Usuarios</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=logout">Salir</a></li>
        </ul>
    </nav>
</header>

<div class="container mt-4">

    <div class="mb-3">
        <?php if ($tab == 'inmobiliario'): ?>
        <h4 class="fw-semibold"><i class="bi bi-building me-2"></i>Inventario — Equipos e Inmobiliario</h4>
        <?php else: ?>
        <h4 class="fw-semibold"><i class="bi bi-cpu me-2"></i>Inventario — Equipos e Inmobiliario</h4>
        <?php endif; ?>
    </div>

    <?php if ($msg_ok): ?>
        <div class="alert alert-success alert-dismissible">
            <i class="bi bi-check-circle me-1"></i><?= $msg_ok ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($msg_error): ?>
        <div class="alert alert-danger alert-dismissible">
            <i class="bi bi-exclamation-triangle me-1"></i><?= $msg_error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?= $tab==='equipos'?'active':'' ?>" href="?view=Inventario_materiales&tab=equipos">
                <i class="bi bi-cpu-fill me-1"></i> Equipos Clínicos
                <span class="badge bg-secondary ms-1"><?= $total_eq ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab==='inmobiliario'?'active':'' ?>" href="?view=Inventario_materiales&tab=inmobiliario">
                <i class="bi bi-building-fill me-1"></i> Inmobiliario
                <span class="badge bg-secondary ms-1"><?= $total_in ?></span>
            </a>
        </li>
    </ul>

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body d-flex align-items-center gap-2 flex-wrap">
            <form method="GET" action="/optometria-inventario/public/index.php" class="flex-grow-1 mb-0 d-flex gap-2">
                <input type="hidden" name="view" value="Inventario_materiales">
                <input type="hidden" name="tab"  value="<?= htmlspecialchars($tab) ?>">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="buscar" class="form-control border-start-0"
                           placeholder="<?= $tab==='equipos' ? 'Buscar por código, nombre, marca, modelo…' : 'Buscar por código, nombre, categoría, estado o ubicación…' ?>"
                           value="<?= htmlspecialchars($buscar) ?>">
                    <button class="btn btn-success" type="submit">Buscar</button>
                    <?php if ($buscar || $estado_filtro): ?>
                        <a href="?view=Inventario_materiales&tab=<?= $tab ?>" class="btn btn-outline-secondary">Limpiar</a>
                    <?php elseif ($ver_todos_mat): ?>
                        <a href="?view=Inventario_materiales&tab=<?= $tab ?>" class="btn btn-outline-secondary">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Ver todos -->
            <?php if (!$ver_todos_mat && !$hay_filtro): ?>
                <a href="?view=Inventario_materiales&tab=<?= $tab ?>&ver_todos=1"
                   class="btn btn-outline-secondary flex-shrink-0">
                    <i class="bi bi-list-ul me-1"></i>Ver todos
                </a>
            <?php elseif ($ver_todos_mat): ?>
                <a href="?view=Inventario_materiales&tab=<?= $tab ?>"
                   class="btn btn-outline-secondary flex-shrink-0">
                    <i class="bi bi-x-lg me-1"></i>Ocultar lista
                </a>
            <?php endif; ?>

            <?php if ($tab === 'equipos'): ?>
            <div class="dropdown flex-shrink-0">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-funnel"></i> Filtros
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item text-success fw-semibold" href="?view=Inventario_materiales&tab=equipos&estado=activo">Activo</a></li>
                    <li><a class="dropdown-item text-warning fw-semibold" href="?view=Inventario_materiales&tab=equipos&estado=en_mantenimiento">En mantenimiento</a></li>
                    <li><a class="dropdown-item text-danger fw-semibold" href="?view=Inventario_materiales&tab=equipos&estado=baja">Baja</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="?view=Inventario_materiales&tab=equipos">Quitar filtro</a></li>
                </ul>
            </div>
            <?php else: ?>
            <div class="dropdown flex-shrink-0">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-funnel"></i> Filtros
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item text-success fw-semibold" href="?view=Inventario_materiales&tab=inmobiliario&estado=activo">Activo</a></li>
                    <li><a class="dropdown-item text-warning fw-semibold" href="?view=Inventario_materiales&tab=inmobiliario&estado=en_espera">En espera</a></li>
                    <li><a class="dropdown-item text-warning fw-semibold" href="?view=Inventario_materiales&tab=inmobiliario&estado=en_reparacion">En reparación</a></li>
                    <li><a class="dropdown-item text-danger fw-semibold" href="?view=Inventario_materiales&tab=inmobiliario&estado=baja">Baja</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="?view=Inventario_materiales&tab=inmobiliario">Quitar filtro</a></li>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($puede_reporte_mat): ?>
               <a href="/optometria-inventario/public/generar_reporte_materiales.php?tab=<?= $tab ?>&buscar=<?= urlencode($buscar) ?>&estado=<?= urlencode($estado_filtro) ?>"
               target="_blank" class="btn btn-success flex-shrink-0 <?= !$hay_filtro ? 'disabled' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph"></i> Generar Reporte
            </a>
               <?php endif; ?>
            <?php if ($puede_editar_mat): ?>
            <button class="btn btn-success flex-shrink-0" id="btnAbrirAgregar">
                <i class="bi bi-plus-lg"></i>
                <span id="lblBtnAgregar"><?= $tab==='equipos' ? 'Agregar equipo' : 'Agregar equipo' ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- TABLA EQUIPOS -->
    <?php if ($tab === 'equipos'): ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-success">
                    <tr>
                        <th style="width:30px"></th>
                        <th>Código</th><th>Nombre</th><th>Marca</th><th>Modelo</th>
                        <th class="text-center">Total</th>
                        <th class="text-center">Servicio</th>
                        <th class="text-center">Mant.</th>
                        <th>Distribución</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($equipos)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted">
                            <?php if ($hay_filtro): ?>
                                <i class="bi bi-search fs-1"></i>
                                <p class="mt-2 fw-semibold">No se encontraron equipos con ese criterio</p>
                            <?php else: ?>
                                <i class="bi bi-search fs-1"></i>
                                <p class="mt-2 fw-semibold">Usa el buscador o los filtros para ver equipos</p>
                            <?php endif; ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($equipos as $eq):
                            $estado = $eq['estado'] ?? 'activo';
                            $pillClass  = 'pill-' . $estado;
                            $pillaLabel = match($estado) {
                                'activo'           => 'Activo',
                                'en_mantenimiento' => 'En mantenimiento',
                                'baja'             => 'Baja',
                                default            => $estado,
                            };
                        ?>
                        <tr class="fila-expandible" onclick="toggleDetalle('eq-<?= $eq['id'] ?>', this)">
                            <td class="text-center"><i class="bi bi-chevron-right expand-icon" id="icon-eq-<?= $eq['id'] ?>"></i></td>
                            <td><code><?= htmlspecialchars($eq['codigo']) ?></code></td>
                            <td class="fw-semibold"><?= htmlspecialchars($eq['nombre']) ?></td>
                            <td><?= htmlspecialchars($eq['marca']) ?></td>
                            <td><?= htmlspecialchars($eq['modelo'] ?? '—') ?></td>
                            <td class="text-center fw-semibold"><?= (int)$eq['cantidad_total'] ?></td>
                            <td class="text-center text-success fw-semibold"><?= (int)$eq['cantidad_servicio'] ?></td>
                            <td class="text-center <?= $eq['cantidad_mantenimiento'] > 0 ? 'text-warning fw-semibold' : 'text-muted' ?>">
                                <?= (int)$eq['cantidad_mantenimiento'] ?>
                            </td>
                            <td>
                                <?php
                                $srv  = (int)$eq['cantidad_servicio'];
                                $mnt  = (int)$eq['cantidad_mantenimiento'];
                                $baja = (int)$eq['cantidad_total'] - $srv - $mnt;
                                $baja = max(0, $baja);
                                ?>
                                <div class="d-flex flex-column gap-1" style="font-size:.72rem; font-weight:700; line-height:1.3;">
                                    <?php if ($srv > 0): ?>
                                        <span style="background:#e8f5e9;color:#2e7d32;padding:1px 7px;border-radius:20px;">
                                            <?= $srv ?> en servicio
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($mnt > 0): ?>
                                        <span style="background:#fff3e0;color:#e65100;padding:1px 7px;border-radius:20px;">
                                            <?= $mnt ?> en mant.
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($baja > 0): ?>
                                        <span style="background:#fce4ec;color:#c62828;padding:1px 7px;border-radius:20px;">
                                            <?= $baja ?> de baja
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td onclick="event.stopPropagation()">
                                <div class="d-flex gap-1">
                                    <?php if ($puede_editar_mat): ?>
                                    <button onclick="abrirEditarEq(<?= $eq['id'] ?>)" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i></button>
                                    <button onclick="abrirEliminarEq('<?= $eq['id'] ?>','<?= addslashes($eq['nombre']) ?>','<?= $eq['codigo'] ?>')" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <tr class="detalle-fila" id="eq-<?= $eq['id'] ?>">
                            <td colspan="10">
                                <div class="d-flex gap-4 p-3">
                                    <div class="flex-shrink-0">
                                        <?php if (!empty($eq['imagen_url'])): ?>
                                            <img src="<?= htmlspecialchars($eq['imagen_url']) ?>" class="detalle-foto" alt="<?= htmlspecialchars($eq['nombre']) ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                            <div class="detalle-foto-placeholder" style="display:none"><i class="bi bi-cpu"></i></div>
                                        <?php else: ?>
                                            <div class="detalle-foto-placeholder"><i class="bi bi-cpu"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold mb-2"><?= htmlspecialchars($eq['nombre']) ?></h6>
                                        <div class="row g-2 small text-muted">
                                            <div class="col-sm-3"><strong>Código:</strong> <?= htmlspecialchars($eq['codigo']) ?></div>
                                            <div class="col-sm-3"><strong>Marca:</strong> <?= htmlspecialchars($eq['marca']) ?></div>
                                            <div class="col-sm-3"><strong>Modelo:</strong> <?= htmlspecialchars($eq['modelo'] ?? '—') ?></div>
                                            <div class="col-sm-3"><strong>Estado:</strong> <?= $pillaLabel ?></div>
                                            <div class="col-sm-3"><strong>Total:</strong> <?= (int)$eq['cantidad_total'] ?></div>
                                            <div class="col-sm-3"><strong>En servicio:</strong> <?= (int)$eq['cantidad_servicio'] ?></div>
                                            <div class="col-sm-3"><strong>En mant.:</strong> <?= (int)$eq['cantidad_mantenimiento'] ?></div>
                                        </div>
                                        <?php if (!empty($eq['descripcion'])): ?>
                                            <p class="mt-2 mb-0 small"><?= htmlspecialchars($eq['descripcion']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center">
            <small class="text-muted">Mostrando <?= count($equipos) ?> de <?= $total_eq ?> registros</small>
            <?php if ($total_pag_eq > 1): ?>
            <nav><ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $pagina<=1?'disabled':'' ?>"><a class="page-link" href="<?= urlPagMat($pagina-1) ?>">Anterior</a></li>
                <?php for ($i=1;$i<=$total_pag_eq;$i++): ?>
                    <li class="page-item <?= $i===$pagina?'active':'' ?>"><a class="page-link" href="<?= urlPagMat($i) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?= $pagina>=$total_pag_eq?'disabled':'' ?>"><a class="page-link" href="<?= urlPagMat($pagina+1) ?>">Siguiente</a></li>
            </ul></nav>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- TABLA INMOBILIARIO -->
    <?php if ($tab === 'inmobiliario'): ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-success">
                    <tr>
                        <th style="width:30px"></th>
                        <th>Código</th><th>Nombre</th><th>Categoría</th>
                        <th class="text-center">Cantidad</th>
                        <th>Estado</th><th>Ubicación</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inmobiliario)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">
                            <?php if ($hay_filtro): ?>
                                <i class="bi bi-search fs-1"></i>
                                <p class="mt-2 fw-semibold">No se encontraron items con ese criterio</p>
                            <?php else: ?>
                                <i class="bi bi-search fs-1"></i>
                                <p class="mt-2 fw-semibold">Usa el buscador o los filtros para ver inmobiliario</p>
                            <?php endif; ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($inmobiliario as $it):
                            $estado = $it['estado'] ?? 'activo';
                            $pillClass  = 'pill-' . $estado;
                            $pillaLabel = match($estado) {
                                'activo'        => 'Activo',
                                'en_espera'     => 'En espera',
                                'en_reparacion' => 'En reparación',
                                'baja'          => 'Baja',
                                default         => $estado,
                            };
                        ?>
                        <tr class="fila-expandible" onclick="toggleDetalle('inm-<?= $it['id'] ?>', this)">
                            <td class="text-center"><i class="bi bi-chevron-right expand-icon" id="icon-inm-<?= $it['id'] ?>"></i></td>
                            <td><code><?= htmlspecialchars($it['codigo']) ?></code></td>
                            <td class="fw-semibold"><?= htmlspecialchars($it['nombre']) ?></td>
                            <td><?= htmlspecialchars($it['categoria']) ?></td>
                            <td class="text-center"><?= (int)$it['cantidad'] ?></td>
                            <td><span class="pill <?= $pillClass ?>"><?= $pillaLabel ?></span></td>
                            <td><?= htmlspecialchars($it['ubicacion'] ?? '—') ?></td>
                            <td onclick="event.stopPropagation()">
                                <div class="d-flex gap-1">
                                    <?php if ($puede_editar_mat): ?>
                                    <button onclick="abrirEditarInmo(<?= $it['id'] ?>)" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i></button>
                                    <button onclick="abrirEliminarInmo('<?= $it['id'] ?>','<?= addslashes($it['nombre']) ?>','<?= $it['codigo'] ?>')" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <tr class="detalle-fila" id="inm-<?= $it['id'] ?>">
                            <td colspan="8">
                                <div class="d-flex gap-4 p-3">
                                    <div class="flex-shrink-0">
                                        <?php if (!empty($it['imagen_url'])): ?>
                                            <img src="<?= htmlspecialchars($it['imagen_url']) ?>" class="detalle-foto" alt="<?= htmlspecialchars($it['nombre']) ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                            <div class="detalle-foto-placeholder" style="display:none"><i class="bi bi-building"></i></div>
                                        <?php else: ?>
                                            <div class="detalle-foto-placeholder"><i class="bi bi-building"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold mb-2"><?= htmlspecialchars($it['nombre']) ?></h6>
                                        <div class="row g-2 small text-muted">
                                            <div class="col-sm-3"><strong>Código:</strong> <?= htmlspecialchars($it['codigo']) ?></div>
                                            <div class="col-sm-3"><strong>Categoría:</strong> <?= htmlspecialchars($it['categoria']) ?></div>
                                            <div class="col-sm-3"><strong>Estado:</strong> <?= $pillaLabel ?></div>
                                            <div class="col-sm-3"><strong>Cantidad:</strong> <?= (int)$it['cantidad'] ?></div>
                                            <div class="col-sm-6"><strong>Ubicación:</strong> <?= htmlspecialchars($it['ubicacion'] ?? '—') ?></div>
                                        </div>
                                        <?php if (!empty($it['descripcion'])): ?>
                                            <p class="mt-2 mb-0 small"><?= htmlspecialchars($it['descripcion']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center">
            <small class="text-muted">Mostrando <?= count($inmobiliario) ?> de <?= $total_in ?> registros</small>
            <?php if ($total_pag_in > 1): ?>
            <nav><ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $pagina<=1?'disabled':'' ?>"><a class="page-link" href="<?= urlPagMat($pagina-1) ?>">Anterior</a></li>
                <?php for ($i=1;$i<=$total_pag_in;$i++): ?>
                    <li class="page-item <?= $i===$pagina?'active':'' ?>"><a class="page-link" href="<?= urlPagMat($i) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?= $pagina>=$total_pag_in?'disabled':'' ?>"><a class="page-link" href="<?= urlPagMat($pagina+1) ?>">Siguiente</a></li>
            </ul></nav>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="/optometria-inventario/public/assets/js/bootstrap.bundle.min.js"></script>
<script>
const toggle = document.getElementById('menuToggle');
const menu   = document.getElementById('mobileMenu');
toggle.addEventListener('click', e => { e.stopPropagation(); menu.classList.toggle('show'); toggle.classList.toggle('open'); });
document.addEventListener('click', e => {
    if (!toggle.contains(e.target) && !menu.contains(e.target)) { menu.classList.remove('show'); toggle.classList.remove('open'); }
});

function toggleDetalle(id, fila) {
    const detalle = document.getElementById(id);
    const icon    = document.getElementById('icon-' + id);
    const abierto = detalle.classList.contains('abierto');
    document.querySelectorAll('.detalle-fila.abierto').forEach(el => el.classList.remove('abierto'));
    document.querySelectorAll('.expand-icon.abierto').forEach(el => el.classList.remove('abierto'));
    if (!abierto) { detalle.classList.add('abierto'); icon.classList.add('abierto'); }
}
</script>

<!-- ══ MODAL EQUIPO ══════════════════════════════════════════ -->
<div class="modal fade" id="modalEquipo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="tituloModalEquipo"><i class="bi bi-plus-circle me-2"></i>Agregar equipo clínico</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="equipoId">
        <div class="row g-3">
          <div class="col-sm-8">
            <label class="form-label">Nombre <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="equipoNombre" placeholder="Ej. Autorrefractómetro">
          </div>
          <div class="col-sm-4">
            <label class="form-label">Código <small class="text-muted fw-normal">(auto)</small></label>
            <input type="text" class="form-control" id="equipoCodigo" disabled placeholder="EC-AUT-001">
          </div>
          <div class="col-sm-6">
            <label class="form-label">Marca</label>
            <div class="input-group">
                <select class="form-select" id="equipoMarca"><option value="">— Sin marca —</option></select>
                <button class="btn btn-outline-secondary" type="button" id="btnNuevaMarca"><i class="bi bi-plus-lg"></i></button>
            </div>
            <div id="formMarcaRapida" class="d-none mt-2">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="inputMarcaRapida" placeholder="Nueva marca">
                    <button class="btn btn-success" id="btnGuardarMarcaRapida"><i class="bi bi-check-lg"></i></button>
                    <button class="btn btn-secondary" id="btnCancelarMarcaRapida"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
          </div>
          <div class="col-sm-6">
            <label class="form-label">Modelo</label>
            <input type="text" class="form-control" id="equipoModelo" placeholder="Ej. KR-800">
          </div>
          <div class="col-12">
            <label class="form-label">Foto del equipo</label>
            <div class="border rounded p-3 text-center position-relative" style="cursor:pointer;min-height:100px;">
                <input type="file" id="equipoFoto" accept="image/*" style="position:absolute;inset:0;opacity:0;cursor:pointer;">
                <img id="equipoFotoPreview" src="" alt="Preview" style="max-height:120px;display:none;margin:auto;">
                <div id="equipoFotoLabel">
                    <i class="bi bi-image fs-3 text-muted"></i>
                    <p class="text-muted small mb-0">Arrastra una imagen o haz clic para seleccionar</p>
                </div>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" id="equipoDesc" rows="2" placeholder="Uso clínico, características…"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Cantidades</label>
            <div class="row g-2">
                <div class="col-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-stack text-success"></i></span>
                        <input type="number" class="form-control" id="equipoCantTotal" min="1" value="1">
                        <span class="input-group-text" style="font-size:.75rem">Total</span>
                    </div>
                </div>
                <div class="col-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-check-circle text-success"></i></span>
                        <input type="number" class="form-control" id="equipoCantServicio" min="0" value="1">
                        <span class="input-group-text" style="font-size:.75rem">Servicio</span>
                    </div>
                </div>
                <div class="col-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-tools text-warning"></i></span>
                        <input type="number" class="form-control" id="equipoCantMant" min="0" value="0">
                        <span class="input-group-text" style="font-size:.75rem">Mant.</span>
                    </div>
                </div>
            </div>
            <small class="text-muted">Servicio + Mantenimiento no puede superar el Total.</small>
          </div>
          <!-- Estado calculado automáticamente — NO es un select manual -->
          <div class="col-sm-6">
            <label class="form-label">Estado <small class="text-muted fw-normal">(automático)</small></label>
            <div class="form-control bg-light" id="equipoEstadoInfo">Se calculará según las cantidades</div>
            <small class="text-muted">≥1 servicio → Activo &nbsp;|&nbsp; 0 servicio ≥1 mant. → En mantenimiento &nbsp;|&nbsp; 0 ambos → Baja</small>
          </div>
        </div>
        <div class="alert d-none mt-3" id="alertaEquipo"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" id="btnGuardarEquipo"><i class="bi bi-floppy me-1"></i> Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ MODAL ELIMINAR EQUIPO ═════════════════════════════════ -->
<div class="modal fade" id="modalEliminarEq" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Eliminar equipo</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>¿Confirmas la eliminación de <strong id="nombreEliminarEq"></strong> <code id="codigoEliminarEq"></code>?</p>
        <p class="text-danger small mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Esta acción no se puede deshacer.</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-danger" id="btnConfirmarEliminarEq"><i class="bi bi-trash me-1"></i> Sí, eliminar</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ MODAL INMOBILIARIO ════════════════════════════════════ -->
<div class="modal fade" id="modalInmo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="tituloModalInmo"><i class="bi bi-plus-circle me-2"></i>Agregar inmobiliario</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="inmoId">
        <div class="row g-3">
          <div class="col-sm-8">
            <label class="form-label">Nombre <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="inmoNombre" placeholder="Ej. Silla ergonómica, Escritorio, Laptop">
          </div>
          <div class="col-sm-4">
            <label class="form-label">Código <small class="text-muted fw-normal">(auto)</small></label>
            <input type="text" class="form-control" id="inmoCodigo" disabled placeholder="INM-SIL-001">
          </div>
          <div class="col-sm-6">
            <label class="form-label">Categoría <span class="text-danger">*</span></label>
            <div class="input-group">
                <select class="form-select" id="inmoCategoria"><option value="">— Selecciona —</option></select>
                <button class="btn btn-outline-secondary" type="button" id="btnNuevaCategoria"><i class="bi bi-plus-lg"></i></button>
            </div>
            <div id="formCatRapida" class="d-none mt-2">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="inputCatRapida" placeholder="Nueva categoría">
                    <button class="btn btn-success" id="btnGuardarCatRapida"><i class="bi bi-check-lg"></i></button>
                    <button class="btn btn-secondary" id="btnCancelarCatRapida"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Foto del equipo</label>
            <div class="border rounded p-3 text-center position-relative" style="cursor:pointer;min-height:100px;">
                <input type="file" id="inmoFoto" accept="image/*" style="position:absolute;inset:0;opacity:0;cursor:pointer;">
                <img id="inmoFotoPreview" src="" alt="Preview" style="max-height:120px;display:none;margin:auto;">
                <div id="inmoFotoLabel">
                    <i class="bi bi-image fs-3 text-muted"></i>
                    <p class="text-muted small mb-0">Arrastra una imagen o haz clic para seleccionar</p>
                </div>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" id="inmoDesc" rows="2" placeholder="Color, características, observaciones…"></textarea>
          </div>
          <div class="col-sm-6">
            <label class="form-label">Cantidad</label>
            <input type="number" class="form-control" id="inmoCantidad" min="1" value="1">
          </div>
          <!-- Inmobiliario SÍ tiene estado manual (no hay cantidades servicio/mant) -->
          <div class="col-sm-6">
            <label class="form-label">Estado</label>
            <select class="form-select" id="inmoEstado">
                <option value="activo">Activo</option>
                <option value="en_espera">En espera</option>
                <option value="en_reparacion">En reparación</option>
                <option value="baja">Baja</option>
            </select>
          </div>
        </div>
        <div class="alert d-none mt-3" id="alertaInmo"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" id="btnGuardarInmo"><i class="bi bi-floppy me-1"></i> Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ MODAL ELIMINAR INMOBILIARIO ══════════════════════════ -->
<div class="modal fade" id="modalEliminarInmo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Eliminar registro</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>¿Confirmas la eliminación de <strong id="nombreEliminarInmo"></strong> <code id="codigoEliminarInmo"></code>?</p>
        <p class="text-danger small mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Esta acción no se puede deshacer.</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-danger" id="btnConfirmarEliminarInmo"><i class="bi bi-trash me-1"></i> Sí, eliminar</button>
      </div>
    </div>
  </div>
</div>

<script>
const API_URL = '<?= $API ?>';

async function apiCall(action, data = null, method = 'GET') {
    let url = `${API_URL}?action=${action}`;
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (method === 'GET' && data) {
        url += '&' + new URLSearchParams(data).toString();
    } else if (data) {
        opts.body = JSON.stringify({ action, ...data });
        opts.method = 'POST';
    }
    const res  = await fetch(url, opts);
    const json = await res.json();
    if (json.error) throw new Error(json.error);
    return json;
}

function showAlert(el, msg, tipo = 'danger') {
    el.className = `alert alert-${tipo}`;
    el.innerHTML = msg;
    el.classList.remove('d-none');
}

function initFoto(inputId, previewId, labelId) {
    const input = document.getElementById(inputId);
    input.addEventListener('change', () => {
        const file = input.files[0];
        if (!file || !file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = e => {
            const prev = document.getElementById(previewId);
            prev.src = e.target.result;
            prev.style.display = 'block';
            document.getElementById(labelId).style.display = 'none';
        };
        reader.readAsDataURL(file);
    });
}

function limpiarFoto(previewId, labelId, inputId) {
    const prev = document.getElementById(previewId);
    prev.src = ''; prev.style.display = 'none';
    document.getElementById(labelId).style.display = '';
    document.getElementById(inputId).value = '';
}

async function fotoBase64(inputId) {
    const file = document.getElementById(inputId).files[0];
    if (!file) return null;
    return new Promise(res => {
        const r = new FileReader();
        r.onload = e => res(e.target.result);
        r.readAsDataURL(file);
    });
}

initFoto('equipoFoto', 'equipoFotoPreview', 'equipoFotoLabel');
initFoto('inmoFoto',   'inmoFotoPreview',   'inmoFotoLabel');

// Actualiza el indicador de estado en tiempo real según las cantidades
function actualizarEstadoInfo() {
    const srv = parseInt(document.getElementById('equipoCantServicio').value) || 0;
    const mnt = parseInt(document.getElementById('equipoCantMant').value)     || 0;
    const info = document.getElementById('equipoEstadoInfo');
    if (!info) return;
    if (srv > 0)      { info.textContent = '✅ Activo';             info.style.color = '#2e7d32'; }
    else if (mnt > 0) { info.textContent = '🔧 En mantenimiento';   info.style.color = '#e65100'; }
    else              { info.textContent = '❌ Baja';                info.style.color = '#c62828'; }
}
document.getElementById('equipoCantServicio').addEventListener('input', actualizarEstadoInfo);
document.getElementById('equipoCantMant').addEventListener('input',     actualizarEstadoInfo);

let _marcas = [], _categorias = [], _elimEqId = null, _elimInmoId = null;

async function cargarMarcasYCategorias() {
    try {
        [_marcas, _categorias] = await Promise.all([
            apiCall('listar_marcas'), apiCall('listar_categorias')
        ]);
        poblarMarcas(); poblarCategorias();
    } catch(e) { console.error('Error cargando catálogos:', e.message); }
}

function poblarMarcas() {
    const sel = document.getElementById('equipoMarca');
    const val = sel.value;
    sel.innerHTML = '<option value="">— Sin marca —</option>';
    _marcas.forEach(m => { const o = document.createElement('option'); o.value=m.id; o.textContent=m.nombre; sel.appendChild(o); });
    sel.value = val;
}

function poblarCategorias() {
    const sel = document.getElementById('inmoCategoria');
    const val = sel.value;
    sel.innerHTML = '<option value="">— Selecciona —</option>';
    _categorias.forEach(c => { const o = document.createElement('option'); o.value=c.id; o.textContent=c.nombre; sel.appendChild(o); });
    sel.value = val;
}

const modalEq       = new bootstrap.Modal(document.getElementById('modalEquipo'));
const modalElimEq   = new bootstrap.Modal(document.getElementById('modalEliminarEq'));
const modalInmo     = new bootstrap.Modal(document.getElementById('modalInmo'));
const modalElimInmo = new bootstrap.Modal(document.getElementById('modalEliminarInmo'));

document.getElementById('btnAbrirAgregar').addEventListener('click', () => {
    const tab = new URLSearchParams(window.location.search).get('tab') || 'equipos';
    if (tab === 'equipos') {
        limpiarFormEq();
        document.getElementById('tituloModalEquipo').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Agregar equipo clínico';
        modalEq.show();
    } else {
        limpiarFormInmo();
        document.getElementById('tituloModalInmo').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Agregar inmobiliario';
        modalInmo.show();
    }
});

// ── EQUIPOS ──────────────────────────────────────────────────
function limpiarFormEq() {
    ['equipoId','equipoCodigo','equipoNombre','equipoModelo','equipoDesc'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('equipoMarca').value        = '';
    document.getElementById('equipoCantTotal').value    = '1';
    document.getElementById('equipoCantServicio').value = '1';
    document.getElementById('equipoCantMant').value     = '0';
    // Estado calculado — mostrar texto inicial
    document.getElementById('equipoEstadoInfo').textContent = '✅ Activo';
    document.getElementById('equipoEstadoInfo').style.color = '#2e7d32';
    document.getElementById('alertaEquipo').classList.add('d-none');
    document.getElementById('formMarcaRapida').classList.add('d-none');
    document.getElementById('inputMarcaRapida').value = '';
    limpiarFoto('equipoFotoPreview', 'equipoFotoLabel', 'equipoFoto');
}

document.getElementById('btnGuardarEquipo').addEventListener('click', async () => {
    const id     = document.getElementById('equipoId').value;
    const alerta = document.getElementById('alertaEquipo');
    alerta.classList.add('d-none');

    const foto = await fotoBase64('equipoFoto');
    const payload = {
        nombre:                 document.getElementById('equipoNombre').value.trim(),
        descripcion:            document.getElementById('equipoDesc').value.trim(),
        marca_id:               document.getElementById('equipoMarca').value || null,
        modelo:                 document.getElementById('equipoModelo').value.trim(),
        cantidad_total:         document.getElementById('equipoCantTotal').value,
        cantidad_servicio:      document.getElementById('equipoCantServicio').value,
        cantidad_mantenimiento: document.getElementById('equipoCantMant').value,
        foto_base64:            foto,
        // 'estado' NO se envía — el servidor lo calcula automáticamente
    };

    if (!payload.nombre) { showAlert(alerta, 'El nombre es obligatorio.'); return; }

    const btn = document.getElementById('btnGuardarEquipo');
    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
        if (id) {
            await apiCall('editar_equipo', { id, ...payload }, 'POST');
            showAlert(alerta, '<i class="bi bi-check-circle me-1"></i>Equipo actualizado.', 'success');
        } else {
            const res = await apiCall('agregar_equipo', payload, 'POST');
            showAlert(alerta, `<i class="bi bi-check-circle me-1"></i>Código asignado: <strong>${res.codigo}</strong>`, 'success');
        }
        setTimeout(() => { modalEq.hide(); location.reload(); }, 1000);
    } catch(e) {
        showAlert(alerta, e.message);
    } finally {
        btn.disabled = false; btn.textContent = 'Guardar';
    }
});

function abrirEditarEq(id) {
    apiCall('listar_equipos').then(lista => {
        const eq = lista.find(e => e.id == id);
        if (!eq) return;
        limpiarFormEq();
        document.getElementById('equipoId').value           = eq.id;
        document.getElementById('equipoCodigo').value       = eq.codigo;
        document.getElementById('equipoNombre').value       = eq.nombre;
        document.getElementById('equipoModelo').value       = eq.modelo || '';
        document.getElementById('equipoDesc').value         = eq.descripcion || '';
        document.getElementById('equipoCantTotal').value    = eq.cantidad_total;
        document.getElementById('equipoCantServicio').value = eq.cantidad_servicio;
        document.getElementById('equipoCantMant').value     = eq.cantidad_mantenimiento;
        // Actualizar el indicador con los valores reales del equipo
        actualizarEstadoInfo();
        poblarMarcas();
        document.getElementById('equipoMarca').value = eq.marca_id || '';
        if (eq.imagen_url) {
            const prev = document.getElementById('equipoFotoPreview');
            prev.src = eq.imagen_url; prev.style.display = 'block';
            document.getElementById('equipoFotoLabel').style.display = 'none';
        }
        document.getElementById('tituloModalEquipo').innerHTML = `<i class="bi bi-pencil me-2"></i>Editar — ${eq.nombre}`;
        modalEq.show();
    }).catch(e => alert('Error: ' + e.message));
}

document.getElementById('btnConfirmarEliminarEq').addEventListener('click', async () => {
    try { await apiCall('eliminar_equipo', { id: _elimEqId }, 'POST'); modalElimEq.hide(); location.reload(); }
    catch(e) { alert('Error: ' + e.message); }
});

document.getElementById('btnNuevaMarca').addEventListener('click', () => document.getElementById('formMarcaRapida').classList.toggle('d-none'));
document.getElementById('btnCancelarMarcaRapida').addEventListener('click', () => { document.getElementById('formMarcaRapida').classList.add('d-none'); document.getElementById('inputMarcaRapida').value = ''; });
document.getElementById('btnGuardarMarcaRapida').addEventListener('click', async () => {
    const nombre = document.getElementById('inputMarcaRapida').value.trim();
    const alerta = document.getElementById('alertaEquipo');
    if (!nombre) { showAlert(alerta, 'Escribe el nombre de la marca.'); return; }
    try {
        const res = await apiCall('agregar_marca', { nombre }, 'POST');
        _marcas.push({ id: res.id, nombre: res.nombre });
        _marcas.sort((a,b) => a.nombre.localeCompare(b.nombre));
        poblarMarcas();
        document.getElementById('equipoMarca').value = res.id;
        document.getElementById('formMarcaRapida').classList.add('d-none');
        document.getElementById('inputMarcaRapida').value = '';
        showAlert(alerta, `Marca <strong>${res.nombre}</strong> agregada.`, 'success');
    } catch(e) { showAlert(alerta, e.message); }
});

// ── INMOBILIARIO ─────────────────────────────────────────────
function limpiarFormInmo() {
    ['inmoId','inmoCodigo','inmoNombre','inmoDesc'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('inmoCategoria').value = '';
    document.getElementById('inmoCantidad').value  = '1';
    document.getElementById('inmoEstado').value    = 'activo';
    document.getElementById('alertaInmo').classList.add('d-none');
    document.getElementById('formCatRapida').classList.add('d-none');
    document.getElementById('inputCatRapida').value = '';
    limpiarFoto('inmoFotoPreview', 'inmoFotoLabel', 'inmoFoto');
}

document.getElementById('btnGuardarInmo').addEventListener('click', async () => {
    const id     = document.getElementById('inmoId').value;
    const alerta = document.getElementById('alertaInmo');
    alerta.classList.add('d-none');

    const foto = await fotoBase64('inmoFoto');
    const payload = {
        nombre:       document.getElementById('inmoNombre').value.trim(),
        descripcion:  document.getElementById('inmoDesc').value.trim(),
        categoria_id: document.getElementById('inmoCategoria').value || null,
        cantidad:     document.getElementById('inmoCantidad').value,
        estado:       document.getElementById('inmoEstado').value,
        foto_base64:  foto,
    };

    if (!payload.nombre)       { showAlert(alerta, 'El nombre es obligatorio.'); return; }
    if (!payload.categoria_id) { showAlert(alerta, 'Selecciona una categoría.'); return; }

    const btn = document.getElementById('btnGuardarInmo');
    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
        if (id) {
            await apiCall('editar_inmobiliario', { id, ...payload }, 'POST');
            showAlert(alerta, '<i class="bi bi-check-circle me-1"></i>Item actualizado.', 'success');
        } else {
            const res = await apiCall('agregar_inmobiliario', payload, 'POST');
            showAlert(alerta, `<i class="bi bi-check-circle me-1"></i>Código asignado: <strong>${res.codigo}</strong>`, 'success');
        }
        setTimeout(() => { modalInmo.hide(); location.reload(); }, 1000);
    } catch(e) {
        showAlert(alerta, e.message);
    } finally {
        btn.disabled = false; btn.textContent = 'Guardar';
    }
});

function abrirEditarInmo(id) {
    apiCall('listar_inmobiliario').then(lista => {
        const it = lista.find(i => i.id == id);
        if (!it) return;
        limpiarFormInmo();
        document.getElementById('inmoId').value       = it.id;
        document.getElementById('inmoCodigo').value   = it.codigo;
        document.getElementById('inmoNombre').value   = it.nombre;
        document.getElementById('inmoDesc').value     = it.descripcion || '';
        document.getElementById('inmoCantidad').value = it.cantidad;
        document.getElementById('inmoEstado').value   = it.estado;
        poblarCategorias();
        document.getElementById('inmoCategoria').value = it.categoria_id || '';
        if (it.imagen_url) {
            const prev = document.getElementById('inmoFotoPreview');
            prev.src = it.imagen_url; prev.style.display = 'block';
            document.getElementById('inmoFotoLabel').style.display = 'none';
        }
        document.getElementById('tituloModalInmo').innerHTML = `<i class="bi bi-pencil me-2"></i>Editar — ${it.nombre}`;
        modalInmo.show();
    }).catch(e => alert('Error: ' + e.message));
}

document.getElementById('btnConfirmarEliminarInmo').addEventListener('click', async () => {
    try { await apiCall('eliminar_inmobiliario', { id: _elimInmoId }, 'POST'); modalElimInmo.hide(); location.reload(); }
    catch(e) { alert('Error: ' + e.message); }
});

document.getElementById('btnNuevaCategoria').addEventListener('click', () => document.getElementById('formCatRapida').classList.toggle('d-none'));
document.getElementById('btnCancelarCatRapida').addEventListener('click', () => { document.getElementById('formCatRapida').classList.add('d-none'); document.getElementById('inputCatRapida').value = ''; });
document.getElementById('btnGuardarCatRapida').addEventListener('click', async () => {
    const nombre = document.getElementById('inputCatRapida').value.trim();
    const alerta = document.getElementById('alertaInmo');
    if (!nombre) { showAlert(alerta, 'Escribe el nombre de la categoría.'); return; }
    try {
        const res = await apiCall('agregar_categoria', { nombre }, 'POST');
        _categorias.push({ id: res.id, nombre: res.nombre });
        _categorias.sort((a,b) => a.nombre.localeCompare(b.nombre));
        poblarCategorias();
        document.getElementById('inmoCategoria').value = res.id;
        document.getElementById('formCatRapida').classList.add('d-none');
        document.getElementById('inputCatRapida').value = '';
        showAlert(alerta, `Categoría <strong>${res.nombre}</strong> agregada.`, 'success');
    } catch(e) { showAlert(alerta, e.message); }
});

function abrirEliminarEq(id, nombre, codigo) {
    _elimEqId = id;
    document.getElementById('nombreEliminarEq').textContent = nombre + ' ';
    document.getElementById('codigoEliminarEq').textContent = codigo;
    modalElimEq.show();
}

function abrirEliminarInmo(id, nombre, codigo) {
    _elimInmoId = id;
    document.getElementById('nombreEliminarInmo').textContent = nombre + ' ';
    document.getElementById('codigoEliminarInmo').textContent = codigo;
    modalElimInmo.show();
}

cargarMarcasYCategorias();
</script>
</body>
</html>