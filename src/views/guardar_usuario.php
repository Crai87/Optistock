<?php
/**
 * guardar_usuario.php
 * Endpoint POST — crear usuario con permisos desde el panel admin.
 */

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// 1. Autenticación
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autenticado.']);
    exit;
}

// 2. Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
    exit;
}

// 3. Leer y validar campos
$nombre           = trim($_POST['nombre']           ?? '');
$apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
$apellido_materno = trim($_POST['apellido_materno'] ?? '');
$username         = trim($_POST['username']         ?? '');
$password         =      $_POST['password']         ?? '';
$permisos         = $_POST['permisos']              ?? [];

if (!$nombre || !$apellido_paterno || !$username || !$password) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Nombre, apellido paterno, usuario y contraseña son obligatorios.']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'La contraseña debe tener al menos 8 caracteres.']);
    exit;
}

// 4. Verificar que el username no esté tomado
$check = $pdo->prepare("SELECT id FROM usuarios WHERE username = :u");
$check->execute(['u' => $username]);
if ($check->fetch()) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'msg' => "El usuario \"$username\" ya existe."]);
    exit;
}

// 5. Obtener rol "invitado"
$stmtRol = $pdo->prepare("SELECT id FROM roles WHERE nombre = 'invitado'");
$stmtRol->execute();
$rol = $stmtRol->fetch();

if (!$rol) {
    $pdo->exec("INSERT INTO roles (nombre) VALUES ('invitado')");
    $rolId = (int) $pdo->lastInsertId();
} else {
    $rolId = (int) $rol['id'];
}

// 6. Verificar que el creado_por exista en la BD
$creadoPor = null;
if (!empty($_SESSION['usuario_id'])) {
    $chkAdmin = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id");
    $chkAdmin->execute(['id' => $_SESSION['usuario_id']]);
    if ($chkAdmin->fetch()) {
        $creadoPor = (int) $_SESSION['usuario_id'];
    }
}

// 7. Insertar usuario
try {
    $pdo->beginTransaction();

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $ins = $pdo->prepare("
        INSERT INTO usuarios (username, password_hash, nombre, apellido_paterno, apellido_materno, rol_id, activo, creado_por)
        VALUES (:u, :h, :n, :ap, :am, :r, 1, :cp)
    ");
    $ins->execute([
        'u'  => $username,
        'h'  => $hash,
        'n'  => $nombre,
        'ap' => $apellido_paterno,
        'am' => $apellido_materno,
        'r'  => $rolId,
        'cp' => $creadoPor,
    ]);
    $nuevoId = (int) $pdo->lastInsertId();

    // 8. Insertar permisos seleccionados
    if (!empty($permisos)) {
        $insPerm = $pdo->prepare("
            INSERT IGNORE INTO usuario_permisos (usuario_id, permiso_id)
            VALUES (:uid, :pid)
        ");
        foreach ($permisos as $pid) {
            $pid = (int) $pid;
            if ($pid > 0) {
                $insPerm->execute(['uid' => $nuevoId, 'pid' => $pid]);
            }
        }
    }

    $pdo->commit();

    $nombre_completo = trim($nombre . ' ' . $apellido_paterno . ' ' . $apellido_materno);

    echo json_encode([
        'ok'      => true,
        'msg'     => 'Usuario creado correctamente.',
        'usuario' => [
            'id'     => $nuevoId,
            'nombre' => $nombre_completo,
            'alias'  => $username,
        ]
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al guardar: ' . $e->getMessage()]);
}