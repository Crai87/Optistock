<?php
// ============================================================
// src/views/admin.php
//
// CAMBIOS respecto a la versión anterior:
//   - Botón "Eliminar usuario" en cada fila (llama a eliminar_usuario.php)
//   - Solo el JS mínimo de Bootstrap + lógica inline de admin
//     (sin fetch de negocio externo, excepto el toggle y el nuevo eliminar)
// ============================================================

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /optometria-inventario/public/index.php?view=login');
    exit;
}
// Panel de administración: exclusivo para rol admin (rol_id = 1)
if ((int)($_SESSION['rol'] ?? 0) !== 1) {
    http_response_code(403);
    require_once __DIR__ . '/errores/403.php';
    exit;
}

require_once __DIR__ . '/../../config/database.php';

include __DIR__ . '/../../src/views/save.php';

$lastBackupDate = new DateTime($lastBackup);
$today = new DateTime();

$diff = $today->diff($lastBackupDate);
$text = "";
if ($diff->days == 1) {
    $text = "Hace 1 día";
} elseif ($diff->days == 0) {
    $text = "Hoy";
} else {
    $text = "Hace " . $diff->days . " días";
}

$stmtPermisos = $pdo->query('SELECT id, descripcion FROM permisos ORDER BY id');
$permisosDisponibles = $stmtPermisos->fetchAll();

$stmtUsuarios = $pdo->query("
    SELECT u.id, u.nombre, u.apellido_paterno, u.apellido_materno,
           u.username, u.activo, r.nombre AS rol, r.id AS rol_id
    FROM usuarios u
    JOIN roles r ON r.id = u.rol_id
    ORDER BY u.created_at DESC
");
$usuarios = $stmtUsuarios->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de Usuarios — OptiStock</title>
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
            <li><a href="/optometria-inventario/public/index.php?view=admin" class="active">Usuarios</a></li>
            <li><a href="/optometria-inventario/public/index.php?view=logout">Salir</a></li>
        </ul>
    </nav>
</header>

<div class="container py-5">

    <div class="page-title-row">
        <div>
            <h2 class="page-title"><i class="bi bi-person-circle me-2"></i>Gestión de Usuarios</h2>
            <p class="page-subtitle">Crea, activa/desactiva y elimina usuarios del sistema</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <?php if (isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
            <div id="alert-respaldo" 
                style="position: fixed; top: 70px; left: 50%; transform: translateX(-50%); z-index: 9999; min-width: 420px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center;"
                class="alert alert-success alert-dismissible fade show py-2 px-3" 
                role="alert">
                <i class="bi bi-check-circle-fill me-1"></i> ¡Respaldo realizado exitosamente!
                <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert" style="position: relative; top: auto; right: auto;"></button>
            </div>
            <?php endif; ?>            
            <span class="badge bg-secondary small">Ultimo guardado: <?php echo($text) ?></span>
            <a href="/optometria-inventario/src/views/autosave.php?return=1" class="btn btn-outline-danger btn-sm"><i class="bi bi-floppy"></i> Respaldos</a>
            <a href="/optometria-inventario/public/index.php?view=catalogs"
               class="btn btn-outline-success btn-sm">
                <i class="bi bi-tags"></i> Proveedores y Marcas
            </a>
            <a href="/optometria-inventario/public/generar_reporte_proveedores.php"
               target="_blank" class="btn btn-outline-secondary btn-sm" title="Reporte de proveedores">
                <i class="bi bi-file-earmark-bar-graph"></i> Reporte Proveedores
            </a>
        </div>
    </div>

    <div class="row g-4">

        <!-- FORMULARIO NUEVO USUARIO -->
        <div class="col-lg-5">
            <div class="form-card">
                <div class="form-card-header"><h5>Nuevo Usuario</h5></div>
                <div class="form-card-body">

                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="req">*</span></label>
                        <input type="text" id="inputNombre" class="form-control custom-input"
                               oninput="soloLetras(this, 'errNombre')" maxlength="30">
                        <small class="text-danger d-none" id="errNombre">Solo letras y espacios (3–30 caracteres)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Apellido Paterno <span class="req">*</span></label>
                        <input type="text" id="inputApaterno" class="form-control custom-input"
                               oninput="soloLetras(this, 'errApaterno')" maxlength="30">
                        <small class="text-danger d-none" id="errApaterno">Solo letras y espacios (3–30 caracteres)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Apellido Materno</label>
                        <input type="text" id="inputAmaterno" class="form-control custom-input"
                               oninput="soloLetras(this, 'errAmaterno')" maxlength="30">
                        <small class="text-danger d-none" id="errAmaterno">Solo letras y espacios (opcional, 3–30 si se llena)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Usuario (alias) <span class="req">*</span></label>
                        <input type="text" id="inputAlias" class="form-control custom-input"
                               oninput="validarAlias(this)" maxlength="20">
                        <small class="text-danger d-none" id="errAlias">Solo letras, números y guion bajo (3–20 caracteres, sin espacios)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña <span class="req">*</span></label>
                        <div class="input-pass">
                            <input type="password" class="form-control custom-input" id="passInput">
                            <button type="button" class="toggle-pass" onclick="togglePass()">
                                <i class="bi bi-eye" id="iconoOjo"></i>
                            </button>
                        </div>
                        <small class="text-muted">Mínimo 8 caracteres, máximo 16</small>
                        <small class="text-danger d-none" id="errPass">La contraseña debe tener entre 8 y 16 caracteres, al menos una letra y un número</small>
                    </div>

                    <hr class="divider">
                    <p class="section-label">Permisos de acceso</p>

                    <div class="permisos-grid" id="permisosGrid">
                        <?php
                        $grupos = [
                            'Inventario Armazones' => [
                                'Ver armazones',             // Buscar y filtrar — sin editar ni agregar
                                'Generar reporte armazones', // Botón generar reporte PDF en armazones
                                'Agregar armazones',         // Solo puede agregar armazones nuevos
                                'Editar armazones',          // Buscar, filtrar y editar cada armazón
                            ],
                            'Equipos e Inmobiliario' => [
                                'Ver equipos',               // Buscar y filtrar — sin editar ni agregar
                                'Generar reporte equipos',   // Botón generar reporte PDF en equipos
                                'Agregar equipos',           // Solo puede agregar equipos/inmobiliario
                                'Editar equipos',            // Buscar, filtrar y editar cada equipo
                            ],
                            'Garantías' => [
                                'Ver garantias',             // Buscar y filtrar garantías — sin editar
                                'Agregar garantias',         // Crear nuevos folios de garantía
                                'Editar garantias',          // Editar, eliminar y cambiar estado de folios
                            ],
                            // 'Gestionar usuarios' eliminado — el panel admin
                            // solo es accesible por el rol admin, nunca por invitados.
                        ];
                        $permisosIdx = array_column($permisosDisponibles, 'id', 'descripcion');

                        foreach ($grupos as $modulo => $lista): ?>
                            <div class="permiso-grupo">
                                <p class="permiso-modulo"><?= htmlspecialchars($modulo) ?></p>
                                <?php foreach ($lista as $desc):
                                    $pid = $permisosIdx[$desc] ?? null;
                                    if ($pid === null) continue;
                                ?>
                                <label class="permiso-check">
                                    <input type="checkbox" class="perm-cb"
                                           value="<?= $pid ?>"
                                           data-desc="<?= htmlspecialchars($desc) ?>">
                                    <?= htmlspecialchars($desc) ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="advertenciaPermisos" class="alert alert-warning py-2 mt-3 d-none">
                        No seleccionaste ningún permiso. El usuario no podrá ver ninguna sección.
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-outline-secondary flex-fill" onclick="limpiarFormulario()">Limpiar</button>
                        <button class="btn btn-guardar flex-fill" id="btnGuardar" onclick="guardarUsuario()">Guardar Usuario</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLA DE USUARIOS -->
        <div class="col-lg-7">
            <div class="form-card">
                <div class="form-card-header">
                    <h5>Usuarios Registrados</h5>
                    <span class="badge-count" id="userCount"><?= count($usuarios) ?></span>
                </div>
                <div class="form-card-body p-0">
                    <div class="search-bar-wrap">
                        <input type="text" class="form-control custom-input" id="searchInput"
                               placeholder="🔍  Buscar por nombre o alias…" oninput="filtrarUsuarios()">
                    </div>
                    <div class="table-responsive">
                        <table class="table user-table mb-0">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Alias</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="userTableBody">
                                <?php foreach ($usuarios as $u):
                                    $nombre_completo = trim($u['nombre'] . ' ' . $u['apellido_paterno'] . ' ' . $u['apellido_materno']);
                                    $iniciales = mb_strtoupper(mb_substr($u['nombre'] ?? '', 0, 1) . mb_substr($u['apellido_paterno'] ?? '', 0, 1));
                                    $colores = [
                                        ['bg'=>'#c8e6c9','fg'=>'#2e7d32'],
                                        ['bg'=>'#bbdefb','fg'=>'#1565c0'],
                                        ['bg'=>'#ffe0b2','fg'=>'#e65100'],
                                        ['bg'=>'#f3e5f5','fg'=>'#6a1b9a'],
                                        ['bg'=>'#fce4ec','fg'=>'#c62828'],
                                    ];
                                    $c = $colores[$u['id'] % count($colores)];
                                    $esAdmin = (int)$u['rol_id'] === 1;
                                ?>
                                <tr data-id="<?= $u['id'] ?>">
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar" style="background:<?= $c['bg'] ?>;color:<?= $c['fg'] ?>">
                                                <?= htmlspecialchars($iniciales) ?>
                                            </div>
                                            <p class="user-name"><?= htmlspecialchars($nombre_completo) ?></p>
                                        </div>
                                    </td>
                                    <td><span class="user-alias"><?= htmlspecialchars($u['username']) ?></span></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($u['rol']) ?></span></td>
                                    <td>
                                        <span class="estado-badge <?= $u['activo'] ? 'activo' : 'inactivo' ?>">
                                            <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns d-flex gap-1">
                                            <?php if ($esAdmin): ?>
                                                <span class="badge bg-success px-2 py-1" title="El administrador no puede modificarse desde aquí"
                                                      style="font-size:.75rem;">Protegido</span>
                                            <?php else: ?>
                                            <button class="btn-accion" title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>"
                                                onclick="toggleUsuario(this, <?= $u['id'] ?>, <?= $u['activo'] ?>)">
                                                <?= $u['activo'] ? '🚫' : '✅' ?>
                                            </button>
                                            <button class="btn-accion eliminar" title="Eliminar usuario"
                                                onclick="eliminarUsuario(this, <?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($nombre_completo)) ?>')">
                                                🗑️
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="toast-notif" id="toast"></div>

<script src="/optometria-inventario/public/assets/js/bootstrap.bundle.min.js"></script>
<script>
// Menú hamburguesa
const toggle = document.getElementById('menuToggle');
const menu   = document.getElementById('mobileMenu');
toggle.addEventListener('click', e => { e.stopPropagation(); menu.classList.toggle('show'); toggle.classList.toggle('open'); });
document.addEventListener('click', e => {
    if (!toggle.contains(e.target) && !menu.contains(e.target)) { menu.classList.remove('show'); toggle.classList.remove('open'); }
});

function togglePass() {
    const input = document.getElementById('passInput');
    const icono = document.getElementById('iconoOjo');
    if (input.type === 'password') {
        input.type = 'text';
        icono.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icono.className = 'bi bi-eye';
    }
}

// ── Validaciones de formulario ──────────────────────────────

// Permite solo letras (incluyendo tildes y ñ) y espacios.
// Bloquea la escritura de números y caracteres inválidos en tiempo real.
function soloLetras(input, errId) {
    // Eliminar cualquier caracter que no sea letra o espacio mientras escribe
    input.value = input.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]/g, '');
    const val = input.value.trim();
    const err = document.getElementById(errId);
    if (val.length > 0 && (val.length < 3 || val.length > 30)) {
        input.classList.add('is-invalid');
        err.classList.remove('d-none');
    } else {
        input.classList.remove('is-invalid');
        err.classList.add('d-none');
    }
}

function validarAlias(input) {
    // Eliminar caracteres no permitidos en tiempo real
    input.value = input.value.replace(/[^a-zA-Z0-9_]/g, '');
    const err = document.getElementById('errAlias');
    if (input.value.length > 0 && (input.value.length < 3 || input.value.length > 20)) {
        input.classList.add('is-invalid');
        err.classList.remove('d-none');
    } else {
        input.classList.remove('is-invalid');
        err.classList.add('d-none');
    }
}

function validarCampoLetras(val, min, max) {
    return /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/.test(val) && val.length >= min && val.length <= max;
}

function marcarError(inputId, errId) {
    document.getElementById(inputId).classList.add('is-invalid');
    document.getElementById(errId).classList.remove('d-none');
}

async function guardarUsuario() {
    const nombre   = document.getElementById('inputNombre').value.trim();
    const apaterno = document.getElementById('inputApaterno').value.trim();
    const amaterno = document.getElementById('inputAmaterno').value.trim();
    const alias    = document.getElementById('inputAlias').value.trim();
    const password = document.getElementById('passInput').value;
    const adv      = document.getElementById('advertenciaPermisos');

    // Validar nombre
    if (!validarCampoLetras(nombre, 3, 30)) {
        marcarError('inputNombre', 'errNombre'); showToast('❌ El nombre solo acepta letras (3–30 caracteres)'); return;
    }
    // Validar apellido paterno
    if (!validarCampoLetras(apaterno, 3, 30)) {
        marcarError('inputApaterno', 'errApaterno'); showToast('❌ El apellido paterno solo acepta letras (3–30 caracteres)'); return;
    }
    // Apellido materno es opcional pero si se llena debe ser válido
    if (amaterno && !validarCampoLetras(amaterno, 3, 30)) {
        marcarError('inputAmaterno', 'errAmaterno'); showToast('❌ El apellido materno solo acepta letras (3–30 si se llena)'); return;
    }
    // Validar alias
    if (!/^[a-zA-Z0-9_]{3,20}$/.test(alias)) {
        marcarError('inputAlias', 'errAlias'); showToast('❌ El usuario solo acepta letras, números y _ (3–20 caracteres)'); return;
    }
    // Validar contraseña
    if (!/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,16}$/.test(password)) {
        document.getElementById('passInput').classList.add('is-invalid');
        document.getElementById('errPass').classList.remove('d-none');
        showToast('❌ La contraseña debe tener 8–16 caracteres, al menos una letra y un número'); return;
    }
    document.getElementById('passInput').classList.remove('is-invalid');
    document.getElementById('errPass').classList.add('d-none');
    if (!nombre || !apaterno || !alias || !password) { showToast('Completa todos los campos obligatorios'); return; }

    const checks = document.querySelectorAll('.perm-cb:checked');
    if (checks.length === 0) { adv.classList.remove('d-none'); return; }
    adv.classList.add('d-none');

    const fd = new FormData();
    fd.append('nombre',           nombre);
    fd.append('apellido_paterno', apaterno);
    fd.append('apellido_materno', amaterno);
    fd.append('username',         alias);
    fd.append('password',         password);
    checks.forEach(cb => fd.append('permisos[]', cb.value));

    const btn = document.getElementById('btnGuardar');
    btn.disabled = true; btn.textContent = 'Guardando…';

    try {
        const res  = await fetch('/optometria-inventario/public/index.php?view=guardar_usuario', { method:'POST', body:fd });
        const data = await res.json();
        if (data.ok) { showToast('✅ ' + data.msg); agregarFilaTabla(data.usuario); limpiarFormulario(); }
        else showToast('❌ ' + data.msg);
    } catch { showToast('❌ Error de conexión'); }
    finally { btn.disabled = false; btn.textContent = 'Guardar Usuario'; }
}

function agregarFilaTabla(u) {
    const colores = [{bg:'#c8e6c9',fg:'#2e7d32'},{bg:'#bbdefb',fg:'#1565c0'},{bg:'#ffe0b2',fg:'#e65100'},{bg:'#f3e5f5',fg:'#6a1b9a'},{bg:'#fce4ec',fg:'#c62828'}];
    const c = colores[u.id % colores.length];
    const iniciales = u.nombre.split(' ').map(p => p[0]).slice(0,2).join('').toUpperCase();
    const tbody = document.getElementById('userTableBody');
    const tr = document.createElement('tr');
    tr.dataset.id = u.id;
    tr.innerHTML = `
        <td><div class="user-cell">
            <div class="user-avatar" style="background:${c.bg};color:${c.fg}">${iniciales}</div>
            <p class="user-name">${u.nombre}</p>
        </div></td>
        <td><span class="user-alias">${u.alias}</span></td>
        <td><span class="badge bg-secondary">invitado</span></td>
        <td><span class="estado-badge activo">Activo</span></td>
        <td><div class="action-btns d-flex gap-1">
            <button class="btn-accion" onclick="toggleUsuario(this,${u.id},1)">🚫</button>
            <button class="btn-accion eliminar" onclick="eliminarUsuario(this,${u.id},'${u.nombre}')">🗑️</button>
        </div></td>`;
    tbody.insertBefore(tr, tbody.firstChild);
    actualizarConteo();
}

async function toggleUsuario(btn, id, estadoActual) {
    const nuevoEstado = estadoActual ? 0 : 1;
    if (!confirm(`¿${nuevoEstado ? 'Activar' : 'Desactivar'} este usuario?`)) return;
    const fd = new FormData();
    fd.append('id', id); fd.append('activo', nuevoEstado);
    try {
        const res  = await fetch('/optometria-inventario/public/index.php?view=toggle_usuario', { method:'POST', body:fd });
        const data = await res.json();
        if (data.ok) {
            const tr    = btn.closest('tr');
            const badge = tr.querySelector('.estado-badge');
            badge.className  = 'estado-badge ' + (nuevoEstado ? 'activo' : 'inactivo');
            badge.textContent = nuevoEstado ? 'Activo' : 'Inactivo';
            btn.textContent   = nuevoEstado ? '🚫' : '✅';
            btn.setAttribute('onclick', `toggleUsuario(this,${id},${nuevoEstado})`);
            showToast(' Usuario ' + (nuevoEstado ? 'activado' : 'desactivado'));
        } else showToast('❌ ' + data.msg);
    } catch { showToast('❌ Error de conexión'); }
}

async function eliminarUsuario(btn, id, nombre) {
    if (!confirm(`¿Eliminar permanentemente al usuario "${nombre}"? Esta acción no se puede deshacer.`)) return;
    const fd = new FormData();
    fd.append('id', id);
    try {
        const res  = await fetch('/optometria-inventario/public/index.php?view=eliminar_usuario', { method:'POST', body:fd });
        const data = await res.json();
        if (data.ok) {
            btn.closest('tr').remove();
            actualizarConteo();
            showToast(' Usuario eliminado.');
        } else showToast(' ' + data.msg);
    } catch { showToast(' Error de conexión'); }
}

function actualizarConteo() {
    document.getElementById('userCount').textContent = document.querySelectorAll('#userTableBody tr').length;
}
function limpiarFormulario() {
    ['inputNombre','inputApaterno','inputAmaterno','inputAlias','passInput'].forEach(id => {
        const el = document.getElementById(id);
        el.value = '';
        el.classList.remove('is-invalid');
    });
    ['errNombre','errApaterno','errAmaterno','errAlias','errPass'].forEach(id => {
        document.getElementById(id).classList.add('d-none');
    });
    document.querySelectorAll('.perm-cb').forEach(cb => { cb.checked = false; });
    document.getElementById('advertenciaPermisos').classList.add('d-none');
}
function filtrarUsuarios() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#userTableBody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg; t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3500);
}
</script>
<script>
  setTimeout(() => {
    const a = document.getElementById('alert-respaldo');
    if (a) bootstrap.Alert.getOrCreateInstance(a).close();
  }, 4000);
</script>
</body>
</html>