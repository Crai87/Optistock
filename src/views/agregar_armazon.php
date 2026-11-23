<?php
// ============================================================
// src/views/agregar_armazon.php
//
// CORRECCIONES:
//   - Queries usan columnas en minúsculas (id, nombre)
//   - Se agregó campo 'costo'
//   - Muestra mensaje de error si add_frame.php redirige con ?error=
//   - Muestra confirmación si viene de un insert exitoso con ?ok=1
//   - Bootstrap mínimo de JS solo para el menú hamburguesa
// ============================================================

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /optometria-inventario/public/index.php?view=login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

// Cargar marcas y proveedores con columnas en minúsculas
$marcas    = $pdo->query('SELECT id, nombre FROM marcas_armazon ORDER BY nombre')->fetchAll();
$proveedores = $pdo->query('SELECT id, nombre FROM proveedores ORDER BY nombre')->fetchAll();

$error = !empty($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
$ok    = isset($_GET['ok']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Armazón — OptiStock</title>
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/bootstrap-icons.css">
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/materiales.css">
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
            <li><a href="/optometria-inventario/public/index.php?view=inventario">Inventario de Armazones</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=Inventario_materiales">Inventario de Equipos</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=garantias">Garantías</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=admin">Usuarios</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=logout">Salir</a></li>
        </ul>
    </nav>
</header>

<div class="container mt-4" style="max-width:780px">

    <div class="mb-4 d-flex align-items-center gap-3">
        <a href="/optometria-inventario/public/index.php?view=inventario"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
        <h4 class="fw-semibold mb-0">
            <i class="bi bi-plus-circle me-2"></i>Agregar Armazón
        </h4>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($ok): ?>
        <div class="alert alert-success" role="alert">
            <i class="bi bi-check-circle me-2"></i>Armazón agregado correctamente.
            <a href="/optometria-inventario/public/index.php?view=inventario" class="alert-link">Ver inventario</a>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="POST"
                  action="/optometria-inventario/src/views/classes/add_frame.php">

                <div class="row g-3">

                    <!-- Código -->
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">
                            Código <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="codigo" class="form-control"
                               placeholder="Ej. ARM-001" required maxlength="60"
                               value="<?= htmlspecialchars($_POST['codigo'] ?? '') ?>">
                    </div>

                    <!-- Área -->
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">
                            Área / Medida <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="area" class="form-control"
                               placeholder="Ej. 52-18-140" required maxlength="80"
                               value="<?= htmlspecialchars($_POST['area'] ?? '') ?>">
                    </div>

                    <!-- Marca -->
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">
                            Marca <span class="text-danger">*</span>
                        </label>
                        <select name="marca" class="form-select" required>
                            <?php if (empty($marcas)): ?>
                                <option value="" disabled selected>No hay marcas disponibles</option>
                            <?php else: ?>
                                <option value="" hidden selected>Seleccionar marca…</option>
                                <?php foreach ($marcas as $m): ?>
                                    <option value="<?= $m['id'] ?>"
                                        <?= (($_POST['marca'] ?? '') == $m['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($m['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Material -->
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">
                            Material <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="material" class="form-control"
                               placeholder="Ej. Acetato, Metal" required maxlength="80"
                               value="<?= htmlspecialchars($_POST['material'] ?? '') ?>">
                    </div>

                    <!-- Modelo -->
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">
                            Modelo <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="modelo" class="form-control"
                               placeholder="Ej. RB2140" required maxlength="80"
                               value="<?= htmlspecialchars($_POST['modelo'] ?? '') ?>">
                    </div>

                    <!-- Proveedor -->
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">Proveedor</label>
                        <select name="proveedor" class="form-select">
                            <option value="">— Ninguno —</option>
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?= $p['id'] ?>"
                                    <?= (($_POST['proveedor'] ?? '') == $p['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Descripción -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="2"
                                  placeholder="Descripción opcional del armazón…"
                                  maxlength="500"><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                    </div>

                    <!-- Stock y Costo -->
                    <div class="col-sm-4">
                        <label class="form-label fw-semibold">
                            Cantidad <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="stock" class="form-control"
                               min="1" max="9999" required
                               value="<?= htmlspecialchars($_POST['stock'] ?? '1') ?>">
                        <div class="form-text text-muted">
                            Si ingresas más de 1, se crearán armazones con código único (ARM-001-A, ARM-001-B…)
                        </div>
                    </div>

                    <div class="col-sm-4">
                        <label class="form-label fw-semibold">Costo ($)</label>
                        <input type="number" name="costo" class="form-control"
                               min="0" step="0.01" placeholder="0.00"
                               value="<?= htmlspecialchars($_POST['costo'] ?? '') ?>">
                    </div>

                    <!-- Estado inicial -->
                    <div class="col-sm-4">
                        <label class="form-label fw-semibold">Estado inicial</label>
                        <select name="estado" class="form-select">
                            <option value="disponible"  <?= ($_POST['estado'] ?? 'disponible') === 'disponible'  ? 'selected' : '' ?>>Disponible</option>
                            <option value="en_garantia" <?= ($_POST['estado'] ?? '') === 'en_garantia' ? 'selected' : '' ?>>En garantía</option>
                            <option value="donado"      <?= ($_POST['estado'] ?? '') === 'donado'      ? 'selected' : '' ?>>Donado</option>
                            <option value="dañado"      <?= ($_POST['estado'] ?? '') === 'dañado'      ? 'selected' : '' ?>>Dañado</option>
                            <option value="baja"        <?= ($_POST['estado'] ?? '') === 'baja'        ? 'selected' : '' ?>>Baja</option>
                        </select>
                    </div>



                </div><!-- /row -->

                <div class="mt-4 d-flex gap-2">
                    <a href="/optometria-inventario/public/index.php?view=inventario"
                       class="btn btn-outline-secondary flex-fill">Cancelar</a>
                    <button type="submit" class="btn btn-success flex-fill">
                        <i class="bi bi-floppy me-1"></i> Guardar Armazón
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>

<script src="/optometria-inventario/public/assets/js/bootstrap.bundle.min.js"></script>
<script>
// Solo JS de Bootstrap para menú hamburguesa
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