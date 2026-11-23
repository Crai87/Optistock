<?php
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /optometria-inventario/public/index.php?view=login');
    exit;
}
if (isset($_SESSION['rol']) && (int)$_SESSION['rol'] === 1) {
    header('Location: /optometria-inventario/public/index.php?view=inventario');
    exit;
}

include __DIR__ . '/../../src/views/save.php';

$lastBackupDate = new DateTime($lastBackup);
$today = new DateTime();

$diff = $today->diff($lastBackupDate);

if ($diff->days >= 7) {
    header('Location: /optometria-inventario/src/views/autosave.php');
    exit;
}


$p    = $_SESSION['permisos'] ?? [];
$nom  = $_SESSION['nombre_completo'] ?? 'Usuario';
// Herencia
if ((in_array('Agregar armazones',$p)||in_array('Editar armazones',$p)||in_array('Generar reporte armazones',$p))&&!in_array('Ver armazones',$p)) $p[]='Ver armazones';
if ((in_array('Agregar equipos',$p)||in_array('Editar equipos',$p)||in_array('Generar reporte equipos',$p))&&!in_array('Ver equipos',$p)) $p[]='Ver equipos';
if ((in_array('Agregar garantias',$p)||in_array('Editar garantias',$p))&&!in_array('Ver garantias',$p)) $p[]='Ver garantias';

// Cada módulo del dashboard con su permiso de acceso
$mods = [
    ['req'=>'Ver armazones',           'titulo'=>'Ver Armazones',         'sub'=>'Buscar y filtrar'.( in_array('Editar armazones',$p)?' · Editar':( in_array('Agregar armazones',$p)?' · Agregar':' · Solo lectura')), 'ico'=>'bi-box-seam',              'col'=>'#198754','url'=>'index.php?view=inventario'],
    ['req'=>'Agregar armazones',        'titulo'=>'Agregar Armazón',       'sub'=>'Registrar un nuevo armazón',                          'ico'=>'bi-plus-circle',          'col'=>'#0d6efd','url'=>'index.php?view=agregar_armazon'],
    ['req'=>'Generar reporte armazones','titulo'=>'Reporte Armazones',     'sub'=>'Exportar inventario de armazones a PDF',               'ico'=>'bi-file-earmark-pdf',      'col'=>'#dc3545','url'=>'index.php?view=inventario&ver_todos=1'],
    ['req'=>'Ver equipos',             'titulo'=>'Ver Equipos',            'sub'=>'Buscar y filtrar'.( in_array('Editar equipos',$p)?' · Editar':( in_array('Agregar equipos',$p)?' · Agregar':' · Solo lectura')), 'ico'=>'bi-tools',                 'col'=>'#6f42c1','url'=>'index.php?view=Inventario_materiales'],
    ['req'=>'Agregar equipos',          'titulo'=>'Agregar Equipo',        'sub'=>'Registrar equipo o inmobiliario',                     'ico'=>'bi-plus-square',          'col'=>'#0d6efd','url'=>'index.php?view=Inventario_materiales'],
    ['req'=>'Generar reporte equipos',  'titulo'=>'Reporte Equipos',       'sub'=>'Exportar inventario de equipos a PDF',                 'ico'=>'bi-file-earmark-bar-graph','col'=>'#0dcaf0','url'=>'index.php?view=Inventario_materiales&ver_todos=1'],
    ['req'=>'Ver garantias',           'titulo'=>'Ver Garantías',          'sub'=>'Buscar y filtrar'.( in_array('Editar garantias',$p)?' · Editar':( in_array('Agregar garantias',$p)?' · Agregar':' · Solo lectura')), 'ico'=>'bi-shield-check',          'col'=>'#fd7e14','url'=>'index.php?view=garantias'],
    ['req'=>'Agregar garantias',        'titulo'=>'Nuevo Folio Garantía',  'sub'=>'Crear un nuevo folio de garantía',                    'ico'=>'bi-shield-plus',          'col'=>'#fd7e14','url'=>'index.php?view=garantias'],
];

// Deduplicar por URL — si un permiso "mayor" ya puso esa URL, no repetir
$vistas = [];
$disp = array_filter($mods, function($m) use ($p, &$vistas) {
    if (!in_array($m['req'], $p)) return false;
    if (isset($vistas[$m['url']])) return false;
    $vistas[$m['url']] = true;
    return true;
});
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio — OptiStock</title>
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/bootstrap-icons.css">
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/materiales.css">
    <style>
        /* Tarjetas de módulo */
        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 1.1rem;
            margin-top: 1rem;
        }
        .module-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
            padding: 1.6rem 1.2rem;
            text-align: center;
            text-decoration: none;
            color: inherit;
            border-top: 4px solid var(--card-color);
            transition: transform .15s, box-shadow .15s;
            display: block;
        }
        .module-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,.12);
            color: inherit;
            text-decoration: none;
        }
        .module-icon {
            width: 54px; height: 54px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto .9rem;
            font-size: 1.5rem;
        }
        .module-card h6 { font-weight: 700; margin-bottom: .3rem; font-size: .95rem; }
        .module-card small { color: #6c757d; font-size: .78rem; display: block; line-height: 1.4; }
        .no-permisos { text-align: center; padding: 4rem 1rem; color: #6c757d; }
    </style>
</head>
<body class="bg-light">

<!-- HEADER — idéntico al de inventario.php -->
<header class="site-header">
    <div class="header-logo">
        <a href="/optometria-inventario/public/index.php?view=dashboard">
            <img src="/optometria-inventario/public/assets/img/Logo.png" alt="Logo" class="logo-img">
            <span class="logo-text">Clínica Optometría</span>
        </a>
    </div>
    <button class="hamburger" id="menuToggle" aria-label="Abrir menú">
        <span></span><span></span><span></span>
    </button>
    <nav class="mobile-menu" id="mobileMenu">
        <ul>
            <?php if(in_array('Ver armazones',$p)||in_array('Agregar armazones',$p)||in_array('Editar armazones',$p)): ?>
            <li><a href="/optometria-inventario/public/index.php?view=inventario">Inventario de Armazones</a></li>
            <?php endif; ?>
            <?php if(in_array('Ver equipos',$p)||in_array('Agregar equipos',$p)||in_array('Editar equipos',$p)): ?>
            <li><a href="/optometria-inventario/public/index.php?view=Inventario_materiales">Inventario de Equipos</a></li>
            <?php endif; ?>
            <?php if(in_array('Ver garantias',$p)||in_array('Agregar garantias',$p)||in_array('Editar garantias',$p)): ?>
            <li><a href="/optometria-inventario/public/index.php?view=garantias">Garantías</a></li>
            <?php endif; ?>
            <li><a href="/optometria-inventario/public/index.php?view=logout">Salir</a></li>
        </ul>
    </nav>
</header>

<div class="container mt-4">

    <!-- Saludo + info -->
    <div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4 class="fw-semibold mb-0">
                <i class="bi bi-grid-3x3-gap-fill me-2 text-success"></i>
                Bienvenido, <?= htmlspecialchars(explode(' ', $nom)[0]) ?>
            </h4>
            <small class="text-muted">Selecciona una sección para comenzar</small>
        </div>
    </div>

    <!-- Grid de módulos -->
    <?php if (empty($disp)): ?>
        <div class="card shadow-sm border-0">
            <div class="card-body no-permisos">
                <i class="bi bi-lock fs-1 d-block mb-3 text-muted"></i>
                <h5 class="fw-semibold">Sin permisos asignados</h5>
                <p class="text-muted">Tu cuenta aún no tiene módulos habilitados.<br>Contacta al administrador del sistema.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="module-grid">
            <?php foreach ($disp as $m): ?>
                <a href="/optometria-inventario/public/<?= htmlspecialchars($m['url']) ?>"
                   class="module-card"
                   style="--card-color: <?= $m['col'] ?>">
                    <div class="module-icon"
                         style="background:<?= $m['col'] ?>20; color:<?= $m['col'] ?>">
                        <i class="bi <?= $m['ico'] ?>"></i>
                    </div>
                    <h6><?= htmlspecialchars($m['titulo']) ?></h6>
                    <small><?= htmlspecialchars($m['sub']) ?></small>
                </a>
            <?php endforeach; ?>
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