<?php
// ============================================================
// src/views/api/api_materiales.php
//
// CORRECCIONES:
//   - Usa config/database.php en lugar de definir credenciales
//     propias (antes si cambiabas database.php aquí seguían las viejas)
//   - Validación real de imágenes con getimagesizefromstring()
//     para detectar archivos falsos que solo cambian el MIME del header
//   - Ruta corregida para llegar a config/ desde src/views/api/
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_start();
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => "PHP Error [$errno]: $errstr en línea $errline"]);
    exit;
});
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Error fatal PHP: ' . $err['message'] . ' (línea ' . $err['line'] . ')']);
    }
});

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Usa database.php centralizado (3 niveles arriba: api/ → views/ → src/ → raíz)
require_once __DIR__ . '/../../../config/database.php';

// ── Helper: guardar foto con validación real ──────────────────
function guardarFoto($base64, $prefijo) {
    if (!$base64 || strpos($base64, ',') === false) return null;

    list($meta, $data) = explode(',', $base64, 2);
    $bytes = base64_decode($data);
    if (!$bytes) return null;

    // Limitar tamaño a 5 MB
    if (strlen($bytes) > 5 * 1024 * 1024)
        throw new Exception('La imagen no debe superar 5 MB.');

    // Validar que sea una imagen real (no solo el MIME del data URI)
    $info = @getimagesizefromstring($bytes);
    if ($info === false)
        throw new Exception('El archivo no es una imagen válida.');

    $tiposPermitidos = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF, IMAGETYPE_BMP];
    if (!in_array($info[2], $tiposPermitidos))
        throw new Exception('Tipo de imagen no permitido. Usa JPG, PNG, WEBP o GIF.');

    $extensiones = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif', IMAGETYPE_BMP => 'bmp'];
    $ext = $extensiones[$info[2]];

    $dir = __DIR__ . '/../../../public/assets/img/inventario/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $nombre   = $prefijo . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    $ruta_abs = $dir . $nombre;

    if (file_put_contents($ruta_abs, $bytes) === false)
        throw new Exception('No se pudo guardar la imagen. Verifica permisos de la carpeta.');

    return '/optometria-inventario/public/assets/img/inventario/' . $nombre;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$body = [];
if ($method === 'POST') {
    $raw  = file_get_contents('php://input');
    $body = $raw ? (json_decode($raw, true) ?? []) : [];
    $body = array_merge($_POST, $body);
    $action = $body['action'] ?? $action;
}

$usuario_id = (int)$_SESSION['usuario_id'];

try {
    switch ($action) {

        case 'listar_equipos':
            $sql = "
                SELECT ec.id, ec.codigo, ec.nombre, ec.descripcion, ec.modelo,
                       ec.cantidad_total, ec.cantidad_servicio, ec.cantidad_mantenimiento,
                       ec.estado, ec.imagen_url, ec.created_at, ec.marca_id,
                       COALESCE(me.nombre, 'S/M') AS marca
                FROM equipos_clinicos ec
                LEFT JOIN marcas_equipos me ON me.id = ec.marca_id
                ORDER BY ec.nombre ASC
            ";
            echo json_encode($pdo->query($sql)->fetchAll());
            break;

        case 'listar_marcas':
            echo json_encode($pdo->query('SELECT id, nombre FROM marcas_equipos ORDER BY nombre ASC')->fetchAll());
            break;

        case 'listar_inmobiliario':
            $sql = "
                SELECT i.id, i.codigo, i.nombre, i.descripcion,
                       i.cantidad, i.estado, i.ubicacion,
                       i.imagen_url, i.created_at, i.categoria_id,
                       COALESCE(c.nombre, 'Sin categoría') AS categoria
                FROM inmobiliario i
                LEFT JOIN categorias_inmobiliario c ON c.id = i.categoria_id
                ORDER BY c.nombre ASC, i.nombre ASC
            ";
            echo json_encode($pdo->query($sql)->fetchAll());
            break;

        case 'listar_categorias':
            echo json_encode($pdo->query('SELECT id, nombre FROM categorias_inmobiliario ORDER BY nombre ASC')->fetchAll());
            break;

        case 'agregar_equipo':
            $nombre   = trim($body['nombre']      ?? '');
            $desc     = trim($body['descripcion'] ?? '');
            $marca_id = ($body['marca_id'] !== '' && $body['marca_id'] !== null) ? (int)$body['marca_id'] : null;
            $modelo   = trim($body['modelo']      ?? '');
            $cant_tot = max(1, (int)($body['cantidad_total']         ?? 1));
            $cant_srv = max(0, (int)($body['cantidad_servicio']      ?? $cant_tot));
            $cant_mnt = max(0, (int)($body['cantidad_mantenimiento'] ?? 0));
            $estado   = $body['estado'] ?? 'activo';

            if (!$nombre) throw new Exception('El nombre del equipo es obligatorio.');
            if ($cant_srv + $cant_mnt > $cant_tot)
                throw new Exception('Servicio + mantenimiento no puede superar el total.');

            $prefijo = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $nombre), 0, 3));
            if (strlen($prefijo) < 2) $prefijo = str_pad($prefijo, 3, 'X');

            $st = $pdo->prepare('SELECT codigo FROM equipos_clinicos WHERE codigo LIKE ? ORDER BY codigo DESC LIMIT 1');
            $st->execute(["EC-{$prefijo}-%"]);
            $ultimo    = $st->fetchColumn();
            $siguiente = 1;
            if ($ultimo) { preg_match('/-(\\d+)$/', $ultimo, $m); $siguiente = ((int)($m[1] ?? 0)) + 1; }
            $codigo = 'EC-' . $prefijo . '-' . str_pad($siguiente, 3, '0', STR_PAD_LEFT);

            $imagen_url = guardarFoto($body['foto_base64'] ?? null, 'eq_' . $prefijo);

            $st = $pdo->prepare('
                INSERT INTO equipos_clinicos
                    (codigo, nombre, descripcion, marca_id, modelo,
                     cantidad_total, cantidad_servicio, cantidad_mantenimiento,
                     estado, imagen_url, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ');
            $st->execute([$codigo, $nombre, $desc, $marca_id, $modelo,
                          $cant_tot, $cant_srv, $cant_mnt, $estado, $imagen_url, $usuario_id]);

            $equipo_id = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO movimientos_inventario (tipo_item,item_id,tipo_movimiento,cantidad,motivo,usuario_id) VALUES ("equipo_clinico",?,"entrada",?,"Alta de equipo clínico",?)')
                ->execute([$equipo_id, $cant_tot, $usuario_id]);

            echo json_encode(['id' => $equipo_id, 'codigo' => $codigo]);
            break;

        case 'editar_equipo':
            $id       = (int)($body['id']    ?? 0);
            $nombre   = trim($body['nombre'] ?? '');
            $desc     = trim($body['descripcion'] ?? '');
            $marca_id = ($body['marca_id'] !== '' && $body['marca_id'] !== null) ? (int)$body['marca_id'] : null;
            $modelo   = trim($body['modelo'] ?? '');
            $cant_tot = max(1, (int)($body['cantidad_total']         ?? 1));
            $cant_srv = max(0, (int)($body['cantidad_servicio']      ?? 0));
            $cant_mnt = max(0, (int)($body['cantidad_mantenimiento'] ?? 0));
            $estado   = $body['estado'] ?? 'activo';

            if (!$id)     throw new Exception('ID de equipo requerido.');
            if (!$nombre) throw new Exception('El nombre del equipo es obligatorio.');
            if ($cant_srv + $cant_mnt > $cant_tot)
                throw new Exception('Servicio + mantenimiento no puede superar el total.');

            $nueva_img = guardarFoto($body['foto_base64'] ?? null, 'eq');
            $img_sql   = $nueva_img ? ', imagen_url=?' : '';
            $params    = [$nombre, $desc, $marca_id, $modelo, $cant_tot, $cant_srv, $cant_mnt, $estado];
            if ($nueva_img) $params[] = $nueva_img;
            $params[] = $id;

            $pdo->prepare("UPDATE equipos_clinicos SET nombre=?,descripcion=?,marca_id=?,modelo=?,cantidad_total=?,cantidad_servicio=?,cantidad_mantenimiento=?,estado=?{$img_sql} WHERE id=?")
                ->execute($params);

            echo json_encode(['ok' => true]);
            break;

        case 'eliminar_equipo':
            $id = (int)($body['id'] ?? 0);
            if (!$id) throw new Exception('ID de equipo requerido.');
            $pdo->prepare('DELETE FROM movimientos_inventario WHERE tipo_item="equipo_clinico" AND item_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM equipos_clinicos WHERE id=?')->execute([$id]);
            echo json_encode(['ok' => true]);
            break;

        case 'agregar_categoria':
            $nombre = trim($body['nombre'] ?? '');
            if (!$nombre) throw new Exception('El nombre de la categoría es obligatorio.');
            $st = $pdo->prepare('INSERT INTO categorias_inmobiliario (nombre) VALUES (?)');
            $st->execute([$nombre]);
            echo json_encode(['id' => (int)$pdo->lastInsertId(), 'nombre' => $nombre]);
            break;

        case 'agregar_inmobiliario':
            $nombre   = trim($body['nombre']      ?? '');
            $desc     = trim($body['descripcion'] ?? '');
            $cat_id   = ($body['categoria_id'] !== '' && $body['categoria_id'] !== null) ? (int)$body['categoria_id'] : null;
            $ubicacion = trim($body['ubicacion']  ?? '');
            $cantidad = max(1, (int)($body['cantidad'] ?? 1));
            $estado   = $body['estado'] ?? 'activo';

            if (!$nombre) throw new Exception('El nombre es obligatorio.');
            if (!$cat_id) throw new Exception('La categoría es obligatoria.');

            $prefijo = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $nombre), 0, 3));
            if (strlen($prefijo) < 2) $prefijo = str_pad($prefijo, 3, 'X');

            $st = $pdo->prepare('SELECT codigo FROM inmobiliario WHERE codigo LIKE ? ORDER BY codigo DESC LIMIT 1');
            $st->execute(["INM-{$prefijo}-%"]);
            $ultimo    = $st->fetchColumn();
            $siguiente = 1;
            if ($ultimo) { preg_match('/-(\\d+)$/', $ultimo, $m); $siguiente = ((int)($m[1] ?? 0)) + 1; }
            $codigo = 'INM-' . $prefijo . '-' . str_pad($siguiente, 3, '0', STR_PAD_LEFT);

            $imagen_url = guardarFoto($body['foto_base64'] ?? null, 'inm_' . $prefijo);

            $st = $pdo->prepare('INSERT INTO inmobiliario (codigo,nombre,descripcion,categoria_id,ubicacion,cantidad,estado,imagen_url,created_by) VALUES (?,?,?,?,?,?,?,?,?)');
            $st->execute([$codigo, $nombre, $desc, $cat_id, $ubicacion, $cantidad, $estado, $imagen_url, $usuario_id]);

            $inmo_id = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO movimientos_inventario (tipo_item,item_id,tipo_movimiento,cantidad,motivo,usuario_id) VALUES ("inmobiliario",?,"entrada",?,"Alta de inmobiliario",?)')
                ->execute([$inmo_id, $cantidad, $usuario_id]);

            echo json_encode(['id' => $inmo_id, 'codigo' => $codigo]);
            break;

        case 'editar_inmobiliario':
            $id        = (int)($body['id']   ?? 0);
            $nombre    = trim($body['nombre'] ?? '');
            $desc      = trim($body['descripcion'] ?? '');
            $cat_id    = ($body['categoria_id'] !== '' && $body['categoria_id'] !== null) ? (int)$body['categoria_id'] : null;
            $ubicacion = trim($body['ubicacion'] ?? '');
            $cantidad  = max(1, (int)($body['cantidad'] ?? 1));
            $estado    = $body['estado'] ?? 'activo';

            if (!$id)     throw new Exception('ID requerido.');
            if (!$nombre) throw new Exception('El nombre es obligatorio.');
            if (!$cat_id) throw new Exception('La categoría es obligatoria.');

            $nueva_img = guardarFoto($body['foto_base64'] ?? null, 'inm');
            $img_sql   = $nueva_img ? ', imagen_url=?' : '';
            $params    = [$nombre, $desc, $cat_id, $ubicacion, $cantidad, $estado];
            if ($nueva_img) $params[] = $nueva_img;
            $params[] = $id;

            $pdo->prepare("UPDATE inmobiliario SET nombre=?,descripcion=?,categoria_id=?,ubicacion=?,cantidad=?,estado=?{$img_sql} WHERE id=?")
                ->execute($params);

            echo json_encode(['ok' => true]);
            break;

        case 'eliminar_inmobiliario':
            $id = (int)($body['id'] ?? 0);
            if (!$id) throw new Exception('ID requerido.');
            $pdo->prepare('DELETE FROM movimientos_inventario WHERE tipo_item="inmobiliario" AND item_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM inmobiliario WHERE id=?')->execute([$id]);
            echo json_encode(['ok' => true]);
            break;

        case 'agregar_marca':
            $nombre = strtoupper(trim($body['nombre'] ?? ''));
            if (!$nombre) throw new Exception('El nombre de la marca es obligatorio.');
            $st = $pdo->prepare('INSERT INTO marcas_equipos (nombre) VALUES (?)');
            $st->execute([$nombre]);
            echo json_encode(['id' => (int)$pdo->lastInsertId(), 'nombre' => $nombre]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no reconocida: ' . htmlspecialchars($action)]);
    }

} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()]);
}