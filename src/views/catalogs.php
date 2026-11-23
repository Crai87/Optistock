<?php
// ============================================================
// src/views/catalogs.php
// Gestión de Proveedores y Marcas de Armazones.
// Accesible desde el panel de Admin.
// Patrón: formulario izquierda + tabla derecha, PHP puro con redirects.
// ============================================================

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /optometria-inventario/public/index.php?view=login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

// ── Pestaña activa (proveedores por defecto) ─────────────────
$tab = $_GET['tab'] ?? 'proveedores';
if (!in_array($tab, ['proveedores', 'marcas'])) $tab = 'proveedores';

// ── Mensajes de operación anterior ──────────────────────────
$msg_ok    = !empty($_GET['ok'])    ? htmlspecialchars($_GET['ok'])    : '';
$msg_error = !empty($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

// ── Cargar datos ─────────────────────────────────────────────
$proveedores = $pdo->query('SELECT id, nombre, telefono, email FROM proveedores ORDER BY nombre')->fetchAll();
$marcas      = $pdo->query('SELECT id, nombre FROM marcas_armazon ORDER BY nombre')->fetchAll();

// ── Proveedor/Marca en edición (si viene ?editar_prov=N o ?editar_marca=N) ──
$editar_prov  = null;
$editar_marca = null;

if (!empty($_GET['editar_prov'])) {
    $st = $pdo->prepare('SELECT id, nombre, telefono, email FROM proveedores WHERE id = :id');
    $st->execute(['id' => intval($_GET['editar_prov'])]);
    $editar_prov = $st->fetch();
    $tab = 'proveedores';
}

if (!empty($_GET['editar_marca'])) {
    $st = $pdo->prepare('SELECT id, nombre FROM marcas_armazon WHERE id = :id');
    $st->execute(['id' => intval($_GET['editar_marca'])]);
    $editar_marca = $st->fetch();
    $tab = 'marcas';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogos — OptiStock</title>
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/bootstrap-icons.css">
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/materiales.css">
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/administrador.css">
</head>
<body>

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
            <li><a href="/optometria-inventario/public/index.php?view=inventario">Inventario de Armazones</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=Inventario_materiales">Inventario de Equipos</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=garantias">Garantías</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=admin">Usuarios</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=logout">Salir</a></li>
        </ul>
    </nav>
</header>

<div class="container py-5">

    <!-- TÍTULO + VOLVER -->
    <div class="page-title-row mb-4">
        <div>
            <h2 class="page-title"><i class="bi bi-card-list me-2"></i>Catálogos</h2>
            <p class="page-subtitle">Gestiona proveedores y marcas de armazones</p>
        </div>
        <a href="/optometria-inventario/public/index.php?view=admin"
           class="btn btn-outline-secondary btn-sm">
            <i class=""></i> Volver a Usuarios
        </a>
    </div>

    <!-- MENSAJES -->
    <?php if ($msg_ok): ?>
        <div class="alert alert-success alert-dismissible" role="alert">
            <i class="bi bi-check-circle me-1"></i><?= $msg_ok ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($msg_error): ?>
        <div class="alert alert-danger alert-dismissible" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i><?= $msg_error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- PESTAÑAS -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'proveedores' ? 'active' : '' ?>"
               href="?view=catalogs&tab=proveedores">
                <i class=""></i> Proveedores
                <span class="badge bg-secondary ms-1"><?= count($proveedores) ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'marcas' ? 'active' : '' ?>"
               href="?view=catalogs&tab=marcas">
                <i class=""></i> Marcas de Armazones
                <span class="badge bg-secondary ms-1"><?= count($marcas) ?></span>
            </a>
        </li>
    </ul>

    <!-- ══════════════════════════════════════════════════════
         PESTAÑA: PROVEEDORES
    ══════════════════════════════════════════════════════ -->
    <?php if ($tab === 'proveedores'): ?>
    <div class="row g-4">

        <!-- Formulario -->
        <div class="col-lg-4">
            <div class="form-card">
                <div class="form-card-header">
                    <h5><?= $editar_prov ? 'Editar Proveedor' : 'Nuevo Proveedor' ?></h5>
                </div>
                <div class="form-card-body">
                    <form method="POST"
                          action="/optometria-inventario/src/views/classes/guardar_proveedor.php">

                        <?php if ($editar_prov): ?>
                            <input type="hidden" name="id" value="<?= $editar_prov['id'] ?>">
                        <?php endif; ?>
                        <input type="hidden" name="tab" value="proveedores">

                        <div class="mb-3">
                            <label class="form-label">Nombre <span class="req">*</span></label>
                            <input type="text" name="nombre" class="form-control custom-input"
                                   required maxlength="150"
                                   value="<?= htmlspecialchars($editar_prov['nombre'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control custom-input"
                                   maxlength="20"
                                   value="<?= htmlspecialchars($editar_prov['telefono'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control custom-input"
                                   maxlength="120"
                                   value="<?= htmlspecialchars($editar_prov['email'] ?? '') ?>">
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <?php if ($editar_prov): ?>
                                <a href="?view=catalogs&tab=proveedores"
                                   class="btn btn-outline-secondary flex-fill">Cancelar</a>
                                <button type="submit" class="btn btn-success flex-fill">
                                    <i class="bi bi-floppy me-1"></i> Guardar Cambios
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-plus-lg me-1"></i> Agregar Proveedor
                                </button>
                            <?php endif; ?>
                        </div>

                    </form>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="col-lg-8">
            <div class="form-card">
                <div class="form-card-header">
                    <h5>Proveedores Registrados</h5>
                    <span class="badge-count"><?= count($proveedores) ?></span>
                </div>
                <div class="form-card-body p-0">
                    <div class="table-responsive">
                        <table class="table user-table mb-0">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Teléfono</th>
                                    <th>Email</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($proveedores)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            No hay proveedores registrados.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($proveedores as $p): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($p['nombre']) ?></td>
                                        <td><?= htmlspecialchars($p['telefono'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($p['email'] ?? '—') ?></td>
                                        <td>
                                            <div class="action-btns d-flex gap-1">
                                                <a href="?view=catalogs&editar_prov=<?= $p['id'] ?>"
                                                   class="btn btn-outline-secondary btn-sm" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="POST"
                                                      action="/optometria-inventario/src/views/classes/eliminar_proveedor.php"
                                                      onsubmit="return confirm('¿Eliminar al proveedor <?= htmlspecialchars(addslashes($p['nombre'])) ?>?')">
                                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                    <button type="submit"
                                                            class="btn btn-outline-danger btn-sm" title="Eliminar">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════
         PESTAÑA: MARCAS DE ARMAZONES
    ══════════════════════════════════════════════════════ -->
    <?php if ($tab === 'marcas'): ?>
    <div class="row g-4">

        <!-- Formulario -->
        <div class="col-lg-4">
            <div class="form-card">
                <div class="form-card-header">
                    <h5><?= $editar_marca ? 'Editar Marca' : 'Nueva Marca' ?></h5>
                </div>
                <div class="form-card-body">
                    <form method="POST"
                          action="/optometria-inventario/src/views/classes/guardar_marca_armazon.php">

                        <?php if ($editar_marca): ?>
                            <input type="hidden" name="id" value="<?= $editar_marca['id'] ?>">
                        <?php endif; ?>
                        <input type="hidden" name="tab" value="marcas">

                        <div class="mb-3">
                            <label class="form-label">Nombre <span class="req">*</span></label>
                            <input type="text" name="nombre" class="form-control custom-input"
                                   required maxlength="100"
                                   placeholder="Ej. Ray-Ban, Oakley…"
                                   value="<?= htmlspecialchars($editar_marca['nombre'] ?? '') ?>">
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <?php if ($editar_marca): ?>
                                <a href="?view=catalogs&tab=marcas"
                                   class="btn btn-outline-secondary flex-fill">Cancelar</a>
                                <button type="submit" class="btn btn-success flex-fill">
                                    <i class="bi bi-floppy me-1"></i> Guardar Cambios
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-plus-lg me-1"></i> Agregar Marca
                                </button>
                            <?php endif; ?>
                        </div>

                    </form>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="col-lg-8">
            <div class="form-card">
                <div class="form-card-header">
                    <h5>Marcas Registradas</h5>
                    <span class="badge-count"><?= count($marcas) ?></span>
                </div>
                <div class="form-card-body p-0">
                    <div class="table-responsive">
                        <table class="table user-table mb-0">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Armazones vinculados</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($marcas)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            No hay marcas registradas.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php
                                    // Contar armazones por marca en una sola query
                                    $conteos = [];
                                    $rows = $pdo->query('SELECT marca_id, COUNT(*) AS total FROM armazones GROUP BY marca_id')->fetchAll();
                                    foreach ($rows as $r) $conteos[$r['marca_id']] = $r['total'];
                                    ?>
                                    <?php foreach ($marcas as $m):
                                        $total_arm = $conteos[$m['id']] ?? 0;
                                    ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($m['nombre']) ?></td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success">
                                                <?= $total_arm ?> armazón<?= $total_arm !== 1 ? 'es' : '' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-btns d-flex gap-1">
                                                <a href="?view=catalogs&editar_marca=<?= $m['id'] ?>"
                                                   class="btn btn-outline-secondary btn-sm" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="POST"
                                                      action="/optometria-inventario/src/views/classes/eliminar_marca_armazon.php"
                                                      onsubmit="return confirm('¿Eliminar la marca <?= htmlspecialchars(addslashes($m['nombre'])) ?>?<?= $total_arm > 0 ? ' Tiene ' . $total_arm . ' armazón/es vinculados.' : '' ?>')">
                                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                    <button type="submit"
                                                            class="btn btn-outline-danger btn-sm"
                                                            title="Eliminar"
                                                            <?= $total_arm > 0 ? 'style="opacity:.5"' : '' ?>>
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>

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