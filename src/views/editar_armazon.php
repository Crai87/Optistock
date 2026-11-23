<?php
// ============================================================
// src/views/editar_armazon.php
// Vista para editar un armazón existente.
// Se carga via index.php?view=editar_armazon&id=N
// ============================================================

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /optometria-inventario/public/index.php?view=login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /optometria-inventario/public/index.php?view=inventario&error=' . urlencode('ID inválido.'));
    exit;
}

// Cargar armazón
$stmt = $pdo->prepare('SELECT * FROM armazones WHERE id = :id');
$stmt->execute(['id' => $id]);
$arm = $stmt->fetch();

if (!$arm) {
    header('Location: /optometria-inventario/public/index.php?view=inventario&error=' . urlencode('Armazón no encontrado.'));
    exit;
}

$marcas      = $pdo->query('SELECT id, nombre FROM marcas_armazon ORDER BY nombre')->fetchAll();
$proveedores = $pdo->query('SELECT id, nombre FROM proveedores ORDER BY nombre')->fetchAll();

$error = !empty($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

// Estados posibles
$estados = ['disponible', 'en_garantia', 'dañado', 'baja', 'donado', 'inventario_muerto'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Armazón — OptiStock</title>
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
            <i class="bi bi-pencil me-2"></i>Editar Armazón
            <code class="fs-6 ms-2"><?= htmlspecialchars($arm['codigo']) ?></code>
        </h4>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="POST"
                  action="/optometria-inventario/src/views/classes/editar_armazon_guardar.php">

                <input type="hidden" name="id" value="<?= $arm['id'] ?>">

                <div class="row g-3">

                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">Código <span class="text-danger">*</span></label>
                        <input type="text" name="codigo" class="form-control" required maxlength="60"
                               value="<?= htmlspecialchars($arm['codigo']) ?>">
                    </div>

                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">Área / Medida <span class="text-danger">*</span></label>
                        <input type="text" name="area" class="form-control" required maxlength="80"
                               value="<?= htmlspecialchars($arm['area'] ?? '') ?>">
                    </div>

                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">Marca <span class="text-danger">*</span></label>
                        <select name="marca" class="form-select" required>
                            <option value="" hidden>Seleccionar…</option>
                            <?php foreach ($marcas as $m): ?>
                                <option value="<?= $m['id'] ?>"
                                    <?= $arm['marca_id'] == $m['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">Material <span class="text-danger">*</span></label>
                        <input type="text" name="material" class="form-control" required maxlength="80"
                               value="<?= htmlspecialchars($arm['material'] ?? '') ?>">
                    </div>

                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">Modelo <span class="text-danger">*</span></label>
                        <input type="text" name="modelo" class="form-control" required maxlength="80"
                               value="<?= htmlspecialchars($arm['modelo'] ?? '') ?>">
                    </div>

                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">Proveedor</label>
                        <select name="proveedor" class="form-select">
                            <option value="">— Ninguno —</option>
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?= $p['id'] ?>"
                                    <?= $arm['proveedor_id'] == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="2" maxlength="500"><?= htmlspecialchars($arm['descripcion'] ?? '') ?></textarea>
                    </div>

                    <div class="col-sm-4">
                        <label class="form-label fw-semibold">Stock actual</label>
                        <input type="number" name="stock" class="form-control"
                               value="<?= (int)$arm['cantidad_stock'] ?>" readonly
                               style="background:#f8f9fa; cursor:not-allowed;"
                               title="El stock se gestiona agregando nuevas unidades desde 'Agregar Armazón'">
                        <div class="form-text text-muted">
                            Para agregar más unidades usa
                            <a href="/optometria-inventario/public/index.php?view=agregar_armazon">Agregar Armazón</a>.
                        </div>
                    </div>

                    <div class="col-sm-4">
                        <label class="form-label fw-semibold">Costo ($)</label>
                        <input type="number" name="costo" class="form-control" min="0" step="0.01"
                               value="<?= $arm['costo'] !== null ? htmlspecialchars($arm['costo']) : '' ?>">
                    </div>

                    <div class="col-sm-4">
                        <label class="form-label fw-semibold">Estado</label>
                        <select name="estado" class="form-select">
                            <?php
                            $etiquetasEstado = [
                                'disponible'       => 'Disponible',
                                'en_garantia'      => 'En garantía',
                                'dañado'           => 'Dañado',
                                'baja'             => 'Baja',
                                'donado'           => 'Donado',
                                'inventario_muerto'=> 'Inventario muerto',
                            ];
                            foreach ($estados as $e): ?>
                                <option value="<?= $e ?>" <?= $arm['estado'] === $e ? 'selected' : '' ?>>
                                    <?= $etiquetasEstado[$e] ?? ucfirst(str_replace('_', ' ', $e)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>



                    <!-- Campos de donación: solo visibles cuando estado = donado -->
                    <div class="col-12" id="camposDonacion"
                         style="<?= ($arm['estado'] ?? '') === 'donado' ? '' : 'display:none;' ?>">
                        <hr class="my-2">
                        <p class="fw-semibold mb-2 text-purple" style="color:#6f42c1;">
                            <i class="bi bi-heart me-1"></i>Información de donación
                        </p>
                        <div class="row g-3">
                            <div class="col-sm-8">
                                <label class="form-label fw-semibold">Donado a</label>
                                <input type="text" name="donado_a" class="form-control"
                                       placeholder="Nombre de la persona o institución"
                                       maxlength="150"
                                       value="<?= htmlspecialchars($arm['donado_a'] ?? '') ?>">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label fw-semibold">Fecha de donación</label>
                                <input type="date" name="fecha_donacion" class="form-control"
                                       value="<?= htmlspecialchars($arm['fecha_donacion'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                </div>

                <div class="mt-4 d-flex gap-2">
                    <a href="/optometria-inventario/public/index.php?view=inventario"
                       class="btn btn-outline-secondary flex-fill">Cancelar</a>
                    <button type="submit" class="btn btn-success flex-fill">
                        <i class="bi bi-floppy me-1"></i> Guardar Cambios
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<script src="/optometria-inventario/public/assets/js/bootstrap.bundle.min.js"></script>
<script>
// Mostrar/ocultar campos de donación según estado seleccionado
document.querySelector('[name="estado"]').addEventListener('change', function() {
    const campos = document.getElementById('camposDonacion');
    campos.style.display = this.value === 'donado' ? '' : 'none';
});

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