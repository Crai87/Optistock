<?php
// ============================================================
// src/views/inventario.php
// CAMBIOS:
//   - Botón "Ver todos" que carga todos los armazones paginados
//     ordenados por updated_at DESC (más recientes primero)
//   - Columna extra en tabla: cuando estado = donado, muestra
//     icono que al pasar el cursor revela a quién y cuándo se donó
//   - La lógica de carga ahora tiene 3 modos:
//       ver_todos=1 → todos los registros paginados
//       buscar/estado → filtro activo
//       nada         → tabla vacía (comportamiento original)
// ============================================================

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /optometria-inventario/public/index.php?view=login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

include __DIR__ . '/../../src/views/save.php';

$lastBackupDate = new DateTime($lastBackup);
$today = new DateTime();

$diff = $today->diff($lastBackupDate);

if ($diff->days >= 7) {
    header('Location: /optometria-inventario/src/views/autosave.php');
    exit;
}

// ── Permisos granulares ──────────────────────────────────────
$es_admin      = isset($_SESSION['rol']) && (int)$_SESSION['rol'] === 1;
$p             = $_SESSION['permisos'] ?? [];
$puede_agregar = $es_admin || in_array('Agregar armazones', $p);
$puede_editar  = $es_admin || in_array('Editar armazones',  $p);
$puede_reporte = $es_admin || in_array('Generar reporte armazones', $p);
$ver_todos_ok  = $es_admin || in_array('Ver armazones', $p);

// ── Auto-clasificación: inventario muerto (≥ 365 días) ───────
try {
    $pdo->exec("
        UPDATE armazones
        SET estado                  = 'inventario_muerto',
            fecha_inventario_muerto = CURDATE()
        WHERE estado = 'disponible'
          AND DATEDIFF(NOW(), created_at) >= 365
          AND fecha_inventario_muerto IS NULL
    ");
} catch (PDOException $e) { /* pre-migración, ignorar */ }

// ── Paginación ───────────────────────────────────────────────
$por_pagina = 20;
$pagina     = max(1, intval($_GET['pagina'] ?? 1));
$offset     = ($pagina - 1) * $por_pagina;

$ver_todos  = isset($_GET['ver_todos']) && $_GET['ver_todos'] == '1';
$hay_filtro = !empty($_GET['buscar']) || !empty($_GET['estado']);

$armazones       = [];
$total_registros = 0;
$total_paginas   = 1;

if ($ver_todos || $hay_filtro) {
    $where  = 'WHERE 1=1';
    $params = [];

    if ($hay_filtro) {
        if (!empty($_GET['buscar'])) {
            $b = '%' . $_GET['buscar'] . '%';
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
        if (!empty($_GET['estado'])) {
            $where .= ' AND a.estado = :estado';
            $params['estado'] = $_GET['estado'];
        }
    }

    $sqlCount = "SELECT COUNT(*) FROM armazones a
                 LEFT JOIN marcas_armazon m ON m.id = a.marca_id
                 LEFT JOIN proveedores p    ON p.id = a.proveedor_id
                 $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total_registros = (int)$stmtCount->fetchColumn();
    $total_paginas   = max(1, ceil($total_registros / $por_pagina));

    $sql = "SELECT a.*, m.nombre AS nombre_marca, p.nombre AS nombre_proveedor
            FROM armazones a
            LEFT JOIN marcas_armazon m ON m.id = a.marca_id
            LEFT JOIN proveedores p    ON p.id = a.proveedor_id
            $where
            ORDER BY a.updated_at DESC, a.created_at DESC
            LIMIT :limite OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue('limite', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset,     PDO::PARAM_INT);
    $stmt->execute();
    $armazones = $stmt->fetchAll();
}

// ── Mensajes ─────────────────────────────────────────────────
$msg_ok    = !empty($_GET['ok'])    ? 'Operación realizada correctamente.' : '';
$msg_error = !empty($_GET['error']) ? htmlspecialchars($_GET['error'])     : '';

// ── URL base para paginación (preserva filtros y ver_todos) ──
function urlPagina($pagina) {
    $p = $_GET;
    $p['pagina'] = $pagina;
    $p['view']   = 'inventario';
    return '/optometria-inventario/public/index.php?' . http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario de Armazones — OptiStock</title>
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/bootstrap-icons.css">
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/materiales.css">
    <style>
        /* Tooltip de donación */
        .donacion-info {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }
        .donacion-info .donacion-tooltip {
            visibility: hidden;
            opacity: 0;
            background: #1f3864;
            color: #fff;
            font-size: .78rem;
            border-radius: 6px;
            padding: 6px 10px;
            position: absolute;
            z-index: 999;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            transition: opacity .2s;
            pointer-events: none;
            box-shadow: 0 2px 8px rgba(0,0,0,.25);
        }
        .donacion-info:hover .donacion-tooltip {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body class="bg-light">

<!-- HEADER -->
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
            <li><a href="/optometria-inventario/public/index.php?view=inventario" class="active">Inventario de Armazones</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=Inventario_materiales">Inventario de Equipos</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=garantias">Garantías</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=admin">Usuarios</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=logout">Salir</a></li>
        </ul>
    </nav>
</header>

<div class="container mt-4">

    <div class="mb-3">
        <h4 class="fw-semibold"><i class="bi bi-box-seam me-2"></i>Inventario — Armazones</h4>
    </div>

    <?php if ($msg_ok): ?>
        <div class="alert alert-success alert-dismissible" role="alert">
            <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($msg_ok) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($msg_error): ?>
        <div class="alert alert-danger alert-dismissible" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i><?= $msg_error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- BARRA DE BÚSQUEDA + FILTROS -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body d-flex align-items-center gap-2 flex-wrap">
            <form method="GET" action="/optometria-inventario/public/index.php"
                  class="flex-grow-1 mb-0 d-flex gap-2">
                <input type="hidden" name="view" value="inventario">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" name="buscar" class="form-control border-start-0"
                           placeholder="Buscar por código, descripción, área, marca, material, modelo, proveedor o costo…"
                           value="<?= htmlspecialchars($_GET['buscar'] ?? '') ?>">
                    <button class="btn btn-success" type="submit">Buscar</button>
                    <?php if ($hay_filtro || $ver_todos): ?>
                        <a href="?view=inventario" class="btn btn-outline-secondary">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Ver todos -->
            <?php if (!$ver_todos && !$hay_filtro): ?>
                <a href="?view=inventario&ver_todos=1"
                   class="btn btn-outline-secondary flex-shrink-0" title="Mostrar todos los armazones">
                    <i class="bi bi-list-ul me-1"></i>Ver todos
                </a>
            <?php elseif ($ver_todos): ?>
                <a href="?view=inventario"
                   class="btn btn-outline-secondary flex-shrink-0" title="Ocultar lista completa">
                    <i class="bi bi-x-lg me-1"></i>Ocultar lista
                </a>
            <?php endif; ?>

            <!-- Filtro de estado -->
            <div class="dropdown flex-shrink-0">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                        data-bs-toggle="dropdown">
                    <i class="bi bi-funnel"></i> Filtros
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item text-success fw-semibold"
                           href="?view=inventario&estado=disponible">Disponible</a></li>
                    <li><a class="dropdown-item text-danger fw-semibold"
                           href="?view=inventario&estado=baja">Baja</a></li>
                    <li><a class="dropdown-item text-warning fw-semibold"
                           href="?view=inventario&estado=en_garantia">En garantía</a></li>
                    <li><a class="dropdown-item text-secondary fw-semibold"
                           href="?view=inventario&estado=dañado">Dañado</a></li>
                    <li><a class="dropdown-item fw-semibold" style="color:#6f42c1"
                           href="?view=inventario&estado=donado">Donado</a></li>
                    <li><a class="dropdown-item fw-semibold" style="color:#0dcaf0"
                           href="?view=inventario&estado=inventario_muerto">Inventario muerto</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="?view=inventario">Quitar filtro</a></li>
                </ul>
            </div>

            <!-- Reporte PDF — solo si tiene permiso y NO está en modo ver_todos -->
            <?php if ($puede_reporte && !$ver_todos): ?>
            <div class="dropdown flex-shrink-0">
                <?php if ($hay_filtro): ?>
                <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="bi bi-file-earmark-bar-graph"></i> Generar Reporte</button>
                <?php else: ?>
                <button disabled class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="bi bi-file-earmark-bar-graph"></i> Generar Reporte</button>
                <?php endif; ?>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a target="_blank" class="dropdown-item fw-semibold" href="/optometria-inventario/public/generar_reporte.php?buscar=<?= urlencode($_GET['buscar'] ?? '') ?>&estado=<?= urlencode($_GET['estado'] ?? '') ?>">Filtros Aplicados</a></li>
                    <li><a target="_blank" class="dropdown-item fw-semibold" href="/optometria-inventario/public/generar_reporte_general.php?buscar=<?= urlencode($_GET['buscar'] ?? '') ?>&estado=<?= urlencode($_GET['estado'] ?? '') ?>">Resumen General</a></li>
                    <li><a target="_blank" class="dropdown-item fw-semibold" href="/optometria-inventario/public/generar_reporte_proveedor.php?buscar=<?= urlencode($_GET['buscar'] ?? '') ?>&estado=<?= urlencode($_GET['estado'] ?? '') ?>">Por Proveedor</a></li>
                    <li><a target="_blank" class="dropdown-item fw-semibold" href="/optometria-inventario/public/generar_reporte_marca.php?buscar=<?= urlencode($_GET['buscar'] ?? '') ?>&estado=<?= urlencode($_GET['estado'] ?? '') ?>">Por Marca</a></li>
                    <li><a target="_blank" class="dropdown-item fw-semibold" href="/optometria-inventario/public/generar_reporte_critico.php?buscar=<?= urlencode($_GET['buscar'] ?? '') ?>&estado=<?= urlencode($_GET['estado'] ?? '') ?>">Inventario Critico</a></li>
                    <li><a target="_blank" class="dropdown-item fw-semibold" href="/optometria-inventario/public/generar_reporte_edad.php?buscar=<?= urlencode($_GET['buscar'] ?? '') ?>&estado=<?= urlencode($_GET['estado'] ?? '') ?>">Edad Inventario</a></li>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Agregar — solo si tiene permiso agregar -->
            <?php if ($puede_agregar): ?>
            <a href="/optometria-inventario/public/index.php?view=agregar_armazon"
               class="btn btn-success flex-shrink-0">
                <i class="bi bi-plus-lg"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- TABLA -->
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-success">
                    <tr>
                        <th>Código</th>
                        <th>Descripción</th>
                        <th>Área</th>
                        <th>Marca</th>
                        <th>Material</th>
                        <th>Modelo</th>
                        <th>Proveedor</th>
                        <th>Costo</th>
                        <th>Estado</th>
                        <th class="text-center">Stock</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($armazones)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted">
                                <?php if ($hay_filtro): ?>
                                    <i class="bi bi-search fs-1"></i>
                                    <p class="mt-2 fw-semibold">No se encontraron armazones con ese criterio</p>
                                <?php else: ?>
                                    <i class="bi bi-search fs-1"></i>
                                    <p class="mt-2 fw-semibold">Usa el buscador, los filtros o "Ver todos" para ver armazones</p>
                                    <small>Puedes buscar por código, descripción, área, marca, material, modelo, proveedor o costo</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($armazones as $a):
                            $estado = trim(strtolower($a['estado'] ?? ''));

                            $badgeStyle = match($estado) {
                                'disponible'        => 'background:#d1e7dd; color:#0a3622;',
                                'baja'              => 'background:#f8d7da; color:#58151c;',
                                'en_garantia'       => 'background:#fff3cd; color:#664d03;',
                                'dañado'            => 'background:#e2e3e5; color:#2b2d2f;',
                                'donado'            => 'background:#ede7f6; color:#6f42c1;',
                                'inventario_muerto' => 'background:#cff4fc; color:#055160;',
                                default             => 'background:#e2e3e5; color:#2b2d2f;',
                            };

                            $etiqueta = match($estado) {
                                'disponible'        => 'Disponible',
                                'baja'              => 'Baja',
                                'en_garantia'       => 'En Garantía',
                                'dañado'            => 'Dañado',
                                'donado'            => 'Donado',
                                'inventario_muerto' => 'Inv. Muerto',
                                default             => ucfirst(str_replace('_', ' ', $estado)),
                            };
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($a['codigo']) ?></td>
                            <td><?= htmlspecialchars($a['descripcion'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($a['area'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($a['nombre_marca'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($a['material'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($a['modelo'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($a['nombre_proveedor'] ?? '—') ?></td>
                            <td><?= $a['costo'] !== null ? '$' . number_format($a['costo'], 2) : '—' ?></td>
                            <td>
                                <?php if ($estado === 'donado'): ?>
                                    <div class="donacion-info">
                                        <span class="badge rounded-pill px-3 fw-semibold"
                                              style="<?= $badgeStyle ?>; font-size:.78em; cursor:<?= (!empty($a['donado_a']) || !empty($a['fecha_donacion'])) ? 'help' : 'default' ?>;">
                                            <?= $etiqueta ?><?php if (!empty($a['donado_a']) || !empty($a['fecha_donacion'])): ?> <i class="bi bi-info-circle ms-1"></i><?php endif; ?>
                                        </span>
                                        <?php if (!empty($a['donado_a']) || !empty($a['fecha_donacion'])): ?>
                                        <div class="donacion-tooltip">
                                            <?php if (!empty($a['donado_a'])): ?>
                                                <div><strong>A:</strong> <?= htmlspecialchars($a['donado_a']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($a['fecha_donacion'])): ?>
                                                <div><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($a['fecha_donacion'])) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge rounded-pill px-3 fw-semibold"
                                          style="<?= $badgeStyle ?>; font-size:.78em;">
                                        <?= $etiqueta ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= (int)($a['cantidad_stock'] ?? 0) ?></td>
                            <td>
                                <?php if ($puede_editar): ?>
                                <div class="d-flex gap-1">
                                    <a href="/optometria-inventario/public/index.php?view=editar_armazon&id=<?= $a['id'] ?>"
                                       class="btn btn-outline-secondary btn-sm" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST"
                                          action="/optometria-inventario/src/views/classes/eliminar_armazon.php"
                                          onsubmit="return confirm('¿Eliminar el armazón <?= htmlspecialchars(addslashes($a['codigo'])) ?>?')">
                                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN -->
        <div class="card-footer bg-white border-0 pt-3 pb-3 px-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                <!-- Contador de registros -->
                <div class="d-flex align-items-center gap-2">
                    <?php if ($hay_filtro || $ver_todos): ?>
                        <span class="badge rounded-pill px-3 py-2 fw-semibold"
                              style="background:#d1e7dd; color:#0a3622; font-size:.8rem;">
                            <i class="bi bi-list-check me-1"></i>
                            <?= count($armazones) ?> de <?= $total_registros ?> registros
                        </span>
                        <?php if ($total_paginas > 1): ?>
                        <span class="text-muted" style="font-size:.8rem;">
                            Página <?= $pagina ?> de <?= $total_paginas ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($ver_todos): ?>
                            <span class="badge rounded-pill px-2 py-1 fw-normal"
                                  style="background:#e2e3e5; color:#2b2d2f; font-size:.75rem;">
                                <i class="bi bi-sort-down me-1"></i>Más recientes primero
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted" style="font-size:.85rem;">
                            <i class="bi bi-info-circle me-1"></i>
                            Ingresa un criterio de búsqueda o haz clic en "Ver todos"
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Controles de paginación -->
                <?php if ($total_paginas > 1): ?>
                <nav aria-label="Paginación de armazones">
                    <ul class="pagination mb-0" style="gap:4px;">

                        <!-- Primera página -->
                        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link border-0 rounded-2 px-2"
                               href="<?= urlPagina(1) ?>"
                               title="Primera página"
                               style="color:#0a3622; background:<?= $pagina <= 1 ? '#f8f9fa' : '#fff' ?>; transition:background .15s;">
                                <i class="bi bi-chevron-double-left"></i>
                            </a>
                        </li>

                        <!-- Anterior -->
                        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link border-0 rounded-2 px-2"
                               href="<?= urlPagina($pagina - 1) ?>"
                               title="Página anterior"
                               style="color:#0a3622; background:<?= $pagina <= 1 ? '#f8f9fa' : '#fff' ?>; transition:background .15s;">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>

                        <?php
                        $inicio = max(1, $pagina - 2);
                        $fin    = min($total_paginas, $pagina + 2);

                        // Elipsis al inicio
                        if ($inicio > 1): ?>
                            <li class="page-item">
                                <a class="page-link border-0 rounded-2 px-3"
                                   href="<?= urlPagina(1) ?>"
                                   style="color:#0a3622; background:#fff;">1</a>
                            </li>
                            <?php if ($inicio > 2): ?>
                            <li class="page-item disabled">
                                <span class="page-link border-0 bg-transparent text-muted px-2">…</span>
                            </li>
                            <?php endif;
                        endif;

                        // Páginas centrales
                        for ($i = $inicio; $i <= $fin; $i++): ?>
                            <li class="page-item">
                                <a class="page-link border-0 rounded-2 px-3 fw-semibold"
                                   href="<?= urlPagina($i) ?>"
                                   style="<?= $i === $pagina
                                       ? 'background:#198754; color:#fff; box-shadow:0 2px 6px rgba(25,135,84,.35);'
                                       : 'background:#fff; color:#0a3622;' ?>
                                       transition:all .15s;">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor;

                        // Elipsis al final
                        if ($fin < $total_paginas):
                            if ($fin < $total_paginas - 1): ?>
                            <li class="page-item disabled">
                                <span class="page-link border-0 bg-transparent text-muted px-2">…</span>
                            </li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link border-0 rounded-2 px-3"
                                   href="<?= urlPagina($total_paginas) ?>"
                                   style="color:#0a3622; background:#fff;"><?= $total_paginas ?></a>
                            </li>
                        <?php endif; ?>

                        <!-- Siguiente -->
                        <li class="page-item <?= $pagina >= $total_paginas ? 'disabled' : '' ?>">
                            <a class="page-link border-0 rounded-2 px-2"
                               href="<?= urlPagina($pagina + 1) ?>"
                               title="Página siguiente"
                               style="color:#0a3622; background:<?= $pagina >= $total_paginas ? '#f8f9fa' : '#fff' ?>; transition:background .15s;">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>

                        <!-- Última página -->
                        <li class="page-item <?= $pagina >= $total_paginas ? 'disabled' : '' ?>">
                            <a class="page-link border-0 rounded-2 px-2"
                               href="<?= urlPagina($total_paginas) ?>"
                               title="Última página"
                               style="color:#0a3622; background:<?= $pagina >= $total_paginas ? '#f8f9fa' : '#fff' ?>; transition:background .15s;">
                                <i class="bi bi-chevron-double-right"></i>
                            </a>
                        </li>

                    </ul>
                </nav>
                <?php endif; ?>

            </div>
        </div>
    </div>

</div>

<script src="/optometria-inventario/public/assets/js/bootstrap.bundle.min.js"></script>
<script>
const toggle = document.getElementById('menuToggle');
const menu   = document.getElementById('mobileMenu');
toggle.addEventListener('click', e => {
    e.stopPropagation();
    menu.classList.toggle('show');
    toggle.classList.toggle('open');
});
document.addEventListener('click', e => {
    if (!toggle.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.remove('show');
        toggle.classList.remove('open');
    }
});
</script>
</body>
</html>