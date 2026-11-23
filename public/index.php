<?php
// ============================================================
// public/index.php
// Front controller: único punto de entrada al sistema.
// Gestiona sesión, permisos y carga de vistas.
// ============================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Una sola llamada a session_start() en todo el sistema.
// logout.php ya no necesita session_start() propio.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$view = $_GET['view'] ?? 'login';

// ── Vistas públicas (no requieren sesión) ───────────────────
$vistas_publicas = ['login'];

// ── Mapa vista → permiso requerido ──────────────────────────
// PERMISOS (12 en total, misma lógica en los 3 módulos):
//
// ARMAZONES
//   'Ver armazones'              → buscar/filtrar, solo lectura
//   'Generar reporte armazones'  → botón Generar Reporte en inventario
//   'Agregar armazones'          → formulario de agregar (sin ver tabla)
//   'Editar armazones'           → buscar/filtrar + botón editar por fila
//
// EQUIPOS
//   'Ver equipos'                → buscar/filtrar, solo lectura
//   'Generar reporte equipos'    → botón Generar Reporte en equipos
//   'Agregar equipos'            → botón agregar en equipos
//   'Editar equipos'             → buscar/filtrar + botones editar/eliminar
//
// GARANTÍAS
//   'Ver garantias'              → buscar/filtrar, solo lectura
//   'Agregar garantias'          → botón Nuevo Folio
//   'Editar garantias'           → botones editar/eliminar por fila + panel lateral acciones
//
// ADMIN
//   'Gestionar usuarios'         → panel admin completo
$permisos_requeridos = [
    'dashboard'             => null,
    // Armazones
    'inventario'            => 'Ver armazones',
    'agregar_armazon'       => 'Agregar armazones',
    'editar_armazon'        => 'Editar armazones',
    'eliminar_armazon'      => 'Editar armazones',
    // Equipos
    'Inventario_materiales' => 'Ver equipos',
    // Garantías
    'garantias'             => 'Ver garantias',
    'guardar_garantia'      => 'Agregar garantias',
    'eliminar_garantia'     => 'Editar garantias',
    'estado_garantia'       => 'Editar garantias',
    // Admin — bloqueado aquí mismo para no llegar a las vistas
    // Se usa un permiso especial 'admin_only' que ningún invitado puede tener;
    // el admin (rol_id=1) omite esta verificación por el bloque $es_admin de abajo.
    'admin'           => 'admin_only',
    'guardar_usuario' => 'admin_only',
    'eliminar_usuario'=> 'admin_only',
    'toggle_usuario'  => 'admin_only',
    'catalogs'        => 'admin_only',
    'logout'                => null,
];

// ── Logout inline ────────────────────────────────────────────
if ($view === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ' . BASE_URL . '/public/index.php?view=login');
    exit;
}

// ── Vistas no reconocidas → 404 ─────────────────────────────
$vistas_permitidas = array_merge($vistas_publicas, array_keys($permisos_requeridos));
if (!in_array($view, $vistas_permitidas)) {
    http_response_code(404);
    require_once __DIR__ . '/../src/views/errores/404.php';
    exit;
}

// ── Vistas públicas: servir sin más ─────────────────────────
if (in_array($view, $vistas_publicas)) {
    require_once __DIR__ . '/../src/views/' . $view . '.php';
    exit;
}

// ── A partir de aquí: requiere sesión activa ─────────────────
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/public/index.php?view=login');
    exit;
}

// ── Admin (rol_id = 1): acceso total ─────────────────────────
$es_admin = isset($_SESSION['rol']) && (int)$_SESSION['rol'] === 1;

// ── Verificar permiso requerido para la vista ────────────────
$permiso_necesario = $permisos_requeridos[$view] ?? null;

if (!$es_admin && $permiso_necesario !== null) {
    $permisos_usuario = $_SESSION['permisos'] ?? [];
    if (!in_array($permiso_necesario, $permisos_usuario)) {
        http_response_code(403);
        require_once __DIR__ . '/../src/views/errores/403.php';
        exit;
    }
}

// ── Servir la vista ──────────────────────────────────────────
require_once __DIR__ . '/../src/views/' . $view . '.php';